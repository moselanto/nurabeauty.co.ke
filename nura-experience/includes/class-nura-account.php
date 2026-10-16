<?php
/**
 * NURA Account - a premium, secure My Account (NURA Experience 1.63.0).
 *
 *  - Dashboard: welcome header, quick stats, latest order with tracking and
 *    invoice, shortcut tiles (plans, appointments and loyalty also show here).
 *  - Login & security: e-mail two-step verification, signed-in devices with
 *    "sign out everywhere else", recent sign-in history, new-device alerts.
 *  - Privacy & data: marketing choices (e-mail / WhatsApp) synced to NURA Desk,
 *    download my data and delete my account (WordPress privacy requests,
 *    Kenya Data Protection Act 2019).
 *  - Registration: required Terms/Privacy agreement + optional offers opt-in.
 *  - Orders: Invoice button on every order.
 *
 * Builds on NURA Login Guard (lockouts, bot traps, password policy, HTTPS).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Account {

	const EP_SEC  = 'login-security';
	const EP_PRIV = 'privacy-data';

	public function __construct() {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'query_vars' ) );
		add_action( 'init', array( $this, 'flush_once' ), 99 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu' ), 50 );
		add_filter( 'woocommerce_endpoint_' . self::EP_SEC . '_title', function () { return __( 'Login & security', 'nura-experience' ); } );
		add_filter( 'woocommerce_endpoint_' . self::EP_PRIV . '_title', function () { return __( 'Privacy & data', 'nura-experience' ); } );
		add_action( 'woocommerce_account_' . self::EP_SEC . '_endpoint', array( $this, 'security_page' ) );
		add_action( 'woocommerce_account_' . self::EP_PRIV . '_endpoint', array( $this, 'privacy_page' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'dashboard' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 40 );
		add_action( 'template_redirect', array( $this, 'handle_post' ), 5 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'order_actions' ), 10, 2 );
		// Sign-in protection.
		add_action( 'wp_login', array( $this, 'on_login' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'twofa_page' ), 1 );
		// Registration consent.
		add_action( 'woocommerce_register_form', array( $this, 'register_fields' ), 20 );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'register_check' ), 20, 4 );
		add_action( 'woocommerce_created_customer', array( $this, 'register_save' ), 20, 1 );
	}

	/* ------------------------------------------------------------------ */
	/* Endpoints + menu                                                    */
	/* ------------------------------------------------------------------ */

	public function query_vars( $vars ) {
		$vars[ self::EP_SEC ]  = self::EP_SEC;
		$vars[ self::EP_PRIV ] = self::EP_PRIV;
		return $vars;
	}

	public function flush_once() {
		if ( get_option( 'nurax_account_rw' ) !== '1.63.0' ) {
			flush_rewrite_rules( false );
			update_option( 'nurax_account_rw', '1.63.0', false );
		}
	}

	public function menu( $items ) {
		$out    = array();
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : __( 'Log out', 'nura-experience' );
		unset( $items['customer-logout'] );
		foreach ( $items as $k => $v ) {
			if ( 'downloads' === $k ) {
				continue; // NURA sells physical products only.
			}
			$out[ $k ] = $v;
		}
		$out[ self::EP_SEC ]      = __( 'Login & security', 'nura-experience' );
		$out[ self::EP_PRIV ]     = __( 'Privacy & data', 'nura-experience' );
		$out['customer-logout'] = $logout;
		return $out;
	}

	public function assets() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'nurax-account', NURAX_URL . 'assets/css/nura-account.css', array(), NURAX_VERSION );
		}
	}

	private static function url( $ep = '' ) {
		return $ep ? wc_get_endpoint_url( $ep, '', wc_get_page_permalink( 'myaccount' ) ) : wc_get_page_permalink( 'myaccount' );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	public static function device( $ua ) {
		$ua = (string) $ua;
		$os = preg_match( '/iPhone/i', $ua ) ? 'iPhone' : ( preg_match( '/iPad/i', $ua ) ? 'iPad' : ( preg_match( '/Android/i', $ua ) ? 'Android phone' : ( preg_match( '/Windows/i', $ua ) ? 'Windows computer' : ( preg_match( '/Mac OS X|Macintosh/i', $ua ) ? 'Mac' : ( preg_match( '/Linux/i', $ua ) ? 'Linux computer' : 'Unknown device' ) ) ) ) );
		$br = preg_match( '/Edg\//', $ua ) ? 'Edge' : ( preg_match( '/OPR|Opera/', $ua ) ? 'Opera' : ( preg_match( '/Chrome\//', $ua ) ? 'Chrome' : ( preg_match( '/Firefox\//', $ua ) ? 'Firefox' : ( preg_match( '/Safari\//', $ua ) ? 'Safari' : 'Browser' ) ) ) );
		return $br . ' on ' . $os;
	}

	private static function ip() {
		return class_exists( 'NURAX_Login_Guard' ) && method_exists( 'NURAX_Login_Guard', 'ip' ) ? (string) NURAX_Login_Guard::ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	}

	/** 41.90.x.x - enough to recognise, not enough to expose. */
	private static function mask_ip( $ip ) {
		if ( false !== strpos( $ip, '.' ) ) {
			$p = explode( '.', $ip );
			return $p[0] . '.' . ( isset( $p[1] ) ? $p[1] : 'x' ) . '.x.x';
		}
		return substr( $ip, 0, 9 ) . '::';
	}

	private static function notice( $msg, $type = 'success' ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $msg, $type );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                           */
	/* ------------------------------------------------------------------ */

	public function dashboard() {
		$u = wp_get_current_user();
		if ( ! $u || ! $u->ID ) {
			return;
		}
		$first  = get_user_meta( $u->ID, 'first_name', true );
		$first  = $first ? $first : ( get_user_meta( $u->ID, 'billing_first_name', true ) ? get_user_meta( $u->ID, 'billing_first_name', true ) : $u->display_name );
		$orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( array( 'customer_id' => $u->ID, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'paginate' => true ) ) : null;
		$count  = $orders ? (int) $orders->total : 0;
		$last   = $orders && $orders->orders ? $orders->orders[0] : null;
		$points = 0;
		if ( class_exists( 'NURAX_Circle' ) && defined( 'NURAX_Circle::POINTS_META' ) ) {
			$points = (int) get_user_meta( $u->ID, NURAX_Circle::POINTS_META, true );
		}
		$twofa  = (bool) get_user_meta( $u->ID, 'nura_2fa', true );
		$since  = date_i18n( 'F Y', strtotime( $u->user_registered ) );
		$hour   = (int) current_time( 'G' );
		$greet  = $hour < 12 ? __( 'Good morning', 'nura-experience' ) : ( $hour < 17 ? __( 'Good afternoon', 'nura-experience' ) : __( 'Good evening', 'nura-experience' ) );
		?>
		<section class="na-hero">
			<div class="na-hero__av" aria-hidden="true"><?php echo esc_html( strtoupper( mb_substr( $first, 0, 1 ) ) ); ?></div>
			<div class="na-hero__txt">
				<p class="na-eyebrow"><?php echo esc_html( $greet ); ?></p>
				<h2><?php echo esc_html( $first ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Member since %s', 'nura-experience' ), $since ) ); ?></p>
			</div>
			<div class="na-hero__sec <?php echo $twofa ? 'is-on' : ''; ?>">
				<span class="na-shield" aria-hidden="true"></span>
				<div><strong><?php echo $twofa ? esc_html__( 'Account protected', 'nura-experience' ) : esc_html__( 'Add extra protection', 'nura-experience' ); ?></strong>
				<a href="<?php echo esc_url( self::url( self::EP_SEC ) ); ?>"><?php echo $twofa ? esc_html__( 'Review security', 'nura-experience' ) : esc_html__( 'Turn on two-step verification', 'nura-experience' ); ?></a></div>
			</div>
		</section>

		<div class="na-stats">
			<a class="na-stat" href="<?php echo esc_url( self::url( 'orders' ) ); ?>"><small><?php esc_html_e( 'Orders', 'nura-experience' ); ?></small><strong><?php echo (int) $count; ?></strong></a>
			<?php if ( class_exists( 'NURAX_Circle' ) ) : ?><a class="na-stat" href="<?php echo esc_url( self::url( 'nura-circle' ) ); ?>"><small><?php esc_html_e( 'Radiance points', 'nura-experience' ); ?></small><strong><?php echo esc_html( number_format_i18n( $points ) ); ?></strong></a><?php endif; ?>
			<a class="na-stat" href="<?php echo esc_url( self::url( 'edit-address' ) ); ?>"><small><?php esc_html_e( 'Delivery address', 'nura-experience' ); ?></small><strong><?php echo get_user_meta( $u->ID, 'billing_address_1', true ) ? esc_html__( 'Saved', 'nura-experience' ) : esc_html__( 'Add', 'nura-experience' ); ?></strong></a>
		</div>

		<?php if ( $last ) : ?>
			<section class="na-card na-last">
				<header><h3><?php esc_html_e( 'Your latest order', 'nura-experience' ); ?></h3><span class="na-badge na-badge--<?php echo esc_attr( $last->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $last->get_status() ) ); ?></span></header>
				<div class="na-last__row">
					<div><strong>#<?php echo esc_html( $last->get_order_number() ); ?></strong><small><?php echo esc_html( $last->get_date_created() ? $last->get_date_created()->date_i18n( 'j F Y' ) : '' ); ?> - <?php echo esc_html( sprintf( _n( '%d item', '%d items', $last->get_item_count(), 'nura-experience' ), $last->get_item_count() ) ); ?></small></div>
					<strong class="na-last__total"><?php echo wp_kses_post( $last->get_formatted_order_total() ); ?></strong>
				</div>
				<?php $this->progress( $last ); ?>
				<div class="na-actions">
					<a class="na-btn" href="<?php echo esc_url( $last->get_view_order_url() ); ?>"><?php esc_html_e( 'View order', 'nura-experience' ); ?></a>
					<?php if ( class_exists( 'NURAX_Desk_POS' ) ) : ?><a class="na-btn na-btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( NURAX_Desk_POS::public_url( $last, NURAX_Desk_POS::is_paid( $last ) ? 'receipt' : 'invoice' ) ); ?>"><?php esc_html_e( 'Invoice / receipt', 'nura-experience' ); ?></a><?php endif; ?>
					<a class="na-btn na-btn--ghost" href="<?php echo esc_url( home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'Track delivery', 'nura-experience' ); ?></a>
				</div>
			</section>
		<?php else : ?>
			<section class="na-card na-empty"><h3><?php esc_html_e( 'Find your first crown', 'nura-experience' ); ?></h3><p><?php esc_html_e( 'Take the 60-second Wig Finder or browse the collection - pay with M-Pesa or spread it with Lipa Mdogo Mdogo.', 'nura-experience' ); ?></p><div class="na-actions"><a class="na-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Shop wigs', 'nura-experience' ); ?></a><a class="na-btn na-btn--ghost" href="<?php echo esc_url( home_url( '/ai-wig-finder/' ) ); ?>"><?php esc_html_e( 'Wig Finder', 'nura-experience' ); ?></a></div></section>
		<?php endif; ?>

		<nav class="na-tiles" aria-label="<?php esc_attr_e( 'Account shortcuts', 'nura-experience' ); ?>">
			<?php
			$tiles = array(
				array( self::url( 'orders' ), __( 'Orders', 'nura-experience' ), __( 'Track, invoices, buy again', 'nura-experience' ), 'bag' ),
				array( home_url( '/book-appointment/' ), __( 'Book appointment', 'nura-experience' ), __( 'Fitting, install, revamp', 'nura-experience' ), 'cal' ),
				array( self::url( 'edit-address' ), __( 'Addresses', 'nura-experience' ), __( 'Delivery details', 'nura-experience' ), 'pin' ),
				array( self::url( 'edit-account' ), __( 'Account details', 'nura-experience' ), __( 'Name, e-mail, password', 'nura-experience' ), 'user' ),
				array( self::url( self::EP_SEC ), __( 'Login & security', 'nura-experience' ), __( 'Two-step, devices', 'nura-experience' ), 'lock' ),
				array( self::url( self::EP_PRIV ), __( 'Privacy & data', 'nura-experience' ), __( 'Messages, your data', 'nura-experience' ), 'eye' ),
			);
			foreach ( $tiles as $t ) {
				echo '<a class="na-tile na-tile--' . esc_attr( $t[3] ) . '" href="' . esc_url( $t[0] ) . '"><span class="na-tile__ico" aria-hidden="true"></span><strong>' . esc_html( $t[1] ) . '</strong><small>' . esc_html( $t[2] ) . '</small></a>';
			}
			?>
		</nav>
		<?php
	}

	private function progress( $o ) {
		$st    = $o->get_status();
		$steps = array( __( 'Placed', 'nura-experience' ), __( 'Paid', 'nura-experience' ), __( 'Packed', 'nura-experience' ), __( 'Delivered', 'nura-experience' ) );
		$at    = 'completed' === $st ? 3 : ( in_array( $st, array( 'processing' ), true ) ? 2 : ( $o->is_paid() ? 1 : 0 ) );
		if ( in_array( $st, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			return;
		}
		echo '<ol class="na-track">';
		foreach ( $steps as $i => $l ) {
			echo '<li class="' . ( $i <= $at ? 'is-done' : '' ) . ( $i === $at ? ' is-now' : '' ) . '"><span></span>' . esc_html( $l ) . '</li>';
		}
		echo '</ol>';
	}

	public function order_actions( $actions, $order ) {
		if ( class_exists( 'NURAX_Desk_POS' ) && ! in_array( $order->get_status(), array( 'cancelled', 'failed' ), true ) ) {
			$actions['nura_invoice'] = array(
				'url'  => NURAX_Desk_POS::public_url( $order, NURAX_Desk_POS::is_paid( $order ) ? 'receipt' : 'invoice' ),
				'name' => NURAX_Desk_POS::is_paid( $order ) ? __( 'Receipt', 'nura-experience' ) : __( 'Invoice', 'nura-experience' ),
			);
		}
		return $actions;
	}

	/* ------------------------------------------------------------------ */
	/* Login & security                                                    */
	/* ------------------------------------------------------------------ */

	public function security_page() {
		$u        = wp_get_current_user();
		$twofa    = (bool) get_user_meta( $u->ID, 'nura_2fa', true );
		$alerts   = '0' !== get_user_meta( $u->ID, 'nura_login_alerts', true );
		$sessions = WP_Session_Tokens::get_instance( $u->ID )->get_all();
		$current  = wp_get_session_token();
		$hist     = get_user_meta( $u->ID, 'nura_login_hist', true );
		$hist     = is_array( $hist ) ? array_reverse( $hist ) : array();
		$score    = 2 + ( $twofa ? 2 : 0 ) + ( $alerts ? 1 : 0 );
		?>
		<div class="na-sec">
			<section class="na-card na-score">
				<div class="na-ring" style="--p:<?php echo (int) ( $score * 20 ); ?>"><span><?php echo (int) $score; ?>/5</span></div>
				<div><h3><?php echo $score >= 5 ? esc_html__( 'Your account is well protected', 'nura-experience' ) : esc_html__( 'Strengthen your account', 'nura-experience' ); ?></h3>
				<ul class="na-checks">
					<li class="is-ok"><?php esc_html_e( 'Strong password (10+ characters) required', 'nura-experience' ); ?></li>
					<li class="is-ok"><?php esc_html_e( 'Automatic lock after repeated wrong passwords', 'nura-experience' ); ?></li>
					<li class="<?php echo $twofa ? 'is-ok' : 'is-todo'; ?>"><?php esc_html_e( 'Two-step verification by e-mail', 'nura-experience' ); ?></li>
					<li class="<?php echo $alerts ? 'is-ok' : 'is-todo'; ?>"><?php esc_html_e( 'Alerts when you sign in on a new device', 'nura-experience' ); ?></li>
				</ul></div>
			</section>

			<section class="na-card">
				<header><h3><?php esc_html_e( 'Two-step verification', 'nura-experience' ); ?></h3><span class="na-badge <?php echo $twofa ? 'na-badge--completed' : ''; ?>"><?php echo $twofa ? esc_html__( 'On', 'nura-experience' ) : esc_html__( 'Off', 'nura-experience' ); ?></span></header>
				<p><?php echo esc_html( sprintf( __( 'When you sign in, we e-mail a 6-digit code to %s. Even if someone learns your password, they cannot get in without it.', 'nura-experience' ), self::mask_email( $u->user_email ) ) ); ?></p>
				<?php $this->form( 'toggle_2fa', $twofa ? __( 'Turn off', 'nura-experience' ) : __( 'Turn on two-step verification', 'nura-experience' ), $twofa ? 'na-btn--ghost' : '' ); ?>
			</section>

			<section class="na-card">
				<header><h3><?php esc_html_e( 'New sign-in alerts', 'nura-experience' ); ?></h3><span class="na-badge <?php echo $alerts ? 'na-badge--completed' : ''; ?>"><?php echo $alerts ? esc_html__( 'On', 'nura-experience' ) : esc_html__( 'Off', 'nura-experience' ); ?></span></header>
				<p><?php esc_html_e( 'We e-mail you when your account is opened on a phone or computer we have not seen before.', 'nura-experience' ); ?></p>
				<?php $this->form( 'toggle_alerts', $alerts ? __( 'Turn off', 'nura-experience' ) : __( 'Turn on', 'nura-experience' ), 'na-btn--ghost' ); ?>
			</section>

			<section class="na-card">
				<header><h3><?php esc_html_e( 'Where you are signed in', 'nura-experience' ); ?></h3><span class="na-muted"><?php echo esc_html( sprintf( _n( '%d device', '%d devices', count( $sessions ), 'nura-experience' ), count( $sessions ) ) ); ?></span></header>
				<ul class="na-devices">
					<?php foreach ( $sessions as $verifier => $s ) : $is = $current && hash_equals( (string) $verifier, hash( 'sha256', $current ) ); ?>
						<li class="<?php echo $is ? 'is-current' : ''; ?>"><span class="na-dev__ico" aria-hidden="true"></span><div><strong><?php echo esc_html( self::device( isset( $s['ua'] ) ? $s['ua'] : '' ) ); ?></strong><small><?php echo esc_html( ( isset( $s['ip'] ) ? self::mask_ip( $s['ip'] ) . ' - ' : '' ) . sprintf( __( 'signed in %s', 'nura-experience' ), isset( $s['login'] ) ? date_i18n( 'j M Y, H:i', $s['login'] + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) : '' ) ); ?></small></div><?php echo $is ? '<span class="na-badge na-badge--completed">' . esc_html__( 'This device', 'nura-experience' ) . '</span>' : ''; ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $sessions ) > 1 ) { $this->form( 'logout_others', __( 'Sign out of all other devices', 'nura-experience' ), 'na-btn--danger' ); } ?>
			</section>

			<?php if ( $hist ) : ?>
				<section class="na-card">
					<header><h3><?php esc_html_e( 'Recent sign-ins', 'nura-experience' ); ?></h3></header>
					<ul class="na-hist">
						<?php foreach ( array_slice( $hist, 0, 8 ) as $h ) : ?>
							<li><span><?php echo esc_html( $h['d'] ); ?></span><small><?php echo esc_html( date_i18n( 'D j M Y, H:i', strtotime( $h['t'] ) ) . ' - ' . $h['ip'] ); ?></small></li>
						<?php endforeach; ?>
					</ul>
					<p class="na-muted"><?php echo wp_kses_post( sprintf( __( 'Do not recognise one? <a href="%1$s">Change your password</a> and sign out of other devices.', 'nura-experience' ), esc_url( self::url( 'edit-account' ) ) ) ); ?></p>
				</section>
			<?php endif; ?>

			<section class="na-card na-promise">
				<h3><?php esc_html_e( 'How we protect your information', 'nura-experience' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Every page is encrypted (HTTPS).', 'nura-experience' ); ?></li>
					<li><?php esc_html_e( 'Your password is stored only as a one-way hash - nobody at NURA can see it.', 'nura-experience' ); ?></li>
					<li><?php esc_html_e( 'M-Pesa and card payments are handled by Paystack; we never see or keep your card or PIN.', 'nura-experience' ); ?></li>
					<li><?php esc_html_e( 'Repeated wrong passwords lock the account for a while to stop guessing.', 'nura-experience' ); ?></li>
					<li><?php esc_html_e( 'NURA staff will never ask for your password, PIN or code.', 'nura-experience' ); ?></li>
				</ul>
			</section>
		</div>
		<?php
	}

	private static function mask_email( $e ) {
		$p = explode( '@', (string) $e );
		return 2 === count( $p ) ? mb_substr( $p[0], 0, 2 ) . str_repeat( '*', max( 2, mb_strlen( $p[0] ) - 2 ) ) . '@' . $p[1] : $e;
	}

	private function form( $op, $label, $cls = '', $extra = '' ) {
		echo '<form method="post" class="na-form">' . $extra . '<input type="hidden" name="nura_acc" value="' . esc_attr( $op ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra is built from escaped parts.
		wp_nonce_field( 'nura_acc_' . $op, 'nura_acc_nonce' );
		echo '<button type="submit" class="na-btn ' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/* ------------------------------------------------------------------ */
	/* Privacy & data                                                      */
	/* ------------------------------------------------------------------ */

	private static function contact( $u ) {
		global $wpdb;
		if ( ! class_exists( 'NURAX_Desk_Mkt' ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk_Mkt::t_c() . ' WHERE user_id = %d OR email = %s LIMIT 1', $u->ID, strtolower( $u->user_email ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function privacy_page() {
		$u   = wp_get_current_user();
		$c   = self::contact( $u );
		$em  = $c ? (int) $c->email_ok && (int) $c->consent : false;
		$wa  = $c ? (int) $c->wa_ok && '' !== $c->phone : false;
		$req = get_user_meta( $u->ID, 'nura_privacy_req', true );
		?>
		<div class="na-sec">
			<section class="na-card">
				<header><h3><?php esc_html_e( 'Messages from NURA', 'nura-experience' ); ?></h3></header>
				<p><?php esc_html_e( 'Choose what you would like to hear about. Order updates, receipts and payment reminders are always sent.', 'nura-experience' ); ?></p>
				<form method="post" class="na-form na-prefs">
					<input type="hidden" name="nura_acc" value="prefs">
					<?php wp_nonce_field( 'nura_acc_prefs', 'nura_acc_nonce' ); ?>
					<label class="na-switch"><input type="checkbox" name="em" value="1" <?php checked( $em ); ?>><span></span><div><strong><?php esc_html_e( 'Offers and new arrivals by e-mail', 'nura-experience' ); ?></strong><small><?php echo esc_html( self::mask_email( $u->user_email ) ); ?></small></div></label>
					<label class="na-switch"><input type="checkbox" name="wa" value="1" <?php checked( $wa ); ?>><span></span><div><strong><?php esc_html_e( 'Offers and restock alerts on WhatsApp', 'nura-experience' ); ?></strong><small><?php esc_html_e( 'Uses your phone number below', 'nura-experience' ); ?></small></div></label>
					<input type="tel" name="phone" class="na-input" placeholder="<?php esc_attr_e( 'WhatsApp number, e.g. 0712 345 678', 'nura-experience' ); ?>" value="<?php echo esc_attr( $c && $c->phone ? '+' . $c->phone : get_user_meta( $u->ID, 'billing_phone', true ) ); ?>">
					<button type="submit" class="na-btn"><?php esc_html_e( 'Save my choices', 'nura-experience' ); ?></button>
				</form>
			</section>

			<section class="na-card">
				<header><h3><?php esc_html_e( 'Your data', 'nura-experience' ); ?></h3></header>
				<p><?php echo wp_kses_post( sprintf( __( 'Under Kenya\'s Data Protection Act, 2019 you can get a copy of the personal data we hold about you, or ask us to delete it. We confirm each request by e-mail first. See our <a href="%s">Privacy Policy</a>.', 'nura-experience' ), esc_url( home_url( '/privacy-policy/' ) ) ) ); ?></p>
				<?php if ( is_array( $req ) && ! empty( $req['t'] ) ) : ?><p class="na-note"><?php echo esc_html( sprintf( __( 'Request sent on %1$s (%2$s). Please confirm it from the e-mail we sent you.', 'nura-experience' ), date_i18n( 'j M Y', strtotime( $req['t'] ) ), 'export' === $req['type'] ? __( 'copy of my data', 'nura-experience' ) : __( 'delete my account', 'nura-experience' ) ) ); ?></p><?php endif; ?>
				<div class="na-actions">
					<?php $this->form( 'export', __( 'Download my data', 'nura-experience' ), 'na-btn--ghost' ); ?>
					<?php $this->form( 'erase', __( 'Delete my account and data', 'nura-experience' ), 'na-btn--danger', '' ); ?>
				</div>
				<p class="na-muted"><?php esc_html_e( 'Deletion removes your account and personal details. Order and payment records we must keep for tax purposes are anonymised rather than deleted.', 'nura-experience' ); ?></p>
			</section>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Form handling                                                       */
	/* ------------------------------------------------------------------ */

	public function handle_post() {
		if ( empty( $_POST['nura_acc'] ) || ! is_user_logged_in() ) {
			return;
		}
		$op = sanitize_key( wp_unslash( $_POST['nura_acc'] ) );
		if ( empty( $_POST['nura_acc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nura_acc_nonce'] ) ), 'nura_acc_' . $op ) ) {
			self::notice( __( 'Your session expired. Please try again.', 'nura-experience' ), 'error' );
			return;
		}
		$u    = wp_get_current_user();
		$back = self::url( in_array( $op, array( 'prefs', 'export', 'erase' ), true ) ? self::EP_PRIV : self::EP_SEC );
		switch ( $op ) {
			case 'toggle_2fa':
				$on = ! get_user_meta( $u->ID, 'nura_2fa', true );
				update_user_meta( $u->ID, 'nura_2fa', $on ? 1 : 0 );
				self::notice( $on ? __( 'Two-step verification is on. Next time you sign in we will e-mail you a code.', 'nura-experience' ) : __( 'Two-step verification is off.', 'nura-experience' ) );
				break;
			case 'toggle_alerts':
				$on = '0' === get_user_meta( $u->ID, 'nura_login_alerts', true );
				update_user_meta( $u->ID, 'nura_login_alerts', $on ? '1' : '0' );
				self::notice( $on ? __( 'Sign-in alerts are on.', 'nura-experience' ) : __( 'Sign-in alerts are off.', 'nura-experience' ) );
				break;
			case 'logout_others':
				WP_Session_Tokens::get_instance( $u->ID )->destroy_others( wp_get_session_token() );
				self::notice( __( 'You have been signed out of every other device.', 'nura-experience' ) );
				break;
			case 'prefs':
				$em    = ! empty( $_POST['em'] );
				$wa    = ! empty( $_POST['wa'] );
				$phone = isset( $_POST['phone'] ) && class_exists( 'NURAX_Desk' ) ? NURAX_Desk::phone( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
				if ( class_exists( 'NURAX_Desk_Mkt' ) ) {
					global $wpdb;
					$id = NURAX_Desk_Mkt::upsert( array( 'name' => $u->display_name, 'email' => $u->user_email, 'phone' => $phone, 'user_id' => $u->ID, 'source' => 'account', 'consent' => $em || $wa ) );
					if ( $id ) {
						$d = array( 'email_ok' => $em ? 1 : 0, 'wa_ok' => $wa ? 1 : 0, 'consent' => ( $em || $wa ) ? 1 : 0, 'updated_at' => current_time( 'mysql' ) );
						if ( $phone ) {
							$d['phone'] = $phone;
						}
						$wpdb->update( NURAX_Desk_Mkt::t_c(), $d, array( 'id' => $id ) );
					}
				}
				update_user_meta( $u->ID, 'nura_mkt_choice', array( 'em' => $em, 'wa' => $wa, 't' => current_time( 'mysql' ) ) );
				self::notice( __( 'Your choices are saved.', 'nura-experience' ) );
				break;
			case 'export':
			case 'erase':
				$type = 'export' === $op ? 'export_personal_data' : 'remove_personal_data';
				$rid  = wp_create_user_request( $u->user_email, $type );
				if ( is_wp_error( $rid ) ) {
					self::notice( 'duplicate_request' === $rid->get_error_code() ? __( 'You already have a request in progress. Please check your e-mail to confirm it.', 'nura-experience' ) : $rid->get_error_message(), 'notice' );
				} else {
					wp_send_user_request( $rid );
					update_user_meta( $u->ID, 'nura_privacy_req', array( 'type' => $op, 't' => current_time( 'mysql' ) ) );
					wp_mail( get_option( 'admin_email' ), 'NURA - customer privacy request (' . ( 'export' === $op ? 'copy of data' : 'delete account' ) . ')', $u->user_email . ' asked for: ' . ( 'export' === $op ? 'a copy of their data' : 'account deletion' ) . ". Once they confirm by e-mail, complete it under Tools > " . ( 'export' === $op ? 'Export' : 'Erase' ) . ' Personal Data.' );
					self::notice( __( 'Request received. Please confirm it from the e-mail we just sent you.', 'nura-experience' ) );
				}
				break;
		}
		wp_safe_redirect( $back );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Sign-in: history, new-device alert, two-step verification           */
	/* ------------------------------------------------------------------ */

	public function on_login( $login, $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$dev = self::device( $ua );
		$ip  = self::ip();

		// Two-step verification: hold the sign-in until the e-mailed code is entered.
		if ( get_user_meta( $user->ID, 'nura_2fa', true ) && empty( $GLOBALS['nura_2fa_passed'] ) ) {
			$remember = ! empty( $_POST['rememberme'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : ( isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			WP_Session_Tokens::get_instance( $user->ID )->destroy( wp_get_session_token() );
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
			$tok = self::start_2fa( $user, $remember, $redirect );
			wp_safe_redirect( add_query_arg( 'nura_verify', $tok, wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}

		// History (last 10, IP masked).
		$hist   = get_user_meta( $user->ID, 'nura_login_hist', true );
		$hist   = is_array( $hist ) ? $hist : array();
		$hist[] = array( 't' => current_time( 'mysql' ), 'd' => $dev, 'ip' => self::mask_ip( $ip ) );
		update_user_meta( $user->ID, 'nura_login_hist', array_slice( $hist, -10 ) );

		// New device alert (device = browser + OS + IP prefix, remembered by cookie).
		$known = get_user_meta( $user->ID, 'nura_known_dev', true );
		$known = is_array( $known ) ? $known : array();
		$ck    = isset( $_COOKIE['nura_dev'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $_COOKIE['nura_dev'] ) : '';
		if ( ! $ck ) {
			$ck = bin2hex( random_bytes( 12 ) );
			setcookie( 'nura_dev', $ck, array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
		$h = hash( 'sha256', $ck . '|' . $user->ID );
		if ( ! in_array( $h, $known, true ) ) {
			if ( $known && '0' !== get_user_meta( $user->ID, 'nura_login_alerts', true ) ) {
				$msg = sprintf( "Hi %s,\n\nYour NURA Beauty account was just opened on a new device:\n\n%s\n%s\nApproximate network: %s\n\nIf this was you, there is nothing to do.\nIf it was NOT you, change your password now and sign out of other devices:\n%s\n\nNURA Beauty", $user->display_name, $dev, date_i18n( 'l j F Y, H:i' ), self::mask_ip( $ip ), self::url( self::EP_SEC ) );
				wp_mail( $user->user_email, 'New sign-in to your NURA account', $msg );
			}
			$known[] = $h;
			update_user_meta( $user->ID, 'nura_known_dev', array_slice( $known, -15 ) );
		}
	}

	private static function start_2fa( $user, $remember, $redirect ) {
		$code = (string) wp_rand( 100000, 999999 );
		$tok  = wp_generate_password( 32, false, false );
		set_transient( 'nura_2fa_' . $tok, array( 'uid' => $user->ID, 'h' => wp_hash_password( $code ), 'rem' => $remember ? 1 : 0, 'to' => $redirect, 'tries' => 0, 'sent' => time() ), 10 * MINUTE_IN_SECONDS );
		wp_mail( $user->user_email, $code . ' is your NURA sign-in code', sprintf( "Hi %s,\n\nYour NURA sign-in code is:\n\n%s\n\nIt expires in 10 minutes. If you did not try to sign in, change your password straight away - someone may know it.\n\nNURA Beauty will never ask you for this code by phone or WhatsApp.", $user->display_name, $code ) );
		return $tok;
	}

	/** The code screen: /my-account/?nura_verify=TOKEN */
	public function twofa_page() {
		if ( empty( $_GET['nura_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$tok  = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['nura_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = get_transient( 'nura_2fa_' . $tok );
		$err  = '';
		$info = '';
		if ( ! $data ) {
			$err = __( 'This sign-in link has expired. Please sign in again.', 'nura-experience' );
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nura_2fa_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nura_2fa_nonce'] ) ), 'nura_2fa_' . $tok ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$user = get_userdata( (int) $data['uid'] );
			if ( ! empty( $_POST['resend'] ) ) {
				if ( time() - (int) $data['sent'] < 45 ) {
					$err = __( 'Please wait a moment before asking for a new code.', 'nura-experience' );
				} elseif ( $user ) {
					delete_transient( 'nura_2fa_' . $tok );
					$tok  = self::start_2fa( $user, ! empty( $data['rem'] ), $data['to'] );
					wp_safe_redirect( add_query_arg( array( 'nura_verify' => $tok, 'sent' => 1 ), wc_get_page_permalink( 'myaccount' ) ) );
					exit;
				}
			} else {
				$code = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_POST['code'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$data['tries']++;
				if ( $user && 6 === strlen( $code ) && wp_check_password( $code, $data['h'] ) ) {
					delete_transient( 'nura_2fa_' . $tok );
					$GLOBALS['nura_2fa_passed'] = true;
					wp_set_auth_cookie( $user->ID, ! empty( $data['rem'] ), is_ssl() );
					wp_set_current_user( $user->ID );
					do_action( 'wp_login', $user->user_login, $user ); // History + device alert.
					wp_safe_redirect( $data['to'] ? $data['to'] : wc_get_page_permalink( 'myaccount' ) );
					exit;
				}
				if ( $data['tries'] >= 5 ) {
					delete_transient( 'nura_2fa_' . $tok );
					$err = __( 'Too many wrong codes. Please sign in again.', 'nura-experience' );
					$data = null;
				} else {
					set_transient( 'nura_2fa_' . $tok, $data, 10 * MINUTE_IN_SECONDS );
					$err = __( 'That code is not right. Please check the latest e-mail and try again.', 'nura-experience' );
				}
			}
		}
		if ( ! empty( $_GET['sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$info = __( 'We sent you a new code.', 'nura-experience' );
		}
		$user = $data ? get_userdata( (int) $data['uid'] ) : null;
		nocache_headers();
		get_header();
		?>
		<main class="na-verify-wrap"><div class="na-verify">
			<div class="na-verify__ico" aria-hidden="true"></div>
			<h1><?php esc_html_e( 'Check your e-mail', 'nura-experience' ); ?></h1>
			<?php if ( $user ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Enter the 6-digit code we sent to %s to finish signing in.', 'nura-experience' ), self::mask_email( $user->user_email ) ) ); ?></p>
				<?php if ( $info ) : ?><p class="na-ok"><?php echo esc_html( $info ); ?></p><?php endif; ?>
				<?php if ( $err ) : ?><p class="na-err" role="alert"><?php echo esc_html( $err ); ?></p><?php endif; ?>
				<form method="post" class="na-verify__form">
					<?php wp_nonce_field( 'nura_2fa_' . $tok, 'nura_2fa_nonce' ); ?>
					<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus aria-label="<?php esc_attr_e( '6-digit code', 'nura-experience' ); ?>">
					<button type="submit" class="na-btn"><?php esc_html_e( 'Verify and sign in', 'nura-experience' ); ?></button>
				</form>
				<form method="post" class="na-verify__resend"><?php wp_nonce_field( 'nura_2fa_' . $tok, 'nura_2fa_nonce' ); ?><input type="hidden" name="resend" value="1"><button type="submit"><?php esc_html_e( 'Send a new code', 'nura-experience' ); ?></button></form>
			<?php else : ?>
				<p class="na-err" role="alert"><?php echo esc_html( $err ); ?></p>
				<a class="na-btn" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Back to sign in', 'nura-experience' ); ?></a>
			<?php endif; ?>
			<p class="na-muted"><?php esc_html_e( 'NURA will never ask you for this code by phone or WhatsApp.', 'nura-experience' ); ?></p>
		</div></main>
		<?php
		get_footer();
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Registration consent                                                */
	/* ------------------------------------------------------------------ */

	public function register_fields() {
		?>
		<p class="na-reg-consent">
			<label><input type="checkbox" name="nura_terms" value="1" required> <span><?php echo wp_kses_post( sprintf( __( 'I agree to the <a href="%1$s" target="_blank" rel="noopener">Terms</a> and <a href="%2$s" target="_blank" rel="noopener">Privacy Policy</a>.', 'nura-experience' ), esc_url( home_url( '/terms-conditions/' ) ), esc_url( home_url( '/privacy-policy/' ) ) ) ); ?></span></label>
			<label><input type="checkbox" name="nura_offers" value="1"> <span><?php esc_html_e( 'Send me new arrivals and members-only offers (optional).', 'nura-experience' ); ?></span></label>
		</p>
		<?php
	}

	public function register_check( $errors, $username, $password, $email ) {
		if ( empty( $_POST['nura_terms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the registration nonce.
			$errors->add( 'nura_terms', __( 'Please agree to the Terms and Privacy Policy to create your account.', 'nura-experience' ) );
		}
		return $errors;
	}

	public function register_save( $uid ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		update_user_meta( $uid, 'nura_terms_ok', current_time( 'mysql' ) );
		$offers = ! empty( $_POST['nura_offers'] );
		// phpcs:enable
		update_user_meta( $uid, 'nura_mkt_choice', array( 'em' => $offers, 'wa' => false, 't' => current_time( 'mysql' ) ) );
		if ( class_exists( 'NURAX_Desk_Mkt' ) ) {
			$u  = get_userdata( $uid );
			$id = NURAX_Desk_Mkt::upsert( array( 'name' => $u->display_name, 'email' => $u->user_email, 'user_id' => $uid, 'source' => 'account', 'consent' => $offers ) );
			if ( $id && ! $offers ) {
				global $wpdb;
				$wpdb->update( NURAX_Desk_Mkt::t_c(), array( 'consent' => 0 ), array( 'id' => $id ) );
			}
		}
		update_user_meta( $uid, 'nura_login_alerts', '1' );
	}
}
