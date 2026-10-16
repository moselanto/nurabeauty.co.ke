<?php
/**
 * NURA Desk - Sourced sales (NURA Experience 1.70.0).
 *
 * A client wants a wig we do not have. We get it from a supplier (another
 * vendor in town, a wholesaler or an import) and still close the sale at the
 * till. Every sourced line is tracked from the moment it is sold:
 *
 *   Waiting on supplier  ->  Arrived in shop  ->  With the client
 *
 * with our cost, the selling price, the profit, the promised date, the client
 * to notify on WhatsApp, and a running "owed to suppliers" ledger.
 *
 * Storage: {prefix}nurax_sourcing (one row per sourced order line).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Source {

	const DBV     = '1.0.0';
	const DBV_OPT = 'nurax_sourcing_db';
	const PAGE    = 'nurax-sourcing';

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'admin_post_nurax_src_pay', array( __CLASS__, 'h_pay' ) );
		add_action( 'admin_post_nurax_src_pay_all', array( __CLASS__, 'h_pay_all' ) );
		add_action( 'admin_post_nurax_src_status', array( __CLASS__, 'h_status' ) );
		// A sourced wig never came out of our own stock, so never count it off.
		add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'stock_qty' ), 20, 3 );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_sourcing';
	}

	public static function ready() {
		return self::DBV === get_option( self::DBV_OPT );
	}

	public static function maybe_install() {
		if ( self::ready() ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		$c = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			item varchar(190) NOT NULL DEFAULT '',
			qty int(11) NOT NULL DEFAULT 1,
			unit_cost decimal(14,2) NOT NULL DEFAULT 0,
			unit_price decimal(14,2) NOT NULL DEFAULT 0,
			supplier varchar(80) NOT NULL DEFAULT '',
			supplier_phone varchar(20) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL DEFAULT 'waiting',
			eta date NULL DEFAULT NULL,
			arrived_at datetime NULL DEFAULT NULL,
			handed_at datetime NULL DEFAULT NULL,
			paid tinyint(1) NOT NULL DEFAULT 0,
			paid_at datetime NULL DEFAULT NULL,
			paid_ref varchar(40) NOT NULL DEFAULT '',
			cust_name varchar(120) NOT NULL DEFAULT '',
			cust_phone varchar(20) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY item_id (item_id),
			KEY supplier (supplier),
			KEY status (status),
			KEY paid (paid),
			KEY created (created)
		) {$c};" );
		update_option( self::DBV_OPT, self::DBV, false );
	}

	public static function statuses() {
		return array(
			'waiting' => 'Waiting on supplier',
			'arrived' => 'Arrived - tell client',
			'handed'  => 'With the client',
		);
	}

	/** Stock: sourced lines return 0 so WooCommerce leaves our shelf count alone. */
	public static function stock_qty( $qty, $order = null, $item = null ) {
		if ( $item && is_object( $item ) && method_exists( $item, 'get_meta' ) ) {
			$src = $item->get_meta( '_nura_sourced', true );
			if ( is_array( $src ) && ! empty( $src['supplier'] ) ) {
				return 0;
			}
		}
		return $qty;
	}

	/**
	 * Validate the "src" block a till line sends. Returns null when the line is
	 * not sourced or incomplete (it is then sold as a normal line).
	 */
	public static function clean_line( $src, $qty ) {
		if ( ! is_array( $src ) ) {
			return null;
		}
		$supplier = mb_substr( sanitize_text_field( isset( $src['supplier'] ) ? (string) $src['supplier'] : '' ), 0, 80 );
		$cost     = isset( $src['cost'] ) ? max( 0, round( (float) $src['cost'], 2 ) ) : 0;
		if ( '' === $supplier || $cost <= 0 ) {
			return null;
		}
		$status = isset( $src['status'] ) && array_key_exists( $src['status'], self::statuses() ) ? $src['status'] : 'waiting';
		$eta    = isset( $src['eta'] ) ? (string) $src['eta'] : '';
		return array(
			'supplier' => $supplier,
			'phone'    => NURAX_Desk::phone( isset( $src['phone'] ) ? (string) $src['phone'] : '' ),
			'cost'     => $cost,
			'paid'     => ! empty( $src['paid'] ) ? 1 : 0,
			'status'   => $status,
			'eta'      => NURAX_Desk::valid_date( $eta ) ? $eta : '',
			'note'     => mb_substr( sanitize_text_field( isset( $src['note'] ) ? (string) $src['note'] : '' ), 0, 255 ),
			'qty'      => max( 1, (int) $qty ),
		);
	}

	/** After a till sale is saved: copy each sourced line into the ledger. */
	public static function record_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return 0;
		}
		global $wpdb;
		self::maybe_install();
		$n     = 0;
		$notes = array();
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$name  = 'Walk-in customer' === $name ? '' : $name;
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
			$src = $item->get_meta( '_nura_sourced', true );
			if ( ! is_array( $src ) || empty( $src['supplier'] ) ) {
				continue;
			}
			$qty   = max( 1, (int) $src['qty'] );
			$label = preg_replace( '/ x \d+$/', '', (string) $item->get_name() );
			$now   = current_time( 'mysql' );
			$wpdb->replace( self::table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'created'        => $now,
				'order_id'       => $order->get_id(),
				'item_id'        => (int) $item_id,
				'product_id'     => $item instanceof WC_Order_Item_Product ? (int) ( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() ) : 0,
				'item'           => mb_substr( $label, 0, 190 ),
				'qty'            => $qty,
				'unit_cost'      => (float) $src['cost'],
				'unit_price'     => round( (float) $item->get_total() / $qty, 2 ),
				'supplier'       => (string) $src['supplier'],
				'supplier_phone' => (string) $src['phone'],
				'status'         => (string) $src['status'],
				'eta'            => $src['eta'] ? $src['eta'] : null,
				'arrived_at'     => 'waiting' !== $src['status'] ? $now : null,
				'handed_at'      => 'handed' === $src['status'] ? $now : null,
				'paid'           => (int) $src['paid'],
				'paid_at'        => $src['paid'] ? $now : null,
				'cust_name'      => mb_substr( $name, 0, 120 ),
				'cust_phone'     => NURAX_Desk::phone( $order->get_billing_phone() ),
				'note'           => (string) $src['note'],
				'user_id'        => get_current_user_id(),
			) );
			$n++;
			$notes[] = $label . ' sourced from ' . $src['supplier'] . ' at ' . NURAX_Desk::money( $src['cost'] ) . ' each (' . ( $src['paid'] ? 'supplier paid' : 'owed to supplier' ) . ( 'waiting' === $src['status'] && $src['eta'] ? ', due ' . NURAX_Desk::date_label( $src['eta'], 'j M' ) : '' ) . ')';
			if ( class_exists( 'NURAX_Staff' ) ) {
				NURAX_Staff::log( 'sale', 'Sourced ' . $label . ' from ' . $src['supplier'] . ' at ' . NURAX_Desk::money( $src['cost'] ) . ' (' . ( $src['paid'] ? 'paid' : 'owed' ) . ')', array( 'object' => 'order', 'object_id' => $order->get_id(), 'amount' => (float) $src['cost'] * $qty ) );
			}
		}
		if ( $notes ) {
			$order->add_order_note( 'Sourced at NURA Desk: ' . implode( '; ', $notes ) . '.' );
			$order->update_meta_data( '_nura_has_sourced', 1 );
			$order->save();
		}
		return $n;
	}

	/** Known suppliers with their last phone, most used first (till suggestions). */
	public static function suppliers( $limit = 40 ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		$t   = self::table();
		$out = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT supplier, MAX(supplier_phone) phone FROM {$t} GROUP BY supplier ORDER BY COUNT(*) DESC LIMIT %d", $limit ) ) as $r ) { // phpcs:ignore WordPress.DB
			$out[] = array( 'name' => $r->supplier, 'phone' => $r->phone ? '0' . substr( $r->phone, 3 ) : '' );
		}
		return $out;
	}

	/** Totals for the page header. */
	public static function kpis() {
		global $wpdb;
		$out = array( 'owed' => 0, 'owed_n' => 0, 'suppliers' => 0, 'waiting' => 0, 'late' => 0, 'arrived' => 0, 'm_sales' => 0, 'm_cost' => 0, 'm_profit' => 0, 'm_n' => 0 );
		if ( ! self::ready() ) {
			return $out;
		}
		$t     = self::table();
		$today = NURAX_Desk::today();
		$o     = $wpdb->get_row( "SELECT COALESCE(SUM(qty*unit_cost),0) s, COUNT(*) n, COUNT(DISTINCT supplier) d FROM {$t} WHERE paid = 0" ); // phpcs:ignore WordPress.DB
		$s     = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(status='waiting') w, SUM(status='waiting' AND eta IS NOT NULL AND eta < %s) l, SUM(status='arrived') a FROM {$t}", $today ) ); // phpcs:ignore WordPress.DB
		$m     = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(qty*unit_price),0) p, COALESCE(SUM(qty*unit_cost),0) c, COUNT(*) n FROM {$t} WHERE created >= %s", current_time( 'Y-m-01 00:00:00' ) ) ); // phpcs:ignore WordPress.DB
		$out['owed']      = $o ? (float) $o->s : 0;
		$out['owed_n']    = $o ? (int) $o->n : 0;
		$out['suppliers'] = $o ? (int) $o->d : 0;
		$out['waiting']   = $s ? (int) $s->w : 0;
		$out['late']      = $s ? (int) $s->l : 0;
		$out['arrived']   = $s ? (int) $s->a : 0;
		$out['m_sales']   = $m ? (float) $m->p : 0;
		$out['m_cost']    = $m ? (float) $m->c : 0;
		$out['m_profit']  = $out['m_sales'] - $out['m_cost'];
		$out['m_n']       = $m ? (int) $m->n : 0;
		return $out;
	}

	/** Lines that need action (for the Overview / tab badge). */
	public static function attention() {
		$k = self::kpis();
		return (int) $k['late'] + (int) $k['arrived'];
	}

	private static function ref_in() {
		return isset( $_POST['ref'] ) ? mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\- ]/', '', wp_unslash( $_POST['ref'] ) ) ), 0, 40 ) : ''; // phpcs:ignore WordPress.Security
	}

	private static function back( $msg ) {
		$ref = wp_get_referer();
		wp_safe_redirect( $ref ? add_query_arg( 'nd_msg', $msg, $ref ) : NURAX_Desk::url( self::PAGE, array( 'nd_msg' => $msg ) ) );
		exit;
	}

	private static function row( $id ) {
		global $wpdb;
		return $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ) : null; // phpcs:ignore WordPress.DB
	}

	public static function h_pay() {
		NURAX_Desk::guard( 'nurax_src_pay' );
		if ( class_exists( 'NURAX_Staff' ) && ! NURAX_Staff::can( 'mark_paid' ) ) {
			self::back( 'notfound' );
		}
		global $wpdb;
		$row = self::row( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $row ) {
			self::back( 'notfound' );
		}
		$ref = self::ref_in();
		$wpdb->update( self::table(), array( 'paid' => 1, 'paid_at' => current_time( 'mysql' ), 'paid_ref' => $ref ), array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'payment', 'Paid supplier ' . $row->supplier . ' ' . NURAX_Desk::money( $row->qty * $row->unit_cost ) . ' for ' . $row->item . ( $ref ? ' (' . $ref . ')' : '' ), array( 'amount' => (float) $row->qty * (float) $row->unit_cost ) );
		}
		self::back( 'paid' );
	}

	public static function h_pay_all() {
		NURAX_Desk::guard( 'nurax_src_pay_all' );
		if ( class_exists( 'NURAX_Staff' ) && ! NURAX_Staff::can( 'mark_paid' ) ) {
			self::back( 'notfound' );
		}
		global $wpdb;
		$sup = isset( $_POST['supplier'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier'] ) ) : '';
		$ref = self::ref_in();
		if ( '' !== $sup ) {
			$sum = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(qty*unit_cost),0) FROM ' . self::table() . ' WHERE paid = 0 AND supplier = %s', $sup ) ); // phpcs:ignore WordPress.DB
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET paid = 1, paid_at = %s, paid_ref = %s WHERE paid = 0 AND supplier = %s', current_time( 'mysql' ), $ref, $sup ) ); // phpcs:ignore WordPress.DB
			if ( class_exists( 'NURAX_Staff' ) ) {
				NURAX_Staff::log( 'payment', 'Settled supplier ' . $sup . ' - ' . NURAX_Desk::money( $sum ) . ( $ref ? ' (' . $ref . ')' : '' ), array( 'amount' => $sum ) );
			}
		}
		self::back( 'paid' );
	}

	/** Move a line along: waiting -> arrived -> handed (or set a new date). */
	public static function h_status() {
		NURAX_Desk::guard( 'nurax_src_status' );
		global $wpdb;
		$row = self::row( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		$to  = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! $row ) {
			self::back( 'notfound' );
		}
		$now = current_time( 'mysql' );
		$set = array();
		if ( 'eta' === $to ) {
			$eta = isset( $_POST['eta'] ) ? sanitize_text_field( wp_unslash( $_POST['eta'] ) ) : '';
			$set = array( 'eta' => NURAX_Desk::valid_date( $eta ) ? $eta : null );
			$msg = 'New date for ' . $row->item . ': ' . ( $set['eta'] ? NURAX_Desk::date_label( $set['eta'], 'j M Y' ) : 'none' );
		} elseif ( array_key_exists( $to, self::statuses() ) ) {
			$set = array( 'status' => $to );
			if ( 'waiting' !== $to && ! $row->arrived_at ) {
				$set['arrived_at'] = $now;
			}
			if ( 'handed' === $to ) {
				$set['handed_at'] = $now;
			}
			$msg = $row->item . ' (' . $row->cust_name . '): ' . self::statuses()[ $to ];
		} else {
			self::back( 'notfound' );
		}
		$wpdb->update( self::table(), $set, array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB
		$o = wc_get_order( $row->order_id );
		if ( $o ) {
			$o->add_order_note( 'Sourced item - ' . $msg . '.' );
		}
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'sale', 'Sourcing: ' . $msg, array( 'object' => 'order', 'object_id' => (int) $row->order_id ) );
		}
		self::back( 'saved' );
	}

	/** WhatsApp message to the client for a line, matched to where it is. */
	public static function client_wa( $r ) {
		if ( ! $r->cust_phone ) {
			return '';
		}
		$first = $r->cust_name ? strtok( $r->cust_name, ' ' ) : 'there';
		$o     = wc_get_order( $r->order_id );
		$due   = $o && ! $o->is_paid() ? ' The balance due is ' . NURAX_Desk::money( $o->get_total() ) . '.' : '';
		if ( 'arrived' === $r->status ) {
			$t = 'Hi ' . $first . ', great news from NURA Beauty. Your ' . $r->item . ' has arrived and is ready for you.' . $due . ' Let us know when you would like to pick it up or have it delivered.';
		} elseif ( 'handed' === $r->status ) {
			$t = 'Hi ' . $first . ', thank you for shopping with NURA Beauty. We hope you love your ' . $r->item . '. Send us a photo when you wear it.';
		} else {
			$t = 'Hi ' . $first . ', NURA Beauty here. We have ordered your ' . $r->item . ' specially for you' . ( $r->eta ? ' and expect it by ' . NURAX_Desk::date_label( $r->eta, 'l j M' ) : '' ) . '. We will message you the moment it arrives.';
		}
		return NURAX_Desk::wa( $r->cust_phone, $t );
	}

	/** Rows for the Excel / CSV export. */
	public static function export_rows() {
		global $wpdb;
		$rows = array( array( 'Date', 'Sale #', 'Item', 'Qty', 'Client', 'Client phone', 'Status', 'Promised date', 'Arrived', 'With client', 'Our cost', 'Sold at', 'Profit', 'Margin %', 'Supplier', 'Supplier phone', 'Supplier paid', 'Paid on', 'Ref', 'Note' ) );
		if ( ! self::ready() ) {
			return $rows;
		}
		$t = self::table();
		foreach ( (array) $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created DESC LIMIT 5000" ) as $r ) { // phpcs:ignore WordPress.DB
			$o      = wc_get_order( $r->order_id );
			$cost   = (float) $r->unit_cost * (int) $r->qty;
			$price  = (float) $r->unit_price * (int) $r->qty;
			$rows[] = array( $r->created, $o ? $o->get_order_number() : $r->order_id, $r->item, (int) $r->qty, $r->cust_name, NURAX_Desk::phone_label( $r->cust_phone ), self::statuses()[ $r->status ] ?? $r->status, (string) $r->eta, (string) $r->arrived_at, (string) $r->handed_at, $cost, $price, $price - $cost, $price > 0 ? round( ( $price - $cost ) / $price * 100, 1 ) : 0, $r->supplier, NURAX_Desk::phone_label( $r->supplier_phone ), $r->paid ? 'Yes' : 'No', (string) $r->paid_at, $r->paid_ref, $r->note );
		}
		return $rows;
	}

	private static function post_form( $action, $fields, $button, $class = 'nd-btn nd-btn--sm' ) {
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ns-inrow">' . wp_nonce_field( $action, '_wpnonce', true, false ) . '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		foreach ( $fields as $k => $v ) {
			$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $h . $button . '</form>';
	}

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		self::maybe_install();
		global $wpdb;
		$t     = self::table();
		$today = NURAX_Desk::today();
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'track'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sup   = isset( $_GET['supplier'] ) ? sanitize_text_field( wp_unslash( $_GET['supplier'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$k     = self::kpis();
		$money = ! class_exists( 'NURAX_Staff' ) || NURAX_Staff::can( 'see_totals' );
		$act   = NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_POS ), '+ New sourced sale', 'nd-btn--primary' ) . ( $money ? NURAX_Desk::export_btns( 'sourcing', 'Sourcing report' ) : '' );
		NURAX_Desk::header( self::PAGE, 'Sourced sales', $act );

		echo '<div class="ns">';
		echo '<section class="ns-hero"><div><span class="ns-eyebrow">Never lose a sale</span><h2>Sell it first. Source it. Deliver it.</h2><p>When a client wants a wig you do not have, sell it at the till anyway: tap <strong>Source it</strong> on the line, enter the supplier and your cost, and choose whether it is in your hands or still coming. NURA Desk tracks it until the client has it, reminds you who is waiting, and keeps a clean record of what you owe each supplier.</p></div>';
		echo '<ol class="ns-steps"><li><b>1</b><span>Sell at the till</span></li><li><b>2</b><span>Waiting on supplier</span></li><li><b>3</b><span>Arrived - WhatsApp the client</span></li><li><b>4</b><span>With the client</span></li></ol></section>';

		echo '<div class="nd-kpis ns-kpis">';
		NURAX_Desk::kpi( 'Waiting on suppliers', (string) $k['waiting'], $k['late'] ? $k['late'] . ' past the promised date' : 'all on time', $k['late'] ? 'red' : '', NURAX_Desk::url( self::PAGE, array( 'tab' => 'track' ) ) );
		NURAX_Desk::kpi( 'Ready for clients', (string) $k['arrived'], 'arrived - tell the client', $k['arrived'] ? 'gold' : '', NURAX_Desk::url( self::PAGE, array( 'tab' => 'track' ) ) );
		NURAX_Desk::kpi( 'Owed to suppliers', $money ? NURAX_Desk::money( $k['owed'] ) : (string) $k['owed_n'], $k['owed_n'] . ' item(s) from ' . $k['suppliers'] . ' supplier(s)', $k['owed'] > 0 ? 'red' : '', NURAX_Desk::url( self::PAGE, array( 'tab' => 'owed' ) ) );
		if ( $money ) {
			NURAX_Desk::kpi( 'Sourced sales this month', NURAX_Desk::money( $k['m_sales'] ), $k['m_n'] . ' item(s) sold that were not in stock', 'ink' );
			NURAX_Desk::kpi( 'Profit on sourced items', NURAX_Desk::money( $k['m_profit'] ), $k['m_sales'] > 0 ? round( $k['m_profit'] / $k['m_sales'] * 100 ) . '% margin this month' : 'this month', $k['m_profit'] >= 0 ? 'gold' : 'red' );
		}
		echo '</div>';

		$tabs = array( 'track' => 'In progress', 'owed' => 'Owed to suppliers', 'done' => 'Completed', 'all' => 'All' );
		echo '<nav class="nt-tabs ns-tabs">';
		foreach ( $tabs as $key => $label ) {
			echo '<a class="nt-tab' . ( $key === $tab ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		$where = '1=1';
		$args  = array();
		$order = 'created DESC';
		if ( 'track' === $tab ) {
			$where .= " AND status <> 'handed'";
			$order  = "FIELD(status,'arrived','waiting'), (eta IS NULL), eta ASC, created ASC";
		} elseif ( 'owed' === $tab ) {
			$where .= ' AND paid = 0';
		} elseif ( 'done' === $tab ) {
			$where .= " AND status = 'handed'";
		}
		if ( '' !== $sup ) {
			$where .= ' AND supplier = %s';
			$args[] = $sup;
		}
		$sql  = "SELECT * FROM {$t} WHERE {$where} ORDER BY {$order} LIMIT 300";
		$rows = (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ) ); // phpcs:ignore WordPress.DB

		echo '<div class="ns-grid"><div class="ns-main">';
		echo '<section class="nt-card">';
		if ( '' !== $sup ) {
			echo '<p class="ns-filter">Showing <strong>' . esc_html( $sup ) . '</strong> only. <a href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => $tab ) ) ) . '">Show every supplier</a></p>';
		}
		if ( ! $rows ) {
			$empty = array(
				'track' => array( 'Nothing waiting.', 'Every sourced wig is with its client. New sourced sales from the till appear here.' ),
				'owed'  => array( 'All suppliers are paid.', 'Unpaid supplier costs from sourced sales appear here.' ),
				'done'  => array( 'No completed sourced sales yet.', 'Once a client has their sourced wig, it moves here.' ),
				'all'   => array( 'No sourced sales yet.', 'At the till, add the wig (even if it is out of stock), tap Source it and enter the supplier and your cost.' ),
			);
			$e = isset( $empty[ $tab ] ) ? $empty[ $tab ] : $empty['all'];
			echo '<div class="nt-empty"><strong>' . esc_html( $e[0] ) . '</strong><p>' . esc_html( $e[1] ) . '</p></div>';
		} else {
			echo '<div class="nt-table"><table><thead><tr><th>Item &amp; client</th><th>Status</th><th>Supplier</th>' . ( $money ? '<th class="r">Cost</th><th class="r">Sold</th><th class="r">Profit</th>' : '' ) . '<th>Next step</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$cost   = (float) $r->unit_cost * (int) $r->qty;
				$price  = (float) $r->unit_price * (int) $r->qty;
				$profit = $price - $cost;
				$o      = wc_get_order( $r->order_id );
				$late   = 'waiting' === $r->status && $r->eta && $r->eta < $today;
				echo '<tr class="' . ( $late ? 'ns-late' : '' ) . '"><td><strong class="ns-item">' . esc_html( $r->item ) . ( (int) $r->qty > 1 ? ' <em>x' . (int) $r->qty . '</em>' : '' ) . '</strong>';
				echo '<small>' . esc_html( $r->cust_name ? $r->cust_name : 'Walk-in' ) . ( $r->cust_phone ? ' - ' . NURAX_Desk::phone_label( $r->cust_phone ) : '' ) . '</small>';
				echo '<small class="ns-meta">' . esc_html( NURAX_Desk::date_label( substr( $r->created, 0, 10 ), 'j M' ) ) . ( $o ? ' - <a href="' . esc_url( $o->get_edit_order_url() ) . '">Sale #' . esc_html( $o->get_order_number() ) . '</a>' . ( $o->is_paid() ? '' : ' <span class="ns-due">balance due</span>' ) : '' ) . '</small>';
				if ( $r->note ) {
					echo '<small class="ns-note">' . esc_html( $r->note ) . '</small>';
				}
				echo '</td><td><span class="ns-st ns-st--' . esc_attr( $r->status ) . '">' . esc_html( self::statuses()[ $r->status ] ?? $r->status ) . '</span>';
				if ( 'waiting' === $r->status ) {
					echo '<small class="' . ( $late ? 'ns-red' : 'ns-mut' ) . '">' . ( $r->eta ? ( $late ? 'Late - was due ' : 'Due ' ) . esc_html( NURAX_Desk::date_label( $r->eta, 'D j M' ) ) : 'No date set' ) . '</small>';
				} elseif ( 'handed' === $r->status && $r->handed_at ) {
					echo '<small class="ns-mut">' . esc_html( NURAX_Desk::date_label( substr( $r->handed_at, 0, 10 ), 'j M Y' ) ) . '</small>';
				}
				echo '</td><td><a class="ns-sup" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'tab' => $tab, 'supplier' => $r->supplier ) ) ) . '">' . esc_html( $r->supplier ) . '</a>';
				echo '<small>' . ( $r->paid ? '<span class="ns-ok">Paid' . ( $r->paid_ref ? ' - ' . esc_html( $r->paid_ref ) : '' ) . '</span>' : '<span class="ns-red">Owed</span>' ) . '</small></td>';
				if ( $money ) {
					echo '<td class="r nt-num">' . esc_html( NURAX_Desk::money( $cost ) ) . '</td><td class="r nt-num">' . esc_html( NURAX_Desk::money( $price ) ) . '</td>';
					echo '<td class="r nt-num"><strong class="' . ( $profit < 0 ? 'ns-red' : 'ns-ok' ) . '">' . esc_html( NURAX_Desk::money( $profit ) ) . '</strong>' . ( $price > 0 ? '<small class="ns-mut">' . round( $profit / $price * 100 ) . '%</small>' : '' ) . '</td>';
				}
				echo '<td><div class="ns-acts">';
				$wa = self::client_wa( $r );
				if ( 'waiting' === $r->status ) {
					echo self::post_form( 'nurax_src_status', array( 'id' => $r->id, 'to' => 'arrived' ), '<button class="nd-btn nd-btn--sm nd-btn--primary">It has arrived</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo self::post_form( 'nurax_src_status', array( 'id' => $r->id, 'to' => 'eta' ), '<input type="date" name="eta" value="' . esc_attr( (string) $r->eta ) . '" class="ns-date" aria-label="Promised date"><button class="nd-btn nd-btn--sm nd-btn--ghost">Set date</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput
					if ( $r->supplier_phone ) {
						echo '<a class="nd-btn nd-btn--sm nd-btn--ghost" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $r->supplier_phone, 'Hi ' . $r->supplier . ', NURA Beauty here. Checking on the ' . $r->item . ' we ordered on ' . NURAX_Desk::date_label( substr( $r->created, 0, 10 ), 'j M' ) . '. When can we collect it? Thank you.' ) ) . '">Chase supplier</a>';
					}
				} elseif ( 'arrived' === $r->status ) {
					if ( $wa ) {
						echo '<a class="nd-btn nd-btn--sm nd-btn--wa" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">Tell client it is ready</a>';
					}
					echo self::post_form( 'nurax_src_status', array( 'id' => $r->id, 'to' => 'handed' ), '<button class="nd-btn nd-btn--sm nd-btn--primary">Client has it</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput
				} elseif ( $wa ) {
					echo '<a class="nd-btn nd-btn--sm nd-btn--ghost" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">Thank-you message</a>';
				}
				if ( 'waiting' === $r->status && $wa ) {
					echo '<a class="nd-btn nd-btn--sm nd-btn--ghost" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">Update client</a>';
				}
				if ( ! $r->paid && ( ! class_exists( 'NURAX_Staff' ) || NURAX_Staff::can( 'mark_paid' ) ) ) {
					echo self::post_form( 'nurax_src_pay', array( 'id' => $r->id ), '<input type="text" name="ref" placeholder="M-Pesa ref" maxlength="40" class="ns-ref"><button class="nd-btn nd-btn--sm nd-btn--ghost">Supplier paid</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</div></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section></div><aside class="ns-side">';

		// Supplier balances.
		$bal = (array) $wpdb->get_results( "SELECT supplier, MAX(supplier_phone) phone, SUM(qty*unit_cost) owed, COUNT(*) n, MIN(created) oldest FROM {$t} WHERE paid = 0 GROUP BY supplier ORDER BY owed DESC" ); // phpcs:ignore WordPress.DB
		echo '<section class="nt-card nt-card--ink"><h2>Supplier balances</h2>';
		if ( ! $bal ) {
			echo '<p>All suppliers are paid up.</p>';
		} else {
			echo '<ul class="ns-bal">';
			foreach ( $bal as $b ) {
				echo '<li><div class="ns-bal__h"><strong>' . esc_html( $b->supplier ) . '</strong><b>' . esc_html( $money ? NURAX_Desk::money( $b->owed ) : (int) $b->n . ' item(s)' ) . '</b></div><small>' . (int) $b->n . ' item(s), oldest ' . esc_html( NURAX_Desk::date_label( substr( $b->oldest, 0, 10 ), 'j M' ) ) . '</small><div class="ns-bal__a">';
				if ( ! class_exists( 'NURAX_Staff' ) || NURAX_Staff::can( 'mark_paid' ) ) {
					echo self::post_form( 'nurax_src_pay_all', array( 'supplier' => $b->supplier ), '<input type="text" name="ref" placeholder="Ref" maxlength="40" class="ns-ref"><button class="nd-btn nd-btn--sm nd-btn--primary">Settle all</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( $b->phone ) {
					echo '<a class="nd-btn nd-btn--sm nd-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $b->phone, 'Hi ' . $b->supplier . ', NURA Beauty here. Sending ' . NURAX_Desk::money( $b->owed ) . ' for the ' . (int) $b->n . ' item(s) we took from you. Thank you for the partnership.' ) ) . '">WhatsApp</a>';
				}
				echo '</div></li>';
			}
			echo '</ul>';
		}
		echo '</section>';

		// Buying insight: what clients keep asking for.
		$top = (array) $wpdb->get_results( "SELECT item, COUNT(*) n, SUM(qty) q, SUM(qty*(unit_price-unit_cost)) p FROM {$t} WHERE created >= DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY item HAVING n >= 1 ORDER BY n DESC, p DESC LIMIT 6" ); // phpcs:ignore WordPress.DB
		echo '<section class="nt-card"><div class="nt-card__h"><div><h2>Worth stocking</h2><p>Most-sourced items in the last 90 days.</p></div></div>';
		if ( ! $top ) {
			echo '<p class="ns-mut">Once you start sourcing, the wigs clients ask for most will show here, so you know what to buy in bulk.</p>';
		} else {
			echo '<ul class="nt-people">';
			foreach ( $top as $x ) {
				echo '<li><span class="nt-av">' . (int) $x->q . '</span><div>' . esc_html( $x->item ) . '<small>sourced ' . (int) $x->n . ' time(s)' . ( $money ? ' - ' . esc_html( NURAX_Desk::money( $x->p ) ) . ' profit' : '' ) . '</small></div>' . ( (int) $x->n >= 3 ? '<em class="is-own">Stock it</em>' : '' ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';

		$sups = (array) $wpdb->get_results( "SELECT supplier, COUNT(*) n, AVG(CASE WHEN arrived_at IS NOT NULL THEN DATEDIFF(arrived_at, created) END) d FROM {$t} GROUP BY supplier ORDER BY n DESC LIMIT 6" ); // phpcs:ignore WordPress.DB
		if ( $sups ) {
			echo '<section class="nt-card"><div class="nt-card__h"><div><h2>Supplier speed</h2><p>Average days from sale to arrival.</p></div></div><ul class="nt-people">';
			foreach ( $sups as $s ) {
				$d = null === $s->d ? '-' : ( (float) $s->d < 1 ? 'Same day' : round( (float) $s->d, 1 ) . ' days' );
				echo '<li><span class="nt-av">' . esc_html( mb_strtoupper( mb_substr( $s->supplier, 0, 1 ) ) ) . '</span><div>' . esc_html( $s->supplier ) . '<small>' . (int) $s->n . ' item(s)</small></div><em>' . esc_html( $d ) . '</em></li>';
			}
			echo '</ul></section>';
		}
		echo '</aside></div></div>';
		NURAX_Desk::footer();
	}
}
