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
