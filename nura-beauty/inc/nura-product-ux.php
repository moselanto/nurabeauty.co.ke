<?php
/**
 * NURA product-card + variation UX upgrades (v1.10.0).
 *
 * - Hover-reveal of the second gallery image on shop cards.
 * - "Sold out" flash on out-of-stock cards.
 * - Colour dots on variable-product cards (derived from the real Colour attribute).
 * - Premium swatch / pill variation selector on the product page, layered on top of
 *   the native <select> (progressive enhancement) so WooCommerce keeps handling
 *   price / image / availability / add-to-cart and never allows an invalid combo.
 *
 * Data-first (brief #29): colours come only from actual product attributes; when a
 * colour name is not recognised the swatch falls back to a neutral tone rather than
 * inventing a shade.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered colour keyword => hex map (most specific first so compound names win).
 *
 * @return array
 */
function nura_colour_map() {
	return array(
		'platinum white'  => '#efe9dd',
		'platinum blonde' => '#e8ddc6',
		'platinum'        => '#e8ddc6',
		'ash blonde'      => '#c9b79c',
		'golden blonde'   => '#dcae55',
		'golden honey'    => '#c68b45',
		'honey blonde'    => '#c98f4a',
		'honey brown'     => '#7a5230',
		'honey caramel'   => '#8a5a2e',
		'honey'           => '#c08b40',
		'caramel'         => '#8a5a2b',
		'613 blonde'      => '#e6c98f',
		'blonde 613'      => '#e6c98f',
		'613'             => '#e6c98f',
		'dark chocolate'  => '#2a180e',
		'chocolate'       => '#3a2416',
		'dark brown'      => '#3b2417',
		'brown'           => '#5c3a21',
		'natural black'   => '#141210',
		'jet black'       => '#000000',
		'black'           => '#0e0e0e',
		'copper'          => '#b06a2c',
		'ginger'          => '#b5651d',
		'orange'          => '#cc5500',
		'auburn'          => '#6e2f24',
		'burgundy'        => '#6d0a1c',
		'99j'             => '#4a0d1a',
		'plum'            => '#4a1a2e',
		'magenta'         => '#a0006d',
		'red'             => '#a4122b',
		'pastel pink'     => '#f4c2cf',
		'pink'            => '#e87ea1',
		'blonde'          => '#e6be8a',
	);
}

/**
 * Detect the base colour hex codes present in a colour name, in order of appearance.
 *
 * @param string $name Colour label or slug.
 * @return string[] Hex codes.
 */
function nura_colour_bases( $name ) {
	$n = ' ' . strtolower( str_replace( array( '-', '/', '(', ')' ), ' ', (string) $name ) ) . ' ';
	$n = preg_replace( '/\s+/', ' ', $n );
	$found = array();
	foreach ( nura_colour_map() as $kw => $hex ) {
		$needle = ' ' . $kw . ' ';
		$pos    = strpos( $n, $needle );
		if ( false !== $pos ) {
			$found[] = array( $pos, $hex );
			// Consume this span so component words don't match again.
			$n = substr( $n, 0, $pos ) . ' ' . str_repeat( '.', strlen( $kw ) ) . ' ' . substr( $n, $pos + strlen( $needle ) );
		}
	}
	usort( $found, function ( $a, $b ) { return $a[0] - $b[0]; } );
	$out = array();
	foreach ( $found as $f ) {
		if ( ! in_array( $f[1], $out, true ) ) {
			$out[] = $f[1];
		}
	}
	return $out;
}

/**
 * CSS background value for a colour swatch (solid hex, or a gradient for ombre /
 * highlighted / two-tone names). Neutral fallback when unrecognised.
 *
 * @param string $name Colour label or slug.
 * @return string CSS background value.
 */
function nura_colour_style( $name ) {
	$lower = strtolower( (string) $name );
	$bases = nura_colour_bases( $name );
	if ( empty( $bases ) ) {
		return '#cab196';
	}
	$is_grad = (
		false !== strpos( $lower, 'ombre' ) ||
		false !== strpos( $lower, 'highlight' ) ||
		false !== strpos( $lower, 'money' ) ||
		false !== strpos( $lower, 'root' ) ||
		false !== strpos( $lower, ' to ' ) ||
		false !== strpos( $lower, '-to-' ) ||
		false !== strpos( (string) $name, '/' ) ||
		count( $bases ) >= 2
	);
	if ( $is_grad ) {
		$a = $bases[0];
		$b = isset( $bases[1] ) ? $bases[1] : '#e6c98f';
		return 'linear-gradient(135deg,' . $a . ' 0%,' . $a . ' 45%,' . $b . ' 100%)';
	}
	return $bases[0];
}

/**
 * Reveal the second gallery image on hover (shop cards). Uses the loop-sized image,
 * lazy-loaded, so it never pulls a full-resolution file for every card (brief #11).
 */
function nura_loop_second_image() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$ids = $product->get_gallery_image_ids();
	if ( empty( $ids ) ) {
		return;
	}
	$img = wp_get_attachment_image(
		$ids[0],
		'woocommerce_single',
		false,
		array( 'class' => 'nura-loop-2nd', 'aria-hidden' => 'true', 'alt' => '', 'loading' => 'lazy' )
	);
	if ( $img ) {
		echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'woocommerce_before_shop_loop_item_title', 'nura_loop_second_image', 11 );

