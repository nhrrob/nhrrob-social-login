<?php
/**
 * GitHub sign-in (OAuth 2.0 with PKCE). The email is the account's primary
 * address, and only when GitHub has verified it.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub Class
 */
class GitHub extends Provider {

	const AUTH_URL   = 'https://github.com/login/oauth/authorize';
	const TOKEN_URL  = 'https://github.com/login/oauth/access_token';
	const USER_URL   = 'https://api.github.com/user';
	const EMAILS_URL = 'https://api.github.com/user/emails';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'github';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'GitHub';
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://github.com/settings/developers';
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
					'scope'                 => 'read:user user:email',
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
					'client_id'     => $this->value( 'client_id' ),
					'client_secret' => $this->value( 'client_secret' ),
					'code'          => $code,
					'redirect_uri'  => $this->redirect_uri(),
					'code_verifier' => $verifier,
				]
			)
		);

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$user   = $this->get_json( self::USER_URL, $token );
		$emails = $this->get_json( self::EMAILS_URL, $token );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( is_wp_error( $emails ) ) {
			return $emails;
		}

		$email = self::primary_email( $emails );

		return self::make_identity(
			isset( $user['id'] ) ? (string) $user['id'] : '',
			$email,
			'' !== $email,
			! empty( $user['name'] ) ? $user['name'] : ( isset( $user['login'] ) ? $user['login'] : '' ),
			'',
			isset( $user['avatar_url'] ) ? $user['avatar_url'] : ''
		);
	}

	/**
	 * The primary address, if GitHub verified it. Pure.
	 *
	 * @param array $emails The /user/emails answer.
	 * @return string '' when there is none.
	 */
	public static function primary_email( array $emails ) {
		foreach ( $emails as $email ) {
			if ( is_array( $email ) && ! empty( $email['primary'] ) && true === ( isset( $email['verified'] ) ? $email['verified'] : false ) && ! empty( $email['email'] ) ) {
				return (string) $email['email'];
			}
		}

		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon() {
		return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 16 16"><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>';
	}
}
