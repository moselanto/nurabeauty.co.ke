<?php
/**
 * NURA referral programme (NURA Experience 1.128.0, branch referral-programme).
 *
 * Customers recommend us to friends:
 *   1. When a customer's order is completed they get a personal code
 *      (e.g. AMINA300) and link (nurabeauty.co.ke/?ref=AMINA300).
 *   2. A friend who opens the link (code applied automatically) or types the
 *      code gets KSh 300 off a first order of KSh 2,500 or more.
 *   3. When the friend's order is COMPLETED, the referrer gets a single-use
 *      KSh 300 coupon for the next order, valid 60 days. Cancelled, failed or
 *      refunded orders earn nothing.
 *
 * The personal code is a normal WooCommerce coupon (meta _nura_ref_owner), so
 * it works on the website and on hand-entered orders (staff apply the code).
 * Each order using a code stores _nura_ref_code, which NURAX_Order_Source
 * reports as Referral, with top referrers in WooCommerce > Orders by source.
 * WhatsApp share buttons: product pages, order-received page, My Account.
 *
 * Switched off by default (option nurax_referral_on). While off, only shop
 * managers see the share boxes (preview) and no codes or rewards are issued.
 * Checkout code paths are only touched by WooCommerce's own coupon handling.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Referral {

	const OPT_ON     = 'nurax_referral_on';
	const OWNER      = '_nura_ref_owner';
	const USER_CODE  = '_nura_ref_code';
	const ORDER_CODE = '_nura_ref_code';
	const REWARDED   = '_nura_ref_rewarded';
	const COOKIE     = 'nura_ref';
	const PAGE       = 'nurax-referral';

	public function __construct() {
		add_action( 'init', array( $this, 'capture_link' ), 5 );
		add_action( 'wp_loaded', array( $this, 'auto_apply' ), 30 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'auto_apply' ), 30 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate' ), 20, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'tag_order_id' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'tag_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 20, 4 );
		add_action( 'woocommerce_thankyou', array( $this, 'thankyou_box' ), 25, 1 );
		add_action( 'woocommerce_account_dashboard', array( $this, 'account_box' ), 5 );
		add_action( 'woocommerce_share', array( $this, 'product_share' ), 20 );
		add_action( 'admin_menu', array( $this, 'menu' ), 62 );
		add_action( 'admin_post_nurax_referral_save', array( $this, 'save_settings' ) );
	}

	/* ---- Settings ---- */

	public static function on() {
		return '1' === (string) get_option( self::OPT_ON, '0' );
	}

	public static function preview() {
		return ! self::on() && current_user_can( 'manage_woocommerce' );
	}

	public static function cfg() {
		return (array) apply_filters(
			'nurax_referral_config',
			array(
				'friend'  => 300,
				'min'     => 2500,
				'reward'  => 300,
				'expiry'  => 60,
				'cookie'  => 30,
			)
		);
	}

	/* ---- Codes ---- */

	public static function code_for_order( $order ) {
		$email = sanitize_email( $order->get_billing_email() );
		$phone = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
		$uid   = (int) $order->get_customer_id();
		if ( $uid ) {
			$c = (string) get_user_meta( $uid, self::USER_CODE, true );
			if ( $c && wc_get_coupon_id_by_code( $c ) ) {
				return $c;
			}
		}
		$existing = self::find_owner_coupon( $email, $phone );
		if ( $existing ) {
			if ( $uid ) {
				update_user_meta( $uid, self::USER_CODE, $existing );
			}
			return $existing;
		}
		if ( ! $email && ! $phone ) {
			return '';
		}
		$cfg  = self::cfg();
		$base = strtoupper( preg_replace( '/[^A-Za-z]/', '', remove_accents( (string) $order->get_billing_first_name() ) ) );
		$base = substr( $base ? $base : 'NURA', 0, 8 );
		$code = '';
		for ( $i = 0; $i < 20; $i++ ) {
			$try = $base . ( 0 === $i ? (int) $cfg['friend'] : wp_rand( 100, 999 ) );
			if ( ! wc_get_coupon_id_by_code( $try ) ) {
				$code = $try;
				break;
			}
		}
		if ( ! $code ) {
			return '';
		}
		$cp = new WC_Coupon();
		$cp->set_code( $code );
		$cp->set_discount_type( 'fixed_cart' );
		$cp->set_amount( (float) $cfg['friend'] );
		$cp->set_minimum_amount( (float) $cfg['min'] );
		$cp->set_usage_limit_per_user( 1 );
		$cp->set_individual_use( false );
		$cp->set_description( 'NURA referral code for ' . ( $email ? $email : $phone ) . ' (friend discount).' );
		$cp->update_meta_data( self::OWNER, array( 'email' => $email, 'phone' => $phone, 'user' => $uid, 'name' => $order->get_formatted_billing_full_name() ) );
		$cp->save();
		if ( $uid ) {
			update_user_meta( $uid, self::USER_CODE, $code );
		}
		$order->add_order_note( 'Referral code created for this customer: ' . $code );
		return $code;
	}

	private static function find_owner_coupon( $email, $phone ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.post_title, pm.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = 'shop_coupon' AND p.post_status = 'publish' AND pm.meta_key = %s", self::OWNER ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $r ) {
			$o = maybe_unserialize( $r->meta_value );
			if ( is_array( $o ) && ( ( $email && isset( $o['email'] ) && strtolower( $o['email'] ) === strtolower( $email ) ) || ( $phone && ! empty( $o['phone'] ) && substr( $o['phone'], -9 ) === substr( $phone, -9 ) ) ) ) {
				return strtoupper( $r->post_title );
			}
		}
		return '';
	}

	public static function owner( $code ) {
		$id = $code ? wc_get_coupon_id_by_code( $code ) : 0;
		if ( ! $id ) {
			return null;
		}
		$o = get_post_meta( $id, self::OWNER, true );
		return is_array( $o ) ? $o : null;
	}

	public static function ref_code_in( $order ) {
		foreach ( $order->get_coupon_codes() as $c ) {
			if ( self::owner( $c ) ) {
				return strtoupper( $c );
			}
		}
		return '';
	}

	public static function share_link( $code, $url = '' ) {
		$url = $url ? $url : home_url( '/' );
		return add_query_arg( array( 'ref' => $code, 'utm_source' => 'whatsapp', 'utm_medium' => 'referral', 'utm_campaign' => 'refer_a_friend' ), $url );
	}

	/* ---- Friend side: link, auto-apply, validity ---- */

	public function capture_link() {
		if ( is_admin() || empty( $_GET['ref'] ) || ! self::on() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = strtoupper( substr( sanitize_key( wp_unslash( $_GET['ref'] ) ), 0, 20 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( self::owner( $code ) ) {
			setcookie( self::COOKIE, $code, time() + DAY_IN_SECONDS * (int) self::cfg()['cookie'], COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			$_COOKIE[ self::COOKIE ] = $code;
		}
	}

	public function auto_apply() {
		if ( is_admin() || ! self::on() || empty( $_COOKIE[ self::COOKIE ] ) || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$code = strtoupper( sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
		if ( ! self::owner( $code ) || WC()->cart->has_discount( $code ) ) {
			return;
		}
		if ( (float) WC()->cart->get_subtotal() < (float) self::cfg()['min'] ) {
			return; // Applied once the basket reaches the minimum.
		}
		WC()->cart->apply_coupon( $code );
	}

	public function validate( $valid, $coupon, $discounts = null ) {
		if ( ! $valid || ! self::owner( $coupon->get_code() ) ) {
			return $valid;
		}
		if ( ! self::on() && ! current_user_can( 'manage_woocommerce' ) ) {
			throw new Exception( esc_html__( 'This code is not active yet.', 'nura-experience' ) );
		}
		$owner = self::owner( $coupon->get_code() );
		list( $email, $phone, $uid ) = self::buyer( $discounts );
		$self = ( $uid && (int) $owner['user'] === $uid ) || ( $email && strtolower( (string) $owner['email'] ) === strtolower( $email ) ) || ( $phone && ! empty( $owner['phone'] ) && substr( $owner['phone'], -9 ) === substr( $phone, -9 ) );
		if ( $self ) {
			throw new Exception( esc_html__( 'Your own referral code is for friends. Share it and earn KSh 300 when they order.', 'nura-experience' ) );
		}
		if ( self::has_paid_order( $email, $phone, $uid ) ) {
			throw new Exception( esc_html__( 'Referral codes are for a first order with us.', 'nura-experience' ) );
		}
		return $valid;
	}

	private static function buyer( $discounts ) {
		$obj = ( $discounts && method_exists( $discounts, 'get_object' ) ) ? $discounts->get_object() : null;
		if ( $obj instanceof WC_Order ) {
			return array( $obj->get_billing_email(), preg_replace( '/\D+/', '', (string) $obj->get_billing_phone() ), (int) $obj->get_customer_id() );
		}
		$c = function_exists( 'WC' ) && WC()->customer ? WC()->customer : null;
		return array( $c ? $c->get_billing_email() : '', $c ? preg_replace( '/\D+/', '', (string) $c->get_billing_phone() ) : '', get_current_user_id() );
	}

	private static function has_paid_order( $email, $phone, $uid ) {
		$st   = array( 'wc-processing', 'wc-completed', 'wc-on-hold' );
		$args = array( 'limit' => 1, 'return' => 'ids', 'status' => $st, 'type' => 'shop_order' );
		if ( $uid && wc_get_orders( $args + array( 'customer_id' => $uid ) ) ) {
			return true;
		}
		if ( $email && wc_get_orders( $args + array( 'billing_email' => $email ) ) ) {
			return true;
		}
		if ( $phone && strlen( $phone ) >= 9 && wc_get_orders( $args + array( 'billing_phone' => $phone ) ) ) {
			return true;
		}
		return false;
	}

	/* ---- Orders: tag, reward ---- */

	public function tag_order_id( $order_id ) {
		$o = wc_get_order( $order_id );
		if ( $o ) {
			$this->tag_order( $o );
		}
	}

	public function tag_order( $order ) {
		$code = self::ref_code_in( $order );
		if ( $code && $code !== $order->get_meta( self::ORDER_CODE ) ) {
			$order->update_meta_data( self::ORDER_CODE, $code );
			$order->save_meta_data();
		}
	}

	public function status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$this->tag_order( $order );
		if ( 'completed' !== $to || ! self::on() ) {
			return;
		}
		self::code_for_order( $order ); // Every completed buyer can now refer friends.
		$code = (string) $order->get_meta( self::ORDER_CODE );
		if ( ! $code || $order->get_meta( self::REWARDED ) ) {
			return;
		}
		$owner = self::owner( $code );
		if ( ! $owner ) {
			return;
		}
		$cfg = self::cfg();
		$rc  = 'THANKS' . strtoupper( wp_generate_password( 6, false, false ) );
		$cp  = new WC_Coupon();
		$cp->set_code( $rc );
		$cp->set_discount_type( 'fixed_cart' );
		$cp->set_amount( (float) $cfg['reward'] );
		$cp->set_usage_limit( 1 );
		$cp->set_date_expires( time() + DAY_IN_SECONDS * (int) $cfg['expiry'] );
		if ( ! empty( $owner['email'] ) ) {
			$cp->set_email_restrictions( array( $owner['email'] ) );
		}
		$cp->set_description( 'NURA referral reward for ' . $code . ' (order #' . $order->get_order_number() . ').' );
		$cp->update_meta_data( '_nura_ref_reward_for', $code );
		$cp->save();
		$order->update_meta_data( self::REWARDED, $rc );
		$order->add_order_note( 'Referral reward ' . $rc . ' (KSh ' . (int) $cfg['reward'] . ') issued to the owner of ' . $code . '.' );
		$order->save();
		if ( ! empty( $owner['email'] ) ) {
			$body = '<p>Hi' . ( ! empty( $owner['name'] ) ? ' ' . esc_html( strtok( $owner['name'], ' ' ) ) : '' ) . ',</p><p>A friend you referred just received their NURA order. Thank you for recommending our business.</p><p>Here is <strong>KSh ' . (int) $cfg['reward'] . ' off</strong> your next order: <strong>' . esc_html( $rc ) . '</strong> (single use, valid ' . (int) $cfg['expiry'] . ' days).</p><p><a href="' . esc_url( home_url( '/shop/' ) ) . '">Shop NURA Beauty</a></p>';
			wp_mail( $owner['email'], 'Your NURA referral reward: KSh ' . (int) $cfg['reward'] . ' off', $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
		if ( class_exists( 'NURAX_Order_Source' ) && ! $order->get_meta( NURAX_Order_Source::META ) ) {
			$order->update_meta_data( NURAX_Order_Source::META, 'referral' );
			$order->update_meta_data( NURAX_Order_Source::META_REF, $code );
			$order->save_meta_data();
		}
	}

	/* ---- Share boxes ---- */

	private static function wa_url( $text ) {
		return 'https://wa.me/?text=' . rawurlencode( $text );
	}

	private static function box_html( $code ) {
		$cfg  = self::cfg();
		$link = self::share_link( $code );
		$msg  = 'I get my wigs from NURA Beauty. Use my code ' . $code . ' for KSh ' . (int) $cfg['friend'] . ' off your first order of KSh ' . number_format( (int) $cfg['min'] ) . ' or more: ' . $link;
		$h    = '<div class="nura-ref-box" style="margin:22px 0;padding:18px 20px;border:1px solid #e8e1d4;border-radius:10px;background:#fbf8f2">';
		$h   .= self::preview() ? '<p style="margin:0 0 8px;color:#b32d2e;font-size:12px">Preview: only shop managers see this until the referral programme is switched on.</p>' : '';
		$h   .= '<p style="margin:0 0 6px;font-weight:700">Share NURA with a friend</p>';
		$h   .= '<p style="margin:0 0 10px">They get KSh ' . (int) $cfg['friend'] . ' off a first order of KSh ' . esc_html( number_format( (int) $cfg['min'] ) ) . ' or more. When their order is completed, you get KSh ' . (int) $cfg['reward'] . ' off your next order.</p>';
		$h   .= '<p style="margin:0 0 10px">Your code: <strong style="letter-spacing:.06em">' . esc_html( $code ) . '</strong><br><span style="font-size:13px;word-break:break-all">' . esc_html( $link ) . '</span></p>';
		$h   .= '<a href="' . esc_url( self::wa_url( $msg ) ) . '" target="_blank" rel="noopener" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#0E0E0E;color:#E4C983;font-weight:700;text-decoration:none">Share on WhatsApp</a>';
		return $h . '</div>';
	}

	private static function sample_code() {
		return 'PREVIEW' . (int) self::cfg()['friend'];
	}

	public function thankyou_box( $order_id ) {
		if ( ! self::on() && ! self::preview() ) {
			return;
		}
		$o    = wc_get_order( $order_id );
		$code = '';
		if ( $o && $o->get_customer_id() ) {
			$code = (string) get_user_meta( $o->get_customer_id(), self::USER_CODE, true );
		}
		if ( ! $code && self::preview() ) {
			$code = self::sample_code();
		}
		if ( $code ) {
			echo self::box_html( $code ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( self::on() ) {
			echo '<p class="nura-ref-later" style="margin:18px 0">Once your order is completed you will get a personal code to share with friends: they get KSh ' . (int) self::cfg()['friend'] . ' off, and you get KSh ' . (int) self::cfg()['reward'] . ' off your next order.</p>';
		}
	}

	public function account_box() {
		if ( ! self::on() && ! self::preview() ) {
			return;
		}
		$code = (string) get_user_meta( get_current_user_id(), self::USER_CODE, true );
		if ( ! $code && self::preview() ) {
			$code = self::sample_code();
		}
		if ( $code ) {
			echo self::box_html( $code ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public function product_share() {
		if ( ! self::on() && ! self::preview() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$code = is_user_logged_in() ? (string) get_user_meta( get_current_user_id(), self::USER_CODE, true ) : '';
		$url  = $code ? self::share_link( $code, $product->get_permalink() ) : add_query_arg( array( 'utm_source' => 'whatsapp', 'utm_medium' => 'share', 'utm_campaign' => 'product' ), $product->get_permalink() );
		$msg  = 'Look at this wig from NURA Beauty: ' . $product->get_name() . ' ' . $url;
		echo '<p class="nura-wa-share" style="margin:12px 0"><a href="' . esc_url( self::wa_url( $msg ) ) . '" target="_blank" rel="noopener" style="font-weight:600;color:#1f7a3a;text-decoration:none">Share on WhatsApp</a>' . ( self::preview() ? ' <span style="color:#b32d2e;font-size:12px">(preview)</span>' : '' ) . '</p>';
	}

	/* ---- Admin ---- */

	public function menu() {
		add_submenu_page( 'woocommerce', 'Referral programme', 'Referral programme', 'manage_woocommerce', self::PAGE, array( $this, 'page' ) );
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$cfg = self::cfg();
		global $wpdb;
		$codes   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::OWNER ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rewards = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_nura_ref_reward_for'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		echo '<div class="wrap"><h1>Referral programme</h1>';
		echo '<p>Friend: KSh ' . (int) $cfg['friend'] . ' off a first order of KSh ' . esc_html( number_format( (int) $cfg['min'] ) ) . '+. Referrer: KSh ' . (int) $cfg['reward'] . ' coupon when the friend\'s order is completed, valid ' . (int) $cfg['expiry'] . ' days.</p>';
		echo '<p>Status: <strong>' . ( self::on() ? 'ON' : 'OFF (shop managers see a preview only)' ) . '</strong>. Codes issued: ' . (int) $codes . '. Rewards issued: ' . (int) $rewards . '. Referral orders and top referrers: <a href="' . esc_url( admin_url( 'admin.php?page=nurax-order-sources' ) ) . '">Orders by source</a>.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nurax_referral_save' );
		echo '<input type="hidden" name="action" value="nurax_referral_save"><label><input type="checkbox" name="on" value="1"' . checked( self::on(), true, false ) . '> Referral programme is live for customers</label> ';
		submit_button( 'Save', 'primary', 'submit', false );
		echo '</form><p class="description">Staff entering a WhatsApp or studio order for a referred friend: apply the friend\'s code as a coupon on the order and choose Order source "Referral".</p></div>';
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'nurax_referral_save' );
		update_option( self::OPT_ON, empty( $_POST['on'] ) ? '0' : '1', false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}
}
