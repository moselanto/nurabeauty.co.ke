<?php
/**
 * NURA Beauty 1.128.0 - product specifications and shop structure.
 *
 * 1. One "Specifications" section on product pages. Replaces the plugin's spec
 *    strip, the theme's spec chips, the "Good to know" box, the spec table in
 *    the "Hair details" tab and the WooCommerce "Additional information" tab,
 *    which all repeated texture, length and lace. Nothing is lost: every
 *    attribute (global and custom), plus "Who it's for", "Lasts about" and
 *    "Care", now sits in one list. The tab keeps "What's included" and the
 *    length guide. Off switch: add_filter( 'nura_specs_unified', '__return_false' ).
 *
 * 2. Shop split: one category bar "Wigs | Wig Care | All products" (with the
 *    wig sub-categories as a second row inside the Wigs branch), the theme's
 *    "Filter & Sort" panel as the only filter style (the plugin sidebar and its
 *    extra "Filters" button are removed), and on /shop/ wigs are listed first
 *    with a "Wig care" heading before the care products.
 *    Off switch: add_filter( 'nura_shop_split', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Remove a hook callback registered by an object of $class (e.g. the plugin). */
function nura_remove_object_hook( $hook, $class, $method, $priority ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
		return false;
	}
	foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $cb ) {
		$f = $cb['function'];
		if ( is_array( $f ) && is_object( $f[0] ) && $f[0] instanceof $class && $method === $f[1] ) {
			remove_action( $hook, $f, $priority );
			return true;
		}
	}
	return false;
}

/* ---------------------------------------------------------------------------
 * 1. Specifications.
 * ------------------------------------------------------------------------- */

function nura_specs_unified() {
	return (bool) apply_filters( 'nura_specs_unified', true );
}

