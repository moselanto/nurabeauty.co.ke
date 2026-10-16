<?php
/**
 * NURA growth layer (v1.58.0): keep shoppers exploring, help them decide, and
 * give search engines richer, truthful product data.
 *
 * 1. Homepage FAQ price answer is calculated from the live catalogue, so the
 *    visible answer and the FAQPage schema never contradict real prices.
 * 2. Product "Additional information" table: duplicate rows removed.
 * 3. Delivery promise on product pages: live Nairobi same-day cut-off
 *    countdown (Africa/Nairobi time) + real low-stock notice (stock 1-3).
 * 4. "Recently viewed" rail on product, shop and home pages (browser-only,
 *    cache-safe, no cookies sent to the server).
 * 5. Product schema: additionalProperty (hair type, texture, length, density,
 *    lace, cap) and priceValidUntil on offers.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * 1. Truthful "How much is a wig in Kenya?" answer.
 * ------------------------------------------------------------------------- */

/**
 * Lowest and highest live price per hair type (human / blend / fibre).
 *
 * @return array e.g. array( 'fibre' => array( 1500, 4900 ), ... )
 */
function nura_growth_price_bands() {
	$cached = get_transient( 'nura_growth_bands_v1' );
	if ( false !== $cached ) {
		return $cached;
	}
	$bands = array();
	if ( function_exists( 'wc_get_products' ) && function_exists( 'nura_hair_types' ) ) {
		$ids = wc_get_products( array( 'limit' => 500, 'status' => 'publish', 'return' => 'ids', 'stock_status' => 'instock' ) );
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$lo = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) $p->get_price();
			$hi = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'max', true ) : $lo;
			if ( $lo <= 0 ) {
				continue;
			}
			$types = (array) nura_hair_types( $p );
			if ( 1 !== count( $types ) ) {
				continue; // Mixed or unknown: do not let it skew a band.
			}
			$t = $types[0];
			if ( ! isset( $bands[ $t ] ) ) {
				$bands[ $t ] = array( $lo, $hi );
			} else {
				$bands[ $t ][0] = min( $bands[ $t ][0], $lo );
				$bands[ $t ][1] = max( $bands[ $t ][1], $hi );
			}
		}
	}
	set_transient( 'nura_growth_bands_v1', $bands, 6 * HOUR_IN_SECONDS );
	return $bands;
}
add_action( 'save_post_product', function () {
	delete_transient( 'nura_growth_bands_v1' );
} );
add_action( 'woocommerce_product_set_stock_status', function () {
	delete_transient( 'nura_growth_bands_v1' );
} );

/** "KSh 1,500" */
function nura_growth_ksh( $n ) {
	return 'KSh ' . number_format_i18n( (float) $n, 0 );
}

add_filter( 'nura_home_faqs', function ( $faqs ) {
	$b = nura_growth_price_bands();
	if ( empty( $b ) || empty( $faqs[0] ) ) {
		return $faqs;
	}
	$parts = array();
	if ( isset( $b['fibre'] ) ) {
		/* translators: 1: lowest price, 2: highest price. */
		$parts[] = sprintf( __( 'heat-resistant fibre wigs cost %1$s to %2$s', 'nura-beauty' ), nura_growth_ksh( $b['fibre'][0] ), nura_growth_ksh( $b['fibre'][1] ) );
	}
	if ( isset( $b['blend'] ) ) {
		/* translators: 1: lowest price, 2: highest price. */
		$parts[] = sprintf( __( 'human-hair blend (semi-human) wigs %1$s to %2$s', 'nura-beauty' ), nura_growth_ksh( $b['blend'][0] ), nura_growth_ksh( $b['blend'][1] ) );
	}
	if ( isset( $b['human'] ) ) {
		/* translators: 1: lowest price, 2: highest price. */
		$parts[] = sprintf( __( '100%% human hair wigs %1$s to %2$s depending on length', 'nura-beauty' ), nura_growth_ksh( $b['human'][0] ), nura_growth_ksh( $b['human'][1] ) );
	}
	if ( ! $parts ) {
		return $faqs;
	}
	$faqs[0][1] = sprintf(
		/* translators: %s: list of price ranges. */
		__( 'At NURA, %s. Every price is shown in Kenya Shillings on the product page, and you can pay by M-Pesa, card or cash on delivery in Nairobi.', 'nura-beauty' ),
		implode( '; ', $parts )
	);
	return $faqs;
}, 20 );

/* ---------------------------------------------------------------------------
 * 2. No duplicate rows in "Additional information".
 * ------------------------------------------------------------------------- */
add_filter( 'woocommerce_display_product_attributes', function ( $attrs ) {
	if ( ! is_array( $attrs ) ) {
		return $attrs;
	}
	$seen = array();
	foreach ( $attrs as $key => $row ) {
		$label = isset( $row['label'] ) ? strtolower( trim( wp_strip_all_tags( (string) $row['label'] ) ) ) : '';
		$label = str_replace( array( 'construction', 'lace type' ), 'lace', $label );
		if ( '' === $label ) {
			continue;
		}
		if ( isset( $seen[ $label ] ) ) {
			unset( $attrs[ $key ] );
			continue;
		}
		$seen[ $label ] = true;
	}
	return $attrs;
}, 99 );

