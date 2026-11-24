<?php
/**
 * The buttons anywhere: the [nhrrob_social_login] shortcode and the
 * "Social login buttons" block, both rendered on the server.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode Class
 *
 * `label` replaces "Continue with" before each provider's name, and
 * `redirect` is where people go after signing in (the same page when empty).
 * Nothing shows to a visitor who is logged in.
 */
class Shortcode {

	const TAG   = 'nhrrob_social_login';
	const BLOCK = 'nhrrob-social-login/buttons';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( self::TAG, [ $this, 'shortcode' ] );
		add_action( 'init', [ $this, 'block' ] );
	}

	/**
	 * The shortcode.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'label'    => '',
				'redirect' => '',
			],
			$atts,
			self::TAG
		);

		return self::html( (string) $atts['label'], (string) $atts['redirect'] );
	}

	/**
	 * Register the block and its editor script.
	 *
	 * @return void
	 */
	public function block() {
		$dir = dirname( NHRROB_SOCIAL_LOGIN_FILE ) . '/admin/build';

		if ( ! file_exists( $dir . '/block.asset.php' ) ) {
			return; // Not built yet: run `npm run build`.
		}

		$asset = require $dir . '/block.asset.php';

		wp_register_script( 'nhrrob-social-login-block', plugins_url( 'admin/build/block.js', NHRROB_SOCIAL_LOGIN_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'nhrrob-social-login-block', 'nhrrob-social-login' );

		register_block_type(
			self::BLOCK,
			[
				'api_version'     => 3,
				'title'           => __( 'Social login buttons', 'nhrrob-social-login' ),
				'category'        => 'widgets',
				'editor_script'   => 'nhrrob-social-login-block',
				'editor_style'    => 'nhrrob-social-login',
				'attributes'      => [
					'label'    => [
						'type'    => 'string',
						'default' => '',
					],
					'redirect' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'supports'        => [ 'html' => false ],
				'render_callback' => [ $this, 'render_block' ],
			]
		);

		wp_register_style( 'nhrrob-social-login', plugins_url( 'assets/login.css', NHRROB_SOCIAL_LOGIN_FILE ), [], NHRROB_SOCIAL_LOGIN_VERSION );
	}

	/**
	 * The block on the front end.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$html = self::html( isset( $attributes['label'] ) ? (string) $attributes['label'] : '', isset( $attributes['redirect'] ) ? (string) $attributes['redirect'] : '' );

		return '' === $html ? '' : '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * The buttons, returning to the page they are on unless told otherwise.
	 *
	 * @param string $label    Text before each provider's name.
	 * @param string $redirect Where to go after signing in.
	 * @return string
	 */
	private static function html( $label, $redirect ) {
		$redirect = '' !== trim( $redirect ) ? esc_url_raw( trim( $redirect ) ) : self::current_url();
		$html     = Buttons::render( $redirect, 'button', sanitize_text_field( $label ) );

		if ( '' !== $html ) {
			Buttons::enqueue_style();
		}

		return $html;
	}

	/**
	 * The address of the page being shown. Its host is checked with
	 * wp_validate_redirect() before anyone is sent there.
	 *
	 * @return string
	 */
	public static function current_url() {
		if ( ! isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}

		return esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() sanitizes the whole address.
	}
}
