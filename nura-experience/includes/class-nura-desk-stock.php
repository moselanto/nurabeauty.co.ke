<?php
/**
 * NURA Desk - Stock (NURA Stocks phase 1).
 *
 * One place to see and control shelf stock for every product and colour:
 * On shelf, Reserved (payment plans + unpaid WhatsApp orders), Available, where
 * it is kept (owner-named places), and a history of every change with a reason.
 *
 * WooCommerce stays the single stock number (Available). Reservations take the
 * piece out of WooCommerce stock so the website cannot sell it twice:
 *   - Payment plans: already done by NURAX_Desk_Plans::reserve() (stock_reserved = 1).
 *   - WhatsApp orders: reserved when placed, marked sold when paid, put back
 *     when cancelled, and released when the till turns the order into a
 *     WooCommerce order (that order then takes the stock itself).
 * On shelf = Available + Reserved.
 *
 * @package NURA_Experience
 * @since   1.110.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Stock {

	const PAGE  = 'nurax-stock';
	const DBV   = '1.0.0';
	const DBOPT = 'nurax_stock_db';
	const LOC   = '_nura_stock_loc';   // Per-place split on the stock-managing product/variation: array( place key => qty ).
	const PERM  = 'stock';             // NURAX_Staff permission: change stock.
	const COSTS = 'nurax_stock_costs_loaded';
	const STOP  = '_nura_stop_selling'; // 1.111.0: set by staff on the Stock tab = the only way an item shows Out of stock.

	/** Reasons offered on the change form. key => array( label, direction ). */
	public static function reasons() {
		return array(
			'received' => array( 'Goods received from supplier', 'in' ),
			'returned' => array( 'Returned by customer', 'in' ),
			'found'    => array( 'Found / count correction (up)', 'in' ),
			'damaged'  => array( 'Damaged', 'out' ),
			'lost'     => array( 'Lost / missing', 'out' ),
			'own_use'  => array( 'Used in the studio / display', 'out' ),
			'supplier' => array( 'Returned to supplier', 'out' ),
			'count_dn' => array( 'Count correction (down)', 'out' ),
			'count'    => array( 'Stock-take (set to counted number)', 'set' ),
		);
	}

	/** Labels for history sources. */
	public static function sources() {
		return array(
			'manual'     => 'Stock change',
			'count'      => 'Stock-take',
			'move'       => 'Moved between places',
			'wa_reserve' => 'WhatsApp order reserved',
			'wa_sold'    => 'WhatsApp order paid',
			'wa_unpaid'  => 'WhatsApp order marked unpaid',
			'wa_release' => 'WhatsApp order released',
			'order'      => 'Website / till order',
			'other'      => 'Product edit or other',
			'cost'       => 'Buying price set',
			'stop'       => 'Stopped selling (Out of stock)',
			'resume'     => 'Selling again',
		);
	}

	private static $busy   = false;   // True while this class changes stock itself (skip the generic logger).
	private static $before = array(); // product id => stock before a WooCommerce change.
	private static $ctx    = 0;       // Order id when WooCommerce changes stock for an order.

	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		// After NURAX_Desk::menu() (priority 56) so the NURA Desk parent exists; earlier priorities
		// register the page under the wrong hook (403 on admin.php?page=nurax-stock, menu link to /wp-admin/nurax-stock).
		add_action( 'admin_menu', array( $this, 'menu' ), 57 );
		foreach ( array( 'adjust', 'move', 'count', 'export', 'costs', 'stop' ) as $a ) {
			add_action( 'admin_post_nurax_stock_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
		// Generic history of every WooCommerce stock change (orders, product edits, plans, imports).
		add_action( 'woocommerce_product_before_set_stock', array( __CLASS__, 'before_set' ) );
		add_action( 'woocommerce_variation_before_set_stock', array( __CLASS__, 'before_set' ) );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'after_set' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'after_set' ) );
		add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'order_ctx' ), 99, 3 );
		// 1.110.2: name the order on stock put back by WooCommerce (cancelled / pending / refunded) and on
		// payment-complete reductions. Set before WooCommerce's own stock handlers (priority 10), cleared after.
		foreach ( array( 'pending', 'failed', 'on-hold', 'processing', 'completed', 'refunded', 'cancelled' ) as $st ) {
			add_action( 'woocommerce_order_status_' . $st, array( __CLASS__, 'order_ctx_id' ), 1 );
		}
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_ctx_id' ), 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_ctx_clear' ), 999 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_ctx_clear' ), 999 );
		// 1.111.0 owner rule: nothing goes out of stock by itself. Stock held is samples; suppliers are on
		// standby, so a wig stays buyable at 0 (WooCommerce backorder, shown as In stock) until staff press
		// 'Stop selling' on the Stock tab. Desk setting 'stock_never_oos' (default on) switches this off.
		foreach ( array( 'woocommerce_product_get_backorders', 'woocommerce_product_variation_get_backorders' ) as $hk ) {
			add_filter( $hk, array( __CLASS__, 'f_backorders' ), 20, 2 );
		}
		foreach ( array( 'woocommerce_product_get_stock_status', 'woocommerce_product_variation_get_stock_status' ) as $hk ) {
			add_filter( $hk, array( __CLASS__, 'f_stock_status' ), 20, 2 );
		}
		// WhatsApp orders.
		add_action( 'nurax_wa_order_saved', array( __CLASS__, 'wa_saved' ), 20, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'wa_meta' ), 20, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'wa_meta' ), 20, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'wa_meta_deleted' ), 20, 4 );
	}

	/* ------------------------------------------------------------------ */
	/* Database                                                            */
	/* ------------------------------------------------------------------ */

	public static function t_moves() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_stock_moves';
	}

	public static function t_res() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_stock_res';
	}

	public static function maybe_install() {
		if ( self::DBV !== get_option( self::DBOPT ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$m = self::t_moves();
		$r = self::t_res();
		dbDelta( "CREATE TABLE $m (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  item_name varchar(255) NOT NULL DEFAULT '',
  delta int(11) NOT NULL DEFAULT 0,
  qty_before int(11) DEFAULT NULL,
  qty_after int(11) DEFAULT NULL,
  source varchar(20) NOT NULL DEFAULT 'manual',
  reason varchar(30) NOT NULL DEFAULT '',
  ref varchar(40) NOT NULL DEFAULT '',
  place_from varchar(60) NOT NULL DEFAULT '',
  place_to varchar(60) NOT NULL DEFAULT '',
  note varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_name varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY product_id (product_id),
  KEY created_at (created_at),
  KEY source (source)
) $c;" );
		dbDelta( "CREATE TABLE $r (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source varchar(10) NOT NULL DEFAULT 'wa',
  ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  qty int(11) NOT NULL DEFAULT 0,
  status varchar(10) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY ref (source,ref_id),
  KEY product_id (product_id),
  KEY status (status)
) $c;" );
		update_option( self::DBOPT, self::DBV, false );
	}

	/* ------------------------------------------------------------------ */
	/* Never out of stock unless staff say so (1.111.0)                    */
	/* ------------------------------------------------------------------ */

	public static function never_oos() {
		$s = NURAX_Desk::settings();
		return ! isset( $s['stock_never_oos'] ) || (int) $s['stock_never_oos'];
	}

	public static function stopped( $p ) {
		return $p instanceof WC_Product && (int) $p->get_meta( self::STOP );
	}

	/** Tracked items keep selling at 0 (backorder) unless stopped by staff. */
	public static function f_backorders( $v, $p ) {
		if ( ! self::never_oos() || ! ( $p instanceof WC_Product ) || ! $p->get_manage_stock( 'edit' ) ) {
			return $v;
		}
		return self::stopped( $p ) ? 'no' : 'yes';
	}

	/** Shoppers, Google and feeds see In stock instead of 'on backorder'; 'Stop selling' shows Out of stock. */
	public static function f_stock_status( $v, $p ) {
		if ( ! self::never_oos() || ! ( $p instanceof WC_Product ) ) {
			return $v;
		}
		if ( self::stopped( $p ) ) {
			return 'outofstock';
		}
		return 'onbackorder' === $v ? 'instock' : $v;
	}

	/** Re-save so WooCommerce stores the right stock status after a stop / sell-again change. */
	private static function resave( $p ) {
		$p = wc_get_product( $p->get_id() );
		if ( $p ) {
			$p->set_stock_status( self::stopped( $p ) ? 'outofstock' : ( (int) $p->get_stock_quantity( 'edit' ) > 0 ? 'instock' : 'onbackorder' ) );
			$p->save();
			if ( $p->is_type( 'variation' ) && $p->get_parent_id() ) {
				WC_Product_Variable::sync( $p->get_parent_id() );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Places (named by the owner in Desk settings)                        */
	/* ------------------------------------------------------------------ */

	/** Place key => name, from the "Stock places" box in Desk settings (one per line). */
	public static function places() {
		$s   = NURAX_Desk::settings();
		$raw = isset( $s['stock_places'] ) ? (string) $s['stock_places'] : '';
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n|,/', $raw ) as $n ) {
			$n = trim( wp_strip_all_tags( $n ) );
			if ( '' !== $n ) {
				$out[ sanitize_title( $n ) ] = mb_substr( $n, 0, 60 );
			}
		}
		if ( ! $out ) {
			$out['studio'] = 'Studio';
		}
		return $out;
	}

	public static function split( $pid ) {
		$v = get_post_meta( $pid, self::LOC, true );
		$v = is_array( $v ) ? array_map( 'intval', $v ) : array();
		return array_filter( $v );
	}

	/** Keep the per-place split in step with the shelf total: extra pieces go to $place (or the first place), missing pieces come off $place first, then the others. */
	public static function rebalance( $pid, $shelf, $place = '' ) {
		$places = self::places();
		$split  = self::split( $pid );
		$place  = isset( $places[ $place ] ) ? $place : (string) key( $places );
		$diff   = (int) $shelf - array_sum( $split );
		if ( $diff > 0 ) {
			$split[ $place ] = ( isset( $split[ $place ] ) ? $split[ $place ] : 0 ) + $diff;
		} elseif ( $diff < 0 ) {
			$need  = -$diff;
			$order = array_unique( array_merge( array( $place ), array_keys( $split ) ) );
			foreach ( $order as $k ) {
				if ( $need <= 0 ) {
					break;
				}
				$have        = isset( $split[ $k ] ) ? $split[ $k ] : 0;
				$take        = min( $have, $need );
				$split[ $k ] = $have - $take;
				$need       -= $take;
			}
		}
		update_post_meta( $pid, self::LOC, array_filter( $split ) );
	}

	/* ------------------------------------------------------------------ */
	/* Numbers                                                             */
	/* ------------------------------------------------------------------ */

	/** Stock-managing product for any product or variation id (variations that follow the parent resolve to the parent). */
	public static function managed( $id ) {
		$p = $id ? wc_get_product( $id ) : null;
		if ( ! $p || ! $p->managing_stock() ) {
			return null;
		}
		$mid = (int) $p->get_stock_managed_by_id();
		return $mid === (int) $p->get_id() ? $p : wc_get_product( $mid );
	}

	/** Reserved pieces per stock-managing product id: array( id => array( 'plan' => n, 'wa' => n ) ). */
	public static function reserved() {
		global $wpdb;
		$out  = array();
		$rows = $wpdb->get_results( 'SELECT product_id, variation_id FROM ' . NURAX_Desk::t_plans() . ' WHERE stock_reserved = 1' ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$m = self::managed( (int) $r->variation_id ? (int) $r->variation_id : (int) $r->product_id );
			if ( $m ) {
				$k                 = (int) $m->get_id();
				$out[ $k ]['plan'] = ( isset( $out[ $k ]['plan'] ) ? $out[ $k ]['plan'] : 0 ) + 1;
			}
		}
		$rows = $wpdb->get_results( 'SELECT product_id, SUM(qty) q FROM ' . self::t_res() . " WHERE status = 'active' GROUP BY product_id" ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->product_id ]['wa'] = (int) $r->q;
		}
		return $out;
	}

	/** Order id from a WooCommerce order status / payment hook. */
	public static function order_ctx_id( $order_id ) {
		if ( $order_id ) {
			self::$ctx = (int) $order_id;
		}
	}

	public static function order_ctx_clear() {
		self::$ctx = 0;
	}

	public static function reserved_for( $id ) {
		$r = self::reserved();
		return isset( $r[ $id ] ) ? array_sum( $r[ $id ] ) : 0;
	}

	/** All stock rows: one per stock-managing product or variation. */
	public static function rows() {
		$res  = self::reserved();
		$last = self::last_changes();
		$ids  = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$rows = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$items = array();
			if ( $p->is_type( 'variable' ) ) {
				if ( $p->get_manage_stock() ) {
					$items[] = $p; // Stock kept on the parent.
				}
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v && true === $v->get_manage_stock( 'edit' ) ) {
						$items[] = $v;
					}
				}
			} elseif ( $p->get_manage_stock() ) {
				$items[] = $p;
			}
			foreach ( $items as $it ) {
				$k      = (int) $it->get_id();
				$avail  = (int) $it->get_stock_quantity();
				$plan   = isset( $res[ $k ]['plan'] ) ? (int) $res[ $k ]['plan'] : 0;
				$wa     = isset( $res[ $k ]['wa'] ) ? (int) $res[ $k ]['wa'] : 0;
				$low    = $it->get_low_stock_amount();
				$low    = '' === $low || null === $low ? (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) : (int) $low;
				$rows[] = array(
					'id'      => $k,
					'parent'  => (int) $p->get_id(),
					'product' => $p->get_name(),
					'colour'  => $it->is_type( 'variation' ) ? wc_get_formatted_variation( $it, true, false, false ) : '',
					'sku'     => $it->get_sku(),
					'avail'   => $avail,
					'plan'    => $plan,
					'wa'      => $wa,
					'res'     => $plan + $wa,
					'shelf'   => $avail + $plan + $wa,
					'low'     => $low,
					'state'   => self::stopped( $it ) ? 'stop' : ( $avail <= 0 ? ( self::never_oos() ? 'order' : 'out' ) : ( $avail <= $low ? 'low' : 'in' ) ),
					'cost'    => (string) $it->get_meta( '_nura_cost' ),
					'split'   => self::split( $k ),
					'last'    => isset( $last[ $k ] ) ? $last[ $k ] : '',
				);
			}
		}
		return $rows;
	}

	private static function last_changes() {
		global $wpdb;
		$out = array();
		foreach ( (array) $wpdb->get_results( 'SELECT product_id, MAX(created_at) t FROM ' . self::t_moves() . ' GROUP BY product_id' ) as $r ) { // phpcs:ignore WordPress.DB
			$out[ (int) $r->product_id ] = $r->t;
		}
		return $out;
	}

	public static function item_name( $p ) {
		if ( ! $p ) {
			return '';
		}
		if ( $p->is_type( 'variation' ) ) {
			$parent = wc_get_product( $p->get_parent_id() );
			return ( $parent ? $parent->get_name() : '' ) . ' - ' . wc_get_formatted_variation( $p, true, false, false );
		}
		return $p->get_name();
	}

	/* ------------------------------------------------------------------ */
	/* History                                                             */
	/* ------------------------------------------------------------------ */

	public static function log( $pid, $delta, $before, $after, $source, $o = array() ) {
		global $wpdb;
		self::maybe_install();
		$u = wp_get_current_user();
		$p = wc_get_product( $pid );
		$wpdb->insert( self::t_moves(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'created_at' => current_time( 'mysql' ),
			'product_id' => (int) $pid,
			'item_name'  => mb_substr( self::item_name( $p ), 0, 255 ),
			'delta'      => (int) $delta,
			'qty_before' => null === $before ? null : (int) $before,
			'qty_after'  => null === $after ? null : (int) $after,
			'source'     => $source,
			'reason'     => isset( $o['reason'] ) ? mb_substr( $o['reason'], 0, 30 ) : '',
			'ref'        => isset( $o['ref'] ) ? mb_substr( $o['ref'], 0, 40 ) : '',
			'place_from' => isset( $o['from'] ) ? mb_substr( $o['from'], 0, 60 ) : '',
			'place_to'   => isset( $o['to'] ) ? mb_substr( $o['to'], 0, 60 ) : '',
			'note'       => isset( $o['note'] ) ? mb_substr( $o['note'], 0, 255 ) : '',
			'user_id'    => $u && $u->ID ? (int) $u->ID : 0,
			'user_name'  => $u && $u->ID ? mb_substr( $u->display_name, 0, 120 ) : 'System',
		) );
	}

	public static function before_set( $p ) {
		if ( $p instanceof WC_Product ) {
			self::$before[ $p->get_id() ] = $p->get_stock_quantity( 'edit' );
		}
	}

	public static function after_set( $p ) {
		if ( self::$busy || ! ( $p instanceof WC_Product ) ) {
			return;
		}
		$id     = (int) $p->get_id();
		$fresh  = wc_get_product( $id );
		$after  = $fresh ? $fresh->get_stock_quantity( 'edit' ) : $p->get_stock_quantity( 'edit' );
		$before = array_key_exists( $id, self::$before ) ? self::$before[ $id ] : null;
		unset( self::$before[ $id ] );
		if ( null !== $before && (int) $before === (int) $after ) {
			return;
		}
		$delta = null === $before ? 0 : (int) $after - (int) $before;
		self::log( $id, $delta, $before, $after, self::$ctx ? 'order' : 'other', array( 'ref' => self::$ctx ? '#' . self::$ctx : '', 'note' => self::$ctx && $delta > 0 ? 'Stock put back from order #' . self::$ctx : '' ) );
		self::rebalance( $id, (int) $after + self::reserved_for( $id ) );
	}

	/** Remember which order WooCommerce is changing stock for (value passed through unchanged). */
	public static function order_ctx( $qty, $order = null, $item = null ) {
		if ( $order instanceof WC_Order ) {
			self::$ctx = (int) $order->get_id();
		}
		return $qty;
	}

	/** Change WooCommerce stock without the generic logger; returns array( before, after ). */
	private static function set_stock( $p, $n, $op ) {
		self::$busy = true;
		$before     = (int) $p->get_stock_quantity( 'edit' );
		wc_update_product_stock( $p, (int) $n, $op );
		$fresh      = wc_get_product( $p->get_id() );
		self::$busy = false;
		return array( $before, $fresh ? (int) $fresh->get_stock_quantity( 'edit' ) : $before );
	}

	/* ------------------------------------------------------------------ */
	/* WhatsApp orders: reserve when placed, sold when paid, put back when */
	/* cancelled, released when the till turns it into a WooCommerce order */
	/* ------------------------------------------------------------------ */

	private static function is_wa( $post_id ) {
		return 'nurax_wa_order' === get_post_type( $post_id );
	}

	public static function wa_saved( $post_id, $data = array() ) {
		self::wa_reserve( (int) $post_id, is_array( $data ) ? $data : null );
	}

	public static function wa_reserve( $post_id, $data = null ) {
		global $wpdb;
		self::maybe_install();
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t_res() . " WHERE source = 'wa' AND ref_id = %d AND status IN ('active','sold')", $post_id ) ) ) { // phpcs:ignore WordPress.DB
			return; // Already holding stock for this order.
		}
		if ( null === $data ) {
			$data = get_post_meta( $post_id, '_nwo', true );
		}
		if ( ! is_array( $data ) || empty( $data['items'] ) || (int) get_post_meta( $post_id, '_nwo_wc_order', true ) || 'cancelled' === get_post_meta( $post_id, '_nwo_status', true ) ) {
			return;
		}
		$paid = self::wa_paid( $post_id );
		foreach ( (array) $data['items'] as $it ) {
			$qty = isset( $it['qty'] ) ? max( 0, (int) $it['qty'] ) : 0;
			$m   = self::managed( isset( $it['id'] ) ? (int) $it['id'] : 0 );
			if ( ! $qty || ! $m ) {
				continue; // Services, sourced or untracked items are not held.
			}
			list( $b, $a ) = self::set_stock( $m, $qty, 'decrease' );
			$now           = current_time( 'mysql' );
			$wpdb->insert( self::t_res(), array( 'source' => 'wa', 'ref_id' => $post_id, 'product_id' => (int) $m->get_id(), 'qty' => $qty, 'status' => $paid ? 'sold' : 'active', 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::log( $m->get_id(), $a - $b, $b, $a, $paid ? 'wa_sold' : 'wa_reserve', array( 'ref' => 'WA #' . $post_id ) );
			if ( $paid ) {
				self::rebalance( $m->get_id(), $a + self::reserved_for( $m->get_id() ) );
			}
		}
	}

	private static function wa_paid( $post_id ) {
		return 'paid' === get_post_meta( $post_id, '_nwo_pay', true ) || '' !== (string) get_post_meta( $post_id, '_nwo_paid', true );
	}

	/** Put held pieces back on the website (cancelled order, or the till took over the order). */
	public static function wa_release( $post_id, $why ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_res() . " WHERE source = 'wa' AND ref_id = %d AND status IN ('active','sold')", $post_id ) ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$wpdb->update( self::t_res(), array( 'status' => 'released', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $r->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$p = wc_get_product( (int) $r->product_id );
			if ( $p ) {
				list( $b, $a ) = self::set_stock( $p, (int) $r->qty, 'increase' );
				self::log( $p->get_id(), $a - $b, $b, $a, 'wa_release', array( 'ref' => 'WA #' . $post_id, 'note' => $why ) );
				self::rebalance( $p->get_id(), $a + self::reserved_for( $p->get_id() ) );
			}
		}
	}

	private static function wa_mark( $post_id, $from, $to, $source ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_res() . " WHERE source = 'wa' AND ref_id = %d AND status = %s", $post_id, $from ) ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$wpdb->update( self::t_res(), array( 'status' => $to, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $r->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$p = wc_get_product( (int) $r->product_id );
			$q = $p ? (int) $p->get_stock_quantity( 'edit' ) : null;
			self::log( (int) $r->product_id, 0, $q, $q, $source, array( 'ref' => 'WA #' . $post_id, 'note' => (int) $r->qty . ' pc' ) );
			if ( $p ) {
				self::rebalance( $p->get_id(), (int) $q + self::reserved_for( $p->get_id() ) );
			}
		}
	}

	public static function wa_meta( $meta_id, $post_id, $key, $value ) {
		if ( ! in_array( $key, array( '_nwo_status', '_nwo_pay', '_nwo_wc_order' ), true ) || ! self::is_wa( $post_id ) ) {
			return;
		}
		self::maybe_install();
		if ( '_nwo_wc_order' === $key && (int) $value ) {
			self::wa_release( $post_id, 'Till order #' . (int) $value . ' takes the stock' );
		} elseif ( '_nwo_status' === $key ) {
			if ( 'cancelled' === $value ) {
				self::wa_release( $post_id, 'Order cancelled' );
			} elseif ( ! (int) get_post_meta( $post_id, '_nwo_wc_order', true ) && get_post_meta( $post_id, '_nwo', true ) ) {
				self::wa_reserve( $post_id ); // Re-opened after a cancel: hold the pieces again (no-op if already held).
			}
		} elseif ( '_nwo_pay' === $key && 'paid' === $value ) {
			self::wa_mark( $post_id, 'active', 'sold', 'wa_sold' );
		}
	}

	public static function wa_meta_deleted( $meta_ids, $post_id, $key, $value ) {
		if ( '_nwo_pay' === $key && self::is_wa( $post_id ) ) {
			self::wa_mark( $post_id, 'sold', 'active', 'wa_unpaid' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Opening buying prices (register NURA-STK-OS-2026-001 v4.1)          */
	/* ------------------------------------------------------------------ */

	/**
	 * Average making cost per piece (bundles, ventilation, wig cap, labour) for the
	 * stock lines that match a website product: array( product id, name, colours, KES ).
	 * Lines with no website product (Bob Amanda, Empress, Wonderful Frontal,
	 * 2x2 Negatron, Loose Wave Frontal 2/30) are left out until the owner decides.
	 */
	public static function opening_costs() {
		return array(
			array( 364, 'Loose Wave Frontal', array( 'black', 'burgundy', '33', 'red wine', '30' ), '1934.88' ),
			array( 243, '4x4 Loose Wave Closure', array( 'black', 'c33', '33', 'c1 99j', 'c199j', '99j' ), '1606.78' ),
			array( 274, 'Bone Straight High Fibre', array( 'black', '613', 'dark piano', 'piano dark' ), '2488.59' ),
			array( 377, 'Body Wave', array( 'black', '613', 'piano', 'piano dark', 'dark piano' ), '2456.55' ),
		);
	}

	/** Variations of $pid whose colour matches one of $colours (case-insensitive, exact). */
	public static function match_colours( $pid, $colours ) {
		$p   = wc_get_product( $pid );
		$out = array();
		if ( ! $p ) {
			return $out;
		}
		if ( ! $p->is_type( 'variable' ) ) {
			return array( $p );
		}
		foreach ( $p->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v ) {
				continue;
			}
			foreach ( $v->get_variation_attributes( false ) as $val ) {
				// Compare without spaces or hyphens, so 'C199J', 'c1-99j' and 'C1 99J' all match.
				$t = preg_replace( '/[\s\-]+/', '', strtolower( (string) $val ) );
				if ( in_array( $t, array_map( function ( $c ) {
					return preg_replace( '/[\s\-]+/', '', $c );
				}, $colours ), true ) ) {
					$out[] = $v;
					break;
				}
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Screens                                                             */
	/* ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page( NURAX_Desk::MENU, 'Stock', 'Stock', NURAX_Desk::CAP, self::PAGE, array( __CLASS__, 'page' ), 2 );
	}

	public static function can_change() {
		return class_exists( 'NURAX_Staff' ) ? NURAX_Staff::can( self::PERM ) : current_user_can( 'manage_options' );
	}

	private static function see_cost() {
		return method_exists( 'NURAX_Desk_Accounts', 'can_see_cost' ) ? NURAX_Desk_Accounts::can_see_cost() : current_user_can( 'manage_options' );
	}

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		self::maybe_install();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification
		$acts = NURAX_Desk::btn( self::export_url( 'stock' ), 'Download stock (CSV)', 'nd-btn--ghost' ) . ' ' . NURAX_Desk::btn( self::export_url( 'history' ), 'Download history (CSV)', 'nd-btn--ghost' );
		NURAX_Desk::header( self::PAGE, 'Stock', $acts );
		self::msg();
		echo '<style>.ns-t{width:100%;border-collapse:collapse;background:#fff}.ns-t th,.ns-t td{padding:8px 10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.ns-t th{background:#111;color:#fff;font-weight:600}.ns-t td.n,.ns-t th.n{text-align:center}.ns-t tfoot td{font-weight:700;background:#FBF6EA;border-top:2px solid #C9972C}.ns-sub{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}.ns-sub a{padding:6px 12px;border:1px solid #ddd;border-radius:16px;text-decoration:none;color:#111}.ns-sub a.on{background:#111;color:#C9972C;border-color:#111}.ns-out{color:#b32d2e;font-weight:600}.ns-low{color:#9C4A00;font-weight:600}.ns-in{color:#1e7a34}.ns-f{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin:0 0 12px}.ns-f label{display:flex;flex-direction:column;font-size:12px;color:#555}.ns-mini{font-size:12px;color:#666}details.ns-d summary{cursor:pointer;color:#8a6516}details.ns-d form{margin-top:8px;display:grid;gap:6px;max-width:320px}details.ns-d label{display:flex;flex-direction:column;font-size:12px}@media(max-width:782px){.ns-wrap{overflow-x:auto}}</style>';
		$tabs = array( 'list' => 'Stock list', 'count' => 'Stock-take', 'history' => 'History', 'held' => 'Held for orders' );
		if ( current_user_can( 'manage_options' ) && self::see_cost() ) {
			$tabs['costs'] = 'Opening buying prices';
		}
		echo '<nav class="ns-sub">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="' . ( $k === $tab ? 'on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => $k ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav><div class="ns-wrap">';
		if ( 'count' === $tab ) {
			self::screen_count();
		} elseif ( 'history' === $tab ) {
			self::screen_history();
		} elseif ( 'held' === $tab ) {
			self::screen_held();
		} elseif ( 'costs' === $tab && isset( $tabs['costs'] ) ) {
			self::screen_costs();
		} else {
			self::screen_list();
		}
		echo '</div>';
		NURAX_Desk::footer();
	}

	private static function msg() {
		$m   = isset( $_GET['ns_msg'] ) ? sanitize_key( wp_unslash( $_GET['ns_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$map = array(
			'saved'   => array( 'success', 'Stock updated and recorded in History.' ),
			'moved'   => array( 'success', 'Pieces moved. The total did not change.' ),
			'counted' => array( 'success', 'Stock-take saved. Only the lines that changed were updated.' ),
			'costs'   => array( 'success', 'Buying prices saved.' ),
			'stopped' => array( 'success', 'Stopped. The website now shows this item as Out of stock.' ),
			'resumed' => array( 'success', 'On sale again. The website shows it as In stock.' ),
			'reason'  => array( 'error', 'Please choose a reason. Every stock change needs one.' ),
			'qty'     => array( 'error', 'Please enter a valid number of pieces.' ),
			'short'   => array( 'error', 'There are not enough pieces at that place to move.' ),
			'item'    => array( 'error', 'That item does not track stock.' ),
		);
		if ( isset( $map[ $m ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $m ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $m ][1] ) . '</p></div>';
		}
	}

	private static function get( $k ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function filtered_rows() {
		$q     = strtolower( self::get( 'q' ) );
		$state = sanitize_key( self::get( 'state' ) );
		$place = sanitize_key( self::get( 'place' ) );
		return array_values( array_filter( self::rows(), function ( $r ) use ( $q, $state, $place ) {
			if ( $q && false === strpos( strtolower( $r['product'] . ' ' . $r['colour'] . ' ' . $r['sku'] ), $q ) ) {
				return false;
			}
			if ( 'held' === $state ) {
				if ( $r['res'] < 1 ) {
					return false;
				}
			} elseif ( $state && $state !== $r['state'] ) {
				return false;
			}
			if ( $place && empty( $r['split'][ $place ] ) ) {
				return false;
			}
			return true;
		} ) );
	}

	private static function filter_form( $tab ) {
		echo '<form class="ns-f" method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		echo '<label>Search<input type="search" name="q" value="' . esc_attr( self::get( 'q' ) ) . '" placeholder="Wig, colour or code"></label>';
		echo '<label>Show<select name="state">';
		foreach ( array( '' => 'Everything', 'in' => 'In stock', 'low' => 'Running low', 'order' => 'Order from supplier', 'stop' => 'Stopped (out of stock)', 'held' => 'Has reserved pieces' ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( self::get( 'state' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label><label>Place<select name="place"><option value="">All places</option>';
		foreach ( self::places() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( self::get( 'place' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label><button class="nd-btn nd-btn--ghost" type="submit">Filter</button></form>';
	}

	private static function screen_list() {
		$rows   = self::filtered_rows();
		$places = self::places();
		$tot    = array( 'shelf' => 0, 'res' => 0, 'avail' => 0, 'in' => 0, 'out' => 0, 'low' => 0, 'order' => 0, 'stop' => 0 );
		foreach ( $rows as $r ) {
			$tot['shelf'] += $r['shelf'];
			$tot['res']   += $r['res'];
			$tot['avail'] += $r['avail'];
			$tot[ $r['state'] ]++;
		}
		echo '<div class="nd-kpis">';
		echo NURAX_Desk::kpi( 'On shelf', (int) $tot['shelf'], 'pieces in the shop', 'ink' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo NURAX_Desk::kpi( 'Reserved', (int) $tot['res'], 'payment plans + unpaid WhatsApp orders', 'gold' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo NURAX_Desk::kpi( 'Available', (int) $tot['avail'], 'what the website can sell', 'green' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo NURAX_Desk::kpi( 'To order from supplier', (int) ( $tot['order'] + $tot['out'] ), 'items at 0 - still on sale', $tot['order'] + $tot['out'] ? 'amber' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo NURAX_Desk::kpi( 'Stopped (out of stock)', (int) $tot['stop'], 'set by staff', $tot['stop'] ? 'red' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		self::filter_form( 'list' );
		if ( ! $rows ) {
			echo '<p class="nd-empty">No items found. Only products with "Manage stock" switched on appear here.</p>';
			return;
		}
		$can = self::can_change();
		$st  = array( 'in' => array( 'ns-in', 'In stock' ), 'low' => array( 'ns-low', 'Running low' ), 'order' => array( 'ns-low', 'Order from supplier (still on sale)' ), 'out' => array( 'ns-out', 'Out of stock' ), 'stop' => array( 'ns-out', 'Out of stock (stopped by staff)' ) );
		echo '<table class="ns-t"><thead><tr><th>Wig / product</th><th>Colour</th><th>Code</th><th class="n">On shelf</th><th class="n">Reserved</th><th class="n">Available</th><th>Where</th><th>Status</th><th>Last change</th>' . ( $can ? '<th>Change</th>' : '' ) . '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$where = array();
			foreach ( $r['split'] as $k => $n ) {
				$where[] = esc_html( ( isset( $places[ $k ] ) ? $places[ $k ] : 'Other place' ) . ': ' . $n );
			}
			$unplaced = $r['shelf'] - array_sum( $r['split'] );
			if ( $unplaced > 0 ) {
				$where[] = '<span class="ns-mini">Not placed yet: ' . (int) $unplaced . '</span>';
			}
			$res = $r['res'] ? (int) $r['res'] . '<div class="ns-mini">' . ( $r['plan'] ? (int) $r['plan'] . ' plan ' : '' ) . ( $r['wa'] ? (int) $r['wa'] . ' WhatsApp' : '' ) . '</div>' : '0';
			echo '<tr><td>' . esc_html( $r['product'] ) . '</td><td>' . esc_html( $r['colour'] ? $r['colour'] : '-' ) . '</td><td class="ns-mini">' . esc_html( $r['sku'] ) . '</td><td class="n"><strong>' . (int) $r['shelf'] . '</strong></td><td class="n">' . $res . '</td><td class="n">' . (int) $r['avail'] . '</td><td>' . implode( '<br>', $where ) . '</td><td class="' . esc_attr( $st[ $r['state'] ][0] ) . '">' . esc_html( $st[ $r['state'] ][1] ) . '</td><td class="ns-mini">' . esc_html( $r['last'] ? NURAX_Desk::date_label( $r['last'], 'j M Y H:i' ) : '-' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $can ) {
				echo '<td>' . self::change_form( $r, $places ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tr>';
		}
		echo '</tbody><tfoot><tr><td colspan="3">Total (' . count( $rows ) . ' items)</td><td class="n">' . (int) $tot['shelf'] . '</td><td class="n">' . (int) $tot['res'] . '</td><td class="n">' . (int) $tot['avail'] . '</td><td colspan="' . ( $can ? 4 : 3 ) . '"></td></tr></tfoot></table>';
		echo '<p class="ns-mini">On shelf = Available + Reserved. Reserved pieces are held for payment plans and unpaid WhatsApp orders. ' . ( self::never_oos() ? 'Nothing goes out of stock by itself: at 0 a wig stays on sale (order it from the supplier) and Available can go below 0. Only "Stop selling" shows it as Out of stock.' : 'Items at 0 show Out of stock on the website.' ) . ' Name your stock places in Desk settings.</p>';
	}

	private static function change_form( $r, $places ) {
		$opts = '';
		foreach ( self::reasons() as $k => $v ) {
			$opts .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v[0] ) . '</option>';
		}
		$pl = '';
		foreach ( $places as $k => $v ) {
			$pl .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
		}
		$post = esc_url( admin_url( 'admin-post.php' ) );
		$h    = '<details class="ns-d"><summary>Change</summary>';
		$h   .= '<form method="post" action="' . $post . '">' . wp_nonce_field( 'nurax_stock_adjust', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_stock_adjust"><input type="hidden" name="pid" value="' . (int) $r['id'] . '">';
		$h   .= '<label>Reason<select name="reason" required><option value="">Choose...</option>' . $opts . '</select></label>';
		$h   .= '<label>Pieces (for a stock-take: the number counted on the shelf)<input type="number" name="qty" min="0" step="1" required></label>';
		$h   .= '<label>Place<select name="place">' . $pl . '</select></label>';
		$h   .= '<label>Note (supplier, invoice no., what happened)<input type="text" name="note" maxlength="200"></label>';
		$h   .= '<button class="nd-btn nd-btn--primary nd-btn--sm" type="submit">Save change</button></form>';
		if ( count( $places ) > 1 && $r['shelf'] > 0 ) {
			$h .= '<form method="post" action="' . $post . '">' . wp_nonce_field( 'nurax_stock_move', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_stock_move"><input type="hidden" name="pid" value="' . (int) $r['id'] . '">';
			$h .= '<strong>Move between places</strong><label>From<select name="from">' . $pl . '</select></label><label>To<select name="to">' . $pl . '</select></label><label>Pieces<input type="number" name="qty" min="1" step="1" required></label><label>Note<input type="text" name="note" maxlength="200"></label><button class="nd-btn nd-btn--ghost nd-btn--sm" type="submit">Move</button></form>';
		}
		$stop = (int) get_post_meta( (int) $r['id'], self::STOP, true );
		$h   .= '<form method="post" action="' . $post . '">' . wp_nonce_field( 'nurax_stock_stop', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_stock_stop"><input type="hidden" name="pid" value="' . (int) $r['id'] . '"><input type="hidden" name="stop" value="' . ( $stop ? 0 : 1 ) . '">';
		$h   .= '<strong>' . ( $stop ? 'Sell again' : 'Stop selling (show Out of stock)' ) . '</strong><label>Reason<input type="text" name="note" maxlength="200" required></label><button class="nd-btn ' . ( $stop ? 'nd-btn--primary' : 'nd-btn--danger-ghost' ) . ' nd-btn--sm" type="submit">' . ( $stop ? 'Sell again' : 'Stop selling' ) . '</button></form>';
		return $h . '</details>';
	}

	private static function screen_count() {
		if ( ! self::can_change() ) {
			echo '<p class="nd-empty">Only people allowed to change stock can do a stock-take.</p>';
			return;
		}
		self::filter_form( 'count' );
		$rows = self::filtered_rows();
		echo '<p>Count what is physically on the shelf (including pieces held for plans and WhatsApp orders) and type the number. Leave a box empty to skip it. Only lines that differ are changed, and each change is recorded in History.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nurax_stock_count' );
		echo '<input type="hidden" name="action" value="nurax_stock_count"><table class="ns-t"><thead><tr><th>Wig / product</th><th>Colour</th><th>Code</th><th class="n">System on shelf</th><th class="n">Counted</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r['product'] ) . '</td><td>' . esc_html( $r['colour'] ? $r['colour'] : '-' ) . '</td><td class="ns-mini">' . esc_html( $r['sku'] ) . '</td><td class="n">' . (int) $r['shelf'] . '</td><td class="n"><input type="number" min="0" step="1" style="width:80px" name="c[' . (int) $r['id'] . ']"></td></tr>';
		}
		echo '</tbody></table><p><label>Note for this stock-take <input type="text" name="note" maxlength="200" placeholder="e.g. Month-end count, Studio"></label></p><p><button class="nd-btn nd-btn--primary" type="submit">Save stock-take</button></p></form>';
	}

	private static function history_rows( $limit = 300 ) {
		global $wpdb;
		$where = '1=1';
		$args  = array();
		$pid   = absint( self::get( 'pid' ) );
		$from  = self::get( 'from' );
		$to    = self::get( 'to' );
		if ( $pid ) {
			$where .= ' AND product_id = %d';
			$args[] = $pid;
		}
		if ( NURAX_Desk::valid_date( $from ) ) {
			$where .= ' AND created_at >= %s';
			$args[] = $from . ' 00:00:00';
		}
		if ( NURAX_Desk::valid_date( $to ) ) {
			$where .= ' AND created_at <= %s';
			$args[] = $to . ' 23:59:59';
		}
		$sql = 'SELECT * FROM ' . self::t_moves() . " WHERE $where ORDER BY id DESC" . ( $limit ? ' LIMIT ' . (int) $limit : '' );
		return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB
	}

	private static function screen_history() {
		echo '<form class="ns-f" method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="history">';
		echo '<label>From<input type="date" name="from" value="' . esc_attr( self::get( 'from' ) ) . '"></label><label>To<input type="date" name="to" value="' . esc_attr( self::get( 'to' ) ) . '"></label><button class="nd-btn nd-btn--ghost" type="submit">Filter</button></form>';
		$rows = self::history_rows();
		if ( ! $rows ) {
			echo '<p class="nd-empty">No stock changes recorded yet. Every change from now on appears here.</p>';
			return;
		}
		$src = self::sources();
		$rsn = self::reasons();
		echo '<table class="ns-t"><thead><tr><th>When</th><th>Item</th><th class="n">Change</th><th class="n">Before</th><th class="n">After</th><th>What</th><th>Reason / note</th><th>Ref</th><th>By</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$what = isset( $src[ $r->source ] ) ? $src[ $r->source ] : $r->source;
			if ( 'move' === $r->source ) {
				$what .= ': ' . $r->place_from . ' to ' . $r->place_to;
			}
			$why = trim( ( isset( $rsn[ $r->reason ] ) ? $rsn[ $r->reason ][0] : '' ) . ( $r->note ? ' - ' . $r->note : '' ), ' -' );
			$d   = (int) $r->delta;
			echo '<tr><td class="ns-mini">' . esc_html( NURAX_Desk::date_label( $r->created_at, 'j M Y H:i' ) ) . '</td><td>' . esc_html( $r->item_name ) . '</td><td class="n">' . esc_html( $d > 0 ? '+' . $d : (string) $d ) . '</td><td class="n">' . esc_html( null === $r->qty_before ? '-' : $r->qty_before ) . '</td><td class="n">' . esc_html( null === $r->qty_after ? '-' : $r->qty_after ) . '</td><td>' . esc_html( $what ) . '</td><td>' . esc_html( $why ) . '</td><td class="ns-mini">' . esc_html( $r->ref ) . '</td><td class="ns-mini">' . esc_html( $r->user_name ) . '</td></tr>';
		}
		echo '</tbody></table><p class="ns-mini">Before / After are the website (Available) numbers. The latest 300 changes are shown; download the CSV for everything.</p>';
	}

	private static function screen_held() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t_res() . " WHERE status = 'active' ORDER BY id DESC" ); // phpcs:ignore WordPress.DB
		echo '<h2>Unpaid WhatsApp orders</h2>';
		if ( ! $rows ) {
			echo '<p class="nd-empty">No pieces are held for WhatsApp orders right now.</p>';
		} else {
			echo '<table class="ns-t"><thead><tr><th>Order</th><th>Item</th><th class="n">Pieces</th><th>Held since</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$p = wc_get_product( (int) $r->product_id );
				echo '<tr><td>WhatsApp order #' . (int) $r->ref_id . '</td><td>' . esc_html( self::item_name( $p ) ) . '</td><td class="n">' . (int) $r->qty . '</td><td class="ns-mini">' . esc_html( NURAX_Desk::date_label( $r->created_at, 'j M Y H:i' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p class="ns-mini">Pieces come off the shelf when the order is marked paid, and go back on sale if it is cancelled. Payment-plan pieces are listed in Payment plans (marked "Stock reserved").</p>';
	}

	private static function screen_costs() {
		$done = get_option( self::COSTS );
		echo '<p>Buying price per piece from the opening stock register (NURA-STK-OS-2026-001 v4.1): the average making cost of each wig line (bundles, ventilation, wig cap, labour and materials transport). Only colours that match a website product are listed. Bob Amanda, Empress, Wonderful Frontal, 2x2 Negatron and Loose Wave Frontal 2/30 are left out until their website products are decided. Stock numbers are not changed here.</p>';
		if ( $done ) {
			echo '<p class="ns-in">Loaded on ' . esc_html( NURAX_Desk::date_label( $done, 'j M Y H:i' ) ) . '. Saving again overwrites the buying prices of these colours.</p>';
		}
		echo '<table class="ns-t"><thead><tr><th>Website product</th><th>Colours found on the website</th><th class="n">Buying price (KSh)</th></tr></thead><tbody>';
		foreach ( self::opening_costs() as $c ) {
			$nm = array();
			foreach ( self::match_colours( $c[0], $c[2] ) as $v ) {
				$cur  = (string) $v->get_meta( '_nura_cost' );
				$nm[] = esc_html( $v->is_type( 'variation' ) ? wc_get_formatted_variation( $v, true, false, false ) : $v->get_name() ) . ( '' !== $cur ? ' <span class="ns-mini">(now ' . esc_html( $cur ) . ')</span>' : '' );
			}
			echo '<tr><td>' . esc_html( $c[1] ) . ' <span class="ns-mini">#' . (int) $c[0] . '</span></td><td>' . ( $nm ? implode( '<br>', $nm ) : '<span class="ns-out">Product or colours not found - check in Products</span>' ) . '</td><td class="n">' . esc_html( number_format( (float) $c[3], 2 ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nurax_stock_costs' );
		echo '<input type="hidden" name="action" value="nurax_stock_costs"><p><button class="nd-btn nd-btn--primary" type="submit">Save these buying prices</button></p></form>';
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	private static function back( $msg, $tab = 'list' ) {
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'tab' => $tab, 'ns_msg' => $msg ) ) );
		exit;
	}

	private static function guard_change( $action ) {
		NURAX_Desk::guard( $action );
		if ( ! self::can_change() ) {
			if ( class_exists( 'NURAX_Staff' ) ) {
				NURAX_Staff::log( 'blocked', 'Tried to change stock without permission', array( 'level' => 'warn' ) );
			}
			wp_die( esc_html__( 'Sorry, you are not allowed to change stock.', 'nura-experience' ) );
		}
	}

	public static function do_adjust() {
		self::guard_change( 'nurax_stock_adjust' );
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$p      = self::managed( isset( $in['pid'] ) ? absint( $in['pid'] ) : 0 );
		$reason = isset( $in['reason'] ) ? sanitize_key( $in['reason'] ) : '';
		$rs     = self::reasons();
		$qty    = isset( $in['qty'] ) && '' !== $in['qty'] ? absint( $in['qty'] ) : -1;
		$place  = isset( $in['place'] ) ? sanitize_key( $in['place'] ) : '';
		$note   = isset( $in['note'] ) ? sanitize_text_field( $in['note'] ) : '';
		if ( ! $p ) {
			self::back( 'item' );
		}
		if ( ! isset( $rs[ $reason ] ) ) {
			self::back( 'reason' );
		}
		$dir = $rs[ $reason ][1];
		if ( $qty < 0 || ( 'set' !== $dir && 0 === $qty ) ) {
			self::back( 'qty' );
		}
		$id   = (int) $p->get_id();
		$held = self::reserved_for( $id );
		if ( 'set' === $dir ) {
			list( $b, $a ) = self::set_stock( $p, $qty - $held, 'set' ); // Counted shelf pieces include held ones.
			update_post_meta( $id, self::LOC, array() );
		} else {
			list( $b, $a ) = self::set_stock( $p, $qty, 'in' === $dir ? 'increase' : 'decrease' );
		}
		$places = self::places();
		self::log( $id, $a - $b, $b, $a, 'set' === $dir ? 'count' : 'manual', array( 'reason' => $reason, 'note' => $note, 'to' => isset( $places[ $place ] ) ? $places[ $place ] : '' ) );
		self::rebalance( $id, $a + $held, $place );
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'stock', 'Stock - ' . $rs[ $reason ][0] . ': ' . self::item_name( $p ) . ' ' . sprintf( '%+d', $a - $b ), array( 'object' => 'product', 'object_id' => $id ) );
		}
		self::back( 'saved' );
	}

	public static function do_move() {
		self::guard_change( 'nurax_stock_move' );
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$p      = self::managed( isset( $in['pid'] ) ? absint( $in['pid'] ) : 0 );
		$places = self::places();
		$from   = isset( $in['from'] ) ? sanitize_key( $in['from'] ) : '';
		$to     = isset( $in['to'] ) ? sanitize_key( $in['to'] ) : '';
		$qty    = isset( $in['qty'] ) ? absint( $in['qty'] ) : 0;
		if ( ! $p ) {
			self::back( 'item' );
		}
		if ( ! $qty || ! isset( $places[ $from ], $places[ $to ] ) || $from === $to ) {
			self::back( 'qty' );
		}
		$id    = (int) $p->get_id();
		$shelf = (int) $p->get_stock_quantity( 'edit' ) + self::reserved_for( $id );
		$split = self::split( $id );
		// Pieces not yet placed count as being at the first place.
		$first           = (string) key( $places );
		$split[ $first ] = ( isset( $split[ $first ] ) ? $split[ $first ] : 0 ) + max( 0, $shelf - array_sum( $split ) );
		if ( ( isset( $split[ $from ] ) ? $split[ $from ] : 0 ) < $qty ) {
			self::back( 'short' );
		}
		$split[ $from ] -= $qty;
		$split[ $to ]    = ( isset( $split[ $to ] ) ? $split[ $to ] : 0 ) + $qty;
		update_post_meta( $id, self::LOC, array_filter( $split ) );
		$q = (int) $p->get_stock_quantity( 'edit' );
		self::log( $id, 0, $q, $q, 'move', array( 'from' => $places[ $from ], 'to' => $places[ $to ], 'note' => $qty . ' pc' . ( ! empty( $in['note'] ) ? ' - ' . sanitize_text_field( $in['note'] ) : '' ) ) );
		self::back( 'moved' );
	}

	public static function do_stop() {
		self::guard_change( 'nurax_stock_stop' );
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$p    = self::managed( isset( $in['pid'] ) ? absint( $in['pid'] ) : 0 );
		$note = isset( $in['note'] ) ? sanitize_text_field( $in['note'] ) : '';
		if ( ! $p ) {
			self::back( 'item' );
		}
		if ( '' === $note ) {
			self::back( 'reason' );
		}
		$stop = ! empty( $in['stop'] );
		$stop ? update_post_meta( $p->get_id(), self::STOP, 1 ) : delete_post_meta( $p->get_id(), self::STOP );
		self::resave( $p );
		$q = (int) $p->get_stock_quantity( 'edit' );
		self::log( $p->get_id(), 0, $q, $q, $stop ? 'stop' : 'resume', array( 'note' => $note ) );
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'stock', ( $stop ? 'Stopped selling: ' : 'Selling again: ' ) . self::item_name( $p ) . ' - ' . $note, array( 'object' => 'product', 'object_id' => $p->get_id() ) );
		}
		self::back( $stop ? 'stopped' : 'resumed' );
	}

	public static function do_count() {
		self::guard_change( 'nurax_stock_count' );
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$note = isset( $in['note'] ) ? sanitize_text_field( $in['note'] ) : '';
		$n    = 0;
		foreach ( isset( $in['c'] ) && is_array( $in['c'] ) ? $in['c'] : array() as $pid => $v ) {
			if ( '' === trim( (string) $v ) ) {
				continue;
			}
			$p = self::managed( absint( $pid ) );
			if ( ! $p ) {
				continue;
			}
			$id   = (int) $p->get_id();
			$held = self::reserved_for( $id );
			$want = absint( $v ) - $held;
			if ( $want === (int) $p->get_stock_quantity( 'edit' ) ) {
				continue;
			}
			list( $b, $a ) = self::set_stock( $p, $want, 'set' );
			self::log( $id, $a - $b, $b, $a, 'count', array( 'reason' => 'count', 'note' => $note ) );
			self::rebalance( $id, $a + $held );
			$n++;
		}
		if ( $n && class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'stock', 'Stock-take saved: ' . $n . ' item(s) changed' );
		}
		self::back( 'counted', 'count' );
	}

	public static function do_costs() {
		NURAX_Desk::guard( 'nurax_stock_costs' );
		if ( ! current_user_can( 'manage_options' ) || ! self::see_cost() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		foreach ( self::opening_costs() as $c ) {
			foreach ( self::match_colours( $c[0], $c[2] ) as $v ) {
				$v->update_meta_data( '_nura_cost', wc_format_decimal( $c[3] ) );
				$v->save_meta_data();
				$q = $v->managing_stock() ? (int) $v->get_stock_quantity( 'edit' ) : null;
				self::log( $v->get_id(), 0, $q, $q, 'cost', array( 'note' => 'Buying price ' . $c[3] . ' (opening register v4.1)' ) );
			}
		}
		update_option( self::COSTS, current_time( 'mysql' ), false );
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'product', 'Opening buying prices loaded from stock register v4.1' );
		}
		self::back( 'costs', 'costs' );
	}

	/* ------------------------------------------------------------------ */
	/* CSV downloads                                                       */
	/* ------------------------------------------------------------------ */

	public static function export_url( $type ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=nurax_stock_export&type=' . $type ), 'nurax_stock_export' );
	}

	public static function do_export() {
		NURAX_Desk::guard( 'nurax_stock_export' );
		$type   = 'history' === self::get( 'type' ) ? 'history' : 'stock';
		$places = self::places();
		$out    = array();
		if ( 'stock' === $type ) {
			$cost = self::see_cost();
			$head = array( 'Product', 'Colour', 'SKU', 'On shelf', 'Reserved (plans)', 'Reserved (WhatsApp)', 'Available', 'Status' );
			foreach ( $places as $l ) {
				$head[] = $l;
			}
			if ( $cost ) {
				$head[] = 'Buying price (KES)';
				$head[] = 'Shelf value at cost (KES)';
			}
			$out[] = $head;
			foreach ( self::rows() as $r ) {
				$row = array( $r['product'], $r['colour'], $r['sku'], $r['shelf'], $r['plan'], $r['wa'], $r['avail'], $r['state'] );
				foreach ( array_keys( $places ) as $k ) {
					$row[] = isset( $r['split'][ $k ] ) ? $r['split'][ $k ] : 0;
				}
				if ( $cost ) {
					$row[] = $r['cost'];
					$row[] = '' === $r['cost'] ? '' : round( (float) $r['cost'] * $r['shelf'], 2 );
				}
				$out[] = $row;
			}
		} else {
			$src   = self::sources();
			$rsn   = self::reasons();
			$out[] = array( 'Date', 'Item', 'Change', 'Before', 'After', 'What', 'Reason', 'Note', 'From', 'To', 'Ref', 'By' );
			foreach ( self::history_rows( 0 ) as $r ) {
				$out[] = array( $r->created_at, $r->item_name, $r->delta, $r->qty_before, $r->qty_after, isset( $src[ $r->source ] ) ? $src[ $r->source ] : $r->source, isset( $rsn[ $r->reason ] ) ? $rsn[ $r->reason ][0] : $r->reason, $r->note, $r->place_from, $r->place_to, $r->ref, $r->user_name );
			}
		}
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'export', 'Downloaded stock ' . $type . ' (CSV)' );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nura-stock-' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UTF-8 BOM for Excel.
		foreach ( $out as $row ) {
			echo implode( ',', array_map( array( 'NURAX_Desk', 'csv_cell' ), $row ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		exit;
	}
}
