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
	$defer = (array) apply_filters( 'nura_defer_handles', array( 'nura-main', 'nurax-wa-order', 'sourcebuster-js', 'wc-order-attribution' ) );
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

/* ---------------------------------------------------------------------------
 * v1.46.0 - image loading and cart request
 * Live audit: 67 images on the homepage but only 1 lazy-loaded, product cards
 * asking for 600px-wide files at "100vw" on phones, and an extra uncached
 * cart AJAX call on every page view.
 * ------------------------------------------------------------------------- */

/* 5. Product images: lazy after the first two, and the right size for the slot. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment, $size ) {
	static $seen = 0;
	if ( ! nura_speed_on() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $attr;
	}
	$class   = isset( $attr['class'] ) ? (string) $attr['class'] : '';
	$is_card = false !== strpos( $class, 'attachment-woocommerce' ) || in_array( $size, array( 'woocommerce_thumbnail', 'woocommerce_single' ), true );
	if ( ! $is_card ) {
		return $attr;
	}
	// The main gallery image on a product page stays as it is (it is the largest paint).
	$in_loop = function_exists( 'wc_get_loop_prop' ) && '' !== (string) wc_get_loop_prop( 'name' );
	if ( function_exists( 'is_product' ) && is_product() && ! $in_loop && ! did_action( 'woocommerce_after_single_product_summary' ) ) {
		return $attr;
	}
	$seen++;
	if ( $seen > 2 ) {
		$attr['loading']       = 'lazy';
		$attr['fetchpriority'] = 'low';
	}
	$attr['decoding'] = 'async';
	if ( ! empty( $attr['srcset'] ) ) {
		// Cards are 2 per row on phones, 3 on tablets, 4-5 on desktop.
		$attr['sizes'] = '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 300px';
	}
	return $attr;
}, 30, 3 );

/* 6. Skip WooCommerce's cart-refresh request when the visitor's cart is empty. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	if ( is_cart() || is_checkout() || ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( WC()->cart->is_empty() && apply_filters( 'nura_skip_cart_fragments', true ) ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}, 110 );


/* ---------------------------------------------------------------------------
 * v1.55.6 - logo and script weight
 * Live audit: the header logo was a 2548x808 PNG (1.7 MB) loaded on every page
 * at high priority, though it shows at 56px tall. Serve a 400px WebP (33 KB).
 * ------------------------------------------------------------------------- */

/* 7. Light header logo. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! nura_speed_on() || ! defined( 'NURA_URI' ) || ! defined( 'NURA_DIR' ) ) {
		return $attr;
	}
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $logo_id || ! $attachment || (int) $attachment->ID !== $logo_id || ! file_exists( NURA_DIR . 'assets/images/nura-logo.webp' ) ) {
		return $attr;
	}
	if ( ! apply_filters( 'nura_light_logo', true ) ) {
		return $attr;
	}
	$attr['src'] = NURA_URI . 'assets/images/nura-logo.webp';
	unset( $attr['srcset'], $attr['sizes'] );
	$attr['decoding'] = 'async';
	return $attr;
}, 40, 2 );

add_filter( 'get_custom_logo', function ( $html ) {
	if ( ! nura_speed_on() || false === strpos( $html, 'nura-logo.webp' ) ) {
		return $html;
	}
	return preg_replace( array( '/\swidth="\d+"/', '/\sheight="\d+"/' ), array( ' width="400"', ' height="127"' ), $html, 1 );
} );

/* 8. Defer the NURA Experience scripts too (they only run after the page is ready). */
add_filter( 'nura_defer_handles', function ( $handles ) {
	return array_merge( (array) $handles, array( 'nurax', 'nurax-shop', 'nurax-variations' ) );
} );
