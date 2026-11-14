<?php
/**
 * R15 (theme 1.160.0)
 * 1. Product pages (desktop layout shift): the price line keeps its final height from the first paint, and
 *    the "Before you buy" guide links in the product summary are no longer deferred (they sat next to the
 *    add-to-bag form and their placeholder pushed it down).
 * 2. Product cards: the WhatsApp icon in every card is one shared CSS icon instead of a copy of the same
 *    574-byte SVG per card; cards further down the grid are not laid out until scrolled near
 *    (content-visibility), so the first screen is ready sooner on phones.
 * Off: add_filter( 'nura_r15_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r15_on() {
	return (bool) apply_filters( 'nura_r15_on', true ) && ! is_admin();
}

function nura_r15_wa_svg() {
	return '<svg class="nwo-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>';
}

add_action( 'wp_head', function () {
	if ( ! nura_r15_on() ) {
		return;
	}
	$icon = rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>' );
	$css  = '.nwo-ico--m{display:inline-block;width:18px;height:18px;flex:none;background:currentColor;-webkit-mask:url("data:image/svg+xml,' . $icon . '") center/contain no-repeat;mask:url("data:image/svg+xml,' . $icon . '") center/contain no-repeat}'
		. 'html body ul.products li.product .nwo-btn--card .nwo-ico--m{width:15px;height:15px}';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$css .= '@media(min-width:861px){.single-product .summary p.price{line-height:1.7;min-height:41px}}';
	}
	if ( ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) || is_search() ) {
		$css .= '@media(max-width:860px){ul.products>li.product:nth-child(n+5){content-visibility:auto;contain-intrinsic-size:auto 430px}}'
			. '@media(min-width:861px){ul.products>li.product:nth-child(n+9){content-visibility:auto;contain-intrinsic-size:auto 480px}}';
	}
	echo '<style id="nura-r15">' . $css . '</style>' . "\n";
}, 4 );

add_action( 'template_redirect', function () {
	if ( ! nura_r15_on() || is_feed() ) {
		return;
	}
	ob_start( function ( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, 'nwo-btn--card' ) ) {
			return $html;
		}
		return str_replace( nura_r15_wa_svg(), '<span class="nwo-ico nwo-ico--m" aria-hidden="true"></span>', $html );
	} );
}, 95 );
