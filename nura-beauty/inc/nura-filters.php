<?php
/**
 * NURA faceted shop filters (v1.11.0).
 *
 * A lightweight, plugin-free filtering layer for the shop and product archives.
 * Facet links use WooCommerce's native filter_{attribute} query vars, so filtering
 * works with a normal page load (SEO-friendly, no-JS safe); assets/js/main.js then
 * upgrades interactions to AJAX. Only global attribute taxonomies that actually have
 * terms in the catalogue are rendered, so customers never see an empty or irrelevant
 * facet. Colour (v1.11.0) is faceted via a non-variation global pa_colour taxonomy of
 * colour families, added alongside the untouched per-variation Colour axis; the facet
 * renders real colour swatches and auto-hides until the pa_colour terms exist.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this a shop-type archive where the filter bar belongs?
 */
function nura_is_shop_context() {
	if ( ! function_exists( 'is_shop' ) ) {
		return false;
	}
	return is_shop() || is_product_taxonomy();
}

/**
 * Global attribute taxonomies offered as facets, in display order.
 *
 * Every facetable NURA attribute is listed here, but each facet self-hides until
 * its taxonomy exists AND has terms in use (get_terms hide_empty), so shoppers only
 * ever see facets that resolve to real products (brief #6 / #29). The same list also
 * drives smart-search term recognition (inc/nura-search.php).
 *
 * @return array taxonomy => label
 */
function nura_filter_attributes() {
	return array(
		'pa_construction' => __( 'Construction', 'nura-beauty' ),
		'pa_hair-type'    => __( 'Hair Type', 'nura-beauty' ),
		'pa_texture'      => __( 'Texture', 'nura-beauty' ),
		'pa_length'       => __( 'Length', 'nura-beauty' ),
		'pa_colour'       => __( 'Colour', 'nura-beauty' ),
		'pa_density'      => __( 'Density', 'nura-beauty' ),
		'pa_lace'         => __( 'Lace Type', 'nura-beauty' ),
		'pa_cap-size'     => __( 'Cap Size', 'nura-beauty' ),
		'pa_origin'       => __( 'Hair Origin', 'nura-beauty' ),
		'pa_style'        => __( 'Style', 'nura-beauty' ),
		'pa_occasion'     => __( 'Occasion', 'nura-beauty' ),
	);
}

/**
 * The filter query var (filter_{name}) for an attribute taxonomy.
 */
function nura_filter_key( $taxonomy ) {
	return 'filter_' . str_replace( 'pa_', '', $taxonomy );
}

/**
 * Currently chosen term slugs for an attribute taxonomy.
 *
 * @return string[]
 */
function nura_chosen_terms( $taxonomy ) {
	$key = nura_filter_key( $taxonomy );
	if ( empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return array();
	}
	$val = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
	$raw = is_array( $val ) ? implode( ',', $val ) : $val;
	return array_values( array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) ) );
}

/**
 * Base archive URL (without filter query) for the current context.
 */
function nura_shop_base_url() {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return get_permalink( wc_get_page_id( 'shop' ) );
	}
	$obj = get_queried_object();
	if ( $obj instanceof WP_Term ) {
		$link = get_term_link( $obj );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
}

/**
 * All active filter query args on the current request (sanitised), minus pagination.
 *
 * @return array
 */
function nura_active_filter_args() {
	$args = array();
	foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
		$k = (string) $k;
		if ( preg_match( '/^(filter_|query_type_)[a-z0-9_\-]+$/', $k ) || in_array( $k, array( 'min_price', 'max_price', 'instock', 'orderby' ), true ) ) {
			$raw          = wp_unslash( is_array( $v ) ? implode( ',', $v ) : $v );
			$args[ $k ] = sanitize_text_field( $raw );
		}
	}
	return $args;
}

/**
 * URL with a single attribute term toggled on/off, preserving other filters.
 */
