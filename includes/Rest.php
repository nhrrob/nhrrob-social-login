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
 * options. A secret is never in an answer: only whether one is set.
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
						'providers' => [ 'type' => 'object' ],
						'settings'  => [ 'type' => 'object' ],
					],
				],
			]
		);
	}

	/**
	 * The settings and status, without secrets.
	 *
	 * @return \WP_REST_Response
	 */
	public function get() {
		return rest_ensure_response( self::state() );
	}

	/**
	 * Save the settings. An empty secret keeps the stored one unless its
	 * removal is asked for; a value set in wp-config.php is left as it is.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( $request ) {
		$stored    = Settings::get();
		$next      = $stored;
		$providers = is_array( $request['providers'] ) ? $request['providers'] : [];
		$settings  = is_array( $request['settings'] ) ? $request['settings'] : [];

		foreach ( Providers::all() as $id => $provider ) {
			$input = isset( $providers[ $id ] ) && is_array( $providers[ $id ] ) ? $providers[ $id ] : [];

			if ( isset( $input['enabled'] ) ) {
				$next[ $id . '_enabled' ] = (bool) $input['enabled'];
			}

			$remove = isset( $input['remove'] ) ? array_map( 'strval', (array) $input['remove'] ) : [];

			foreach ( $provider->fields() as $field => $spec ) {
				$key = $id . '_' . $field;

				if ( Settings::in_config( $id, $field ) ) {
					continue;
				}

				if ( ! empty( $spec['secret'] ) && in_array( $field, $remove, true ) ) {
					$next[ $key ] = '';
				} elseif ( isset( $input[ $field ] ) && is_scalar( $input[ $field ] ) && ( empty( $spec['secret'] ) || '' !== trim( (string) $input[ $field ] ) ) ) {
					$next[ $key ] = (string) $input[ $field ];
				}
			}
		}

		foreach ( array_keys( Settings::defaults() ) as $name ) {
			if ( array_key_exists( $name, $settings ) ) {
				$next[ $name ] = $settings[ $name ];
			}
		}

		$next = Settings::sanitize( $next );

		if ( $next['new_user_role'] !== $stored['new_user_role'] || $next['allow_author_roles'] !== $stored['allow_author_roles'] ) {
			if ( ! Accounts::safe_role( $next['new_user_role'], $next['allow_author_roles'] ) ) {
				return new \WP_Error( 'nhrrob_social_login_role', __( 'That role can write posts or manage the site. Pick another, or tick the warning to allow roles that can write posts.', 'nhrrob-social-login' ), [ 'status' => 400 ] );
			}
		}

		// Autoloaded: every page asks whether the buttons can be shown.
		update_option( Settings::OPTION, $next );

		return rest_ensure_response( self::state() );
	}

	/**
	 * What the app shows.
	 *
	 * @return array
	 */
	public static function state() {
		$providers = [];

		foreach ( Providers::all() as $id => $provider ) {
			$fields = [];

			foreach ( $provider->fields() as $field => $spec ) {
				$value    = Settings::credential( $id, $field );
				$fields[] = [
					'name'      => $field,
					'label'     => $spec['label'],
					'secret'    => ! empty( $spec['secret'] ),
					'multiline' => ! empty( $spec['multiline'] ),
					'value'     => empty( $spec['secret'] ) ? $value : '',
					'set'       => '' !== $value,
					'in_config' => Settings::in_config( $id, $field ),
					'constant'  => Settings::constant( $id, $field ),
				];
			}

			$providers[] = [
				'id'           => $id,
				'label'        => $provider->label(),
				'enabled'      => Settings::enabled( $id ),
				'ready'        => $provider->ready(),
				'active'       => $provider->active(),
				'redirect_uri' => $provider->redirect_uri(),
				'console_url'  => $provider->console_url(),
				'fields'       => $fields,
			];
		}

		$settings                      = array_intersect_key( Settings::get(), Settings::defaults() );
		$settings['allowed_domains']   = implode( "\n", Settings::allowed_domains() );
		$settings['domains_in_config'] = Settings::domains_in_config();

		return [
			'providers'    => $providers,
			'settings'     => $settings,
			'roles'        => self::roles(),
			'ready'        => (bool) Providers::active(),
			'linked_users' => Accounts::linked_count(),
			'woocommerce'  => class_exists( 'WooCommerce' ),
		];
	}

	/**
	 * The roles a new account may get: name, label and whether it can write posts.
	 *
	 * @return array
	 */
	private static function roles() {
		$roles = [];

		foreach ( wp_roles()->get_names() as $name => $label ) {
			if ( Accounts::safe_role( $name, true ) ) {
				$roles[] = [
					'name'   => $name,
					'label'  => translate_user_role( $label ),
					'writes' => ! Accounts::safe_role( $name, false ),
				];
			}
		}

		return $roles;
	}
}
