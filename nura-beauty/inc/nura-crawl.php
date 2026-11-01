<?php
/**
 * Crawl hygiene from the Search Console audit of 1 Nov 2026 (theme 1.75.0).
 *
 * 1. 301 redirects for /index.php/... and double-slash URLs to the clean path.
 * 2. 301 redirects for removed product URLs that still return 404, to the
 *    closest live product or collection (only when the URL really is a 404, so a
 *    product republished under the same slug wins). Extend with the
 *    `nura_removed_product_redirects` filter.
 * 3. Feeds and REST API responses send X-Robots-Tag: noindex; category, tag
 *    and product comment feed links are removed from the page head.
 * 4. Empty product categories are noindex,follow until they have products
 *    (Google reported them as soft 404 / crawled - not indexed).
 *
 * Shop filter URLs are handled in robots.txt (nura-security.php) and with
 * rel="nofollow" on filter links (nura-filters.php).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/* ---- 1. /index.php/... and // paths ---- */
add_action( 'template_redirect', function () {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$uri   = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$parts = explode( '?', $uri, 2 );
	$path  = $parts[0];
	if ( 0 !== strpos( $path, '/index.php/' ) && false === strpos( $path, '//' ) ) {
		return;
	}
	$clean = preg_replace( '#^/index\.php(?=/)#', '', $path );
	$clean = preg_replace( '#/{2,}#', '/', $clean );
	if ( '' === $clean || $clean === $path ) {
		return;
	}
	$target = home_url( $clean ) . ( isset( $parts[1] ) && '' !== $parts[1] ? '?' . $parts[1] : '' );
	wp_safe_redirect( $target, 301, 'NURA' );
	exit;
}, 1 );

/* ---- 2. Removed products ---- */
function nura_removed_product_redirects() {
	return (array) apply_filters( 'nura_removed_product_redirects', array(
		'human-blend-bodywave-wig'           => '/product/latisha-sensational-wig/',
		'human-blend-bob-wig'                => '/product/bob-wavy-wig/',
		'lakisha-wig'                        => '/product/latisha-sensational-wig/',
		'pixie-wig'                          => '/product/pixie-water-curl-wig/',
		'jerry-curl-wig'                     => '/product-category/wigs/human-hair-wigs/jerry-curl-wigs/',
		'chioma-curls-wig'                   => '/product-category/wigs/human-hair-wigs/curly-wigs/',
		't-part-bubbles-wig'                 => '/product-category/wigs/human-hair-wigs/curly-wigs/',
		'futura-humanblend-bonestraight-wig' => '/product-category/wigs/',
	) );
}
add_action( 'template_redirect', function () {
	if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! preg_match( '#^/product/([a-z0-9-]+)/?$#', $path, $m ) ) {
		return;
	}
	$map = nura_removed_product_redirects();
	if ( isset( $map[ $m[1] ] ) ) {
		wp_safe_redirect( home_url( $map[ $m[1] ] ), 301, 'NURA' );
		exit;
	}
}, 5 );

/* ---- 3. Feeds and REST API out of the index ---- */
add_action( 'init', function () {
	remove_action( 'wp_head', 'feed_links_extra', 3 ); // Category, tag, product and comment feeds.
} );
add_action( 'template_redirect', function () {
	if ( is_feed() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow', true );
	}
}, 0 );
add_filter( 'rest_post_dispatch', function ( $response ) {
	if ( $response instanceof WP_HTTP_Response ) {
		$response->header( 'X-Robots-Tag', 'noindex' );
	}
	return $response;
} );

/* ---- 4. Empty categories ---- */
function nura_crawl_is_empty_cat() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return false;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->slug ) ) {
		return false;
	}
	return function_exists( 'nura_cat_has_products' ) ? ! nura_cat_has_products( $term->slug ) : ( 0 === (int) $term->count );
}
add_filter( 'rank_math/frontend/robots', function ( $robots ) {
	if ( nura_crawl_is_empty_cat() ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
	}
	return $robots;
}, 100 );
add_filter( 'wp_robots', function ( $robots ) {
	if ( nura_crawl_is_empty_cat() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
	}
	return $robots;
}, 100 );
