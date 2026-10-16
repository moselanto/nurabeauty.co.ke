<?php
/**
 * NURA Team - staff permissions and activity (NURA Experience 1.69.0).
 *
 * - Shop managers can add and edit, but only administrators can delete,
 *   trash, void payments, cancel plans or change settings - in NURA Desk
 *   and across WordPress / WooCommerce (products, orders, coupons, users).
 * - Owner-controlled permissions (see all sales, totals, marketing, price
 *   changes, maximum discount, mark paid, refunds, WooCommerce settings).
 * - Activity log table: who did what, when, from where.
 * - NURA Desk > Team: sales per staff member, activity feed, Excel exports.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Staff {

	const OPT   = 'nurax_staff_perms';
	const DBV   = '1.0.0';
	const DBOPT = 'nurax_activity_db';
	const PAGE  = 'nurax-team';

	public static function defaults() {
		return array(
			'view_all_sales' => 0,
			'see_totals'     => 0,
			'marketing'      => 0,
			'social'         => 0,
			'reminders'      => 1,
			'edit_price'     => 0,
			'max_disc'       => 10,
			'mark_paid'      => 1,
			'refunds'        => 0,
			'wc_settings'    => 0,
		);
	}

	public static function labels() {
		return array(
			'view_all_sales' => array( 'See everyone\'s sales', 'Off = each shop manager only sees the sales they made in Sales & invoices.' ),
			'see_totals'     => array( 'See money totals', 'Off = money figures on the NURA Desk overview are hidden from shop managers.' ),
			'mark_paid'      => array( 'Mark invoices as paid', 'Record that an unpaid invoice or order has been paid.' ),
			'edit_price'     => array( 'Change prices at the till', 'Off = wigs are always sold at the shop price (custom services can still be priced).' ),
			'refunds'        => array( 'Make refunds', 'Off = only the owner can refund WooCommerce orders.' ),
			'reminders'      => array( 'Use Reminders', 'Send payment, order and appointment reminders.' ),
			'marketing'      => array( 'Use Marketing', 'Create and send e-mail and WhatsApp campaigns, see the audience.' ),
			'social'         => array( 'Use Social', 'Social catalogue feeds and post kits.' ),
			'wc_settings'    => array( 'Open WooCommerce settings', 'Payments, shipping, tax and store settings.' ),
		);
	}

	public static function perms() {
		$p = get_option( self::OPT, array() );
		return array_merge( self::defaults(), is_array( $p ) ? $p : array() );
	}

	public static function is_admin() {
		return current_user_can( 'manage_options' );
	}

	public static function is_staff( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return false;
		}
		$caps = (array) $user->allcaps;
		return ! empty( $caps['manage_woocommerce'] ) && empty( $caps['manage_options'] );
	}

	public static function can( $perm ) {
		if ( self::is_admin() ) {
			return true;
		}
		$p = self::perms();
		return ! empty( $p[ $perm ] );
	}

	public static function max_disc() {
		return self::is_admin() ? 100 : (float) self::perms()['max_disc'];
	}

	public static function page_cap( $perm ) {
		return self::can( $perm ) ? NURAX_Desk::CAP : 'manage_options';
	}

	public static function deny( $what, $ajax = false ) {
		self::log( 'blocked', 'Blocked: tried to ' . $what, array( 'level' => 'warn' ) );
		$msg = 'Only the owner (administrator) can ' . $what . '. Please ask the owner.';
		if ( $ajax || wp_doing_ajax() ) {
			wp_send_json_error( array( 'message' => $msg ), 403 );
		}
		wp_die( esc_html( $msg ), 'Not allowed - NURA Desk', array( 'response' => 403, 'back_link' => true ) );
	}

	/* ------------------------------------------------------------------ */
	/* Hooks                                                               */
	/* ------------------------------------------------------------------ */

	public static function hooks() {
		add_filter( 'user_has_cap', array( __CLASS__, 'caps' ), 20, 4 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_pages' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_post_nurax_team_perms', array( __CLASS__, 'h_perms' ) );

		foreach ( array( 'nurax_desk_delete', 'nurax_lmm_void', 'nurax_lmm_status', 'nurax_mkt_contact', 'nurax_desk_settings', 'nurax_mkt_save', 'nurax_mkt_sync', 'nurax_lmm_save', 'nurax_lmm_pay', 'nurax_appt_save', 'nurax_appt_status', 'nurax_desk_order_status', 'nurax_desk_order_pay', 'nurax_vault' ) as $a ) {
			add_action( 'admin_post_' . $a, array( __CLASS__, 'pre_post' ), 1 );
		}
		add_action( 'wp_ajax_nurax_pos_order', array( __CLASS__, 'pre_pos_order' ), 1 );
		add_action( 'wp_ajax_woocommerce_refund_line_items', array( __CLASS__, 'pre_refund' ), 1 );
		add_action( 'wp_ajax_woocommerce_delete_refund', array( __CLASS__, 'pre_refund' ), 1 );

		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'product_changes' ), 10, 1 );
		add_action( 'woocommerce_before_product_variation_object_save', array( __CLASS__, 'product_changes' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_status' ), 10, 3 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'refunded' ), 10, 2 );
		add_action( 'woocommerce_new_coupon', array( __CLASS__, 'coupon' ), 10, 1 );
		add_action( 'woocommerce_update_coupon', array( __CLASS__, 'coupon' ), 10, 1 );
		add_action( 'wp_login', array( __CLASS__, 'login' ), 10, 2 );
		add_action( 'wp_trash_post', array( __CLASS__, 'trashed' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'trashed' ) );
		add_action( 'delete_user', array( __CLASS__, 'user_deleted' ) );
	}

	/** Remove every "delete" power (and a few risky ones) from non-admin staff. */
	public static function caps( $allcaps, $caps, $args, $user ) {
		if ( empty( $allcaps['manage_woocommerce'] ) || ! empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}
		foreach ( array_keys( $allcaps ) as $c ) {
			if ( 0 === strpos( $c, 'delete_' ) ) {
				$allcaps[ $c ] = false;
			}
		}
		foreach ( array( 'remove_users', 'promote_users', 'export', 'import', 'install_plugins', 'activate_plugins', 'update_plugins', 'edit_plugins', 'install_themes', 'switch_themes', 'edit_themes', 'update_core' ) as $c ) {
			$allcaps[ $c ] = false;
		}
		return $allcaps;
	}

	public static function block_pages() {
		if ( ! is_admin() || wp_doing_ajax() || self::is_admin() || ! self::is_staff() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! self::can( 'wc_settings' ) && in_array( $page, array( 'wc-settings', 'wc-status', 'wc-addons' ), true ) ) {
			self::deny( 'open the WooCommerce settings' );
		}
		if ( ( 'nurax-mkt' === $page && ! self::can( 'marketing' ) ) || ( 'nurax-social' === $page && ! self::can( 'social' ) ) || ( 'nurax-reminders' === $page && ! self::can( 'reminders' ) ) || in_array( $page, array( 'nurax-desk-settings', self::PAGE ), true ) ) {
			self::deny( 'open this NURA Desk page' );
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'export.php', 'import.php', 'site-health.php', 'plugins.php', 'plugin-install.php', 'theme-editor.php', 'plugin-editor.php', 'user-new.php', 'options-general.php' ), true ) ) {
			self::deny( 'open this page' );
		}
	}

	public static function menu() {
		if ( self::is_admin() ) {
			add_submenu_page( NURAX_Desk::MENU, 'Team & activity', 'Team', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
			return;
		}
		if ( ! self::is_staff() ) {
			return;
		}
		remove_submenu_page( NURAX_Desk::MENU, 'nurax-desk-settings' );
		foreach ( array( 'marketing' => 'nurax-mkt', 'social' => 'nurax-social', 'reminders' => 'nurax-reminders' ) as $perm => $slug ) {
			if ( ! self::can( $perm ) ) {
				remove_submenu_page( NURAX_Desk::MENU, $slug );
			}
		}
		if ( ! self::can( 'wc_settings' ) ) {
			remove_submenu_page( 'woocommerce', 'wc-settings' );
			remove_submenu_page( 'woocommerce', 'wc-status' );
			remove_submenu_page( 'woocommerce', 'wc-addons' );
		}
	}

	public static function pre_post() {
		$a  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$g  = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) && ! is_array( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		};
		$admin = self::is_admin();
		switch ( $a ) {
			case 'nurax_desk_delete':
				if ( ! $admin ) {
					self::deny( 'delete records' );
				}
				self::log( 'delete', 'Deleted ' . str_replace( ':', ' #', $g( 'key' ) ), array( 'level' => 'warn' ) );
				break;
			case 'nurax_lmm_void':
				if ( ! $admin ) {
					self::deny( 'void a payment' );
				}
				self::log( 'payment', 'Voided a plan payment', array( 'object' => 'plan', 'level' => 'warn' ) );
				break;
			case 'nurax_lmm_status':
				if ( 'cancelled' === $g( 'to' ) && ! $admin ) {
					self::deny( 'cancel a payment plan' );
				}
				self::log( 'plan', 'Plan #' . $g( 'plan_id' ) . ' set to ' . $g( 'to' ), array( 'object' => 'plan', 'object_id' => (int) $g( 'plan_id' ) ) );
				break;
			case 'nurax_mkt_contact':
				if ( 'delete' === $g( 'op' ) && ! $admin ) {
					self::deny( 'delete a contact' );
				}
				self::log( 'contact', ucfirst( str_replace( '_', ' ', $g( 'op' ) ? $g( 'op' ) : 'add' ) ) . ' contact' . ( $g( 'name' ) ? ': ' . $g( 'name' ) : ( $g( 'id' ) ? ' #' . $g( 'id' ) : '' ) ) );
				break;
			case 'nurax_desk_settings':
				if ( ! $admin ) {
					self::deny( 'change NURA Desk settings' );
				}
				self::log( 'settings', 'Changed NURA Desk settings' );
				break;
			case 'nurax_vault':
				if ( ! $admin ) {
					self::deny( 'change data protection' );
				}
				break;
			case 'nurax_mkt_save':
			case 'nurax_mkt_sync':
				if ( ! self::can( 'marketing' ) ) {
					self::deny( 'use Marketing' );
				}
				self::log( 'marketing', 'nurax_mkt_sync' === $a ? 'Refreshed the audience' : 'Saved or sent campaign: ' . $g( 'name' ) );
				break;
			case 'nurax_lmm_save':
				self::log( 'plan', ( $g( 'plan_id' ) ? 'Edited plan #' . $g( 'plan_id' ) : 'Created a payment plan' ) . ' for ' . $g( 'customer_name' ) . ' - ' . $g( 'product_name' ), array( 'object' => 'plan', 'object_id' => (int) $g( 'plan_id' ), 'amount' => (float) $g( 'price' ) ) );
				break;
			case 'nurax_lmm_pay':
				self::log( 'payment', 'Recorded a plan payment on plan #' . $g( 'plan_id' ) . ( $g( 'reference' ) ? ' (' . strtoupper( $g( 'reference' ) ) . ')' : '' ), array( 'object' => 'plan', 'object_id' => (int) $g( 'plan_id' ), 'amount' => (float) $g( 'amount' ) ) );
				break;
			case 'nurax_appt_save':
				self::log( 'appointment', ( $g( 'appt_id' ) ? 'Edited appointment #' . $g( 'appt_id' ) : 'Booked an appointment' ) . ' for ' . $g( 'customer_name' ) . ' on ' . $g( 'appt_date' ) . ' ' . $g( 'appt_time' ), array( 'object' => 'appointment', 'object_id' => (int) $g( 'appt_id' ), 'amount' => (float) $g( 'price' ) ) );
				break;
			case 'nurax_appt_status':
				self::log( 'appointment', 'Appointment #' . $g( 'appt_id' ) . ' set to ' . $g( 'to' ), array( 'object' => 'appointment', 'object_id' => (int) $g( 'appt_id' ) ) );
				break;
			case 'nurax_desk_order_status':
				if ( 'cancelled' === $g( 'to' ) && ! $admin ) {
					self::deny( 'cancel a WhatsApp order' );
				}
				self::log( 'order', 'WhatsApp order #' . $g( 'order_id' ) . ' set to ' . $g( 'to' ), array( 'object' => 'wa_order', 'object_id' => (int) $g( 'order_id' ) ) );
				break;
			case 'nurax_desk_order_pay':
				if ( ! self::can( 'mark_paid' ) ) {
					self::deny( 'mark orders as paid' );
				}
				self::log( 'payment', 'Marked order #' . $g( 'order_id' ) . ' as paid' . ( $g( 'ref' ) ? ' (' . strtoupper( $g( 'ref' ) ) . ')' : '' ), array( 'object' => 'order', 'object_id' => (int) $g( 'order_id' ) ) );
				break;
		}
	}

	public static function pre_pos_order() {
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'paid' === $op && ! self::can( 'mark_paid' ) ) {
			self::deny( 'mark invoices as paid', true );
		}
		$map = array( 'paid' => 'Marked invoice as paid', 'complete' => 'Marked order as fulfilled', 'email' => 'E-mailed invoice' );
		if ( isset( $map[ $op ] ) ) {
			$o = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
			self::log( 'paid' === $op ? 'payment' : 'sale', $map[ $op ] . ' #' . ( $o ? $o->get_order_number() : $id ), array( 'object' => 'order', 'object_id' => $id, 'amount' => $o && 'paid' === $op ? (float) $o->get_total() : 0 ) );
		}
	}

	public static function pre_refund() {
		if ( ! self::can( 'refunds' ) ) {
			self::deny( 'make or remove refunds', true );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Activity log                                                        */
	/* ------------------------------------------------------------------ */

	public static function t() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_activity';
	}

	public static function maybe_install() {
		if ( self::DBV === get_option( self::DBOPT ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::t();
		dbDelta( "CREATE TABLE $t (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_name varchar(120) NOT NULL DEFAULT '',
  role varchar(40) NOT NULL DEFAULT '',
  area varchar(30) NOT NULL DEFAULT '',
  level varchar(10) NOT NULL DEFAULT 'info',
  summary varchar(255) NOT NULL DEFAULT '',
  object varchar(30) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  amount decimal(12,2) NOT NULL DEFAULT 0,
  ip varchar(45) NOT NULL DEFAULT '',
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY area (area),
  KEY created_at (created_at)
) " . $wpdb->get_charset_collate() . ';' );
		update_option( self::DBOPT, self::DBV, false );
	}

	public static function log( $area, $summary, $o = array() ) {
		global $wpdb;
		if ( self::DBV !== get_option( self::DBOPT ) ) {
			self::maybe_install();
		}
		$u     = isset( $o['user'] ) ? $o['user'] : wp_get_current_user();
		$roles = $u && $u->ID ? (array) $u->roles : array();
		$role  = ! $roles ? 'System' : ( in_array( 'administrator', $roles, true ) ? 'Owner / admin' : ( in_array( 'shop_manager', $roles, true ) ? 'Shop manager' : ucfirst( str_replace( '_', ' ', (string) reset( $roles ) ) ) ) );
		$wpdb->insert( self::t(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'user_id'    => $u && $u->ID ? (int) $u->ID : 0,
			'user_name'  => $u && $u->ID ? mb_substr( $u->display_name, 0, 120 ) : 'System',
			'role'       => mb_substr( $role, 0, 40 ),
			'area'       => mb_substr( sanitize_key( $area ), 0, 30 ),
			'level'      => isset( $o['level'] ) ? $o['level'] : 'info',
			'summary'    => mb_substr( wp_strip_all_tags( (string) $summary ), 0, 255 ),
			'object'     => isset( $o['object'] ) ? mb_substr( $o['object'], 0, 30 ) : '',
			'object_id'  => isset( $o['object_id'] ) ? (int) $o['object_id'] : 0,
			'amount'     => isset( $o['amount'] ) ? (float) $o['amount'] : 0,
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? mb_substr( preg_replace( '/[^0-9a-fA-F\.:]/', '', (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 0, 45 ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'created_at' => current_time( 'mysql' ),
		) );
	}

	private static function by_team() {
		$u = wp_get_current_user();
		return $u && $u->ID && ( ! empty( $u->allcaps['manage_woocommerce'] ) || ! empty( $u->allcaps['manage_options'] ) );
	}

	public static function product_changes( $p ) {
		if ( ! self::by_team() || ! is_object( $p ) || ! method_exists( $p, 'get_changes' ) ) {
			return;
		}
		if ( ! $p->get_id() ) {
			self::log( 'product', 'Added product: ' . $p->get_name(), array( 'object' => 'product' ) );
			return;
		}
		$ch   = $p->get_changes();
		$old  = wc_get_product( $p->get_id() );
		$bits = array();
		$keys = array( 'regular_price' => 'price', 'sale_price' => 'sale price', 'stock_quantity' => 'stock', 'stock_status' => 'stock status', 'name' => 'name', 'status' => 'status', 'sku' => 'SKU' );
		foreach ( $keys as $k => $label ) {
			if ( array_key_exists( $k, $ch ) ) {
				$getter = 'get_' . $k;
				$was    = $old && method_exists( $old, $getter ) ? $old->$getter( 'edit' ) : '';
				if ( (string) $was !== (string) $ch[ $k ] ) {
					$bits[] = $label . ' ' . ( '' === (string) $was ? '-' : $was ) . ' > ' . ( '' === (string) $ch[ $k ] ? '-' : $ch[ $k ] );
				}
			}
		}
		if ( $bits ) {
			self::log( 'product', 'Edited ' . $p->get_name() . ': ' . implode( ', ', $bits ), array( 'object' => 'product', 'object_id' => $p->get_id(), 'level' => ( isset( $ch['regular_price'] ) || isset( $ch['sale_price'] ) ) ? 'warn' : 'info' ) );
		}
	}

	public static function order_status( $id, $from, $to ) {
		if ( ! self::by_team() || ! is_admin() ) {
			return;
		}
		$o = wc_get_order( $id );
		self::log( 'order', 'Order #' . ( $o ? $o->get_order_number() : $id ) . ': ' . wc_get_order_status_name( $from ) . ' > ' . wc_get_order_status_name( $to ), array( 'object' => 'order', 'object_id' => $id, 'amount' => $o ? (float) $o->get_total() : 0, 'level' => in_array( $to, array( 'cancelled', 'refunded' ), true ) ? 'warn' : 'info' ) );
	}

	public static function refunded( $order_id, $refund_id ) {
		$r = wc_get_order( $refund_id );
		self::log( 'refund', 'Refund on order #' . $order_id . ( $r && $r->get_reason() ? ' - ' . $r->get_reason() : '' ), array( 'object' => 'order', 'object_id' => $order_id, 'amount' => $r ? abs( (float) $r->get_amount() ) : 0, 'level' => 'warn' ) );
	}

	public static function coupon( $id ) {
		if ( ! self::by_team() || ! class_exists( 'WC_Coupon' ) ) {
			return;
		}
		$c = new WC_Coupon( $id );
		self::log( 'coupon', 'Saved coupon ' . strtoupper( $c->get_code() ) . ' (' . $c->get_amount() . ( 'percent' === $c->get_discount_type() ? '%' : ' KSh' ) . ')', array( 'object' => 'coupon', 'object_id' => $id ) );
	}

	public static function login( $login, $user ) {
		if ( ! empty( $user->allcaps['manage_woocommerce'] ) || ! empty( $user->allcaps['manage_options'] ) ) {
			self::log( 'login', 'Signed in', array( 'user' => $user ) );
		}
	}

	public static function trashed( $post_id ) {
		$t = get_post_type( $post_id );
		if ( ! self::by_team() || ! in_array( $t, array( 'product', 'product_variation', 'shop_order', 'shop_coupon', 'page', 'post', 'nurax_wa_order' ), true ) ) {
			return;
		}
		self::log( 'delete', ( 'wp_trash_post' === current_filter() ? 'Moved to trash: ' : 'Deleted permanently: ' ) . get_the_title( $post_id ) . ' (' . $t . ' #' . $post_id . ')', array( 'object' => $t, 'object_id' => $post_id, 'level' => 'warn' ) );
	}

	public static function user_deleted( $id ) {
		$u = get_userdata( $id );
		self::log( 'delete', 'Deleted user ' . ( $u ? $u->user_login : '#' . $id ), array( 'level' => 'warn' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Reports                                                             */
	/* ------------------------------------------------------------------ */

	public static function team_users() {
		return get_users( array( 'role__in' => array( 'administrator', 'shop_manager' ), 'orderby' => 'display_name' ) );
	}

	private static function blank( $name, $role ) {
		return array( 'name' => $name, 'role' => $role, 'sales' => 0, 'total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0, 'disc' => 0.0, 'items' => 0, 'plans' => 0, 'payments' => 0, 'appts' => 0, 'products' => 0, 'blocked' => 0, 'actions' => 0, 'last' => '' );
	}

	public static function summary( $from, $to ) {
		global $wpdb;
		$out = array();
		foreach ( self::team_users() as $u ) {
			$out[ $u->ID ] = self::blank( $u->display_name, in_array( 'administrator', (array) $u->roles, true ) ? 'Owner / admin' : 'Shop manager' );
		}
		$orders = NURAX_Desk_POS::orders( $from, $to, 5000 );
		$orders = is_object( $orders ) ? $orders->orders : (array) $orders;
		foreach ( $orders as $o ) {
			$uid = (int) $o->get_meta( '_nura_cashier' );
			if ( ! $uid ) {
				continue;
			}
			if ( ! isset( $out[ $uid ] ) ) {
				$u           = get_userdata( $uid );
				$out[ $uid ] = self::blank( $u ? $u->display_name : 'User #' . $uid, 'Former staff' );
			}
			$t = (float) $o->get_total();
			$out[ $uid ]['sales']++;
			$out[ $uid ]['total'] += $t;
			if ( NURAX_Desk_POS::is_paid( $o ) ) {
				$out[ $uid ]['paid'] += $t;
			} else {
				$out[ $uid ]['unpaid'] += $t;
			}
			foreach ( $o->get_fees() as $f ) {
				if ( (float) $f->get_total() < 0 ) {
					$out[ $uid ]['disc'] += abs( (float) $f->get_total() );
				}
			}
			$out[ $uid ]['items'] += (int) $o->get_item_count();
		}
		$t    = self::t();
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, area, COUNT(*) n, MAX(created_at) last FROM $t WHERE created_at BETWEEN %s AND %s GROUP BY user_id, area", $from . ' 00:00:00', $to . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map  = array( 'plan' => 'plans', 'payment' => 'payments', 'appointment' => 'appts', 'product' => 'products', 'blocked' => 'blocked' );
		foreach ( $rows as $r ) {
			$k = (int) $r->user_id;
			if ( ! isset( $out[ $k ] ) ) {
				continue;
			}
			if ( isset( $map[ $r->area ] ) ) {
				$out[ $k ][ $map[ $r->area ] ] += (int) $r->n;
			}
			if ( 'login' !== $r->area ) {
				$out[ $k ]['actions'] += (int) $r->n;
			}
			if ( $r->last > $out[ $k ]['last'] ) {
				$out[ $k ]['last'] = $r->last;
			}
		}
		uasort( $out, function ( $a, $b ) {
			return $b['total'] <=> $a['total'];
		} );
		return $out;
	}

	public static function feed_where( $from, $to, $uid, $area ) {
		global $wpdb;
		$w = $wpdb->prepare( 'created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59' );
		if ( $uid ) {
			$w .= $wpdb->prepare( ' AND user_id = %d', $uid );
		}
		if ( $area ) {
			$w .= $wpdb->prepare( ' AND area = %s', $area );
		}
		return $w;
	}

	public static function areas() {
		return array( 'sale' => 'Sales', 'payment' => 'Payments', 'plan' => 'Payment plans', 'appointment' => 'Appointments', 'order' => 'Orders', 'product' => 'Products & prices', 'refund' => 'Refunds', 'coupon' => 'Coupons', 'contact' => 'Contacts', 'marketing' => 'Marketing', 'delete' => 'Deletions', 'blocked' => 'Blocked attempts', 'settings' => 'Settings', 'security' => 'Security', 'export' => 'Downloads', 'login' => 'Sign-ins' );
	}

	public static function export_rows( $type ) {
		global $wpdb;
		list( $from, $to, $uid, $area ) = self::filters();
		if ( 'team' === $type ) {
			$rows = array( array( 'Team member', 'Role', 'Sales', 'Sales total', 'Paid', 'Unpaid', 'Discounts given', 'Qty sold', 'Plans', 'Plan payments', 'Appointments', 'Product edits', 'Blocked attempts', 'Actions', 'Last active' ) );
			foreach ( self::summary( $from, $to ) as $s ) {
				$rows[] = array( $s['name'], $s['role'], $s['sales'], $s['total'], $s['paid'], $s['unpaid'], $s['disc'], $s['items'], $s['plans'], $s['payments'], $s['appts'], $s['products'], $s['blocked'], $s['actions'], $s['last'] );
			}
			return $rows;
		}
		$rows = array( array( 'When', 'Team member', 'Role', 'Area', 'What happened', 'Amount', 'IP address' ) );
		$a    = self::areas();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' WHERE ' . self::feed_where( $from, $to, $uid, $area ) . ' ORDER BY id DESC LIMIT 20000' ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows[] = array( $r->created_at, $r->user_name, $r->role, isset( $a[ $r->area ] ) ? $a[ $r->area ] : ucfirst( $r->area ), $r->summary, (float) $r->amount ? $r->amount : '', $r->ip );
		}
		return $rows;
	}

	public static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$r    = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : 'month';
		$uid  = isset( $_GET['u'] ) ? absint( $_GET['u'] ) : 0;
		$area = isset( $_GET['area'] ) ? sanitize_key( wp_unslash( $_GET['area'] ) ) : '';
		$area = array_key_exists( $area, self::areas() ) ? $area : '';
		$f    = isset( $_GET['from'] ) ? NURAX_Desk::valid_date( wp_unslash( $_GET['from'] ) ) : '';
		$t    = isset( $_GET['to'] ) ? NURAX_Desk::valid_date( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable
		$today = NURAX_Desk::today();
		if ( $f && $t ) {
			return array( min( $f, $t ), max( $f, $t ), $uid, $area );
		}
		$m1 = substr( $today, 0, 7 ) . '-01';
		switch ( $r ) {
			case 'today':
				return array( $today, $today, $uid, $area );
			case 'week':
				return array( gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) ), $today, $uid, $area );
			case 'last':
				return array( gmdate( 'Y-m-01', strtotime( $m1 . ' -1 month' ) ), gmdate( 'Y-m-t', strtotime( $m1 . ' -1 month' ) ), $uid, $area );
			case 'year':
				return array( substr( $today, 0, 4 ) . '-01-01', $today, $uid, $area );
			default:
				return array( $m1, $today, $uid, $area );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	public static function h_perms() {
		if ( ! self::is_admin() ) {
			self::deny( 'change team permissions' );
		}
		check_admin_referer( 'nurax_team_perms' );
		$p = array();
		foreach ( array_keys( self::labels() ) as $k ) {
			$p[ $k ] = empty( $_POST[ $k ] ) ? 0 : 1;
		}
		$p['max_disc'] = isset( $_POST['max_disc'] ) ? min( 100, max( 0, (float) $_POST['max_disc'] ) ) : 10;
		update_option( self::OPT, $p, false );
		self::log( 'security', 'Changed shop manager permissions', array( 'level' => 'warn' ) );
		wp_safe_redirect( add_query_arg( 'nd_msg', 'saved', NURAX_Desk::url( self::PAGE, array( 'tab' => 'perms' ) ) ) );
		exit;
	}

	public static function page() {
		if ( ! self::is_admin() ) {
			wp_die( esc_html__( 'Only the owner can open this page.', 'nura-experience' ) );
		}
		global $wpdb;
		self::maybe_install();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'team';
		$rsel   = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : ( isset( $_GET['from'] ) ? 'custom' : 'month' );
		// phpcs:enable
		list( $from, $to, $uid, $area ) = self::filters();
		$q     = array( 'from' => $from, 'to' => $to, 'u' => $uid, 'area' => $area );
		$extra = '&from=' . $from . '&to=' . $to . '&u=' . $uid . '&area=' . $area;
		$tabs  = array( 'team' => 'Performance', 'log' => 'Activity log', 'perms' => 'Permissions' );
		$exp   = 'perms' === $tab ? '' : NURAX_Desk::export_btns( 'log' === $tab ? 'activity' : 'team', 'log' === $tab ? 'Activity report' : 'Team report', $extra );
		NURAX_Desk::header( self::PAGE, 'Team & activity', $exp );

		echo '<div class="nt">';
		echo '<nav class="nt-tabs" aria-label="Team">';
		foreach ( $tabs as $k => $l ) {
			$url = 'perms' === $k ? NURAX_Desk::url( self::PAGE, array( 'tab' => 'perms' ) ) : NURAX_Desk::url( self::PAGE, array_merge( $q, array( 'tab' => $k ) ) );
			echo '<a class="nt-tab' . ( $k === $tab ? ' is-on' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';

		if ( 'perms' === $tab ) {
			self::perms_form();
			echo '</div>';
			NURAX_Desk::footer();
			return;
		}

		/* Filter bar. */
		$users = self::team_users();
		echo '<form method="get" class="nt-filter"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		echo '<div class="nt-seg" role="group" aria-label="Period">';
		foreach ( array( 'today' => 'Today', 'week' => '7 days', 'month' => 'This month', 'last' => 'Last month', 'year' => 'This year' ) as $k => $l ) {
			echo '<a class="nt-seg__b' . ( $k === $rsel ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => $tab, 'r' => $k, 'u' => $uid, 'area' => $area ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</div><div class="nt-filter__f">';
		echo '<label class="nt-in"><span>From</span><input type="date" name="from" value="' . esc_attr( $from ) . '"></label>';
		echo '<label class="nt-in"><span>To</span><input type="date" name="to" value="' . esc_attr( $to ) . '"></label>';
		echo '<label class="nt-in"><span>Team member</span><select name="u"><option value="0">Everyone</option>';
		foreach ( $users as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( $uid, $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select></label>';
		if ( 'log' === $tab ) {
			echo '<label class="nt-in"><span>Activity</span><select name="area"><option value="">All activity</option>';
			foreach ( self::areas() as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $area, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<button class="nd-btn nd-btn--primary nt-apply">Apply</button></div></form>';
		echo '<p class="nt-period">' . esc_html( NURAX_Desk::date_label( $from, 'j M Y' ) . '  -  ' . NURAX_Desk::date_label( $to, 'j M Y' ) ) . '</p>';

		$areas = self::areas();
		$feed  = function ( $rows, $compact = false ) use ( $areas ) {
			echo '<div class="nt-table"><table><thead><tr><th>When</th><th>Team member</th><th>Area</th><th>What happened</th><th class="r">Amount</th>' . ( $compact ? '' : '<th>IP address</th>' ) . '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr class="nt-lv--' . esc_attr( $r->level ) . '"><td class="nt-when">' . esc_html( NURAX_Desk::date_label( $r->created_at, 'j M Y' ) ) . '<small>' . esc_html( NURAX_Desk::date_label( $r->created_at, 'H:i' ) ) . '</small></td>';
				echo '<td><span class="nt-who"><span class="nt-av">' . esc_html( strtoupper( mb_substr( $r->user_name, 0, 1 ) ) ) . '</span><span>' . esc_html( $r->user_name ) . '<small>' . esc_html( $r->role ) . '</small></span></span></td>';
				echo '<td><span class="nd-area nd-area--' . esc_attr( $r->area ) . '">' . esc_html( isset( $areas[ $r->area ] ) ? $areas[ $r->area ] : $r->area ) . '</span></td><td class="nt-what">' . esc_html( $r->summary ) . '</td>';
				echo '<td class="r nt-num">' . ( (float) $r->amount ? esc_html( NURAX_Desk::money( $r->amount ) ) : '<span class="nt-mut">-</span>' ) . '</td>' . ( $compact ? '' : '<td class="nt-mut">' . esc_html( $r->ip ) . '</td>' ) . '</tr>';
			}
			echo '</tbody></table></div>';
		};

		if ( 'log' === $tab ) {
			$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$w     = self::feed_where( $from, $to, $uid, $area );
			$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE ' . $w ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE ' . $w . ' ORDER BY id DESC LIMIT 50 OFFSET %d', ( $paged - 1 ) * 50 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			echo '<section class="nt-card"><header class="nt-card__h"><div><h2>Activity log</h2><p>' . (int) $total . ' recorded actions in this period</p></div></header>';
			if ( ! $rows ) {
				echo '<div class="nt-empty"><strong>No activity in this period</strong><p>Sales, payments, plans, appointments, price and stock changes, refunds, deletions, sign-ins and blocked attempts are recorded here automatically.</p></div></section></div>';
				NURAX_Desk::footer();
				return;
			}
			$feed( $rows );
			echo '</section>';
			NURAX_Desk_Plans::pager( $total, 50, $paged, array_merge( $q, array( 'tab' => 'log' ) ), self::PAGE );
			echo '</div>';
			NURAX_Desk::footer();
			return;
		}

		/* Performance. */
		$sum = self::summary( $from, $to );
		if ( $uid && isset( $sum[ $uid ] ) ) {
			$sum = array( $uid => $sum[ $uid ] );
		}
		$tot = array( 'sales' => 0, 'total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0, 'disc' => 0.0, 'blocked' => 0, 'actions' => 0 );
		foreach ( $sum as $s ) {
			foreach ( $tot as $k => $v ) {
				$tot[ $k ] += $s[ $k ];
			}
		}
		$avg = $tot['sales'] ? $tot['total'] / $tot['sales'] : 0;
		$mgr = 0;
		foreach ( $users as $u ) {
			if ( ! in_array( 'administrator', (array) $u->roles, true ) ) {
				$mgr++;
			}
		}

		echo '<div class="nt-kpis">';
		$kpi = function ( $l, $v, $s, $tone = '' ) {
			echo '<div class="nt-kpi' . ( $tone ? ' nt-kpi--' . esc_attr( $tone ) : '' ) . '"><span class="nt-kpi__l">' . esc_html( $l ) . '</span><strong class="nt-kpi__v">' . esc_html( $v ) . '</strong><span class="nt-kpi__s">' . esc_html( $s ) . '</span></div>';
		};
		$kpi( 'Till sales', NURAX_Desk::money( $tot['total'] ), $tot['sales'] . ' sales by the team', 'ink' );
		$kpi( 'Average sale', NURAX_Desk::money( $avg ), 'per till sale' );
		$kpi( 'Unpaid invoices', NURAX_Desk::money( $tot['unpaid'] ), 'still to collect', $tot['unpaid'] > 0 ? 'amber' : '' );
		$kpi( 'Discounts given', NURAX_Desk::money( $tot['disc'] ), $tot['total'] > 0 ? round( $tot['disc'] / ( $tot['total'] + $tot['disc'] ) * 100, 1 ) . '% of gross sales' : 'none', $tot['disc'] > 0 ? 'gold' : '' );
		$kpi( 'Blocked attempts', (string) $tot['blocked'], 'owner-only actions stopped', $tot['blocked'] ? 'red' : '' );
		echo '</div>';

		if ( ! $mgr ) {
			echo '<section class="nt-card nt-callout"><div><h2>Add your shop managers</h2><p>Each person needs their own login so their sales and actions are recorded under their name. Go to Users &gt; Add New, enter their name and e-mail, and choose the role <strong>Shop manager</strong>. They will appear here automatically.</p></div><a class="nd-btn nd-btn--primary" href="' . esc_url( admin_url( 'user-new.php' ) ) . '">Add a shop manager</a></section>';
		}

		/* Leaderboard. */
		echo '<section class="nt-card"><header class="nt-card__h"><div><h2>Team performance</h2><p>Ranked by till sales. Click a name to see everything they did.</p></div></header>';
		echo '<div class="nt-table"><table class="nt-board"><thead><tr><th class="c">#</th><th>Team member</th><th class="r">Sales</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Unpaid</th><th class="r">Discounts</th><th class="r">Items</th><th class="r">Plans</th><th class="r">Plan pay.</th><th class="r">Appts</th><th class="r">Edits</th><th class="r">Blocked</th><th>Last active</th></tr></thead><tbody>';
		$best = 0;
		foreach ( $sum as $s ) {
			$best = max( $best, $s['total'] );
		}
		$i = 0;
		foreach ( $sum as $id => $s ) {
			$i++;
			$pct = $best > 0 ? round( $s['total'] / $best * 100 ) : 0;
			$url = NURAX_Desk::url( self::PAGE, array( 'tab' => 'log', 'u' => $id, 'from' => $from, 'to' => $to ) );
			echo '<tr><td class="c"><span class="nt-rank' . ( 1 === $i && $s['total'] > 0 ? ' is-top' : '' ) . '">' . (int) $i . '</span></td>';
			echo '<td><a class="nt-who" href="' . esc_url( $url ) . '"><span class="nt-av">' . esc_html( strtoupper( mb_substr( $s['name'], 0, 1 ) ) ) . '</span><span>' . esc_html( $s['name'] ) . '<small>' . esc_html( $s['role'] ) . '</small></span></a><span class="nt-bar"><span style="width:' . (int) $pct . '%"></span></span></td>';
			echo '<td class="r nt-num">' . (int) $s['sales'] . '</td><td class="r nt-num"><strong>' . esc_html( NURAX_Desk::money( $s['total'] ) ) . '</strong></td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $s['paid'] ) ) . '</td>';
			echo '<td class="r nt-num' . ( $s['unpaid'] > 0 ? ' nt-amber' : '' ) . '">' . esc_html( NURAX_Desk::money( $s['unpaid'] ) ) . '</td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $s['disc'] ) ) . '</td><td class="r nt-num">' . (int) $s['items'] . '</td>';
			echo '<td class="r nt-num">' . (int) $s['plans'] . '</td><td class="r nt-num">' . (int) $s['payments'] . '</td><td class="r nt-num">' . (int) $s['appts'] . '</td><td class="r nt-num">' . (int) $s['products'] . '</td>';
			echo '<td class="r nt-num' . ( $s['blocked'] ? ' nt-red' : '' ) . '">' . (int) $s['blocked'] . '</td><td class="nt-mut">' . esc_html( $s['last'] ? NURAX_Desk::date_label( $s['last'], 'j M, H:i' ) : 'No activity' ) . '</td></tr>';
		}
		echo '</tbody>';
		if ( count( $sum ) > 1 ) {
			echo '<tfoot><tr><td></td><td>Team total</td><td class="r nt-num">' . (int) $tot['sales'] . '</td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $tot['total'] ) ) . '</td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $tot['paid'] ) ) . '</td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $tot['unpaid'] ) ) . '</td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $tot['disc'] ) ) . '</td><td colspan="7"></td></tr></tfoot>';
		}
		echo '</table></div><p class="nt-foot">Sales are Point of sale (till) sales recorded under the person who made them. Online shop orders are not linked to a team member.</p></section>';

		/* Recent activity. */
		$recent = (array) $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' WHERE ' . self::feed_where( $from, $to, $uid, '' ) . ' ORDER BY id DESC LIMIT 8' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<section class="nt-card"><header class="nt-card__h"><div><h2>Recent activity</h2><p>The latest actions by your team in this period.</p></div><a class="nd-btn nd-btn--ghost" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $q, array( 'tab' => 'log' ) ) ) ) . '">View full log</a></header>';
		if ( $recent ) {
			$feed( $recent, true );
		} else {
			echo '<div class="nt-empty"><strong>Nothing recorded yet</strong><p>As your team makes sales, records payments, books appointments or edits products, it appears here.</p></div>';
		}
		echo '</section></div>';
		NURAX_Desk::footer();
	}

	private static function perms_form() {
		$p   = self::perms();
		$grp = array(
			'Sales & money'      => array( 'view_all_sales', 'see_totals', 'mark_paid', 'edit_price', 'refunds' ),
			'Tools & pages'      => array( 'reminders', 'marketing', 'social', 'wc_settings' ),
		);
		$lab = self::labels();
		echo '<div class="nt-perms"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nt-perms__main"><input type="hidden" name="action" value="nurax_team_perms">';
		wp_nonce_field( 'nurax_team_perms' );
		foreach ( $grp as $title => $keys ) {
			echo '<section class="nt-card"><header class="nt-card__h"><div><h2>' . esc_html( $title ) . '</h2><p>What shop managers are allowed to do.</p></div></header><div class="nt-switches">';
			foreach ( $keys as $k ) {
				echo '<label class="nt-switch"><span class="nt-switch__t"><strong>' . esc_html( $lab[ $k ][0] ) . '</strong><small>' . esc_html( $lab[ $k ][1] ) . '</small></span><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( ! empty( $p[ $k ] ), true, false ) . '><span class="nt-switch__ui" aria-hidden="true"></span></label>';
			}
			if ( 'Sales & money' === $title ) {
				echo '<label class="nt-switch nt-switch--num"><span class="nt-switch__t"><strong>Highest discount at the till</strong><small>Larger discounts are refused and recorded. 0 = no discounts.</small></span><span class="nt-pct"><input type="number" name="max_disc" min="0" max="100" step="1" value="' . esc_attr( $p['max_disc'] ) . '"><em>%</em></span></label>';
			}
			echo '</div></section>';
		}
		echo '<div class="nt-save"><button class="nd-btn nd-btn--primary">Save permissions</button><span>Changes apply to every shop manager straight away.</span></div></form>';

		echo '<aside class="nt-perms__side"><section class="nt-card nt-card--ink"><h2>Always owner-only</h2><ul class="nt-locks">';
		foreach ( array( 'Delete or trash anything - products, orders, coupons, customers, plans, appointments, WhatsApp orders, contacts, pages', 'Void payments and cancel plans or WhatsApp orders', 'NURA Desk settings, data protection and this Team page', 'Plugins, themes, tools, import / export and new users' ) as $l ) {
			echo '<li>' . esc_html( $l ) . '</li>';
		}
		echo '</ul><p>Attempts are blocked and recorded in the activity log.</p></section>';
		echo '<section class="nt-card"><header class="nt-card__h"><div><h2>Your team</h2></div><a class="nd-btn nd-btn--ghost" href="' . esc_url( admin_url( 'user-new.php' ) ) . '">Add</a></header><ul class="nt-people">';
		foreach ( self::team_users() as $u ) {
			$own = in_array( 'administrator', (array) $u->roles, true );
			echo '<li><span class="nt-av">' . esc_html( strtoupper( mb_substr( $u->display_name, 0, 1 ) ) ) . '</span><span>' . esc_html( $u->display_name ) . '<small>' . esc_html( $u->user_email ) . '</small></span><em class="' . ( $own ? 'is-own' : '' ) . '">' . esc_html( $own ? 'Owner' : 'Shop manager' ) . '</em></li>';
		}
		echo '</ul><p class="nt-foot">Give every person their own login so their work is recorded under their name.</p></section></aside></div>';
	}
}
