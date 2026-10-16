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
	delete_option( 'nhrrob_social_login_settings' );
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
