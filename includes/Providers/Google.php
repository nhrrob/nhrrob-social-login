<?php
/**
 * Google sign-in with OpenID Connect.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

use NHRRob\SocialLogin\Jwt;
use NHRRob\SocialLogin\Login;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Class
 *
 * The only outside requests are to accounts.google.com (in the person's
 * browser) and oauth2.googleapis.com (from the server, to exchange the code).
 * The ID token comes straight from Google's token endpoint over TLS, so its
 * claims are checked but its signature is not, as OpenID Connect Core
 * 3.1.3.7 allows for a token received that way. One Tap's token comes
 * through the browser, so OneTap checks its signature.
 */
class Google extends Provider {

	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const ISSUERS   = [ 'accounts.google.com', 'https://accounts.google.com' ];

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'google';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Google';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://console.cloud.google.com/apis/credentials';
	}

	/**
	 * Google's address back stays the one it had before there were other providers.
	 *
	 * @return string
	 */
	public function redirect_uri() {
		return admin_url( 'admin-post.php?action=' . Login::ACTION );
	}

	/**
	 * The meta key Google links have used since 0.1.0.
	 *
	 * @return string
	 */
	public function meta_key() {
		return Login::META_SUB;
	}

	/**
	 * The address that sends the person to the provider.
	 *
	 * @param string $state     Value returned unchanged.
	 * @param string $nonce     Value put into the ID token, where the provider takes one.
	 * @param string $challenge PKCE code challenge, where the provider takes one.
	 * @return string
	 */
	public function auth_url( $state, $nonce, $challenge ) {
		return add_query_arg(
			rawurlencode_deep(
				[
					'client_id'             => $this->value( 'client_id' ),
					'redirect_uri'          => $this->redirect_uri(),
					'response_type'         => 'code',
					'scope'                 => 'openid email profile',
					'state'                 => $state,
					'nonce'                 => $nonce,
					'code_challenge'        => $challenge,
					'code_challenge_method' => 'S256',
					'prompt'                => 'select_account',
				]
			),
			self::AUTH_URL
		);
	}

	/**
	 * Exchange the code for the person's identity.
	 *
	 * @param string $code     Authorization code.
	 * @param string $verifier PKCE code verifier.
	 * @param string $nonce    The nonce sent with the sign-in.
	 * @return array|\WP_Error
	 */
	public function identity( $code, $verifier, $nonce ) {
		$answer = $this->post_json(
			self::TOKEN_URL,
			[
				'code'          => $code,
				'client_id'     => $this->value( 'client_id' ),
				'client_secret' => $this->value( 'client_secret' ),
				'redirect_uri'  => $this->redirect_uri(),
				'grant_type'    => 'authorization_code',
				'code_verifier' => $verifier,
			]
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		if ( empty( $answer['id_token'] ) ) {
			return new \WP_Error( 'nhrrob_social_login_http', __( 'Google did not confirm the sign-in. Please try again.', 'nhrrob-social-login' ) );
		}

		return self::check_claims( Jwt::claims( (string) $answer['id_token'] ), $this->value( 'client_id' ), $nonce, time() );
	}

	/**
	 * Check an ID token's claims, and return the identity they prove. Pure.
	 *
	 * @param array  $claims    Claims of the ID token.
	 * @param string $client_id This site's Google client ID.
	 * @param string $nonce     The nonce sent with the sign-in.
	 * @param int    $now       Current time.
	 * @return array|\WP_Error
	 */
	public static function check_claims( array $claims, $client_id, $nonce, $now ) {
		// Google always gets a nonce: an empty one would skip the check.
		if ( '' === (string) $nonce || ! Jwt::valid_claims( $claims, self::ISSUERS, $client_id, $nonce, $now ) ) {
			return new \WP_Error( 'nhrrob_social_login_token', __( 'The sign-in could not be confirmed. Please try again.', 'nhrrob-social-login' ) );
		}

		return self::make_identity(
			$claims['sub'],
			isset( $claims['email'] ) ? $claims['email'] : '',
			Jwt::is_true( $claims, 'email_verified' ),
			isset( $claims['name'] ) ? $claims['name'] : '',
			isset( $claims['given_name'] ) ? $claims['given_name'] : '',
			isset( $claims['picture'] ) ? $claims['picture'] : ''
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
	}
}
