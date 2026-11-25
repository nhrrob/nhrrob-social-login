<?php
/**
 * Settings > Social Login: the React app in admin/build, and the Settings
 * link on the Plugins screen.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 */
class Admin {

	const PAGE = 'nhrrob-social-login';

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string|false
	 */
	private $hook = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( NHRROB_SOCIAL_LOGIN_FILE ), [ $this, 'action_link' ] );
	}

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public function menu() {
		/**
		 * Filters the menu this screen sits under: '' keeps it where it is, a
		 * parent menu's slug moves it there (one menu for several plugins).
		 *
		 * @param string $parent Parent menu slug, or ''.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shared by every NHR plugin, so it can't carry this one's prefix.
		$parent = (string) apply_filters( 'nhr_admin_parent_menu', '' );

		$this->hook = '' === $parent
			? add_options_page( __( 'Social Login', 'nhrrob-social-login' ), __( 'Social Login', 'nhrrob-social-login' ), 'manage_options', self::PAGE, [ $this, 'render' ] )
			: add_submenu_page( $parent, __( 'Social Login', 'nhrrob-social-login' ), __( 'Social Login', 'nhrrob-social-login' ), 'manage_options', self::PAGE, [ $this, 'render' ] );
	}

	/**
	 * The settings screen's address.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Add "Settings" to the plugin's row on the Plugins screen.
	 *
	 * @param array $actions Links under the plugin.
	 * @return array
	 */
	public function action_link( $actions ) {
		if ( Settings::can_manage() ) {
			array_unshift( $actions, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'nhrrob-social-login' ) ) );
		}

		return $actions;
	}

	/**
	 * Print the mount point of the app.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Settings::can_manage() ) {
			return;
		}
		?>
		<div class="wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Social Login', 'nhrrob-social-login' ); ?></h1>
			<hr class="wp-header-end">
			<div id="nhrrob-social-login-app"></div>
			<noscript><p><?php esc_html_e( 'This screen needs JavaScript.', 'nhrrob-social-login' ); ?></p></noscript>
		</div>
		<?php
	}

	/**
	 * Enqueue the app on its own screen only.
	 *
	 * @param string $hook Hook suffix of the current screen.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$dir = dirname( NHRROB_SOCIAL_LOGIN_FILE ) . '/admin/build';

		if ( ! $this->hook || $hook !== $this->hook || ! file_exists( $dir . '/index.asset.php' ) ) {
			return; // Another screen, or not built yet: run `npm run build`.
		}

		$asset  = require $dir . '/index.asset.php';
		$url    = plugins_url( 'admin/build', NHRROB_SOCIAL_LOGIN_FILE );
		$handle = 'nhrrob-social-login-app';

		wp_enqueue_script( $handle, $url . '/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( $handle, 'nhrrob-social-login' );
		wp_localize_script(
			$handle,
			'nhrrobSocialLoginApp',
			[
				'restRoot' => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				// Shown under the app's name; React escapes it.
				'siteName' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'version'  => NHRROB_SOCIAL_LOGIN_VERSION,
			]
		);

		if ( file_exists( $dir . '/style-index.css' ) ) {
			// Own version: the asset hash covers the script only.
			wp_enqueue_style( $handle, $url . '/style-index.css', [], (string) filemtime( $dir . '/style-index.css' ) );
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}

		/**
		 * After the app's script was enqueued. Another plugin enqueues its own
		 * script here, depending on this handle, and adds screens through the
		 * `nhrrobSocialLogin.app` JavaScript filter.
		 *
		 * @param string $handle The app's script handle.
		 */
		do_action( 'nhrrob_social_login_enqueue', $handle );
	}
}
