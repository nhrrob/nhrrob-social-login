<?php
/**
 * Sign in with Apple. Apple answers with a POST (form_post) and its client
 * secret is a short-lived JWT signed with the site's private key.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

use NHRRob\SocialLogin\Jwt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apple Class
 *
 * Apple sends the person's name only the first time, in the POST; Login
 * keeps it with the sign-in in progress. The address may be one of Apple's
 * private relay addresses, which Apple counts as verified.
 */
class Apple extends Provider {

	const AUTH_URL  = 'https://appleid.apple.com/auth/authorize';
	const TOKEN_URL = 'https://appleid.apple.com/auth/token';
	const ISSUER    = 'https://appleid.apple.com';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'apple';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Apple';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://developer.apple.com/account/resources/identifiers/list/serviceId';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return [
			'client_id'   => [
				'label'  => __( 'Services ID', 'nhrrob-social-login' ),
				'secret' => false,
			],
			'team_id'     => [
				'label'  => __( 'Team ID', 'nhrrob-social-login' ),
				'secret' => false,
			],
			'key_id'      => [
				'label'  => __( 'Key ID', 'nhrrob-social-login' ),
				'secret' => false,
			],
			'private_key' => [
				'label'     => __( 'Private key (.p8)', 'nhrrob-social-login' ),
				'secret'    => true,
				'multiline' => true,
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function posts_back() {
		return true;
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
					'response_mode' => 'form_post',
					'client_id'     => $this->value( 'client_id' ),
					'redirect_uri'  => $this->redirect_uri(),
					'scope'         => 'name email',
					'state'         => $state,
					'nonce'         => $nonce,
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
		$secret = $this->client_secret( time() );

		if ( '' === $secret ) {
			return new \WP_Error( 'nhrrob_social_login_key', __( 'The Apple private key could not be read.', 'nhrrob-social-login' ) );
		}

		$answer = $this->post_json(
			self::TOKEN_URL,
			[
				'client_id'     => $this->value( 'client_id' ),
				'client_secret' => $secret,
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $this->redirect_uri(),
			]
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		return self::check_claims( Jwt::claims( isset( $answer['id_token'] ) ? (string) $answer['id_token'] : '' ), $this->value( 'client_id' ), $nonce, time() );
	}

	/**
	 * The client secret: a JWT, valid for five minutes, signed with the private key.
	 *
	 * @param int $now Current time.
	 * @return string '' when the key cannot sign.
	 */
	public function client_secret( $now ) {
		return Jwt::sign_es256(
			[ 'kid' => $this->value( 'key_id' ) ],
			[
				'iss' => $this->value( 'team_id' ),
				'iat' => $now,
				'exp' => $now + 300,
				'aud' => self::ISSUER,
				'sub' => $this->value( 'client_id' ),
			],
			$this->value( 'private_key' )
		);
	}

	/**
	 * Check an ID token's claims, and return the identity they prove. Pure.
	 *
	 * @param array  $claims    Claims.
	 * @param string $client_id The Services ID.
	 * @param string $nonce     The nonce sent with the sign-in.
	 * @param int    $now       Current time.
	 * @return array|\WP_Error
	 */
	public static function check_claims( array $claims, $client_id, $nonce, $now ) {
		if ( '' === (string) $nonce || ! Jwt::valid_claims( $claims, [ self::ISSUER ], $client_id, $nonce, $now ) ) {
			return new \WP_Error( 'nhrrob_social_login_token', __( 'The sign-in could not be confirmed. Please try again.', 'nhrrob-social-login' ) );
		}

		return self::make_identity( $claims['sub'], isset( $claims['email'] ) ? $claims['email'] : '', Jwt::is_true( $claims, 'email_verified' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M16.37 12.6c-.02-2.3 1.88-3.4 1.97-3.46-1.07-1.57-2.74-1.79-3.33-1.81-1.42-.14-2.77.83-3.49.83-.72 0-1.83-.81-3.01-.79-1.55.02-2.98.9-3.78 2.29-1.61 2.8-.41 6.94 1.16 9.21.77 1.11 1.68 2.36 2.88 2.31 1.16-.05 1.59-.75 2.99-.75 1.4 0 1.79.75 3.01.72 1.24-.02 2.03-1.13 2.79-2.25.88-1.29 1.24-2.53 1.26-2.6-.03-.01-2.42-.93-2.45-3.7zm-2.29-6.76c.64-.77 1.07-1.85.95-2.92-.92.04-2.03.61-2.69 1.38-.59.68-1.11 1.77-.97 2.82 1.03.08 2.07-.52 2.71-1.28z"/></svg>';
	}
}