add_action( 'wp', function () {
	if ( ! nura_specs_unified() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	nura_remove_object_hook( 'woocommerce_single_product_summary', 'NURAX_Product_Page', 'spec_strip', 25 );
	remove_action( 'woocommerce_single_product_summary', 'nura_product_spec_chips', 25 );
	add_filter( 'nura_good_to_know', '__return_false' );
	add_action( 'woocommerce_single_product_summary', 'nura_specs_render', 25 );
}, 30 );

/** Label normaliser so custom attributes never repeat a global one. */
function nura_specs_key( $label ) {
	$k   = strtolower( trim( preg_replace( '/[^a-z ]/i', '', (string) $label ) ) );
	$map = array(
		'hair material' => 'hair type',
		'hair'          => 'hair type',
		'lace'          => 'lace type',
		'color'         => 'colour',
		'colours'       => 'colour',
		'colors'        => 'colour',
		'colours available' => 'colour',
		'cap'           => 'cap size',
		'origin'        => 'hair origin',
	);
	return isset( $map[ $k ] ) ? $map[ $k ] : $k;
}

/** Ordered rows: label => value. */
function nura_specs_rows( $product ) {
	$rows = array();
	if ( ! $product instanceof WC_Product ) {
		return $rows;
	}
	$hair = function_exists( 'nura_hub_hair' ) && function_exists( 'nura_hub_is_wig' ) && nura_hub_is_wig( $product ) ? nura_hub_hair( $product ) : '';
	$name = strtolower( $product->get_name() );

	if ( '' !== $hair ) {
		$who = __( 'Everyday wear with a natural look.', 'nura-beauty' );
		if ( false !== strpos( $name, 'headband' ) ) {
			$who = __( 'Beginners and busy days: no glue, on in minutes.', 'nura-beauty' );
		} elseif ( false !== strpos( $name, 'closure' ) ) {
			$who = __( 'A natural parting with little lace to manage; great first lace wig.', 'nura-beauty' );
		} elseif ( false !== strpos( $name, 'lace front' ) || false !== strpos( $name, 'frontal' ) ) {
			$who = __( 'The most natural hairline; ideal with a studio fitting.', 'nura-beauty' );
		} elseif ( false !== strpos( $name, 'bob' ) ) {
			$who = __( 'A light, easy everyday style.', 'nura-beauty' );
		}
		$rows[ __( "Who it's for", 'nura-beauty' ) ] = $who;
	}

	$global = array(
		'pa_hair-type'    => __( 'Hair type', 'nura-beauty' ),
		'pa_construction' => __( 'Construction', 'nura-beauty' ),
		'pa_texture'      => __( 'Texture', 'nura-beauty' ),
		'pa_length'       => __( 'Length', 'nura-beauty' ),
		'pa_density'      => __( 'Density', 'nura-beauty' ),
		'pa_lace'         => __( 'Lace type', 'nura-beauty' ),
		'pa_cap-size'     => __( 'Cap size', 'nura-beauty' ),
		'pa_origin'       => __( 'Hair origin', 'nura-beauty' ),
		'pa_colour'       => __( 'Colour', 'nura-beauty' ),
	);
	$seen = array();
	foreach ( $global as $tax => $label ) {
		$v = trim( (string) $product->get_attribute( $tax ) );
		if ( '' !== $v ) {
			$rows[ $label ]                 = $v;
			$seen[ nura_specs_key( $label ) ] = true;
		}
	}
	if ( '' !== $hair && empty( $seen['hair type'] ) && function_exists( 'nura_hub_labels' ) ) {
		$l = nura_hub_labels();
		$rows[ __( 'Hair type', 'nura-beauty' ) ] = ucfirst( $l[ $hair ] );
		$seen['hair type']                         = true;
	}
	if ( '' !== $hair && empty( $seen['length'] ) && preg_match( '/(\d{1,2})\s*(inch|")/i', $product->get_name(), $m ) ) {
		$rows[ __( 'Length', 'nura-beauty' ) ] = $m[1] . '"';
		$seen['length']                         = true;
	}
	// Custom (product-level) visible attributes not already covered.
	foreach ( $product->get_attributes() as $attr ) {
		if ( ! is_object( $attr ) || $attr->is_taxonomy() || ! $attr->get_visible() ) {
			continue;
		}
		$label = wc_attribute_label( $attr->get_name(), $product );
		$key   = nura_specs_key( $label );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$val = implode( ', ', array_map( 'trim', (array) $attr->get_options() ) );
		if ( '' !== $val ) {
			$rows[ $label ] = $val;
			$seen[ $key ]   = true;
		}
	}
	if ( $product->has_weight() ) {
		$rows[ __( 'Weight', 'nura-beauty' ) ] = wc_format_weight( $product->get_weight() );
	}

	if ( '' !== $hair ) {
		$life = array(
			'human' => __( 'About a year or more of regular wear with good care.', 'nura-beauty' ),
			'blend' => __( 'About 6 to 9 months of regular wear with good care.', 'nura-beauty' ),
			'fibre' => __( 'About 3 to 6 months of regular wear with good care.', 'nura-beauty' ),
		);
		$care = array(
			'human' => __( 'Sulphate-free shampoo every 2-3 weeks, condition, heat protectant before styling.', 'nura-beauty' ),
			'blend' => __( 'Cool wash, gentle detangling from the ends, low to medium heat only.', 'nura-beauty' ),
			'fibre' => __( 'Cool wash when needed, air dry on a stand, low heat only.', 'nura-beauty' ),
		);
		$rows[ __( 'Lasts about', 'nura-beauty' ) ] = $life[ $hair ];
		$rows[ __( 'Care', 'nura-beauty' ) ]        = $care[ $hair ];
	}
	return (array) apply_filters( 'nura_specs_rows', $rows, $product );
}

function nura_specs_render() {
	global $product;
	$rows = nura_specs_rows( $product );
	if ( empty( $rows ) ) {
		return;
	}
	echo '<section class="nura-specsec" aria-labelledby="nura-specsec-h"><h2 class="nura-specsec__title" id="nura-specsec-h">' . esc_html__( 'Specifications', 'nura-beauty' ) . '</h2><dl class="nura-specsec__list">';
	foreach ( $rows as $k => $v ) {
		echo '<div class="nura-specsec__row"><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	echo '</dl></section>';
}

/** Tabs: drop "Additional information"; "Hair details" keeps only what is not in Specifications. */
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	if ( ! nura_specs_unified() ) {
		return $tabs;
	}
	unset( $tabs['additional_information'] );
	// 1.129.1: wig care / beauty products get their own tabs, never wig text
	// ("Your NURA unit", "How to care for your NURA wig", wig warranty).
	global $product;
	$p = $product instanceof WC_Product ? $product : ( function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null );
	if ( $p instanceof WC_Product && function_exists( 'nura_hub_is_wig' ) && ! nura_hub_is_wig( $p ) ) {
		unset( $tabs['nura_hair'], $tabs['nura_care'], $tabs['nura_warranty'] );
		if ( isset( $tabs['nura_shipping'] ) ) {
			$tabs['nura_shipping']['title']    = __( 'Delivery & returns', 'nura-beauty' );
			$tabs['nura_shipping']['callback'] = 'nura_specs_care_shipping_tab';
		}
		return $tabs;
	}
	if ( isset( $tabs['nura_hair'] ) ) {
		$tabs['nura_hair']['title']    = __( "What's included", 'nura-beauty' );
		$tabs['nura_hair']['callback'] = 'nura_specs_included_tab';
	}
	return $tabs;
}, 99 );

