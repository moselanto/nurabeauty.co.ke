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
		// v1.57.0 NURA house shades (industry colour codes), most specific first.
		'black colour 1'  => '#0b0b0b',
		'burgundy 99j'    => '#5a0f22',
		'redwine 900'     => '#6b1428',
		'red wine 900'    => '#6b1428',
		'colour 1b'       => '#1c1714',
		'colour 1'        => '#0b0b0b',
		'colour 30'       => '#7a3b1a',
		'colour 33'       => '#4e1f14',
		'colour 130'      => '#a8431c',
		'colour 350'      => '#c25a1c',
		'colour 900'      => '#6b1428',
		'colour 99j'      => '#4a0d1a',
		'colour 613'      => '#e6c98f',
		'redwine'         => '#6b1428',
		'red wine'        => '#6b1428',
		'wine'            => '#6b1428',
		'1b'              => '#1c1714',
		'30'              => '#7a3b1a',
		'33'              => '#4e1f14',
		'130'             => '#a8431c',
		'350'             => '#c25a1c',
		'900'             => '#6b1428',
		// Descriptive names.
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
 * Normalise a colour label for matching: lower case, US "color" -> "colour",
 * separators to spaces, padded with spaces for whole-word matching.
 *
 * @param string $name Colour label or slug.
 * @return string
 */
function nura_colour_normalise( $name ) {
	$n = ' ' . strtolower( str_replace( array( '-', '/', '(', ')', '_', ',' ), ' ', (string) $name ) ) . ' ';
	$n = preg_replace( '/\bcolor\b/', 'colour', $n );
	$n = preg_replace( '/\bcol\.?\s/', 'colour ', $n );
	return preg_replace( '/\s+/', ' ', $n );
}

/**
 * Plain-language description of a NURA house shade code, shown in swatch
 * tooltips and the product-page colour guide so international shoppers know
 * what "Colour 33" or "1/350" looks like.
 *
 * @param string $name Colour label.
 * @return string Empty when no description applies.
 */
function nura_colour_hint( $name ) {
	$n = nura_colour_normalise( $name );
	$hints = array(
		' colour 1 900 '   => __( 'Black fading to red wine (ombre)', 'nura-beauty' ),
		' colour 1 350 '   => __( 'Black fading to bright copper (ombre)', 'nura-beauty' ),
		' black colour 1 ' => __( 'Jet black', 'nura-beauty' ),
		' burgundy 99j '   => __( 'Deep burgundy-plum', 'nura-beauty' ),
		' redwine 900 '    => __( 'Rich red wine', 'nura-beauty' ),
		' red wine 900 '   => __( 'Rich red wine', 'nura-beauty' ),
		' 613 blonde '     => __( 'Light golden blonde', 'nura-beauty' ),
		' colour 1b '      => __( 'Natural off-black', 'nura-beauty' ),
		' colour 1 '       => __( 'Jet black', 'nura-beauty' ),
		' colour 30 '      => __( 'Light auburn / copper brown', 'nura-beauty' ),
		' colour 33 '      => __( 'Dark auburn / reddish brown', 'nura-beauty' ),
		' colour 130 '     => __( 'Copper red', 'nura-beauty' ),
		' colour 350 '     => __( 'Bright copper', 'nura-beauty' ),
		' colour 900 '     => __( 'Rich red wine', 'nura-beauty' ),
		' ginger '         => __( 'Warm ginger orange', 'nura-beauty' ),
		' 99j '            => __( 'Deep burgundy-plum', 'nura-beauty' ),
		' 613 '            => __( 'Light golden blonde', 'nura-beauty' ),
	);
	foreach ( $hints as $needle => $hint ) {
		if ( false !== strpos( $n, $needle ) ) {
			return $hint;
		}
	}
	return '';
}

/**
 * Label plus description, e.g. "Colour 33 - Dark auburn / reddish brown".
 *
 * @param string $label Colour label.
 * @return string
 */
function nura_colour_title( $label ) {
	$hint = nura_colour_hint( $label );
	return $hint ? $label . ' - ' . $hint : $label;
}

/**
 * Detect the base colour hex codes present in a colour name, in order of appearance.
 *
 * @param string $name Colour label or slug.
 * @return string[] Hex codes.
 */
function nura_colour_bases( $name ) {
	$n = nura_colour_normalise( $name );
	// "Colour 1/900" -> "colour 1 900": the second code is a shade of its own.
	$n = preg_replace( '/ colour (1b|1|30|33|130|350|613|900|99j) (1b|30|33|130|350|613|900|99j) /', ' colour $1 colour $2 ', $n );
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
 * v1.57.0: a slash alone (e.g. "Redwine/900", one shade with two names) no
 * longer forces a gradient; two different shades ("Colour 1/350") still do.
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
	if ( count( $bases ) >= 2 ) {
		return 'linear-gradient(135deg,' . $bases[0] . ' 0%,' . $bases[0] . ' 45%,' . $bases[1] . ' 100%)';
	}
	$is_grad = (
		false !== strpos( $lower, 'ombre' ) ||
		false !== strpos( $lower, 'highlight' ) ||
		false !== strpos( $lower, 'money' ) ||
		false !== strpos( $lower, 'root' )
	);
	if ( $is_grad ) {
		return 'linear-gradient(135deg,' . $bases[0] . ' 0%,' . $bases[0] . ' 45%,#e6c98f 100%)';
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
	// v1.40.0: the hover photo is only downloaded when a mouse hovers the card (see main.js),
	// so phones never pay for an image they cannot see.
	$src = wp_get_attachment_image_src( $ids[0], 'woocommerce_single' );
	if ( ! $src ) {
		return;
	}
	printf(
		'<img class="nura-loop-2nd" data-src="%1$s" width="%2$d" height="%3$d" alt="" aria-hidden="true" decoding="async">',
		esc_url( $src[0] ),
		(int) $src[1],
		(int) $src[2]
	);
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
		echo '<div class="nura-swatch-dots nura-swatch-dots--empty" aria-hidden="true"></div>'; // keeps every card the same height
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
		echo '<div class="nura-swatch-dots nura-swatch-dots--empty" aria-hidden="true"></div>'; // keeps every card the same height
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
			esc_attr( nura_colour_title( $label ) )
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
				'<button type="button" class="nura-swatch" data-value="%1$s" aria-pressed="false" title="%4$s" aria-label="%4$s"><span class="nura-swatch__dot" style="background:%3$s" aria-hidden="true"></span><span class="nura-swatch__name">%2$s</span></button>',
				esc_attr( $value ),
				esc_html( $label ),
				esc_attr( nura_colour_style( $label ) ),
				esc_attr( nura_colour_title( $label ) )
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

	// v1.57.0 Colour guide: what each shade code looks like, for shoppers worldwide.
	if ( $is_colour ) {
		$guide = '';
		foreach ( $pairs as $pair ) {
			$hint = nura_colour_hint( $pair[1] );
			if ( $hint ) {
				$guide .= '<li><span class="nura-colour-guide__dot" style="background:' . esc_attr( nura_colour_style( $pair[1] ) ) . '" aria-hidden="true"></span><strong>' . esc_html( $pair[1] ) . '</strong><span>' . esc_html( $hint ) . '</span></li>';
			}
		}
		if ( $guide ) {
			echo '<details class="nura-colour-guide"><summary>' . esc_html__( 'Colour guide', 'nura-beauty' ) . '</summary><ul>' . $guide . '</ul><p>' . esc_html__( 'Shades can look slightly different on screen. Not sure? Ask us on WhatsApp for a live photo.', 'nura-beauty' ) . '</p></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
	}
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
