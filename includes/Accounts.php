<?php
/**
 * Which WordPress user a Google identity is: the one already linked to it,
 * the one with its email address, or a new subscriber.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accounts Class
 *
 * A user is linked to a Google account by its `sub`, which never changes,
 * stored in the `nhrrob_social_login_google_sub` user meta. The email
 * address only finds a user the first time.
 */
class Accounts {

	/**
	 * The user a Google identity signs in as.
	 *
	 * @param array $identity `sub`, `email`, `name`, `given_name`, from Google::check_claims().
	 * @param bool  $create   Whether a new subscriber may be created.
	 * @param bool  $created  Set to whether a new user was created.
	 * @return \WP_User|\WP_Error
	 */
	public static function user_for( array $identity, $create, &$created = false ) {
		$created = false;

		$linked = get_users(
			[
				'meta_key'   => Login::META_SUB, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup per sign-in.
				'meta_value' => $identity['sub'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one indexed lookup per sign-in.
				'number'     => 1,
			]
		);

		if ( $linked ) {
			return $linked[0];
		}

		$user = get_user_by( 'email', $identity['email'] );

		if ( $user ) {
			// Already linked to another Google account with this address: the address moved, so ask.
			if ( '' !== (string) get_user_meta( $user->ID, Login::META_SUB, true ) ) {
				return new \WP_Error( 'linked_other', '' );
			}

			update_user_meta( $user->ID, Login::META_SUB, $identity['sub'] );

			return $user;
		}

		if ( ! $create ) {
			return new \WP_Error( 'no_account', '' );
		}

		$id = wp_insert_user(
			[
				'user_login'   => self::username( $identity['email'] ),
				'user_email'   => $identity['email'],
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => '' !== $identity['name'] ? $identity['name'] : strstr( $identity['email'], '@', true ),
				'first_name'   => $identity['given_name'],
				'role'         => 'subscriber',
			]
		);

		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'create_failed', '' );
		}

		update_user_meta( $id, Login::META_SUB, $identity['sub'] );
		$created = true;

		return get_user_by( 'id', $id );
	}

	/**
	 * How many users are linked to a Google account.
	 *
	 * @return int
	 */
	public static function linked_count() {
		$linked = new \WP_User_Query(
			[
				'meta_key'    => Login::META_SUB, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a count for the owner.
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			]
		);

		return (int) $linked->get_total();
	}

	/**
	 * A free username from the part of an email address before the @.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	public static function username( $email ) {
		$base = sanitize_user( strstr( $email, '@', true ), true );
		$base = '' !== $base ? substr( $base, 0, 50 ) : 'user';
		$name = $base;
		$n    = 2;

		while ( username_exists( $name ) ) {
			$name = $base . $n;
			++$n;
		}

		return $name;
	}
}
