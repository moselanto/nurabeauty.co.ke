<?php
/**
 * NURA Experience 1.109.0: Ask past customers for a review.
 *
 * WooCommerce > Ask for reviews lists every past customer who has actually bought
 * (paid orders only; test and unpaid orders are left out), one row per phone number,
 * newest first. "Ask on WhatsApp" opens WhatsApp with a friendly message that links
 * straight to the review form of each piece they bought (guests can review, no login),
 * plus the Google review link, and records the date so nobody is asked twice.
 * Customers already asked by the automatic follow-up are shown as asked.
 * Real reviews only - nothing is ever written or posted for a customer.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Review_Asks {

	const META = '_nurax_review_asked';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 33 );
		add_action( 'admin_post_nurax_ra_ask', array( $this, 'ask' ) );
		add_action( 'admin_post_nurax_ra_undo', array( $this, 'undo' ) );
	}

	public function menu() {
		add_submenu_page( 'woocommerce', 'Ask for reviews', 'Ask for reviews', 'manage_woocommerce', 'nurax-review-asks', array( $this, 'page' ) );
	}

	/** Paid order? Uses the Profit & Loss marks when available. */
	private static function is_sale( $o ) {
		if ( class_exists( 'NURAX_Desk_Accounts' ) && method_exists( 'NURAX_Desk_Accounts', 'sale_state' ) ) {
			return 'paid' === NURAX_Desk_Accounts::sale_state( $o );
		}
		return 'completed' === $o->get_status();
	}

	private static function phone( $o ) {
		$p = $o->get_billing_phone();
		if ( '' === trim( (string) $p ) ) {
			$p = $o->get_shipping_phone();
		}
		return class_exists( 'NURAX_Community' ) ? NURAX_Community::wa_number( $p ) : preg_replace( '/\D+/', '', (string) $p );
	}

	/** When this order's customer was asked (timestamp) or 0. */
	private static function asked( $o ) {
		foreach ( array( self::META, '_nurax_fu_2_sent' ) as $k ) {
			$v = (string) $o->get_meta( $k );
			if ( is_numeric( $v ) && (int) $v > 0 ) {
				return (int) $v;
			}
		}
		return 0;
	}

	/** Products bought: id => title (max 4). */
	private static function products( $o ) {
		$out = array();
		foreach ( $o->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ( $pid > 0 && 'publish' === get_post_status( $pid ) && ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = wp_strip_all_tags( get_the_title( $pid ) );
			}
			if ( count( $out ) >= 4 ) {
				break;
			}
		}
		return $out;
	}

	public static function message( $o ) {
		$first = trim( (string) $o->get_billing_first_name() );
		$prods = self::products( $o );
		$lines = array();
		$lines[] = 'Hi ' . ( '' !== $first ? $first : 'there' ) . ', thank you for shopping with NURA Beauty. We hope you are loving your new look!';
		$lines[] = '';
		if ( $prods ) {
			$lines[] = 'Could you spare a minute to rate what you bought? Tap the link, choose your stars and write a line or two. No account needed:';
			foreach ( $prods as $pid => $title ) {
				$lines[] = '- ' . $title . ': ' . get_permalink( $pid ) . '#reviews';
			}
			$lines[] = '';
			$lines[] = 'A Google review also helps other women find us: ' . home_url( '/review/' );
		} else {
			$lines[] = 'Could you spare a minute to leave us a Google review? It really helps other women find us: ' . home_url( '/review/' );
		}
		$lines[] = '';
		$lines[] = 'A photo wearing it is very welcome too. With your OK we would love to feature you.';
		return implode( "\n", $lines );
	}

	/** One row per customer (phone), newest paid order first. */
	private static function customers() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders( array( 'limit' => 500, 'type' => 'shop_order', 'status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ), 'orderby' => 'date', 'order' => 'DESC' ) );
		$rows   = array();
		foreach ( (array) $orders as $o ) {
			if ( ! self::is_sale( $o ) ) {
				continue;
			}
			$ph = self::phone( $o );
			if ( '' === $ph ) {
				continue;
			}
			$a = self::asked( $o );
			if ( ! isset( $rows[ $ph ] ) ) {
				$rows[ $ph ] = array( 'order' => $o, 'asked' => $a, 'count' => 1 );
			} else {
				$rows[ $ph ]['count']++;
				if ( $a > $rows[ $ph ]['asked'] ) {
					$rows[ $ph ]['asked'] = $a;
				}
			}
		}
		return $rows;
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$rows = self::customers();
		$todo = array_filter( $rows, function ( $r ) { return ! $r['asked']; } );
		$done = array_filter( $rows, function ( $r ) { return (bool) $r['asked']; } );
		echo '<div class="wrap"><h1>Ask past customers for a review</h1>';
		echo '<p>Everyone who has paid for an order, one line per customer. Tap <strong>Ask on WhatsApp</strong>: WhatsApp opens with the message ready (links to the review form of each piece they bought, no account needed, plus your Google review link). Press send in WhatsApp. The customer then moves to "Already asked" so nobody is asked twice.</p>';
		echo '<p><strong>' . count( $todo ) . '</strong> to ask &middot; ' . count( $done ) . ' already asked. Test orders and unpaid orders are not listed (mark them on NURA Desk &gt; Accounts).</p>';
		if ( isset( $_GET['asked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>Marked as asked. Send the message in the WhatsApp tab that opened.</p></div>';
		}
		self::table( $todo, false );
		if ( $done ) {
			echo '<h2 style="margin-top:28px">Already asked</h2>';
			self::table( $done, true );
		}
		echo '</div>';
	}

	private static function table( $rows, $asked ) {
		if ( ! $rows ) {
			echo '<p><em>' . ( $asked ? 'Nobody yet.' : 'Everyone has been asked.' ) . '</em></p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Customer</th><th>Last order</th><th>Bought</th><th>' . ( $asked ? 'Asked' : '' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $ph => $r ) {
			$o    = $r['order'];
			$name = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
			$date = $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j M Y' ) : '';
			$edit = $o->get_edit_order_url();
			echo '<tr><td><strong>' . esc_html( '' !== $name ? $name : 'Customer' ) . '</strong><br><span style="color:#666">+' . esc_html( $ph ) . '</span></td>';
			echo '<td><a href="' . esc_url( $edit ) . '">#' . esc_html( $o->get_order_number() ) . '</a> &middot; ' . esc_html( $date ) . ( $r['count'] > 1 ? '<br><span style="color:#666">' . (int) $r['count'] . ' orders</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', self::products( $o ) ) ) . '</td><td>';
			$ask = wp_nonce_url( admin_url( 'admin-post.php?action=nurax_ra_ask&order=' . $o->get_id() ), 'nurax_ra_' . $o->get_id() );
			if ( $asked ) {
				echo esc_html( wp_date( 'j M Y', $r['asked'] ) ) . '<br><a href="' . esc_url( $ask ) . '" target="_blank" rel="noopener">Ask again</a>';
			} else {
				echo '<a class="button button-primary" href="' . esc_url( $ask ) . '" target="_blank" rel="noopener" onclick="var t=this.closest(\'tr\');setTimeout(function(){t.style.opacity=.4;},300);">Ask on WhatsApp</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function ask() {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $id || ! check_admin_referer( 'nurax_ra_' . $id ) ) {
			wp_die( 'Not allowed.' );
		}
		$o = wc_get_order( $id );
		$ph = $o ? self::phone( $o ) : '';
		if ( ! $o || '' === $ph ) {
			wp_die( 'This order has no usable phone number.' );
		}
		$o->update_meta_data( self::META, (string) time() );
		$o->save();
		$u = wp_get_current_user();
		$o->add_order_note( 'Review request opened in WhatsApp by ' . ( $u && $u->ID ? $u->display_name : 'owner' ) . '.' );
		wp_redirect( 'https://wa.me/' . $ph . '?text=' . rawurlencode( self::message( $o ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed WhatsApp host.
		exit;
	}

	public function undo() {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $id || ! check_admin_referer( 'nurax_ra_' . $id ) ) {
			wp_die( 'Not allowed.' );
		}
		$o = wc_get_order( $id );
		if ( $o ) {
			$o->delete_meta_data( self::META );
			$o->save();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=nurax-review-asks' ) );
		exit;
	}
}
