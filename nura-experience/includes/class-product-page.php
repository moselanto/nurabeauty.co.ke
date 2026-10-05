<?php
/**
 * NURA Product Page.
 *
 * Upgrades the single-product page without overriding WooCommerce templates:
 *   - A clean attribute "spec strip" in the summary (Hair Type, Construction,
 *     Texture, Length, Density, Colour, Lace, Cap Size, Origin).
 *   - Structured content tabs: Hair details (+ what's included + length guide),
 *     Care & install, Shipping & returns, Warranty.
 *   - "Complete your NURA look" cross-sell row (cross-sells -> upsells -> Wig
 *     Care fallback).
 *   - Fixes the hardcoded "100% human hair" reassurance line so it reflects the
 *     product's real Hair Type attribute (no false claim on blends/synthetic).
 *
 * Native WooCommerce variations already update price/image/stock on selection,
 * so no custom variation JS is needed here.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Product_Page {

	/** Guard so the action-row CSS prints only once per page. */
	private static $actions_css = false;

	public function __construct() {
		// Attribute spec strip inside the summary (after excerpt, before add-to-cart).
		add_action( 'woocommerce_single_product_summary', array( $this, 'spec_strip' ), 25 );

		// Complete-the-look cross-sell row.
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'complete_look' ), 12 );

		// Structured content tabs.
		add_filter( 'woocommerce_product_tabs', array( $this, 'tabs' ) );

		// Replace the theme's hardcoded trust line with an attribute-aware one.
		add_action( 'wp', array( $this, 'retarget_trust' ) );

		// Interactive gallery (zoom + lightbox + slider) for multi-image units.
		add_action( 'after_setup_theme', array( $this, 'gallery_support' ), 20 );

		// One-click "add the full care set" bundle handler.
		add_action( 'template_redirect', array( $this, 'handle_bundle' ) );

		// Consolidated single-product action row (Try it on + Order on WhatsApp),
		// rendered just after the add-to-cart button so both sit in a tidy row.
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'secondary_actions' ), 20 );
	}

	/**
	 * Enable WooCommerce's interactive gallery so multiple product images
	 * (front / back / lace / hairline / parting) get thumbnails, a slider and
	 * click-to-zoom / lightbox. Only added if the theme has not opted in, so a
	 * deliberate theme choice is respected.
	 */
	public function gallery_support() {
		foreach ( array( 'wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider' ) as $feature ) {
			if ( ! current_theme_supports( $feature ) ) {
				add_theme_support( $feature );
			}
		}
	}

	/** Map of attribute taxonomy => label, in display order. */
	private function attr_map() {
		return array(
			'pa_hair-type'    => __( 'Hair Type', 'nura-experience' ),
			'pa_construction' => __( 'Construction', 'nura-experience' ),
			'pa_texture'      => __( 'Texture', 'nura-experience' ),
			'pa_length'       => __( 'Length', 'nura-experience' ),
			'pa_density'      => __( 'Density', 'nura-experience' ),
			'pa_colour'       => __( 'Colour', 'nura-experience' ),
			'pa_lace'         => __( 'Lace Type', 'nura-experience' ),
			'pa_cap-size'     => __( 'Cap Size', 'nura-experience' ),
			'pa_origin'       => __( 'Hair Origin', 'nura-experience' ),
		);
	}

	/** Non-empty attribute rows for a product: [ label => value ]. */
	private function specs( $product ) {
		$rows = array();
		foreach ( $this->attr_map() as $tax => $label ) {
			$val = $product->get_attribute( $tax );
			if ( '' !== (string) $val ) {
				$rows[ $label ] = $val;
			}
		}
		return $rows;
	}

	/** Compact key-spec strip in the product summary. */
	public function spec_strip() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$rows = $this->specs( $product );
		if ( empty( $rows ) ) {
			return;
		}
		echo '<ul class="nura-specs">';
		foreach ( $rows as $label => $val ) {
			echo '<li><span class="nura-specs__k">' . esc_html( $label ) . '</span><span class="nura-specs__v">' . esc_html( $val ) . '</span></li>';
		}
		echo '</ul>';
	}

	/** Swap the theme trust strip for an attribute-aware version on product pages. */
	public function retarget_trust() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			remove_action( 'woocommerce_single_product_summary', 'nura_trust_strip', 35 );
			add_action( 'woocommerce_single_product_summary', array( $this, 'trust_strip' ), 35 );
		}
	}

	/** Reassurance strip whose first line reflects the real Hair Type. */
	public function trust_strip() {
		global $product;
		$hair  = ( $product instanceof WC_Product ) ? $product->get_attribute( 'pa_hair-type' ) : '';
		$first = $hair
			? sprintf( /* translators: %s: hair type, e.g. 100% Human Hair */ __( '%s, quality guaranteed', 'nura-experience' ), $hair )
			: __( 'Premium quality, quality guaranteed', 'nura-experience' );

		// Delivery + payment mirror the store's real WooCommerce shipping zones
		// and enabled gateways (theme helpers in inc/nura-commerce.php), so the
		// reassurance strip never promises what checkout does not offer. Both
		// fall back to the original curated copy when the helpers are absent.
		$delivery      = function_exists( 'nura_delivery_lines' ) ? nura_delivery_lines( 1 ) : array();
		$delivery_line = ! empty( $delivery ) ? $delivery[0] : __( 'Same-day delivery in Nairobi', 'nura-experience' );
		$pay           = function_exists( 'nura_payment_summary' ) ? nura_payment_summary( '' ) : '';
		$pay_line      = '' !== $pay
			? sprintf( /* translators: %s: list of payment methods, e.g. M-Pesa, Card & Pay on Delivery. */ __( 'Pay with %s', 'nura-experience' ), $pay )
			: __( 'Pay with M-Pesa, card or on delivery', 'nura-experience' );

		$items = array(
			$first,
			$delivery_line,
			$pay_line,
			__( 'Free virtual wig consultation', 'nura-experience' ),
		);
		echo '<ul class="nura-trust">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	/** Add structured content tabs. */
	public function tabs( $tabs ) {
		$tabs['nura_hair'] = array(
			'title'    => __( 'Hair details', 'nura-experience' ),
			'priority' => 15,
			'callback' => array( $this, 'tab_hair' ),
		);
		$tabs['nura_care'] = array(
			'title'    => __( 'Care & install', 'nura-experience' ),
			'priority' => 25,
			'callback' => array( $this, 'tab_care' ),
		);
		$tabs['nura_shipping'] = array(
			'title'    => __( 'Shipping & returns', 'nura-experience' ),
			'priority' => 30,
			'callback' => array( $this, 'tab_shipping' ),
		);
		$tabs['nura_warranty'] = array(
			'title'    => __( 'Warranty', 'nura-experience' ),
			'priority' => 35,
			'callback' => array( $this, 'tab_warranty' ),
		);
		return $tabs;
	}

	public function tab_hair() {
		global $product;
		if ( $product instanceof WC_Product ) {
			$rows = $this->specs( $product );
			if ( $rows ) {
				echo '<table class="nura-spec-table"><tbody>';
				foreach ( $rows as $label => $val ) {
					echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		}
		echo '<h4>' . esc_html__( "What's included", 'nura-experience' ) . '</h4>';
		echo '<ul class="nura-inc"><li>' . esc_html__( 'Your NURA unit', 'nura-experience' ) . '</li><li>' . esc_html__( 'Provenance note', 'nura-experience' ) . '</li><li>' . esc_html__( 'Written longevity guarantee', 'nura-experience' ) . '</li><li>' . esc_html__( 'Satin storage bag', 'nura-experience' ) . '</li></ul>';
		echo '<p class="nura-note">' . esc_html__( 'Length guide: lengths are measured straightened, tip to tip. Curly and wavy textures appear shorter once styled.', 'nura-experience' ) . '</p>';
	}

	public function tab_care() {
		echo '<h4>' . esc_html__( 'How to care for your NURA wig', 'nura-experience' ) . '</h4>';
		echo '<ul class="nura-inc">';
		echo '<li>' . esc_html__( 'Wash and revamp every 6-8 weeks with a sulfate-free shampoo and a moisturising conditioner.', 'nura-experience' ) . '</li>';
		echo '<li>' . esc_html__( 'Air-dry on a wig stand; avoid excessive heat and always use a heat protectant.', 'nura-experience' ) . '</li>';
		echo '<li>' . esc_html__( 'Store in the satin bag provided to keep the hair and lace fresh.', 'nura-experience' ) . '</li>';
		echo '</ul>';
		echo '<h4>' . esc_html__( 'How to install', 'nura-experience' ) . '</h4>';
		echo '<p>' . esc_html__( 'Glueless and wear-and-go units fit straight out of the box using the adjustable straps and combs. For lace units, book a NURA installation and our stylists will fit and style it for you.', 'nura-experience' ) . '</p>';
	}

	public function tab_shipping() {
		echo '<ul class="nura-inc">';
		echo '<li>' . esc_html__( 'Free same-day delivery in Nairobi on orders over KES 10,000.', 'nura-experience' ) . '</li>';
		echo '<li>' . esc_html__( 'Countrywide delivery in 1-3 business days.', 'nura-experience' ) . '</li>';
		echo '<li>' . esc_html__( 'International shipping available on request.', 'nura-experience' ) . '</li>';
		echo '<li>' . esc_html__( 'Pay with M-Pesa, card, or on delivery within Nairobi.', 'nura-experience' ) . '</li>';
		echo '<li>' . esc_html__( 'Unworn units in original condition may be returned within 7 days; custom units are made to order.', 'nura-experience' ) . '</li>';
		echo '</ul>';
	}

	public function tab_warranty() {
		echo '<p>' . esc_html__( 'Every NURA unit ships with a provenance note and a written longevity guarantee. Register your unit in The NURA Circle to store your warranty certificate, care schedule and revamp reminders.', 'nura-experience' ) . '</p>';
	}

	/** "Complete your NURA look" cross-sell / care row. */
	public function complete_look() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$ids = $product->get_cross_sell_ids();
		if ( empty( $ids ) ) {
			$ids = $product->get_upsell_ids();
		}
		if ( empty( $ids ) && function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products( array(
				'status'   => 'publish',
				'limit'    => 4,
				'category' => array( 'wig-care' ),
				'return'   => 'ids',
			) );
		}
		$ids = array_values( array_diff( array_map( 'absint', (array) $ids ), array( $product->get_id() ) ) );
		$ids = array_slice( $ids, 0, 4 );
		if ( empty( $ids ) ) {
			return;
		}
		echo '<section class="nura-complete"><div class="nura-container">';
		echo '<div class="nura-shead"><p class="nura-eyebrow">' . esc_html__( 'Finish the ritual', 'nura-experience' ) . '</p><h2>' . esc_html__( 'Complete your NURA look', 'nura-experience' ) . '</h2></div>';
		echo do_shortcode( '[products ids="' . esc_attr( implode( ',', $ids ) ) . '" columns="4" limit="4" class="nura-complete-products"]' );

		// One-click "add the full set" bar: only the simple, purchasable,
		// in-stock items can be added straight to cart (variable units need a
		// chosen variation, so they are left for the shopper to configure).
		$bundle = array_values( array_filter( $ids, function ( $id ) {
			$p = wc_get_product( $id );
			return $p && $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock();
		} ) );
		if ( count( $bundle ) >= 2 ) {
			$total = 0.0;
			foreach ( $bundle as $id ) {
				$p = wc_get_product( $id );
				if ( $p ) {
					$total += (float) wc_get_price_to_display( $p );
				}
			}
			$url = wp_nonce_url(
				add_query_arg( 'nura_bundle', implode( ',', $bundle ), get_permalink( $product->get_id() ) ),
				'nura_bundle',
				'nura_bundle_nonce'
			);
			echo '<div class="nura-bundle-bar">';
			echo '<span class="nura-bundle-bar__txt">' . esc_html__( 'Add the full care set to your cart', 'nura-experience' ) . '</span>';
			echo '<a class="nura-btn nura-btn--gold nura-bundle-bar__btn" href="' . esc_url( $url ) . '">'
				. esc_html( sprintf( /* translators: 1: item count, 2: total price */ __( 'Add %1$d items to cart', 'nura-experience' ), count( $bundle ) ) )
				. ' <span class="nura-bundle-bar__price">' . wp_kses_post( wc_price( $total ) ) . '</span></a>';
			echo '</div>';
		}

		echo '</div></section>';
	}

	/**
	 * Handle the one-click bundle link: add each simple item to the cart and
	 * redirect to the cart page. A configured coupon (via the nurax_bundle_coupon
	 * filter) is applied if present, so any "set" saving is a real WooCommerce
	 * discount rather than a fabricated number.
	 */
	public function handle_bundle() {
		if ( empty( $_GET['nura_bundle'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! isset( $_GET['nura_bundle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nura_bundle_nonce'] ) ), 'nura_bundle' ) ) {
			return;
		}
		$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['nura_bundle'] ) ) ) ) );
		$added = 0;
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p && $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock() ) {
				if ( WC()->cart->add_to_cart( $id ) ) {
					$added++;
				}
			}
		}
		$coupon = (string) apply_filters( 'nurax_bundle_coupon', '' );
		if ( $added && '' !== $coupon && ! WC()->cart->has_discount( $coupon ) ) {
			WC()->cart->apply_coupon( $coupon );
		}
		if ( $added && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( sprintf( /* translators: %d: item count */ _n( '%d item added to your cart.', '%d items added to your cart.', $added, 'nura-experience' ), $added ) );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Single-product action row: Try it on + Order on WhatsApp            */
	/* ------------------------------------------------------------------ */

	/** Resolve the store WhatsApp number to digits (with a public fallback). */
	private function whatsapp_number() {
		$raw = '';
		if ( class_exists( 'NURAX_Settings' ) ) {
			$raw = (string) NURAX_Settings::get( 'whatsapp', '' );
		}
		if ( '' === $raw ) {
			$raw = (string) get_theme_mod( 'nura_whatsapp', '' );
		}
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( '' === $digits ) {
			$digits = '254714994898'; // NURA's public WhatsApp number.
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = '254' . substr( $digits, 1 );
		}
		/** Allow overriding the destination WhatsApp number (digits only). */
		return preg_replace( '/\D+/', '', (string) apply_filters( 'nurax_whatsapp_number', $digits ) );
	}

	/** Render "Try it on" + "Order on WhatsApp" in one aligned row under add-to-cart. */
	public function secondary_actions() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$buttons = '';

		// Try it on -> the Virtual Try-On page preloaded with this product.
		$vto = get_page_by_path( 'virtual-try-on' );
		if ( $vto ) {
			$url      = add_query_arg( 'tryon', $product->get_id(), get_permalink( $vto->ID ) );
			$buttons .= sprintf(
				'<a class="nura-btn nura-btn--ghost nura-pdp-actions__btn" href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Try it on', 'nura-experience' )
			);
		}

		// Order on WhatsApp -> prefilled message with the product name + link.
		$num = $this->whatsapp_number();
		if ( $num ) {
			$msg = sprintf(
				/* translators: 1: product name, 2: product URL */
				__( "Hi NURA, I'd like to order the %1\$s (%2\$s). Is it available?", 'nura-experience' ),
				$product->get_name(),
				get_permalink( $product->get_id() )
			);
			$wa       = 'https://wa.me/' . $num . '?text=' . rawurlencode( $msg );
			$buttons .= sprintf(
				'<a class="nura-btn nura-btn--wa nura-pdp-actions__btn" href="%s" target="_blank" rel="noopener nofollow">%s</a>',
				esc_url( $wa ),
				esc_html__( 'Order on WhatsApp', 'nura-experience' )
			);
		}

		if ( '' === $buttons ) {
			return;
		}

		echo '<div class="nura-pdp-actions">' . $buttons . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* helpers above.

		if ( ! self::$actions_css ) {
			self::$actions_css = true;
			echo '<style>'
				. '.nura-pdp-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin:1rem 0 0;clear:both;width:100%}'
				. '.nura-pdp-actions__btn{flex:1 1 200px;display:inline-flex;align-items:center;justify-content:center;text-align:center;min-height:48px;padding:.7rem 1.2rem;border-radius:6px;text-decoration:none}'
				. '.nura-btn--wa{background:#25D366;color:#0b3d24;border:1px solid #1da851;font-weight:700}'
				. '.nura-btn--wa:hover{background:#1ebe5b}'
				. '</style>';
		}
	}
}
