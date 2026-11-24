<?php
/**
 * The sign-in a button starts: off to the provider and back, then logged in
 * (or, from the profile screen, the account linked).
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

use NHRRob\SocialLogin\Providers\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login Class
 *
 * The address admin-post.php?action=nhrrob_social_login starts every
 * sign-in, and is also where Google answers. The other providers answer at Callback::url().
 * admin-post.php is used because it answers logged-out visitors on every
 * site, including one whose login page has been moved.
 *
 * A sign-in in progress is a transient named after the hash of a random
 * value kept in a short-lived cookie. The provider returns the same value as
 * `state`, so an answer is accepted only in the browser that started it.
 */
class Login {

	const ACTION   = 'nhrrob_social_login';
	const COOKIE   = 'nhrrob_social_login_state';
	const META_SUB = 'nhrrob_social_login_google_sub';

	/**
	 * Where a sign-in can start.
	 */
	const SOURCES = [ 'login', 'comment', 'button', 'woocommerce', 'profile' ];

	/**
	 * Seconds a sign-in may take.
	 */
	const TTL = 600;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		add_filter( 'login_message', [ $this, 'error_message' ] );
	}

	/**
	 * The address that starts a sign-in.
	 *
	 * @param string $provider    Provider ID.
	 * @param string $redirect_to Where to go after signing in.
	 * @param string $source      Where it starts, one of SOURCES.
	 * @return string
	 */
	public static function start_url( $provider, $redirect_to, $source ) {
		$args = [
			'action'      => self::ACTION,
			'provider'    => $provider,
			'source'      => $source,
			'redirect_to' => rawurlencode( $redirect_to ),
		];

		if ( 'profile' === $source ) {
			$args['_wpnonce'] = wp_create_nonce( self::ACTION . '_link_' . $provider );
		}

		return add_query_arg( array_filter( $args ), admin_url( 'admin-post.php' ) );
	}

	/**
	 * Start a sign-in, or finish Google's.
	 *
	 * @return void
	 */
	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the OAuth state, checked in finish(), does the job of a nonce; linking checks one in start().
		if ( isset( $_GET['code'] ) || isset( $_GET['state'] ) || isset( $_GET['error'] ) ) {
			$this->callback( 'google' );
		}

		$provider = Providers::get( isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : 'google' );
		$source   = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : 'login';
		$to       = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		// phpcs:enable

		if ( ! $provider || ! $provider->active() ) {
			self::fail( 'not_ready' );
		}

		$this->start( $provider, $to, in_array( $source, self::SOURCES, true ) ? $source : 'login' );
	}

	/**
	 * Send the person to the provider.
	 *
	 * @param Provider $provider    Provider.
	 * @param string   $redirect_to Where to go after signing in.
	 * @param string   $source      Where it started.
	 * @return void
	 */
	private function start( Provider $provider, $redirect_to, $source ) {
		$link_user = 0;

		if ( 'profile' === $source ) {
			check_admin_referer( self::ACTION . '_link_' . $provider->id() );
			$link_user = get_current_user_id();

			if ( ! $link_user ) {
				self::fail( 'expired' );
			}
		}

		$state    = Jwt::random();
		$nonce    = Jwt::random();
		$verifier = Jwt::random();

		set_transient(
			self::key( $state ),
			[
				'provider'    => $provider->id(),
				'nonce'       => $nonce,
				'verifier'    => $verifier,
				'redirect_to' => $redirect_to,
				'source'      => $source,
				'link_user'   => $link_user,
			],
			self::TTL
		);

		setcookie(
			self::COOKIE,
			$state,
			[
				'expires'  => time() + self::TTL,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				// Lax still sends it on the provider's top-level redirect back.
				'samesite' => 'Lax',
			]
		);

		wp_redirect( $provider->auth_url( $state, $nonce, Jwt::challenge( $verifier ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the provider's sign-in page, a fixed address.
		exit;
	}

	/**
	 * A provider sent the person back.
	 *
	 * @param string $id Provider ID.
	 * @return void
	 */
	public function callback( $id ) {
		$provider = Providers::get( $id );

		if ( ! $provider || ! $provider->active() ) {
			self::fail( 'not_ready' );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- the OAuth state, checked in finish(), does the job of a nonce.
		if ( $provider->posts_back() && isset( $_POST['state'] ) ) {
			$this->posted_back( $provider );
		}

		if ( isset( $_GET['error'] ) ) {
			self::fail( 'cancelled' );
		}

		if ( ! isset( $_GET['code'], $_GET['state'] ) ) {
			self::fail( 'expired' );
		}

		$this->finish( $provider, sanitize_text_field( wp_unslash( $_GET['code'] ) ), sanitize_text_field( wp_unslash( $_GET['state'] ) ) );
		// phpcs:enable
	}

	/**
	 * Apple answers with a cross-site POST, which leaves out the SameSite=Lax
	 * state cookie. Keep the name Apple sends only this once with the sign-in
	 * in progress, and come back here with a GET, which carries the cookie.
	 *
	 * @param Provider $provider Provider.
	 * @return void
	 */
	private function posted_back( Provider $provider ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the OAuth state does the job of a nonce, and is checked with the cookie in finish().
		$state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';

		if ( isset( $_POST['error'] ) || ! isset( $_POST['code'] ) ) {
			self::fail( 'cancelled' );
		}

		$saved = '' !== $state ? get_transient( self::key( $state ) ) : false;

		if ( is_array( $saved ) && isset( $_POST['user'] ) ) {
			$user = json_decode( sanitize_text_field( wp_unslash( $_POST['user'] ) ), true );

			if ( isset( $user['name'] ) && is_array( $user['name'] ) ) {
				$saved['given_name'] = isset( $user['name']['firstName'] ) ? sanitize_text_field( (string) $user['name']['firstName'] ) : '';
				$saved['name']       = trim( $saved['given_name'] . ' ' . ( isset( $user['name']['lastName'] ) ? sanitize_text_field( (string) $user['name']['lastName'] ) : '' ) );
				set_transient( self::key( $state ), $saved, self::TTL );
			}
		}

		$code = sanitize_text_field( wp_unslash( $_POST['code'] ) );
		// phpcs:enable

		wp_safe_redirect(
			add_query_arg(
				rawurlencode_deep(
					[
						'code'  => $code,
						'state' => $state,
					]
				),
				$provider->redirect_uri()
			),
			303
		);
		exit;
	}

	/**
	 * Finish the sign-in the provider answered.
	 *
	 * @param Provider $provider Provider.
	 * @param string   $code     Authorization code.
	 * @param string   $state    State the provider returned.
	 * @return void
	 */
	private function finish( Provider $provider, $code, $state ) {
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$saved  = '' !== $state ? get_transient( self::key( $state ) ) : false;

		// Used once: a second answer with the same state finds nothing.
		delete_transient( self::key( $state ) );
		setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

		if ( ! is_array( $saved ) || '' === $cookie || ! hash_equals( $cookie, $state ) || ! isset( $saved['provider'] ) || $provider->id() !== $saved['provider'] ) {
			self::fail( 'expired' );
		}

		$identity = $provider->identity( $code, $saved['verifier'], $saved['nonce'] );

		if ( is_wp_error( $identity ) ) {
			self::fail( 'nhrrob_social_login_email' === $identity->get_error_code() ? 'unverified' : 'provider' );
		}

		foreach ( [ 'name', 'given_name' ] as $field ) {
			if ( '' === $identity[ $field ] && ! empty( $saved[ $field ] ) ) {
				$identity[ $field ] = $saved[ $field ];
			}
		}

		if ( ! empty( $saved['link_user'] ) ) {
			Profile::link( (int) $saved['link_user'], $provider, $identity );
		}

		$created = false;
		$user    = Accounts::user_for( $identity, $provider->meta_key(), Settings::create_accounts(), $created, Accounts::new_role( $saved['source'] ) );

		if ( is_wp_error( $user ) ) {
			self::fail( $user->get_error_code() );
		}

		self::log_in( $user, $provider->id(), $created, $saved['redirect_to'], $saved['source'] );
	}

	/**
	 * Log a user in and send them on. Every way in ends here: a provider,
	 * One Tap and a sign-in link by email.
	 *
	 * @param \WP_User $user        User.
	 * @param string   $provider    Provider ID, or 'email'.
	 * @param bool     $created     Whether the account was made for this sign-in.
	 * @param string   $redirect_to Where the sign-in asked to go.
	 * @param string   $source      Where it started.
	 * @return void
	 */
	public static function log_in( $user, $provider, $created, $redirect_to, $source ) {
		// Two-factor is left to the provider, which already checked the account.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		/** This action is documented in wp-includes/user.php */
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own login event, so activity logs and login alerts see this sign-in.

		$to = self::destination( $user, $created, $redirect_to, $source );

		/**
		 * After someone signed in with a social account and was logged in.
		 *
		 * @param \WP_User $user    The user who signed in.
		 * @param array    $context `provider` (such as 'google', or 'email' for a sign-in link),
		 *                          `created` (whether the account was made for this sign-in),
		 *                          `redirect_to` (where they are sent next: the post for a sign-in from a comment form)
		 *                          and `source` (login, comment, button, woocommerce, onetap or email).
		 */
		do_action(
			'nhrrob_social_login_signed_in',
			$user,
			[
				'provider'    => $provider,
				'created'     => $created,
				'redirect_to' => $to,
				'source'      => $source,
			]
		);

		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Where a user goes after signing in. From the login form, a new account
	 * goes to the sign-up address, and anyone else who was not sent to a
	 * particular page goes to the login address, when those are set.
	 * Everywhere else, back where the sign-in started, such as the post for
	 * a comment form.
	 *
	 * @param \WP_User $user        User.
	 * @param bool     $created     Whether the account is new.
	 * @param string   $redirect_to Where the sign-in asked to go.
	 * @param string   $source      Where it started.
	 * @return string
	 */
	public static function destination( $user, $created, $redirect_to, $source ) {
		$fallback = user_can( $user, 'edit_posts' ) ? admin_url() : home_url( '/' );

		if ( 'login' === $source ) {
			$signup = (string) Settings::value( 'signup_redirect' );
			$login  = (string) Settings::value( 'login_redirect' );

			if ( $created && '' !== $signup ) {
				$redirect_to = $signup;
			} elseif ( ( '' === $redirect_to || admin_url() === $redirect_to ) && '' !== $login ) {
				$redirect_to = $login;
			}
		}

		// wp_validate_redirect() returns an empty address as it is, not the fallback.
		return '' !== $redirect_to ? wp_validate_redirect( $redirect_to, $fallback ) : $fallback;
	}

	/**
	 * Send the person to the login page with a message.
	 *
	 * @param string $code Error code.
	 * @return void
	 */
	public static function fail( $code ) {
		wp_safe_redirect( add_query_arg( self::ACTION . '_error', rawurlencode( $code ), wp_login_url() ) );
		exit;
	}

	/**
	 * The message on the login page after a failed sign-in.
	 *
	 * @param string $message Existing message.
	 * @return string
	 */
	public function error_message( $message ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks one of the fixed messages below.
		$code = isset( $_GET[ self::ACTION . '_error' ] ) ? sanitize_key( wp_unslash( $_GET[ self::ACTION . '_error' ] ) ) : '';

		if ( '' === $code ) {
			return $message;
		}

		$texts = [
			'no_account'   => __( 'There is no account with the email address of that account.', 'nhrrob-social-login' ),
			'linked_other' => __( 'The account with that email address is linked to a different account at that provider. Log in another way instead.', 'nhrrob-social-login' ),
			'cancelled'    => __( 'Signing in was cancelled.', 'nhrrob-social-login' ),
			'expired'      => __( 'That sign-in took too long or was started in another browser. Please try again.', 'nhrrob-social-login' ),
			'unverified'   => __( 'That account has no verified email address, so it cannot sign in here.', 'nhrrob-social-login' ),
			'domain'       => __( 'Only email addresses from this site\'s allowed domains can sign in here.', 'nhrrob-social-login' ),
			'link_used'    => __( 'That sign-in link has expired or was already used. Ask for a new one.', 'nhrrob-social-login' ),
		];
		$text  = isset( $texts[ $code ] ) ? $texts[ $code ] : __( 'Signing in did not work. Please try again.', 'nhrrob-social-login' );

		return $message . '<div id="login_error" class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Transient name of a sign-in in progress.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function key( $state ) {
		return 'nhrrob_social_login_' . hash( 'sha256', $state );
	}
}
