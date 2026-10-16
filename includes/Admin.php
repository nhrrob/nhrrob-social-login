<?php
/**
 * Settings > Social Login: the Google client and whether new users get an account.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 */
class Admin {

	const PAGE = 'nhrrob-social-login';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register' ] );
	}

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public function menu() {
		add_options_page( __( 'Social Login', 'nhrrob-social-login' ), __( 'Social Login', 'nhrrob-social-login' ), 'manage_options', self::PAGE, [ $this, 'render' ] );
	}

	/**
	 * Register the option with its sanitizer.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			self::PAGE,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ Settings::class, 'sanitize_from_form' ],
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Print the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Settings::can_manage() ) {
			return;
		}

		$settings   = Settings::get();
		$has_secret = '' !== Settings::client_secret();
		$name       = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Social Login', 'nhrrob-social-login' ); ?></h1>
			<p><?php esc_html_e( 'Lets people log in, and comment, with their Google account.', 'nhrrob-social-login' ); ?></p>

			<h2><?php esc_html_e( 'Google client', 'nhrrob-social-login' ); ?></h2>
			<p>
				<?php esc_html_e( 'In the Google Cloud Console, under APIs & Services > Credentials, create an OAuth client ID of type "Web application" and add this address to its authorized redirect URIs:', 'nhrrob-social-login' ); ?>
			</p>
			<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( Settings::redirect_uri() ); ?>" onfocus="this.select();"></p>
			<p><?php esc_html_e( 'The client ID and secret are best kept in wp-config.php:', 'nhrrob-social-login' ); ?></p>
			<pre><code>define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID', '...' );
define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET', '...' );</code></pre>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="nhrrob-social-login-client-id"><?php esc_html_e( 'Client ID', 'nhrrob-social-login' ); ?></label></th>
						<td>
							<?php if ( Settings::id_in_config() ) : ?>
								<p><?php esc_html_e( 'Set in wp-config.php.', 'nhrrob-social-login' ); ?></p>
								<input type="hidden" name="<?php echo esc_attr( $name ); ?>[google_client_id]" value="<?php echo esc_attr( $settings['google_client_id'] ); ?>">
							<?php else : ?>
								<input type="text" class="large-text code" id="nhrrob-social-login-client-id" name="<?php echo esc_attr( $name ); ?>[google_client_id]" value="<?php echo esc_attr( $settings['google_client_id'] ); ?>" autocomplete="off">
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nhrrob-social-login-client-secret"><?php esc_html_e( 'Client secret', 'nhrrob-social-login' ); ?></label></th>
						<td>
							<?php if ( Settings::secret_in_config() ) : ?>
								<p><?php esc_html_e( 'Set in wp-config.php.', 'nhrrob-social-login' ); ?></p>
							<?php else : ?>
								<input type="password" class="regular-text code" id="nhrrob-social-login-client-secret" name="<?php echo esc_attr( $name ); ?>[google_client_secret]" value="" autocomplete="new-password" placeholder="<?php echo $has_secret ? esc_attr__( 'Saved. Type a new one to replace it.', 'nhrrob-social-login' ) : ''; ?>">
								<?php if ( $has_secret ) : ?>
									<p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[remove_secret]" value="1"> <?php esc_html_e( 'Remove the saved secret', 'nhrrob-social-login' ); ?></label></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'New users', 'nhrrob-social-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[create_accounts]" value="1" <?php checked( $settings['create_accounts'] ); ?>> <?php esc_html_e( 'Create an account, as a Subscriber, for a Google user who has none', 'nhrrob-social-login' ); ?></label>
							<p class="description"><?php esc_html_e( 'This also shows "Continue with Google to comment" above the comment form. It does not open WordPress\'s registration form ("Anyone can register" under General settings), which can stay off.', 'nhrrob-social-login' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
