<?php
/**
 * NURA Desk - WhatsApp orders (NURA Experience 1.58.0).
 *
 * Brings the "Order on WhatsApp" pop-up leads (stored by NURAX_WA_Order as
 * private nurax_wa_order posts) into NURA Desk next to payment plans and
 * appointments: list, detail page, status, WhatsApp replies, and one-click
 * hand-over to a payment plan or an appointment.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Orders {

	const CPT    = 'nurax_wa_order';
	const META   = '_nwo';
	const STATUS = '_nwo_status';
	const LOG    = '_nwo_log';

	public static function hooks() {
		add_action( 'admin_post_nurax_desk_order_status', array( __CLASS__, 'h_status' ) );
	}

	public static function statuses() {
		return array(
			'new'       => 'New',
			'confirmed' => 'Confirmed',
			'delivered' => 'Delivered',
			'cancelled' => 'Cancelled',
		);
	}

	public static function status_label( $k ) {
		$s = self::statuses();
		return isset( $s[ $k ] ) ? $s[ $k ] : (string) $k;
	}

	/** Badge colour key per status (re-uses the Desk badge palette). */
	private static function tone( $k ) {
		$m = array( 'new' => 'request', 'confirmed' => 'confirmed', 'delivered' => 'completed', 'cancelled' => 'cancelled' );
		return isset( $m[ $k ] ) ? $m[ $k ] : 'muted';
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	public static function data( $post_id ) {
		$d = get_post_meta( $post_id, self::META, true );
		$d = is_array( $d ) ? $d : array();
		return array_merge( array(
			'ref' => '', 'name' => '', 'phone' => '', 'items' => array(), 'total' => 0, 'fulfil' => 'delivery',
			'delivery' => '', 'county' => '', 'town' => '', 'payment' => '', 'note' => '', 'source' => '', 'page' => '', 'ship' => '',
		), $d );
	}

	public static function status( $post_id ) {
		$s = (string) get_post_meta( $post_id, self::STATUS, true );
		return array_key_exists( $s, self::statuses() ) ? $s : 'new';
	}

	private static function query( $status, $search, $paged, $per ) {
		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => $per,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$mq = array();
		if ( 'all' !== $status ) {
			$mq[] = array( 'key' => self::STATUS, 'value' => $status );
		}
		if ( '' !== $search ) {
			$ph   = NURAX_Desk::phone( $search );
			$mq[] = array(
				'relation' => 'OR',
				array( 'key' => self::META, 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => self::META, 'value' => $ph ? $ph : $search, 'compare' => 'LIKE' ),
			);
		}
		if ( $mq ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $mq ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return new WP_Query( $args );
	}

	private static function count( $status ) {
		$q = self::query( $status, '', 1, 1 );
		return (int) $q->found_posts;
	}

	public static function kpis() {
		global $wpdb;
		$k = array( 'new' => self::count( 'new' ), 'week' => 0, 'week_value' => 0, 'month_delivered' => 0 );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' -7 days' ) );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'private' AND post_date >= %s", self::CPT, $since ) );
		foreach ( (array) $ids as $id ) {
			if ( 'cancelled' === self::status( $id ) ) {
				continue;
			}
			$d = self::data( $id );
			$k['week']++;
			$k['week_value'] += (float) $d['total'];
		}
		$from = current_time( 'Y-m-01 00:00:00' );
		$q    = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => $from, 'inclusive' => true ) ),
			'meta_query'     => array( array( 'key' => self::STATUS, 'value' => 'delivered' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( (array) $q->posts as $id ) {
			$d = self::data( $id );
			$k['month_delivered'] += (float) $d['total'] + (float) $d['ship'];
		}
		return $k;
	}

	private static function items_line( $d ) {
		$out = array();
		foreach ( (array) $d['items'] as $it ) {
			$out[] = ( isset( $it['qty'] ) && (int) $it['qty'] > 1 ? (int) $it['qty'] . ' x ' : '' ) . ( isset( $it['name'] ) ? $it['name'] : '' ) . ( ! empty( $it['option'] ) ? ' (' . $it['option'] . ')' : '' );
		}
		return implode( ', ', $out );
	}

	private static function grand( $d ) {
		return (float) $d['total'] + ( '' !== (string) $d['ship'] ? (float) $d['ship'] : 0 );
	}

	public static function confirm_text( $d ) {
		$first = trim( strtok( (string) $d['name'], ' ' ) );
		$l     = array( 'Hi ' . ( $first ? $first : 'there' ) . ', thank you for your order with NURA Beauty.', '', '*Order ' . $d['ref'] . '*' );
		foreach ( (array) $d['items'] as $it ) {
			$l[] = '- ' . ( isset( $it['name'] ) ? $it['name'] : '' ) . ( ! empty( $it['option'] ) ? ' (' . $it['option'] . ')' : '' ) . ' x ' . ( isset( $it['qty'] ) ? (int) $it['qty'] : 1 ) . ' - ' . NURAX_Desk::money( isset( $it['price'] ) ? (float) $it['price'] * (int) $it['qty'] : 0 );
		}
		$l[] = 'Subtotal: ' . NURAX_Desk::money( $d['total'] );
		if ( 'pickup' === $d['fulfil'] ) {
			$l[] = 'Pick-up at our studio: ' . NURAX_Desk::shop_address();
		} else {
			$l[] = 'Delivery to ' . $d['delivery'] . ': ' . ( '' !== (string) $d['ship'] ? ( (float) $d['ship'] > 0 ? NURAX_Desk::money( $d['ship'] ) : 'Free' ) : 'KSh ____' );
		}
		$l[] = '*Total: ' . ( '' !== (string) $d['ship'] || 'pickup' === $d['fulfil'] ? NURAX_Desk::money( self::grand( $d ) ) : NURAX_Desk::money( $d['total'] ) . ' + delivery' ) . '*';
		$l[] = '';
		$l[] = 'Your order is *confirmed* and in stock. ' . ( $d['payment'] ? 'Payment: ' . $d['payment'] . '.' : '' );
		$l[] = 'We will let you know as soon as it is on the way.';
		return implode( "\n", $l );
	}

	public static function add_log( $post_id, $msg ) {
		self::log( $post_id, $msg );
	}

	public static function is_paid( $post_id ) {
		return 'paid' === get_post_meta( $post_id, '_nwo_pay', true );
	}

	public static function set_paid( $post_id, $paid, $ref = '' ) {
		if ( $paid ) {
			update_post_meta( $post_id, '_nwo_pay', 'paid' );
			update_post_meta( $post_id, '_nwo_pay_ref', mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\- ]/', '', (string) $ref ) ), 0, 40 ) );
			update_post_meta( $post_id, '_nwo_paid_at', current_time( 'mysql' ) );
			self::log( $post_id, 'Marked as paid' . ( $ref ? ' (' . $ref . ')' : '' ) . '.' );
		} else {
			delete_post_meta( $post_id, '_nwo_pay' );
			delete_post_meta( $post_id, '_nwo_pay_ref' );
			delete_post_meta( $post_id, '_nwo_paid_at' );
			self::log( $post_id, 'Marked as unpaid.' );
		}
	}

	private static function log( $post_id, $msg ) {
		$l = get_post_meta( $post_id, self::LOG, true );
		$l = is_array( $l ) ? $l : array();
		$u = wp_get_current_user();
		$l[] = array( 't' => current_time( 'mysql' ), 'u' => ( $u && $u->ID ) ? $u->display_name : 'System', 'm' => $msg );
		update_post_meta( $post_id, self::LOG, array_slice( $l, -100 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id && self::CPT === get_post_type( $id ) ) {
			self::view( $id );
		} else {
			self::listing();
		}
	}

	private static function listing() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'new';
		$q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$tabs = array_merge( self::statuses(), array( 'all' => 'All' ) );
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'new';
		}
		NURAX_Desk::header( NURAX_Desk::P_ORDERS, 'WhatsApp orders', NURAX_Desk::btn( NURAX_Desk::export_url( 'orders' ), 'Export CSV', 'nd-btn--ghost' ) );
		$k = self::kpis();
		echo '<div class="nd-kpis nd-kpis--4">';
		NURAX_Desk::kpi( 'New orders', (string) $k['new'], 'waiting for a reply', $k['new'] ? 'amber' : '' );
		NURAX_Desk::kpi( 'Last 7 days', (string) $k['week'], 'orders received', 'ink' );
		NURAX_Desk::kpi( 'Value last 7 days', NURAX_Desk::money( $k['week_value'] ), 'before delivery fees' );
		NURAX_Desk::kpi( 'Delivered this month', NURAX_Desk::money( $k['month_delivered'] ), 'including delivery', 'gold' );
		echo '</div>';
		echo '<div class="nd-toolbar"><nav class="nd-pills">';
		foreach ( $tabs as $kk => $lbl ) {
			$c = self::count( $kk );
			echo '<a class="nd-pill' . ( $kk === $tab ? ' is-on' : '' ) . ( 'new' === $kk && $c ? ' nd-pill--alert' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'tab' => $kk, 's' => $q ) ) ) . '">' . esc_html( $lbl ) . ' <span>' . (int) $c . '</span></a>';
		}
		echo '</nav><form method="get" class="nd-search"><input type="hidden" name="page" value="' . esc_attr( NURAX_Desk::P_ORDERS ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="search" name="s" value="' . esc_attr( $q ) . '" placeholder="Search name, phone, ref or wig"><button class="nd-btn nd-btn--ghost" type="submit">Search</button></form></div>';

		$per = 25;
		$res = self::query( $tab, $q, $paged, $per );
		if ( ! $res->posts ) {
			echo '<div class="nd-empty"><strong>No orders here.</strong><p>Every order sent from the "Order on WhatsApp" pop-up lands here, even if the customer never pressed send in WhatsApp.</p></div>';
			NURAX_Desk::footer();
			return;
		}
		echo '<div class="nd-table-wrap"><table class="nd-table" data-nd-type="order"><thead><tr><th class="nd-col-chk"><input type="checkbox" data-nd-all aria-label="Select all"></th><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Delivery</th><th>Payment</th><th>Status</th><th class="nd-col-act"></th></tr></thead><tbody>';
		foreach ( $res->posts as $post ) {
			$d  = self::data( $post->ID );
			$st = self::status( $post->ID );
			$v  = NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'order' => $post->ID ) );
			echo '<tr class="is-' . esc_attr( 'new' === $st ? 'request' : $st ) . '" data-nd-key="order:' . (int) $post->ID . '" data-nd-search="' . esc_attr( strtolower( $d['ref'] . ' ' . $d['name'] . ' ' . $d['phone'] . ' ' . self::items_line( $d ) ) ) . '">';
			echo '<td class="nd-col-chk"><input type="checkbox" class="nd-check" value="order:' . (int) $post->ID . '" aria-label="Select"></td>';
			echo '<td><a class="nd-ref" href="' . esc_url( $v ) . '">' . esc_html( $d['ref'] ? $d['ref'] : '#' . $post->ID ) . '</a><small>' . esc_html( get_the_date( 'j M, H:i', $post ) ) . '</small></td>';
			echo '<td><strong>' . esc_html( $d['name'] ) . '</strong><small>' . esc_html( NURAX_Desk::phone_label( $d['phone'] ) ) . '</small></td>';
			echo '<td><span class="nd-clip">' . esc_html( self::items_line( $d ) ) . '</span></td>';
			echo '<td><strong>' . esc_html( NURAX_Desk::money( self::grand( $d ) ) ) . '</strong><small>' . esc_html( 'pickup' === $d['fulfil'] ? 'pick-up, no delivery fee' : ( '' !== (string) $d['ship'] ? ( (float) $d['ship'] > 0 ? 'incl. delivery ' . NURAX_Desk::money( $d['ship'] ) : 'free delivery' ) : 'delivery not quoted' ) ) . '</small></td>';
			echo '<td>' . esc_html( 'pickup' === $d['fulfil'] ? 'Pick-up' : ( $d['town'] ? $d['town'] . ', ' . $d['county'] : $d['delivery'] ) ) . '</td>';
			echo '<td>' . ( self::is_paid( $post->ID ) ? NURAX_Desk::badge( 'confirmed', 'Paid' ) : NURAX_Desk::badge( 'muted', 'Unpaid' ) ) . '<small>' . esc_html( $d['payment'] ? $d['payment'] : '-' ) . '</small></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . NURAX_Desk::badge( self::tone( $st ), self::status_label( $st ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="nd-col-act">';
			self::quick( $post->ID, $d, $st );
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		NURAX_Desk_Plans::pager( (int) $res->found_posts, $per, $paged, array( 'tab' => $tab, 's' => $q ), NURAX_Desk::P_ORDERS );
		NURAX_Desk::footer();
	}

	private static function quick( $id, $d, $st ) {
		if ( 'new' === $st ) {
			self::status_btn( $id, 'confirmed', 'Confirm', 'nd-ico-btn nd-ico-btn--ok' );
		}
		if ( in_array( $st, array( 'new', 'confirmed' ), true ) && $d['phone'] ) {
			echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $d['phone'], self::confirm_text( $d ) ) ) . '">WhatsApp</a>';
		}
		if ( 'confirmed' === $st ) {
			if ( ! self::is_paid( $id ) ) {
				echo '<button type="button" class="nd-ico-btn nd-ico-btn--ok" data-nd-paid="order:' . (int) $id . '">Paid</button>';
				if ( $d['phone'] ) {
					echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $d['phone'], NURAX_Desk_Remind::order_text( $id, $d, 'pay' ) ) ) . '" data-nd-remind="order:' . (int) $id . '">Remind</a>';
				}
			}
			self::status_btn( $id, 'delivered', 'Delivered', 'nd-ico-btn nd-ico-btn--ok' );
		}
		echo '<button type="button" class="nd-ico-btn nd-ico-btn--danger" data-nd-del="order:' . (int) $id . '">Delete</button>';
	}

	private static function status_btn( $id, $to, $label, $cls, $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inline"' . ( $confirm ? ' data-nd-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '><input type="hidden" name="action" value="nurax_desk_order_status"><input type="hidden" name="order_id" value="' . (int) $id . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
		wp_nonce_field( 'nurax_desk_order_status' );
		echo '<button type="submit" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function view( $id ) {
		$d    = self::data( $id );
		$st   = self::status( $id );
		$post = get_post( $id );
		$act  = NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_ORDERS ), 'All orders', 'nd-btn--ghost' );
		if ( $d['phone'] && in_array( $st, array( 'new', 'confirmed' ), true ) ) {
			$act .= NURAX_Desk::btn( NURAX_Desk::wa( $d['phone'], self::confirm_text( $d ) ), 'Reply on WhatsApp', 'nd-btn--wa', 'target="_blank" rel="noopener"' );
		}
		$act .= '<button type="button" class="nd-btn nd-btn--ghost" data-nd-print>Print</button>';
		NURAX_Desk::header( NURAX_Desk::P_ORDERS, ( $d['ref'] ? $d['ref'] : 'Order #' . $id ) . ' - ' . $d['name'], $act );
		echo '<div class="nd-plan"><div class="nd-plan__main">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Items</h2>' . NURAX_Desk::badge( self::tone( $st ), self::status_label( $st ) ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<table class="nd-table nd-table--flat"><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Line</th></tr></thead><tbody>';
		$first_item = null;
		foreach ( (array) $d['items'] as $it ) {
			$first_item = $first_item ? $first_item : $it;
			$pid  = isset( $it['id'] ) ? absint( $it['id'] ) : 0;
			$qty  = isset( $it['qty'] ) ? (int) $it['qty'] : 1;
			$pr   = isset( $it['price'] ) ? (float) $it['price'] : 0;
			$name = isset( $it['name'] ) ? $it['name'] : '';
			$link = $pid ? get_edit_post_link( wp_get_post_parent_id( $pid ) ? wp_get_post_parent_id( $pid ) : $pid ) : '';
			echo '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '"><strong>' . esc_html( $name ) . '</strong></a>' : '<strong>' . esc_html( $name ) . '</strong>' ) . ( ! empty( $it['option'] ) ? '<small>' . esc_html( $it['option'] ) . '</small>' : '' ) . '</td><td>' . (int) $qty . '</td><td>' . esc_html( NURAX_Desk::money( $pr ) ) . '</td><td><strong>' . esc_html( NURAX_Desk::money( $pr * $qty ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table>';
		echo '<dl class="nd-stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">';
		echo '<div><dt>Subtotal</dt><dd>' . esc_html( NURAX_Desk::money( $d['total'] ) ) . '</dd></div>';
		echo '<div><dt>Delivery</dt><dd>' . esc_html( 'pickup' === $d['fulfil'] ? 'Free (pick-up)' : ( '' !== (string) $d['ship'] ? ( (float) $d['ship'] > 0 ? NURAX_Desk::money( $d['ship'] ) : 'Free' ) : 'Not quoted' ) ) . '</dd></div>';
		echo '<div><dt>Total</dt><dd>' . esc_html( NURAX_Desk::money( self::grand( $d ) ) ) . '</dd></div>';
		echo '</dl></section>';
		if ( $d['note'] ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Customer note</h2></header><p class="nd-note">' . nl2br( esc_html( $d['note'] ) ) . '</p></section>';
		}
		self::customer_activity( $d['phone'], $id );
		echo '</div><aside class="nd-plan__side">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Customer</h2></header><dl class="nd-dl">';
		echo '<div><dt>Name</dt><dd>' . esc_html( $d['name'] ) . '</dd></div>';
		echo '<div><dt>Phone</dt><dd>' . ( $d['phone'] ? '<a href="' . esc_url( 'https://wa.me/' . $d['phone'] ) . '" target="_blank" rel="noopener">' . esc_html( NURAX_Desk::phone_label( $d['phone'] ) ) . '</a>' : '-' ) . '</dd></div>';
		echo '<div><dt>' . ( 'pickup' === $d['fulfil'] ? 'Pick-up' : 'Deliver to' ) . '</dt><dd>' . esc_html( $d['delivery'] ) . '</dd></div>';
		echo '<div><dt>Payment</dt><dd>' . esc_html( $d['payment'] ? $d['payment'] : '-' ) . '</dd></div>';
		echo '<div><dt>Received</dt><dd>' . esc_html( get_the_date( 'D j M Y, H:i', $post ) ) . '</dd></div>';
		echo '<div><dt>From</dt><dd>' . esc_html( ucfirst( $d['source'] ? $d['source'] : 'website' ) ) . '</dd></div>';
		echo '</dl></section>';

		echo '<section class="nd-card nd-noprint"><header class="nd-card__h"><h2>Status</h2></header><div class="nd-stack">';
		$flow = array(
			'new'       => array( 'confirmed' => array( 'Confirm order', 'nd-btn--primary', '' ), 'cancelled' => array( 'Cancel order', 'nd-btn--danger-ghost', 'Cancel this order?' ) ),
			'confirmed' => array( 'delivered' => array( 'Mark delivered / collected', 'nd-btn--primary', '' ), 'cancelled' => array( 'Cancel order', 'nd-btn--danger-ghost', 'Cancel this order?' ) ),
			'delivered' => array( 'confirmed' => array( 'Undo - back to confirmed', 'nd-btn--ghost', '' ) ),
			'cancelled' => array( 'new' => array( 'Restore order', 'nd-btn--ghost', '' ) ),
		);
		foreach ( isset( $flow[ $st ] ) ? $flow[ $st ] : array() as $to => $o ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $o[2] ? ' data-nd-confirm="' . esc_attr( $o[2] ) . '"' : '' ) . '><input type="hidden" name="action" value="nurax_desk_order_status"><input type="hidden" name="order_id" value="' . (int) $id . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
			wp_nonce_field( 'nurax_desk_order_status' );
			echo '<button type="submit" class="nd-btn nd-btn--block ' . esc_attr( $o[1] ) . '">' . esc_html( $o[0] ) . '</button></form>';
		}
		NURAX_Desk_Remind::delete_form( 'order:' . (int) $id, 'Delete order' );
		echo '</div></section>';

		// Payment.
		$paid = self::is_paid( $id );
		echo '<section class="nd-card nd-noprint' . ( $paid ? '' : ' nd-card--accent' ) . '"><header class="nd-card__h"><h2>Payment</h2>' . ( $paid ? NURAX_Desk::badge( 'confirmed', 'Paid' ) : NURAX_Desk::badge( 'request', 'Not paid' ) ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form nd-form--tight"><input type="hidden" name="action" value="nurax_desk_order_pay"><input type="hidden" name="order_id" value="' . (int) $id . '"><input type="hidden" name="paid" value="' . ( $paid ? '0' : '1' ) . '">';
		wp_nonce_field( 'nurax_desk_order_pay' );
		if ( $paid ) {
			$pr = (string) get_post_meta( $id, '_nwo_pay_ref', true );
			echo '<p>' . esc_html( 'Paid on ' . NURAX_Desk::date_label( get_post_meta( $id, '_nwo_paid_at', true ), 'D j M Y' ) . ( $pr ? ' - ' . $pr : '' ) ) . '</p><button type="submit" class="nd-btn nd-btn--ghost nd-btn--block">Mark as unpaid</button>';
		} else {
			echo '<label class="nd-field"><span class="nd-label">M-Pesa code (optional)</span><input type="text" name="ref" maxlength="40" placeholder="e.g. SJK4X2PQ7Z" style="text-transform:uppercase"></label><button type="submit" class="nd-btn nd-btn--primary nd-btn--block">Mark as paid</button>';
		}
		echo '</form>';
		if ( ! $paid && $d['phone'] && 'cancelled' !== $st ) {
			$kind = 'confirmed' === $st ? 'pay' : 'follow';
			echo '<div style="margin-top:8px">' . NURAX_Desk::btn( NURAX_Desk::wa( $d['phone'], NURAX_Desk_Remind::order_text( $id, $d, $kind ) ), 'pay' === $kind ? 'Send payment reminder' : 'Send follow-up', 'nd-btn--wa nd-btn--block', 'target="_blank" rel="noopener" data-nd-remind="order:' . (int) $id . '"' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$rl = get_post_meta( $id, '_nwo_reminded', true );
		if ( $rl ) {
			echo '<p class="nd-mut nd-small">' . esc_html( NURAX_Desk_Remind::last_label( $rl, (int) get_post_meta( $id, '_nwo_reminders', true ) ) ) . '</p>';
		}
		echo '</section>';

		// Hand-over to the other Desk modules.
		echo '<section class="nd-card nd-noprint"><header class="nd-card__h"><h2>Next step</h2></header><div class="nd-stack">';
		$pf = array( 'action' => 'new', 'pf_name' => $d['name'], 'pf_phone' => $d['phone'], 'pf_order' => $id );
		if ( $first_item ) {
			$pf['pf_pid']   = isset( $first_item['id'] ) ? absint( $first_item['id'] ) : 0;
			$pf['pf_price'] = isset( $first_item['price'] ) ? (float) $first_item['price'] : 0;
			$pf['pf_name2'] = isset( $first_item['name'] ) ? $first_item['name'] : '';
			$pf['pf_opts']  = isset( $first_item['option'] ) ? $first_item['option'] : '';
			$pf['pf_fulfil'] = 'pickup' === $d['fulfil'] ? 'Pick up at the studio' : $d['delivery'];
		}
		echo NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_PLANS, $pf ), 'Turn into a Lipa Mdogo Mdogo plan', 'nd-btn--ghost nd-btn--block' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'action' => 'new', 'pf_name' => $d['name'], 'pf_phone' => $d['phone'], 'pf_service' => 'pickup' === $d['fulfil'] ? 'collection' : 'fitting', 'pf_notes' => 'From WhatsApp order ' . $d['ref'] . ': ' . self::items_line( $d ) ) ), 'Book a fitting / collection', 'nd-btn--ghost nd-btn--block' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></section>';

		$log = get_post_meta( $id, self::LOG, true );
		if ( is_array( $log ) && $log ) {
			echo '<section class="nd-card nd-noprint"><header class="nd-card__h"><h2>Activity</h2></header><ol class="nd-log">';
			foreach ( array_reverse( $log ) as $e ) {
				echo '<li><span>' . esc_html( $e['m'] ) . '</span><small>' . esc_html( NURAX_Desk::date_label( $e['t'], 'j M, H:i' ) . ' - ' . $e['u'] ) . '</small></li>';
			}
			echo '</ol></section>';
		}
		echo '</aside></div>';
		NURAX_Desk::footer();
	}

	/** Everything else this customer has done with NURA (by phone). */
	public static function customer_activity( $phone, $exclude_order = 0 ) {
		global $wpdb;
		if ( '' === (string) $phone ) {
			return;
		}
		$items = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_plans() . ' WHERE phone = %s ORDER BY id DESC LIMIT 10', $phone ) ) as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$items[] = array( $p->created_at, 'Payment plan', $p->ref . ' - ' . $p->product_name, NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) ), $p->status );
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_appts() . ' WHERE phone = %s ORDER BY appt_date DESC LIMIT 10', $phone ) ) as $a ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$items[] = array( $a->appt_date . ' ' . $a->appt_time . ':00', 'Appointment', NURAX_Desk_Appts::service_label( $a->service ) . ' - ' . NURAX_Desk::date_label( $a->appt_date, 'D j M' ), NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $a->id ) ), $a->status );
		}
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 10,
			'post__not_in'   => $exclude_order ? array( (int) $exclude_order ) : array(),
			'meta_query'     => array( array( 'key' => self::META, 'value' => '"' . $phone . '"', 'compare' => 'LIKE' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( $q->posts as $o ) {
			$d       = self::data( $o->ID );
			$items[] = array( $o->post_date, 'WhatsApp order', ( $d['ref'] ? $d['ref'] : '#' . $o->ID ) . ' - ' . NURAX_Desk::money( $d['total'] ), NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'order' => $o->ID ) ), self::status( $o->ID ) );
		}
		if ( ! $items ) {
			return;
		}
		usort( $items, function ( $a, $b ) {
			return strcmp( $b[0], $a[0] );
		} );
		echo '<section class="nd-card"><header class="nd-card__h"><h2>This customer with NURA</h2><span class="nd-mut">' . count( $items ) . ' records</span></header><ul class="nd-list">';
		foreach ( $items as $it ) {
			echo '<li><a class="nd-list__main" href="' . esc_url( $it[3] ) . '"><strong>' . esc_html( $it[1] ) . '</strong><small>' . esc_html( $it[2] ) . '</small></a><span class="nd-list__meta"><small>' . esc_html( NURAX_Desk::date_label( $it[0], 'j M Y' ) . ' - ' . ucfirst( str_replace( '_', ' ', $it[4] ) ) ) . '</small></span></li>';
		}
		echo '</ul></section>';
	}

	public static function h_status() {
		NURAX_Desk::guard( 'nurax_desk_order_status' );
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$to = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! $id || self::CPT !== get_post_type( $id ) || ! array_key_exists( $to, self::statuses() ) ) {
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		update_post_meta( $id, self::STATUS, $to );
		self::log( $id, 'Status changed to ' . self::status_label( $to ) . '.' );
		NURAX_Desk::bust();
		$ref  = wp_get_referer();
		$dest = ( $ref && false === strpos( $ref, 'order=' ) ) ? remove_query_arg( 'nd_msg', $ref ) : NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'order' => $id ) );
		wp_safe_redirect( add_query_arg( 'nd_msg', 'status', $dest ) );
		exit;
	}

	/** Overview: newest orders waiting for a reply. */
	public static function request_list( $limit ) {
		$res = self::query( 'new', '', 1, $limit );
		if ( ! $res->posts ) {
			return;
		}
		echo '<h3 class="nd-sub">WhatsApp orders to confirm</h3><ul class="nd-list">';
		foreach ( $res->posts as $post ) {
			$d = self::data( $post->ID );
			echo '<li><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'order' => $post->ID ) ) ) . '"><strong>' . esc_html( $d['name'] ) . '</strong><small>' . esc_html( self::items_line( $d ) . ' - ' . NURAX_Desk::money( self::grand( $d ) ) ) . '</small></a><span class="nd-list__acts">';
			self::quick( $post->ID, $d, 'new' );
			echo '</span></li>';
		}
		echo '</ul>';
	}

	/** CSV rows. */
	public static function export_rows() {
		$rows = array( array( 'Ref', 'Received', 'Status', 'Customer', 'Phone', 'Items', 'Subtotal', 'Delivery fee', 'Total', 'Fulfilment', 'Deliver to', 'Payment', 'Note' ) );
		$q    = new WP_Query( array( 'post_type' => self::CPT, 'post_status' => 'private', 'posts_per_page' => 5000, 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( $q->posts as $o ) {
			$d      = self::data( $o->ID );
			$rows[] = array( $d['ref'], $o->post_date, self::status_label( self::status( $o->ID ) ), $d['name'], $d['phone'], self::items_line( $d ), $d['total'], $d['ship'], self::grand( $d ), $d['fulfil'], $d['delivery'], $d['payment'], $d['note'] );
		}
		return $rows;
	}
}
