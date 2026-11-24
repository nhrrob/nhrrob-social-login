<?php
/**
 * Microsoft sign-in with OpenID Connect, on the common endpoint: personal
 * accounts and work or school accounts.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

use NHRRob\SocialLogin\Jwt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Microsoft Class
 *
 * Microsoft does not say whether the `email` claim was verified. A personal
 * account's address is Microsoft's own, so it is taken. A work or school
 * account's address is whatever its organisation typed in, which anyone with
 * a tenant can set to someone else's address, so it is taken only with the
 * `xms_edov` claim ("email domain owner verified"), an optional claim the
 * site owner adds to the app registration.
 */
class Microsoft extends Provider {

	const AUTH_URL  = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
	const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

	/**
	 * The tenant of personal Microsoft accounts.
	 */
	const CONSUMERS = '9188040d-6c67-4c5b-b112-36a304b66dad';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'microsoft';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Microsoft';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade';
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
					'response_mode'         => 'query',
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
				'client_id'     => $this->value( 'client_id' ),
				'client_secret' => $this->value( 'client_secret' ),
				'code'          => $code,
				'redirect_uri'  => $this->redirect_uri(),
				'grant_type'    => 'authorization_code',
				'code_verifier' => $verifier,
				'scope'         => 'openid email profile',
			]
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		return self::check_claims( Jwt::claims( isset( $answer['id_token'] ) ? (string) $answer['id_token'] : '' ), $this->value( 'client_id' ), $nonce, time() );
	}

	/**
	 * Check an ID token's claims, and return the identity they prove. Pure.
	 *
	 * @param array  $claims    Claims.
	 * @param string $client_id This site's client ID.
	 * @param string $nonce     The nonce sent with the sign-in.
	 * @param int    $now       Current time.
	 * @return array|\WP_Error
	 */
	public static function check_claims( array $claims, $client_id, $nonce, $now ) {
		$tid    = isset( $claims['tid'] ) ? (string) $claims['tid'] : '';
		$issuer = 'https://login.microsoftonline.com/' . $tid . '/v2.0';

		if ( '' === $tid || '' === (string) $nonce || ! Jwt::valid_claims( $claims, [ $issuer ], $client_id, $nonce, $now ) ) {
			return new \WP_Error( 'nhrrob_social_login_token', __( 'The sign-in could not be confirmed. Please try again.', 'nhrrob-social-login' ) );
		}

		$email = isset( $claims['email'] ) ? $claims['email'] : '';

		if ( '' === $email && self::CONSUMERS === $tid && isset( $claims['preferred_username'] ) ) {
			$email = $claims['preferred_username'];
		}

		return self::make_identity(
			// Unique to this app and person, and never reused.
			$claims['sub'],
			$email,
			self::CONSUMERS === $tid || Jwt::is_true( $claims, 'xms_edov' ),
			isset( $claims['name'] ) ? $claims['name'] : ''
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 22 22"><path fill="#F25022" d="M0 0h10.5v10.5H0z"/><path fill="#7FBA00" d="M11.5 0H22v10.5H11.5z"/><path fill="#00A4EF" d="M0 11.5h10.5V22H0z"/><path fill="#FFB900" d="M11.5 11.5H22V22H11.5z"/></svg>';
	}
}
