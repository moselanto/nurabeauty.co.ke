<?php
/**
 * NURA Beauty v1.39.0 - front-end speed.
 *
 * Safe, reversible trims aimed at a 1-3 second load on Kenyan mobile data:
 * - Homepage: preload the first hero image at high priority (it is the largest paint).
 * - Load Contact Form 7 files only on pages that actually contain a CF7 form.
 * - Defer non-critical footer scripts (order pop-up, WooCommerce attribution).
 * - Drop WordPress emoji scripts/styles and the oEmbed script for visitors.
 * - Drop Dashicons and the block-library CSS for logged-out visitors on shop pages
 *   that never use blocks (the theme styles everything itself).
 * Every trim can be switched off with: add_filter( 'nura_speed_enabled', '__return_false' );
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() ) {
	return;
}

function nura_speed_on() {
	return (bool) apply_filters( 'nura_speed_enabled', true );
}

/* 1. Preload the first hero slide on the homepage. */
add_action( 'wp_head', function () {
	if ( ! nura_speed_on() || ! is_front_page() || ! defined( 'NURA_URI' ) ) {
		return;
	}
	echo '<link rel="preload" as="image" href="' . esc_url( NURA_URI . 'assets/images/hero.webp' ) . '" fetchpriority="high">' . "\n";
}, 1 );

/* 2. Contact Form 7 only where a CF7 form is used. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || ! defined( 'WPCF7_VERSION' ) ) {
		return;
	}
	$needs = false;
	if ( is_singular() ) {
		$post  = get_post();
		$needs = $post && ( has_shortcode( (string) $post->post_content, 'contact-form-7' ) || false !== strpos( (string) $post->post_content, 'wp:contact-form-7' ) );
	}
	if ( ! apply_filters( 'nura_load_cf7', $needs ) ) {
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'swv' );
		wp_dequeue_style( 'contact-form-7' );
	}
}, 100 );

/* 3. Defer non-critical scripts. */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( ! nura_speed_on() ) {
		return $tag;
	}
	$defer = (array) apply_filters( 'nura_defer_handles', array( 'nurax-wa-order', 'sourcebuster-js', 'wc-order-attribution' ) );
	if ( in_array( $handle, $defer, true ) && false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' async' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}, 20, 2 );

/* 4. Emoji + oEmbed scripts are not used by the theme. */
add_action( 'init', function () {
	if ( ! nura_speed_on() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	add_filter( 'emoji_svg_url', '__return_false' );
} );

/* 5. Dashicons / block CSS for logged-out visitors on WooCommerce pages and the homepage. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || is_user_logged_in() ) {
		return;
	}
	wp_dequeue_style( 'dashicons' );
	$wc_page = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() );
	if ( is_front_page() || $wc_page ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'wc-blocks-style' );
	}
}, 100 );
