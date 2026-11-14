<?php
/**
 * R12 (theme 1.157.0): less code per page.
 * 1. Kadence Blocks and WordPress block styling (kadence-blocks-global-variables, kadence_blocks_palette_css,
 *    global-styles) are removed from any page whose content has no block markup (home, shop, products and the
 *    guides use none). Pages built with blocks keep them. No theme stylesheet uses the --wp--preset variables.
 * 2. Shop product cards: the long list of tag/category classes on each card (unused by any style or script)
 *    is dropped, which trims ~300 bytes per card.
 * Never applies to cart, checkout, account or logged-in views. Off: add_filter( 'nura_r12_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r12_on() {
	return (bool) apply_filters( 'nura_r12_on', true ) && ! is_admin() && ! is_user_logged_in()
		&& ! ( function_exists( 'nura_perf_protected' ) && nura_perf_protected() );
}

/* 1. Block styling only where blocks are used. */
add_action( 'template_redirect', function () {
	if ( ! nura_r12_on() || is_feed() || isset( $_GET['nura_home_panel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	ob_start( function ( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '</head>' ) ) {
			return $html;
		}
		$body = substr( $html, stripos( $html, '</head>' ) );
		if ( preg_match( '/class="[^"]*\b(wp-block-|kb-row-layout|kt-|has-[a-z0-9-]+-(color|background-color|font-size))/', $body ) ) {
			return $html; // the page uses block markup: keep everything.
		}
		return preg_replace(
			'#<style id="(kadence-blocks-global-variables-inline-css|kadence_blocks_palette_css|global-styles-inline-css|classic-theme-styles-inline-css)"[^>]*>.*?</style>\s*#s',
			'',
			$html
		);
	} );
}, 2 );

/* 2. Leaner product cards. */
add_filter( 'woocommerce_post_class', function ( $classes ) {
	if ( ! nura_r12_on() || ( function_exists( 'is_product' ) && is_product() && ! wc_get_loop_prop( 'name' ) ) ) {
		return $classes;
	}
	return array_values( array_filter( (array) $classes, function ( $c ) {
		return ! preg_match( '/^(product_cat-|product_tag-|pa_|shipping-taxable$|purchasable$|has-default-attributes$|status-publish$)/', (string) $c );
	} ) );
}, 50 );
