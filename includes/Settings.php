<?php
/**
 * Plugin settings: each provider's credentials and on/off, and what happens
 * to the people who sign in (new accounts, their role, redirects, domains)
 * and the optional extras.
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
 * A provider's credentials are read from wp-config.php first
 * (NHRROB_SOCIAL_LOGIN_<PROVIDER>_<FIELD>, such as
 * NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET), then from the option. Secrets
 * are never printed in a page or returned by an ability or a route.
 */
class Settings {

	const OPTION = 'nhrrob_social_login_settings';

	/**
	 * One Tap: off, on posts, or on every front-end page.
	 */
	const ONE_TAP = [ 'off', 'posts', 'site' ];

	/**
	 * Settings that are not a provider's, with their defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'create_accounts'    => false,
			'new_user_role'      => 'subscriber',
			'allow_author_roles' => false,
			'login_redirect'     => '',
			'signup_redirect'    => '',
			'allowed_domains'    => [],
			'use_avatar'         => true,
			'one_tap'            => 'off',
			'notify_admin'       => false,
			'magic_link'         => false,
			'verified_mark'      => false,
		];
	}

	/**
	 * All settings, sanitized, with defaults filled in. Sanitized once per
	 * stored value, since every page asks several times.
	 *
	 * @return array
	 */
	public static function get() {
		static $raw   = null;
		static $clean = [];

		$stored = (array) get_option( self::OPTION, [] );

		if ( $stored !== $raw ) {
			$raw   = $stored;
			$clean = self::sanitize( $stored );
		}

		return $clean;
	}

	/**
	 * Sanitize a settings array: every provider's fields and on/off, then the rest.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$clean = [];

		foreach ( Providers::all() as $id => $provider ) {
			// Google was the only provider before 0.3.0, on whenever it was set up.
			$key           = $id . '_enabled';
			$clean[ $key ] = array_key_exists( $key, $input ) ? (bool) $input[ $key ] : 'google' === $id;

			foreach ( $provider->fields() as $field => $spec ) {
				$key           = $id . '_' . $field;
				$value         = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
				$clean[ $key ] = ! empty( $spec['multiline'] ) ? trim( sanitize_textarea_field( $value ) ) : trim( sanitize_text_field( $value ) );
			}
		}

		$defaults = self::defaults();
		$role     = isset( $input['new_user_role'] ) ? sanitize_key( (string) $input['new_user_role'] ) : '';
		$one_tap  = isset( $input['one_tap'] ) ? sanitize_key( (string) $input['one_tap'] ) : '';

		$clean['create_accounts']    = ! empty( $input['create_accounts'] );
		$clean['new_user_role']      = '' !== $role ? $role : $defaults['new_user_role'];
		$clean['allow_author_roles'] = ! empty( $input['allow_author_roles'] );
		$clean['login_redirect']     = isset( $input['login_redirect'] ) ? esc_url_raw( trim( (string) $input['login_redirect'] ) ) : '';
		$clean['signup_redirect']    = isset( $input['signup_redirect'] ) ? esc_url_raw( trim( (string) $input['signup_redirect'] ) ) : '';
		$clean['allowed_domains']    = self::domains( isset( $input['allowed_domains'] ) ? $input['allowed_domains'] : [] );
		$clean['use_avatar']         = array_key_exists( 'use_avatar', $input ) ? (bool) $input['use_avatar'] : $defaults['use_avatar'];
		$clean['one_tap']            = in_array( $one_tap, self::ONE_TAP, true ) ? $one_tap : 'off';
		$clean['notify_admin']       = ! empty( $input['notify_admin'] );
		$clean['magic_link']         = ! empty( $input['magic_link'] );
		$clean['verified_mark']      = ! empty( $input['verified_mark'] );

		return $clean;
	}

	/**
	 * A list of email domains from an array or from text split by commas,
	 * spaces or lines: lowercase, without a leading @, invalid ones dropped.
	 *
	 * @param mixed $input Domains.
	 * @return string[]
	 */
	public static function domains( $input ) {
		$list = is_array( $input ) ? $input : preg_split( '/[\s,]+/', (string) $input );
		$out  = [];

		foreach ( (array) $list as $domain ) {
			$domain = is_scalar( $domain ) ? strtolower( ltrim( trim( (string) $domain ), '@' ) ) : '';

			if ( '' !== $domain && preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain ) ) {
				$out[] = $domain;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $name Setting.
	 * @return mixed
	 */
	public static function value( $name ) {
		$all = self::get();

		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	/**
	 * The wp-config.php constant of a provider's field.
	 *
	 * @param string $provider Provider ID.
	 * @param string $field    Field.
	 * @return string
	 */
	public static function constant( $provider, $field ) {
		return 'NHRROB_SOCIAL_LOGIN_' . strtoupper( $provider . '_' . $field );
	}

	/**
	 * Whether a provider's field comes from wp-config.php.
	 *
	 * @param string $provider Provider ID.
	 * @param string $field    Field.
	 * @return bool
	 */
	public static function in_config( $provider, $field ) {
		$name = self::constant( $provider, $field );

		return defined( $name ) && '' !== trim( (string) constant( $name ) );
	}

	/**
	 * A provider's field: wp-config.php first, then the option.
	 *
	 * @param string $provider Provider ID.
	 * @param string $field    Field, such as client_id.
	 * @return string
	 */
	public static function credential( $provider, $field ) {
		if ( self::in_config( $provider, $field ) ) {
			return trim( (string) constant( self::constant( $provider, $field ) ) );
		}

		return (string) self::value( $provider . '_' . $field );
	}

	/**
	 * Whether a provider is switched on (it still needs its credentials to show).
	 *
	 * @param string $provider Provider ID.
	 * @return bool
	 */
	public static function enabled( $provider ) {
		return (bool) self::value( $provider . '_enabled' );
	}

	/**
	 * Whether a user without an account gets one.
	 * Independent of "Anyone can register", so the registration form can stay closed.
	 *
	 * @return bool
	 */
	public static function create_accounts() {
		return (bool) self::value( 'create_accounts' );
	}

	/**
	 * The email domains allowed to sign in: the constant
	 * NHRROB_SOCIAL_LOGIN_ALLOWED_DOMAINS (comma-separated) first, then the
	 * setting. Empty means every domain.
	 *
	 * @return string[]
	 */
	public static function allowed_domains() {
		if ( self::domains_in_config() ) {
			return self::domains( (string) NHRROB_SOCIAL_LOGIN_ALLOWED_DOMAINS );
		}

		return (array) self::value( 'allowed_domains' );
	}

	/**
	 * Whether the allowed domains come from wp-config.php.
	 *
	 * @return bool
	 */
	public static function domains_in_config() {
		return defined( 'NHRROB_SOCIAL_LOGIN_ALLOWED_DOMAINS' );
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
