<?php
/**
 * NURA SEO R5 (theme 1.149.0, 14 Nov 2026) - search snippets that earn the click.
 *
 * Search Console (22 Aug - 10 Nov 2026) shows product pages with hundreds of impressions and
 * about 1% CTR. Their stored Rank Math titles were 80-120 characters (Google shows about 60), and
 * some descriptions repeated words ("Luxury Fibre Luxury Fibre"). This module, running after
 * every other title/description filter:
 *  1. Product titles longer than 65 characters become "Name - KSh price | NURA Beauty Kenya"
 *     (or "Name | NURA Beauty Kenya" when that is still too long). Short custom titles are kept.
 *  2. Removes a word or phrase repeated back to back in titles and descriptions.
 *  3. Category descriptions: "Shop 1 styles from KSh 4,500 to KSh 4,500" -> "Shop 1 style at KSh 4,500".
 * Off switch: add_filter( 'nura_seo_r5', '__return_false' ).
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nura_r5_on() {
	return (bool) apply_filters( 'nura_seo_r5', true );
}

/** Remove a phrase of 1-3 words repeated straight after itself (case-insensitive). */
function nura_r5_dedupe( $s ) {
	$s = (string) $s;
	for ( $n = 3; $n >= 1; $n-- ) {
		$s = preg_replace( '/\b((?:[\p{L}\p{N}%&\'-]+\s+){' . ( $n - 1 ) . '}[\p{L}\p{N}%&\'-]+)\s+\1\b/iu', '$1', $s );
	}
	return $s;
}

function nura_r5_ksh( $n ) {
	return function_exists( 'nura_seo_ksh' ) ? nura_seo_ksh( $n ) : 'KSh ' . number_format( (float) $n );
}

add_filter( 'rank_math/frontend/title', function ( $title ) {
	if ( ! nura_r5_on() ) {
		return $title;
	}
	$title = nura_r5_dedupe( $title );
	if ( function_exists( 'is_product' ) && is_product() && mb_strlen( wp_strip_all_tags( $title ) ) > 65 ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$name  = nura_r5_dedupe( wp_strip_all_tags( $p->get_name() ) );
			$price = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) $p->get_price();
			$t     = $price > 0 ? $name . ' - ' . nura_r5_ksh( $price ) . ' | NURA Beauty Kenya' : $name . ' | NURA Beauty Kenya';
			if ( mb_strlen( $t ) > 65 ) {
				$t = $name . ' | NURA Beauty Kenya';
			}
			return $t;
		}
	}
	return $title;
}, 40 );

add_filter( 'rank_math/frontend/description', function ( $desc ) {
	if ( ! nura_r5_on() ) {
		return $desc;
	}
	$desc = nura_r5_dedupe( $desc );
	$desc = preg_replace_callback( '/Shop (\d+) styles from (KSh [\d,]+) to (KSh [\d,]+)\./', function ( $m ) {
		$one = '1' === $m[1];
		if ( $m[2] === $m[3] ) {
			return 'Shop ' . $m[1] . ( $one ? ' style' : ' styles' ) . ' at ' . $m[2] . '.';
		}
		return 'Shop ' . $m[1] . ( $one ? ' style' : ' styles' ) . ' from ' . $m[2] . ' to ' . $m[3] . '.';
	}, $desc );
	return $desc;
}, 40 );
