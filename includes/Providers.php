<?php
/**
 * The sign-in providers, in the order their buttons show.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

use NHRRob\SocialLogin\Providers\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Providers Class
 */
class Providers {

	/**
	 * Every provider, by ID.
	 *
	 * @return Provider[]
	 */
	public static function all() {
		static $all = null;

		if ( null === $all ) {
			$all = [];

			foreach ( [ Providers\Google::class, Providers\Microsoft::class, Providers\Apple::class, Providers\GitHub::class, Providers\Facebook::class, Providers\X::class, Providers\LinkedIn::class ] as $class ) {
				$provider               = new $class();
				$all[ $provider->id() ] = $provider;
			}
		}

		return $all;
	}

	/**
	 * One provider.
	 *
	 * @param string $id Provider ID.
	 * @return Provider|null
	 */
	public static function get( $id ) {
		$all = self::all();

		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * The providers whose buttons show: switched on and set up.
	 *
	 * @return Provider[]
	 */
	public static function active() {
		return array_filter(
			self::all(),
			[ __CLASS__, 'is_active' ]
		);
	}

	/**
	 * Whether a provider's buttons show. For array_filter().
	 *
	 * @param Provider $provider Provider.
	 * @return bool
	 */
	public static function is_active( Provider $provider ) {
		return $provider->active();
	}

	/**
	 * Whether any sign-in is offered: a provider, or links by email.
	 *
	 * @return bool
	 */
	public static function any() {
		return (bool) self::active() || MagicLink::on();
	}

	/**
	 * The providers a user is linked to: ID => account ID.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function linked( $user_id ) {
		$linked = [];

		foreach ( self::all() as $id => $provider ) {
			$sub = (string) get_user_meta( $user_id, $provider->meta_key(), true );

			if ( '' !== $sub ) {
				$linked[ $id ] = $sub;
			}
		}

		return $linked;
	}
}