function nura_specs_included_tab() {
	echo '<ul class="nura-inc"><li>' . esc_html__( 'Your NURA unit', 'nura-beauty' ) . '</li><li>' . esc_html__( 'Provenance note', 'nura-beauty' ) . '</li><li>' . esc_html__( 'Written longevity guarantee', 'nura-beauty' ) . '</li><li>' . esc_html__( 'Satin storage bag', 'nura-beauty' ) . '</li></ul>';
	echo '<p class="nura-note">' . esc_html__( 'Length guide: lengths are measured straightened, tip to tip. Curly and wavy textures appear shorter once styled. Full specifications are listed beside the price.', 'nura-beauty' ) . '</p>';
}


/** 1.129.1: delivery and returns for wig care / beauty products. */
function nura_specs_care_shipping_tab() {
	$free = function_exists( 'nura_free_delivery_threshold' ) ? (float) nura_free_delivery_threshold() : 0;
	echo '<ul class="nura-inc">';
	echo '<li>' . esc_html__( 'Same-day delivery in Nairobi on orders placed before 5:00pm; otherwise next day.', 'nura-beauty' ) . '</li>';
	if ( $free > 0 ) {
		/* translators: %s: free delivery minimum. */
		echo '<li>' . esc_html( sprintf( __( 'Free Nairobi delivery on orders over %s.', 'nura-beauty' ), 'KSh ' . number_format( $free ) ) ) . '</li>';
	}
	echo '<li>' . esc_html__( 'Same-day dispatch countrywide; the shuttle or courier sets the arrival time.', 'nura-beauty' ) . '</li>';
	echo '<li>' . esc_html__( 'Pay with M-Pesa or card at checkout, or by M-Pesa Till 1679269 when you order on WhatsApp. Pay on delivery within Nairobi CBD only; pay at the shop when you collect.', 'nura-beauty' ) . '</li>';
	echo '<li>' . esc_html__( 'Unopened products in their original packaging may be returned within 48 hours of delivery; opened products cannot be returned for hygiene reasons.', 'nura-beauty' ) . '</li>';
	echo '<li>' . esc_html__( 'Directions are in the Description tab and on the label. Do a patch test before first use of glues, sprays and removers.', 'nura-beauty' ) . '</li>';
	echo '</ul>';
}

/* ---------------------------------------------------------------------------
 * 2. Shop split.
 * ------------------------------------------------------------------------- */

function nura_shop_split_on() {
	return (bool) apply_filters( 'nura_shop_split', true );
}

/** Term IDs (term_taxonomy_ids) of Wig Care and its children. */
function nura_care_tt_ids() {
	$care = get_term_by( 'slug', 'wig-care', 'product_cat' );
	if ( ! $care || is_wp_error( $care ) ) {
		return array();
	}
	$ids = array( (int) $care->term_taxonomy_id );
	foreach ( (array) get_term_children( $care->term_id, 'product_cat' ) as $cid ) {
		$t = get_term( $cid, 'product_cat' );
		if ( $t && ! is_wp_error( $t ) ) {
			$ids[] = (int) $t->term_taxonomy_id;
		}
	}
	return $ids;
}

