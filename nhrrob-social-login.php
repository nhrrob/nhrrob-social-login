<?php
/**
 * Plugin Name: NHR Social Login
 * Plugin URI: https://github.com/nhrrob/nhrrob-social-login
 * Description: Log in, and comment, with Google, Microsoft, Apple, GitHub, Facebook, X, LinkedIn or a link by email.
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 0.3.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: nhrrob-social-login
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NHRRob\SocialLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class NHRRob_Social_Login {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const VERSION = '0.3.0';

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'NHRROB_SOCIAL_LOGIN_VERSION', self::VERSION );
		define( 'NHRROB_SOCIAL_LOGIN_FILE', __FILE__ );

		add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
	}

	/**
	 * Initializes a singleton instance
	 *
	 * @return self
	 */
	public static function init(): self {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Features started on every request, one class each.
	 */
	const FEATURES = [
		\NHRRob\SocialLogin\Login::class,
		\NHRRob\SocialLogin\Callback::class,
		\NHRRob\SocialLogin\Buttons::class,
		\NHRRob\SocialLogin\Profile::class,
		\NHRRob\SocialLogin\Avatar::class,
		\NHRRob\SocialLogin\Shortcode::class,
		\NHRRob\SocialLogin\OneTap::class,
		\NHRRob\SocialLogin\WooCommerce::class,
		\NHRRob\SocialLogin\Notify::class,
		\NHRRob\SocialLogin\MagicLink::class,
		\NHRRob\SocialLogin\VerifiedMark::class,
		\NHRRob\SocialLogin\Abilities::class,
		\NHRRob\SocialLogin\Rest::class,
	];

	/**
	 * Initialize the plugin: every feature, and the settings app in the dashboard.
	 *
	 * @return void
	 */
	public function init_plugin(): void {
		foreach ( self::FEATURES as $feature ) {
			( new $feature() )->init();
		}

		if ( is_admin() ) {
			( new \NHRRob\SocialLogin\Admin() )->init();
		}
	}
}

/**
 * Initializes the main plugin
 *
 * @return NHRRob_Social_Login
 */
function nhrrob_social_login(): NHRRob_Social_Login {
	return NHRRob_Social_Login::init();
}

nhrrob_social_login();
