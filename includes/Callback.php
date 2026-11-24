<?php
/**
 * The address the providers other than Google send people back to:
 * /nhrrob-social-login/callback/<provider>/, without a query string, which
 * Microsoft requires for personal accounts.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Callback Class
 *
 * One rewrite rule. On a site with plain permalinks the address is
 * /?nhrrob_social_login_callback=<provider> instead.
 */
class Callback {

	const QUERY_VAR = 'nhrrob_social_login_callback';
	const PATH      = 'nhrrob-social-login/callback/';
	const RULE      = '^nhrrob-social-login/callback/([a-z]+)/?$';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'rule' ] );
		add_action( 'wp_loaded', [ $this, 'maybe_flush' ] );
		add_filter( 'query_vars', [ $this, 'query_var' ] );
		add_action( 'parse_request', [ $this, 'handle' ] );
	}

	/**
	 * Add the rule.
	 *
	 * @return void
	 */
	public function rule() {
		add_rewrite_rule( self::RULE, 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Write the rules once if they do not have this one yet (after an
	 * update, or a change of permalinks), once every plugin added its own.
	 *
	 * @return void
	 */
	public function maybe_flush() {
		$rules = get_option( 'rewrite_rules' );

		if ( get_option( 'permalink_structure' ) && is_array( $rules ) && ! isset( $rules[ self::RULE ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Let WordPress read the query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Finish a sign-in that came back here.
	 *
	 * @param \WP $wp The request.
	 * @return void
	 */
	public function handle( $wp ) {
		if ( empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}

		( new Login() )->callback( sanitize_key( (string) $wp->query_vars[ self::QUERY_VAR ] ) );
	}

	/**
	 * The address back for a provider.
	 *
	 * @param string $provider Provider ID.
	 * @return string
	 */
	public static function url( $provider ) {
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( self::QUERY_VAR, $provider, home_url( '/' ) );
		}

		return home_url( '/' . self::PATH . $provider . '/' );
	}
}
