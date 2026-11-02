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

/**
 * 1.92.0: WordPress auto-paragraphs (wpautop) were injecting <br /> and <p> tags
 * inside the generated price list (/wig-prices-kenya/, 361 <br>) and the town
 * delivery pages' product grid (wigs-*), which broke the grid rows: one-word-per-line
 * wig names, two cards plus an orphan, misaligned columns. Clean them out of these
 * components only (never touches Journal prose).
 */
function nura_inner_unautop( $html ) {
	$start = false;
	foreach ( array( '<div class="npg">', '<div class="ncp">' ) as $m ) {
		$p = strpos( $html, $m );
		if ( false !== $p && ( false === $start || $p < $start ) ) {
			$start = $p;
		}
	}
	if ( false === $start ) {
		return $html;
	}
	$head = substr( $html, 0, $start );
	$body = substr( $html, $start );
	$tags = 'a|div|section|span|img|ul|ol|li|article|header|footer|nav|table|thead|tbody|tr|td|th|form|label|input|button|select|option|details|summary|h[1-6]|p|strong|small|svg|path';
	$body = preg_replace( '#(<(?:' . $tags . ')\b[^>]*>|</(?:' . $tags . ')>)\s*<br\s*/?>#i', '$1', $body );
	$body = preg_replace( '#<br\s*/?>\s*(</?(?:' . $tags . ')\b)#i', '$1', $body );
	$body = preg_replace( '#<p>\s*(<(?:a|div|section|span|ul|ol|article|table|nav|form|details|h[1-6])\b)#i', '$1', $body );
	$body = preg_replace( '#(</(?:a|div|section|span|ul|ol|article|table|nav|form|details|h[1-6])>)\s*</p>#i', '$1', $body );
	$body = preg_replace( '#<p>\s*</p>#i', '', $body );
	return $head . $body;
}
add_filter( 'the_content', function ( $content ) {
	return ( is_page() && ( false !== strpos( $content, 'class="npg"' ) || false !== strpos( $content, 'class="ncp"' ) ) ) ? nura_inner_unautop( $content ) : $content;
}, 999 );

/* 1.93.0: homepage polish layer (front page only). Off: add_filter( 'nura_home_design', '__return_false' ). */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_front_page() && apply_filters( 'nura_home_design', true ) ) {
		wp_enqueue_style( 'nura-home', NURA_URI . 'assets/css/nura-home.css', array( 'nura-main' ), NURA_VERSION );
	}
}, 41 );
