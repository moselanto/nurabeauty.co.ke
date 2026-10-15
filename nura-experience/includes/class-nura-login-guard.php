<?php
/**
 * NURA Login Guard - authentication, session and transport hardening.
 *
 * What this adds (all reversible with filters, nothing removed):
 *  1. Brute-force protection: failed logins are counted per IP and per
 *     username; after too many failures the IP/username is locked out for a
 *     cooling-off period. Works for wp-login.php, WooCommerce My Account and
 *     any other form that goes through wp_authenticate().
 *  2. Bot traps on login, registration and lost-password forms: a hidden
 *     honeypot field plus a minimum fill-time check.
 *  3. Rate limits on account registration and password-reset requests so the
 *     site cannot be used to spam inboxes or create junk accounts.
 *  4. Password policy: new/reset passwords must be at least 10 characters and
 *     not be the user's email or username. WordPress core already hashes
 *     passwords (bcrypt in WP 6.8+); passwords are never stored or logged here.
 *  5. Session expiry: staff accounts (anyone who can edit posts or manage the
 *     shop) get short sessions - 8 hours, or 3 days with "Remember me".
 *     Customers keep the WordPress defaults (2 / 14 days).
 *  6. HTTPS everywhere: plain-HTTP front-end requests are 301-redirected to
 *     HTTPS when the site URL is HTTPS; secure headers also on login/admin.
 *  7. CORS: the REST API only reflects our own origin (plus any origins added
 *     via the nurax_cors_allowed_origins filter) instead of any caller.
 *  8. Application passwords limited to administrators.
 *
 * Client IP: REMOTE_ADDR is used. If the site sits behind Cloudflare or a
 * trusted proxy, define NURAX_TRUSTED_PROXY_HEADER (e.g. 'HTTP_CF_CONNECTING_IP')
 * in wp-config.php so every visitor is not counted as the proxy's IP.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Login_Guard {

	/** Failed attempts allowed per IP inside the window. */
	const IP_MAX = 5;

	/** Failed attempts allowed per username inside the window. */
	const USER_MAX = 10;

	/** Counting window and lockout length (seconds). */
	const WINDOW  = 900;   // 15 minutes.
	const LOCKOUT = 1800;  // 30 minutes.

	/** Minimum seconds a human needs to fill a login/registration form. */
	const MIN_FILL_SECONDS = 2;

	/** Minimum password length for new and reset passwords. */
	const MIN_PASSWORD = 10;

	public function __construct() {
		// 1. Brute-force protection.
		add_filter( 'authenticate', array( $this, 'block_if_locked' ), 30, 3 );
		add_action( 'wp_login_failed', array( $this, 'record_failure' ), 10, 2 );
		add_action( 'wp_login', array( $this, 'clear_failures' ), 10, 1 );

		// 2. Bot traps (wp-login.php + WooCommerce forms).
		add_action( 'login_form', array( $this, 'trap_fields' ) );
		add_action( 'register_form', array( $this, 'trap_fields' ) );
		add_action( 'lostpassword_form', array( $this, 'trap_fields' ) );
		add_action( 'woocommerce_login_form', array( $this, 'trap_fields' ) );
		add_action( 'woocommerce_register_form', array( $this, 'trap_fields' ) );
		add_action( 'woocommerce_lostpassword_form', array( $this, 'trap_fields' ) );
		add_filter( 'authenticate', array( $this, 'check_trap_on_login' ), 20, 3 );

		// 3. Registration + lost-password limits.
		add_filter( 'registration_errors', array( $this, 'guard_registration' ), 10, 1 );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'guard_registration' ), 10, 1 );
		add_action( 'lostpassword_post', array( $this, 'guard_lostpassword' ), 10, 1 );

		// 4. Password policy.
		add_action( 'validate_password_reset', array( $this, 'check_password_reset' ), 10, 2 );
		add_action( 'user_profile_update_errors', array( $this, 'check_profile_password' ), 10, 3 );
		add_action( 'woocommerce_save_account_details_errors', array( $this, 'check_account_password' ), 10, 2 );

		// 5. Session expiry for staff.
		add_filter( 'auth_cookie_expiration', array( $this, 'session_length' ), 10, 3 );

		// 6. HTTPS + headers on screens the theme does not cover.
		add_action( 'template_redirect', array( $this, 'force_https' ), 0 );
		add_action( 'login_init', array( $this, 'login_headers' ) );
		add_action( 'admin_init', array( $this, 'login_headers' ) );

		// 7. CORS.
		add_action( 'rest_api_init', array( $this, 'tighten_cors' ), 15 );

		// 8. Application passwords for administrators only.
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'app_passwords_for_admins' ), 10, 2 );
	}

	/* ------------------------------------------------------------ helpers */

	/** Visitor IP, honouring a trusted proxy header only when configured. */
	public static function ip() {
		$ip = '';
		if ( defined( 'NURAX_TRUSTED_PROXY_HEADER' ) && ! empty( $_SERVER[ NURAX_TRUSTED_PROXY_HEADER ] ) ) {
			$raw = explode( ',', (string) wp_unslash( $_SERVER[ NURAX_TRUSTED_PROXY_HEADER ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
			$ip  = trim( $raw[0] );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/** Increment a counter stored in a transient and return the new value. */
	private static function bump( $key, $ttl ) {
		$n = (int) get_transient( $key ) + 1;
		set_transient( $key, $n, $ttl );
		return $n;
	}

	private static function ip_key( $suffix ) {
		return 'nurax_lg_' . $suffix . '_' . md5( self::ip() );
	}

	private static function user_key( $suffix, $username ) {
		return 'nurax_lg_' . $suffix . '_' . md5( strtolower( trim( (string) $username ) ) );
	}

	/* ------------------------------------------------ 1. brute-force guard */

	public function block_if_locked( $user, $username, $password ) {
		if ( get_transient( self::ip_key( 'lock' ) ) || ( $username && get_transient( self::user_key( 'ulock', $username ) ) ) ) {
			return new WP_Error(
				'nurax_locked',
				__( 'Too many failed sign-in attempts. Please wait 30 minutes or reset your password.', 'nura-experience' )
			);
		}
		return $user;
	}

	public function record_failure( $username, $error = null ) {
		// Attempts made while already locked out (or stopped by the bot trap) must not
		// restart the lockout clock, otherwise a real owner can never get back in.
		if ( is_wp_error( $error ) && array_intersect( array( 'nurax_locked', 'nurax_bot' ), $error->get_error_codes() ) ) {
			return;
		}
		if ( get_transient( self::ip_key( 'lock' ) ) ) {
			return;
		}
		if ( self::bump( self::ip_key( 'fail' ), self::WINDOW ) >= (int) apply_filters( 'nurax_login_ip_max', self::IP_MAX ) ) {
			set_transient( self::ip_key( 'lock' ), 1, self::LOCKOUT );
		}
		if ( $username && self::bump( self::user_key( 'ufail', $username ), HOUR_IN_SECONDS ) >= (int) apply_filters( 'nurax_login_user_max', self::USER_MAX ) ) {
			set_transient( self::user_key( 'ulock', $username ), 1, self::LOCKOUT );
		}
	}

	public function clear_failures( $username ) {
		delete_transient( self::ip_key( 'fail' ) );
		delete_transient( self::user_key( 'ufail', $username ) );
	}

	/* ------------------------------------------------------ 2. bot traps */

	public function trap_fields() {
		echo '<p class="nurax-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
			. '<label>' . esc_html__( 'Leave this field empty', 'nura-experience' ) . '<input type="text" name="nurax_hp" value="" tabindex="-1" autocomplete="off"></label></p>'
			. '<input type="hidden" name="nurax_ts" value="' . esc_attr( (string) time() ) . '">';
	}

	/** True when the current POST looks automated. Forms without our fields (API, apps) pass. */
	private static function trap_tripped() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- anti-bot fields, not state changes.
		if ( ! empty( $_POST['nurax_hp'] ) ) {
			return true;
		}
		if ( isset( $_POST['nurax_ts'] ) ) {
			$ts = absint( $_POST['nurax_ts'] );
			if ( $ts && ( time() - $ts ) < self::MIN_FILL_SECONDS ) {
				return true;
			}
		}
		// phpcs:enable
		return false;
	}

	public function check_trap_on_login( $user, $username, $password ) {
		if ( self::trap_tripped() ) {
			return new WP_Error( 'nurax_bot', __( 'Invalid login details.', 'nura-experience' ) );
		}
		return $user;
	}

	/* ------------------------------------- 3. registration + reset limits */

	public function guard_registration( $errors ) {
		if ( self::trap_tripped() ) {
			$errors->add( 'nurax_bot', __( 'Registration could not be completed. Please try again.', 'nura-experience' ) );
			return $errors;
		}
		$max = (int) apply_filters( 'nurax_registrations_per_hour', 3 );
		if ( self::bump( self::ip_key( 'reg' ), HOUR_IN_SECONDS ) > $max ) {
			$errors->add( 'nurax_rate', __( 'Too many accounts created from this connection. Please try again later.', 'nura-experience' ) );
		}
		return $errors;
	}

	public function guard_lostpassword( $errors ) {
		if ( ! is_wp_error( $errors ) ) {
			return;
		}
		if ( self::trap_tripped() ) {
			$errors->add( 'nurax_bot', __( 'Please try again.', 'nura-experience' ) );
			return;
		}
		$max = (int) apply_filters( 'nurax_password_resets_per_hour', 5 );
		if ( self::bump( self::ip_key( 'lost' ), HOUR_IN_SECONDS ) > $max ) {
			$errors->add( 'nurax_rate', __( 'Too many reset requests. Please try again in an hour.', 'nura-experience' ) );
		}
	}

	/* ----------------------------------------------- 4. password policy */

	/** Returns an error message, or '' when the password is acceptable. */
	private static function password_problem( $pass, $user = null ) {
		$pass = (string) $pass;
		if ( '' === $pass ) {
			return '';
		}
		if ( strlen( $pass ) < self::MIN_PASSWORD ) {
			/* translators: %d: minimum length */
			return sprintf( __( 'Please use a password of at least %d characters.', 'nura-experience' ), self::MIN_PASSWORD );
		}
		if ( $user instanceof WP_User ) {
			$lower = strtolower( $pass );
			if ( strtolower( $user->user_login ) === $lower || strtolower( $user->user_email ) === $lower ) {
				return __( 'Your password cannot be your username or email address.', 'nura-experience' );
			}
		}
		return '';
	}

	public function check_password_reset( $errors, $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- core verifies the reset key; passwords must not be altered.
		$pass = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$msg  = self::password_problem( $pass, $user instanceof WP_User ? $user : null );
		if ( $msg ) {
			$errors->add( 'nurax_weak_password', $msg );
		}
	}

	public function check_profile_password( $errors, $update, $user ) {
		$pass = isset( $user->user_pass ) ? $user->user_pass : '';
		$wpu  = ! empty( $user->ID ) ? get_userdata( $user->ID ) : null;
		$msg  = self::password_problem( $pass, $wpu ? $wpu : null );
		if ( $msg ) {
			$errors->add( 'nurax_weak_password', $msg );
		}
	}

	public function check_account_password( $errors, $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verifies the nonce; passwords must not be altered.
		$pass = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
		$wpu  = ( is_object( $user ) && ! empty( $user->ID ) ) ? get_userdata( $user->ID ) : null;
		$msg  = self::password_problem( $pass, $wpu ? $wpu : null );
		if ( $msg ) {
			$errors->add( 'nurax_weak_password', $msg );
		}
	}

	/* ------------------------------------------------ 5. session expiry */

	public function session_length( $length, $user_id, $remember ) {
		if ( $user_id && user_can( $user_id, 'edit_posts' ) ) {
			return $remember
				? (int) apply_filters( 'nurax_staff_session_remember', 3 * DAY_IN_SECONDS )
				: (int) apply_filters( 'nurax_staff_session', 8 * HOUR_IN_SECONDS );
		}
		return $length;
	}

	/* ---------------------------------------------- 6. HTTPS + headers */

	public function force_https() {
		if ( is_ssl() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! apply_filters( 'nurax_force_https', true ) ) {
			return;
		}
		if ( 0 !== strpos( home_url(), 'https://' ) ) {
			return; // Site is not configured for HTTPS; never create a redirect loop.
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		if ( wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		}
		wp_safe_redirect( 'https://' . $host . $uri, 301 );
		exit;
	}

	public function login_headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}

	/* ----------------------------------------------------------- 7. CORS */

	public function tighten_cors() {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', array( $this, 'send_cors' ) );
	}

	public function send_cors( $value ) {
		$origin = get_http_origin();
		if ( ! $origin ) {
			return $value;
		}
		$allowed = (array) apply_filters( 'nurax_cors_allowed_origins', array( untrailingslashit( home_url() ), untrailingslashit( site_url() ) ) );
		if ( in_array( untrailingslashit( $origin ), array_map( 'untrailingslashit', $allowed ), true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Vary: Origin', false );
		}
		return $value;
	}

	/* ------------------------------------------- 8. application passwords */

	public function app_passwords_for_admins( $available, $user ) {
		return $available && $user instanceof WP_User && user_can( $user, 'manage_options' );
	}
}

new NURAX_Login_Guard();
