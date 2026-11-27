<?php
/**
 * NURA Staff Login - authenticator-app sign-in for staff and a private login address.
 *
 * 1. Authenticator app for every administrator and shop manager (1.137.0).
 *    Uses the "Two Factor" plugin (already active on the live site). A staff
 *    account without an authenticator app can open only its Profile page until
 *    the app is set up (Users > Profile > Two-Factor Options). Once the app is
 *    on, e-mail codes are no longer accepted for that account; backup codes
 *    still work. Turning the app on or off e-mails the owner and the user and
 *    writes to the NURA Desk activity log.
 *    - Fails open: if the Two Factor plugin is not active nothing is enforced
 *      (admins see a warning), so nobody is locked out by a missing plugin.
 *    - Emergency switch: define( 'NURAX_STAFF_2FA_OFF', true ); in wp-config.php.
 *
 * 2. Private login address (1.137.0). When the owner saves an address in
 *    NURA Desk > Settings > Staff sign-in (for example /my-secret-door/), the
 *    login screen is served only there. /wp-login.php and /wp-admin/ answer
 *    "Page not found" to signed-out visitors. Signed-in staff use /wp-admin/
 *    as normal. Customers are not affected (they sign in on My Account).
 *    - The address is stored in the database (option nurax_login_slug), never
 *      in this public repository. Saving it e-mails it to the site admin e-mail.
 *    - Optional: define( 'NURAX_LOGIN_SLUG', 'my-secret-door' ); in wp-config.php.
 *    - Emergency switch: define( 'NURAX_LOGIN_SLUG_OFF', true ); in wp-config.php
 *      brings /wp-login.php back straight away.
 *    - admin-ajax.php and admin-post.php stay open (shop forms use them);
 *      ?action=postpass (password-protected pages) keeps working.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Staff_Login {

	const OPT_SLUG = 'nurax_login_slug';
	const TOTP     = 'Two_Factor_Totp';
	const EMAIL    = 'Two_Factor_Email';
	const BACKUP   = 'Two_Factor_Backup_Codes';
	const META_ON  = 'nurax_2fa_app_on';

	/** True when this request came in on the private login address. */
	private $via_slug = false;

	/** True when this request asked for /wp-login.php directly. */
	private $direct_login = false;

	public function __construct() {
		// 1. Authenticator app for staff.
		add_action( 'admin_init', array( $this, 'require_app' ), 2 );
		add_filter( 'two_factor_enabled_providers_for_user', array( $this, 'staff_providers' ), 20, 2 );
		add_filter( 'two_factor_primary_provider_for_user', array( $this, 'staff_primary' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'added_user_meta', array( $this, 'watch_providers' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'watch_providers' ), 10, 4 );

		// 2. Private login address.
		add_action( 'admin_post_nurax_login_slug', array( __CLASS__, 'h_save' ) );
		if ( '' !== self::slug() ) {
			add_action( 'plugins_loaded', array( $this, 'detect' ), 1 );
			add_action( 'wp_loaded', array( $this, 'route' ), 1 );
			add_filter( 'site_url', array( $this, 'filter_site_url' ), 100, 3 );
			add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 100, 3 );
			add_filter( 'wp_redirect', array( $this, 'filter_redirect' ), 100, 2 );
			add_filter( 'login_url', array( $this, 'filter_plain' ), 100 );
			add_filter( 'logout_url', array( $this, 'filter_plain' ), 100 );
			add_filter( 'lostpassword_url', array( $this, 'filter_plain' ), 100 );
			add_filter( 'register_url', array( $this, 'filter_plain' ), 100 );
			add_action( 'init', array( $this, 'no_admin_shortcuts' ) );
		}
	}

	/* ------------------------------------------------------------ helpers */

	/** Staff = administrators and shop managers (anyone who can run the shop). */
	public static function is_staff( $user ) {
		$user = is_numeric( $user ) ? get_userdata( (int) $user ) : $user;
		if ( ! $user instanceof WP_User || ! $user->ID ) {
			return false;
		}
		$staff = user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' );
		return (bool) apply_filters( 'nurax_2fa_staff', $staff, $user );
	}

	public static function two_factor_ready() {
		return class_exists( 'Two_Factor_Core' );
	}

	public static function enforced() {
		return self::two_factor_ready() && ! ( defined( 'NURAX_STAFF_2FA_OFF' ) && NURAX_STAFF_2FA_OFF );
	}

	/** Raw enabled providers (read from user meta, so our filter is not involved). */
	private static function raw_providers( $user_id ) {
		$p = get_user_meta( (int) $user_id, '_two_factor_enabled_providers', true );
		return is_array( $p ) ? $p : array();
	}

	/** True when the account has a working authenticator app. */
	public static function has_app( $user_id ) {
		return in_array( self::TOTP, self::raw_providers( $user_id ), true )
			&& '' !== (string) get_user_meta( (int) $user_id, '_two_factor_totp_key', true );
	}

	private static function setup_url() {
		return admin_url( 'profile.php?nurax_2fa=setup#two-factor-options' );
	}

	/* -------------------------------------------- 1. authenticator app */

	/** Staff without the app can only reach their Profile page. */
	public function require_app() {
		if ( ! self::enforced() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$u = wp_get_current_user();
		if ( ! $u->ID || ! self::is_staff( $u ) || self::has_app( $u->ID ) ) {
			return;
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'profile.php', 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( self::setup_url() );
		exit;
	}

	/** Once the app is on, staff no longer get e-mail codes (app or backup codes only). */
	public function staff_providers( $enabled, $user_id ) {
		if ( ! is_array( $enabled ) || ! self::enforced() || ! self::is_staff( $user_id ) || ! self::has_app( $user_id ) ) {
			return $enabled;
		}
		return array_values( array_diff( $enabled, array( self::EMAIL ) ) );
	}

	public function staff_primary( $provider, $user_id ) {
		if ( self::enforced() && self::is_staff( $user_id ) && self::has_app( $user_id ) ) {
			return self::TOTP;
		}
		return $provider;
	}

	public function notices() {
		$u = wp_get_current_user();
		if ( ! $u->ID || ! self::is_staff( $u ) ) {
			return;
		}
		if ( ! self::two_factor_ready() ) {
			if ( current_user_can( 'manage_options' ) && ! ( defined( 'NURAX_STAFF_2FA_OFF' ) && NURAX_STAFF_2FA_OFF ) ) {
				echo '<div class="notice notice-error"><p><strong>NURA security:</strong> the "Two Factor" plugin is not active, so staff are signing in with a password only. Go to Plugins and activate "Two Factor".</p></div>';
			}
			return;
		}
		if ( ! self::enforced() || self::has_app( $u->ID ) ) {
			return;
		}
		global $pagenow;
		if ( 'profile.php' !== $pagenow ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Set up your authenticator app to open NURA Desk and the rest of wp-admin.</strong></p><ol>'
			. '<li>Install Google Authenticator or Microsoft Authenticator on your phone.</li>'
			. '<li>Scroll down to <a href="#two-factor-options">Two-Factor Options</a>. Under Authenticator App, scan the QR code with the app and type the 6-digit code it shows.</li>'
			. '<li>Tick Authenticator App (and make it the primary method), then create Backup Recovery Codes. Print or write them down and keep them somewhere safe. Each code works once if you lose your phone.</li>'
			. '<li>Click Update Profile.</li></ol>'
			. '<p>From then on you sign in with your password plus the 6-digit code from the app.</p></div>';
	}

	/** Alert the owner and the user when an authenticator app is turned on or off for a staff account. */
	public function watch_providers( $meta_id, $user_id, $meta_key, $value ) {
		if ( '_two_factor_enabled_providers' !== $meta_key || ! self::is_staff( $user_id ) ) {
			return;
		}
		$now_on = is_array( $value ) && in_array( self::TOTP, $value, true );
		$was_on = '1' === (string) get_user_meta( (int) $user_id, self::META_ON, true );
		if ( $now_on === $was_on ) {
			return;
		}
		if ( $now_on ) {
			update_user_meta( (int) $user_id, self::META_ON, '1' );
		} else {
			delete_user_meta( (int) $user_id, self::META_ON );
		}
		$user = get_userdata( (int) $user_id );
		$what = $now_on ? 'turned ON' : 'turned OFF';
		$ip   = class_exists( 'NURAX_Login_Guard' ) ? NURAX_Login_Guard::ip() : '';
		$when = wp_date( 'j M Y, H:i' );
		$msg  = sprintf(
			"The authenticator app sign-in was %s for the staff account %s (%s) on %s from IP %s.\n\nIf this was not you or your team, sign in to wp-admin, open Users, reset that account's password and check Two-Factor Options.\n\nNURA Beauty",
			$what,
			$user ? $user->display_name : '#' . (int) $user_id,
			$user ? $user->user_login : '',
			$when,
			$ip
		);
		$to = array_unique( array_filter( array( get_option( 'admin_email' ), $user ? $user->user_email : '' ) ) );
		wp_mail( $to, 'NURA Beauty: authenticator app ' . ( $now_on ? 'turned on' : 'turned off' ) . ' for ' . ( $user ? $user->user_login : 'a staff account' ), $msg );
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'security', 'Authenticator app ' . ( $now_on ? 'turned on' : 'turned off' ) . ' for ' . ( $user ? $user->user_login : '#' . (int) $user_id ), array( 'level' => $now_on ? 'info' : 'warn' ) );
		}
	}

	/* ----------------------------------------- 2. private login address */

	/** Reserved words that cannot be used as the login address. */
	private static function reserved() {
		return array( 'wp-admin', 'wp-login', 'wp-login-php', 'login', 'admin', 'dashboard', 'wp-json', 'wp-content', 'wp-includes', 'my-account', 'shop', 'cart', 'checkout', 'product', 'products', 'category', 'tag', 'feed', 'sitemap', 'book-appointment', 'staff', 'signin', 'sign-in', 'account' );
	}

	/** Returns a clean slug or '' when the value is not allowed. */
	public static function clean_slug( $raw ) {
		$s = sanitize_title( (string) $raw );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{5,39}$/', $s ) || in_array( $s, self::reserved(), true ) ) {
			return '';
		}
		return $s;
	}

	/** Active private login slug, or '' when the feature is off. */
	public static function slug() {
		if ( defined( 'NURAX_LOGIN_SLUG_OFF' ) && NURAX_LOGIN_SLUG_OFF ) {
			return '';
		}
		if ( defined( 'NURAX_LOGIN_SLUG' ) ) {
			return self::clean_slug( NURAX_LOGIN_SLUG );
		}
		return self::clean_slug( get_option( self::OPT_SLUG, '' ) );
	}

	public static function login_address() {
		$s = self::slug();
		return $s ? trailingslashit( home_url( '/' . $s ) ) : '';
	}

	private static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared, never output.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return strtolower( untrailingslashit( rawurldecode( $path ) ) );
	}

	public function detect() {
		$path = self::request_path();
		$home = strtolower( untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) ) );
		if ( $path === $home . '/' . self::slug() ) {
			$this->via_slug       = true;
			$GLOBALS['pagenow']   = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		} elseif ( false !== strpos( $path, 'wp-login.php' ) ) {
			$this->direct_login = true;
		}
	}

	public function route() {
		if ( $this->via_slug ) {
			do_action( 'litespeed_control_set_nocache', 'nura private login' );
			nocache_headers();
			// Same globals wp-login.php expects when it runs at file level.
			global $error, $interim_login, $action, $user_login;
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
		if ( $this->direct_login ) {
			$act = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'postpass' === $act ) {
				return; // Password-protected pages.
			}
			self::not_found();
		}
		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			global $pagenow;
			if ( ! in_array( $pagenow, array( 'admin-post.php', 'admin-ajax.php' ), true ) ) {
				self::not_found();
			}
		}
	}

	private static function not_found() {
		do_action( 'litespeed_control_set_nocache', 'nura hidden login' );
		nocache_headers();
		status_header( 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Page not found - NURA Beauty</title>'
			. '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0E0E0E;color:#F3E9DC;font-family:Montserrat,Arial,sans-serif;text-align:center}a{color:#C9A24B}h1{font-family:"Playfair Display",Georgia,serif;font-weight:500}</style></head>'
			. '<body><main><h1>Page not found</h1><p>The page you are looking for is not here.</p><p><a href="' . esc_url( home_url( '/' ) ) . '">Go to the NURA Beauty shop</a></p></main></body></html>';
		exit;
	}

	/** Rewrites any wp-login.php URL to the private address (query string kept). */
	private function rewrite( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) || false !== strpos( $url, 'action=postpass' ) ) {
			return $url;
		}
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$frag  = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );
		return self::login_address() . ( '' !== $query ? '?' . $query : '' ) . ( '' !== $frag ? '#' . $frag : '' );
	}

	public function filter_site_url( $url, $path = '', $scheme = null ) {
		return $this->rewrite( $url );
	}

	public function filter_redirect( $location, $status = 302 ) {
		return $this->rewrite( $location );
	}

	public function filter_plain( $url ) {
		return $this->rewrite( $url );
	}

	/** /login, /admin and /dashboard must not redirect to the login screen. */
	public function no_admin_shortcuts() {
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}

	/* ------------------------------------------- NURA Desk settings card */

	public static function card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg  = isset( $_GET['nd_login'] ) ? sanitize_key( wp_unslash( $_GET['nd_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msgs = array(
			'saved'   => array( 'success', 'Private login address saved and e-mailed to ' . get_option( 'admin_email' ) . '. Bookmark it now. /wp-login.php and /wp-admin/ (signed out) now show Page not found.' ),
			'cleared' => array( 'success', 'Private login address removed. Staff sign in at /wp-admin/ again.' ),
			'bad'     => array( 'error', 'That address cannot be used. Use 6 to 40 lower-case letters, numbers or dashes, not a word like login, admin or shop, and not the address of an existing page.' ),
			'confirm' => array( 'error', 'Tick the box to confirm you have written the new address down.' ),
		);

		echo '<section class="nd-card nd-sec" id="nd-login"><header class="nd-card__h"><h2>Staff sign-in</h2>' . ( self::enforced() ? '<span class="nd-badge nd-badge--confirmed">App required</span>' : '<span class="nd-badge nd-badge--muted">Password only</span>' ) . '</header>';
		if ( isset( $msgs[ $msg ] ) ) {
			echo '<p class="nd-alert nd-alert--' . esc_attr( $msgs[ $msg ][0] ) . '">' . esc_html( $msgs[ $msg ][1] ) . '</p>';
		}

		// Authenticator app status per staff account.
		echo '<h3>Authenticator app</h3>';
		if ( ! self::two_factor_ready() ) {
			echo '<p class="nd-alert nd-alert--error">The "Two Factor" plugin is not active, so the app is not required. Activate it under Plugins.</p>';
		} elseif ( defined( 'NURAX_STAFF_2FA_OFF' ) && NURAX_STAFF_2FA_OFF ) {
			echo '<p class="nd-alert nd-alert--error">Switched off in wp-config.php (NURAX_STAFF_2FA_OFF). Remove that line to require the app again.</p>';
		} else {
			echo '<p class="nd-mut">Every administrator and shop manager signs in with their password plus a 6-digit code from Google Authenticator or Microsoft Authenticator. Staff without the app can only open their Profile page until they set it up.</p>';
		}
		$staff = get_users( array( 'role__in' => array( 'administrator', 'shop_manager' ), 'orderby' => 'display_name' ) );
		if ( $staff ) {
			echo '<table class="nd-table"><thead><tr><th>Name</th><th>Role</th><th>App</th></tr></thead><tbody>';
			foreach ( $staff as $s ) {
				$on = self::has_app( $s->ID );
				echo '<tr><td>' . esc_html( $s->display_name ) . ' <small class="nd-mut">' . esc_html( $s->user_login ) . '</small></td><td>' . esc_html( in_array( 'administrator', (array) $s->roles, true ) ? 'Owner / admin' : 'Shop manager' ) . '</td><td>' . ( $on ? '<span class="nd-badge nd-badge--confirmed">Set up</span>' : '<span class="nd-badge nd-badge--due">Not set up</span>' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p class="nd-small nd-mut">Lost phone: sign in with a backup code, or another administrator opens Users &gt; that person &gt; Two-Factor Options and turns the app off, then they set it up again on the new phone.</p>';

		// Private login address.
		echo '<h3>Private login address</h3>';
		$cur = self::login_address();
		if ( defined( 'NURAX_LOGIN_SLUG_OFF' ) && NURAX_LOGIN_SLUG_OFF ) {
			echo '<p class="nd-alert nd-alert--error">Switched off in wp-config.php (NURAX_LOGIN_SLUG_OFF). Staff sign in at /wp-admin/.</p>';
		} elseif ( $cur ) {
			echo '<p>Staff sign in at <strong><a href="' . esc_url( $cur ) . '">' . esc_html( $cur ) . '</a></strong>. Do not share it publicly. /wp-login.php and /wp-admin/ show Page not found to signed-out visitors.</p>';
		} else {
			echo '<p class="nd-mut">Not set: the login screen is still at /wp-admin/ and /wp-login.php, where bots try passwords every day.</p>';
		}
		if ( ! defined( 'NURAX_LOGIN_SLUG' ) ) {
			$form = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'nurax_login_slug', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_login_slug">';
			echo $form . '<input type="hidden" name="op" value="save"><div class="nd-fields">' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
				. '<label class="nd-field nd-field--wide"><span class="nd-label">New login address</span><span>' . esc_html( trailingslashit( home_url() ) ) . '<input type="text" name="slug" value="" maxlength="40" autocomplete="off" placeholder="e.g. crown-door-4821" style="max-width:260px"></span><small>6 to 40 lower-case letters, numbers or dashes. Pick something not easy to guess.</small></label>'
				. '<label class="nd-field nd-field--check"><input type="checkbox" name="confirm" value="1"><span><strong>I have written the new address down</strong><small>It is also e-mailed to ' . esc_html( get_option( 'admin_email' ) ) . '. Tell every staff member.</small></span></label>'
				. '</div><button class="nd-btn nd-btn--primary">' . ( $cur ? 'Change login address' : 'Save login address' ) . '</button></form>';
			if ( get_option( self::OPT_SLUG ) ) {
				echo '<details class="nd-sec__more"><summary>Go back to /wp-admin/</summary>' . $form . '<input type="hidden" name="op" value="clear"><p>Removes the private address. The login screen returns to /wp-login.php.</p><button class="nd-btn nd-btn--ghost">Remove private address</button></form></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '<details class="nd-sec__more"><summary>Locked out? Emergency switches</summary><p>Ask your host (or use the hosting File Manager) to add one of these lines to <code>wp-config.php</code>, above "That\'s all, stop editing":</p>'
			. '<pre class="nd-code">define( \'NURAX_LOGIN_SLUG_OFF\', true ); // login back at /wp-login.php' . "\n" . 'define( \'NURAX_STAFF_2FA_OFF\', true );  // stop requiring the app</pre><p class="nd-small nd-mut">Remove the line again once you are back in.</p></details>';
		echo '</section>';
	}

	/** Save or clear the private login address (administrators only). */
	public static function h_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only site administrators can change the login address.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_login_slug' );
		$back = NURAX_Desk::url( NURAX_Desk::P_SET );
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		if ( 'clear' === $op ) {
			delete_option( self::OPT_SLUG );
			self::audit( 'Private login address removed', 'warn' );
			do_action( 'litespeed_purge_all' );
			wp_safe_redirect( add_query_arg( 'nd_login', 'cleared', $back ) . '#nd-login' );
			exit;
		}
		$slug = self::clean_slug( isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clean_slug sanitizes.
		if ( '' === $slug || get_page_by_path( $slug, OBJECT, array( 'page', 'post', 'product' ) ) ) {
			wp_safe_redirect( add_query_arg( 'nd_login', 'bad', $back ) . '#nd-login' );
			exit;
		}
		if ( empty( $_POST['confirm'] ) ) {
			wp_safe_redirect( add_query_arg( 'nd_login', 'confirm', $back ) . '#nd-login' );
			exit;
		}
		update_option( self::OPT_SLUG, $slug, true );
		$url = trailingslashit( home_url( '/' . $slug ) );
		wp_mail(
			get_option( 'admin_email' ),
			'NURA Beauty: new staff login address',
			"The staff login address for NURA Beauty is now:\n\n" . $url . "\n\n/wp-login.php and /wp-admin/ now show Page not found to anyone who is not signed in. Keep this address private and share it only with staff.\n\nLocked out? Add define( 'NURAX_LOGIN_SLUG_OFF', true ); to wp-config.php to bring /wp-login.php back.\n\nNURA Beauty"
		);
		self::audit( 'Private login address changed', 'warn' );
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( add_query_arg( 'nd_login', 'saved', $back ) . '#nd-login' );
		exit;
	}

	private static function audit( $summary, $level = 'info' ) {
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'security', $summary, array( 'level' => $level ) );
		}
	}
}

new NURAX_Staff_Login();
