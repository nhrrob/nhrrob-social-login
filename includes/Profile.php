<?php
/**
 * "Sign-in accounts" on the profile screen: which provider accounts are
 * linked, link one while logged in, and unlink one while another way in is
 * left.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

use NHRRob\SocialLogin\Providers\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Profile Class
 *
 * Linking is only on a user's own profile, since it signs in at the provider
 * as them. Unlinking is on their own profile and, for anyone who may edit
 * them, on their edit screen. Each change has a nonce.
 */
class Profile {

	const UNLINK = 'nhrrob_social_login_unlink';
	const NOTICE = 'nhrrob_social_login_notice';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'show_user_profile', [ $this, 'section' ] );
		add_action( 'edit_user_profile', [ $this, 'section' ] );
		add_action( 'admin_post_' . self::UNLINK, [ $this, 'unlink' ] );
		add_action( 'admin_notices', [ $this, 'notice' ] );
		add_action( 'profile_update', [ $this, 'password_changed' ], 10, 2 );
		add_action( 'after_password_reset', [ $this, 'password_reset' ] );
	}

	/**
	 * The section on the profile and user edit screens.
	 *
	 * @param \WP_User $user The user being shown.
	 * @return void
	 */
	public function section( $user ) {
		$linked = Providers::linked( $user->ID );
		$own    = get_current_user_id() === $user->ID;
		$rows   = [];

		foreach ( Providers::all() as $id => $provider ) {
			if ( isset( $linked[ $id ] ) ) {
				$action = self::can_unlink( $user->ID, $id )
					? sprintf( '<a class="button" href="%1$s">%2$s</a>', esc_url( self::unlink_url( $user->ID, $id ) ), esc_html__( 'Unlink', 'nhrrob-social-login' ) )
					: '<span class="description">' . esc_html__( 'The only way in: set a password first to unlink it.', 'nhrrob-social-login' ) . '</span>';
				$rows[] = [ $provider, esc_html__( 'Linked', 'nhrrob-social-login' ), $action ];
			} elseif ( $own && $provider->active() ) {
				$link   = Login::start_url( $id, admin_url( 'profile.php' ), 'profile' );
				$rows[] = [ $provider, esc_html__( 'Not linked', 'nhrrob-social-login' ), sprintf( '<a class="button" href="%1$s">%2$s</a>', esc_url( $link ), esc_html__( 'Link', 'nhrrob-social-login' ) ) ];
			}
		}

		if ( ! $rows ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Sign-in accounts', 'nhrrob-social-login' ) . '</h2><table class="form-table" role="presentation"><tbody>';

		foreach ( $rows as $row ) {
			// The status and action are escaped above.
			printf( '<tr><th scope="row">%1$s</th><td>%2$s &nbsp; %3$s</td></tr>', esc_html( $row[0]->label() ), $row[1], $row[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</tbody></table>';
	}

	/**
	 * Link a provider account to the logged-in user who started it from their profile.
	 *
	 * @param int      $user_id  User who started the link.
	 * @param Provider $provider Provider.
	 * @param array    $identity Verified identity.
	 * @return void
	 */
	public static function link( $user_id, Provider $provider, array $identity ) {
		$result = 'linked';
		$other  = Accounts::linked_user( $provider->meta_key(), $identity['sub'] );

		if ( get_current_user_id() !== $user_id ) {
			$result = 'link_failed';
		} elseif ( $other && $other->ID !== $user_id ) {
			$result = 'link_taken';
		} elseif ( '' !== (string) get_user_meta( $user_id, $provider->meta_key(), true ) && ! $other ) {
			$result = 'link_other';
		} else {
			update_user_meta( $user_id, $provider->meta_key(), $identity['sub'] );

			if ( ! empty( $identity['picture'] ) ) {
				update_user_meta( $user_id, Accounts::META_PICTURE, $identity['picture'] );
			}
		}

		wp_safe_redirect( add_query_arg( self::NOTICE, $result, admin_url( 'profile.php' ) ) );
		exit;
	}

	/**
	 * Unlink a provider account.
	 *
	 * @return void
	 */
	public function unlink() {
		$user_id  = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked next, with the user and provider in the action.
		$provider = Providers::get( isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked next.

		if ( ! $provider ) {
			wp_die( esc_html__( 'Unknown provider.', 'nhrrob-social-login' ), '', [ 'response' => 400 ] );
		}

		check_admin_referer( self::UNLINK . '_' . $user_id . '_' . $provider->id() );

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You may not change this user.', 'nhrrob-social-login' ), '', [ 'response' => 403 ] );
		}

		$result = 'unlink_last';

		if ( self::can_unlink( $user_id, $provider->id() ) ) {
			delete_user_meta( $user_id, $provider->meta_key() );
			$result = 'unlinked';
		}

		$back = get_current_user_id() === $user_id ? admin_url( 'profile.php' ) : add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );

		wp_safe_redirect( add_query_arg( self::NOTICE, $result, $back ) );
		exit;
	}

	/**
	 * Whether a link can go: the user keeps a password they know, or another linked account.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $provider Provider ID.
	 * @return bool
	 */
	public static function can_unlink( $user_id, $provider ) {
		$others = array_diff_key( Providers::linked( $user_id ), [ $provider => true ] );

		return $others || ! get_user_meta( $user_id, Accounts::META_PASSWORD, true );
	}

	/**
	 * The address that unlinks a provider account.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $provider Provider ID.
	 * @return string
	 */
	private static function unlink_url( $user_id, $provider ) {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'   => self::UNLINK,
					'user_id'  => $user_id,
					'provider' => $provider,
				],
				admin_url( 'admin-post.php' )
			),
			self::UNLINK . '_' . $user_id . '_' . $provider
		);
	}

	/**
	 * The notice after a link or unlink.
	 *
	 * @return void
	 */
	public function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks one of the fixed messages below.
		$code  = isset( $_GET[ self::NOTICE ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE ] ) ) : '';
		$texts = [
			'linked'      => [ 'success', __( 'The account is linked. You can sign in with it now.', 'nhrrob-social-login' ) ],
			'unlinked'    => [ 'success', __( 'The account is unlinked.', 'nhrrob-social-login' ) ],
			'unlink_last' => [ 'error', __( 'That is the only way in left. Set a password, or link another account, first.', 'nhrrob-social-login' ) ],
			'link_taken'  => [ 'error', __( 'That account is already linked to another user.', 'nhrrob-social-login' ) ],
			'link_other'  => [ 'error', __( 'Another account at that provider is linked already. Unlink it first.', 'nhrrob-social-login' ) ],
			'link_failed' => [ 'error', __( 'The account could not be linked. Please try again.', 'nhrrob-social-login' ) ],
		];

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! isset( $texts[ $code ] ) || ! $screen || ! in_array( $screen->id, [ 'profile', 'user-edit' ], true ) ) {
			return;
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $texts[ $code ][0] ), esc_html( $texts[ $code ][1] ) );
	}

	/**
	 * A user who set a password on the profile screen has a way in without a provider.
	 *
	 * @param int      $user_id  User ID.
	 * @param \WP_User $old_data The user before the change.
	 * @return void
	 */
	public function password_changed( $user_id, $old_data ) {
		$user = get_userdata( $user_id );

		if ( $user && $old_data && $user->user_pass !== $old_data->user_pass ) {
			delete_user_meta( $user_id, Accounts::META_PASSWORD );
		}
	}

	/**
	 * The same after "Lost your password?".
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public function password_reset( $user ) {
		delete_user_meta( $user->ID, Accounts::META_PASSWORD );
	}
}
