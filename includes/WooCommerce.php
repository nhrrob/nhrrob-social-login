<?php
/**
 * The buttons on WooCommerce's My account login and register forms, and
 * above its checkout login notice. Its hooks only fire when WooCommerce is
 * active, so there is no dependency on it.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Class
 *
 * A sign-in from these forms comes back to the same page, and a new account
 * gets WooCommerce's customer role (Accounts::new_role()).
 */
class WooCommerce {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'woocommerce_login_form_start', [ $this, 'buttons' ] );
		add_action( 'woocommerce_register_form_start', [ $this, 'register_buttons' ] );
		// The login notice is at priority 10.
		add_action( 'woocommerce_before_checkout_form', [ $this, 'buttons' ], 9 );
	}

	/**
	 * The buttons, for a visitor who is not logged in.
	 *
	 * @return void
	 */
	public function buttons() {
		if ( is_user_logged_in() ) {
			return;
		}

		$html = Buttons::render( Shortcode::current_url(), 'woocommerce' );

		if ( '' !== $html ) {
			Buttons::enqueue_style();
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Buttons::render().
		}
	}

	/**
	 * On the register form only when new accounts are allowed.
	 *
	 * @return void
	 */
	public function register_buttons() {
		if ( Settings::create_accounts() ) {
			$this->buttons();
		}
	}
}