add_action( 'wp', function () {
	if ( ! nura_shop_split_on() || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}
	nura_remove_object_hook( 'woocommerce_before_shop_loop', 'NURAX_Shop_Filters', 'open_layout', 5 );
	nura_remove_object_hook( 'woocommerce_after_shop_loop', 'NURAX_Shop_Filters', 'close_layout', 50 );
	if ( nura_remove_object_hook( 'woocommerce_before_shop_loop', 'NURAX_Shop_Enhance', 'category_pills', 4 ) ) {
		add_action( 'woocommerce_before_shop_loop', 'nura_shop_category_bar', 3 );
	}
}, 30 );

function nura_shop_category_bar() {
	$wigs    = get_term_by( 'slug', 'wigs', 'product_cat' );
	$care    = get_term_by( 'slug', 'wig-care', 'product_cat' );
	$current = is_product_category() ? get_queried_object() : null;
	$cur_id  = $current ? (int) $current->term_id : 0;
	$in_wigs = $wigs && $current && ( $cur_id === (int) $wigs->term_id || term_is_ancestor_of( $wigs, $current, 'product_cat' ) );
	$in_care = $care && $current && ( $cur_id === (int) $care->term_id || term_is_ancestor_of( $care, $current, 'product_cat' ) );
	$items   = array();
	if ( $wigs ) {
		$items[] = array( get_term_link( $wigs ), __( 'Wigs', 'nura-beauty' ), $in_wigs );
	}
	if ( $care ) {
		$items[] = array( get_term_link( $care ), __( 'Wig Care', 'nura-beauty' ), $in_care );
	}
	// Other top-level categories (if any are ever added) stay reachable.
	$others = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => array_filter( array( (int) get_option( 'default_product_cat' ), $wigs ? (int) $wigs->term_id : 0, $care ? (int) $care->term_id : 0 ) ) ) );
	if ( ! is_wp_error( $others ) ) {
		foreach ( $others as $o ) {
			$items[] = array( get_term_link( $o ), $o->name, $cur_id === (int) $o->term_id || ( $current && term_is_ancestor_of( $o, $current, 'product_cat' ) ) );
		}
	}
	$items[] = array( wc_get_page_permalink( 'shop' ), __( 'All products', 'nura-beauty' ), is_shop() );
	echo '<nav class="nura-shop-pills nura-shop-split" aria-label="' . esc_attr__( 'Shop sections', 'nura-beauty' ) . '">';
	foreach ( $items as $it ) {
		if ( is_wp_error( $it[0] ) ) {
			continue;
		}
		echo '<a class="' . ( $it[2] ? 'is-active' : '' ) . '" href="' . esc_url( $it[0] ) . '"' . ( $it[2] ? ' aria-current="page"' : '' ) . '>' . esc_html( $it[1] ) . '</a>';
	}
	echo '</nav>';
	// Second row: sub-categories of the branch the shopper is in.
	$branch = $in_wigs ? $wigs : ( $in_care ? $care : null );
	if ( $branch ) {
		$kids = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => (int) $branch->term_id ) );
		if ( ! is_wp_error( $kids ) && $kids ) {
			echo '<nav class="nura-shop-subpills" aria-label="' . esc_attr( sprintf( __( '%s types', 'nura-beauty' ), $branch->name ) ) . '">';
			echo '<a class="' . ( $cur_id === (int) $branch->term_id ? 'is-active' : '' ) . '" href="' . esc_url( get_term_link( $branch ) ) . '">' . esc_html__( 'All', 'nura-beauty' ) . '</a>';
			foreach ( $kids as $k ) {
				$a = $cur_id === (int) $k->term_id;
				echo '<a class="' . ( $a ? 'is-active' : '' ) . '" href="' . esc_url( get_term_link( $k ) ) . '"' . ( $a ? ' aria-current="page"' : '' ) . '>' . esc_html( $k->name ) . '</a>';
			}
			echo '</nav>';
		}
	}
}

