<?php
/**
 * NURA Desk (NURA Experience 1.57.0).
 *
 * One admin workspace for the studio:
 *   - Lipa Mdogo Mdogo payment plans: requests from the website, deposits,
 *     weekly instalments, payment ledger, overdue tracking, receipts,
 *     reminders, stock reservation, customer statement page.
 *   - Appointments: website bookings saved automatically, week board,
 *     confirm / complete / no-show, WhatsApp confirmations and reminders,
 *     slot capacity so a time cannot be over-booked.
 *   - Overview with live KPIs, daily admin digest e-mail, CSV export,
 *     WP dashboard widget and a "My plans & appointments" panel in My Account.
 *
 * Data lives in three custom tables (created/updated automatically):
 *   {prefix}nurax_lmm_plans, {prefix}nurax_lmm_payments, {prefix}nurax_appts.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk {

	const DBV     = '1.2.0';
	const DBV_OPT = 'nurax_desk_db';
	const OPT     = 'nurax_desk';
	const CAP     = 'manage_woocommerce';
	const MENU    = 'nurax-desk';
	const P_PLANS = 'nurax-lmm';
	const P_APPTS = 'nurax-appts';
	const P_ORDERS = 'nurax-orders';
	const P_REM    = 'nurax-reminders';
	const P_POS    = 'nurax-pos';
	const P_MKT    = 'nurax-mkt';
	const P_SOC    = 'nurax-social';
	const P_SET   = 'nurax-desk-settings';
	const CRON    = 'nurax_desk_daily';

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::CRON, array( $this, 'daily' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 56 );
		// Publish the Lipa Mdogo Mdogo Terms page once, so the website pop-up can link to it.
		add_action( 'admin_init', function () {
			if ( get_option( 'nurax_lmm_terms_page' ) || ! class_exists( 'NURAX_Pages' ) || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$defs = NURAX_Pages::defs();
			if ( ! get_page_by_path( 'lipa-mdogo-mdogo-terms' ) && isset( $defs['lipa-mdogo-mdogo-terms'] ) ) {
				wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'lipa-mdogo-mdogo-terms', 'post_title' => $defs['lipa-mdogo-mdogo-terms'][0], 'post_content' => $defs['lipa-mdogo-mdogo-terms'][1] ) );
			}
			update_option( 'nurax_lmm_terms_page', 1, false );
		} );
		add_action( 'admin_menu', function () {
			remove_submenu_page( 'woocommerce', 'nurax-wa-orders' );
		}, 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_action( 'admin_post_nurax_desk_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_nurax_desk_export', array( $this, 'export' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'widget' ) );
		add_filter( 'nura_lmm_settings', array( $this, 'theme_lmm_settings' ), 5 );
		add_action( 'woocommerce_account_dashboard', array( $this, 'account' ), 20 );
		NURAX_Desk_Plans::hooks();
		NURAX_Desk_Appts::hooks();
		NURAX_Desk_Orders::hooks();
		NURAX_Desk_Remind::hooks();
		NURAX_Desk_POS::hooks();
		NURAX_Desk_Mkt::hooks();
		NURAX_Desk_Social::hooks();
	}

	/* ------------------------------------------------------------------ */
	/* Install                                                             */
	/* ------------------------------------------------------------------ */

	public static function t_plans() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_lmm_plans';
	}

	public static function t_pay() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_lmm_payments';
	}

	public static function t_appts() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_appts';
	}

	public static function maybe_install() {
		if ( self::DBV !== get_option( self::DBV_OPT ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = self::t_plans();
		$y = self::t_pay();
		$a = self::t_appts();

		dbDelta( "CREATE TABLE $p (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ref varchar(20) NOT NULL DEFAULT '',
  token varchar(40) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'request',
  customer_name varchar(120) NOT NULL DEFAULT '',
  phone varchar(20) NOT NULL DEFAULT '',
  email varchar(120) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_name varchar(255) NOT NULL DEFAULT '',
  options varchar(255) NOT NULL DEFAULT '',
  price decimal(12,2) NOT NULL DEFAULT 0,
  deposit decimal(12,2) NOT NULL DEFAULT 0,
  weeks smallint(5) unsigned NOT NULL DEFAULT 4,
  start_date date DEFAULT NULL,
  fulfil varchar(120) NOT NULL DEFAULT '',
  notes text,
  wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
  stock_reserved tinyint(1) NOT NULL DEFAULT 0,
  source varchar(20) NOT NULL DEFAULT 'admin',
  log longtext,
  last_reminded datetime DEFAULT NULL,
  reminders smallint(5) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  completed_at datetime DEFAULT NULL,
  released_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ref (ref),
  KEY token (token),
  KEY status (status),
  KEY phone (phone)
) $c;" );

		dbDelta( "CREATE TABLE $y (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  method varchar(20) NOT NULL DEFAULT 'mpesa',
  reference varchar(60) NOT NULL DEFAULT '',
  paid_at datetime DEFAULT NULL,
  note varchar(255) NOT NULL DEFAULT '',
  recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  voided tinyint(1) NOT NULL DEFAULT 0,
  void_reason varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY plan_id (plan_id),
  KEY reference (reference),
  KEY paid_at (paid_at)
) $c;" );

		dbDelta( "CREATE TABLE $a (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ref varchar(20) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'request',
  service varchar(40) NOT NULL DEFAULT 'consultation',
  type varchar(20) NOT NULL DEFAULT 'studio',
  appt_date date DEFAULT NULL,
  appt_time char(5) NOT NULL DEFAULT '',
  duration smallint(5) unsigned NOT NULL DEFAULT 60,
  customer_name varchar(120) NOT NULL DEFAULT '',
  phone varchar(20) NOT NULL DEFAULT '',
  email varchar(120) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  staff varchar(80) NOT NULL DEFAULT '',
  price decimal(12,2) NOT NULL DEFAULT 0,
  plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
  notes text,
  source varchar(20) NOT NULL DEFAULT 'admin',
  log longtext,
  last_reminded datetime DEFAULT NULL,
  reminders smallint(5) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ref (ref),
  KEY status (status),
  KEY appt_date (appt_date),
  KEY phone (phone)
) $c;" );

		NURAX_Desk_Mkt::install( $c );
		update_option( self::DBV_OPT, self::DBV, true );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'lmm_min'       => 7000,
			'lmm_deposit'   => 30,
			'lmm_weeks'     => '2,3,4',
			'lmm_grace'     => 2,
			'lmm_reserve'   => 1,
			'digest'        => 1,
			'digest_to'     => '',
			'cust_email'    => 0,
			'appt_capacity' => 2,
			'appt_days'     => '1,2,3,4,5,6',
			'appt_open'     => '09:00',
			'appt_close'    => '17:30',
			'appt_step'     => 30,
			'appt_duration' => 60,
			'staff'         => '',
			'mpesa_details' => '',
			'auto_wa'       => 0,
			'biz_name'      => 'NURA Beauty',
			'biz_pin'       => '',
			'biz_phone'     => '',
			'biz_email'     => '',
			'invoice_note'  => '',
			'mkt_from_name' => 'NURA Beauty',
			'mkt_from_email'=> '',
			'mkt_soft_optin'=> 1,
			'mkt_vip'       => 20000,
			'mkt_winback'   => 60,
		) + NURAX_Desk_Remind::template_defaults();
	}

	public static function settings() {
		$o = get_option( self::OPT, array() );
		return array_merge( self::defaults(), is_array( $o ) ? $o : array() );
	}

	/** Week options as integers, e.g. array( 2, 3, 4 ). */
	public static function week_options() {
		$s   = self::settings();
		$out = array();
		foreach ( explode( ',', (string) $s['lmm_weeks'] ) as $w ) {
			$w = absint( trim( $w ) );
			if ( $w >= 1 && $w <= 52 ) {
				$out[] = $w;
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out );
		return $out ? $out : array( 2, 3, 4 );
	}

	/** The theme's Lipa Mdogo Mdogo box reads these. */
	public function theme_lmm_settings( $s ) {
		$o = self::settings();
		return array(
			'min'     => (float) $o['lmm_min'],
			'deposit' => (int) $o['lmm_deposit'],
			'weeks'   => self::week_options(),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	public static function money( $n ) {
		return 'KSh ' . number_format( (float) $n, 0, '.', ',' );
	}

	/** Phone to digits with country code (Kenya default). Returns '' if invalid. */
	public static function phone( $raw ) {
		$p = preg_replace( '/\D/', '', (string) $raw );
		if ( 10 === strlen( $p ) && '0' === $p[0] ) {
			$p = '254' . substr( $p, 1 );
		} elseif ( 9 === strlen( $p ) && in_array( $p[0], array( '7', '1' ), true ) ) {
			$p = '254' . $p;
		}
		if ( strlen( $p ) < 8 || strlen( $p ) > 15 ) {
			return '';
		}
		return $p;
	}

	public static function phone_label( $p ) {
		$p = (string) $p;
		if ( 12 === strlen( $p ) && 0 === strpos( $p, '254' ) ) {
			return '+254 ' . substr( $p, 3, 3 ) . ' ' . substr( $p, 6, 3 ) . ' ' . substr( $p, 9 );
		}
		return '' !== $p ? '+' . $p : '';
	}

	public static function wa( $phone, $text ) {
		$p = self::phone( $phone );
		return '' === $p ? '' : 'https://wa.me/' . $p . '?text=' . rawurlencode( $text );
	}

	public static function date_label( $d, $fmt = 'D j M Y' ) {
		if ( empty( $d ) || '0000-00-00' === substr( (string) $d, 0, 10 ) ) {
			return '';
		}
		return date_i18n( $fmt, strtotime( (string) $d ) );
	}

	public static function time_label( $t ) {
		return preg_match( '/^\d{2}:\d{2}$/', (string) $t ) ? date_i18n( 'g:i a', strtotime( '2000-01-01 ' . $t ) ) : '';
	}

	public static function today() {
		return current_time( 'Y-m-d' );
	}

	public static function valid_date( $d ) {
		$d = (string) $d;
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $d : '';
	}

	public static function shop_address() {
		$addr = function_exists( 'nura_opt' ) ? (string) nura_opt( 'nura_address' ) : '';
		$addr = trim( wp_strip_all_tags( $addr ) );
		return '' !== $addr ? $addr : 'Imenti House, Moi Avenue, Nairobi CBD';
	}

	public static function new_ref( $prefix, $table ) {
		global $wpdb;
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		for ( $i = 0; $i < 20; $i++ ) {
			$r = '';
			for ( $j = 0; $j < 4; $j++ ) {
				$r .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
			}
			$ref = $prefix . '-' . current_time( 'ymd' ) . '-' . $r;
			$hit = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE ref = %s", $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $hit ) {
				return $ref;
			}
		}
		return $prefix . '-' . current_time( 'ymdHis' );
	}

	/** Append an activity line to a row's log column. */
	public static function log( $table, $id, $msg ) {
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT log FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$l   = json_decode( (string) $raw, true );
		if ( ! is_array( $l ) ) {
			$l = array();
		}
		$u   = wp_get_current_user();
		$l[] = array(
			't' => current_time( 'mysql' ),
			'u' => ( $u && $u->ID ) ? $u->display_name : 'System',
			'm' => (string) $msg,
		);
		$l = array_slice( $l, -200 );
		$wpdb->update( $table, array( 'log' => wp_json_encode( $l ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	public static function log_rows( $raw ) {
		$l = json_decode( (string) $raw, true );
		return is_array( $l ) ? array_reverse( $l ) : array();
	}

	public static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( $action );
	}

	public static function url( $page, $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
	}

	public static function badge( $key, $label ) {
		return '<span class="nd-badge nd-badge--' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span>';
	}

	/** Clear cached counters after any change. */
	public static function bust() {
		delete_transient( 'nurax_desk_attn' );
		delete_transient( 'nurax_desk_rem' );
	}

	public static function notice() {
		$map = array(
			'saved'     => array( 'success', 'Saved.' ),
			'paid'      => array( 'success', 'Payment recorded.' ),
			'voided'    => array( 'success', 'Payment voided. Balances have been recalculated.' ),
			'status'    => array( 'success', 'Status updated.' ),
			'settings'  => array( 'success', 'Settings saved.' ),
			'dup'       => array( 'error', 'That payment reference is already recorded on another payment. Nothing was saved.' ),
			'over'      => array( 'error', 'The amount is more than the balance on this plan. Nothing was saved.' ),
			'amount'    => array( 'error', 'Please enter an amount greater than zero.' ),
			'invalid'   => array( 'error', 'Please complete the required fields (name, a valid phone number, price, date).' ),
			'full'      => array( 'warning', 'Saved, but this time slot is now over its capacity.' ),
			'notfound'  => array( 'error', 'That record could not be found.' ),
			'deleted'   => array( 'success', 'Deleted.' ),
			'synced'    => array( 'success', 'Audience refreshed from the shop, accounts, WhatsApp orders, plans and appointments.' ),
			'tested'    => array( 'success', 'Test e-mail sent. Check your inbox (and spam folder).' ),
		);
		$k = isset( $_GET['nd_msg'] ) ? sanitize_key( wp_unslash( $_GET['nd_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $map[ $k ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $k ][0] ) . ' is-dismissible nd-notice"><p>' . esc_html( $map[ $k ][1] ) . '</p></div>';
		}
	}

	/** Header with the Desk tabs. */
	public static function header( $current, $title, $actions = '' ) {
		$tabs = array(
			self::MENU    => 'Overview',
			self::P_POS   => 'Point of sale',
			self::P_ORDERS => 'WhatsApp orders',
			self::P_PLANS => 'Payment plans',
			self::P_APPTS => 'Appointments',
			self::P_REM   => 'Reminders',
			self::P_MKT   => 'Marketing',
			self::P_SOC   => 'Social',
			self::P_SET   => 'Settings',
		);
		echo '<div class="wrap nd-wrap">';
		echo '<div class="nd-top"><div class="nd-brand"><span class="nd-logo">NURA</span><span class="nd-brand__sub">Desk</span></div><nav class="nd-tabs" aria-label="NURA Desk">';
		$rem = NURAX_Desk_Remind::pending_count();
		foreach ( $tabs as $slug => $label ) {
			echo '<a class="nd-tab' . ( $slug === $current ? ' is-on' : '' ) . '" href="' . esc_url( self::url( $slug ) ) . '">' . esc_html( $label ) . ( self::P_REM === $slug && $rem ? ' <span class="nd-tab__n">' . (int) $rem . '</span>' : '' ) . '</a>';
		}
		echo '</nav></div>';
		echo '<div class="nd-head"><h1 class="nd-title">' . esc_html( $title ) . '</h1><div class="nd-head__actions">' . $actions . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- actions are built from escaped parts.
		echo '<hr class="wp-header-end">';
		self::notice();
	}

	public static function footer() {
		echo '</div>';
	}

	public static function btn( $url, $label, $class = '', $attrs = '' ) {
		return '<a class="nd-btn ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" ' . $attrs . '>' . esc_html( $label ) . '</a>';
	}

	/* ------------------------------------------------------------------ */
	/* Admin shell                                                         */
	/* ------------------------------------------------------------------ */

	public static function attention_count() {
		$n = get_transient( 'nurax_desk_attn' );
		if ( false === $n ) {
			$k = NURAX_Desk_Plans::kpis();
			$a = NURAX_Desk_Appts::kpis();
			$o = NURAX_Desk_Orders::kpis();
			$n = (int) $k['overdue'] + (int) $k['requests'] + (int) $a['requests'] + (int) $o['new'];
			set_transient( 'nurax_desk_attn', $n, 10 * MINUTE_IN_SECONDS );
		}
		return (int) $n;
	}

	public function menu() {
		$n     = self::attention_count();
		$title = 'NURA Desk' . ( $n > 0 ? ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>' : '' );
		add_menu_page( 'NURA Desk', $title, self::CAP, self::MENU, array( $this, 'overview' ), 'dashicons-store', 56 );
		add_submenu_page( self::MENU, 'Overview', 'Overview', self::CAP, self::MENU, array( $this, 'overview' ) );
		add_submenu_page( self::MENU, 'Point of sale', 'Point of sale', self::CAP, self::P_POS, array( 'NURAX_Desk_POS', 'page' ) );
		add_submenu_page( self::MENU, 'WhatsApp orders', 'WhatsApp orders', self::CAP, self::P_ORDERS, array( 'NURAX_Desk_Orders', 'page' ) );
		add_submenu_page( self::MENU, 'Payment plans', 'Payment plans', self::CAP, self::P_PLANS, array( 'NURAX_Desk_Plans', 'page' ) );
		add_submenu_page( self::MENU, 'Appointments', 'Appointments', self::CAP, self::P_APPTS, array( 'NURAX_Desk_Appts', 'page' ) );
		add_submenu_page( self::MENU, 'Reminders', 'Reminders', self::CAP, self::P_REM, array( 'NURAX_Desk_Remind', 'page' ) );
		add_submenu_page( self::MENU, 'Marketing', 'Marketing', self::CAP, self::P_MKT, array( 'NURAX_Desk_Mkt', 'page' ) );
		add_submenu_page( self::MENU, 'Social', 'Social', self::CAP, self::P_SOC, array( 'NURAX_Desk_Social', 'page' ) );
		add_submenu_page( self::MENU, 'Desk settings', 'Settings', self::CAP, self::P_SET, array( $this, 'settings_page' ) );
	}

	public function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( self::MENU, self::P_POS, self::P_ORDERS, self::P_PLANS, self::P_APPTS, self::P_REM, self::P_MKT, self::P_SOC, self::P_SET ), true ) ) {
			return;
		}
		wp_enqueue_style( 'nurax-desk', NURAX_URL . 'assets/css/nura-desk.css', array(), NURAX_VERSION );
		$deps = array( 'jquery' );
		if ( in_array( $page, array( self::P_PLANS, self::P_MKT, self::P_SOC ), true ) && wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
			$deps[] = 'wc-enhanced-select';
		}
		wp_enqueue_script( 'nurax-desk', NURAX_URL . 'assets/js/nura-desk.js', $deps, NURAX_VERSION, true );
		wp_localize_script( 'nurax-desk', 'NURAX_DESK', array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'nurax_lmm_price' ),
			'dnonce'    => wp_create_nonce( 'nurax_desk' ),
			'api'       => NURAX_Desk_Remind::api_ready() ? 1 : 0,
			'deposit'   => (int) self::settings()['lmm_deposit'],
			'confirmVoid'   => 'Void this payment? Please give a short reason:',
			'confirmCancel' => 'Cancel this? Reserved stock (if any) will be returned.',
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Overview                                                            */
	/* ------------------------------------------------------------------ */

	public function overview() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$k   = NURAX_Desk_Plans::kpis();
		$a   = NURAX_Desk_Appts::kpis();
		$o   = NURAX_Desk_Orders::kpis();
		$act = self::btn( self::url( self::P_POS ), '+ New sale', 'nd-btn--primary' ) . self::btn( self::url( self::P_PLANS, array( 'action' => 'new' ) ), '+ New payment plan', 'nd-btn--ghost' ) .
			self::btn( self::url( self::P_APPTS, array( 'action' => 'new' ) ), '+ New appointment', 'nd-btn--ghost' );
		wp_localize_script( 'nurax-desk', 'NURAX_POS', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nurax_pos' ), 'methods' => NURAX_Desk_POS::methods() ) );
		self::header( self::MENU, 'Good ' . ( (int) current_time( 'G' ) < 12 ? 'morning' : ( (int) current_time( 'G' ) < 17 ? 'afternoon' : 'evening' ) ) . ' - ' . self::date_label( self::today(), 'l j F Y' ), $act );

		$td = NURAX_Desk_POS::today_total();
		$pk = count( NURAX_Desk_POS::to_pack( 50 ) );
		echo '<div class="nd-kpis">';
		self::kpi( 'Money in today', self::money( $td['sum'] ), $td['orders'] . ' paid sales' . ( $td['plan'] > 0 ? ' + plan payments' : '' ), 'ink', self::url( self::P_POS, array( 'view' => 'sales', 'r' => 'today' ) ) );
		self::kpi( 'Online orders to pack', (string) $pk, 'paid or on hold', $pk ? 'amber' : '', self::url( self::P_POS, array( 'view' => 'sales', 'pay' => 'pack', 'r' => 'year' ) ) );
		self::kpi( 'Collected this month', self::money( $k['month'] ), $k['month_count'] . ' payments', 'gold' );
		self::kpi( 'Outstanding on plans', self::money( $k['outstanding'] ), $k['active'] . ' active plans', '' );
		self::kpi( 'Overdue', self::money( $k['arrears'] ), $k['overdue'] . ' plans behind', $k['overdue'] ? 'red' : '', self::url( self::P_PLANS, array( 'tab' => 'overdue' ) ) );
		self::kpi( 'Due next 7 days', self::money( $k['due7'] ), $k['due7_count'] . ' instalments', '' , self::url( self::P_PLANS, array( 'tab' => 'due_week' ) ) );
		self::kpi( 'Appointments today', (string) $a['today'], $a['today_confirmed'] . ' confirmed', 'ink', self::url( self::P_APPTS, array( 'tab' => 'today' ) ) );
		self::kpi( 'Next 7 days', (string) $a['week'], 'appointments booked', '', self::url( self::P_APPTS, array( 'view' => 'week' ) ) );
		$wait = $a['requests'] + $k['requests'] + $o['new'];
		self::kpi( 'Waiting for you', (string) $wait, $o['new'] . ' orders, ' . $a['requests'] . ' bookings, ' . $k['requests'] . ' plans', $wait ? 'amber' : '' );
		$rc = NURAX_Desk_Remind::pending_count();
		self::kpi( 'Reminders to send', (string) $rc, 'payments, orders and visits', $rc ? 'amber' : '', self::url( self::P_REM ) );
		self::kpi( 'Ready to release', (string) $k['ready'], 'fully paid wigs', $k['ready'] ? 'green' : '', self::url( self::P_PLANS, array( 'tab' => 'completed' ) ) );
		echo '</div>';

		echo '<div class="nd-grid2">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Today in the studio</h2>' . self::btn( self::url( self::P_APPTS, array( 'view' => 'week' ) ), 'Week board', 'nd-btn--link' ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		NURAX_Desk_Appts::mini_list( self::today(), self::today(), 'No appointments today.' );
		echo '<h3 class="nd-sub">Tomorrow</h3>';
		$tm = gmdate( 'Y-m-d', strtotime( self::today() . ' +1 day' ) );
		NURAX_Desk_Appts::mini_list( $tm, $tm, 'Nothing booked for tomorrow yet.', true );
		echo '</section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Payments needing attention</h2>' . self::btn( self::url( self::P_PLANS, array( 'tab' => 'overdue' ) ), 'All overdue', 'nd-btn--link' ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		NURAX_Desk_Plans::attention_list( 8 );
		echo '</section>';
		echo '</div>';

		echo '<div class="nd-grid2">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Online orders to pack</h2>' . self::btn( self::url( self::P_POS, array( 'view' => 'sales', 'pay' => 'pack', 'r' => 'year' ) ), 'All', 'nd-btn--link' ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		NURAX_Desk_POS::pack_list( 8 );
		echo '</section>';
		echo '</div><div class="nd-grid2">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>New requests</h2></header>';
		NURAX_Desk_Orders::request_list( 6 );
		NURAX_Desk_Appts::request_list( 6 );
		NURAX_Desk_Plans::request_list( 6 );
		echo '</section>';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Latest payments</h2></header>';
		NURAX_Desk_Plans::latest_payments( 8 );
		echo '</section>';
		echo '</div>';

		echo '<p class="nd-foot">Export: <a href="' . esc_url( self::export_url( 'orders' ) ) . '">WhatsApp orders (CSV)</a> &middot; <a href="' . esc_url( self::export_url( 'plans' ) ) . '">Payment plans (CSV)</a> &middot; <a href="' . esc_url( self::export_url( 'payments' ) ) . '">Payments ledger (CSV)</a> &middot; <a href="' . esc_url( self::export_url( 'appts' ) ) . '">Appointments (CSV)</a></p>';
		self::footer();
	}

	public static function kpi( $label, $value, $sub, $tone = '', $link = '' ) {
		$tag = $link ? 'a' : 'div';
		echo '<' . $tag . ' class="nd-kpi' . ( $tone ? ' nd-kpi--' . esc_attr( $tone ) : '' ) . '"' . ( $link ? ' href="' . esc_url( $link ) . '"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="nd-kpi__l">' . esc_html( $label ) . '</span><strong class="nd-kpi__v">' . esc_html( $value ) . '</strong><span class="nd-kpi__s">' . esc_html( $sub ) . '</span>';
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/* ------------------------------------------------------------------ */
	/* Settings page                                                       */
	/* ------------------------------------------------------------------ */

	public function settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s = self::settings();
		self::header( self::P_SET, 'Desk settings' );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form">';
		echo '<input type="hidden" name="action" value="nurax_desk_settings">';
		wp_nonce_field( 'nurax_desk_settings' );

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Lipa Mdogo Mdogo</h2></header><div class="nd-fields">';
		self::field_num( 'lmm_min', 'Available on wigs from (KSh)', $s['lmm_min'], 'The calculator shows on product pages from this price.' );
		self::field_num( 'lmm_deposit', 'Deposit (%)', $s['lmm_deposit'], 'Deposit needed to reserve the wig.' );
		self::field_text( 'lmm_weeks', 'Plan lengths (weeks)', $s['lmm_weeks'], 'Comma separated, e.g. 2,3,4' );
		self::field_num( 'lmm_grace', 'Grace days before "Overdue"', $s['lmm_grace'], 'A payment counts as overdue this many days after its due date.' );
		self::field_check( 'lmm_reserve', 'Reserve stock when the deposit is recorded', $s['lmm_reserve'], 'Takes one unit out of stock (only for products that track stock) and returns it if the plan is cancelled.' );
		self::field_check( 'cust_email', 'E-mail customers payment reminders', $s['cust_email'], 'Only for plans with an e-mail address: the day before a payment is due and when it becomes overdue.' );
		echo '</div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Appointments</h2></header><div class="nd-fields">';
		$days = array_map( 'absint', explode( ',', (string) $s['appt_days'] ) );
		echo '<div class="nd-field"><span class="nd-label">Open days</span><div class="nd-days">';
		foreach ( array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' ) as $n => $lbl ) {
			echo '<label class="nd-day"><input type="checkbox" name="appt_days[]" value="' . (int) $n . '"' . checked( in_array( $n, $days, true ), true, false ) . '><span>' . esc_html( $lbl ) . '</span></label>';
		}
		echo '</div></div>';
		self::field_text( 'appt_open', 'First slot', $s['appt_open'], 'Format 09:00', 'time' );
		self::field_text( 'appt_close', 'Last slot', $s['appt_close'], 'Format 17:30', 'time' );
		self::field_num( 'appt_step', 'Minutes between slots', $s['appt_step'], '' );
		self::field_num( 'appt_duration', 'Default appointment length (minutes)', $s['appt_duration'], '' );
		self::field_num( 'appt_capacity', 'Clients per time slot', $s['appt_capacity'], 'When a time is full the website asks the client to choose another time.' );
		self::field_text( 'staff', 'Stylists / team', $s['staff'], 'Comma separated names to assign appointments, e.g. Faith, Wanjiru' );
		echo '</div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Delivery fees</h2></header><p class="nd-mut">The WhatsApp order pop-up uses your WooCommerce shipping zones first, then these fallback fees.</p>' . self::btn( admin_url( 'admin.php?page=nurax-wa-orders&nwo_fees=1' ), 'Edit delivery fees', 'nd-btn--ghost' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Business details (invoices and receipts)</h2></header><div class="nd-fields nd-fields--2">';
		self::field_text( 'biz_name', 'Business name', $s['biz_name'], '' );
		self::field_text( 'biz_pin', 'KRA PIN (optional)', $s['biz_pin'], 'Printed on invoices when filled in.' );
		self::field_text( 'biz_phone', 'Phone on invoices', $s['biz_phone'], 'Leave empty to use the site phone number.' );
		self::field_text( 'biz_email', 'E-mail on invoices', $s['biz_email'], '', 'email' );
		echo '</div><label class="nd-field"><span class="nd-label">Footer note on invoices and receipts</span><textarea name="invoice_note" rows="2" placeholder="Thank you for choosing NURA Beauty. Unworn wigs can be returned within 7 days with this receipt.">' . esc_textarea( $s['invoice_note'] ) . '</textarea></label></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Marketing</h2></header><div class="nd-fields nd-fields--2">';
		self::field_text( 'mkt_from_name', 'E-mails come from (name)', $s['mkt_from_name'], '' );
		self::field_text( 'mkt_from_email', 'E-mails come from (address)', $s['mkt_from_email'], 'Use an address on your own domain, e.g. offers@nurabeauty.co.ke. For reliable delivery install an SMTP plugin.', 'email' );
		self::field_num( 'mkt_vip', 'Top customer = spent at least (KSh)', $s['mkt_vip'], '' );
		self::field_num( 'mkt_winback', 'Win-back after (days without buying)', $s['mkt_winback'], '' );
		echo '</div>';
		self::field_check( 'mkt_soft_optin', 'Include past customers who have not unsubscribed', $s['mkt_soft_optin'], 'Untick to message only people who explicitly agreed to offers. Every e-mail has an unsubscribe link and every WhatsApp has "Reply STOP to opt out".' );
		echo '</section>';

		echo '<section class="nd-card" id="nd-templates"><header class="nd-card__h"><h2>WhatsApp reminders</h2></header><div class="nd-fields">';
		self::field_text( 'mpesa_details', 'M-Pesa payment details', $s['mpesa_details'], 'Shown in payment reminders, e.g. Till 123456 (NURA Beauty) or Paybill 222111, Account NURA' );
		self::field_check( 'auto_wa', 'Send reminders automatically every morning', $s['auto_wa'], NURAX_Desk_Remind::api_ready() ? 'WhatsApp Business API is connected. Each customer gets at most one reminder every 2 days.' : 'Needs the WhatsApp Business API (Settings > NURA Experience > WhatsApp). Until then use the one-tap Send buttons on the Reminders page.' );
		$labels = array(
			'tpl_plan_due'     => 'Plan - payment due soon',
			'tpl_plan_late'    => 'Plan - late (1-6 days)',
			'tpl_plan_firm'    => 'Plan - late 7+ days',
			'tpl_plan_final'   => 'Plan - late 14+ days',
			'tpl_plan_deposit' => 'Plan - deposit not paid',
			'tpl_order_pay'    => 'Order - confirmed, not paid',
			'tpl_order_follow' => 'Order - no reply yet',
			'tpl_appt'         => 'Appointment reminder',
		);
		echo '<p class="nd-mut nd-small">Placeholders: {first} {name} {product} {amount} {due} {days} {paid} {price} {balance} {progress} {weekly} {weeks} {ref} {link} {items} {service} {when} {time} {where} {pay_line}. Text between *stars* shows in bold on WhatsApp. Empty a box to restore the default.</p>';
		foreach ( $labels as $k => $lbl ) {
			echo '<label class="nd-field"><span class="nd-label">' . esc_html( $lbl ) . '</span><textarea name="' . esc_attr( $k ) . '" rows="6">' . esc_textarea( $s[ $k ] ) . '</textarea></label>';
		}
		echo '</div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Daily summary e-mail</h2></header><div class="nd-fields">';
		self::field_check( 'digest', 'Send me a summary every morning', $s['digest'], 'Today\'s and tomorrow\'s appointments, overdue plans and payments due today.' );
		self::field_text( 'digest_to', 'Send to', $s['digest_to'], 'Leave empty to use ' . get_option( 'admin_email' ), 'email' );
		echo '</div></section>';

		echo '<p><button type="submit" class="nd-btn nd-btn--primary">Save settings</button></p></form>';
		self::footer();
	}

	private static function field_num( $k, $label, $v, $help ) {
		echo '<label class="nd-field"><span class="nd-label">' . esc_html( $label ) . '</span><input type="number" min="0" step="1" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>';
	}

	private static function field_text( $k, $label, $v, $help, $type = 'text' ) {
		echo '<label class="nd-field"><span class="nd-label">' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>';
	}

	private static function field_check( $k, $label, $v, $help ) {
		echo '<label class="nd-field nd-field--check"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $v, 1, false ) . '><span><strong>' . esc_html( $label ) . '</strong>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</span></label>';
	}

	public function save_settings() {
		self::guard( 'nurax_desk_settings' );
		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$d   = self::defaults();
		$out = array();
		foreach ( array( 'lmm_min', 'lmm_deposit', 'lmm_grace', 'appt_capacity', 'appt_step', 'appt_duration' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : $d[ $k ];
		}
		$out['lmm_deposit']   = min( 100, $out['lmm_deposit'] );
		$out['appt_capacity'] = max( 1, $out['appt_capacity'] );
		$out['appt_step']     = max( 10, $out['appt_step'] );
		$out['appt_duration'] = max( 10, $out['appt_duration'] );
		$weeks = array();
		foreach ( explode( ',', isset( $in['lmm_weeks'] ) ? (string) $in['lmm_weeks'] : '' ) as $w ) {
			$w = absint( trim( $w ) );
			if ( $w >= 1 && $w <= 52 ) {
				$weeks[] = $w;
			}
		}
		$out['lmm_weeks']   = $weeks ? implode( ',', array_unique( $weeks ) ) : $d['lmm_weeks'];
		$out['lmm_reserve'] = empty( $in['lmm_reserve'] ) ? 0 : 1;
		$out['cust_email']  = empty( $in['cust_email'] ) ? 0 : 1;
		$out['digest']      = empty( $in['digest'] ) ? 0 : 1;
		$out['digest_to']   = isset( $in['digest_to'] ) && is_email( $in['digest_to'] ) ? sanitize_email( $in['digest_to'] ) : '';
		$days = array();
		if ( isset( $in['appt_days'] ) && is_array( $in['appt_days'] ) ) {
			foreach ( $in['appt_days'] as $n ) {
				$n = absint( $n );
				if ( $n >= 1 && $n <= 7 ) {
					$days[] = $n;
				}
			}
		}
		$out['appt_days']  = implode( ',', array_unique( $days ) );
		$out['appt_open']  = isset( $in['appt_open'] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $in['appt_open'] ) ? $in['appt_open'] : $d['appt_open'];
		$out['appt_close'] = isset( $in['appt_close'] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $in['appt_close'] ) ? $in['appt_close'] : $d['appt_close'];
		$out['staff']      = isset( $in['staff'] ) ? sanitize_text_field( $in['staff'] ) : '';
		$out['mpesa_details'] = isset( $in['mpesa_details'] ) ? sanitize_text_field( $in['mpesa_details'] ) : '';
		$out['auto_wa']       = empty( $in['auto_wa'] ) ? 0 : 1;
		foreach ( array( 'biz_name', 'biz_pin', 'biz_phone', 'mkt_from_name' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? mb_substr( sanitize_text_field( $in[ $k ] ), 0, 120 ) : '';
		}
		foreach ( array( 'biz_email', 'mkt_from_email' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) && is_email( $in[ $k ] ) ? sanitize_email( $in[ $k ] ) : '';
		}
		$out['invoice_note']   = isset( $in['invoice_note'] ) ? mb_substr( sanitize_textarea_field( $in['invoice_note'] ), 0, 400 ) : '';
		$out['mkt_soft_optin'] = empty( $in['mkt_soft_optin'] ) ? 0 : 1;
		$out['mkt_vip']        = isset( $in['mkt_vip'] ) ? absint( $in['mkt_vip'] ) : 20000;
		$out['mkt_winback']    = isset( $in['mkt_winback'] ) ? max( 1, absint( $in['mkt_winback'] ) ) : 60;
		foreach ( array_keys( NURAX_Desk_Remind::template_defaults() ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? trim( sanitize_textarea_field( $in[ $k ] ) ) : '';
		}
		update_option( self::OPT, $out, false );
		self::bust();
		wp_safe_redirect( self::url( self::P_SET, array( 'nd_msg' => 'settings' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Export                                                              */
	/* ------------------------------------------------------------------ */

	public static function export_url( $type ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=nurax_desk_export&type=' . $type ), 'nurax_desk_export' );
	}

	public function export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_desk_export' );
		global $wpdb;
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'plans';
		$rows = array();
		if ( 'sales' === $type || 'sale_items' === $type ) {
			$rows = NURAX_Desk_POS::export_rows( $type );
		} elseif ( 'contacts' === $type ) {
			$rows = NURAX_Desk_Mkt::export_rows();
		} elseif ( 'orders' === $type ) {
			$rows = NURAX_Desk_Orders::export_rows();
		} elseif ( 'payments' === $type ) {
			$rows[] = array( 'Paid on', 'Plan', 'Customer', 'Phone', 'Amount', 'Method', 'Reference', 'Note', 'Voided', 'Void reason' );
			$res    = $wpdb->get_results( 'SELECT y.*, p.ref, p.customer_name, p.phone FROM ' . self::t_pay() . ' y LEFT JOIN ' . self::t_plans() . ' p ON p.id = y.plan_id ORDER BY y.paid_at DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( (array) $res as $r ) {
				$rows[] = array( $r->paid_at, $r->ref, $r->customer_name, $r->phone, $r->amount, NURAX_Desk_Plans::method_label( $r->method ), $r->reference, $r->note, $r->voided ? 'Yes' : 'No', $r->void_reason );
			}
		} elseif ( 'appts' === $type ) {
			$rows[] = array( 'Ref', 'Date', 'Time', 'Status', 'Service', 'Where', 'Customer', 'Phone', 'E-mail', 'Stylist', 'Price', 'Notes', 'Source', 'Created' );
			$res    = $wpdb->get_results( 'SELECT * FROM ' . self::t_appts() . ' ORDER BY appt_date DESC, appt_time DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( (array) $res as $r ) {
				$rows[] = array( $r->ref, $r->appt_date, $r->appt_time, NURAX_Desk_Appts::status_label( $r->status ), NURAX_Desk_Appts::service_label( $r->service ), NURAX_Desk_Appts::type_label( $r->type ), $r->customer_name, $r->phone, $r->email, $r->staff, $r->price, $r->notes, $r->source, $r->created_at );
			}
		} else {
			$type   = 'plans';
			$rows[] = array( 'Ref', 'Created', 'Status', 'Health', 'Customer', 'Phone', 'E-mail', 'Wig', 'Options', 'Price', 'Deposit', 'Weeks', 'Start', 'Paid', 'Balance', 'Arrears', 'Next due', 'Next amount', 'Source' );
			$paid   = NURAX_Desk_Plans::paid_map();
			foreach ( NURAX_Desk_Plans::all() as $p ) {
				$m      = NURAX_Desk_Plans::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
				$rows[] = array( $p->ref, $p->created_at, $p->status, NURAX_Desk_Plans::health_label( $m['health'] ), $p->customer_name, $p->phone, $p->email, $p->product_name, $p->options, $p->price, $p->deposit, $p->weeks, $p->start_date, $m['paid'], $m['balance'], $m['arrears'], $m['next'] ? $m['next']['due'] : '', $m['next'] ? $m['next']['amount'] : '', $p->source );
			}
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=nura-' . $type . '-' . current_time( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $r ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Stop spreadsheet formula injection. */
	public static function csv_cell( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $v ) ) ? "'" . $v : $v;
	}

	/* ------------------------------------------------------------------ */
	/* Daily digest + reminders                                            */
	/* ------------------------------------------------------------------ */

	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			$tz   = wp_timezone();
			$next = new DateTime( 'tomorrow 07:00', $tz );
			wp_schedule_event( $next->getTimestamp(), 'daily', self::CRON );
		}
	}

	public function daily() {
		$today = self::today();
		if ( get_option( 'nurax_desk_last_daily' ) === $today ) {
			return;
		}
		update_option( 'nurax_desk_last_daily', $today, false );
		self::bust();
		$s = self::settings();

		NURAX_Desk_Plans::customer_reminders();
		NURAX_Desk_Remind::auto();

		if ( empty( $s['digest'] ) ) {
			return;
		}
		$tomorrow = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		$appt_t   = NURAX_Desk_Appts::between( $today, $today, array( 'request', 'confirmed' ) );
		$appt_m   = NURAX_Desk_Appts::between( $tomorrow, $tomorrow, array( 'request', 'confirmed' ) );
		$over     = array();
		$due      = array();
		$paid     = NURAX_Desk_Plans::paid_map();
		foreach ( NURAX_Desk_Plans::all( array( 'active' ) ) as $p ) {
			$m = NURAX_Desk_Plans::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
			if ( 'overdue' === $m['health'] ) {
				$over[] = array( $p, $m );
			} elseif ( $m['next'] && $m['next']['due'] <= $today ) {
				$due[] = array( $p, $m );
			}
		}
		if ( ! $appt_t && ! $appt_m && ! $over && ! $due ) {
			return;
		}
		$h  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1b1b1b;max-width:640px">';
		$h .= '<h2 style="font-family:Georgia,serif;margin:0 0 4px">NURA Desk - ' . esc_html( self::date_label( $today, 'l j F Y' ) ) . '</h2>';
		$h .= '<p style="color:#6f675d;margin:0 0 18px">Your morning summary.</p>';
		$sec = function ( $title, $items ) {
			if ( ! $items ) {
				return '';
			}
			return '<h3 style="margin:18px 0 6px;font-size:15px">' . esc_html( $title ) . '</h3><ul style="margin:0;padding-left:18px">' . implode( '', $items ) . '</ul>';
		};
		$li = function ( $a ) {
			return '<li>' . esc_html( self::time_label( $a->appt_time ) . ' - ' . $a->customer_name . ' - ' . NURAX_Desk_Appts::service_label( $a->service ) . ' (' . NURAX_Desk_Appts::status_label( $a->status ) . ')' ) . '</li>';
		};
		$h .= $sec( 'Appointments today', array_map( $li, $appt_t ) );
		$h .= $sec( 'Appointments tomorrow', array_map( $li, $appt_m ) );
		$h .= $sec( 'Overdue payment plans', array_map( function ( $x ) {
			return '<li>' . esc_html( $x[0]->customer_name . ' (' . $x[0]->ref . ') - ' . self::money( $x[1]['arrears'] ) . ' behind, ' . $x[1]['late'] . ' days late' ) . '</li>';
		}, $over ) );
		$h .= $sec( 'Payments due today', array_map( function ( $x ) {
			return '<li>' . esc_html( $x[0]->customer_name . ' (' . $x[0]->ref . ') - ' . self::money( $x[1]['next']['amount'] ) ) . '</li>';
		}, $due ) );
		$h .= '<p style="margin-top:22px"><a href="' . esc_url( self::url( self::MENU ) ) . '" style="background:#0E0E0E;color:#E4C983;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:bold">Open NURA Desk</a></p></div>';
		$to = is_email( $s['digest_to'] ) ? $s['digest_to'] : get_option( 'admin_email' );
		wp_mail( $to, 'NURA Desk - ' . self::date_label( $today, 'D j M' ) . ' summary', $h, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/* ------------------------------------------------------------------ */
	/* WP dashboard widget                                                 */
	/* ------------------------------------------------------------------ */

	public function widget() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		wp_add_dashboard_widget( 'nurax_desk_widget', 'NURA Desk', function () {
			$k = NURAX_Desk_Plans::kpis();
			$a = NURAX_Desk_Appts::kpis();
			echo '<ul style="margin:0;display:grid;grid-template-columns:1fr 1fr;gap:10px">';
			$cells = array(
				array( 'Appointments today', $a['today'] ),
				array( 'New WhatsApp orders', NURAX_Desk_Orders::kpis()['new'] ),
				array( 'Booking requests', $a['requests'] ),
				array( 'Collected this month', self::money( $k['month'] ) ),
				array( 'Overdue plans', $k['overdue'] . ' (' . self::money( $k['arrears'] ) . ')' ),
			);
			foreach ( $cells as $c ) {
				echo '<li style="margin:0;padding:10px;border:1px solid #e3ddd3;border-radius:8px"><span style="display:block;color:#6f675d;font-size:12px">' . esc_html( $c[0] ) . '</span><strong style="font-size:16px">' . esc_html( (string) $c[1] ) . '</strong></li>';
			}
			echo '</ul><p style="margin:12px 0 0"><a class="button button-primary" href="' . esc_url( self::url( self::MENU ) ) . '">Open NURA Desk</a></p>';
		} );
	}

	/* ------------------------------------------------------------------ */
	/* My Account                                                          */
	/* ------------------------------------------------------------------ */

	public function account() {
		$u = wp_get_current_user();
		if ( ! $u || ! $u->ID ) {
			return;
		}
		$phone = self::phone( get_user_meta( $u->ID, 'billing_phone', true ) );
		$plans = NURAX_Desk_Plans::for_customer( $u->ID, $u->user_email, $phone );
		$appts = NURAX_Desk_Appts::for_customer( $u->ID, $u->user_email, $phone );
		if ( ! $plans && ! $appts ) {
			return;
		}
		echo '<section class="nura-mydesk" style="margin:24px 0">';
		if ( $plans ) {
			$paid = NURAX_Desk_Plans::paid_map();
			echo '<h3>Your Lipa Mdogo Mdogo plans</h3><ul style="list-style:none;margin:0;padding:0;display:grid;gap:10px">';
			foreach ( $plans as $p ) {
				$m = NURAX_Desk_Plans::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
				echo '<li style="padding:14px 16px;border:1px solid #ece6dc;border-radius:12px;background:#fffdf8"><strong>' . esc_html( $p->product_name ) . '</strong> <small>(' . esc_html( $p->ref ) . ')</small>';
				echo '<div style="height:8px;border-radius:99px;background:#eee;margin:8px 0;overflow:hidden"><span style="display:block;height:100%;width:' . (int) $m['progress'] . '%;background:#C9A24B"></span></div>';
				echo '<span>' . esc_html( self::money( $m['paid'] ) . ' paid of ' . self::money( $p->price ) . ' - balance ' . self::money( $m['balance'] ) ) . '</span>';
				if ( $m['next'] && ! in_array( $p->status, array( 'cancelled', 'released' ), true ) ) {
					echo '<br><small>Next payment: ' . esc_html( self::money( $m['next']['amount'] ) . ' on ' . self::date_label( $m['next']['due'] ) ) . '</small>';
				}
				echo ' <a href="' . esc_url( NURAX_Desk_Plans::status_url( $p ) ) . '">View statement</a></li>';
			}
			echo '</ul>';
		}
		if ( $appts ) {
			echo '<h3 style="margin-top:22px">Your appointments</h3><ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px">';
			foreach ( $appts as $a ) {
				echo '<li style="padding:12px 16px;border:1px solid #ece6dc;border-radius:12px">' . esc_html( self::date_label( $a->appt_date, 'D j M Y' ) . ', ' . self::time_label( $a->appt_time ) . ' - ' . NURAX_Desk_Appts::service_label( $a->service ) . ' (' . NURAX_Desk_Appts::status_label( $a->status ) . ')' ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';
	}
}
