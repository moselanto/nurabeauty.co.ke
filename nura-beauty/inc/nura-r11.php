<?php
/**
 * R11 (theme 1.156.0): homepage speed.
 * - Hidden homepage product tabs (Best Sellers, Under KSh 5,000) are fetched from
 *   /?nura_home_panel=<key> the first time the tab is opened, hovered or focused (main.js),
 *   instead of shipping ~86 KB of product cards inside the homepage.
 *   Off: add_filter( 'nura_home_tabs_fetch', '__return_false' ) (falls back to inline <template>).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['nura_home_panel'] ) || ! function_exists( 'nura_home_product_tabs' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$key = sanitize_key( wp_unslash( $_GET['nura_home_panel'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $key, array( 'new', 'best', 'budget' ), true ) ) {
		status_header( 404 );
		exit;
	}
	$GLOBALS['nura_home_panel_only'] = $key;
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	nocache_headers();
	header( 'Cache-Control: public, max-age=600' );
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	nura_home_product_tabs( $shop );
	exit;
}, 0 );
