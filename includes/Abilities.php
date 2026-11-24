<?php
/**
 * Registers the plugin's abilities with the WordPress Abilities API.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What AI agents and MCP clients may ask the plugin (WordPress 6.9+).
 *
 * Read-only: which providers are set up and how many accounts use them.
 * Never a secret, an email address or a username.
 */
class Abilities {

	const CATEGORY = 'nhrrob-social-login';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register' ] );
	}

	/**
	 * Register the category.
	 *
	 * @return void
	 */
	public function register_category() {
		// The hook only exists on 6.9+; the check is for Plugin Check ("Requires at least" is 6.6).
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::CATEGORY,
				[
					'label'       => __( 'Social Login', 'nhrrob-social-login' ),
					'description' => __( 'Logging in, and commenting, with Google and other accounts.', 'nhrrob-social-login' ),
				]
			);
		}
	}

	/**
	 * Register every ability.
	 *
	 * @return void
	 */
	public function register() {
		if ( function_exists( 'wp_register_ability' ) ) {
			foreach ( $this->definitions() as $name => $args ) {
				wp_register_ability( $name, $args );
			}
		}
	}

	/**
	 * Every ability: name => wp_register_ability() arguments.
	 *
	 * @return array
	 */
	public function definitions() {
		return [
			'nhrrob-social-login/get-status' => [
				'label'               => __( 'Get social login status', 'nhrrob-social-login' ),
				'description'         => __( 'Returns each sign-in provider (Google, Microsoft, Apple, GitHub, Facebook, X, LinkedIn): whether it is on and set up, whether each credential is set and comes from wp-config.php, and the redirect URI to register with it. Also whether new users get an account and with which role, the other options, and how many users are linked to a provider.', 'nhrrob-social-login' ),
				'category'            => self::CATEGORY,
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'get_status' ],
				'permission_callback' => [ Settings::class, 'can_manage' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					'mcp'          => [ 'public' => true ],
				],
			],
		];
	}

	/**
	 * Setup and usage, without secrets or personal data.
	 *
	 * @return array
	 */
	public function get_status() {
		$state     = Rest::state();
		$providers = [];

		foreach ( $state['providers'] as $provider ) {
			$fields = [];

			foreach ( $provider['fields'] as $field ) {
				$fields[ $field['name'] ] = [
					'set'       => $field['set'],
					'in_config' => $field['in_config'],
				];
			}

			$providers[ $provider['id'] ] = [
				'enabled'      => $provider['enabled'],
				'ready'        => $provider['ready'],
				'active'       => $provider['active'],
				'redirect_uri' => $provider['redirect_uri'],
				'fields'       => $fields,
			];
		}

		$settings = $state['settings'];
		unset( $settings['allowed_domains'], $settings['login_redirect'], $settings['signup_redirect'] );

		return [
			'providers'       => $providers,
			'settings'        => $settings,
			'allowed_domains' => count( Settings::allowed_domains() ),
			'linked_users'    => $state['linked_users'],
		];
	}
}
