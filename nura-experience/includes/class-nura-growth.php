<?php
/**
 * NURA growth (1.126.0): review stars, review link in the order completed
 * email, and the weekly AI / channel referral report.
 *
 * - Stars + review count on shop cards and beside the price on product pages,
 *   shown only when a product has approved reviews (no fake or empty stars).
 * - Order completed email: a short "How is your wig?" block linking to each
 *   product's review form (and the Google review page when a Place ID is set).
 *   This is part of the transactional email every buyer already gets.
 * - WooCommerce > AI referrals: orders and revenue by source (ChatGPT,
 *   Perplexity, Copilot, Gemini, Claude, Google, Bing/Ecosia, Facebook,
 *   Instagram, TikTok, WhatsApp, direct) for 7 / 28 / 90 days, from
 *   WooCommerce order attribution. A summary is emailed every Monday 08:00.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Growth {

	const CRON = 'nurax_weekly_referral_report';

	public function __construct() {
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'loop_stars' ), 7 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'single_stars' ), 9 );
		add_action( 'wp_head', array( $this, 'css' ), 99 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_review_block' ), 15, 4 );
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::CRON, array( $this, 'send_weekly' ) );
	}

	/* ---------- Stars ---------- */

	private static function stars_html( $avg, $count, $link = '' ) {
		$avg   = max( 0, min( 5, (float) $avg ) );
		$pct   = round( $avg / 5 * 100 );
		$label = sprintf( '%s out of 5 from %d review%s', number_format( $avg, 1 ), $count, 1 === $count ? '' : 's' );
		$inner = '<span class="nurax-stars" role="img" aria-label="' . esc_attr( $label ) . '"><span class="nurax-stars__on" style="width:' . (int) $pct . '%"></span></span>'
			. '<span class="nurax-stars__n">' . esc_html( number_format( $avg, 1 ) ) . ' (' . (int) $count . ')</span>';
		if ( $link ) {
			return '<a class="nurax-rating" href="' . esc_url( $link ) . '">' . $inner . '</a>';
		}
		return '<div class="nurax-rating">' . $inner . '</div>';
	}

	public function loop_stars() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$n = (int) $product->get_review_count();
		if ( $n < 1 ) {
			return;
		}
		echo self::stars_html( $product->get_average_rating(), $n ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function single_stars() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$n = (int) $product->get_review_count();
		if ( $n < 1 ) {
			return;
		}
		// WooCommerce's own rating block would duplicate ours.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
		echo self::stars_html( $product->get_average_rating(), $n, '#reviews' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function css() {
		if ( ! function_exists( 'is_woocommerce' ) || ! ( is_woocommerce() || is_front_page() ) ) {
			return;
		}
		echo '<style id="nurax-stars-css">.nurax-rating{display:flex;align-items:center;gap:6px;margin:4px 0;font-size:13px;line-height:1;color:inherit;text-decoration:none}'
			. '.nurax-stars{position:relative;display:inline-block;width:80px;height:14px;background:linear-gradient(90deg,#d9d2c5 0 100%);-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 80 14\'%3E%3Cpath d=\'M7 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L7 10.8 2.6 13.3l1.1-4.9L0 5.1l4.9-.5zM23 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L23 10.8l-4.4 2.5 1.1-4.9L16 5.1l4.9-.5zM39 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L39 10.8l-4.4 2.5 1.1-4.9L32 5.1l4.9-.5zM55 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L55 10.8l-4.4 2.5 1.1-4.9L48 5.1l4.9-.5zM71 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L71 10.8l-4.4 2.5 1.1-4.9L64 5.1l4.9-.5z\'/%3E%3C/svg%3E") no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 80 14\'%3E%3Cpath d=\'M7 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L7 10.8 2.6 13.3l1.1-4.9L0 5.1l4.9-.5zM23 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L23 10.8l-4.4 2.5 1.1-4.9L16 5.1l4.9-.5zM39 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L39 10.8l-4.4 2.5 1.1-4.9L32 5.1l4.9-.5zM55 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L55 10.8l-4.4 2.5 1.1-4.9L48 5.1l4.9-.5zM71 0l2.1 4.6 4.9.5-3.7 3.3 1.1 4.9L71 10.8l-4.4 2.5 1.1-4.9L64 5.1l4.9-.5z\'/%3E%3C/svg%3E") no-repeat}'
			. '.nurax-stars__on{position:absolute;inset:0 auto 0 0;background:#c9a24a}.nurax-stars__n{opacity:.8}</style>';
	}

	/* ---------- Review link in the order completed email ---------- */

	private static function google_review_url() {
		$pid = defined( 'NURA_GOOGLE_PLACE_ID' ) ? NURA_GOOGLE_PLACE_ID : get_option( 'nura_google_place_id', '' );
		$pid = trim( (string) apply_filters( 'nurax_google_place_id', $pid ) );
		return $pid ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $pid ) : '';
	}

	public function email_review_block( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email || 'customer_completed_order' !== $email->id ) {
			return;
		}
		$links = array();
		$seen  = array();
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( ! $pid || isset( $seen[ $pid ] ) || 'publish' !== get_post_status( $pid ) ) {
				continue;
			}
			$seen[ $pid ] = 1;
			$links[]      = array( get_the_title( $pid ), add_query_arg( 'utm_source', 'completed_email', get_permalink( $pid ) ) . '#reviews' );
			if ( count( $links ) >= 3 ) {
				break;
			}
		}
		if ( ! $links ) {
			return;
		}
		$g     = self::google_review_url();
		$intro = 'How is your new look? A quick review helps other women in Kenya choose with confidence.';
		if ( $plain_text ) {
			echo "\n" . esc_html( $intro ) . "\n";
			foreach ( $links as $l ) {
				echo esc_html( 'Review ' . $l[0] . ': ' . $l[1] ) . "\n";
			}
			if ( $g ) {
				echo esc_html( 'Review NURA Beauty on Google: ' . $g ) . "\n";
			}
			echo "\n";
			return;
		}
		echo '<div style="margin:24px 0;padding:16px 18px;border:1px solid #e8e1d4;border-radius:8px;background:#fbf8f2">';
		echo '<p style="margin:0 0 10px;font-weight:600">' . esc_html( $intro ) . '</p>';
		foreach ( $links as $l ) {
			echo '<p style="margin:6px 0"><a href="' . esc_url( $l[1] ) . '" style="color:#8a6a2b;font-weight:600">Review ' . esc_html( $l[0] ) . '</a></p>';
		}
		if ( $g ) {
			echo '<p style="margin:6px 0"><a href="' . esc_url( $g ) . '" style="color:#8a6a2b;font-weight:600">Review NURA Beauty on Google</a></p>';
		}
		echo '</div>';
	}

	/* ---------- Referral report ---------- */

	public static function classify( $o ) {
		$src  = strtolower( (string) $o->get_meta( '_wc_order_attribution_utm_source' ) );
		$ref  = strtolower( (string) $o->get_meta( '_wc_order_attribution_referrer' ) );
		$type = strtolower( (string) $o->get_meta( '_wc_order_attribution_source_type' ) );
		$med  = strtolower( (string) $o->get_meta( '_wc_order_attribution_utm_medium' ) );
		$hay  = $src . ' ' . $ref;
		$map  = array(
			'ChatGPT'        => array( 'chatgpt', 'openai' ),
			'Perplexity'     => array( 'perplexity' ),
			'Copilot'        => array( 'copilot' ),
			'Gemini'         => array( 'gemini.google', 'bard.google' ),
			'Claude'         => array( 'claude.ai', 'anthropic' ),
			'Facebook ads'   => array( 'fb_ad', 'facebook_ads' ),
			'Instagram'      => array( 'instagram', 'ig' ),
			'Facebook'       => array( 'facebook', 'fb.', 'm.facebook', 'l.facebook' ),
			'TikTok'         => array( 'tiktok' ),
			'WhatsApp'       => array( 'whatsapp', 'wa.me' ),
			'Google'         => array( 'google' ),
			'Bing / Ecosia'  => array( 'bing', 'ecosia', 'duckduckgo', 'yahoo' ),
			'Review email'   => array( 'completed_email', 'review' ),
		);
		if ( in_array( $med, array( 'paid', 'cpc', 'paid_social' ), true ) && ( false !== strpos( $hay, 'facebook' ) || false !== strpos( $hay, 'fb' ) || false !== strpos( $hay, 'instagram' ) ) ) {
			return 'Facebook ads';
		}
		foreach ( $map as $label => $needles ) {
			foreach ( $needles as $n ) {
				if ( 'ig' === $n ? 'ig' === $src : false !== strpos( $hay, $n ) ) {
					return $label;
				}
			}
		}
		if ( 'admin' === $type || 'nura_pos' === $o->get_created_via() ) {
			return 'Shop / admin';
		}
		if ( 'typein' === $type || '(direct)' === $src ) {
			return 'Direct';
		}
		return $src ? ucfirst( $src ) : 'Unknown';
	}

	public static function is_ai( $label ) {
		return in_array( $label, array( 'ChatGPT', 'Perplexity', 'Copilot', 'Gemini', 'Claude' ), true );
	}

	public static function report( $days ) {
		$rows = array();
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $rows;
		}
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				'date_created' => '>' . ( time() - DAY_IN_SECONDS * (int) $days ),
				'type'         => 'shop_order',
			)
		);
		foreach ( $orders as $o ) {
			$l = self::classify( $o );
			if ( ! isset( $rows[ $l ] ) ) {
				$rows[ $l ] = array( 'orders' => 0, 'revenue' => 0.0, 'ids' => array() );
			}
			$rows[ $l ]['orders']++;
			$rows[ $l ]['revenue'] += (float) $o->get_total();
			$rows[ $l ]['ids'][]    = $o->get_order_number();
		}
		uasort( $rows, function ( $a, $b ) { return $b['revenue'] <=> $a['revenue']; } );
		return $rows;
	}

	public function menu() {
		add_submenu_page( 'woocommerce', 'AI referrals', 'AI referrals', 'manage_woocommerce', 'nurax-referrals', array( $this, 'page' ) );
	}

	private static function table_html( $rows ) {
		if ( ! $rows ) {
			return '<p>No paid orders in this period.</p>';
		}
		$t  = '<table class="widefat striped" style="max-width:760px"><thead><tr><th>Source</th><th>Orders</th><th>Revenue (KSh)</th><th>Order numbers</th></tr></thead><tbody>';
		$to = 0;
		$tr = 0;
		$ai = 0;
		foreach ( $rows as $l => $r ) {
			$to += $r['orders'];
			$tr += $r['revenue'];
			if ( self::is_ai( $l ) ) {
				$ai += $r['orders'];
			}
			$t .= '<tr><td>' . ( self::is_ai( $l ) ? '<strong>' . esc_html( $l ) . ' (AI)</strong>' : esc_html( $l ) ) . '</td><td>' . (int) $r['orders'] . '</td><td>' . esc_html( number_format( $r['revenue'] ) ) . '</td><td>#' . esc_html( implode( ', #', array_slice( $r['ids'], 0, 12 ) ) ) . '</td></tr>';
		}
		$t .= '<tr><td><strong>Total</strong></td><td><strong>' . (int) $to . '</strong></td><td><strong>' . esc_html( number_format( $tr ) ) . '</strong></td><td>AI assistants: ' . (int) $ai . ' order' . ( 1 === $ai ? '' : 's' ) . '</td></tr>';
		return $t . '</tbody></table>';
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>AI and channel referrals</h1><p>Where paid orders came from, using WooCommerce order attribution. AI assistants are ChatGPT, Perplexity, Copilot, Gemini and Claude. A summary is emailed every Monday at 08:00.</p>';
		foreach ( array( 7, 28, 90 ) as $d ) {
			echo '<h2>Last ' . (int) $d . ' days</h2>' . self::table_html( self::report( $d ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			$tz   = wp_timezone();
			$next = new DateTime( 'next monday 08:00', $tz );
			wp_schedule_event( $next->getTimestamp(), 'weekly', self::CRON );
		}
	}

	public function send_weekly() {
		$to = apply_filters( 'nurax_referral_report_to', get_option( 'admin_email' ) );
		if ( ! $to ) {
			return;
		}
		$html = '<h2>NURA Beauty orders by source, last 7 days</h2>' . self::table_html( self::report( 7 ) )
			. '<h3>Last 28 days</h3>' . self::table_html( self::report( 28 ) )
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=nurax-referrals' ) ) . '">Open the full report</a></p>';
		wp_mail( $to, 'NURA weekly orders by source (AI, search, social)', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}
