<?php
/**
 * R9 (theme 1.154.0): site-wide QA fixes from the 14 Nov 2026 audit.
 * 1. Sitemap lists only indexable, final URLs (no cart/checkout/account, no pages that redirect).
 * 2. Old links inside guides point straight at the live product/category (no redirect hop).
 * 3. Phones: wide comparison tables scroll sideways inside their box instead of being cut off;
 *    the "add the full care set" button wraps instead of running off screen.
 * Off: add_filter( 'nura_r9_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r9_on() {
	return (bool) apply_filters( 'nura_r9_on', true );
}

/* 1. Sitemap. */
function nura_r9_sitemap_skip() {
	return (array) apply_filters( 'nura_r9_sitemap_skip', array( 'cart', 'checkout', 'my-account', 'delivery', 'virtual-try-on' ) );
}
add_filter( 'rank_math/sitemap/entry', function ( $url, $type = '', $object = null ) {
	if ( ! nura_r9_on() || empty( $url['loc'] ) ) {
		return $url;
	}
	$path = trim( (string) wp_parse_url( $url['loc'], PHP_URL_PATH ), '/' );
	return in_array( $path, nura_r9_sitemap_skip(), true ) ? false : $url;
}, 20, 3 );
add_filter( 'rank_math/sitemap/exclude_post', function ( $exclude, $post = null ) {
	if ( nura_r9_on() && $post && isset( $post->post_type, $post->post_name ) && 'page' === $post->post_type && in_array( $post->post_name, nura_r9_sitemap_skip(), true ) ) {
		return true;
	}
	return $exclude;
}, 20, 2 );

/* 2. Old product links in guide content. */
function nura_r9_link_map() {
	return (array) apply_filters( 'nura_r9_link_map', array(
		'/product/headbands-wig/'           => '/product-category/wigs/headband-wigs/',
		'/product/latisha-sensational-wig/' => '/product/latisha-sensational-headband-wig/',
		'/product/water-curl-8-wig/'        => '/product-category/wigs/bob-wigs/',
	) );
}
add_filter( 'the_content', function ( $html ) {
	if ( ! nura_r9_on() || false === strpos( (string) $html, '/product/' ) ) {
		return $html;
	}
	foreach ( nura_r9_link_map() as $from => $to ) {
		$html = str_replace( array( home_url( $from ), 'href="' . $from ), array( home_url( $to ), 'href="' . $to ), $html );
	}
	return $html;
}, 30 );

/* 3. Phone layout. */
add_action( 'wp_head', function () {
	if ( ! nura_r9_on() ) {
		return;
	}
	echo '<style id="nura-r9">@media(max-width:768px){.entry-content table,.nura-r4 table,.nura-guide table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}}'
		. '@media(max-width:560px){.nura-bundle-bar__btn{white-space:normal;flex-wrap:wrap;max-width:100%}.nura-bundle-bar__txt{max-width:100%}}</style>' . "\n";
}, 20 );
