<?php
/**
 * NURA Beauty 1.161.40 - keep page titles short enough for Bing and Google.
 *
 * Bing's site scan flagged 55 titles as too long. Every title that comes out of
 * Rank Math (and the NURA title filters that run before this one) is capped at
 * 65 characters: first the tagline parts go (" | Same-Day Nairobi Delivery",
 * "Kenya" after the brand), then the brand shortens to "NURA", then the main
 * text is cut at a natural break (bracket, colon, dash, comma). Titles that
 * already fit are not touched. Off: add_filter( 'nura_title_cap', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_title_fits( $s, $max ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) <= $max : strlen( $s ) <= $max;
}

function nura_title_shorten( $t, $max = 65 ) {
	$t = trim( preg_replace( '/\s+/', ' ', (string) $t ) );
	if ( '' === $t || nura_title_fits( $t, $max ) ) {
		return $t;
	}
	$t = str_replace( ' | NURA Beauty Kenya', ' | NURA Beauty', $t );
	$t = preg_replace( '/ \| (Same-Day Nairobi Delivery|Private Fittings)/', '', $t );
	if ( nura_title_fits( $t, $max ) ) {
		return $t;
	}
	$parts = explode( ' | ', $t );
	$brand = ( count( $parts ) > 1 && 0 === strpos( end( $parts ), 'NURA' ) );
	$main  = $brand ? implode( ' | ', array_slice( $parts, 0, -1 ) ) : $t;
	$sufs  = ( false !== stripos( $main, 'NURA' ) ) ? array( '' ) : array( ' | NURA Beauty', ' | NURA' );
	foreach ( $sufs as $b ) {
		if ( nura_title_fits( $main . $b, $max ) ) {
			return $main . $b;
		}
	}
	foreach ( array( ' (', ': ', ' - ', ' & ' ) as $sep ) {
		$pos = strpos( $main, $sep );
		if ( false !== $pos && $pos >= 25 ) {
			$m = substr( $main, 0, $pos );
			foreach ( $sufs as $b ) {
				if ( nura_title_fits( $m . $b, $max ) ) {
					return $m . $b;
				}
			}
		}
	}
	$b = end( $sufs );
	$m = '';
	foreach ( explode( ' ', $main ) as $w ) {
		if ( ! nura_title_fits( trim( $m . ' ' . $w ) . $b, $max ) ) {
			break;
		}
		$m = trim( $m . ' ' . $w );
	}
	$c = strrpos( $m, ',' );
	if ( false !== $c && $c >= 25 ) {
		$m = substr( $m, 0, $c );
	}
	$m = preg_replace( '/\s+(na|vs|and|&|or|ya|of|the|to|for|in|with)$/i', '', rtrim( $m, ' ,:;-&' ) );
	return $m . $b;
}

function nura_title_cap_filter( $title ) {
	if ( is_admin() || ! apply_filters( 'nura_title_cap', true ) ) {
		return $title;
	}
	return nura_title_shorten( $title, (int) apply_filters( 'nura_title_max', 65 ) );
}
add_filter( 'rank_math/frontend/title', 'nura_title_cap_filter', 9999 );
add_filter( 'pre_get_document_title', function ( $t ) {
	return '' === (string) $t ? $t : nura_title_cap_filter( $t );
}, 9999 );
