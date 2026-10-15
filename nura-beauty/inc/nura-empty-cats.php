<?php
/**
 * Hide empty shop categories everywhere they are linked (header fallback menu,
 * assigned WP menus, footer, homepage chips/tiles/rails) until they have
 * products, and require a phone number at checkout (classic + block).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nura_cat_has_products' ) ) {
	/** True when a product category (or any child) holds at least one product. */
	function nura_cat_has_products( $slug ) {
		static $cache = array();
		$slug = sanitize_title( $slug );
		if ( isset( $cache[ $slug ] ) ) {
			return $cache[ $slug ];
		}
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return $cache[ $slug ] = true;
		}
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return $cache[ $slug ] = false;
		}
		if ( (int) $term->count > 0 || (int) get_term_meta( $term->term_id, 'product_count_product_cat', true ) > 0 ) {
			return $cache[ $slug ] = true;
		}
		$children = get_term_children( $term->term_id, 'product_cat' );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $cid ) {
				$c = get_term( $cid, 'product_cat' );
				if ( $c && ! is_wp_error( $c ) && (int) $c->count > 0 ) {
					return $cache[ $slug ] = true;
				}
			}
		}
		return $cache[ $slug ] = false;
	}
}

if ( ! function_exists( 'nura_url_is_empty_cat' ) ) {
	/** True when a URL points at a product-category archive with no products. */
	function nura_url_is_empty_cat( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, '/product-category/' ) ) {
			return false;
		}
		$path  = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$parts = explode( '/', $path );
		$slug  = end( $parts );
		return $slug ? ! nura_cat_has_products( $slug ) : false;
	}
}

if ( ! function_exists( 'nura_filter_links' ) ) {
	/** Drop [label, url] rows (or arrays with 'url'/'slug') that point at empty categories. */
	function nura_filter_links( $items ) {
		return array_values( array_filter( $items, function ( $it ) {
			if ( isset( $it['slug'] ) ) {
				return 'wigs' === $it['slug'] || nura_cat_has_products( $it['slug'] );
			}
			$url = isset( $it['url'] ) ? $it['url'] : ( isset( $it[1] ) ? $it[1] : '' );
			return ! nura_url_is_empty_cat( $url );
		} ) );
	}
}

/** Assigned WP menus: hide product-category items with no products (and their children). */
add_filter( 'wp_nav_menu_objects', function ( $items ) {
	if ( is_admin() ) {
		return $items;
	}
	$removed = array();
	foreach ( $items as $k => $item ) {
		$empty = false;
		if ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
			$t     = get_term( (int) $item->object_id, 'product_cat' );
			$empty = $t && ! is_wp_error( $t ) && ! nura_cat_has_products( $t->slug );
		} elseif ( 'custom' === $item->type ) {
			$empty = nura_url_is_empty_cat( $item->url );
		}
		if ( $empty || in_array( (int) $item->menu_item_parent, $removed, true ) ) {
			$removed[] = (int) $item->ID;
			unset( $items[ $k ] );
		}
	}
	return $items;
}, 20 );

/** Phone number required at checkout (block checkout reads this option). */
add_filter( 'pre_option_woocommerce_checkout_phone_field', function () {
	return 'required';
} );
