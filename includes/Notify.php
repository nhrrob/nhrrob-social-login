<?php
/**
 * An optional email to the site's admin address when a sign-in makes a new
 * account.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notify Class
 */
class Notify {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'nhrrob_social_login_signed_in', [ $this, 'signed_in' ], 10, 2 );
	}

	/**
	 * Email the owner about a new account.
	 *
	 * @param \WP_User $user    User.
	 * @param array    $context Sign-in context.
	 * @return void
	 */
	public function signed_in( $user, $context ) {
		if ( empty( $context['created'] ) || ! Settings::value( 'notify_admin' ) ) {
			return;
		}

		wp_mail( (string) get_option( 'admin_email' ), self::subject( $user ), self::body( $user, $context ) );
	}

	/**
	 * The subject line.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	public static function subject( $user ) {
		/* translators: 1: site name, 2: the new user's name. */
		return sprintf( __( '[%1$s] New account: %2$s', 'nhrrob-social-login' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $user->display_name );
	}

	/**
	 * The message: who, how, and the post they came from.
	 *
	 * @param \WP_User $user    User.
	 * @param array    $context Sign-in context.
	 * @return string
	 */
	public static function body( $user, array $context ) {
		$provider = Providers::get( isset( $context['provider'] ) ? (string) $context['provider'] : '' );
		$how      = $provider ? $provider->label() : __( 'a sign-in link by email', 'nhrrob-social-login' );
		$lines    = [
			/* translators: %s: the new user's name. */
			sprintf( __( 'Name: %s', 'nhrrob-social-login' ), $user->display_name ),
			/* translators: %s: email address. */
			sprintf( __( 'Email: %s', 'nhrrob-social-login' ), $user->user_email ),
			/* translators: %s: how they signed in, such as Google. */
			sprintf( __( 'Signed up with: %s', 'nhrrob-social-login' ), $how ),
		];

		$post_id = 'comment' === ( isset( $context['source'] ) ? $context['source'] : '' ) ? url_to_postid( (string) $context['redirect_to'] ) : 0;

		if ( $post_id ) {
			/* translators: 1: post title, 2: post address. */
			$lines[] = sprintf( __( 'From the comment form of: %1$s (%2$s)', 'nhrrob-social-login' ), get_the_title( $post_id ), get_permalink( $post_id ) );
		}

		$lines[] = '';
		/* translators: %s: address of the user's edit screen. */
		$lines[] = sprintf( __( 'Edit the user: %s', 'nhrrob-social-login' ), add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) );

		return implode( "\n", $lines );
	}
}
