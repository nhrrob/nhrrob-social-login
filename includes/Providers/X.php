<?php
/**
 * Sign in with X (OAuth 2.0 with PKCE). The email is X's confirmed address,
 * which needs the "Request email from users" setting on the X app.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * X Class
 */
class X extends Provider {

	const AUTH_URL  = 'https://x.com/i/oauth2/authorize';
	const TOKEN_URL = 'https://api.x.com/2/oauth2/token';
	const ME_URL    = 'https://api.x.com/2/users/me?user.fields=name,confirmed_email,profile_image_url';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'x';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'X';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://developer.x.com/en/portal/projects-and-apps';
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
					'response_type'         => 'code',
					'client_id'             => $this->value( 'client_id' ),
					'redirect_uri'          => $this->redirect_uri(),
					'scope'                 => 'users.read tweet.read users.email',
					'state'                 => $state,
					'code_challenge'        => $challenge,
					'code_challenge_method' => 'S256',
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
		$token = self::access_token(
			$this->post_json(
				self::TOKEN_URL,
				[
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->redirect_uri(),
					'code_verifier' => $verifier,
					'client_id'     => $this->value( 'client_id' ),
				],
				// A confidential client authenticates with Basic.
				[ 'Authorization' => 'Basic ' . base64_encode( $this->value( 'client_id' ) . ':' . $this->value( 'client_secret' ) ) ] // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication.
			)
		);

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$me = $this->get_json( self::ME_URL, $token );

		if ( is_wp_error( $me ) ) {
			return $me;
		}

		return self::from_user( isset( $me['data'] ) && is_array( $me['data'] ) ? $me['data'] : [] );
	}

	/**
	 * The identity in a /2/users/me answer's `data`. Pure.
	 *
	 * @param array $user User.
	 * @return array|\WP_Error
	 */
	public static function from_user( array $user ) {
		$email = isset( $user['confirmed_email'] ) ? (string) $user['confirmed_email'] : '';

		return self::make_identity(
			isset( $user['id'] ) ? (string) $user['id'] : '',
			$email,
			'' !== $email,
			isset( $user['name'] ) ? $user['name'] : '',
			'',
			isset( $user['profile_image_url'] ) ? $user['profile_image_url'] : ''
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.65l-5.21-6.82-5.97 6.82H1.68l7.73-8.84L1.25 2.25h6.83l4.71 6.23zm-1.16 17.52h1.83L7.08 4.13H5.12z"/></svg>';
	}
}
