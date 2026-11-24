<?php
/**
 * NURA order source (NURA Experience 1.127.0).
 *
 * Records where every order came from so the shop can see total orders per
 * day by source, including sales that never touch the website.
 *
 * - "Order source" box on the wp-admin order screen. Required when staff
 *   enter an order by hand (WhatsApp chat, studio walk-in, phone, DMs,
 *   referral with the referrer's code or name). Website orders are filled in
 *   automatically from WooCommerce order attribution (UTM tags, search, AI,
 *   direct) and can be corrected by staff.
 * - "Source" column on the WooCommerce orders page (HPOS and legacy).
 * - WooCommerce > Orders by source: orders and revenue per day by source for
 *   7 / 30 / 90 days, totals by source and top referrers.
 * - Short bio links: /ig, /tt, /fb and /wa send a 302 to the home page with
 *   the UTM tags from the growth plan, so bio clicks show their channel.
 *
 * Checkout is not touched. Storage: order meta _nura_order_source (key) and
 * _nura_order_referrer (text). A future referral module may set
 * _nura_ref_code, which is reported as Referral.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Order_Source {

	const META     = '_nura_order_source';
	const META_REF = '_nura_order_referrer';
	const PAGE     = 'nurax-order-sources';

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'short_links' ), 1 );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ), 30 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), 20, 1 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'columns' ), 30 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'columns' ), 30 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'column_legacy' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu' ), 61 );
	}

	/* ---- Short bio links ---- */

	public static function short_map() {
		return (array) apply_filters(
			'nurax_short_links',
			array(
				'ig' => array( 'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'bio' ),
				'tt' => array( 'utm_source' => 'tiktok', 'utm_medium' => 'social', 'utm_campaign' => 'bio' ),
				'fb' => array( 'utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => 'page' ),
				'wa' => array( 'utm_source' => 'whatsapp', 'utm_medium' => 'social', 'utm_campaign' => 'profile' ),
			)
		);
	}

	public static function short_links() {
		if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$key  = strtolower( trim( $path, '/' ) );
		$map  = self::short_map();
		if ( '' === $key || ! isset( $map[ $key ] ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		wp_safe_redirect( add_query_arg( $map[ $key ], home_url( '/' ) ), 302, 'NURA' );
		exit;
	}

	/* ---- Sources ---- */

	public static function options() {
		return array(
			'whatsapp'     => 'WhatsApp chat',
			'studio'       => 'Studio walk-in',
			'phone'        => 'Phone call',
			'instagram_dm' => 'Instagram DM',
			'tiktok_dm'    => 'TikTok DM',
			'facebook_msg' => 'Facebook message',
			'referral'     => 'Referral (a customer recommended us)',
			'website'      => 'Website',
			'other'        => 'Other',
		);
	}

	private static function option_channel( $key ) {
		$map = array(
			'whatsapp'     => 'WhatsApp',
			'studio'       => 'Studio',
			'phone'        => 'Phone',
			'instagram_dm' => 'Instagram',
			'tiktok_dm'    => 'TikTok',
			'facebook_msg' => 'Facebook',
			'referral'     => 'Referral',
			'website'      => 'Website',
			'other'        => 'Other',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : 'Other';
	}

	public static function is_hand_entered( $order ) {
		$type = (string) $order->get_meta( '_wc_order_attribution_source_type' );
		$via  = (string) $order->get_created_via();
		return 'admin' === $type || 'admin' === $via || '' === $via;
	}

	/**
	 * Channel used in the column and the report.
	 *
	 * @param WC_Order $order Order.
	 * @return array { channel, detail }
	 */
	public static function source( $order ) {
		$key = (string) $order->get_meta( self::META );
		if ( $key ) {
			$ref    = (string) $order->get_meta( self::META_REF );
			$opts   = self::options();
			$detail = isset( $opts[ $key ] ) ? $opts[ $key ] : $key;
			if ( 'referral' === $key ) {
				$detail = $ref ? 'Referral: ' . $ref : 'Referral';
			}
			return array( 'channel' => self::option_channel( $key ), 'detail' => $detail, 'manual' => true );
		}
		$code = (string) $order->get_meta( '_nura_ref_code' );
		if ( $code ) {
			return array( 'channel' => 'Referral', 'detail' => 'Referral: ' . $code, 'manual' => false );
		}
		if ( 'nura_pos' === $order->get_created_via() ) {
			return array( 'channel' => 'Studio', 'detail' => 'Studio till', 'manual' => false );
		}
		$label = class_exists( 'NURAX_Growth' ) ? NURAX_Growth::classify( $order ) : 'Unknown';
		$map   = array(
			'ChatGPT'       => 'AI search',
			'Perplexity'    => 'AI search',
			'Copilot'       => 'AI search',
			'Gemini'        => 'AI search',
			'Claude'        => 'AI search',
			'Google'        => 'Search',
			'Bing / Ecosia' => 'Search',
			'Facebook ads'  => 'Facebook',
			'Instagram'     => 'Instagram',
			'Facebook'      => 'Facebook',
			'TikTok'        => 'TikTok',
			'WhatsApp'      => 'WhatsApp',
			'Review email'  => 'Email',
			'Direct'        => 'Direct',
			'Shop / admin'  => 'No source yet',
			'Unknown'       => 'Unknown',
		);
		$channel = isset( $map[ $label ] ) ? $map[ $label ] : 'Other site';
		if ( 'No source yet' !== $channel && 'Unknown' !== $channel ) {
			$detail = 'Website: ' . $label;
		} else {
			$detail = $channel;
		}
		return array( 'channel' => $channel, 'detail' => $detail, 'manual' => false );
	}

	/* ---- Order screen box ---- */

	public function meta_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $s ) {
			add_meta_box( 'nurax-order-source', 'Order source', array( $this, 'box' ), $s, 'side', 'high' );
		}
	}

	public function box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : 0 );
		$key   = $order ? (string) $order->get_meta( self::META ) : '';
		$ref   = $order ? (string) $order->get_meta( self::META_REF ) : '';
		$auto  = $order && $order->get_id() && ! self::is_hand_entered( $order ) ? self::source( $order ) : null;
		$need  = ! $auto; // Hand-entered or brand-new order: staff must choose.
		wp_nonce_field( 'nurax_order_source', 'nurax_order_source_nonce' );
		if ( $auto && ! $key ) {
			echo '<p style="margin:0 0 8px">Detected: <strong>' . esc_html( $auto['detail'] ) . '</strong></p><p class="description" style="margin:0 0 8px">Change it only if this is wrong.</p>';
		}
		echo '<select name="nurax_order_source" id="nurax_order_source" style="width:100%"' . ( $need ? ' required' : '' ) . '>';
		echo '<option value="">' . esc_html( $need ? 'Choose where this order came from' : 'Keep detected source' ) . '</option>';
		foreach ( self::options() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $key, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		echo '<p id="nurax_ref_wrap" style="margin:8px 0 0"><label for="nurax_order_referrer">Referred by (code or name)</label><input type="text" name="nurax_order_referrer" id="nurax_order_referrer" maxlength="60" style="width:100%" value="' . esc_attr( $ref ) . '"></p>';
		if ( $need ) {
			echo '<p class="description" style="margin:8px 0 0">Required for orders entered by hand, so the daily report counts WhatsApp, studio and referral sales.</p>';
		}
		echo '<script>(function(){var s=document.getElementById("nurax_order_source"),w=document.getElementById("nurax_ref_wrap"),r=document.getElementById("nurax_order_referrer");if(!s||!w){return;}function t(){var on="referral"===s.value;w.style.display=on?"":"none";if(r){r.required=on;}}s.addEventListener("change",t);t();})();</script>';
	}

	public function save( $order_id ) {
		if ( empty( $_POST['nurax_order_source_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nurax_order_source_nonce'] ) ), 'nurax_order_source' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$key = isset( $_POST['nurax_order_source'] ) ? sanitize_key( wp_unslash( $_POST['nurax_order_source'] ) ) : '';
		$ref = isset( $_POST['nurax_order_referrer'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['nurax_order_referrer'] ) ), 0, 60 ) : '';
		if ( $key && isset( self::options()[ $key ] ) ) {
			$order->update_meta_data( self::META, $key );
			if ( 'referral' === $key && '' !== $ref ) {
				$order->update_meta_data( self::META_REF, $ref );
			} else {
				$order->delete_meta_data( self::META_REF );
			}
			$order->save();
		}
	}

	/* ---- Orders page column ---- */

	public function columns( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'origin' === $k ) {
				$out['nurax_source'] = 'Source';
			}
		}
		if ( ! isset( $out['nurax_source'] ) ) {
			$out['nurax_source'] = 'Source';
		}
		return $out;
	}

	public function column_hpos( $column, $order ) {
		if ( 'nurax_source' === $column && $order instanceof WC_Order ) {
			$this->cell( $order );
		}
	}

	public function column_legacy( $column, $post_id ) {
		if ( 'nurax_source' === $column ) {
			$o = wc_get_order( $post_id );
			if ( $o ) {
				$this->cell( $o );
			}
		}
	}

	private function cell( $order ) {
		$s = self::source( $order );
		if ( 'No source yet' === $s['channel'] ) {
			echo '<span style="color:#b32d2e">No source yet</span>';
			return;
		}
		echo '<strong>' . esc_html( $s['channel'] ) . '</strong>';
		if ( $s['detail'] !== $s['channel'] ) {
			echo '<br><span style="color:#646970;font-size:12px">' . esc_html( $s['detail'] ) . '</span>';
		}
	}

	/* ---- Report ---- */

	public function menu() {
		add_submenu_page( 'woocommerce', 'Orders by source', 'Orders by source', 'manage_woocommerce', self::PAGE, array( $this, 'page' ) );
	}

	public static function data( $days ) {
		$out = array( 'days' => array(), 'totals' => array(), 'refs' => array(), 'orders' => 0, 'revenue' => 0.0 );
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}
		$tz    = wp_timezone();
		$start = new DateTime( 'today', $tz );
		$start->modify( '-' . ( (int) $days - 1 ) . ' days' );
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				'date_created' => '>=' . $start->getTimestamp(),
				'type'         => 'shop_order',
			)
		);
		foreach ( $orders as $o ) {
			$s   = self::source( $o );
			$c   = $s['channel'];
			$dt  = $o->get_date_created();
			$day = $dt ? $dt->date_i18n( 'Y-m-d' ) : 'unknown';
			$amt = (float) $o->get_total();
			if ( ! isset( $out['days'][ $day ][ $c ] ) ) {
				$out['days'][ $day ][ $c ] = 0;
			}
			$out['days'][ $day ][ $c ]++;
			if ( ! isset( $out['totals'][ $c ] ) ) {
				$out['totals'][ $c ] = array( 'orders' => 0, 'revenue' => 0.0 );
			}
			$out['totals'][ $c ]['orders']++;
			$out['totals'][ $c ]['revenue'] += $amt;
			$out['orders']++;
			$out['revenue'] += $amt;
			if ( 'Referral' === $c ) {
				$r = (string) $o->get_meta( self::META_REF );
				$r = $r ? $r : (string) $o->get_meta( '_nura_ref_code' );
				$r = $r ? $r : 'Not recorded';
				if ( ! isset( $out['refs'][ $r ] ) ) {
					$out['refs'][ $r ] = array( 'orders' => 0, 'revenue' => 0.0 );
				}
				$out['refs'][ $r ]['orders']++;
				$out['refs'][ $r ]['revenue'] += $amt;
			}
		}
		krsort( $out['days'] );
		uasort( $out['totals'], function ( $a, $b ) { return $b['orders'] <=> $a['orders']; } );
		uasort( $out['refs'], function ( $a, $b ) { return $b['orders'] <=> $a['orders']; } );
		return $out;
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification
		$days = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$d    = self::data( $days );
		echo '<div class="wrap"><h1>Orders by source</h1>';
		echo '<p>Paid orders (processing, on hold, completed) by where they came from. Hand-entered orders use the Order source chosen by staff; website orders use WooCommerce order attribution. Short bio links: /ig, /tt, /fb, /wa.</p><p>';
		foreach ( array( 7, 30, 90 ) as $p ) {
			$url = add_query_arg( array( 'page' => self::PAGE, 'days' => $p ), admin_url( 'admin.php' ) );
			echo '<a class="button' . ( $p === $days ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">Last ' . (int) $p . ' days</a> ';
		}
		echo '</p>';
		echo '<p><strong>' . (int) $d['orders'] . '</strong> orders, <strong>KSh ' . esc_html( number_format( $d['revenue'] ) ) . '</strong>, average <strong>' . esc_html( number_format( $d['orders'] / $days, 1 ) ) . '</strong> orders a day.</p>';
		if ( ! $d['orders'] ) {
			echo '<p>No paid orders in this period.</p></div>';
			return;
		}
		$chans = array_keys( $d['totals'] );
		echo '<h2>By source</h2><table class="widefat striped" style="max-width:640px"><thead><tr><th>Source</th><th>Orders</th><th>Revenue (KSh)</th><th>Share</th></tr></thead><tbody>';
		foreach ( $d['totals'] as $c => $t ) {
			echo '<tr><td>' . esc_html( $c ) . '</td><td>' . (int) $t['orders'] . '</td><td>' . esc_html( number_format( $t['revenue'] ) ) . '</td><td>' . esc_html( round( 100 * $t['orders'] / $d['orders'] ) ) . '%</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>Per day</h2><table class="widefat striped"><thead><tr><th>Day</th>';
		foreach ( $chans as $c ) {
			echo '<th>' . esc_html( $c ) . '</th>';
		}
		echo '<th>Total</th></tr></thead><tbody>';
		$tz  = wp_timezone();
		$cur = new DateTime( 'today', $tz );
		for ( $i = 0; $i < $days; $i++ ) {
			$key = $cur->format( 'Y-m-d' );
			$row = isset( $d['days'][ $key ] ) ? $d['days'][ $key ] : array();
			echo '<tr><td>' . esc_html( $cur->format( 'D j M' ) ) . '</td>';
			foreach ( $chans as $c ) {
				echo '<td>' . ( isset( $row[ $c ] ) ? (int) $row[ $c ] : '' ) . '</td>';
			}
			echo '<td><strong>' . (int) array_sum( $row ) . '</strong></td></tr>';
			$cur->modify( '-1 day' );
		}
		echo '</tbody></table>';
		if ( $d['refs'] ) {
			echo '<h2>Top referrers</h2><table class="widefat striped" style="max-width:640px"><thead><tr><th>Referred by</th><th>Orders</th><th>Revenue (KSh)</th></tr></thead><tbody>';
			foreach ( $d['refs'] as $r => $t ) {
				echo '<tr><td>' . esc_html( $r ) . '</td><td>' . (int) $t['orders'] . '</td><td>' . esc_html( number_format( $t['revenue'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
