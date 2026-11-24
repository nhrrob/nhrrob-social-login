<?php
/**
 * What every sign-in provider gives the flow in Login: where to send the
 * person, and the verified identity the code they bring back proves.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

use NHRRob\SocialLogin\Callback;
use NHRRob\SocialLogin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider Class
 *
 * An identity is `sub` (the provider's account ID, which links the user),
 * `email` (verified by the provider, or the sign-in is refused), `name`,
 * `given_name` and `picture` (an https address, or '').
 */
abstract class Provider {

	/**
	 * Provider ID: lowercase letters, used in option keys, constants and meta keys.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Name shown on buttons and screens.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Where a developer makes the client.
	 *
	 * @return string
	 */
	abstract public function console_url();

	/**
	 * The address that sends the person to the provider.
	 *
	 * @param string $state     Value returned unchanged, tying the answer to this browser.
	 * @param string $nonce     Value an OpenID Connect provider puts into the ID token.
	 * @param string $challenge PKCE code challenge (S256).
	 * @return string
	 */
	abstract public function auth_url( $state, $nonce, $challenge );

	/**
	 * Exchange the code for the person's identity.
	 *
	 * @param string $code     Authorization code.
	 * @param string $verifier PKCE code verifier.
	 * @param string $nonce    The nonce sent with the sign-in.
	 * @return array|\WP_Error
	 */
	abstract public function identity( $code, $verifier, $nonce );

	/**
	 * Credential fields: name => [ label, secret, multiline ].
	 *
	 * @return array
	 */
	public function fields() {
		return [
			'client_id'     => [
				'label'  => __( 'Client ID', 'nhrrob-social-login' ),
				'secret' => false,
			],
			'client_secret' => [
				'label'  => __( 'Client secret', 'nhrrob-social-login' ),
				'secret' => true,
			],
		];
	}

	/**
	 * Whether every credential is set.
	 *
	 * @return bool
	 */
	public function ready() {
		foreach ( array_keys( $this->fields() ) as $field ) {
			if ( '' === $this->value( $field ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether its buttons show: switched on and set up.
	 *
	 * @return bool
	 */
	public function active() {
		return Settings::enabled( $this->id() ) && $this->ready();
	}

	/**
	 * The address the provider sends the person back to; it is registered with the provider.
	 *
	 * @return string
	 */
	public function redirect_uri() {
		return Callback::url( $this->id() );
	}

	/**
	 * Whether the provider answers with a POST (Sign in with Apple's form_post).
	 *
	 * @return bool
	 */
	public function posts_back() {
		return false;
	}

	/**
	 * The user meta that links a user to an account at this provider.
	 *
	 * @return string
	 */
	public function meta_key() {
		return 'nhrrob_social_login_' . $this->id() . '_sub';
	}

	/**
	 * The icon on its button: an SVG, made of fixed markup.
	 *
	 * @return string
	 */
	abstract public function icon();

	/**
	 * One credential.
	 *
	 * @param string $field Field.
	 * @return string
	 */
	protected function value( $field ) {
		return Settings::credential( $this->id(), $field );
	}

	/**
	 * An identity from what the provider returned, or an error when its email
	 * address is missing or not verified.
	 *
	 * @param string $sub      Account ID.
	 * @param string $email    Email address.
	 * @param bool   $verified Whether the provider verified it.
	 * @param string $name     Full name.
	 * @param string $given    Given name.
	 * @param string $picture  Picture address.
	 * @return array|\WP_Error
	 */
	public static function make_identity( $sub, $email, $verified, $name = '', $given = '', $picture = '' ) {
		$email = sanitize_email( (string) $email );

		if ( '' === (string) $sub ) {
			return new \WP_Error( 'nhrrob_social_login_token', __( 'The sign-in could not be confirmed. Please try again.', 'nhrrob-social-login' ) );
		}

		if ( ! $verified || ! is_email( $email ) ) {
			return new \WP_Error( 'nhrrob_social_login_email', __( 'This account has no verified email address.', 'nhrrob-social-login' ) );
		}

		$picture = (string) $picture;

		return [
			'sub'        => (string) $sub,
			'email'      => $email,
			'name'       => sanitize_text_field( (string) $name ),
			'given_name' => sanitize_text_field( (string) $given ),
			'picture'    => 0 === strpos( $picture, 'https://' ) ? esc_url_raw( $picture ) : '',
		];
	}

	/**
	 * POST to a token endpoint and decode the JSON answer.
	 *
	 * @param string $url     Endpoint.
	 * @param array  $body    Form fields.
	 * @param array  $headers Extra headers.
	 * @return array|\WP_Error
	 */
	protected function post_json( $url, array $body, array $headers = [] ) {
		return self::json(
			wp_remote_post(
				$url,
				[
					'timeout' => 15,
					'headers' => array_merge( [ 'Accept' => 'application/json' ], $headers ),
					'body'    => $body,
				]
			)
		);
	}

	/**
	 * GET an API address with a bearer token and decode the JSON answer.
	 *
	 * @param string $url   Address.
	 * @param string $token Access token.
	 * @return array|\WP_Error
	 */
	protected function get_json( $url, $token ) {
		return self::json(
			wp_remote_get(
				$url,
				[
					'timeout' => 15,
					'headers' => [
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $token,
						'User-Agent'    => 'NHR Social Login',
					],
				]
			)
		);
	}

	/**
	 * A decoded JSON answer, or an error when the request failed or was refused.
	 *
	 * @param array|\WP_Error $response Response.
	 * @return array|\WP_Error
	 */
	private static function json( $response ) {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'nhrrob_social_login_http', __( 'The sign-in service could not be reached. Please try again.', 'nhrrob-social-login' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $body ) ) {
			return new \WP_Error( 'nhrrob_social_login_http', __( 'The sign-in service did not confirm the sign-in. Please try again.', 'nhrrob-social-login' ) );
		}

		return $body;
	}

	/**
	 * The access token in a token answer.
	 *
	 * @param array|\WP_Error $answer Token answer.
	 * @return string|\WP_Error
	 */
	protected static function access_token( $answer ) {
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		if ( empty( $answer['access_token'] ) || ! is_string( $answer['access_token'] ) ) {
			return new \WP_Error( 'nhrrob_social_login_http', __( 'The sign-in service did not confirm the sign-in. Please try again.', 'nhrrob-social-login' ) );
		}

		return $answer['access_token'];
	}
}
