<?php
/**
 * NURA Experience 1.115.0 - Paystack split: separate M-Pesa and Card choices at
 * checkout, with a 2.9% card processing fee (rounded to whole shillings) that the
 * customer pays only when the card choice is used.
 *
 * How it works (Paystack WooCommerce Payment Gateway 5.9.0 is required):
 *   - The Paystack plugin ships "custom gateways" (WC_Gateway_Paystack_One...) but
 *     only registers them for NGN stores. For KES we register WC_Gateway_Paystack_One
 *     ourselves as the card choice; the Paystack plugin already registers its block
 *     checkout integration, which becomes active once the gateway exists.
 *   - Channels go through the plugin's own filter `wc_paystack_payment_channels`:
 *     main `paystack` => mobile_money (M-Pesa), `paystack-one` => card.
 *   - Front-end title/description of the main gateway become "M-Pesa (Paystack)".
 *   - The fee is a cart fee (woocommerce_cart_calculate_fees) when the chosen method
 *     is the card choice. Block checkout tells the server which method is chosen via
 *     extensionCartUpdate (namespace nura-card-fee); classic checkout re-runs
 *     update_checkout. The Store API checkout is re-checked server-side so an order
 *     always carries the fee only when it is paid by card.
 *   - Fee lines carry meta _nura_card_fee; NURA Desk Accounts books them to 4110.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Paystack_Split {

	const CARD     = 'paystack-one';
	const MAIN     = 'paystack';
	const RATE     = 0.029;
	const FEE_META = '_nura_card_fee';
	const NS       = 'nura-card-fee';
	const SEEDV    = '1.115.0';

	public static function fee_name() {
		return 'Card processing fee (2.9%)';
	}

	public static function hooks() {
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'gateways' ), 120 );
		add_filter( 'wc_paystack_payment_channels', array( __CLASS__, 'channels' ), 20, 3 );
		add_filter( 'option_woocommerce_paystack_settings', array( __CLASS__, 'main_labels' ), 20 );
		add_action( 'init', array( __CLASS__, 'seed' ), 20 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'cart_fee' ), 20 );
		add_action( 'woocommerce_checkout_create_order_fee_item', array( __CLASS__, 'tag_fee' ), 10, 4 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'guard_order' ), 50, 2 );
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'store_api' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'admin_notices', array( __CLASS__, 'secret_check' ) );
		// TEMPORARY save probe (1.116.1): yes/no facts about Paystack settings saves, never the value.
		add_action( 'admin_init', array( __CLASS__, 'probe_request' ), 1 );
		add_action( 'woocommerce_update_options_payment_gateways_paystack', array( __CLASS__, 'probe_before' ), 1 );
		add_action( 'woocommerce_update_options_payment_gateways_paystack', array( __CLASS__, 'probe_after' ), 999 );
		// Tell shoppers about the card fee before they pay (classic + block cart/checkout, product page).
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'fee_notice_echo' ) );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'fee_notice_echo' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'fee_notice_echo' ) );
		add_filter( 'render_block', array( __CLASS__, 'fee_notice_block' ), 20, 2 );
	}

	/** Shopper-facing wording for the card fee. */
	public static function fee_notice_html() {
		if ( ! self::card_on() ) {
			return '';
		}
		return '<div class="nura-card-fee-note" style="margin:10px 0;padding:10px 12px;border:1px solid #e6d3a3;background:#fdf8ec;border-radius:6px;font-size:14px;line-height:1.45;">'
			. '<strong>For card transactions: 2.9% card processing fee.</strong> '
			. 'Paying by Visa or Mastercard adds a 2.9% fee to your total, shown at checkout before you pay. M-Pesa has no extra fee.'
			. '</div>';
	}

	public static function fee_notice_echo() {
		static $done = array();
		$k = current_filter();
		if ( isset( $done[ $k ] ) ) {
			return;
		}
		$done[ $k ] = true;
		echo self::fee_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
	}

	public static function fee_notice_block( $html, $block ) {
		if ( is_admin() || empty( $block['blockName'] ) ) {
			return $html;
		}
		if ( in_array( $block['blockName'], array( 'woocommerce/checkout', 'woocommerce/cart' ), true ) ) {
			return self::fee_notice_html() . $html;
		}
		return $html;
	}

	/**
	 * Read-only check on WooCommerce > Settings > Payments > Paystack: is a whsec_ Live Webhook
	 * Signing Secret stored? Shows yes/no only - the value is never printed or changed.
	 */
	public static function secret_status() {
		$o = get_option( 'woocommerce_paystack_settings', array() );
		$v = is_array( $o ) && isset( $o['live_webhook_signing_secret'] ) ? trim( (string) $o['live_webhook_signing_secret'] ) : '';
		if ( '' === $v ) {
			return 'no-empty';
		}
		return 0 === strpos( $v, 'whsec_' ) ? 'yes' : 'no-other';
	}

	/* ---- TEMPORARY save probe (remove once the signing secret saves) ---- */

	const PROBE = 'nurax_paystack_save_probe';
	const FIELD = 'woocommerce_paystack_live_webhook_signing_secret';

	private static function probe_log( $row ) {
		$log = get_option( self::PROBE, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array_merge( array( 'time' => current_time( 'Y-m-d H:i:s' ) ), $row ) );
		update_option( self::PROBE, array_slice( $log, 0, 8 ), false );
	}

	private static function posted() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only yes/no facts; value is never stored.
		$raw = isset( $_POST[ self::FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::FIELD ] ) ) : null;
		return array(
			'field_sent' => null === $raw ? 'no' : 'yes',
			'filled'     => ( null !== $raw && '' !== $raw ) ? 'yes' : 'no',
			'whsec'      => ( null !== $raw && 0 === strpos( $raw, 'whsec_' ) ) ? 'yes' : 'no',
		);
	}

	/** Did a POST for the Paystack settings screen reach WordPress at all? */
	public static function probe_request() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return;
		}
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( 'wc-settings' !== $page || 'paystack' !== $section ) {
			return;
		}
		$nonce_ok = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ? 'yes' : 'no';
		self::probe_log( array_merge( array( 'stage' => 'request reached WordPress', 'nonce_ok' => $nonce_ok, 'can_manage' => current_user_can( 'manage_woocommerce' ) ? 'yes' : 'no' ), self::posted() ) );
	}

	public static function probe_before() {
		self::probe_log( array_merge( array( 'stage' => 'WooCommerce started the Paystack save' ), self::posted() ) );
	}

	public static function probe_after() {
		$errs = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$gws = WC()->payment_gateways()->payment_gateways();
			if ( isset( $gws['paystack'] ) && method_exists( $gws['paystack'], 'get_errors' ) ) {
				foreach ( (array) $gws['paystack']->get_errors() as $e ) {
					$errs[] = mb_substr( wp_strip_all_tags( (string) $e ), 0, 120 );
				}
			}
		}
		wp_cache_delete( 'woocommerce_paystack_settings', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$st = self::secret_status();
		self::probe_log( array(
			'stage'          => 'Paystack save finished',
			'stored_after'   => 'yes' === $st ? 'yes (whsec_)' : ( 'no-other' === $st ? 'no (non-whsec_ value)' : 'no (empty)' ),
			'save_errors'    => $errs ? implode( ' | ', $errs ) : 'none',
		) );
	}

	private static function probe_html() {
		$log = get_option( self::PROBE, array() );
		if ( ! is_array( $log ) || ! $log ) {
			return '<p><strong>Save check (temporary):</strong> no Paystack save recorded yet - click Save changes, then reload this page.</p>';
		}
		$out = '<p><strong>Save check (temporary) - newest first, values never recorded:</strong></p><ul style="margin-left:18px;list-style:disc">';
		foreach ( $log as $r ) {
			$bits = array();
			foreach ( $r as $k => $v ) {
				if ( 'time' !== $k && 'stage' !== $k ) {
					$bits[] = $k . ': ' . $v;
				}
			}
			$out .= '<li>' . esc_html( $r['time'] . ' - ' . $r['stage'] . ( $bits ? ' (' . implode( ', ', $bits ) . ')' : '' ) ) . '</li>';
		}
		return $out . '</ul>';
	}

	public static function secret_check() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( 'wc-settings' !== $page || 'checkout' !== $tab || ! in_array( $section, array( 'paystack', self::CARD ), true ) ) {
			return;
		}
		$st  = self::secret_status();
		$msg = array(
			'yes'      => 'Yes - a whsec_ secret is stored.',
			'no-empty' => 'No - nothing is stored (the field is empty in the database).',
			'no-other' => 'No - a value is stored but it does not start with whsec_.',
		);
		printf(
			'<div class="notice %1$s"><p><strong>NURA Desk check - Live Webhook Signing Secret stored:</strong> %2$s <em>(read-only; the value is never shown)</em></p></div>',
			'yes' === $st ? 'notice-success' : 'notice-warning',
			esc_html( $msg[ $st ] )
		);
		echo '<div class="notice notice-info">' . self::probe_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in probe_html().
	}

	private static function ready() {
		return class_exists( 'WC_Gateway_Paystack_One' ) && function_exists( 'get_woocommerce_currency' ) && 'NGN' !== get_woocommerce_currency();
	}

	/* ---- Gateways ---------------------------------------------------- */

	public static function gateways( $methods ) {
		if ( self::ready() && ! in_array( 'WC_Gateway_Paystack_One', (array) $methods, true ) ) {
			$methods[] = 'WC_Gateway_Paystack_One';
		}
		return $methods;
	}

	public static function channels( $channels, $id, $order = null ) {
		if ( self::CARD === $id ) {
			return array( 'card' );
		}
		if ( self::MAIN === $id && self::card_on() ) {
			return array( 'mobile_money' ); // Paystack's name for M-Pesa in Kenya.
		}
		return $channels;
	}

	/** Card choice is switched on in WooCommerce > Payments. */
	public static function card_on() {
		$s = get_option( 'woocommerce_' . self::CARD . '_settings', array() );
		return self::ready() && is_array( $s ) && isset( $s['enabled'] ) && 'yes' === $s['enabled'];
	}

	/** Shopper-facing labels for the main Paystack gateway (wp-admin keeps the saved values). */
	public static function main_labels( $v ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $v;
		}
		if ( ! is_array( $v ) || ! self::card_on() ) {
			return $v;
		}
		$v['title']       = 'M-Pesa (Paystack)';
		$v['description'] = 'Pay with M-Pesa through Paystack. No extra fee.';
		return $v;
	}

	/** One-time defaults for the card gateway (owner can change them in WooCommerce > Payments). */
	public static function seed() {
		if ( ! self::ready() || self::SEEDV === get_option( 'nurax_paystack_split_seed' ) ) {
			return;
		}
		$main = get_option( 'woocommerce_paystack_settings', array() );
		$main = is_array( $main ) ? $main : array();
		$s    = get_option( 'woocommerce_' . self::CARD . '_settings', array() );
		$s    = is_array( $s ) ? $s : array();
		if ( empty( $s['enabled'] ) ) {
			$s = array_merge( $s, array(
				'enabled'                    => 'yes',
				'title'                      => 'Card - Visa / Mastercard (Paystack)',
				'description'                => 'Pay by Visa or Mastercard through Paystack. A 2.9% card processing fee is added to your total.',
				'payment_page'               => ! empty( $main['payment_page'] ) ? $main['payment_page'] : 'inline',
				'payment_channels'           => array( 'card' ),
				'autocomplete_order'         => isset( $main['autocomplete_order'] ) ? $main['autocomplete_order'] : 'no',
				'remove_cancel_order_button' => 'no',
				'split_payment'              => 'no',
				'custom_metadata'            => 'no',
			) );
			update_option( 'woocommerce_' . self::CARD . '_settings', $s );
		}
		update_option( 'nurax_paystack_split_seed', self::SEEDV, false );
	}

	/* ---- Fee --------------------------------------------------------- */

	public static function fee_for( $base ) {
		return (float) round( max( 0, (float) $base ) * self::RATE );
	}

	private static function chosen() {
		return ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'chosen_payment_method' ) : '';
	}

	public static function cart_fee( $cart ) {
		if ( ! self::card_on() || self::CARD !== self::chosen() || ! is_object( $cart ) ) {
			return;
		}
		$base = (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax() + (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
		$fee  = self::fee_for( $base );
		if ( $fee > 0 ) {
			$cart->add_fee( self::fee_name(), $fee, false );
		}
	}

	public static function tag_fee( $item, $fee_key, $fee, $order ) {
		if ( isset( $fee->name ) && self::fee_name() === $fee->name ) {
			$item->add_meta_data( self::FEE_META, 1, true );
		}
	}

	public static function is_fee_item( $item ) {
		return $item && ( $item->get_meta( self::FEE_META ) || 0 === strpos( (string) $item->get_name(), 'Card processing fee' ) );
	}

	/** Total card fee on an order (used by NURA Desk Accounts). */
	public static function order_fee( $order ) {
		$t = 0.0;
		if ( $order && is_callable( array( $order, 'get_items' ) ) ) {
			foreach ( $order->get_items( 'fee' ) as $it ) {
				if ( self::is_fee_item( $it ) ) {
					$t += (float) $it->get_total();
				}
			}
		}
		return $t;
	}

	/** Store API checkout: the order carries the fee only when it is paid by card. */
	public static function guard_order( $order, $request ) {
		$pm = is_object( $request ) && isset( $request['payment_method'] ) ? sanitize_key( (string) $request['payment_method'] ) : '';
		if ( function_exists( 'WC' ) && WC()->session && '' !== $pm ) {
			WC()->session->set( 'chosen_payment_method', $pm );
		}
		$want    = self::card_on() && self::CARD === $pm;
		$have    = array();
		foreach ( $order->get_items( 'fee' ) as $iid => $it ) {
			if ( self::is_fee_item( $it ) ) {
				$have[ $iid ] = $it;
			}
		}
		$changed = false;
		if ( ! $want && $have ) {
			foreach ( array_keys( $have ) as $iid ) {
				$order->remove_item( $iid );
			}
			$changed = true;
		} elseif ( $want ) {
			$base = (float) $order->get_total() - self::order_fee( $order );
			$fee  = self::fee_for( $base );
			$cur  = self::order_fee( $order );
			if ( abs( $cur - $fee ) >= 0.5 || count( $have ) !== 1 ) {
				foreach ( array_keys( $have ) as $iid ) {
					$order->remove_item( $iid );
				}
				if ( $fee > 0 ) {
					$it = new WC_Order_Item_Fee();
					$it->set_name( self::fee_name() );
					$it->set_amount( $fee );
					$it->set_total( $fee );
					$it->set_tax_status( 'none' );
					$it->add_meta_data( self::FEE_META, 1, true );
					$order->add_item( $it );
				}
				$changed = true;
			}
		}
		if ( $changed ) {
			$order->calculate_totals( false );
		}
	}

	/* ---- Block checkout sync ------------------------------------------ */

	public static function store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback( array(
			'namespace' => self::NS,
			'callback'  => function ( $data ) {
				$m = is_array( $data ) && isset( $data['method'] ) ? sanitize_key( (string) $data['method'] ) : '';
				if ( '' !== $m && strlen( $m ) <= 60 && function_exists( 'WC' ) && WC()->session ) {
					WC()->session->set( 'chosen_payment_method', $m );
				}
			},
		) );
	}

	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! self::card_on() ) {
			return;
		}
		$js = '(function(){var last=null,busy=false;'
			. 'function blocks(){if(!window.wp||!wp.data||!window.wc||!wc.blocksCheckout||!wc.blocksCheckout.extensionCartUpdate)return;'
			. 'wp.data.subscribe(function(){var s=wp.data.select("wc/store/payment");if(!s||!s.getActivePaymentMethod)return;var m=s.getActivePaymentMethod();if(!m||m===last||busy)return;last=m;busy=true;'
			. 'Promise.resolve(wc.blocksCheckout.extensionCartUpdate({namespace:"' . self::NS . '",data:{method:m}})).then(function(){busy=false;},function(){busy=false;});});}'
			. 'function classic(){if(!window.jQuery)return;jQuery(document.body).on("change","form.checkout input[name=payment_method]",function(){jQuery(document.body).trigger("update_checkout");});}'
			. 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){blocks();classic();});}else{blocks();classic();}})();';
		wp_register_script( 'nurax-card-fee', '', array(), NURAX_VERSION, true );
		wp_enqueue_script( 'nurax-card-fee' );
		wp_add_inline_script( 'nurax-card-fee', $js );
	}
}

NURAX_Paystack_Split::hooks();
