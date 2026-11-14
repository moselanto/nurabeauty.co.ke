<?php
/**
 * R10 (theme 1.155.0): owner-approved speed plan (14 Nov 2026).
 * - Meta pixel, Google tag, Google Ads and the R7 helper scripts load on the visitor's first
 *   tap, key, scroll or mouse move only (no timed fallback). Visitors who leave without touching
 *   the page are not counted in Meta / Google Analytics; owner accepted this.
 * - Cart, checkout, My Account and order-received pages are never changed (nura_perf_protected()).
 * Off: add_filter( 'nura_r10_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r10_on() {
	return (bool) apply_filters( 'nura_r10_on', true ) && ! ( function_exists( 'nura_perf_protected' ) && nura_perf_protected() );
}

add_filter( 'nura_delay_tags_timeout', function ( $ms ) {
	return nura_r10_on() ? 0 : $ms;
}, 99 );

/* Meta Pixel on every page (not only product pages): a queue stub is defined before the two inline
 * Pixel snippets, so they queue init/PageView instead of fetching fbevents.js at load; fbevents.js
 * then loads on the first interaction (nura-speed.php #13) and replays the queue. */
add_action( 'wp_head', function () {
	if ( ! nura_r10_on() || ( function_exists( 'nura_tbt_on' ) && nura_tbt_on() ) || ! apply_filters( 'nura_delay_tags', true ) ) {
		return;
	}
	echo "<script id=\"nura-fbq-stub\">!function(f){if(f.fbq)return;var n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[]}(window);</script>\n";
	echo '<script type="text/plain" data-nura-src="https://connect.facebook.net/en_US/fbevents.js"></script>' . "\n";
}, 1 );
