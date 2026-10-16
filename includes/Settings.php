<?php
/**
 * Plugin settings: the Google client, and whether a new Google user gets an account.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Class
 *
 * The client ID and secret are read from wp-config.php first
 * (NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID and NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET),
 * then from the option. The secret is never printed in a page or returned by an ability.
 */
class Settings {

	const OPTION = 'nhrrob_social_login_settings';

	/**
	 * All settings, sanitized, with defaults filled in.
	 *
	 * @return array
	 */
	public static function get() {
		return self::sanitize( (array) get_option( self::OPTION, [] ) );
	}

	/**
	 * Sanitize a settings array.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		return [
			'google_client_id'     => isset( $input['google_client_id'] ) ? trim( sanitize_text_field( (string) $input['google_client_id'] ) ) : '',
			'google_client_secret' => isset( $input['google_client_secret'] ) ? trim( sanitize_text_field( (string) $input['google_client_secret'] ) ) : '',
			'create_accounts'      => ! empty( $input['create_accounts'] ),
		];
	}

	/**
	 * Settings as submitted from the screen. An empty secret keeps the stored one,
	 * since the field is never filled in with it.
	 *
	 * @param mixed $input Raw settings from the form.
	 * @return array
	 */
	public static function sanitize_from_form( $input ) {
		$input = is_array( $input ) ? $input : [];
		$clean = self::sanitize( $input );

		if ( '' === $clean['google_client_secret'] && empty( $input['remove_secret'] ) ) {
			$clean['google_client_secret'] = self::get()['google_client_secret'];
		}

		return $clean;
	}

	/**
	 * The Google client ID.
	 *
	 * @return string
	 */
	public static function client_id() {
		return self::id_in_config() ? trim( (string) NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID ) : self::get()['google_client_id'];
	}

	/**
	 * The Google client secret.
	 *
	 * @return string
	 */
	public static function client_secret() {
		return self::secret_in_config() ? trim( (string) NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET ) : self::get()['google_client_secret'];
	}

	/**
	 * Whether the client ID comes from wp-config.php.
	 *
	 * @return bool
	 */
	public static function id_in_config() {
		return defined( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID' ) && '' !== trim( (string) NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID );
	}

	/**
	 * Whether the client secret comes from wp-config.php.
	 *
	 * @return bool
	 */
	public static function secret_in_config() {
		return defined( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET' ) && '' !== trim( (string) NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET );
	}

	/**
	 * Whether Google sign-in can be offered: both the client ID and the secret are set.
	 *
	 * @return bool
	 */
	public static function google_ready() {
		return '' !== self::client_id() && '' !== self::client_secret();
	}

	/**
	 * Whether a Google user without an account gets one, as a subscriber.
	 * Independent of "Anyone can register", so the registration form can stay closed.
	 *
	 * @return bool
	 */
	public static function create_accounts() {
		return self::get()['create_accounts'];
	}

	/**
	 * The address Google sends the person back to. It has to be registered in the Google client.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=' . Login::ACTION );
	}

	/**
	 * Whether the current user may change the settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}
}
