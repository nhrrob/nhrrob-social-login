<?php
/**
 * Facebook Login (OAuth 2.0). Facebook only returns an email address the
 * person has confirmed, and none when there is no confirmed one.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facebook Class
 *
 * The picture is not kept: Facebook's picture addresses expire.
 */
class Facebook extends Provider {

	const GRAPH     = 'v23.0';
	const AUTH_URL  = 'https://www.facebook.com/' . self::GRAPH . '/dialog/oauth';
	const TOKEN_URL = 'https://graph.facebook.com/' . self::GRAPH . '/oauth/access_token';
	const ME_URL    = 'https://graph.facebook.com/' . self::GRAPH . '/me?fields=id,name,first_name,email';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'facebook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Facebook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://developers.facebook.com/apps/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return [
			'client_id'     => [
				'label'  => __( 'App ID', 'nhrrob-social-login' ),
				'secret' => false,
			],
			'client_secret' => [
				'label'  => __( 'App secret', 'nhrrob-social-login' ),
				'secret' => true,
			],
		];
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
					'client_id'     => $this->value( 'client_id' ),
					'redirect_uri'  => $this->redirect_uri(),
					'response_type' => 'code',
					'scope'         => 'public_profile,email',
					'state'         => $state,
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
					'client_id'     => $this->value( 'client_id' ),
					'client_secret' => $this->value( 'client_secret' ),
					'code'          => $code,
					'redirect_uri'  => $this->redirect_uri(),
				]
			)
		);

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$me = $this->get_json( self::ME_URL . '&appsecret_proof=' . hash_hmac( 'sha256', $token, $this->value( 'client_secret' ) ), $token );

		if ( is_wp_error( $me ) ) {
			return $me;
		}

		$email = isset( $me['email'] ) ? (string) $me['email'] : '';

		return self::make_identity(
			isset( $me['id'] ) ? (string) $me['id'] : '',
			$email,
			'' !== $email,
			isset( $me['name'] ) ? $me['name'] : '',
			isset( $me['first_name'] ) ? $me['first_name'] : ''
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24"><circle fill="#fff" cx="12" cy="12" r="12"/><path fill="#1877F2" d="M24 12a12 12 0 10-13.88 11.85v-8.38H7.08V12h3.04V9.36c0-3 1.79-4.67 4.53-4.67 1.31 0 2.68.24 2.68.24v2.95h-1.51c-1.49 0-1.95.93-1.95 1.87V12h3.33l-.53 3.47h-2.8v8.38A12 12 0 0024 12z"/></svg>';
	}
}
