<?php
/**
 * Google sign-in with OpenID Connect: the address that starts it, and the
 * exchange of the code Google returns for the person's verified identity.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Class
 *
 * The only outside requests are to accounts.google.com (in the person's
 * browser) and oauth2.googleapis.com (from the server, to exchange the code).
 * The ID token comes straight from Google's token endpoint over TLS, so its
 * claims are checked but its signature is not fetched and verified, as
 * OpenID Connect Core 3.1.3.7 allows for a token received that way.
 */
class Google {

	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const ISSUERS   = [ 'accounts.google.com', 'https://accounts.google.com' ];

	/**
	 * Seconds of clock difference allowed when checking the token's expiry.
	 */
	const LEEWAY = 60;

	/**
	 * The address that sends the person to Google.
	 *
	 * @param string $state     Value Google returns unchanged, tying the answer to this browser.
	 * @param string $nonce     Value Google puts into the ID token, tying the token to this sign-in.
	 * @param string $challenge PKCE code challenge (S256).
	 * @return string
	 */
	public static function auth_url( $state, $nonce, $challenge ) {
		return add_query_arg(
			rawurlencode_deep(
				[
					'client_id'             => Settings::client_id(),
					'redirect_uri'          => Settings::redirect_uri(),
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
	 * A PKCE code challenge for a verifier.
	 *
	 * @param string $verifier Code verifier.
	 * @return string
	 */
	public static function challenge( $verifier ) {
		return self::base64url_encode( hash( 'sha256', $verifier, true ) );
	}

	/**
	 * Exchange the code Google returned for the person's identity.
	 *
	 * @param string $code     Authorization code.
	 * @param string $verifier PKCE code verifier.
	 * @param string $nonce    The nonce sent with the sign-in.
	 * @return array|\WP_Error `sub`, `email`, `name`, `given_name`.
	 */
	public static function identity( $code, $verifier, $nonce ) {
		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'body'    => [
					'code'          => $code,
					'client_id'     => Settings::client_id(),
					'client_secret' => Settings::client_secret(),
					'redirect_uri'  => Settings::redirect_uri(),
					'grant_type'    => 'authorization_code',
					'code_verifier' => $verifier,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'nhrrob_social_login_google', __( 'Google could not be reached. Please try again.', 'nhrrob-social-login' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $body['id_token'] ) ) {
			return new \WP_Error( 'nhrrob_social_login_google', __( 'Google did not confirm the sign-in. Please try again.', 'nhrrob-social-login' ) );
		}

		return self::check_claims( self::claims( (string) $body['id_token'] ), Settings::client_id(), $nonce, time() );
	}

	/**
	 * The claims of an ID token, unverified.
	 *
	 * @param string $id_token ID token (a JWT).
	 * @return array Empty when it cannot be read.
	 */
	public static function claims( $id_token ) {
		$parts = explode( '.', $id_token );

		if ( 3 !== count( $parts ) ) {
			return [];
		}

		$claims = json_decode( self::base64url_decode( $parts[1] ), true );

		return is_array( $claims ) ? $claims : [];
	}

	/**
	 * Check an ID token's claims, and return the identity they prove. Pure.
	 *
	 * @param array  $claims    Claims of the ID token.
	 * @param string $client_id This site's Google client ID.
	 * @param string $nonce     The nonce sent with the sign-in.
	 * @param int    $now       Current time.
	 * @return array|\WP_Error `sub`, `email`, `name`, `given_name`.
	 */
	public static function check_claims( array $claims, $client_id, $nonce, $now ) {
		$audience = isset( $claims['aud'] ) ? (array) $claims['aud'] : [];
		$valid    = isset( $claims['iss'], $claims['sub'], $claims['exp'], $claims['nonce'] )
			&& in_array( $claims['iss'], self::ISSUERS, true )
			&& '' !== $client_id && in_array( $client_id, $audience, true )
			&& (int) $claims['exp'] + self::LEEWAY > $now
			&& '' !== (string) $nonce && hash_equals( (string) $nonce, (string) $claims['nonce'] )
			&& '' !== (string) $claims['sub'];

		if ( ! $valid ) {
			return new \WP_Error( 'nhrrob_social_login_token', __( 'The sign-in could not be confirmed. Please try again.', 'nhrrob-social-login' ) );
		}

		$verified = isset( $claims['email_verified'] ) && ( true === $claims['email_verified'] || 'true' === $claims['email_verified'] );
		$email    = isset( $claims['email'] ) ? sanitize_email( (string) $claims['email'] ) : '';

		if ( ! $verified || ! is_email( $email ) ) {
			return new \WP_Error( 'nhrrob_social_login_email', __( 'This Google account has no verified email address.', 'nhrrob-social-login' ) );
		}

		return [
			'sub'        => (string) $claims['sub'],
			'email'      => $email,
			'name'       => isset( $claims['name'] ) ? sanitize_text_field( (string) $claims['name'] ) : '',
			'given_name' => isset( $claims['given_name'] ) ? sanitize_text_field( (string) $claims['given_name'] ) : '',
		];
	}

	/**
	 * A random value safe for a URL, for the state, the nonce and the code verifier.
	 *
	 * @return string 43 characters.
	 */
	public static function random() {
		return self::base64url_encode( random_bytes( 32 ) );
	}

	/**
	 * Base64url without padding.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url is the encoding PKCE and JWT use.
	}

	/**
	 * Decode base64url.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function base64url_decode( $data ) {
		return (string) base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- base64url is the encoding a JWT uses.
	}
}
