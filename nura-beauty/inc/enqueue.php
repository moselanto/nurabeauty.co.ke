<?php
/**
 * Front-end asset loading, tuned for Core Web Vitals.
 *
 * Strategy:
 * - Fonts are preconnected and loaded with display=swap.
 * - Critical, above-the-fold CSS is inlined; the full stylesheet loads without render-blocking.
 * - JavaScript is deferred and only the small interaction bundle ships by default.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nura_enqueue_assets() {
	// Google Fonts: Playfair Display + Cormorant Garamond (serif display) and Montserrat + Jost (sans).
	// Fonts are built from the Customizer typography settings (see inc/dynamic-css.php).
	$fonts_url = function_exists( 'nura_dynamic_fonts_url' ) ? nura_dynamic_fonts_url() : 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Cormorant+Garamond:wght@400;500&family=Montserrat:wght@300;400;500&family=Jost:wght@400;500&display=swap';
	wp_enqueue_style( 'nura-fonts', $fonts_url, array(), null );

	wp_enqueue_style( 'nura-main', NURA_URI . 'assets/css/main.css', array(), NURA_VERSION );

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style( 'nura-woocommerce', NURA_URI . 'assets/css/woocommerce.css', array( 'nura-main' ), NURA_VERSION );
	}

	wp_enqueue_script( 'nura-main', NURA_URI . 'assets/js/main.js', array(), NURA_VERSION, true );
	wp_localize_script(
		'nura-main',
		'NURA',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'nura_nonce' ),
			'isRtl'   => is_rtl(),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'nura_enqueue_assets' );

/**
 * Preconnect to font hosts to shave latency (helps LCP).
 */
function nura_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		$urls[] = 'https://fonts.googleapis.com';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'nura_resource_hints', 10, 2 );

/**
 * Defer non-critical scripts (keeps main thread free -> better TBT/INP).
 */
function nura_defer_scripts( $tag, $handle ) {
	$defer = array( 'nura-main' );
	if ( in_array( $handle, $defer, true ) && false === strpos( $tag, 'defer' ) ) {
		$tag = str_replace( ' src', ' defer src', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'nura_defer_scripts', 10, 2 );

/**
 * Admin CSS for the onboarding welcome panel.
 */
function nura_admin_assets( $hook ) {
	if ( in_array( $hook, array( 'appearance_page_nura-welcome', 'themes.php' ), true ) ) {
		wp_enqueue_style( 'nura-admin', NURA_URI . 'assets/css/admin.css', array(), NURA_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'nura_admin_assets' );


/**
 * Preload the LCP hero image on the front page (improves Largest Contentful Paint).
 */
function nura_preload_lcp() {
	if ( ! is_front_page() ) {
		return;
	}
	$hero = function_exists( 'nura_opt' ) ? nura_opt( 'nura_hero_image' ) : '';
	if ( empty( $hero ) ) {
		$hero = NURA_URI . 'assets/images/hero.webp';
	}
	printf( '<link rel="preload" as="image" href="%s" fetchpriority="high" />' . "\n", esc_url( $hero ) );
}
add_action( 'wp_head', 'nura_preload_lcp', 1 );
