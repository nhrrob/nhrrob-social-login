<?php
/**
 * PHPUnit bootstrap.
 *
 * Runs under the CLI test runner, never through WordPress. The plugin's files
 * exit without ABSPATH, so it is defined here before the autoloader loads them.
 *
 * @package NHRRob\SocialLogin
 */

define( 'ABSPATH', __DIR__ . '/' );

// No allow-list: Accounts::in_domains() is tested on its own.
define( 'NHRROB_SOCIAL_LOGIN_ALLOWED_DOMAINS', '' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::bootstrap();

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * The message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * The code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}
	}
}
