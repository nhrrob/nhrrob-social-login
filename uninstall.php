<?php
/**
 * Fired when the plugin is deleted: removes everything it stored.
 *
 * @package NHRRob\SocialLogin
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove this plugin's option from the current site.
 *
 * @return void
 */
function nhrrob_social_login_uninstall_site() {
	global $wpdb;

	delete_option( 'nhrrob_social_login_settings' );

	// Sign-ins still in progress: ten-minute transients named after a hash.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- once, on uninstall; transients have no API to delete by prefix.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_nhrrob_social_login_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_nhrrob_social_login_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) as $nhrrob_social_login_site_id ) {
		switch_to_blog( $nhrrob_social_login_site_id );
		nhrrob_social_login_uninstall_site();
		restore_current_blog();
	}
} else {
	nhrrob_social_login_uninstall_site();
}

// Users are shared by the network: the links to their Google accounts go once. The accounts stay.
delete_metadata( 'user', 0, 'nhrrob_social_login_google_sub', '', true );