/** /shop/ with the default sort: wigs first, then wig care. */
add_filter( 'posts_clauses', function ( $clauses, $q ) {
	if ( ! nura_shop_split_on() || is_admin() || ! $q->is_main_query() || ! function_exists( 'is_shop' ) || ! is_shop() || is_search() ) {
		return $clauses;
	}
	if ( ! empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $clauses; // The shopper chose a sort: respect it.
	}
	$ids = nura_care_tt_ids();
	if ( ! $ids ) {
		return $clauses;
	}
	global $wpdb;
	$in = implode( ',', array_map( 'intval', $ids ) );
	$is_care = "(SELECT COUNT(1) FROM {$wpdb->term_relationships} nura_tr WHERE nura_tr.object_id = {$wpdb->posts}.ID AND nura_tr.term_taxonomy_id IN ($in))";
	$clauses['orderby'] = $is_care . ' ASC' . ( '' !== trim( (string) $clauses['orderby'] ) ? ', ' . $clauses['orderby'] : '' );
	return $clauses;
}, 20, 2 );

/** Section headings inside the /shop/ grid. */
add_action( 'woocommerce_shop_loop', function () {
	static $last = null;
	if ( ! nura_shop_split_on() || ! is_shop() || is_search() || ! empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$id      = get_the_ID();
	$is_care = has_term( 'wig-care', 'product_cat', $id );
	if ( ! $is_care ) {
		$care = get_term_by( 'slug', 'wig-care', 'product_cat' );
		if ( $care ) {
			$kids    = get_term_children( $care->term_id, 'product_cat' );
			$is_care = $kids && ! is_wp_error( $kids ) && has_term( $kids, 'product_cat', $id );
		}
	}
	$type = $is_care ? 'care' : 'wigs';
	if ( $type === $last ) {
		return;
	}
	if ( null === $last && 'wigs' === $type ) {
		$last = $type;
		echo '<li class="nura-loop-divider"><h2>' . esc_html__( 'Wigs', 'nura-beauty' ) . '</h2></li>';
		return;
	}
	$last = $type;
	if ( 'care' === $type ) {
		$care = get_term_by( 'slug', 'wig-care', 'product_cat' );
		echo '<li class="nura-loop-divider"><h2>' . esc_html__( 'Wig care', 'nura-beauty' ) . '</h2>' . ( $care ? '<a href="' . esc_url( get_term_link( $care ) ) . '">' . esc_html__( 'Shop all wig care', 'nura-beauty' ) . '</a>' : '' ) . '</li>';
	}
} );

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
		return;
	}
	echo '<style id="nura-128-css">'
		. '.nura-specsec{margin:1.1em 0;border:1px solid #eadfc6;border-radius:12px;background:#fffdf8;padding:.9em 1.1em}'
		. '.nura-specsec__title{font-size:1rem;font-weight:700;margin:0 0 .5em;letter-spacing:.02em}'
		. '.nura-specsec__list{margin:0;display:grid;gap:0}'
		. '.nura-specsec__row{display:grid;grid-template-columns:minmax(7.5em,34%) 1fr;gap:.8em;padding:.45em 0;border-top:1px solid #f1e8d6;font-size:.93em}'
		. '.nura-specsec__row:first-child{border-top:0}'
		. '.nura-specsec dt{font-weight:600;color:#5c4a24}.nura-specsec dd{margin:0}'
		. '.nura-shop-split{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 .8rem}'
		. '.nura-shop-split a,.nura-shop-subpills a{display:inline-flex;align-items:center;min-height:44px;padding:0 1.1em;border:1px solid #d9c9a3;border-radius:999px;text-decoration:none;font-weight:600}'
		. '.nura-shop-split a.is-active,.nura-shop-subpills a.is-active{background:#111;color:#fff;border-color:#111}'
		. '.nura-shop-subpills{display:flex;gap:.4rem;overflow-x:auto;margin:0 0 1rem;padding-bottom:.2rem}'
		. '.nura-shop-subpills a{min-height:38px;font-weight:500;font-size:.9em;white-space:nowrap}'
		. 'ul.products li.nura-loop-divider{grid-column:1/-1;flex:0 0 100%;list-style:none;display:flex;align-items:baseline;justify-content:space-between;gap:1em;border-bottom:1px solid #eadfc6;margin:.6em 0 .2em;padding:0 0 .4em}'
		. 'ul.products li.nura-loop-divider h2{font-size:1.25rem;margin:0}'
		. '@media(max-width:640px){.nura-specsec__row{grid-template-columns:1fr;gap:.1em}}'
		. '</style>' . "\n";
}, 30 );
