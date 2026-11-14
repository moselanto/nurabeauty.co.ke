<?php
/**
 * NURA WhatsApp Order pop-up (v1.43.0, redesigned v1.44.0).
 *
 * A polished "Order on WhatsApp" form, ported from the Tabarak Electronics order
 * funnel and restyled for NURA:
 *   - One modal rendered in wp_footer on every front-end page.
 *   - Triggers: single product page (under add to cart, uses the chosen
 *     variation), every shop / category product card, and "Order whole cart on
 *     WhatsApp" on the cart page. Every trigger is a real wa.me link, so it still
 *     works without JavaScript.
 *   - Kenyan phone validation, delivery area / pick-up, payment by M-Pesa
 *     Buy Goods Till or an M-Pesa prompt via Paystack (1.94.0; pay on delivery
 *     within Nairobi CBD only; paying at the shop is only
 *     offered for studio pick-up), optional M-Pesa code capture,
 *     order reference NURA-YYMMDD-XXXX and a cleanly formatted WhatsApp message.
 *   - Every order is saved as a lead (admin-ajax, no nonce so cached pages keep
 *     working; protected by a honeypot, a fill-time trap, link-spam filtering,
 *     length caps and a per-IP limit) and listed under
 *     WooCommerce > WhatsApp Orders.
 *
 * The destination number is the one already configured for NURA:
 * Settings > NURA Experience > "WhatsApp link or number", then the theme
 * Customizer "WhatsApp link", then NURA's public number. Filter:
 * nurax_whatsapp_number.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_WA_Order {

	const CPT       = 'nurax_wa_order';
	const META      = '_nwo';
	const STATUS    = '_nwo_status';
	const PAGE      = 'nurax-wa-orders';
	const STORE_KEY = 'nura_wa_order_contact';

	public function __construct() {
		if ( ! apply_filters( 'nurax_wa_order_enabled', true ) ) {
			return;
		}
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_action( 'wp_footer', array( $this, 'modal' ), 5 ); // Before footer scripts (priority 20) so the pop-up exists when its script runs.

		// Triggers.
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'product_button' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'card_button' ), 14 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'cart_button' ), 30 );

		// AJAX: save lead + variation options for product-card orders.
		add_action( 'wp_ajax_nurax_wa_order', array( $this, 'ajax_lead' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_order', array( $this, 'ajax_lead' ) );
		add_action( 'wp_ajax_nurax_wa_product', array( $this, 'ajax_product' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_product', array( $this, 'ajax_product' ) );
		add_action( 'wp_ajax_nurax_wa_ship', array( $this, 'ajax_ship' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_ship', array( $this, 'ajax_ship' ) );
		// 1.94.0: M-Pesa PIN prompt through Paystack (customer enters phone, approves on the handset).
		add_action( 'wp_ajax_nurax_wa_stk', array( $this, 'ajax_stk' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_stk', array( $this, 'ajax_stk' ) );
		add_action( 'wp_ajax_nurax_wa_stk_status', array( $this, 'ajax_stk_status' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_stk_status', array( $this, 'ajax_stk_status' ) );
		// 1.95.0: same M-Pesa prompt for the studio booking deposit (/book-appointment/).
		add_action( 'wp_ajax_nurax_bk_stk', array( $this, 'ajax_bk_stk' ) );
		add_action( 'wp_ajax_nopriv_nurax_bk_stk', array( $this, 'ajax_bk_stk' ) );
		add_action( 'admin_post_nurax_wa_fees', array( $this, 'save_fees' ) );
		add_action( 'admin_post_nurax_stk_log_clear', array( $this, 'log_clear' ) );

		// Admin list.
		add_action( 'admin_menu', array( $this, 'menu' ), 55 );
		add_action( 'admin_post_nurax_wa_order_status', array( $this, 'admin_update' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/** Store WhatsApp number as digits, reusing NURA's existing settings. */
	public static function whatsapp_number() {
		$raw = '';
		if ( class_exists( 'NURAX_Settings' ) ) {
			$raw = (string) NURAX_Settings::get( 'whatsapp', '' );
		}
		if ( '' === trim( $raw ) ) {
			$raw = (string) get_theme_mod( 'nura_whatsapp', '' );
		}
		$digits = (string) preg_replace( '/\D+/', '', $raw );
		if ( '' === $digits ) {
			$digits = '254714994898'; // NURA's public WhatsApp number (same fallback as the theme).
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = '254' . substr( $digits, 1 );
		}
		return (string) preg_replace( '/\D+/', '', (string) apply_filters( 'nurax_whatsapp_number', $digits ) );
	}

	/** Plain wa.me link with a short prefilled text (no-JS fallback). */
	private static function wa_link( $text ) {
		return 'https://wa.me/' . self::whatsapp_number() . '?text=' . rawurlencode( $text );
	}

	/** Normalise a Kenyan mobile number to 2547XXXXXXXX / 2541XXXXXXXX, or ''. */
	public static function normalise_phone( $raw ) {
		$d = (string) preg_replace( '/\D/', '', (string) $raw );
		if ( 10 === strlen( $d ) && '0' === $d[0] ) {
			$d = '254' . substr( $d, 1 );
		} elseif ( 9 === strlen( $d ) && in_array( $d[0], array( '7', '1' ), true ) ) {
			$d = '254' . $d;
		}
		return preg_match( '/^254[17]\d{8}$/', $d ) ? $d : '';
	}

	/** "KSh 12,500" */
	public static function money( $amount ) {
		return 'KSh ' . number_format( (float) $amount, 0, '.', ',' );
	}

	/* ------------------------------------------------------------------ */
	/* Payment (v1.79.0): M-Pesa Till for every order, shop pay on pick-up */
	/* ------------------------------------------------------------------ */

	const PAY_TILL = 'M-Pesa Till (pay now)';
	const PAY_SHOP = 'Pay at the shop on collection';
	const PAY_COD  = 'Pay on delivery (Nairobi CBD)'; // 1.94.0: owner - pay on delivery only within Nairobi CBD (cash or M-Pesa).

	/** M-Pesa Buy Goods till number. Constant NURAX_MPESA_TILL or filter nurax_mpesa_till. */
	public static function till() {
		$t = defined( 'NURAX_MPESA_TILL' ) ? (string) NURAX_MPESA_TILL : '1679269';
		return (string) preg_replace( '/\D+/', '', (string) apply_filters( 'nurax_mpesa_till', $t ) );
	}

	/** Business name M-Pesa shows for the till (filter nurax_mpesa_till_name). 1.107.1: owner confirmed the M-Pesa message reads NURA CROWN & BEAUTY. */
	public static function till_name() {
		return trim( wp_strip_all_tags( (string) apply_filters( 'nurax_mpesa_till_name', defined( 'NURAX_MPESA_TILL_NAME' ) ? (string) NURAX_MPESA_TILL_NAME : 'NURA CROWN & BEAUTY' ) ) );
	}

	/** Normalise an M-Pesa confirmation code (10 letters/digits, e.g. TJR4ABC12D), or ''. */
	public static function normalise_code( $raw ) {
		$c = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw ) );
		return ( preg_match( '/^[A-Z0-9]{10}$/', $c ) && preg_match( '/[A-Z]/', $c ) && preg_match( '/\d/', $c ) ) ? $c : '';
	}

	/** Display price (incl./excl. tax as the shop shows it). */
	private static function display_price( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return 0.0;
		}
		if ( $product->is_type( 'variable' ) ) {
			return (float) $product->get_variation_price( 'min', true );
		}
		return (float) wc_get_price_to_display( $product );
	}

	/** Pick-up point text for the "Pick-up" option. */
	private static function pickup_text() {
		$addr = function_exists( 'nura_opt' ) ? (string) nura_opt( 'nura_address' ) : (string) get_theme_mod( 'nura_address', '' );
		$addr = trim( wp_strip_all_tags( $addr ) );
		$txt  = '' !== $addr ? sprintf( /* translators: %s: studio address */ __( 'Pick-up at NURA studio (%s)', 'nura-experience' ), $addr ) : __( 'Pick-up at NURA studio, Nairobi CBD', 'nura-experience' );
		return (string) apply_filters( 'nurax_wa_order_pickup_text', $txt );
	}

	/** Delivery areas: Nairobi estates + major towns. */
	/** Kenya's 47 counties (Nairobi first, the rest A-Z). */
	private static function counties() {
		$list = array( 'Nairobi', 'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu', 'Garissa', 'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia', 'Lamu', 'Machakos', 'Makueni', 'Mandera', 'Marsabit', 'Meru', 'Migori', 'Mombasa', "Murang'a", 'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri', 'Samburu', 'Siaya', 'Taita-Taveta', 'Tana River', 'Tharaka-Nithi', 'Trans Nzoia', 'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot' );
		return apply_filters( 'nurax_wa_order_counties', $list );
	}

	private static function payments() {
		// 1.94.0: delivered orders are paid by M-Pesa before dispatch, except
		// Nairobi CBD deliveries (pay on delivery, cash or M-Pesa); paying at the
		// shop is for studio pick-up.
		return apply_filters( 'nurax_wa_order_payments', array(
			self::PAY_TILL,
			self::PAY_COD,
			self::PAY_SHOP,
		) );
	}

	private static function statuses() {
		return array(
			'new'       => __( 'New', 'nura-experience' ),
			'confirmed' => __( 'Confirmed', 'nura-experience' ),
			'delivered' => __( 'Delivered', 'nura-experience' ),
			'cancelled' => __( 'Cancelled', 'nura-experience' ),
		);
	}

	private static function wa_icon() {
		return '<svg class="nwo-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>';
	}

	/** JSON payload describing one product for the modal. */
	/** Pop-up payload for other modules (e.g. the NURA Stylist chat's Order on WhatsApp button). */
	public static function payload_for( $product ) {
		return $product instanceof WC_Product ? self::product_payload( $product ) : array();
	}

	private static function product_payload( $product ) {
		$img = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
		return array(
			'id'       => $product->get_id(),
			'name'     => wp_strip_all_tags( $product->get_name() ),
			'price'    => self::display_price( $product ),
			'img'      => $img ? $img : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' ),
			'url'      => get_permalink( $product->get_id() ),
			'variable' => $product->is_type( 'variable' ) ? 1 : 0,
			'regular'  => self::regular_price( $product ),
			'cat'      => self::category_name( $product ),
		);
	}

	/** Regular (crossed-out) price when the product is on sale, else 0. */
	private static function regular_price( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
			return 0.0;
		}
		if ( $product->is_type( 'variable' ) ) {
			return (float) $product->get_variation_regular_price( 'min', true );
		}
		$reg = $product->get_regular_price();
		if ( '' === $reg ) {
			return 0.0;
		}
		return (float) wc_get_price_to_display( $product, array( 'price' => $reg ) );
	}

	/** First real product category name, shown above the product in the pop-up. */
	private static function category_name( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		foreach ( $terms as $term ) {
			if ( 'uncategorized' !== $term->slug ) {
				return wp_strip_all_tags( $term->name );
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Storage                                                             */
	/* ------------------------------------------------------------------ */

	public function register_cpt() {
		register_post_type( self::CPT, array(
			'label'               => __( 'WhatsApp Orders', 'nura-experience' ),
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Front end                                                           */
	/* ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ */
	/* Delivery fee quote (v1.56.0)                                        */
	/* ------------------------------------------------------------------ */

	const FEES = 'nurax_wo_fees';

	/** Manual fallback fees (used only where WooCommerce zones give no price). */
	private static function fee_table() {
		$d = array( 'nairobi' => '', 'nearby' => '', 'rest' => '', 'free_over' => '' );
		$o = get_option( self::FEES, array() );
		return array_merge( $d, is_array( $o ) ? $o : array() );
	}

	private static function nearby_counties() {
		return (array) apply_filters( 'nurax_wa_order_nearby_counties', array( 'Kiambu', 'Kajiado', 'Machakos', "Murang'a" ) );
	}

	private static function eta( $county ) {
		if ( 'Nairobi' === $county ) {
			return __( 'Same-day delivery when ordered before 5 PM', 'nura-experience' );
		}
		if ( in_array( $county, self::nearby_counties(), true ) ) {
			return __( 'Next-day delivery', 'nura-experience' );
		}
		return __( 'Dispatched the same day you pay; arrival set by the shuttle or courier', 'nura-experience' );
	}

	/** WooCommerce state code for a county name (KE01...KE47). */
	private static function state_code( $county ) {
		if ( ! function_exists( 'WC' ) || ! WC() || empty( WC()->countries ) ) {
			return '';
		}
		$norm = static function ( $s ) {
			return preg_replace( '/[^a-z]/', '', str_replace( 'county', '', strtolower( remove_accents( (string) $s ) ) ) );
		};
		$want = $norm( $county );
		foreach ( (array) WC()->countries->get_states( 'KE' ) as $code => $name ) {
			if ( $norm( $name ) === $want ) {
				return (string) $code;
			}
		}
		return '';
	}

	/** Price from the shop's own WooCommerce shipping zones, or null. */
	private static function wc_quote( $county, $sub ) {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return null;
		}
		$package = array(
			'contents'        => array(),
			'contents_cost'   => $sub,
			'cart_subtotal'   => $sub,
			'applied_coupons' => array(),
			'user'            => array( 'ID' => get_current_user_id() ),
			'destination'     => array(
				'country'   => 'KE',
				'state'     => self::state_code( $county ),
				'postcode'  => '',
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
		);
		$best = null;
		$free = false;
		$over = 0;
		try {
			$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
			if ( ! $zone ) {
				return null;
			}
			foreach ( $zone->get_shipping_methods( true ) as $m ) {
				$id = isset( $m->id ) ? (string) $m->id : '';
				if ( in_array( $id, array( 'local_pickup', 'pickup_location' ), true ) ) {
					continue;
				}
				if ( 'free_shipping' === $id ) {
					$req = (string) $m->get_option( 'requires', '' );
					$min = (float) $m->get_option( 'min_amount', 0 );
					if ( '' === $req ) {
						$free = true;
					} elseif ( in_array( $req, array( 'min_amount', 'either' ), true ) ) {
						if ( $sub >= $min ) {
							$free = true;
						} elseif ( $min > 0 ) {
							$over = $over > 0 ? min( $over, $min ) : $min;
						}
					}
					continue;
				}
				$cost = null;
				$raw  = trim( (string) $m->get_option( 'cost', '' ) );
				if ( '' !== $raw && is_numeric( $raw ) ) {
					$cost = (float) $raw;
				} elseif ( method_exists( $m, 'get_rates_for_package' ) ) {
					foreach ( (array) $m->get_rates_for_package( $package ) as $r ) {
						if ( is_object( $r ) && method_exists( $r, 'get_cost' ) ) {
							$c    = (float) $r->get_cost();
							$cost = null === $cost ? $c : min( $cost, $c );
						}
					}
				}
				if ( null !== $cost ) {
					$best = null === $best ? $cost : min( $best, $cost );
				}
			}
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( $free ) {
			return array( 'known' => true, 'fee' => 0, 'free_over' => 0 );
		}
		if ( null === $best ) {
			return null;
		}
		return array( 'known' => true, 'fee' => max( 0, $best ), 'free_over' => $over );
	}

	/** Price from the manual fallback table, or null. */
	private static function table_quote( $county, $sub ) {
		$t   = self::fee_table();
		$key = 'Nairobi' === $county ? 'nairobi' : ( in_array( $county, self::nearby_counties(), true ) ? 'nearby' : 'rest' );
		if ( '' === (string) $t[ $key ] ) {
			return null;
		}
		$fee  = max( 0, (float) $t[ $key ] );
		$over = '' === (string) $t['free_over'] ? 0 : max( 0, (float) $t['free_over'] );
		if ( $over > 0 && $sub >= $over ) {
			$fee = 0;
		}
		return array( 'known' => true, 'fee' => $fee, 'free_over' => $fee > 0 ? $over : 0 );
	}

	/** Delivery quote for a county + subtotal. */
	public static function ship_quote( $county, $sub ) {
		$q = self::wc_quote( $county, $sub );
		if ( null === $q ) {
			$q = self::table_quote( $county, $sub );
		}
		if ( null === $q ) {
			$q = array( 'known' => false, 'fee' => 0, 'free_over' => 0 );
		}
		$q['county'] = $county;
		$q['eta']    = self::eta( $county );
		$q['free']   = $q['known'] && $q['fee'] <= 0;
		return apply_filters( 'nurax_wa_order_shipping', $q, $county, $sub );
	}

	/** AJAX: delivery quote (public, read-only, no nonce so cached pages work). */
	public function ajax_ship() {
		$county = isset( $_GET['county'] ) ? sanitize_text_field( wp_unslash( $_GET['county'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sub    = isset( $_GET['subtotal'] ) ? min( 10000000, max( 0, (float) $_GET['subtotal'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $county, self::counties(), true ) ) {
			wp_send_json_error( array( 'message' => 'county' ), 400 );
		}
		wp_send_json_success( self::ship_quote( $county, $sub ) );
	}

	/** Admin: fallback delivery fees box on the WhatsApp Orders page. */
	private function fees_box() {
		$t      = self::fee_table();
		$fields = array(
			'nairobi'   => __( 'Nairobi', 'nura-experience' ),
			'nearby'    => implode( ', ', self::nearby_counties() ),
			'rest'      => __( 'Rest of Kenya', 'nura-experience' ),
			'free_over' => __( 'Free delivery on orders from', 'nura-experience' ),
		);
		$open = isset( $_GET['nwo_fees'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<details style="max-width:760px;margin:14px 0;padding:10px 16px;background:#fff;border:1px solid #dcdcde;border-radius:6px"' . ( $open ? ' open' : '' ) . '>';
		echo '<summary style="cursor:pointer;font-weight:600">' . esc_html__( 'Delivery fees shown in the order pop-up', 'nura-experience' ) . '</summary>';
		if ( $open ) {
			echo '<p style="color:#008a20">' . esc_html__( 'Delivery fees saved.', 'nura-experience' ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'The pop-up uses your WooCommerce shipping zones first (WooCommerce > Settings > Shipping). These amounts are only used for counties your zones do not price. Leave a box empty to show "Confirmed on WhatsApp" instead.', 'nura-experience' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nurax_wa_fees">';
		wp_nonce_field( 'nurax_wa_fees' );
		echo '<table class="form-table" role="presentation">';
		foreach ( $fields as $k => $label ) {
			echo '<tr><th scope="row"><label for="nwo-fee-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>KSh <input type="number" min="0" step="1" class="regular-text" style="max-width:140px" id="nwo-fee-' . esc_attr( $k ) . '" name="fees[' . esc_attr( $k ) . ']" value="' . esc_attr( $t[ $k ] ) . '"></td></tr>';
		}
		echo '</table>';
		submit_button( __( 'Save delivery fees', 'nura-experience' ), 'secondary' );
		echo '</form></details>';
	}

	public function save_fees() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_wa_fees' );
		$in  = isset( $_POST['fees'] ) && is_array( $_POST['fees'] ) ? wp_unslash( $_POST['fees'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$out = array();
		foreach ( array( 'nairobi', 'nearby', 'rest', 'free_over' ) as $k ) {
			$v         = isset( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : '';
			$out[ $k ] = '' === $v ? '' : (string) max( 0, (int) $v );
		}
		update_option( self::FEES, $out, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&nwo_fees=1' ) );
		exit;
	}

	public function assets() {
		if ( is_admin() ) {
			return;
		}
		wp_enqueue_style( 'nurax-wa-order', NURAX_URL . 'assets/css/nura-wa-order.css', array(), NURAX_VERSION );
		wp_enqueue_script( 'nurax-wa-order', NURAX_URL . 'assets/js/nura-wa-order.js', array(), NURAX_VERSION, true );
		wp_localize_script( 'nurax-wa-order', 'NURAX_WO', array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'wa'       => self::whatsapp_number(),
			'pickup'   => self::pickup_text(),
			'brand'    => 'NURA Beauty',
			'store'    => self::STORE_KEY,
			'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'KES',
			'till'     => self::till(),
			'tillName' => self::till_name(),
			'payTill'  => self::PAY_TILL,
			'payShop'  => self::PAY_SHOP,
			'payCod'   => self::PAY_COD,
			'stk'      => '' !== self::paystack_secret() ? 1 : 0,
		) );
	}

	/** Single product page: under add to cart. */
	public function product_button() {
		global $product;
		if ( ! $product instanceof WC_Product || ! function_exists( 'is_product' ) ) {
			return;
		}
		$data = self::product_payload( $product );
		$text = sprintf( /* translators: 1: product name, 2: URL */ __( "Hi NURA, I'd like to order the %1\$s (%2\$s). Is it available?", 'nura-experience' ), $data['name'], $data['url'] );
		printf(
			'<div class="nwo-pdp"><a class="nwo-btn nwo-btn--pdp" href="%1$s" target="_blank" rel="noopener nofollow" data-nwo-open="product" data-nwo="%2$s">%3$s<span>%4$s</span></a><p class="nwo-pdp__hint" data-nwo-hint role="status" aria-live="polite" hidden></p></div>',
			esc_url( self::wa_link( $text ) ),
			esc_attr( wp_json_encode( $data ) ),
			self::wa_icon(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Order on WhatsApp', 'nura-experience' )
		);
	}

	/** Shop / category product card. */
	public function card_button() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$data = self::product_payload( $product );
		$text = sprintf( /* translators: 1: product name, 2: URL */ __( "Hi NURA, I'd like to order the %1\$s (%2\$s). Is it available?", 'nura-experience' ), $data['name'], $data['url'] );
		printf(
			'<a class="nwo-btn nwo-btn--card" href="%1$s" target="_blank" rel="noopener nofollow" data-nwo-open="card" data-nwo="%2$s" aria-label="%3$s">%4$s<span>%5$s</span></a>',
			esc_url( self::wa_link( $text ) ),
			esc_attr( wp_json_encode( $data ) ),
			esc_attr( sprintf( /* translators: %s: product name */ __( 'Order %s on WhatsApp', 'nura-experience' ), $data['name'] ) ),
			self::wa_icon(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Order on WhatsApp', 'nura-experience' )
		);
	}

	/** Cart page: order the whole cart. */
	public function cart_button() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$lines = array();
		$plain = array();
		$n     = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			$p = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $p instanceof WC_Product ) {
				continue;
			}
			$qty    = max( 1, (int) $item['quantity'] );
			$unit   = (float) wc_get_price_to_display( $p );
			$option = '';
			if ( ! empty( $item['variation'] ) && is_array( $item['variation'] ) && function_exists( 'wc_get_formatted_variation' ) ) {
				$option = str_replace( ', ', ' / ', wp_strip_all_tags( wc_get_formatted_variation( $item['variation'], true, false, false ) ) );
			}
			$parent  = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
			$name    = wp_strip_all_tags( $parent ? $parent->get_name() : $p->get_name() );
			$lines[] = array(
				'id'     => $p->get_id(),
				'name'   => $name,
				'option' => $option,
				'qty'    => $qty,
				'price'  => $unit,
				'line'   => $unit * $qty,
			);
			$n++;
			$plain[] = $n . '. ' . $name . ( '' !== $option ? ' - ' . $option : '' ) . ' x ' . $qty . ' = ' . self::money( $unit * $qty );
		}
		if ( empty( $lines ) ) {
			return;
		}
		$total = (float) WC()->cart->get_displayed_subtotal();
		$disc  = (float) WC()->cart->get_discount_total();
		if ( $disc > 0 ) {
			$total -= WC()->cart->display_prices_including_tax() ? $disc + (float) WC()->cart->get_discount_tax() : $disc;
		}
		$total = max( 0, $total );
		$text  = __( 'Hi NURA, I would like to order:', 'nura-experience' ) . "\n" . implode( "\n", $plain ) . "\n" . __( 'Subtotal (delivery extra):', 'nura-experience' ) . ' ' . self::money( $total );
		$data  = array(
			'items' => $lines,
			'total' => $total,
			'url'   => wc_get_cart_url(),
		);
		printf(
			'<a class="nwo-btn nwo-btn--cart" href="%1$s" target="_blank" rel="noopener nofollow" data-nwo-open="cart" data-nwo="%2$s">%3$s<span>%4$s</span></a>',
			esc_url( self::wa_link( $text ) ),
			esc_attr( wp_json_encode( $data ) ),
			self::wa_icon(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Order whole cart on WhatsApp', 'nura-experience' )
		);
	}

	/** The order modal, rendered once per page. */
	public function modal() {
		if ( is_admin() ) {
			return;
		}
		$steps = array(
			__( 'Your details', 'nura-experience' ),
			__( 'Pay & send', 'nura-experience' ),
			__( 'We confirm & dispatch', 'nura-experience' ),
		);
		$till      = self::till();
		$till_name = self::till_name();
		$pay_notes = array(
			self::PAY_TILL => sprintf( /* translators: %s: till number */ __( 'Buy Goods Till %s - we dispatch once payment is confirmed', 'nura-experience' ), $till ),
			self::PAY_SHOP => __( 'Cash or M-Pesa at our Stanbank House studio (2nd Floor, Room 20) when you collect', 'nura-experience' ),
			self::PAY_COD  => __( 'Cash or M-Pesa to our rider when your order arrives - Nairobi CBD only', 'nura-experience' ),
		);
		$stk_on = self::paystack_secret() !== '';
		?>
		<div class="nwo" id="nwo-modal" data-nwo-modal hidden>
			<div class="nwo__overlay" data-nwo-close></div>
			<div class="nwo__panel" role="dialog" aria-modal="true" aria-labelledby="nwo-title" tabindex="-1">
				<div class="nwo__top">
					<div class="nwo__head">
						<div class="nwo__headtxt">
							<p class="nwo__eyebrow"><?php echo self::wa_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Fast WhatsApp order', 'nura-experience' ); ?></span></p>
							<h2 class="nwo__title" id="nwo-title" data-nwo-title><?php esc_html_e( 'Complete your order', 'nura-experience' ); ?></h2>
						</div>
						<button type="button" class="nwo__close" data-nwo-close aria-label="<?php esc_attr_e( 'Close', 'nura-experience' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>
					</div>
					<ol class="nwo-progress" aria-label="<?php esc_attr_e( 'Order progress', 'nura-experience' ); ?>">
						<?php foreach ( $steps as $i => $label ) : ?>
							<li class="nwo-progress__step<?php echo 0 === $i ? ' is-active' : ''; ?>" data-nwo-step="<?php echo (int) $i; ?>"><span class="nwo-progress__dot"><?php echo (int) ( $i + 1 ); ?></span><span class="nwo-progress__label"><?php echo esc_html( $label ); ?></span></li>
						<?php endforeach; ?>
					</ol>
				</div>

				<form class="nwo-form" data-nwo-form novalidate>
					<div class="nwo-body">
						<div class="nwo-view" data-nwo-view="0"><?php // 1.108.0: step 1 = product, contact, delivery. ?>
						<div class="nwo-card">
							<div class="nwo-sum" data-nwo-sum></div>
							<div class="nwo-opts" data-nwo-opts hidden></div>
							<div class="nwo-qtyrow" data-nwo-qtyrow>
								<div class="nwo-qty__ctrl" role="group" aria-label="<?php esc_attr_e( 'Quantity', 'nura-experience' ); ?>">
									<button type="button" class="nwo-qty__btn" data-nwo-qty="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'nura-experience' ); ?>">&minus;</button>
									<input type="number" name="qty" class="nwo-qty__input" value="1" min="1" max="20" inputmode="numeric" aria-label="<?php esc_attr_e( 'Quantity', 'nura-experience' ); ?>">
									<button type="button" class="nwo-qty__btn" data-nwo-qty="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'nura-experience' ); ?>">+</button>
								</div>
								<div class="nwo-total"><span><?php esc_html_e( 'Subtotal', 'nura-experience' ); ?></span><strong data-nwo-total aria-live="polite"></strong></div>
							</div>
						</div>

						<h3 class="nwo-sec"><span aria-hidden="true">1</span><?php esc_html_e( 'Contact details', 'nura-experience' ); ?></h3>
						<div class="nwo-f">
							<label for="nwo-name"><?php esc_html_e( 'Full name', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
							<input id="nwo-name" name="name" type="text" autocomplete="name" maxlength="80" placeholder="<?php esc_attr_e( 'e.g. Jane Wanjiku', 'nura-experience' ); ?>" required aria-describedby="nwo-err-name">
							<p class="nwo-err" id="nwo-err-name"></p>
						</div>

						<div class="nwo-f">
							<label for="nwo-phone"><?php esc_html_e( 'Phone / WhatsApp number', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
							<div class="nwo-phone">
								<span class="nwo-phone__cc" aria-hidden="true">+254</span>
								<input id="nwo-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="16" placeholder="0712 345 678" required aria-describedby="nwo-err-phone">
							</div>
							<p class="nwo-err" id="nwo-err-phone"></p>
						</div>

						<h3 class="nwo-sec"><span aria-hidden="true">2</span><?php esc_html_e( 'Delivery', 'nura-experience' ); ?></h3>
						<div class="nwo-row2">
							<div class="nwo-f">
								<label for="nwo-location"><?php esc_html_e( 'County', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
								<div class="nwo-select">
									<select id="nwo-location" name="location" autocomplete="address-level1" required aria-describedby="nwo-err-location">
										<option value=""><?php esc_html_e( 'Select county', 'nura-experience' ); ?></option>
										<?php foreach ( self::counties() as $county ) : ?>
											<option value="<?php echo esc_attr( $county ); ?>"><?php echo esc_html( $county ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<p class="nwo-err" id="nwo-err-location"></p>
							</div>
							<div class="nwo-f">
								<label for="nwo-town"><?php esc_html_e( 'Town', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
								<input id="nwo-town" name="town" type="text" autocomplete="address-level2" maxlength="60" placeholder="<?php esc_attr_e( 'e.g. Westlands, Thika', 'nura-experience' ); ?>" required aria-describedby="nwo-err-town">
								<p class="nwo-err" id="nwo-err-town"></p>
							</div>
						</div>

						<fieldset class="nwo-f nwo-choice">
							<legend><?php esc_html_e( 'How do you want to receive it?', 'nura-experience' ); ?></legend>
							<div class="nwo-cards nwo-cards--2">
								<label class="nwo-opt"><input type="radio" name="fulfil" value="delivery" checked><span class="nwo-opt__box"><span class="nwo-opt__ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="7" cy="17.5" r="1.7" fill="#fff" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="17.5" r="1.7" fill="#fff" stroke="currentColor" stroke-width="1.6"/></svg></span><span class="nwo-opt__txt"><strong><?php esc_html_e( 'Delivery', 'nura-experience' ); ?></strong><small><?php esc_html_e( 'Same day in Nairobi, same-day dispatch countrywide', 'nura-experience' ); ?></small></span><span class="nwo-opt__tick" aria-hidden="true"></span></span></label>
								<label class="nwo-opt"><input type="radio" name="fulfil" value="pickup"><span class="nwo-opt__box"><span class="nwo-opt__ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 10l1.5-5h13L20 10M4 10v10h16V10M4 10h16M10 20v-5h4v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span><span class="nwo-opt__txt"><strong><?php esc_html_e( 'Pick up', 'nura-experience' ); ?></strong><small><?php esc_html_e( 'Collect at our Nairobi CBD studio', 'nura-experience' ); ?></small></span><span class="nwo-opt__tick" aria-hidden="true"></span></span></label>
							</div>
						</fieldset>

						<div data-nwo-delivery>
							<div class="nwo-f">
								<label for="nwo-area"><?php esc_html_e( 'Estate, street or landmark (optional)', 'nura-experience' ); ?></label>
								<input id="nwo-area" name="area" type="text" autocomplete="street-address" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Kilimani, Argwings Kodhek Rd, near Yaya Centre', 'nura-experience' ); ?>">
							</div>
							<label class="nwo-check" data-nwo-cbdwrap hidden><input type="checkbox" name="cbd" value="1"><span><strong><?php esc_html_e( 'Deliver within Nairobi CBD', 'nura-experience' ); ?></strong><small><?php esc_html_e( 'Pay on delivery (cash or M-Pesa) is available in the CBD only.', 'nura-experience' ); ?></small></span></label>
						</div>
						<p class="nwo-pickup" data-nwo-pickup hidden><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="9.5" r="2.4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg><span><?php echo esc_html( self::pickup_text() ); ?></span></p>
						<div class="nwo-ship" data-nwo-ship aria-live="polite" hidden></div>

						</div>
						<div class="nwo-view" data-nwo-view="1" hidden><?php // 1.108.0: step 2 = payment and send. ?>
						<h3 class="nwo-sec"><span aria-hidden="true">3</span><?php esc_html_e( 'Payment', 'nura-experience' ); ?></h3>
						<fieldset class="nwo-f nwo-choice">
							<legend><?php esc_html_e( 'How will you pay?', 'nura-experience' ); ?></legend>
							<div class="nwo-cards">
								<?php foreach ( self::payments() as $i => $pay ) : ?>
									<label class="nwo-opt nwo-opt--row"<?php echo self::PAY_SHOP === $pay ? ' data-nwo-shoponly hidden' : ( self::PAY_COD === $pay ? ' data-nwo-cbdonly hidden' : '' ); ?>><input type="radio" name="payment" value="<?php echo esc_attr( $pay ); ?>"<?php checked( 0, $i ); ?>><span class="nwo-opt__box"><span class="nwo-opt__txt"><strong><?php echo esc_html( $pay ); ?></strong><?php if ( isset( $pay_notes[ $pay ] ) ) : ?><small><?php echo esc_html( $pay_notes[ $pay ] ); ?></small><?php endif; ?></span><span class="nwo-opt__tick" aria-hidden="true"></span></span></label>
								<?php endforeach; ?>
							</div>
							<p class="nwo-paynote" data-nwo-paynote><?php esc_html_e( 'Pay on delivery (cash or M-Pesa) is available within Nairobi CBD only. All other deliveries are paid in full, delivery fee included, before dispatch. Paying in person is also available when you collect from our studio.', 'nura-experience' ); ?></p>
						</fieldset>

						<div class="nwo-paybox" data-nwo-paybox>
							<?php if ( $stk_on ) : ?>
							<div class="nwo-stk" data-nwo-stk>
								<p class="nwo-stk__title"><?php esc_html_e( 'Pay with an M-Pesa prompt', 'nura-experience' ); ?></p>
								<p class="nwo-help"><?php esc_html_e( 'Enter your M-Pesa number. You will get a prompt on your phone: enter your PIN to pay. No till number or code to copy.', 'nura-experience' ); ?></p>
								<div class="nwo-stk__row">
									<label class="screen-reader-text" for="nwo-stk_phone"><?php esc_html_e( 'M-Pesa phone number', 'nura-experience' ); ?></label>
									<input id="nwo-stk_phone" name="stk_phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="16" placeholder="<?php esc_attr_e( 'e.g. 0712 345 678', 'nura-experience' ); ?>">
									<button type="button" class="nwo-stk__btn" data-nwo-stkgo><?php esc_html_e( 'Send M-Pesa prompt', 'nura-experience' ); ?></button>
								</div>
								<p class="nwo-stk__status" data-nwo-stkstatus aria-live="polite" hidden></p>
							</div>
							<p class="nwo-stk__or" data-nwo-stkor><?php esc_html_e( 'Or pay manually with the till:', 'nura-experience' ); ?></p>
							<?php endif; ?>
							<div class="nwo-paybox__head">
								<span class="nwo-paybox__badge"><?php esc_html_e( 'Lipa na M-Pesa', 'nura-experience' ); ?></span>
								<span class="nwo-paybox__kind"><?php esc_html_e( 'Buy Goods and Services', 'nura-experience' ); ?></span>
							</div>
							<div class="nwo-paybox__till">
								<div>
									<span class="nwo-paybox__lbl"><?php esc_html_e( 'Till number', 'nura-experience' ); ?></span>
									<strong class="nwo-paybox__num" data-nwo-till><?php echo esc_html( $till ); ?></strong>
									<?php if ( '' !== $till_name ) : ?><span class="nwo-paybox__name"><?php echo esc_html( $till_name ); ?></span><?php endif; ?>
								</div>
								<button type="button" class="nwo-paybox__copy" data-nwo-copy="<?php echo esc_attr( $till ); ?>"><?php esc_html_e( 'Copy', 'nura-experience' ); ?></button>
							</div>
							<div class="nwo-paybox__amt"><span><?php esc_html_e( 'Amount to pay', 'nura-experience' ); ?></span><strong data-nwo-payamt></strong></div>
							<ol class="nwo-paybox__steps">
								<li><?php esc_html_e( 'Open M-Pesa and choose Lipa na M-Pesa', 'nura-experience' ); ?></li>
								<li><?php esc_html_e( 'Select Buy Goods and Services', 'nura-experience' ); ?></li>
								<li><?php echo esc_html( sprintf( /* translators: %s: till number */ __( 'Enter Till number %s and the amount', 'nura-experience' ), $till ) ); ?></li>
								<?php if ( '' !== $till_name ) : ?><li><?php echo esc_html( sprintf( /* translators: %s: business name */ __( 'Check the name shows %s, then enter your PIN', 'nura-experience' ), $till_name ) ); ?></li><?php endif; ?>
								<li><?php esc_html_e( 'Enter your PIN and confirm', 'nura-experience' ); ?></li>
							</ol>
							<div class="nwo-f nwo-f--code">
								<label for="nwo-mpesa_code"><?php esc_html_e( 'M-Pesa confirmation code (if you have paid)', 'nura-experience' ); ?></label>
								<input id="nwo-mpesa_code" name="mpesa_code" type="text" maxlength="14" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?php esc_attr_e( 'e.g. TJR4ABC12D', 'nura-experience' ); ?>" aria-describedby="nwo-err-mpesa_code nwo-code-help">
								<p class="nwo-help" id="nwo-code-help"><?php esc_html_e( 'You can also pay after we confirm stock and send the M-Pesa message on WhatsApp. Orders are dispatched only after payment is verified.', 'nura-experience' ); ?></p>
								<p class="nwo-err" id="nwo-err-mpesa_code"></p>
							</div>
						</div>

						<div class="nwo-f">
							<label for="nwo-note"><?php esc_html_e( 'Anything else? (optional)', 'nura-experience' ); ?></label>
							<textarea id="nwo-note" name="note" rows="2" maxlength="300" placeholder="<?php esc_attr_e( 'Preferred delivery time, colour question, styling request...', 'nura-experience' ); ?>"></textarea>
						</div>

						<div class="nwo-hp" aria-hidden="true">
							<label for="nwo-website">Website</label>
							<input id="nwo-website" name="website" type="text" tabindex="-1" autocomplete="off">
						</div>

						<ul class="nwo-trust">
							<li><?php esc_html_e( 'Secure M-Pesa Till', 'nura-experience' ); ?></li>
							<li><?php esc_html_e( 'Dispatched once payment is verified', 'nura-experience' ); ?></li>
							<li><?php esc_html_e( '48-hour returns', 'nura-experience' ); ?></li>
						</ul>
						</div>
						<button type="button" class="nwo-more" data-nwo-more hidden><?php esc_html_e( 'More below', 'nura-experience' ); ?> <span aria-hidden="true">&#8595;</span></button>
					</div>

					<div class="nwo-foot">
						<p class="nwo-formerr" data-nwo-formerr role="alert" hidden></p>
						<dl class="nwo-bill" hidden>
							<div><dt><?php esc_html_e( 'Subtotal', 'nura-experience' ); ?></dt><dd data-nwo-bsub></dd></div>
							<div><dt><?php esc_html_e( 'Delivery', 'nura-experience' ); ?></dt><dd data-nwo-bship><?php esc_html_e( 'Select your county', 'nura-experience' ); ?></dd></div>
						</dl>
						<div class="nwo-foot__row">
							<div class="nwo-foot__total"><span><?php esc_html_e( 'Total', 'nura-experience' ); ?></span><strong data-nwo-foottotal aria-live="polite"></strong></div>
							<button type="button" class="nwo-submit nwo-next" data-nwo-next><span><?php esc_html_e( 'Continue to payment', 'nura-experience' ); ?></span><span class="nwo-arrow" aria-hidden="true">&#8594;</span></button>
							<button type="submit" class="nwo-submit" data-nwo-send hidden><?php echo self::wa_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Send order on WhatsApp', 'nura-experience' ); ?></span></button>
						</div>
						<button type="button" class="nwo-back" data-nwo-back hidden>&#8592; <?php esc_html_e( 'Back to your details', 'nura-experience' ); ?></button>
						<p class="nwo-small" hidden><?php esc_html_e( 'WhatsApp opens with your order and payment details ready. Just tap send.', 'nura-experience' ); ?></p>
					</div>
				</form>

				<div class="nwo-done" data-nwo-done hidden>
					<div class="nwo-done__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.2 4.2L19 7"/></svg></div>
					<h3 class="nwo-done__title" tabindex="-1"><?php esc_html_e( 'Almost done - tap send on WhatsApp', 'nura-experience' ); ?></h3>
					<p class="nwo-done__text"><?php esc_html_e( 'WhatsApp has opened with your order already written. Tap send and a NURA stylist will confirm stock and your total within minutes.', 'nura-experience' ); ?></p>
					<p class="nwo-done__ref"><?php esc_html_e( 'Your order reference', 'nura-experience' ); ?> <strong data-nwo-ref></strong></p>
					<div class="nwo-done__pay" data-nwo-donepay hidden>
						<p class="nwo-done__paytitle"><?php esc_html_e( 'Complete your payment', 'nura-experience' ); ?></p>
						<p><?php echo esc_html( sprintf( /* translators: %s: till number */ __( 'Lipa na M-Pesa > Buy Goods and Services > Till %s', 'nura-experience' ), $till ) ); ?></p>
						<p><?php esc_html_e( 'Amount:', 'nura-experience' ); ?> <strong data-nwo-doneamt></strong></p>
						<p class="nwo-help"><?php esc_html_e( 'Send the M-Pesa confirmation message in the same WhatsApp chat. We dispatch as soon as it is verified.', 'nura-experience' ); ?></p>
					</div>
					<a class="nwo-submit nwo-done__wa" data-nwo-again href="#" target="_blank" rel="noopener nofollow"><?php echo self::wa_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Open WhatsApp again', 'nura-experience' ); ?></span></a>
					<button type="button" class="nwo-done__close" data-nwo-close><?php esc_html_e( 'Continue shopping', 'nura-experience' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Variation options for a variable product (used when ordering from a
	 * product card). Read-only public data, cached; no nonce so cached pages work.
	 */
	public function ajax_product() {
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product = ( $id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $id ) : null;
		if ( ! $product || ! $product->is_type( 'variable' ) || 'publish' !== $product->get_status() ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}
		$mod  = $product->get_date_modified();
		$ckey = 'nurax_wo_p_' . $id . '_' . ( $mod ? $mod->getTimestamp() : 0 );
		$hit  = get_transient( $ckey );
		if ( is_array( $hit ) ) {
			wp_send_json_success( $hit );
		}
		$attrs = array();
		foreach ( $product->get_variation_attributes() as $attr => $options ) {
			$opts = array();
			$tax  = taxonomy_exists( $attr );
			if ( $tax ) {
				$terms = wc_get_product_terms( $id, $attr, array( 'fields' => 'all' ) );
				foreach ( $terms as $t ) {
					if ( in_array( $t->slug, (array) $options, true ) ) {
						$opts[] = array( 'v' => $t->slug, 't' => $t->name );
					}
				}
			} else {
				foreach ( (array) $options as $o ) {
					$opts[] = array( 'v' => (string) $o, 't' => (string) $o );
				}
			}
			$attrs[] = array(
				'key'     => 'attribute_' . sanitize_title( $attr ),
				'label'   => wc_attribute_label( $attr, $product ),
				'options' => $opts,
			);
		}
		$vars = array();
		foreach ( array_slice( $product->get_available_variations(), 0, 150 ) as $v ) {
			$vars[] = array(
				'id'    => (int) $v['variation_id'],
				'a'     => (array) $v['attributes'],
				'p'     => (float) $v['display_price'],
				'img'   => ! empty( $v['image']['thumb_src'] ) ? (string) $v['image']['thumb_src'] : '',
				'stock' => ! empty( $v['is_in_stock'] ) ? 1 : 0,
			);
		}
		$out = array(
			'attrs' => $attrs,
			'vars'  => $vars,
		);
		set_transient( $ckey, $out, HOUR_IN_SECONDS );
		wp_send_json_success( $out );
	}

	/** Save the order as a lead. */
	public function ajax_lead() {
		// Public form on cached pages: a stale nonce must not block real customers,
		// so spam is stopped with a honeypot, a fill-time trap, link filtering,
		// length caps and a per-IP limit instead (same approach as Tabarak 1.12.1).
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		if ( ! empty( $in['website'] ) || ! isset( $in['tt'] ) || (int) $in['tt'] < 2500 ) {
			wp_send_json_success( array( 'ok' => 1 ) ); // Bot: quietly ignore.
		}
		$note = isset( $in['note'] ) ? (string) $in['note'] : '';
		if ( preg_match_all( '#https?://#i', $note ) > 0 ) {
			wp_send_json_success( array( 'ok' => 1 ) ); // Link spam: quietly ignore.
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rk  = 'nurax_wo_rl_' . md5( $ip );
		$cnt = (int) get_transient( $rk );
		if ( $cnt >= 12 ) {
			wp_send_json_error( array( 'message' => __( 'Too many orders from this connection. Please chat with us on WhatsApp.', 'nura-experience' ) ), 429 );
		}
		set_transient( $rk, $cnt + 1, HOUR_IN_SECONDS );

		$name  = isset( $in['name'] ) ? self::cut( sanitize_text_field( (string) $in['name'] ), 80 ) : '';
		$phone = self::normalise_phone( isset( $in['phone'] ) ? $in['phone'] : '' );
		$ref   = isset( $in['ref'] ) ? strtoupper( sanitize_text_field( (string) $in['ref'] ) ) : '';
		if ( strlen( $name ) < 2 || '' === $phone || ! preg_match( '/^NURA-\d{6}-[A-Z0-9]{4}$/', $ref ) ) {
			wp_send_json_error( array( 'message' => __( 'Please check your name and phone number.', 'nura-experience' ) ), 400 );
		}

		// Idempotent: the same reference is only stored once.
		$dupe = get_posts( array(
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'title'          => $ref,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		if ( ! empty( $dupe ) ) {
			wp_send_json_success( array( 'ok' => 1 ) );
		}

		$items = array();
		$raw   = isset( $in['items'] ) ? json_decode( (string) $in['items'], true ) : array();
		if ( is_array( $raw ) ) {
			foreach ( array_slice( $raw, 0, 30 ) as $it ) {
				if ( ! is_array( $it ) ) {
					continue;
				}
				$pid  = isset( $it['id'] ) ? absint( $it['id'] ) : 0;
				$prod = ( $pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $pid ) : null;
				$nm   = isset( $it['name'] ) ? self::cut( sanitize_text_field( (string) $it['name'] ), 160 ) : '';
				if ( $prod ) {
					$par = $prod->get_parent_id() ? wc_get_product( $prod->get_parent_id() ) : $prod;
					$nm  = wp_strip_all_tags( $par ? $par->get_name() : $prod->get_name() );
				}
				if ( '' === $nm ) {
					continue;
				}
				$qty     = isset( $it['qty'] ) ? min( 99, max( 1, (int) $it['qty'] ) ) : 1;
				$price   = isset( $it['price'] ) ? max( 0, (float) $it['price'] ) : 0;
				if ( $prod && ! $prod->is_type( 'variable' ) ) {
					$price = (float) wc_get_price_to_display( $prod ); // Trust the store price, not the browser.
				}
				$items[] = array(
					'id'     => $pid,
					'name'   => $nm,
					'option' => isset( $it['option'] ) ? self::cut( sanitize_text_field( (string) $it['option'] ), 160 ) : '',
					'qty'    => $qty,
					'price'  => $price,
					'line'   => $price * $qty,
				);
			}
		}
		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a product first.', 'nura-experience' ) ), 400 );
		}
		$total = 0.0;
		foreach ( $items as $it ) {
			$total += $it['line'];
		}

		$fulfil   = ( isset( $in['fulfil'] ) && 'pickup' === $in['fulfil'] ) ? 'pickup' : 'delivery';
		$location = isset( $in['location'] ) ? self::cut( sanitize_text_field( (string) $in['location'] ), 80 ) : '';
		$town     = isset( $in['town'] ) ? self::cut( sanitize_text_field( (string) $in['town'] ), 60 ) : '';
		$area     = isset( $in['area'] ) ? self::cut( sanitize_text_field( (string) $in['area'] ), 120 ) : '';
		if ( '' === $location || '' === $town ) {
			wp_send_json_error( array( 'message' => __( 'Please choose your county and enter your town.', 'nura-experience' ) ), 400 );
		}
		$place    = $town . ', ' . $location . ' County';
		$payment  = isset( $in['payment'] ) ? sanitize_text_field( (string) $in['payment'] ) : '';
		if ( ! in_array( $payment, self::payments(), true ) ) {
			$payment = self::PAY_TILL;
		}
		// Paying at the shop is only for studio pick-up; pay on delivery only within Nairobi CBD (1.94.0).
		$cbd = 'delivery' === $fulfil && 'Nairobi' === $location && ! empty( $in['cbd'] );
		if ( self::PAY_SHOP === $payment && 'pickup' !== $fulfil ) {
			$payment = self::PAY_TILL;
		}
		if ( self::PAY_COD === $payment && ! $cbd ) {
			$payment = self::PAY_TILL;
		}
		// Paid through the M-Pesa prompt (Paystack): trust only a reference this server started and verified.
		$stk = isset( $in['stk_ref'] ) ? self::stk_record( (string) $in['stk_ref'] ) : null;
		if ( $stk && ( empty( $stk['nura_ref'] ) || $stk['nura_ref'] !== $ref ) ) {
			$stk = null;
		}
		if ( $stk && 'success' !== ( isset( $stk['status'] ) ? $stk['status'] : '' ) ) {
			$stk = self::stk_verify( (string) $in['stk_ref'] );
		}
		$code      = self::PAY_TILL === $payment ? self::normalise_code( isset( $in['mpesa_code'] ) ? $in['mpesa_code'] : '' ) : '';
		$code_flag = '';
		if ( '' !== $code && class_exists( 'NURAX_Desk' ) && method_exists( 'NURAX_Desk', 'ref_used' ) ) {
			$used = (string) NURAX_Desk::ref_used( $code );
			if ( '' !== $used ) {
				$code_flag = sprintf( 'Code already used on %s', $used );
			}
		}
		if ( '' === $code_flag && '' !== $code ) {
			$seen = get_posts( array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_nwo_pay_ref', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $code, // phpcs:ignore WordPress.DB.SlowDBQuery
			) );
			if ( ! empty( $seen ) ) {
				$code_flag = sprintf( 'Code already used on WhatsApp order %s', get_the_title( (int) $seen[0] ) );
			}
		}
		$delivery = 'pickup' === $fulfil ? self::pickup_text() : trim( ( '' !== $area ? $area . ', ' : '' ) . $place, ', ' ) . ( $cbd ? ' (Nairobi CBD)' : '' );
		$source   = isset( $in['source'] ) && in_array( $in['source'], array( 'product', 'card', 'cart' ), true ) ? (string) $in['source'] : 'product';
		$page     = isset( $in['page'] ) ? esc_url_raw( (string) $in['page'] ) : '';
		if ( '' !== $page && wp_parse_url( $page, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$page = '';
		}

		$data = array(
			'ref'      => $ref,
			'name'     => $name,
			'phone'    => $phone,
			'items'    => $items,
			'total'    => $total,
			'fulfil'   => $fulfil,
			'delivery' => $delivery,
			'county'   => $location,
			'town'     => $town,
			'payment'  => $payment,
			'till'     => self::PAY_TILL === $payment ? self::till() : '',
			'mpesa_code' => $code,
			'code_flag'  => $code_flag,
			'cbd'        => $cbd ? 1 : 0,
			'paid_stk'   => ( $stk && 'success' === $stk['status'] ) ? array( 'reference' => $stk['reference'], 'amount' => $stk['amount'], 'receipt' => isset( $stk['receipt'] ) ? $stk['receipt'] : '' ) : '',
			'ship'     => isset( $in['ship'] ) && '' !== (string) $in['ship'] ? (int) $in['ship'] : '',
			'note'     => self::cut( sanitize_textarea_field( $note ), 300 ),
			'source'   => $source,
			'page'     => $page,
		);

		$post_id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'private',
			'post_title'  => $ref,
		), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => 'save_failed' ), 500 );
		}
		update_post_meta( $post_id, self::META, $data );
		update_post_meta( $post_id, self::STATUS, 'new' );
		if ( '' !== $code ) {
			update_post_meta( $post_id, '_nwo_pay_ref', $code ); // Staff verify against the till statement before dispatch.
		}
		if ( $stk && 'success' === $stk['status'] ) {
			update_post_meta( $post_id, '_nwo_pay_ref', 'PAYSTACK-' . $stk['reference'] ); // Verified with Paystack; one reference, one order.
			update_post_meta( $post_id, '_nwo_paid', 'paystack' );
		}

		/** Fires after a WhatsApp order lead is saved. */
		do_action( 'nurax_wa_order_saved', $post_id, $data );

		wp_send_json_success( array( 'ok' => 1 ) );
	}

	/* ------------------------------------------------------------------ */
	/* 1.94.0: M-Pesa PIN prompt (STK push) through Paystack                */
	/* ------------------------------------------------------------------ */

	/**
	 * Paystack secret key: constant NURAX_PAYSTACK_SECRET, then the Paystack
	 * WooCommerce gateway settings (live or test key per its test mode).
	 * Filter nurax_paystack_secret. Never printed or sent to the browser.
	 */
	private static function paystack_secret() {
		$key = defined( 'NURAX_PAYSTACK_SECRET' ) ? (string) NURAX_PAYSTACK_SECRET : '';
		if ( '' === $key ) {
			$o = get_option( 'woocommerce_paystack_settings', array() );
			if ( is_array( $o ) && ( ! isset( $o['enabled'] ) || 'yes' === $o['enabled'] ) ) {
				$test = isset( $o['testmode'] ) && 'yes' === $o['testmode'];
				$key  = (string) ( $test ? ( isset( $o['test_secret_key'] ) ? $o['test_secret_key'] : '' ) : ( isset( $o['live_secret_key'] ) ? $o['live_secret_key'] : '' ) );
			}
		}
		$key = trim( (string) apply_filters( 'nurax_paystack_secret', $key ) );
		return preg_match( '/^sk_(live|test)_[A-Za-z0-9]+$/', $key ) ? $key : '';
	}

	/** Paystack API call. Returns decoded body or null. */
	private static function paystack( $method, $path, $body = null ) {
		$key = self::paystack_secret();
		if ( '' === $key ) {
			return null;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( 'https://api.paystack.co' . $path, $args );
		self::$last_http = array( 'code' => 0, 'error' => '', 'body' => '' );
		if ( is_wp_error( $res ) ) {
			self::$last_http['error'] = $res->get_error_message();
			return null;
		}
		self::$last_http['code'] = (int) wp_remote_retrieve_response_code( $res );
		self::$last_http['body'] = substr( (string) wp_remote_retrieve_body( $res ), 0, 1500 );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : null;
	}

	/* ---- 1.97.0: M-Pesa prompt log (Paystack replies), WooCommerce > M-Pesa prompt log ---- */

	/** Last raw HTTP result from paystack(): code, transport error, body (trimmed). */
	private static $last_http = array( 'code' => 0, 'error' => '', 'body' => '' );

	const LOG_OPT = 'nurax_stk_log';

	/** 2547****5678 */
	private static function mask_phone( $p ) {
		$p = (string) $p;
		return strlen( $p ) > 7 ? substr( $p, 0, 4 ) . str_repeat( '*', max( 0, strlen( $p ) - 7 ) ) . substr( $p, -3 ) : $p;
	}

	/**
	 * Record one Paystack interaction. Never stores the secret key or full phone.
	 * Keeps the latest 200 entries (option, not autoloaded). Filter nurax_stk_log_enabled.
	 */
	private static function log_stk( $kind, $source, $nura_ref, $reference, $amount, $phone, $res, $note = '' ) {
		if ( ! apply_filters( 'nurax_stk_log_enabled', true ) ) {
			return;
		}
		$d   = ( is_array( $res ) && isset( $res['data'] ) && is_array( $res['data'] ) ) ? $res['data'] : array();
		$row = array(
			't'         => current_time( 'mysql' ),
			'kind'      => $kind,
			'source'    => (string) $source,
			'nura_ref'  => (string) $nura_ref,
			'reference' => (string) $reference,
			'amount'    => (int) $amount,
			'phone'     => self::mask_phone( $phone ),
			'mode'      => 0 === strpos( self::paystack_secret(), 'sk_test_' ) ? 'test' : ( '' === self::paystack_secret() ? 'no key' : 'live' ),
			'http'      => (int) self::$last_http['code'],
			'wp_error'  => (string) self::$last_http['error'],
			'ok'        => is_array( $res ) && ! empty( $res['status'] ) ? 1 : 0,
			'message'   => is_array( $res ) && isset( $res['message'] ) ? (string) $res['message'] : '',
			'pstatus'   => isset( $d['status'] ) ? (string) $d['status'] : '',
			'detail'    => trim( implode( ' | ', array_filter( array(
				isset( $d['message'] ) ? (string) $d['message'] : '',
				isset( $d['display_text'] ) ? (string) $d['display_text'] : '',
				isset( $d['gateway_response'] ) ? (string) $d['gateway_response'] : '',
				isset( $d['channel'] ) ? 'channel ' . $d['channel'] : '',
				$note,
			) ) ) ),
			'raw'       => is_array( $res ) ? '' : (string) self::$last_http['body'],
		);
		foreach ( $row as $k => $v ) {
			if ( is_string( $v ) ) {
				$row[ $k ] = self::cut( sanitize_text_field( $v ), 'raw' === $k ? 600 : 300 );
			}
		}
		$log = get_option( self::LOG_OPT, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, $row );
		update_option( self::LOG_OPT, array_slice( $log, 0, 200 ), false );
	}

	/** Admin: the log screen. */
	public function log_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$log = get_option( self::LOG_OPT, array() );
		$log = is_array( $log ) ? $log : array();
		echo '<div class="wrap"><h1>' . esc_html__( 'M-Pesa prompt log (Paystack)', 'nura-experience' ) . '</h1>';
		echo '<p>' . esc_html__( 'Every "Send M-Pesa prompt" from the WhatsApp pop-up and the booking page, and every payment check, with Paystack\'s reply. Newest first, last 200. Phone numbers are masked; no keys are stored.', 'nura-experience' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Paystack key in use:', 'nura-experience' ) . '</strong> ' . esc_html( '' === self::paystack_secret() ? __( 'none found', 'nura-experience' ) : ( 0 === strpos( self::paystack_secret(), 'sk_test_' ) ? __( 'TEST key', 'nura-experience' ) : __( 'LIVE key', 'nura-experience' ) ) ) . '</p>';
		if ( ! $log ) {
			echo '<p><em>' . esc_html__( 'No prompts logged yet.', 'nura-experience' ) . '</em></p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Time', 'Step', 'Source', 'Our ref', 'Paystack ref', 'KSh', 'Phone', 'Key', 'HTTP', 'OK', 'Paystack message', 'Status', 'Details', 'Raw / error' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $log as $r ) {
			$raw = trim( ( isset( $r['wp_error'] ) ? $r['wp_error'] : '' ) . ' ' . ( isset( $r['raw'] ) ? $r['raw'] : '' ) );
			echo '<tr>';
			foreach ( array( 't', 'kind', 'source', 'nura_ref', 'reference', 'amount', 'phone', 'mode', 'http', 'ok', 'message', 'pstatus', 'detail' ) as $k ) {
				echo '<td>' . esc_html( isset( $r[ $k ] ) ? (string) $r[ $k ] : '' ) . '</td>';
			}
			echo '<td>' . esc_html( $raw ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px">';
		wp_nonce_field( 'nurax_stk_log_clear' );
		echo '<input type="hidden" name="action" value="nurax_stk_log_clear"><button class="button" onclick="return confirm(\'Clear the log?\')">' . esc_html__( 'Clear log', 'nura-experience' ) . '</button></form></div>';
	}

	/** Admin: clear the log. */
	public function log_clear() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_stk_log_clear' );
		update_option( self::LOG_OPT, array(), false );
		wp_safe_redirect( admin_url( 'admin.php?page=nurax-stk-log' ) );
		exit;
	}

	/** Stored prompt record for a Paystack reference, or null. */
	private static function stk_record( $reference ) {
		$reference = (string) preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $reference );
		if ( '' === $reference ) {
			return null;
		}
		$r = get_transient( 'nurax_stk_' . md5( $reference ) );
		return is_array( $r ) ? $r : null;
	}

	/** Verify a prompt with Paystack and update its record. Returns the record or null. */
	private static function stk_verify( $reference ) {
		$rec = self::stk_record( $reference );
		if ( ! $rec ) {
			return null;
		}
		if ( in_array( $rec['status'], array( 'success', 'failed' ), true ) ) {
			return $rec;
		}
		$res = self::paystack( 'GET', '/transaction/verify/' . rawurlencode( $rec['reference'] ) );
		$st  = ( $res && ! empty( $res['data']['status'] ) ) ? (string) $res['data']['status'] : '';
		if ( 'success' === $st ) {
			$paid = isset( $res['data']['amount'] ) ? (int) $res['data']['amount'] : 0;
			$cur  = isset( $res['data']['currency'] ) ? (string) $res['data']['currency'] : '';
			if ( $paid >= (int) $rec['amount'] * 100 && 'KES' === strtoupper( $cur ) ) {
				$rec['status']  = 'success';
				$rec['receipt'] = isset( $res['data']['receipt_number'] ) ? sanitize_text_field( (string) $res['data']['receipt_number'] ) : '';
			} else {
				$rec['status'] = 'failed';
				$rec['why']    = 'amount';
			}
		} elseif ( in_array( $st, array( 'failed', 'abandoned', 'reversed' ), true ) ) {
			$rec['status'] = 'failed';
			$rec['why']    = $st;
		} elseif ( time() - (int) $rec['ts'] > 5 * MINUTE_IN_SECONDS ) {
			$rec['status'] = 'failed';
			$rec['why']    = 'timeout';
		}
		set_transient( 'nurax_stk_' . md5( $rec['reference'] ), $rec, 6 * HOUR_IN_SECONDS );
		if ( in_array( $rec['status'], array( 'success', 'failed' ), true ) ) {
			self::log_stk( 'verify', isset( $rec['source'] ) ? $rec['source'] : '', $rec['nura_ref'], $rec['reference'], $rec['amount'], $rec['phone'], $res, isset( $rec['why'] ) ? 'result: ' . $rec['status'] . ' (' . $rec['why'] . ')' : 'result: ' . $rec['status'] );
		}
		return $rec;
	}

	/** Per-IP counter; true when the limit is reached. */
	private static function limited( $key, $max, $ttl ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rk  = 'nurax_' . $key . '_' . md5( $ip );
		$cnt = (int) get_transient( $rk );
		if ( $cnt >= $max ) {
			return true;
		}
		set_transient( $rk, $cnt + 1, $ttl );
		return false;
	}

	/**
	 * AJAX: send the M-Pesa prompt. The amount is worked out here from store
	 * prices and the delivery quote, never taken from the browser.
	 */
	public function ajax_stk() {
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- public form on cached pages; sanitised per field, rate limited.
		if ( ! empty( $in['website'] ) ) {
			wp_send_json_error( array( 'message' => 'bot' ), 400 );
		}
		if ( '' === self::paystack_secret() ) {
			wp_send_json_error( array( 'message' => __( 'The M-Pesa prompt is not available right now. Please pay with the till below.', 'nura-experience' ) ), 503 );
		}
		if ( self::limited( 'stk_rl', 6, 15 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many payment prompts. Please wait a few minutes or pay with the till below.', 'nura-experience' ) ), 429 );
		}
		$phone = self::normalise_phone( isset( $in['stk_phone'] ) ? $in['stk_phone'] : '' );
		$ref   = isset( $in['ref'] ) ? strtoupper( sanitize_text_field( (string) $in['ref'] ) ) : '';
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid M-Pesa number, e.g. 0712 345 678.', 'nura-experience' ) ), 400 );
		}
		if ( ! preg_match( '/^NURA-\d{6}-[A-Z0-9]{4}$/', $ref ) ) {
			wp_send_json_error( array( 'message' => 'ref' ), 400 );
		}
		$total = 0.0;
		$raw   = isset( $in['items'] ) ? json_decode( (string) $in['items'], true ) : array();
		foreach ( array_slice( is_array( $raw ) ? $raw : array(), 0, 30 ) as $it ) {
			$pid  = is_array( $it ) && isset( $it['id'] ) ? absint( $it['id'] ) : 0;
			$prod = ( $pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $pid ) : null;
			if ( ! $prod || $prod->is_type( 'variable' ) || ! $prod->is_purchasable() ) {
				wp_send_json_error( array( 'message' => __( 'Please choose the colour or length first, or pay after we confirm your order on WhatsApp.', 'nura-experience' ) ), 400 );
			}
			$qty    = isset( $it['qty'] ) ? min( 99, max( 1, (int) $it['qty'] ) ) : 1;
			$total += (float) wc_get_price_to_display( $prod ) * $qty;
		}
		if ( $total <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a product first.', 'nura-experience' ) ), 400 );
		}
		$fulfil = ( isset( $in['fulfil'] ) && 'pickup' === $in['fulfil'] ) ? 'pickup' : 'delivery';
		$county = isset( $in['location'] ) ? sanitize_text_field( (string) $in['location'] ) : '';
		$fee    = 0.0;
		if ( 'delivery' === $fulfil ) {
			if ( ! in_array( $county, self::counties(), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Please choose your county first.', 'nura-experience' ) ), 400 );
			}
			$q = self::ship_quote( $county, $total );
			if ( empty( $q['known'] ) ) {
				wp_send_json_error( array( 'message' => __( 'We will confirm the delivery fee for your area on WhatsApp first. Send the order, then pay when we confirm the total.', 'nura-experience' ) ), 400 );
			}
			$fee = max( 0, (float) $q['fee'] );
		}
		$amount = (int) round( $total + $fee );
		if ( $amount < 10 || $amount > 300000 ) {
			wp_send_json_error( array( 'message' => __( 'This total cannot be paid with an M-Pesa prompt. Please pay with the till below.', 'nura-experience' ) ), 400 );
		}
		$r = self::stk_charge( $phone, $amount, $ref, 'whatsapp_popup' );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => __( 'We could not send the M-Pesa prompt. Check the number or pay with the till below.', 'nura-experience' ), 'detail' => (string) $r->get_error_data() ), 502 );
		}
		wp_send_json_success( array(
			'reference' => $r['reference'],
			'amount'    => $r['amount'],
			'text'      => __( 'Check your phone and enter your M-Pesa PIN to pay.', 'nura-experience' ),
		) );
	}

	/** True when the M-Pesa prompt can be offered (a Paystack secret key is configured). */
	public static function stk_enabled() {
		return '' !== self::paystack_secret();
	}

	/**
	 * Send an M-Pesa prompt for an amount worked out by the caller on the server.
	 * Returns array( reference, amount ) or WP_Error.
	 */
	public static function stk_charge( $phone, $amount, $nura_ref, $source ) {
		$amount = (int) round( (float) $amount );
		if ( '' === self::paystack_secret() || $amount < 10 ) {
			self::$last_http = array( 'code' => 0, 'error' => '', 'body' => '' );
			self::log_stk( 'charge', $source, $nura_ref, '', $amount, $phone, null, '' === self::paystack_secret() ? 'not sent: no Paystack secret key found' : 'not sent: amount below KSh 10' );
			return new WP_Error( 'nurax_stk_off', __( 'The M-Pesa prompt is not available right now. Please pay manually.', 'nura-experience' ) );
		}
		$reference = $nura_ref . '-' . strtoupper( wp_generate_password( 4, false, false ) );
		$domain    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$res       = self::paystack( 'POST', '/charge', array(
			'email'        => 'wa' . $phone . '@' . ( '' !== $domain ? $domain : 'nurabeauty.co.ke' ),
			'amount'       => $amount * 100,
			'currency'     => 'KES',
			'reference'    => $reference,
			'mobile_money' => array( 'phone' => '+' . $phone, 'provider' => 'mpesa' ),
			'metadata'     => array(
				'nura_ref'      => $nura_ref,
				'source'        => $source,
				'custom_fields' => array( array( 'display_name' => 'Ref', 'variable_name' => 'nura_ref', 'value' => $nura_ref ) ),
			),
		) );
		$st = ( $res && ! empty( $res['status'] ) && isset( $res['data']['status'] ) ) ? (string) $res['data']['status'] : '';
		self::log_stk( 'charge', $source, $nura_ref, $reference, $amount, $phone, $res );
		if ( ! in_array( $st, array( 'pay_offline', 'pending', 'send_otp', 'success', 'ongoing' ), true ) ) {
			$why = ( $res && ! empty( $res['data']['message'] ) ) ? (string) $res['data']['message'] : ( ( $res && ! empty( $res['message'] ) ) ? (string) $res['message'] : (string) self::$last_http['error'] );
			return new WP_Error( 'nurax_stk_fail', __( 'We could not send the M-Pesa prompt. Check the number or pay manually.', 'nura-experience' ), self::cut( sanitize_text_field( $why ), 140 ) );
		}
		set_transient( 'nurax_stk_' . md5( $reference ), array(
			'reference' => $reference,
			'nura_ref'  => $nura_ref,
			'amount'    => $amount,
			'phone'     => $phone,
			'source'    => $source,
			'status'    => 'pending',
			'polls'     => 0,
			'ts'        => time(),
		), 6 * HOUR_IN_SECONDS );
		return array( 'reference' => $reference, 'amount' => $amount );
	}

	/**
	 * A verified, paid prompt for this reference + our ref, for at least $min KSh.
	 * Returns the record or null.
	 */
	public static function stk_paid( $reference, $nura_ref, $min ) {
		$rec = self::stk_record( $reference );
		if ( ! $rec || (string) $rec['nura_ref'] !== (string) $nura_ref ) {
			return null;
		}
		$rec = self::stk_verify( $rec['reference'] );
		return ( $rec && 'success' === $rec['status'] && (int) $rec['amount'] >= (int) $min ) ? $rec : null;
	}

	/** AJAX: M-Pesa prompt for a studio booking deposit. Amount = the deposit rule for the service. */
	public function ajax_bk_stk() {
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- public form on cached pages; sanitised per field, rate limited.
		if ( ! empty( $in['website'] ) || ! class_exists( 'NURAX_Desk_Appts' ) ) {
			wp_send_json_error( array( 'message' => 'bad' ), 400 );
		}
		if ( self::limited( 'bkstk_rl', 6, 15 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many payment prompts. Please wait a few minutes or pay manually.', 'nura-experience' ) ), 429 );
		}
		$phone   = self::normalise_phone( isset( $in['stk_phone'] ) ? $in['stk_phone'] : '' );
		$ref     = isset( $in['ref'] ) ? strtoupper( sanitize_text_field( (string) $in['ref'] ) ) : '';
		$service = isset( $in['service'] ) ? sanitize_key( (string) $in['service'] ) : '';
		$type    = isset( $in['type'] ) ? sanitize_key( (string) $in['type'] ) : 'studio';
		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid M-Pesa number, e.g. 0712 345 678.', 'nura-experience' ) ), 400 );
		}
		if ( ! preg_match( '/^BK-\d{6}-[A-Z0-9]{4}$/', $ref ) ) {
			wp_send_json_error( array( 'message' => 'ref' ), 400 );
		}
		$bk_time = isset( $in['time'] ) && preg_match( '/^\d{2}:\d{2}$/', (string) $in['time'] ) ? (string) $in['time'] : '';
		$deposit = (int) NURAX_Desk_Appts::deposit_for( $service, $type, $bk_time ); // 1.113.0: early fee included.
		if ( $deposit <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No deposit is needed for this booking.', 'nura-experience' ) ), 400 );
		}
		$r = self::stk_charge( $phone, $deposit, $ref, 'booking_deposit' );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ), 502 );
		}
		wp_send_json_success( array(
			'reference' => $r['reference'],
			'amount'    => $r['amount'],
			'text'      => __( 'Check your phone and enter your M-Pesa PIN to pay the deposit.', 'nura-experience' ),
		) );
	}

	/** AJAX: poll a prompt. Read-only, rate limited per reference. */
	public function ajax_stk_status() {
		$ref = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rec = self::stk_record( $ref );
		if ( ! $rec ) {
			wp_send_json_error( array( 'status' => 'unknown' ), 404 );
		}
		$rec['polls'] = (int) $rec['polls'] + 1;
		if ( $rec['polls'] > 80 ) {
			wp_send_json_success( array( 'status' => $rec['status'] ) );
		}
		set_transient( 'nurax_stk_' . md5( $rec['reference'] ), $rec, 6 * HOUR_IN_SECONDS );
		$rec = self::stk_verify( $rec['reference'] );
		wp_send_json_success( array(
			'status'  => $rec['status'],
			'amount'  => (int) $rec['amount'],
			'receipt' => isset( $rec['receipt'] ) ? $rec['receipt'] : '',
			'why'     => isset( $rec['why'] ) ? $rec['why'] : '',
		) );
	}

	private static function cut( $s, $len ) {
		$s = (string) $s;
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $len ) : substr( $s, 0, $len );
	}

	/* ------------------------------------------------------------------ */
	/* Admin: WooCommerce > WhatsApp Orders                                */
	/* ------------------------------------------------------------------ */

	public function menu() {
		$new   = self::count_new();
		$title = __( 'WhatsApp Orders', 'nura-experience' );
		if ( $new > 0 ) {
			$title .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $new . '</span></span>';
		}
		add_submenu_page( 'woocommerce', __( 'WhatsApp Orders', 'nura-experience' ), $title, 'manage_woocommerce', self::PAGE, array( $this, 'page' ) );
		add_submenu_page( 'woocommerce', __( 'M-Pesa prompt log', 'nura-experience' ), __( 'M-Pesa prompt log', 'nura-experience' ), 'manage_woocommerce', 'nurax-stk-log', array( $this, 'log_page' ) );
	}

	private static function count_new() {
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => 'new', // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return (int) $q->found_posts;
	}

	/** Status change / delete (POST, nonce + capability checked). */
	public function admin_update() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_wa_order_status' );
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( $id && self::CPT === get_post_type( $id ) ) {
			if ( ! empty( $_POST['nwo_delete'] ) ) {
				wp_delete_post( $id, true );
			} else {
				$st = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
				if ( array_key_exists( $st, self::statuses() ) ) {
					update_post_meta( $id, self::STATUS, $st );
				}
			}
		}
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : '';
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$statuses = self::statuses();
		$filter   = isset( $_GET['nwo_status'] ) ? sanitize_key( wp_unslash( $_GET['nwo_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args     = array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 30,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( array_key_exists( $filter, $statuses ) ) {
			$args['meta_key']   = self::STATUS; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $filter; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$q    = new WP_Query( $args );
		$base = admin_url( 'admin.php?page=' . self::PAGE );
		$self = add_query_arg( array( 'nwo_status' => $filter, 'paged' => $paged ), $base );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WhatsApp Orders', 'nura-experience' ); ?></h1>
			<p class="description"><?php echo esc_html( sprintf( /* translators: %s: phone number */ __( 'Every order sent from the "Order on WhatsApp" pop-up is saved here, even if the customer never presses send in WhatsApp. Orders go to WhatsApp +%s (Settings > NURA Experience > WhatsApp link or number).', 'nura-experience' ), self::whatsapp_number() ) ); ?></p>
			<?php if ( class_exists( 'NURAX_Desk' ) ) : ?><div class="notice notice-info inline" style="margin:12px 0"><p><?php esc_html_e( 'Tip: these orders are now also managed in NURA Desk, next to appointments and payment plans.', 'nura-experience' ); ?> <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=nurax-orders' ) ); ?>"><?php esc_html_e( 'Open in NURA Desk', 'nura-experience' ); ?></a></p></div><?php endif; ?>
			<?php $this->fees_box(); ?>
			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( $base ); ?>"<?php echo '' === $filter ? ' class="current"' : ''; ?>><?php esc_html_e( 'All', 'nura-experience' ); ?></a> |</li>
				<?php $last = array_key_last( $statuses ); ?>
				<?php foreach ( $statuses as $k => $label ) : ?>
					<li><a href="<?php echo esc_url( add_query_arg( 'nwo_status', $k, $base ) ); ?>"<?php echo $filter === $k ? ' class="current"' : ''; ?>><?php echo esc_html( $label ); ?></a><?php echo $k !== $last ? ' |' : ''; ?></li>
				<?php endforeach; ?>
			</ul>
			<table class="widefat striped" style="clear:both">
				<thead><tr>
					<th><?php esc_html_e( 'Ref', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Date', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'nura-experience' ); ?></th>
					<th style="width:26%"><?php esc_html_e( 'Items', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Total', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Delivery', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'nura-experience' ); ?></th>
					<th><?php esc_html_e( 'Status', 'nura-experience' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $q->have_posts() ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No WhatsApp orders yet.', 'nura-experience' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $q->posts as $post ) : ?>
					<?php
					$d  = get_post_meta( $post->ID, self::META, true );
					$d  = is_array( $d ) ? $d : array();
					$st = (string) get_post_meta( $post->ID, self::STATUS, true );
					$ph = isset( $d['phone'] ) ? (string) $d['phone'] : '';
					$nm = isset( $d['name'] ) ? (string) $d['name'] : '';
					$rf = isset( $d['ref'] ) ? (string) $d['ref'] : $post->post_title;
					$hi = sprintf( /* translators: 1: customer name, 2: order ref */ __( 'Hi %1$s, this is NURA Beauty about your order %2$s.', 'nura-experience' ), $nm, $rf );
					?>
					<tr>
						<td><strong><?php echo esc_html( $rf ); ?></strong><?php if ( ! empty( $d['page'] ) ) : ?><br><a href="<?php echo esc_url( $d['page'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'page', 'nura-experience' ); ?></a><?php endif; ?></td>
						<td><?php echo esc_html( get_the_date( 'j M Y, H:i', $post ) ); ?></td>
						<td><?php echo esc_html( $nm ); ?></td>
						<td><?php if ( '' !== $ph ) : ?><a href="<?php echo esc_url( 'https://wa.me/' . $ph . '?text=' . rawurlencode( $hi ) ); ?>" target="_blank" rel="noopener">+<?php echo esc_html( $ph ); ?></a><?php endif; ?></td>
						<td>
							<?php
							$n = 0;
							foreach ( isset( $d['items'] ) ? (array) $d['items'] : array() as $it ) {
								$n++;
								echo esc_html( $n . '. ' . $it['name'] . ( ! empty( $it['option'] ) ? ' - ' . $it['option'] : '' ) . ' x ' . (int) $it['qty'] ) . '<br>';
							}
							if ( ! empty( $d['note'] ) ) {
								echo '<em>' . esc_html( $d['note'] ) . '</em>';
							}
							?>
						</td>
						<td><?php echo esc_html( self::money( isset( $d['total'] ) ? $d['total'] : 0 ) ); ?></td>
						<td><?php echo esc_html( isset( $d['delivery'] ) ? $d['delivery'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $d['payment'] ) ? $d['payment'] : '' ); ?><?php if ( ! empty( $d['mpesa_code'] ) ) : ?><br><code><?php echo esc_html( $d['mpesa_code'] ); ?></code> <small><?php esc_html_e( '(verify on till statement)', 'nura-experience' ); ?></small><?php endif; ?><?php if ( ! empty( $d['code_flag'] ) ) : ?><br><strong style="color:#b32d2e"><?php echo esc_html( $d['code_flag'] ); ?></strong><?php endif; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
								<?php wp_nonce_field( 'nurax_wa_order_status' ); ?>
								<input type="hidden" name="action" value="nurax_wa_order_status">
								<input type="hidden" name="order_id" value="<?php echo (int) $post->ID; ?>">
								<input type="hidden" name="back" value="<?php echo esc_attr( $self ); ?>">
								<select name="status">
									<?php foreach ( $statuses as $k => $label ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"<?php selected( $st, $k ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Save', 'nura-experience' ); ?></button>
								<button type="submit" class="button-link button-link-delete" name="nwo_delete" value="1" onclick="return confirm('<?php echo esc_js( __( 'Delete this order permanently?', 'nura-experience' ) ); ?>');"><?php esc_html_e( 'Delete', 'nura-experience' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			if ( $q->max_num_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post( paginate_links( array(
					'base'    => add_query_arg( 'paged', '%#%', add_query_arg( 'nwo_status', $filter, $base ) ),
					'format'  => '',
					'current' => $paged,
					'total'   => (int) $q->max_num_pages,
				) ) );
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}
}
