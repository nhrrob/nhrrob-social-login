<?php
/**
 * A sign-in link by email, for readers without an account at any provider:
 * type an address, get a one-time link, come back logged in.
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MagicLink Class
 *
 * A screen of its own on the login page, wp-login.php?action=nhrrob_social_login_email,
 * like "Lost your password?". The link holds a random token; only its hash
 * is kept, in a transient that lasts 15 minutes and is deleted when used.
 * Opening the link shows a button, and only pressing it logs in, so a mail
 * scanner that opens links does not use the token up; a cookie set when the
 * page opens ties the press to that browser. The answer is the
 * same whether or not the address has an account. Requests are limited per
 * address and per IP address.
 */
class MagicLink {

	const ACTION    = 'nhrrob_social_login_email';
	const COOKIE    = 'nhrrob_social_login_email';
	const TTL       = 900;
	const PER_EMAIL = 3;
	const PER_IP    = 10;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'login_form_' . self::ACTION, [ $this, 'screen' ] );
	}

	/**
	 * Whether sign-in links are on.
	 *
	 * @return bool
	 */
	public static function on() {
		return (bool) Settings::value( 'magic_link' );
	}

	/**
	 * The address of the screen that asks for an email address.
	 *
	 * @param string $redirect_to Where to go after signing in.
	 * @param string $source      Where it started.
	 * @return string
	 */
	public static function form_url( $redirect_to, $source ) {
		return add_query_arg(
			array_filter(
				[
					'action'      => self::ACTION,
					'source'      => $source,
					'redirect_to' => rawurlencode( $redirect_to ),
				]
			),
			wp_login_url()
		);
	}

	/**
	 * The screen: ask for an address, send the link, or confirm it.
	 *
	 * @return void
	 */
	public function screen() {
		if ( ! self::on() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- sending checks a nonce in send(); the token does the job of one when confirming.
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		$post  = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
		// phpcs:enable

		if ( '' !== $token ) {
			$post ? $this->confirm( $token ) : $this->confirm_screen( $token );
		} elseif ( $post ) {
			$this->send();
		}

		$this->form();
	}

	/**
	 * Ask for an address.
	 *
	 * @param string $notice A message above the form.
	 * @return void
	 */
	private function form( $notice = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only carried on in the form.
		$to     = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$source = isset( $_REQUEST['source'] ) ? sanitize_key( wp_unslash( $_REQUEST['source'] ) ) : 'login';
		// phpcs:enable

		login_header( __( 'Email me a sign-in link', 'nhrrob-social-login' ), $notice ? '<p class="message">' . esc_html( $notice ) . '</p>' : '<p class="message">' . esc_html__( 'Type your email address, and we will send you a link that logs you in.', 'nhrrob-social-login' ) . '</p>' );
		?>
		<form name="nhrrob-social-login-email" id="loginform" action="<?php echo esc_url( self::form_url( $to, $source ) ); ?>" method="post">
			<p>
				<label for="nhrrob-social-login-email"><?php esc_html_e( 'Email address', 'nhrrob-social-login' ); ?></label>
				<input type="email" name="email" id="nhrrob-social-login-email" class="input" size="20" autocomplete="email" required>
			</p>
			<?php wp_nonce_field( self::ACTION ); ?>
			<p class="submit">
				<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Send the link', 'nhrrob-social-login' ); ?>">
			</p>
		</form>
		<p id="nav"><a href="<?php echo esc_url( wp_login_url( $to ) ); ?>"><?php esc_html_e( 'Log in another way', 'nhrrob-social-login' ); ?></a></p>
		<?php
		login_footer( 'nhrrob-social-login-email' );
		exit;
	}

	/**
	 * Send a link, when the address may sign in. The answer is the same either way.
	 *
	 * @return void
	 */
	private function send() {
		check_admin_referer( self::ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked above.
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$to     = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$source = isset( $_REQUEST['source'] ) ? sanitize_key( wp_unslash( $_REQUEST['source'] ) ) : 'login';
		// phpcs:enable

		if ( ! is_email( $email ) ) {
			$this->form( __( 'That is not an email address.', 'nhrrob-social-login' ) );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! self::allow( 'email', strtolower( $email ), self::PER_EMAIL ) || ! self::allow( 'ip', $ip, self::PER_IP ) ) {
			$this->form( __( 'Too many links were asked for. Please wait a while and try again.', 'nhrrob-social-login' ) );
		}

		$user = get_user_by( 'email', $email );

		if ( ( $user || Settings::create_accounts() ) && Accounts::domain_allowed( $email, $user ? $user : null ) ) {
			$token = Jwt::random();

			set_transient(
				self::key( $token ),
				[
					'email'       => $email,
					'redirect_to' => $to,
					'source'      => $source,
				],
				self::TTL
			);

			wp_mail( $email, self::subject(), self::message( add_query_arg( 'token', $token, self::form_url( '', '' ) ) ) );
		}

		$this->form( __( 'If that address can sign in here, a link is on its way. It works once, for 15 minutes.', 'nhrrob-social-login' ) );
	}

	/**
	 * The page a link opens: a button that logs in.
	 *
	 * @param string $token Token.
	 * @return void
	 */
	private function confirm_screen( $token ) {
		$saved = get_transient( self::key( $token ) );

		if ( ! is_array( $saved ) ) {
			Login::fail( 'link_used' );
		}

		setcookie(
			self::COOKIE,
			hash( 'sha256', $token ),
			[
				'expires'  => time() + self::TTL,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			]
		);

		login_header( __( 'Log in', 'nhrrob-social-login' ) );
		?>
		<form id="loginform" action="<?php echo esc_url( self::form_url( '', '' ) ); ?>" method="post">
			<p>
				<?php
				/* translators: %s: email address. */
				echo esc_html( sprintf( __( 'Log in as %s?', 'nhrrob-social-login' ), $saved['email'] ) );
				?>
			</p>
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<p class="submit">
				<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Log in', 'nhrrob-social-login' ); ?>">
			</p>
		</form>
		<?php
		login_footer();
		exit;
	}

	/**
	 * Log in with a link's token, once.
	 *
	 * @param string $token Token.
	 * @return void
	 */
	private function confirm( $token ) {
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';

		if ( ! hash_equals( hash( 'sha256', $token ), $cookie ) ) {
			$this->confirm_screen( $token );
		}

		$saved = get_transient( self::key( $token ) );
		delete_transient( self::key( $token ) );
		setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

		if ( ! is_array( $saved ) || empty( $saved['email'] ) ) {
			Login::fail( 'link_used' );
		}

		$identity = [
			'sub'        => '',
			'email'      => (string) $saved['email'],
			'name'       => '',
			'given_name' => '',
			'picture'    => '',
		];
		$created  = false;
		$user     = Accounts::user_for( $identity, '', Settings::create_accounts(), $created, Accounts::new_role( (string) $saved['source'] ) );

		if ( is_wp_error( $user ) ) {
			Login::fail( $user->get_error_code() );
		}

		Login::log_in( $user, 'email', $created, (string) $saved['redirect_to'], in_array( $saved['source'], Login::SOURCES, true ) ? $saved['source'] : 'login' );
	}

	/**
	 * Count a request, and say whether it is within the limit (per hour).
	 *
	 * @param string $kind  'email' or 'ip'.
	 * @param string $value The address.
	 * @param int    $limit Requests allowed.
	 * @return bool
	 */
	private static function allow( $kind, $value, $limit ) {
		$key   = 'nhrrob_social_login_rate_' . $kind . '_' . hash( 'sha256', $value );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * The email's subject.
	 *
	 * @return string
	 */
	private static function subject() {
		/* translators: %s: site name. */
		return sprintf( __( '[%s] Your sign-in link', 'nhrrob-social-login' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/**
	 * The email's text.
	 *
	 * @param string $url The link.
	 * @return string
	 */
	private static function message( $url ) {
		return implode(
			"\n\n",
			[
				/* translators: %s: site name. */
				sprintf( __( 'Someone asked to sign in to %s with this email address.', 'nhrrob-social-login' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				__( 'Open this link to log in. It works once, for 15 minutes:', 'nhrrob-social-login' ),
				$url,
				__( 'If it was not you, ignore this email.', 'nhrrob-social-login' ),
			]
		);
	}

	/**
	 * Transient name of a link.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function key( $token ) {
		return 'nhrrob_social_login_magic_' . hash( 'sha256', $token );
	}
}
