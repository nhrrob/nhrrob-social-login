<?php
/**
 * Plugin Name: NHR Social Login
 * Plugin URI: https://github.com/nhrrob/nhrrob-social-login
 * Description: Log in, and comment, with a Google account.
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 0.2.1
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
	const VERSION = '0.2.1';

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
	 * Initialize the plugin: the buttons, the sign-in flow, the abilities, the settings app and its routes.
	 *
	 * @return void
	 */
	public function init_plugin(): void {
		( new \NHRRob\SocialLogin\Login() )->init();
		( new \NHRRob\SocialLogin\Abilities() )->init();
		( new \NHRRob\SocialLogin\Rest() )->init();

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