function nura_toggle_term_url( $taxonomy, $term_slug ) {
	$key    = nura_filter_key( $taxonomy );
	$name   = str_replace( 'pa_', '', $taxonomy );
	$chosen = nura_chosen_terms( $taxonomy );
	$args   = nura_active_filter_args();

	if ( in_array( $term_slug, $chosen, true ) ) {
		$chosen = array_diff( $chosen, array( $term_slug ) );
	} else {
		$chosen[] = $term_slug;
	}

	if ( $chosen ) {
		$args[ $key ]                  = implode( ',', $chosen );
		$args[ 'query_type_' . $name ] = 'or';
	} else {
		unset( $args[ $key ], $args[ 'query_type_' . $name ] );
	}

	return add_query_arg( $args, nura_shop_base_url() );
}

/**
 * URL with the in-stock filter toggled.
 */
function nura_toggle_instock_url() {
	$args = nura_active_filter_args();
	if ( ! empty( $args['instock'] ) ) {
		unset( $args['instock'] );
	} else {
		$args['instock'] = '1';
	}
	return add_query_arg( $args, nura_shop_base_url() );
}

/**
 * Build the list of active-filter chips (label + toggle-off URL).
 *
 * @return array
 */
function nura_active_filter_chips() {
	$chips = array();

	foreach ( nura_filter_attributes() as $tax => $label ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		foreach ( nura_chosen_terms( $tax ) as $slug ) {
			$term = get_term_by( 'slug', $slug, $tax );
			$chips[] = array(
				'label' => ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug,
				'url'   => nura_toggle_term_url( $tax, $slug ),
			);
		}
	}

	$args = nura_active_filter_args();
	$cur  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
	$min  = isset( $args['min_price'] ) ? $args['min_price'] : '';
	$max  = isset( $args['max_price'] ) ? $args['max_price'] : '';
	if ( '' !== $min || '' !== $max ) {
		if ( '' !== $min && '' !== $max ) {
			$plabel = $cur . $min . ' - ' . $cur . $max;
		} elseif ( '' !== $min ) {
			$plabel = $cur . $min . '+';
		} else {
			$plabel = __( 'Up to', 'nura-beauty' ) . ' ' . $cur . $max;
		}
		$price_args = $args;
		unset( $price_args['min_price'], $price_args['max_price'] );
		$chips[] = array(
			'label' => $plabel,
			'url'   => add_query_arg( $price_args, nura_shop_base_url() ),
		);
	}

	if ( ! empty( $args['instock'] ) ) {
		$chips[] = array(
			'label' => __( 'In stock', 'nura-beauty' ),
			'url'   => nura_toggle_instock_url(),
		);
	}

	return $chips;
}

/**
 * Render the price facet (native min_price / max_price query vars).
 */
function nura_render_price_facet() {
	$base = nura_shop_base_url();
	$args = nura_active_filter_args();
	$min  = isset( $args['min_price'] ) ? $args['min_price'] : '';
	$max  = isset( $args['max_price'] ) ? $args['max_price'] : '';
	$open = ( '' !== $min || '' !== $max );
	$cur  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

	echo '<details class="nura-facet nura-facet--price"' . ( $open ? ' open' : '' ) . '>';
	echo '<summary>' . esc_html__( 'Price', 'nura-beauty' ) . ( $open ? ' <span class="nura-facet__badge">1</span>' : '' ) . '</summary>';
	echo '<form class="nura-facet__menu nura-price-form" method="get" action="' . esc_url( $base ) . '" data-nura-price>';

	foreach ( $args as $k => $v ) {
		if ( 'min_price' === $k || 'max_price' === $k ) {
			continue;
		}
		printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $k ), esc_attr( $v ) );
	}

	echo '<div class="nura-price-row">';
	printf(
		'<label class="screen-reader-text" for="nura-min-price">%s</label><input type="number" inputmode="numeric" min="0" id="nura-min-price" name="min_price" value="%s" placeholder="%s">',
		esc_html__( 'Minimum price', 'nura-beauty' ),
		esc_attr( $min ),
		esc_attr__( 'Min', 'nura-beauty' )
	);
	echo '<span class="nura-price-sep" aria-hidden="true">&ndash;</span>';
	printf(
		'<label class="screen-reader-text" for="nura-max-price">%s</label><input type="number" inputmode="numeric" min="0" id="nura-max-price" name="max_price" value="%s" placeholder="%s">',
		esc_html__( 'Maximum price', 'nura-beauty' ),
		esc_attr( $max ),
		esc_attr__( 'Max', 'nura-beauty' )
	);
	echo '</div>';
	printf(
		'<button type="submit" class="nura-btn nura-price-go">%s</button>',
		$cur ? esc_html( sprintf( /* translators: %s: currency symbol */ __( 'Apply (%s)', 'nura-beauty' ), $cur ) ) : esc_html__( 'Apply', 'nura-beauty' )
	);
	echo '</form></details>';
}

