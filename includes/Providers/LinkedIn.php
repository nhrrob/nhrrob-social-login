<?php
/**
 * Sign In with LinkedIn using OpenID Connect. The identity comes from
 * LinkedIn's userinfo endpoint, which says whether the email is verified.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

use NHRRob\SocialLogin\Jwt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LinkedIn Class
 *
 * The picture is not kept: LinkedIn's picture addresses expire.
 */
class LinkedIn extends Provider {

	const AUTH_URL     = 'https://www.linkedin.com/oauth/v2/authorization';
	const TOKEN_URL    = 'https://www.linkedin.com/oauth/v2/accessToken';
	const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'linkedin';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'LinkedIn';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://www.linkedin.com/developers/apps';
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
					'response_type' => 'code',
					'client_id'     => $this->value( 'client_id' ),
					'redirect_uri'  => $this->redirect_uri(),
					'scope'         => 'openid profile email',
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
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'client_id'     => $this->value( 'client_id' ),
					'client_secret' => $this->value( 'client_secret' ),
					'redirect_uri'  => $this->redirect_uri(),
				]
			)
		);

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$info = $this->get_json( self::USERINFO_URL, $token );

		return is_wp_error( $info ) ? $info : self::from_userinfo( $info );
	}

	/**
	 * The identity in a userinfo answer. Pure.
	 *
	 * @param array $info Userinfo.
	 * @return array|\WP_Error
	 */
	public static function from_userinfo( array $info ) {
		return self::make_identity(
			isset( $info['sub'] ) ? (string) $info['sub'] : '',
			isset( $info['email'] ) ? $info['email'] : '',
			Jwt::is_true( $info, 'email_verified' ),
			isset( $info['name'] ) ? $info['name'] : '',
			isset( $info['given_name'] ) ? $info['given_name'] : ''
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24"><rect width="24" height="24" rx="3" fill="#0A66C2"/><path fill="#fff" d="M5.5 9.2h2.8V18H5.5zM6.9 5a1.6 1.6 0 110 3.2 1.6 1.6 0 010-3.2zM10.1 9.2h2.7v1.2c.4-.7 1.3-1.4 2.7-1.4 2.9 0 3.4 1.9 3.4 4.3V18h-2.8v-4.2c0-1 0-2.3-1.4-2.3s-1.7 1.1-1.7 2.2V18h-2.8z"/></svg>';
	}
}
