<?php
/**
 * The provider's picture as the avatar, for users without a Gravatar.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Avatar Class
 *
 * Whether a user has a Gravatar is asked once, at sign-in (a request to
 * gravatar.com that answers 404 when there is none), and kept in user
 * meta, so a page view makes no request beyond the image itself.
 */
class Avatar {

	const META_GRAVATAR = 'nhrrob_social_login_gravatar';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'nhrrob_social_login_signed_in', [ $this, 'check_gravatar' ] );
		add_filter( 'pre_get_avatar_data', [ $this, 'avatar' ], 10, 2 );
	}

	/**
	 * After a sign-in, ask Gravatar once whether the user has an image.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public function check_gravatar( $user ) {
		if ( ! Settings::value( 'use_avatar' ) || '' === (string) get_user_meta( $user->ID, Accounts::META_PICTURE, true ) || '' !== (string) get_user_meta( $user->ID, self::META_GRAVATAR, true ) ) {
			return;
		}

		$response = wp_remote_head(
			'https://www.gravatar.com/avatar/' . hash( 'sha256', strtolower( trim( $user->user_email ) ) ) . '?d=404',
			[ 'timeout' => 5 ]
		);

		if ( ! is_wp_error( $response ) ) {
			$found = 200 === (int) wp_remote_retrieve_response_code( $response );
			update_user_meta( $user->ID, self::META_GRAVATAR, $found ? 'yes' : 'no' );
		}
	}

	/**
	 * Use the provider's picture for a user who has one and no Gravatar.
	 *
	 * @param array $args        Avatar arguments.
	 * @param mixed $id_or_email User ID, email, WP_User, WP_Post or WP_Comment.
	 * @return array
	 */
	public function avatar( $args, $id_or_email ) {
		if ( ! Settings::value( 'use_avatar' ) ) {
			return $args;
		}

		$user_id = self::user_id( $id_or_email );
		$picture = $user_id ? (string) get_user_meta( $user_id, Accounts::META_PICTURE, true ) : '';

		if ( '' !== $picture && 'no' === get_user_meta( $user_id, self::META_GRAVATAR, true ) ) {
			$args['url']          = $picture;
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * The user behind what get_avatar() was given.
	 *
	 * @param mixed $id_or_email User ID, email, WP_User, WP_Post or WP_Comment.
	 * @return int 0 when it is not a user.
	 */
	private static function user_id( $id_or_email ) {
		if ( is_numeric( $id_or_email ) ) {
			return (int) $id_or_email;
		}

		if ( $id_or_email instanceof \WP_User ) {
			return (int) $id_or_email->ID;
		}

		if ( $id_or_email instanceof \WP_Post ) {
			return (int) $id_or_email->post_author;
		}

		if ( $id_or_email instanceof \WP_Comment ) {
			return (int) $id_or_email->user_id;
		}

		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );

			return $user ? (int) $user->ID : 0;
		}

		return 0;
	}
}
