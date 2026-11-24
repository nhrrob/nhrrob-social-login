<?php
/**
 * Which WordPress user an identity is: the one already linked to it, the
 * one with its email address, or a new account.
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
 * A user is linked to a provider's account by its ID, which never changes,
 * in the user meta `nhrrob_social_login_<provider>_sub` (Google's is
 * `nhrrob_social_login_google_sub`). The email address only finds a user
 * the first time.
 */
class Accounts {

	const META_PICTURE  = 'nhrrob_social_login_picture';
	const META_PASSWORD = 'nhrrob_social_login_no_password';

	/**
	 * The user an identity signs in as.
	 *
	 * @param array  $identity `sub`, `email`, `name`, `given_name`, `picture`.
	 * @param string $meta_key The provider's link meta key; '' for a sign-in link by email, which links nothing.
	 * @param bool   $create   Whether a new account may be made.
	 * @param bool   $created  Set to whether a new user was made.
	 * @param string $role     Role of a new account.
	 * @return \WP_User|\WP_Error
	 */
	public static function user_for( array $identity, $meta_key, $create, &$created = false, $role = 'subscriber' ) {
		$created = false;
		$user    = '' !== $meta_key ? self::linked_user( $meta_key, $identity['sub'] ) : false;
		$linked  = (bool) $user;

		if ( ! $user ) {
			$user = get_user_by( 'email', $identity['email'] );

			// Already linked to another account at this provider with this address: the address moved, so ask.
			if ( $user && '' !== $meta_key && '' !== (string) get_user_meta( $user->ID, $meta_key, true ) ) {
				return new \WP_Error( 'linked_other', '' );
			}
		}

		if ( ! self::domain_allowed( $identity['email'], $user ? $user : null ) ) {
			return new \WP_Error( 'domain', '' );
		}

		if ( $user ) {
			if ( '' !== $meta_key && ! $linked ) {
				update_user_meta( $user->ID, $meta_key, $identity['sub'] );
			}

			self::remember_picture( $user->ID, $identity );

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
				'role'         => $role,
			]
		);

		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'create_failed', '' );
		}

		if ( '' !== $meta_key ) {
			update_user_meta( $id, $meta_key, $identity['sub'] );
		}

		// The password is random and was never shown: until they set one, it is not a way in.
		update_user_meta( $id, self::META_PASSWORD, 1 );
		self::remember_picture( $id, $identity );
		$created = true;

		return get_user_by( 'id', $id );
	}

	/**
	 * The user linked to an account ID.
	 *
	 * @param string $meta_key Link meta key.
	 * @param string $sub      Account ID.
	 * @return \WP_User|false
	 */
	public static function linked_user( $meta_key, $sub ) {
		$linked = get_users(
			[
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed lookup per sign-in.
				'meta_value' => $sub, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one indexed lookup per sign-in.
				'number'     => 1,
			]
		);

		return $linked ? $linked[0] : false;
	}

	/**
	 * Whether an address may sign in, under the allowed domains.
	 *
	 * @param string        $email Email address.
	 * @param \WP_User|null $user  The user it would sign in as, if any.
	 * @return bool
	 */
	public static function domain_allowed( $email, $user = null ) {
		$allowed = self::in_domains( $email, Settings::allowed_domains() );

		/**
		 * Whether an address may sign in under the allowed domains. The list
		 * applies to existing users too; return true here to let, say,
		 * administrators in from any domain.
		 *
		 * @param bool          $allowed Whether its domain is in the list (true when the list is empty).
		 * @param string        $email   The address.
		 * @param \WP_User|null $user    The existing user it would sign in as, or null for a new account.
		 */
		return (bool) apply_filters( 'nhrrob_social_login_domain_allowed', $allowed, $email, $user );
	}

	/**
	 * Whether an address is in one of the domains, exactly (not a subdomain). Pure.
	 *
	 * @param string   $email   Email address.
	 * @param string[] $domains Domains, lowercase; empty allows every address.
	 * @return bool
	 */
	public static function in_domains( $email, array $domains ) {
		$at = strrchr( (string) $email, '@' );

		return ! $domains || ( false !== $at && in_array( strtolower( substr( $at, 1 ) ), $domains, true ) );
	}

	/**
	 * The role a new account gets: the setting, or WooCommerce's customer for
	 * a sign-in from its forms. Never a role that can manage the site or its
	 * users, and one that can write posts only when the owner allowed it.
	 *
	 * @param string $source Where the sign-in started.
	 * @return string
	 */
	public static function new_role( $source = '' ) {
		$wanted = 'woocommerce' === $source && get_role( 'customer' ) ? 'customer' : (string) Settings::value( 'new_user_role' );

		return self::safe_role( $wanted, (bool) Settings::value( 'allow_author_roles' ) ) ? $wanted : 'subscriber';
	}

	/**
	 * Whether a role may be given to new accounts.
	 *
	 * @param string $role          Role name.
	 * @param bool   $allow_authors Whether roles that can write posts are allowed.
	 * @return bool
	 */
	public static function safe_role( $role, $allow_authors ) {
		$object = get_role( $role );

		if ( ! $object ) {
			return false;
		}

		foreach ( [ 'manage_options', 'promote_users', 'edit_users', 'create_users' ] as $cap ) {
			if ( $object->has_cap( $cap ) ) {
				return false;
			}
		}

		return $allow_authors || ! $object->has_cap( 'edit_posts' );
	}

	/**
	 * Keep the picture the provider gave, for the avatar.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $identity Identity.
	 * @return void
	 */
	private static function remember_picture( $user_id, array $identity ) {
		if ( ! empty( $identity['picture'] ) ) {
			update_user_meta( $user_id, self::META_PICTURE, $identity['picture'] );
		}
	}

	/**
	 * How many users are linked to any provider.
	 *
	 * @return int
	 */
	public static function linked_count() {
		$keys = [];

		foreach ( Providers::all() as $provider ) {
			$keys[] = $provider->meta_key();
		}

		// One join with the keys in a list; one EXISTS clause per key would join usermeta once per provider.
		$linked = new \WP_User_Query(
			[
				'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a count for the owner, on the settings screen.
					[
						'key'         => $keys,
						'compare_key' => 'IN',
					],
				],
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