/**
 * Render the full filter bar before the shop loop.
 */
function nura_render_filter_bar() {
	if ( ! nura_is_shop_context() ) {
		return;
	}

	$attributes = nura_filter_attributes();
	$chips      = nura_active_filter_chips();
	$active     = ! empty( $chips );
	$args       = nura_active_filter_args();
	$instock    = ! empty( $args['instock'] );

	echo '<div class="nura-filterbar" data-nura-filterbar>';

	// Top row: mobile toggle + active chips + clear all.
	echo '<div class="nura-filterbar__top">';
	echo '<button type="button" class="nura-filter-toggle" data-nura-filter-toggle aria-expanded="false" aria-controls="nura-filters">';
	echo '<span class="nura-filter-toggle__icon" aria-hidden="true"></span>' . esc_html__( 'Filter &amp; Sort', 'nura-beauty' );
	if ( $active ) {
		echo ' <span class="nura-filter-toggle__count">' . absint( count( $chips ) ) . '</span>';
	}
	echo '</button>';

	if ( $active ) {
		echo '<div class="nura-chips" aria-label="' . esc_attr__( 'Active filters', 'nura-beauty' ) . '">';
		foreach ( $chips as $chip ) {
			printf(
				'<a class="nura-chip" href="%1$s" data-nura-filter><span>%2$s</span><span class="nura-chip__x" aria-hidden="true">&times;</span><span class="screen-reader-text">%3$s</span></a>',
				esc_url( $chip['url'] ),
				esc_html( $chip['label'] ),
				esc_attr__( 'Remove filter', 'nura-beauty' )
			);
		}
		printf(
			'<a class="nura-chip nura-chip--clear" href="%1$s" data-nura-filter>%2$s</a>',
			esc_url( nura_shop_base_url() ),
			esc_html__( 'Clear all', 'nura-beauty' )
		);
		echo '</div>';
	}
	echo '</div>'; // .nura-filterbar__top

	// Facet panel: horizontal bar on desktop, slide-up drawer on mobile.
	echo '<div class="nura-filters" id="nura-filters" data-nura-filters>';
	echo '<div class="nura-filters__head"><span>' . esc_html__( 'Filter &amp; Sort', 'nura-beauty' ) . '</span><button type="button" class="nura-filters__close" data-nura-filter-close aria-label="' . esc_attr__( 'Close filters', 'nura-beauty' ) . '">&times;</button></div>';
	echo '<div class="nura-filters__groups">';

	foreach ( $attributes as $tax => $label ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue; // No relevant values -> no facet.
		}
		$chosen    = nura_chosen_terms( $tax );
		$is_colour = in_array( $tax, array( 'pa_colour', 'pa_color' ), true ) && function_exists( 'nura_colour_style' );
		printf(
			'<details class="nura-facet%1$s"%2$s><summary>%3$s%4$s</summary><div class="nura-facet__menu%5$s">',
			$is_colour ? ' nura-facet--colour' : '',
			$chosen ? ' open' : '',
			esc_html( $label ),
			$chosen ? ' <span class="nura-facet__badge">' . absint( count( $chosen ) ) . '</span>' : '',
			$is_colour ? ' nura-facet__menu--colour' : ''
		);
		foreach ( $terms as $term ) {
			$is_active = in_array( $term->slug, $chosen, true );
			if ( $is_colour ) {
				$box = '<span class="nura-facet__box nura-facet__box--swatch" style="background:' . esc_attr( nura_colour_style( $term->name ) ) . '" aria-hidden="true"></span>';
			} else {
				$box = '<span class="nura-facet__box" aria-hidden="true"></span>';
			}
			printf(
				'<a class="nura-facet__opt%1$s%2$s" href="%3$s" data-nura-filter aria-pressed="%4$s">%5$s<span class="nura-facet__name">%6$s</span><em class="nura-facet__count">%7$d</em></a>',
				$is_active ? ' is-active' : '',
				$is_colour ? ' nura-facet__opt--swatch' : '',
				esc_url( nura_toggle_term_url( $tax, $term->slug ) ),
				$is_active ? 'true' : 'false',
				$box,
				esc_html( $term->name ),
				absint( $term->count )
			);
		}
		echo '</div></details>';
	}

	nura_render_price_facet();

	// Availability facet.
	printf(
		'<div class="nura-facet nura-facet--toggle"><a class="nura-facet__opt%1$s" href="%2$s" data-nura-filter aria-pressed="%3$s"><span class="nura-facet__box" aria-hidden="true"></span><span class="nura-facet__name">%4$s</span></a></div>',
		$instock ? ' is-active' : '',
		esc_url( nura_toggle_instock_url() ),
		$instock ? 'true' : 'false',
		esc_html__( 'In stock only', 'nura-beauty' )
	);

	echo '</div>'; // .nura-filters__groups

	echo '<div class="nura-filters__foot">';
	echo '<a class="nura-btn nura-btn--ghost nura-filters__clear" href="' . esc_url( nura_shop_base_url() ) . '" data-nura-filter>' . esc_html__( 'Clear all', 'nura-beauty' ) . '</a>';
	echo '<button type="button" class="nura-btn nura-btn--gold nura-filters__apply" data-nura-filter-close>' . esc_html__( 'Apply', 'nura-beauty' ) . '</button>';
	echo '</div>';

	echo '</div>'; // .nura-filters
	echo '</div>'; // .nura-filterbar
}
add_action( 'woocommerce_before_shop_loop', 'nura_render_filter_bar', 4 );