/**
 * "Sold out" flash on out-of-stock shop cards.
 */
function nura_loop_soldout_flash() {
	global $product;
	if ( ! $product || $product->is_in_stock() ) {
		return;
	}
	echo '<span class="nura-soldout">' . esc_html__( 'Sold out', 'nura-beauty' ) . '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'nura_loop_soldout_flash', 12 );

/**
 * Colour dots on variable-product shop cards.
 */
function nura_loop_colour_dots() {
	global $product;
	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		return;
	}
	$attrs   = $product->get_variation_attributes();
	$colours = array();
	$tax     = '';
	foreach ( $attrs as $key => $values ) {
		$k = strtolower( $key );
		if ( false !== strpos( $k, 'colour' ) || false !== strpos( $k, 'color' ) ) {
			$colours = $values;
			$tax     = $key;
			break;
		}
	}
	if ( empty( $colours ) ) {
		return;
	}
	$is_tax = taxonomy_exists( $tax );
	echo '<div class="nura-swatch-dots" aria-label="' . esc_attr__( 'Available shades', 'nura-beauty' ) . '">';
	$max = 6;
	$i   = 0;
	foreach ( $colours as $value ) {
		if ( $i >= $max ) {
			break;
		}
		$label = $value;
		if ( $is_tax ) {
			$term = get_term_by( 'slug', $value, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$label = $term->name;
			}
		}
		printf(
			'<span class="nura-dot" style="background:%s" title="%s"></span>',
			esc_attr( nura_colour_style( $label ) ),
			esc_attr( $label )
		);
		$i++;
	}
	$count = count( $colours );
	if ( $count > $max ) {
		printf( '<span class="nura-dot-more">+%d</span>', absint( $count - $max ) );
	}
	echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'nura_loop_colour_dots', 9 );

/**
 * Premium swatch / pill selector layered on the native variation <select>.
 * The <select> stays in the DOM (hidden when JS is active) so WooCommerce keeps
 * driving price/image/availability and blocks invalid combinations.
 *
 * @param string $html Original <select> markup.
 * @param array  $args Attribute args.
 * @return string
 */
function nura_variation_swatches( $html, $args ) {
	$attribute = isset( $args['attribute'] ) ? $args['attribute'] : '';
	$options   = isset( $args['options'] ) ? $args['options'] : array();
	$product   = isset( $args['product'] ) ? $args['product'] : null;

	if ( empty( $options ) || ! $product instanceof WC_Product ) {
		return $html;
	}

	$is_colour = ( false !== stripos( $attribute, 'colour' ) || false !== stripos( $attribute, 'color' ) );
	$is_tax    = taxonomy_exists( $attribute );

	// Build [value, label] pairs, preserving the product's attribute order.
	$pairs = array();
	if ( $is_tax ) {
		$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
		foreach ( $terms as $term ) {
			if ( in_array( $term->slug, $options, true ) ) {
				$pairs[] = array( $term->slug, $term->name );
			}
		}
	} else {
		foreach ( $options as $opt ) {
			$pairs[] = array( $opt, $opt );
		}
	}
	if ( empty( $pairs ) ) {
		return $html;
	}

	ob_start();
	printf(
		'<div class="nura-swatches %s" data-nura-swatches role="group" aria-label="%s">',
		$is_colour ? 'nura-swatches--colour' : 'nura-swatches--pill',
		esc_attr( wc_attribute_label( $attribute, $product ) )
	);
	foreach ( $pairs as $pair ) {
		$value = $pair[0];
		$label = $pair[1];
		if ( $is_colour ) {
			printf(
				'<button type="button" class="nura-swatch" data-value="%1$s" aria-pressed="false" title="%2$s" aria-label="%2$s"><span class="nura-swatch__dot" style="background:%3$s" aria-hidden="true"></span><span class="nura-swatch__name">%2$s</span></button>',
				esc_attr( $value ),
				esc_attr( $label ),
				esc_attr( nura_colour_style( $label ) )
			);
		} else {
			printf(
				'<button type="button" class="nura-pill" data-value="%1$s" aria-pressed="false">%2$s</button>',
				esc_attr( $value ),
				esc_html( $label )
			);
		}
	}
	echo '</div>';
	return $html . ob_get_clean();
}
add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'nura_variation_swatches', 20, 2 );

/**
 * Hide the non-variation pa_colour facet taxonomy from the product "Additional
 * information" tab (v1.11.0). pa_colour is a colour-family taxonomy added purely to
 * power the shop Colour filter; the exact per-variation shade is already shown in the
 * swatch selector, so listing pa_colour again in the specs would duplicate it.
 *
 * @param array      $attributes Display attribute rows keyed by attribute_{name}.
 * @param WC_Product $product    Product (unused; signature required by the filter).
 * @return array
 */
function nura_hide_colour_facet_attribute( $attributes, $product ) {
	unset( $attributes['attribute_pa_colour'], $attributes['attribute_pa_color'] );
	return $attributes;
}
add_filter( 'woocommerce_display_product_attributes', 'nura_hide_colour_facet_attribute', 20, 2 );
