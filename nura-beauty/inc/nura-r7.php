<?php
/**
 * R7 (theme 1.152.0): less JavaScript at page load on phones (home, shop, product, guides).
 *
 * Scripts that are only needed once a visitor does something now load on the first tap, key,
 * scroll or mouse move (or ~7 s after the page has loaded, in idle time):
 * - order attribution (sourcebuster + WooCommerce order-attribution),
 * - the "Join The House" pop-up script, comment-reply,
 * - option-picker scripts (variations, underscore, wp-util) everywhere except product pages
 *   (Quick View still uses them: the tap that opens it loads them),
 * - on guides / plain pages with no product cards: WooCommerce add-to-cart, blockUI, cookie and
 *   woocommerce.js (the add-to-cart links still work as normal links without them).
 * Their inline settings run too, in the original order, right before each script.
 * A script is never delayed if another script that is NOT delayed depends on it.
 * Never applies to cart, checkout, My Account or order-received pages.
 * Off: add_filter( 'nura_r7_lazy_js', '__return_false' ) or ?nura_r7_off=1 on one page view.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r7_on() {
	static $on = null;
	if ( null !== $on ) {
		return $on;
	}
	$on = ! is_admin() && ! is_customize_preview() && ! is_feed()
		&& empty( $_GET['nura_r7_off'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		&& ! ( function_exists( 'nura_perf_protected' ) && nura_perf_protected() )
		&& (bool) apply_filters( 'nura_r7_lazy_js', true );
	return $on;
}

/** The handles delayed on this page (computed once, while footer scripts print). */
function nura_r7_set() {
	static $set = null;
	if ( null !== $set ) {
		return $set;
	}
	$h       = array( 'sourcebuster-js', 'wc-order-attribution', 'nurax-sub', 'comment-reply' );
	$product = function_exists( 'is_product' ) && is_product();
	$shop    = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || $product );
	if ( ! $product ) {
		$h = array_merge( $h, array( 'wc-add-to-cart-variation', 'wp-util', 'underscore' ) );
	}
	if ( ! $shop && ! is_front_page() && empty( $GLOBALS['nura_cards_printed'] ) ) {
		$h = array_merge( $h, array( 'wc-add-to-cart', 'wc-jquery-blockui', 'jquery-blockui', 'woocommerce', 'wc-js-cookie', 'js-cookie' ) );
	}
	$h  = array_values( array_unique( (array) apply_filters( 'nura_r7_lazy_handles', $h ) ) );
	$ws = wp_scripts();
	$all = array_unique( array_merge( (array) $ws->done, (array) $ws->to_do, (array) $ws->queue ) );
	do {
		$changed = false;
		foreach ( $all as $q ) {
			if ( in_array( $q, $h, true ) || empty( $ws->registered[ $q ] ) ) {
				continue;
			}
			foreach ( (array) $ws->registered[ $q ]->deps as $dep ) {
				$i = array_search( $dep, $h, true );
				if ( false !== $i ) {
					unset( $h[ $i ] );
					$changed = true;
				}
			}
		}
		$h = array_values( $h );
	} while ( $changed );
	$set = $h;
	return $set;
}

add_filter( 'script_loader_tag', function ( $tag, $handle, $src = '' ) {
	if ( ! did_action( 'wp_footer' ) || ! nura_r7_on() || ! in_array( $handle, nura_r7_set(), true ) ) {
		return $tag;
	}
	if ( false !== strpos( $tag, 'data-nura-src' ) ) {
		return $tag;
	}
	$GLOBALS['nura_r7_used'] = true;
	return preg_replace_callback( '#<script\b([^>]*)>#i', function ( $m ) {
		$a = $m[1];
		if ( preg_match( '#\stype=(["\'])(.*?)\1#i', $a, $t ) && '' !== $t[2] && 'text/javascript' !== strtolower( $t[2] ) ) {
			return $m[0]; // JSON / templates untouched.
		}
		$a = preg_replace( '#\stype=(["\'])[^"\']*\1#i', '', $a );
		$a = preg_replace( '#\s(?:defer|async)(?=[\s/]|$)#i', '', $a );
		$a = preg_replace( '#\ssrc=#i', ' data-nura-lazy=', $a );
		return '<script type="nura/lazy"' . $a . '>';
	}, $tag );
}, 70, 3 );

add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['nura_r7_used'] ) ) {
		return;
	}
	echo "<script id=\"nura-r7-lazy\">(function(){var d=0;function run(){if(d)return;d=1;var l=[].slice.call(document.querySelectorAll('script[type=\"nura/lazy\"]')),i=0;function next(){if(i>=l.length){document.dispatchEvent(new Event('nura:lazy-ready'));return;}var o=l[i++],s=document.createElement('script'),u=o.getAttribute('data-nura-lazy');for(var k=0;k<o.attributes.length;k++){var a=o.attributes[k];if(a.name!=='type'&&a.name!=='data-nura-lazy')s.setAttribute(a.name,a.value);}if(u){s.async=false;s.onload=s.onerror=function(){setTimeout(next,0);};s.src=u;o.parentNode.replaceChild(s,o);}else{s.text=o.text;o.parentNode.replaceChild(s,o);setTimeout(next,0);}}next();}['pointerdown','keydown','touchstart','wheel','mousemove'].forEach(function(e){addEventListener(e,run,{once:true,passive:true});});function late(){addEventListener('scroll',run,{once:true,passive:true});" . ( ( $fb = (int) apply_filters( 'nura_r7_fallback_ms', 0 ) ) > 0 ? "setTimeout(function(){(window.requestIdleCallback||setTimeout)(run);}," . $fb . ");" : '' ) . "}if(document.readyState==='complete'){late();}else{addEventListener('load',late);}})();</script>\n";
}, 1000 );
