<?php
/**
 * The sign-in buttons: at the top of the login form, above the comment
 * fields, and wherever Shortcode or WooCommerce puts them.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buttons Class
 */
class Buttons {

	/**
	 * What `nhrrob_social_login_button_extra` may print.
	 */
	const EXTRA_HTML = [
		'label' => [
			'class' => true,
			'for'   => true,
		],
		'input' => [
			'type'  => true,
			'class' => true,
			'id'    => true,
			'name'  => true,
			'value' => true,
		],
		'span'  => [ 'class' => true ],
	];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'login_form', [ $this, 'login_buttons' ] );
		add_action( 'comment_form_top', [ $this, 'comment_buttons' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'login_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'front_assets' ] );
	}

	/**
	 * The buttons on the login form, returning to where the form would have.
	 *
	 * @return void
	 */
	public function login_buttons() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only read to send the person back there.
		$to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

		echo self::render( $to, 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * The buttons above the comment fields, for a visitor who is not logged
	 * in, when new accounts are allowed. They come back to the form, logged in.
	 *
	 * @return void
	 */
	public function comment_buttons() {
		if ( is_user_logged_in() || ! Settings::create_accounts() ) {
			return;
		}

		echo self::render( get_permalink() . '#respond', 'comment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * The buttons, as HTML. Empty for a logged-in visitor or when nothing is set up.
	 *
	 * @param string $redirect_to Where to go after signing in.
	 * @param string $source      login, comment, button or woocommerce.
	 * @param string $label       Text before each provider's name; '' for the default.
	 * @return string
	 */
	public static function render( $redirect_to, $source, $label = '' ) {
		$providers = Providers::active();

		if ( ( is_user_logged_in() && 'login' !== $source ) || ( ! $providers && ! MagicLink::on() ) ) {
			return '';
		}

		$html = '';

		foreach ( $providers as $id => $provider ) {
			if ( '' !== $label ) {
				$text = $label . ' ' . $provider->label();
			} elseif ( 'comment' === $source && 1 === count( $providers ) ) {
				/* translators: %s: provider name, such as Google. */
				$text = sprintf( __( 'Continue with %s to comment', 'nhrrob-social-login' ), $provider->label() );
			} else {
				/* translators: %s: provider name, such as Google. */
				$text = sprintf( __( 'Continue with %s', 'nhrrob-social-login' ), $provider->label() );
			}

			$html .= sprintf(
				'<a class="nhrrob-social-login__button is-%1$s" href="%2$s">%3$s<span>%4$s</span></a>',
				esc_attr( $id ),
				esc_url( Login::start_url( $id, $redirect_to, $source ) ),
				$provider->icon(),
				esc_html( $text )
			);
		}

		if ( MagicLink::on() ) {
			$html .= sprintf(
				'<a class="nhrrob-social-login__email" href="%1$s">%2$s</a>',
				esc_url( MagicLink::form_url( $redirect_to, $source ) ),
				esc_html__( 'Email me a sign-in link', 'nhrrob-social-login' )
			);
		}

		/**
		 * Extra markup after the buttons, inside their block: an add-on's
		 * checkbox, for example. Only labels, checkboxes and spans are kept.
		 *
		 * @param string $html        Markup, empty by default.
		 * @param string $redirect_to Where the buttons send the person after signing in.
		 */
		$extra = wp_kses( (string) apply_filters( 'nhrrob_social_login_button_extra', '', $redirect_to ), self::EXTRA_HTML );

		$lead = 'comment' === $source && count( $providers ) > 1 ? '<span class="nhrrob-social-login__lead">' . esc_html__( 'Sign in to comment', 'nhrrob-social-login' ) . '</span>' : '';

		return sprintf(
			'<div class="nhrrob-social-login is-%1$s">%2$s<span class="nhrrob-social-login__buttons">%3$s</span>%5$s<span class="nhrrob-social-login__or">%4$s</span></div>',
			esc_attr( $source ),
			$lead,
			$html,
			esc_html__( 'or', 'nhrrob-social-login' ),
			$extra
		);
	}

	/**
	 * The stylesheet, and the script that moves the buttons to the top of the login form.
	 *
	 * @return void
	 */
	public function login_assets() {
		if ( ! Providers::any() ) {
			return;
		}

		self::enqueue_style();
		// The login_form hook prints after the password; the script moves the buttons to the top of the form, above an "or".
		wp_enqueue_script( 'nhrrob-social-login', plugins_url( 'assets/login.js', NHRROB_SOCIAL_LOGIN_FILE ), [], NHRROB_SOCIAL_LOGIN_VERSION, true );
	}

	/**
	 * The stylesheet on posts that take comments. Shortcode, WooCommerce and
	 * VerifiedMark enqueue it where they print.
	 *
	 * @return void
	 */
	public function front_assets() {
		if ( is_singular() && comments_open() && ( Providers::any() || VerifiedMark::on() ) ) {
			self::enqueue_style();
		}
	}

	/**
	 * Enqueue the stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		wp_enqueue_style( 'nhrrob-social-login', plugins_url( 'assets/login.css', NHRROB_SOCIAL_LOGIN_FILE ), [], NHRROB_SOCIAL_LOGIN_VERSION );
	}
}
