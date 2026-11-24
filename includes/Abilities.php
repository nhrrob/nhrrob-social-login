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
 * Read-only: whether Google sign-in is set up and how many accounts use it.
 * Never the client secret, an email address or a username.
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
					'description' => __( 'Logging in, and commenting, with a Google account.', 'nhrrob-social-login' ),
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
				'description'         => __( 'Returns whether Google sign-in is set up (client ID and secret present, and whether each comes from wp-config.php), whether a new Google user gets a subscriber account, the redirect URI to register with Google, and how many users are linked to a Google account.', 'nhrrob-social-login' ),
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
		return [
			'google'          => [
				'ready'            => Settings::google_ready(),
				'client_id_set'    => '' !== Settings::client_id(),
				'secret_set'       => '' !== Settings::client_secret(),
				'id_in_config'     => Settings::id_in_config(),
				'secret_in_config' => Settings::secret_in_config(),
				'redirect_uri'     => Settings::redirect_uri(),
			],
			'create_accounts' => Settings::create_accounts(),
			'linked_users'    => Accounts::linked_count(),
		];
	}
}
