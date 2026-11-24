<?php
/**
 * REST routes of the settings app: read and save the settings.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rest Class
 *
 * GET and POST nhrrob-social-login/v1/settings, for users who can manage
 * options. The client secret is never in an answer: only whether one is set.
 */
class Rest {

	const REST_NAMESPACE = 'nhrrob-social-login/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get' ],
					'permission_callback' => [ Settings::class, 'can_manage' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save' ],
					'permission_callback' => [ Settings::class, 'can_manage' ],
					'args'                => [
						'client_id'       => [ 'type' => 'string' ],
						'client_secret'   => [ 'type' => 'string' ],
						'remove_secret'   => [ 'type' => 'boolean' ],
						'create_accounts' => [ 'type' => 'boolean' ],
					],
				],
			]
		);
	}

	/**
	 * The settings and status, without the secret.
	 *
	 * @return \WP_REST_Response
	 */
	public function get() {
		return rest_ensure_response( self::state() );
	}

	/**
	 * Save the settings. An empty secret keeps the stored one; a value set in
	 * wp-config.php is left as it is.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save( $request ) {
		$current = Settings::get();
		$input   = [
			'google_client_id'     => Settings::id_in_config() || null === $request['client_id'] ? $current['google_client_id'] : (string) $request['client_id'],
			'google_client_secret' => Settings::secret_in_config() ? $current['google_client_secret'] : (string) $request['client_secret'],
			'remove_secret'        => ! Settings::secret_in_config() && (bool) $request['remove_secret'],
			'create_accounts'      => null === $request['create_accounts'] ? $current['create_accounts'] : (bool) $request['create_accounts'],
		];

		// Autoloaded: every page asks whether the buttons can be shown.
		update_option( Settings::OPTION, Settings::sanitize_from_form( $input ) );

		return rest_ensure_response( self::state() );
	}

	/**
	 * What the app shows.
	 *
	 * @return array
	 */
	public static function state() {
		return [
			'client_id'        => Settings::client_id(),
			'id_in_config'     => Settings::id_in_config(),
			'secret_set'       => '' !== Settings::client_secret(),
			'secret_in_config' => Settings::secret_in_config(),
			'create_accounts'  => Settings::create_accounts(),
			'redirect_uri'     => Settings::redirect_uri(),
			'ready'            => Settings::google_ready(),
			'linked_users'     => Accounts::linked_count(),
		];
	}
}
