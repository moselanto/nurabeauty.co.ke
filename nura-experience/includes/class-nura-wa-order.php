<?php
/**
 * NURA WhatsApp Order pop-up (v1.43.0).
 *
 * A polished "Order on WhatsApp" form, ported from the Tabarak Electronics order
 * funnel and restyled for NURA:
 *   - One modal rendered in wp_footer on every front-end page.
 *   - Triggers: single product page (under add to cart, uses the chosen
 *     variation), every shop / category product card, and "Order whole cart on
 *     WhatsApp" on the cart page. Every trigger is a real wa.me link, so it still
 *     works without JavaScript.
 *   - Kenyan phone validation, delivery area / pick-up, payment preference,
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
		add_action( 'wp_footer', array( $this, 'modal' ), 30 );

		// Triggers.
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'product_button' ), 25 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'card_button' ), 14 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'cart_button' ), 30 );

		// AJAX: save lead + variation options for product-card orders.
		add_action( 'wp_ajax_nurax_wa_order', array( $this, 'ajax_lead' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_order', array( $this, 'ajax_lead' ) );
		add_action( 'wp_ajax_nurax_wa_product', array( $this, 'ajax_product' ) );
		add_action( 'wp_ajax_nopriv_nurax_wa_product', array( $this, 'ajax_product' ) );

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
	private static function locations() {
		$nairobi = array( 'CBD', 'Westlands', 'Parklands', 'Kilimani', 'Kileleshwa', 'Lavington', 'Hurlingham', 'Upper Hill', 'Ngong Road', 'Karen', 'Langata', 'South B', 'South C', 'Industrial Area', 'Embakasi', 'Donholm', 'Buruburu', 'Umoja', 'Eastleigh', 'Kasarani', 'Roysambu', 'Thika Road', 'Kahawa', 'Runda', 'Gigiri', 'Ruaka', 'Kitisuru', 'Syokimau', 'Kitengela', 'Rongai', 'Ngong', 'Ruiru', 'Juja', 'Kikuyu', 'Other Nairobi area' );
		$towns   = array( 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Machakos', 'Nyeri', 'Meru', 'Embu', 'Naivasha', 'Nanyuki', 'Kisii', 'Kakamega', 'Kericho', 'Kitale', 'Malindi', 'Diani', 'Kilifi', 'Bungoma', 'Narok', 'Garissa', 'Other town in Kenya' );
		return apply_filters( 'nurax_wa_order_locations', array(
			'Nairobi'           => $nairobi,
			'Countrywide towns' => $towns,
		) );
	}

	private static function payments() {
		return apply_filters( 'nurax_wa_order_payments', array(
			'M-Pesa on delivery',
			'M-Pesa now',
			'Cash on delivery',
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
	private static function product_payload( $product ) {
		$img = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
		return array(
			'id'       => $product->get_id(),
			'name'     => wp_strip_all_tags( $product->get_name() ),
			'price'    => self::display_price( $product ),
			'img'      => $img ? $img : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' ),
			'url'      => get_permalink( $product->get_id() ),
			'variable' => $product->is_type( 'variable' ) ? 1 : 0,
		);
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
		$text  = __( 'Hi NURA, I would like to order:', 'nura-experience' ) . "\n" . implode( "\n", $plain ) . "\n" . __( 'Total:', 'nura-experience' ) . ' ' . self::money( $total );
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
		$steps = array( __( 'Details', 'nura-experience' ), __( 'Sent', 'nura-experience' ) );
		?>
		<div class="nwo" id="nwo-modal" data-nwo-modal hidden>
			<div class="nwo__overlay" data-nwo-close></div>
			<div class="nwo__panel" role="dialog" aria-modal="true" aria-labelledby="nwo-title" tabindex="-1">
				<div class="nwo__head">
					<div>
						<p class="nwo__eyebrow"><?php esc_html_e( 'NURA Beauty', 'nura-experience' ); ?></p>
						<h2 class="nwo__title" id="nwo-title"><?php esc_html_e( 'Order on WhatsApp', 'nura-experience' ); ?></h2>
					</div>
					<button type="button" class="nwo__close" data-nwo-close aria-label="<?php esc_attr_e( 'Close', 'nura-experience' ); ?>">&times;</button>
				</div>
				<ol class="nwo-progress" aria-label="<?php esc_attr_e( 'Order progress', 'nura-experience' ); ?>">
					<?php foreach ( $steps as $i => $label ) : ?>
						<li class="nwo-progress__step<?php echo 0 === $i ? ' is-active' : ''; ?>" data-nwo-step="<?php echo (int) $i; ?>"><span class="nwo-progress__dot"><?php echo (int) ( $i + 1 ); ?></span><span class="nwo-progress__label"><?php echo esc_html( $label ); ?></span></li>
					<?php endforeach; ?>
				</ol>

				<form class="nwo-form" data-nwo-form novalidate>
					<div class="nwo-sum" data-nwo-sum></div>

					<div class="nwo-opts" data-nwo-opts hidden></div>

					<div class="nwo-qtyrow" data-nwo-qtyrow>
						<div class="nwo-qty">
							<span class="nwo-qty__label" id="nwo-qty-label"><?php esc_html_e( 'Quantity', 'nura-experience' ); ?></span>
							<div class="nwo-qty__ctrl" role="group" aria-labelledby="nwo-qty-label">
								<button type="button" class="nwo-qty__btn" data-nwo-qty="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'nura-experience' ); ?>">&minus;</button>
								<input type="number" name="qty" class="nwo-qty__input" value="1" min="1" max="20" inputmode="numeric" aria-labelledby="nwo-qty-label">
								<button type="button" class="nwo-qty__btn" data-nwo-qty="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'nura-experience' ); ?>">+</button>
							</div>
						</div>
						<div class="nwo-total"><span><?php esc_html_e( 'Total', 'nura-experience' ); ?></span><strong data-nwo-total aria-live="polite"></strong></div>
					</div>

					<div class="nwo-grid">
						<div class="nwo-f">
							<label for="nwo-name"><?php esc_html_e( 'Full name', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
							<input id="nwo-name" name="name" type="text" autocomplete="name" maxlength="80" required aria-describedby="nwo-err-name">
							<p class="nwo-err" id="nwo-err-name"></p>
						</div>
						<div class="nwo-f">
							<label for="nwo-phone"><?php esc_html_e( 'Phone (M-Pesa / WhatsApp)', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
							<input id="nwo-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="16" placeholder="0712 345 678" required aria-describedby="nwo-err-phone">
							<p class="nwo-err" id="nwo-err-phone"></p>
						</div>
					</div>

					<fieldset class="nwo-f nwo-choice">
						<legend><?php esc_html_e( 'How would you like to receive it?', 'nura-experience' ); ?></legend>
						<div class="nwo-pills">
							<label class="nwo-pill"><input type="radio" name="fulfil" value="delivery" checked><span><?php esc_html_e( 'Delivery', 'nura-experience' ); ?></span></label>
							<label class="nwo-pill"><input type="radio" name="fulfil" value="pickup"><span><?php esc_html_e( 'Pick-up', 'nura-experience' ); ?></span></label>
						</div>
					</fieldset>

					<div class="nwo-grid" data-nwo-delivery>
						<div class="nwo-f">
							<label for="nwo-location"><?php esc_html_e( 'County / town', 'nura-experience' ); ?> <span class="nwo-req" aria-hidden="true">*</span></label>
							<select id="nwo-location" name="location" aria-describedby="nwo-err-location">
								<option value=""><?php esc_html_e( 'Choose your area', 'nura-experience' ); ?></option>
								<?php foreach ( self::locations() as $group => $places ) : ?>
									<optgroup label="<?php echo esc_attr( $group ); ?>">
										<?php foreach ( (array) $places as $place ) : ?>
											<?php $val = ( 'Nairobi' === $group ) ? 'Nairobi - ' . $place : $place; ?>
											<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $place ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<p class="nwo-err" id="nwo-err-location"></p>
						</div>
						<div class="nwo-f">
							<label for="nwo-area"><?php esc_html_e( 'Estate / street / landmark', 'nura-experience' ); ?></label>
							<input id="nwo-area" name="area" type="text" autocomplete="street-address" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Argwings Kodhek Rd, near Yaya Centre', 'nura-experience' ); ?>">
						</div>
					</div>
					<p class="nwo-pickup" data-nwo-pickup hidden><?php echo esc_html( self::pickup_text() ); ?></p>

					<fieldset class="nwo-f nwo-choice">
						<legend><?php esc_html_e( 'Payment preference', 'nura-experience' ); ?></legend>
						<div class="nwo-pills">
							<?php foreach ( self::payments() as $i => $pay ) : ?>
								<label class="nwo-pill"><input type="radio" name="payment" value="<?php echo esc_attr( $pay ); ?>"<?php checked( 0, $i ); ?>><span><?php echo esc_html( $pay ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="nwo-f">
						<label for="nwo-note"><?php esc_html_e( 'Note (optional)', 'nura-experience' ); ?></label>
						<textarea id="nwo-note" name="note" rows="2" maxlength="300" placeholder="<?php esc_attr_e( 'Preferred delivery time, styling request...', 'nura-experience' ); ?>"></textarea>
					</div>

					<div class="nwo-hp" aria-hidden="true">
						<label for="nwo-website">Website</label>
						<input id="nwo-website" name="website" type="text" tabindex="-1" autocomplete="off">
					</div>

					<p class="nwo-formerr" data-nwo-formerr role="alert" hidden></p>
					<button type="submit" class="nwo-submit"><?php echo self::wa_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Send order on WhatsApp', 'nura-experience' ); ?></span></button>
					<p class="nwo-small"><?php esc_html_e( 'No payment now. We confirm availability and delivery cost on WhatsApp first.', 'nura-experience' ); ?></p>
				</form>

				<div class="nwo-done" data-nwo-done hidden>
					<div class="nwo-done__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.2 4.2L19 7"/></svg></div>
					<h3 class="nwo-done__title" tabindex="-1"><?php esc_html_e( 'Your order is on its way to us', 'nura-experience' ); ?></h3>
					<p class="nwo-done__text"><?php esc_html_e( 'WhatsApp has opened with your order. Just tap send and a NURA stylist will confirm availability, delivery and payment.', 'nura-experience' ); ?></p>
					<p class="nwo-done__ref"><?php esc_html_e( 'Order reference', 'nura-experience' ); ?> <strong data-nwo-ref></strong></p>
					<a class="nwo-btn nwo-btn--pdp nwo-done__wa" data-nwo-again href="#" target="_blank" rel="noopener nofollow"><?php echo self::wa_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Open WhatsApp again', 'nura-experience' ); ?></span></a>
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
		$area     = isset( $in['area'] ) ? self::cut( sanitize_text_field( (string) $in['area'] ), 120 ) : '';
		$payment  = isset( $in['payment'] ) ? sanitize_text_field( (string) $in['payment'] ) : '';
		if ( ! in_array( $payment, self::payments(), true ) ) {
			$payment = '';
		}
		$delivery = 'pickup' === $fulfil ? self::pickup_text() : trim( $location . ( '' !== $area ? ', ' . $area : '' ), ', ' );
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
			'payment'  => $payment,
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

		/** Fires after a WhatsApp order lead is saved. */
		do_action( 'nurax_wa_order_saved', $post_id, $data );

		wp_send_json_success( array( 'ok' => 1 ) );
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
						<td><?php echo esc_html( isset( $d['payment'] ) ? $d['payment'] : '' ); ?></td>
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