/* ---------------------------------------------------------------------------
 * 3. Delivery promise + real low-stock notice under the buy buttons.
 * ------------------------------------------------------------------------- */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product instanceof WC_Product || ! $product->is_in_stock() ) {
		return;
	}
	echo '<div class="nura-promise">';
	echo '<p class="nura-promise__line nura-promise__line--delivery" data-nura-cutoff="17">';
	echo '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/></svg>';
	echo '<span class="nura-promise__text">' . esc_html__( 'Same-day delivery in Nairobi on orders before 5pm. Countrywide in 1-3 days.', 'nura-beauty' ) . '</span></p>';
	if ( $product->managing_stock() ) {
		$qty = (int) $product->get_stock_quantity();
		if ( $qty > 0 && $qty <= 3 ) {
			/* translators: %d: units left. */
			echo '<p class="nura-promise__line nura-promise__line--stock"><span class="nura-promise__pulse" aria-hidden="true"></span>' . esc_html( sprintf( _n( 'Only %d left in stock', 'Only %d left in stock', $qty, 'nura-beauty' ), $qty ) ) . '</p>';
		}
	}
	echo '<p class="nura-promise__line nura-promise__line--secure"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>' . esc_html__( 'Secure M-Pesa and card checkout. 7-day returns on unworn units.', 'nura-beauty' ) . '</span></p>';
	echo '</div>';
}, 32 );

/* ---------------------------------------------------------------------------
 * 4. Recently viewed (browser-side, cache-safe).
 * ------------------------------------------------------------------------- */

/** Data for the product being viewed, read by nura-growth.js. */
add_action( 'woocommerce_after_single_product', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$img  = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
	$data = array(
		'id'    => $product->get_id(),
		'name'  => $product->get_name(),
		'url'   => get_permalink( $product->get_id() ),
		'img'   => $img ? $img : '',
		'price' => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ),
	);
	echo '<script type="application/json" id="nura-rv-current">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded with HEX flags.
} );

/** Empty rail shell; nura-growth.js fills it, or it stays hidden. */
function nura_growth_rv_rail( $context = 'product' ) {
	echo '<section class="nura-rv nura-rv--' . esc_attr( $context ) . '" data-nura-rv hidden aria-labelledby="nura-rv-h-' . esc_attr( $context ) . '">';
	echo '<div class="nura-container"><div class="nura-rv__head"><p class="nura-eyebrow">' . esc_html__( 'Pick up where you left off', 'nura-beauty' ) . '</p>';
	echo '<h2 id="nura-rv-h-' . esc_attr( $context ) . '">' . esc_html__( 'Recently viewed', 'nura-beauty' ) . '</h2>';
	echo '<button type="button" class="nura-rv__clear" data-nura-rv-clear>' . esc_html__( 'Clear', 'nura-beauty' ) . '</button></div>';
	echo '<div class="nura-rv__track" data-nura-rv-track></div></div></section>';
}
add_action( 'woocommerce_after_single_product_summary', function () {
	nura_growth_rv_rail( 'product' );
}, 25 );
add_action( 'woocommerce_after_shop_loop', function () {
	nura_growth_rv_rail( 'shop' );
}, 30 );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'nura-growth', NURA_URI . 'assets/js/nura-growth.js', array(), NURA_VERSION, true );
	wp_localize_script( 'nura-growth', 'nuraGrowth', array(
		'sameDay'  => __( 'Order within %s for same-day delivery in Nairobi.', 'nura-beauty' ),
		'nextDay'  => __( 'Order now for next-day delivery in Nairobi. Countrywide in 1-3 days.', 'nura-beauty' ),
		'hours'    => __( '%1$dh %2$dm', 'nura-beauty' ),
		'minutes'  => __( '%dm', 'nura-beauty' ),
		'viewItem' => __( 'View', 'nura-beauty' ),
	) );
}, 20 );

/* ---------------------------------------------------------------------------
 * 5. Richer, truthful Product schema.
 * ------------------------------------------------------------------------- */
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $data;
	}
	$p = wc_get_product( get_queried_object_id() );
	if ( ! $p ) {
		return $data;
	}
	$props = array();
	$map   = array(
		'Hair type' => array( 'pa_hair-type', 'hair-type' ),
		'Texture'   => array( 'pa_texture', 'texture' ),
		'Length'    => array( 'pa_length', 'length' ),
		'Density'   => array( 'pa_density', 'density' ),
		'Lace'      => array( 'pa_lace-type', 'lace-type', 'pa_cap-construction', 'construction' ),
		'Cap size'  => array( 'pa_cap-size', 'cap-size' ),
	);
	foreach ( $map as $name => $keys ) {
		foreach ( $keys as $k ) {
			$v = trim( (string) $p->get_attribute( $k ) );
			if ( '' !== $v ) {
				$props[] = array( '@type' => 'PropertyValue', 'name' => $name, 'value' => $v );
				break;
			}
		}
	}
	$valid = gmdate( 'Y' ) + 1 . '-12-31';
	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		if ( ! array_intersect( (array) $node['@type'], array( 'Product', 'ProductGroup' ) ) ) {
			continue;
		}
		if ( $props && empty( $node['additionalProperty'] ) ) {
			$data[ $key ]['additionalProperty'] = $props;
		}
		if ( empty( $node['brand'] ) ) {
			$data[ $key ]['brand'] = array( '@type' => 'Brand', 'name' => 'NURA Beauty' );
		}
		if ( isset( $node['offers'] ) && is_array( $node['offers'] ) && isset( $node['offers']['@type'] ) && empty( $node['offers']['priceValidUntil'] ) ) {
			$data[ $key ]['offers']['priceValidUntil'] = $valid;
		}
	}
	return $data;
}, 120 );