/**
 * Wrap the result count + ordering + product loop + pagination in an AJAX-swappable region.
 */
function nura_open_results_wrap() {
	if ( nura_is_shop_context() ) {
		echo '<div class="nura-shop-results" data-nura-results>';
	}
}
add_action( 'woocommerce_before_shop_loop', 'nura_open_results_wrap', 19 );

function nura_close_results_wrap() {
	if ( nura_is_shop_context() ) {
		echo '</div>';
	}
}
add_action( 'woocommerce_after_shop_loop', 'nura_close_results_wrap', 99 );

/**
 * Apply the "in stock only" filter to the product query (WooCommerce handles the
 * filter_{attribute} and min/max price query vars natively).
 */
function nura_apply_instock_filter( $q ) {
	if ( empty( $_GET['instock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
		return;
	}
	$ids = wc_get_product_visibility_term_ids();
	if ( empty( $ids['outofstock'] ) ) {
		return;
	}
	$tax_query   = (array) $q->get( 'tax_query' );
	$tax_query[] = array(
		'taxonomy' => 'product_visibility',
		'field'    => 'term_taxonomy_id',
		'terms'    => array( $ids['outofstock'] ),
		'operator' => 'NOT IN',
	);
	$q->set( 'tax_query', $tax_query );
}
add_action( 'woocommerce_product_query', 'nura_apply_instock_filter' );
