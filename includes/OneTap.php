<?php
/**
 * Google One Tap: an optional sign-in prompt for logged-out visitors, on
 * posts or on every page, through Google Identity Services.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OneTap Class
 *
 * The prompt hands an ID token to assets/onetap.js, which posts it to
 * admin-post.php?action=nhrrob_social_login_onetap. That token passed
 * through the browser, so unlike the code flow its signature is checked,
 * with Google's published keys (cached as long as Google says). Its nonce is
 * a random value the script keeps in a cookie, so a token is accepted only
 * in the browser that asked for it, and pages can still be cached.
 */
class OneTap {

	const ACTION = 'nhrrob_social_login_onetap';
	const COOKIE = 'nhrrob_social_login_onetap';
	const CERTS  = 'https://www.googleapis.com/oauth2/v3/certs';
	const KEYS   = 'nhrrob_social_login_google_keys';
	const GIS    = 'https://accounts.google.com/gsi/client';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Whether the prompt shows on this page.
	 *
	 * @return bool
	 */
	public static function shows_here() {
		$where  = Settings::value( 'one_tap' );
		$google = Providers::get( 'google' );

		if ( 'off' === $where || is_user_logged_in() || ! $google || ! $google->active() ) {
			return false;
		}

		return 'site' === $where || is_singular( 'post' );
	}

	/**
	 * Google's script and ours.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::shows_here() ) {
			return;
		}

		wp_enqueue_script( 'nhrrob-social-login-gis', self::GIS, [], null, [ 'strategy' => 'async' ] ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google's script, served by Google; a version would only break its cache.
		wp_enqueue_script( 'nhrrob-social-login-onetap', plugins_url( 'assets/onetap.js', NHRROB_SOCIAL_LOGIN_FILE ), [], NHRROB_SOCIAL_LOGIN_VERSION, [ 'strategy' => 'defer' ] );
		wp_localize_script(
			'nhrrob-social-login-onetap',
			'nhrrobSocialLoginOneTap',
			[
				'clientId' => Settings::credential( 'google', 'client_id' ),
				'action'   => admin_url( 'admin-post.php' ),
				'cookie'   => self::COOKIE,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
			]
		);
	}

	/**
	 * Sign in with the token the prompt returned.
	 *
	 * @return void
	 */
	public function handle() {
		$google = Providers::get( 'google' );

		if ( 'off' === Settings::value( 'one_tap' ) || ! $google || ! $google->active() ) {
			Login::fail( 'not_ready' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the token's nonce, compared with the cookie below, does the job of a nonce.
		$token = isset( $_POST['credential'] ) ? sanitize_text_field( wp_unslash( $_POST['credential'] ) ) : '';
		$to    = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		// phpcs:enable
		$nonce = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';

		setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );

		if ( strlen( $nonce ) < 20 || ! Jwt::verify_rs256( $token, self::keys( $token ) ) ) {
			Login::fail( 'expired' );
		}

		$identity = Providers\Google::check_claims( Jwt::claims( $token ), Settings::credential( 'google', 'client_id' ), $nonce, time() );

		if ( is_wp_error( $identity ) ) {
			Login::fail( 'nhrrob_social_login_email' === $identity->get_error_code() ? 'unverified' : 'expired' );
		}

		$created = false;
		$user    = Accounts::user_for( $identity, $google->meta_key(), Settings::create_accounts(), $created, Accounts::new_role( 'onetap' ) );

		if ( is_wp_error( $user ) ) {
			Login::fail( $user->get_error_code() );
		}

		Login::log_in( $user, 'google', $created, $to, 'onetap' );
	}

	/**
	 * Google's signing keys, cached; fetched again once for a key not in the cache.
	 *
	 * @param string $token The token, for its key ID.
	 * @return array
	 */
	private static function keys( $token ) {
		$header = Jwt::part( $token, 0 );
		$kid    = isset( $header['kid'] ) ? (string) $header['kid'] : '';
		$keys   = get_transient( self::KEYS );

		if ( is_array( $keys ) && in_array( $kid, wp_list_pluck( $keys, 'kid' ), true ) ) {
			return $keys;
		}

		$response = wp_remote_get( self::CERTS, [ 'timeout' => 10 ] );
		$body     = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $body['keys'] ) || ! is_array( $body['keys'] ) ) {
			return [];
		}

		preg_match( '/max-age=(\d+)/', (string) wp_remote_retrieve_header( $response, 'cache-control' ), $age );
		set_transient( self::KEYS, $body['keys'], isset( $age[1] ) ? min( (int) $age[1], DAY_IN_SECONDS ) : HOUR_IN_SECONDS );

		return $body['keys'];
	}
}
