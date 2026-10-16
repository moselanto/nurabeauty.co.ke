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
		$tab                           = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'team'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		list( $from, $to, $uid, $area ) = self::filters();
		$q     = array( 'from' => $from, 'to' => $to, 'u' => $uid, 'area' => $area );
		$extra = '&from=' . $from . '&to=' . $to . '&u=' . $uid . '&area=' . $area;
		$nav   = '<nav class="nd-subnav"><a class="' . ( 'team' === $tab ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, $q ) ) . '">Team performance</a><a class="' . ( 'log' === $tab ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $q, array( 'tab' => 'log' ) ) ) ) . '">Activity log</a><a class="' . ( 'perms' === $tab ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => 'perms' ) ) ) . '">Permissions</a></nav>';
		$exp   = 'perms' === $tab ? '' : NURAX_Desk::export_btns( 'log' === $tab ? 'activity' : 'team', 'log' === $tab ? 'Activity report' : 'Team report', $extra );
		NURAX_Desk::header( self::PAGE, 'Team & activity', $nav . $exp );

		if ( 'perms' === $tab ) {
			self::perms_form();
			NURAX_Desk::footer();
			return;
		}

		$users = self::team_users();
		echo '<form method="get" class="nd-toolbar nd-team-filter"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><nav class="nd-pills">';
		foreach ( array( 'today' => 'Today', 'week' => '7 days', 'month' => 'This month', 'last' => 'Last month', 'year' => 'This year' ) as $k => $l ) {
			echo '<a class="nd-pill" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => $tab, 'r' => $k, 'u' => $uid, 'area' => $area ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav><span class="nd-team-filter__f"><input type="date" name="from" value="' . esc_attr( $from ) . '"><span>to</span><input type="date" name="to" value="' . esc_attr( $to ) . '"><select name="u"><option value="0">Everyone</option>';
		foreach ( $users as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( $uid, $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select>';
		if ( 'log' === $tab ) {
			echo '<select name="area"><option value="">All activity</option>';
			foreach ( self::areas() as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $area, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
		}
		echo '<button class="nd-btn nd-btn--ghost">Apply</button></span></form>';
		echo '<p class="nd-mut nd-small">Showing ' . esc_html( NURAX_Desk::date_label( $from, 'j M Y' ) . ' - ' . NURAX_Desk::date_label( $to, 'j M Y' ) ) . '</p>';

		if ( 'log' === $tab ) {
			$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$w     = self::feed_where( $from, $to, $uid, $area );
			$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE ' . $w ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE ' . $w . ' ORDER BY id DESC LIMIT 50 OFFSET %d', ( $paged - 1 ) * 50 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $rows ) {
				echo '<div class="nd-empty"><strong>No activity for this period.</strong><p>Every sale, payment, plan, appointment, price change, refund, deletion and sign-in by your team appears here.</p></div>';
				NURAX_Desk::footer();
				return;
			}
			$areas = self::areas();
			echo '<div class="nd-table-wrap"><table class="nd-table nd-feed"><thead><tr><th>When</th><th>Who</th><th>Area</th><th>What happened</th><th>Amount</th><th>IP</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr class="nd-feed--' . esc_attr( $r->level ) . '"><td><small>' . esc_html( NURAX_Desk::date_label( $r->created_at, 'D j M, H:i' ) ) . '</small></td><td><strong>' . esc_html( $r->user_name ) . '</strong><small>' . esc_html( $r->role ) . '</small></td><td><span class="nd-area nd-area--' . esc_attr( $r->area ) . '">' . esc_html( isset( $areas[ $r->area ] ) ? $areas[ $r->area ] : $r->area ) . '</span></td><td>' . esc_html( $r->summary ) . '</td><td>' . ( (float) $r->amount ? esc_html( NURAX_Desk::money( $r->amount ) ) : '' ) . '</td><td><small>' . esc_html( $r->ip ) . '</small></td></tr>';
			}
			echo '</tbody></table></div>';
			NURAX_Desk_Plans::pager( $total, 50, $paged, array_merge( $q, array( 'tab' => 'log' ) ), self::PAGE );
			NURAX_Desk::footer();
			return;
		}

		$sum = self::summary( $from, $to );
		$tot = array( 'sales' => 0, 'total' => 0, 'disc' => 0, 'blocked' => 0 );
		foreach ( $sum as $s ) {
			$tot['sales']   += $s['sales'];
			$tot['total']   += $s['total'];
			$tot['disc']    += $s['disc'];
			$tot['blocked'] += $s['blocked'];
		}
		echo '<div class="nd-kpis">';
		NURAX_Desk::kpi( 'Till sales by the team', NURAX_Desk::money( $tot['total'] ), $tot['sales'] . ' sales', 'ink' );
		NURAX_Desk::kpi( 'Discounts given', NURAX_Desk::money( $tot['disc'] ), 'across all staff', $tot['disc'] ? 'amber' : '' );
		NURAX_Desk::kpi( 'Team members', (string) count( $sum ), 'owner + shop managers', 'gold' );
		NURAX_Desk::kpi( 'Blocked attempts', (string) $tot['blocked'], 'tried something not allowed', $tot['blocked'] ? 'red' : '', NURAX_Desk::url( self::PAGE, array_merge( $q, array( 'tab' => 'log', 'area' => 'blocked' ) ) ) );
		echo '</div><div class="nd-team">';
		$best = 0;
		foreach ( $sum as $s ) {
			$best = max( $best, $s['total'] );
		}
		foreach ( $sum as $id => $s ) {
			$pct = $best > 0 ? round( $s['total'] / $best * 100 ) : 0;
			echo '<section class="nd-card nd-staff"><header><span class="nd-avatar">' . esc_html( strtoupper( mb_substr( $s['name'], 0, 1 ) ) ) . '</span><div><strong>' . esc_html( $s['name'] ) . '</strong><small>' . esc_html( $s['role'] ) . ( $s['last'] ? ' - last active ' . esc_html( NURAX_Desk::date_label( $s['last'], 'j M, H:i' ) ) : '' ) . '</small></div></header>';
			echo '<div class="nd-staff__big">' . esc_html( NURAX_Desk::money( $s['total'] ) ) . '<small>' . (int) $s['sales'] . ' sales, ' . (int) $s['items'] . ' items</small></div>';
			echo '<div class="nd-staff__bar"><span style="width:' . (int) $pct . '%"></span></div>';
			echo '<dl class="nd-staff__dl"><div><dt>Paid</dt><dd>' . esc_html( NURAX_Desk::money( $s['paid'] ) ) . '</dd></div><div><dt>Unpaid</dt><dd>' . esc_html( NURAX_Desk::money( $s['unpaid'] ) ) . '</dd></div><div><dt>Discounts</dt><dd>' . esc_html( NURAX_Desk::money( $s['disc'] ) ) . '</dd></div><div><dt>Plans</dt><dd>' . (int) $s['plans'] . '</dd></div><div><dt>Plan payments</dt><dd>' . (int) $s['payments'] . '</dd></div><div><dt>Appointments</dt><dd>' . (int) $s['appts'] . '</dd></div><div><dt>Product edits</dt><dd>' . (int) $s['products'] . '</dd></div><div><dt>Blocked</dt><dd' . ( $s['blocked'] ? ' class="nd-red"' : '' ) . '>' . (int) $s['blocked'] . '</dd></div></dl>';
			echo '<a class="nd-btn nd-btn--ghost nd-btn--block" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => 'log', 'u' => $id, 'from' => $from, 'to' => $to ) ) ) . '">See everything ' . esc_html( strtok( $s['name'], ' ' ) ) . ' did</a></section>';
		}
		echo '</div><p class="nd-mut nd-small">Sales are till sales (Point of sale) made by each person. Online shop orders are not linked to a team member.</p>';
		NURAX_Desk::footer();
	}

	private static function perms_form() {
		$p = self::perms();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form"><input type="hidden" name="action" value="nurax_team_perms">';
		wp_nonce_field( 'nurax_team_perms' );
		echo '<section class="nd-card nd-sec"><header class="nd-card__h"><h2>Always owner-only</h2></header><ul class="nd-locks">';
		foreach ( array( 'Delete or trash anything: products, orders, coupons, customers, plans, appointments, WhatsApp orders, contacts, pages', 'Void payments, cancel payment plans and cancel WhatsApp orders', 'NURA Desk settings, team permissions, data protection and this Team page', 'Plugins, themes, WordPress tools, import / export and adding new users' ) as $l ) {
			echo '<li>' . esc_html( $l ) . '</li>';
		}
		echo '</ul><p class="nd-mut nd-small">Shop managers can add and edit data. If they try something owner-only it is blocked and recorded in the activity log.</p></section>';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>What shop managers can do</h2></header><div class="nd-fields nd-fields--2">';
		foreach ( self::labels() as $k => $l ) {
			echo '<label class="nd-field nd-field--check"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( ! empty( $p[ $k ] ), true, false ) . '><span><strong>' . esc_html( $l[0] ) . '</strong><small>' . esc_html( $l[1] ) . '</small></span></label>';
		}
		echo '<label class="nd-field"><span class="nd-label">Highest discount a shop manager can give (%)</span><input type="number" name="max_disc" min="0" max="100" step="1" value="' . esc_attr( $p['max_disc'] ) . '"><small>Applies at the till. 0 = no discounts.</small></label>';
		echo '</div></section><p><button class="nd-btn nd-btn--primary">Save permissions</button></p></form>';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Your team</h2></header><ul class="nd-locks nd-locks--users">';
		foreach ( self::team_users() as $u ) {
			echo '<li><strong>' . esc_html( $u->display_name ) . '</strong> - ' . esc_html( $u->user_email ) . ' - ' . esc_html( in_array( 'administrator', (array) $u->roles, true ) ? 'Owner / admin' : 'Shop manager' ) . '</li>';
		}
		echo '</ul><p class="nd-mut nd-small">Add a shop manager under Users > Add New and choose the role "Shop manager". Give every person their own login so their work is recorded under their name.</p></section>';
	}
}
