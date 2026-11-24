<?php
/**
 * The "Continue with Google" buttons, and the sign-in they start: off to
 * Google and back through admin-post.php, then logged in.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login Class
 *
 * One admin-post action does both halves: without `code` it sends the
 * person to Google, with `code` it finishes the sign-in. admin-post.php is
 * used because it answers logged-out visitors on every site, including one
 * whose login page has been moved.
 *
 * A sign-in in progress is a transient named after the hash of a random
 * value kept in a short-lived cookie. Google returns the same value as
 * `state`, so an answer is accepted only in the browser that started it.
 */
class Login {

	const ACTION   = 'nhrrob_social_login';
	const COOKIE   = 'nhrrob_social_login_state';
	const META_SUB = 'nhrrob_social_login_google_sub';

	/**
	 * Seconds a sign-in may take.
	 */
	const TTL = 600;

	/**
	 * What `nhrrob_social_login_button_extra` may print.
	 */
	const EXTRA_HTML = [
		'label' => [
			'class' => true,
			'for'   => true,
		],
		'input' => [
			'type'  => true,
			'class' => true,
			'id'    => true,
			'name'  => true,
			'value' => true,
		],
		'span'  => [ 'class' => true ],
	];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'login_form', [ $this, 'login_button' ] );
		add_action( 'comment_form_top', [ $this, 'comment_button' ] );
		add_filter( 'login_message', [ $this, 'error_message' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'styles' ] );
	}

	/**
	 * The button on the login form, returning to where the form would have.
	 *
	 * @return void
	 */
	public function login_button() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only read to send the person back there.
		$to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

		$this->button( $to, __( 'Continue with Google', 'nhrrob-social-login' ) );
	}

	/**
	 * The button above the comment fields, for a visitor who is not logged in.
	 * It brings them back to the form, logged in.
	 *
	 * @return void
	 */
	public function comment_button() {
		if ( is_user_logged_in() || ! Settings::create_accounts() ) {
			return;
		}

		$this->button( get_permalink() . '#respond', __( 'Continue with Google to comment', 'nhrrob-social-login' ) );
	}

	/**
	 * Print a button.
	 *
	 * @param string $redirect_to Where to go after signing in.
	 * @param string $label       Button text.
	 * @return void
	 */
	private function button( $redirect_to, $label ) {
		if ( ! Settings::google_ready() ) {
			return;
		}

		$url = add_query_arg(
			array_filter(
				[
					'action'      => self::ACTION,
					'redirect_to' => rawurlencode( $redirect_to ),
				]
			),
			admin_url( 'admin-post.php' )
		);

		/**
		 * Extra markup after the button, inside its paragraph: an add-on's
		 * checkbox, for example. Only labels, checkboxes and spans are kept.
		 *
		 * @param string $html        Markup, empty by default.
		 * @param string $redirect_to Where the button sends the person after signing in.
		 */
		$extra = (string) apply_filters( 'nhrrob_social_login_button_extra', '', $redirect_to );

		printf(
			'<p class="nhrrob-social-login"><a class="nhrrob-social-login__google" href="%1$s">%2$s<span>%3$s</span></a>%5$s<span class="nhrrob-social-login__or">%4$s</span></p>',
			esc_url( $url ),
			'<svg aria-hidden="true" width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>',
			esc_html( $label ),
			esc_html__( 'or', 'nhrrob-social-login' ),
			wp_kses( $extra, self::EXTRA_HTML )
		);
	}

	/**
	 * Styles of the button, on the login page and on posts that take comments.
	 *
	 * @return void
	 */
	public function styles() {
		if ( ! Settings::google_ready() || ( 'wp_enqueue_scripts' === current_action() && ! ( is_singular() && comments_open() ) ) ) {
			return;
		}

		wp_enqueue_style( 'nhrrob-social-login', plugins_url( 'assets/login.css', NHRROB_SOCIAL_LOGIN_FILE ), [], NHRROB_SOCIAL_LOGIN_VERSION );

		if ( 'login_enqueue_scripts' === current_action() ) {
			// The login_form hook prints after the password; the script moves the button to the top of the form, above an "or".
			wp_enqueue_script( 'nhrrob-social-login', plugins_url( 'assets/login.js', NHRROB_SOCIAL_LOGIN_FILE ), [], NHRROB_SOCIAL_LOGIN_VERSION, true );
		}
	}

	/**
	 * Start or finish a sign-in.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! Settings::google_ready() ) {
			$this->fail( 'not_ready' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the OAuth state, checked below, does the job of a nonce.
		if ( isset( $_GET['error'] ) ) {
			$this->fail( 'cancelled' );
		}

		if ( ! isset( $_GET['code'], $_GET['state'] ) ) {
			$this->start( isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '' );
		}

		$this->finish( sanitize_text_field( wp_unslash( $_GET['code'] ) ), sanitize_text_field( wp_unslash( $_GET['state'] ) ) );
		// phpcs:enable
	}

	/**
	 * Send the person to Google.
	 *
	 * @param string $redirect_to Where to go after signing in.
	 * @return void
	 */
	private function start( $redirect_to ) {
		$state    = Google::random();
		$nonce    = Google::random();
		$verifier = Google::random();

		/**
		 * Data an add-on keeps for the length of a sign-in, read from the
		 * request that started it (a checkbox's choice, for example). It comes
		 * back as `data` in `nhrrob_social_login_signed_in`'s context.
		 *
		 * @param array  $data        Data, empty by default.
		 * @param string $redirect_to Where the person goes after signing in.
		 */
		$data = (array) apply_filters( 'nhrrob_social_login_start_data', [], $redirect_to );

		set_transient(
			self::key( $state ),
			[
				'nonce'       => $nonce,
				'verifier'    => $verifier,
				'redirect_to' => $redirect_to,
				'data'        => $data,
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
				// Lax still sends it on Google's top-level redirect back.
				'samesite' => 'Lax',
			]
		);

		wp_redirect( Google::auth_url( $state, $nonce, Google::challenge( $verifier ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google's sign-in page, a fixed address.
		exit;
	}

	/**
	 * Finish the sign-in Google answered.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State Google returned.
	 * @return void
	 */
	private function finish( $code, $state ) {
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$saved  = '' !== $state ? get_transient( self::key( $state ) ) : false;

		// Used once: a second answer with the same state finds nothing.
		delete_transient( self::key( $state ) );
		setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

		if ( ! is_array( $saved ) || '' === $cookie || ! hash_equals( $cookie, $state ) ) {
			$this->fail( 'expired' );
		}

		$identity = Google::identity( $code, $saved['verifier'], $saved['nonce'] );

		if ( is_wp_error( $identity ) ) {
			$this->fail( 'google' );
		}

		$created = false;
		$user    = Accounts::user_for( $identity, Settings::create_accounts(), $created );

		if ( is_wp_error( $user ) ) {
			$this->fail( $user->get_error_code() );
		}

		// Two-factor is left to Google, which already checked the account.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		/** This action is documented in wp-includes/user.php */
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own login event, so activity logs and login alerts see this sign-in.

		$fallback = user_can( $user, 'edit_posts' ) ? admin_url() : home_url( '/' );

		// wp_validate_redirect() returns an empty address as it is, not the fallback.
		$to = '' !== $saved['redirect_to'] ? wp_validate_redirect( $saved['redirect_to'], $fallback ) : $fallback;

		/**
		 * After someone signed in with a social account and was logged in.
		 *
		 * @param \WP_User $user    The user who signed in.
		 * @param array    $context `provider` ('google'), `created` (whether the account was made for this sign-in),
		 *                          `redirect_to` (where they are sent next: the post for a sign-in from a comment form)
		 *                          and `data` (what `nhrrob_social_login_start_data` kept when the sign-in started).
		 */
		do_action(
			'nhrrob_social_login_signed_in',
			$user,
			[
				'provider'    => 'google',
				'created'     => $created,
				'redirect_to' => $to,
				'data'        => isset( $saved['data'] ) && is_array( $saved['data'] ) ? $saved['data'] : [],
			]
		);

		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Send the person to the login page with a message.
	 *
	 * @param string $code Error code.
	 * @return void
	 */
	private function fail( $code ) {
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
			'no_account'   => __( 'There is no account with the email address of that Google account.', 'nhrrob-social-login' ),
			'linked_other' => __( 'The account with that email address is linked to a different Google account. Log in with your password instead.', 'nhrrob-social-login' ),
			'cancelled'    => __( 'Signing in with Google was cancelled.', 'nhrrob-social-login' ),
			'expired'      => __( 'That sign-in took too long or was started in another browser. Please try again.', 'nhrrob-social-login' ),
		];
		$text  = isset( $texts[ $code ] ) ? $texts[ $code ] : __( 'Signing in with Google did not work. Please try again.', 'nhrrob-social-login' );

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
