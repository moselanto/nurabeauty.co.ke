<?php
/**
 * Shop tidy-up (v1.55.4).
 *  - Empty product categories: hidden from menus and category lists, and
 *    set to noindex so Google does not index empty pages.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Products in a category including its sub-categories (cached per request). */
function nura_cat_total( $term_id ) {
	static $cache = array();
	$term_id = (int) $term_id;
	if ( isset( $cache[ $term_id ] ) ) {
		return $cache[ $term_id ];
	}
	$t = get_term( $term_id, 'product_cat' );
	$n = ( $t && ! is_wp_error( $t ) ) ? (int) $t->count : 0;
	$kids = get_term_children( $term_id, 'product_cat' );
	if ( ! is_wp_error( $kids ) ) {
		foreach ( $kids as $kid ) {
			$k = get_term( $kid, 'product_cat' );
			if ( $k && ! is_wp_error( $k ) ) {
				$n += (int) $k->count;
			}
		}
	}
	$cache[ $term_id ] = $n;
	return $n;
}

/** Find an empty product category from a menu item (category item or custom link). */
function nura_menu_item_is_empty_cat( $item ) {
	if ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
		return 0 === nura_cat_total( $item->object_id );
	}
	if ( 'custom' === $item->type && is_string( $item->url ) && false !== strpos( $item->url, '/product-category/' ) ) {
		$path  = trim( (string) wp_parse_url( $item->url, PHP_URL_PATH ), '/' );
		$parts = explode( '/', $path );
		$slug  = end( $parts );
		$t     = $slug ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
		return $t ? 0 === nura_cat_total( $t->term_id ) : false;
	}
	return false;
}

add_filter( 'wp_nav_menu_objects', function ( $items ) {
	if ( is_admin() ) {
		return $items;
	}
	$removed = array();
	foreach ( $items as $i => $item ) {
		if ( nura_menu_item_is_empty_cat( $item ) || in_array( (int) $item->menu_item_parent, $removed, true ) ) {
			$removed[] = (int) $item->ID;
			unset( $items[ $i ] );
		}
	}
	return $items;
}, 20 );

add_filter( 'woocommerce_product_subcategories_hide_empty', '__return_true' );
add_filter( 'woocommerce_product_categories_widget_args', function ( $args ) {
	$args['hide_empty'] = 1;
	return $args;
} );

function nura_is_empty_cat_archive() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return false;
	}
	$t = get_queried_object();
	return ( $t instanceof WP_Term ) && 0 === nura_cat_total( $t->term_id );
}

add_filter( 'wp_robots', function ( $robots ) {
	if ( nura_is_empty_cat_archive() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
	}
	return $robots;
}, 30 );

add_filter( 'rank_math/frontend/robots', function ( $robots ) {
	if ( nura_is_empty_cat_archive() ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
	}
	return $robots;
}, 30 );
