<?php
/**
 * Inner pages: classic editorial design system (theme 1.91.0).
 *
 * Adds body class `nura-inner` to content pages, Journal posts, the blog and
 * category archives, and loads assets/css/nura-inner.css there. Never on the
 * homepage, shop, product, cart, checkout or My Account pages, so the
 * buying flow is untouched. Off: add_filter( 'nura_inner_design', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_is_inner() {
	if ( is_admin() || is_front_page() || ! apply_filters( 'nura_inner_design', true ) ) {
		return false;
	}
	if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		return false;
	}
	return is_page() || is_singular( 'post' ) || is_home() || is_category() || is_tag() || is_author() || is_search();
}

add_filter( 'body_class', function ( $classes ) {
	if ( nura_is_inner() ) {
		$classes[] = 'nura-inner';
	}
	return $classes;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( nura_is_inner() ) {
		wp_enqueue_style( 'nura-inner', NURA_URI . 'assets/css/nura-inner.css', array( 'nura-main' ), NURA_VERSION );
	}
}, 40 );
