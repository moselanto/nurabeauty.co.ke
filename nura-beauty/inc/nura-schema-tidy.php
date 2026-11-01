<?php
/**
 * Structured data tidy-up (theme 1.76.0, release R2 of the SEO strategy).
 *
 * Runs last on the Rank Math graph (rank_math/json_ld, priority 999) and only
 * normalises what is already there - it never invents facts:
 *
 * 1. Every Offer seller points to the one business entity (/#organization)
 *    instead of a second "Nura beauty" Organization with a different @id.
 * 2. Brand and logo caption use the business's own spelling, "NURA Beauty"
 *    (the WooCommerce Brands term is "Nura Beauty"). Supplier brands are untouched.
 * 3. ProductGroup additionalProperty: drops duplicate WooCommerce "pa_*" rows
 *    when a same-meaning custom row exists, and the colour list (colour is
 *    already expressed per variant through variesBy/color).
 *
 * Checked live on 1 Nov 2026 before this release: 24 product pages, one
 * Product/ProductGroup node each, and all 45 colour variant URLs preselect the
 * right colour with matching price, stock and image.
 *
 * Off: add_filter( 'nura_schema_tidy', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_schema_tidy_brand_name( $name ) {
	return ( 'nura beauty' === strtolower( trim( (string) $name ) ) || 'nura' === strtolower( trim( (string) $name ) ) ) ? 'NURA Beauty' : $name;
}

function nura_schema_tidy_walk( &$node, $org_id ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['seller'] ) && is_array( $node['seller'] ) ) {
		$node['seller'] = array( '@id' => $org_id );
	}
	if ( isset( $node['brand'] ) && is_array( $node['brand'] ) && isset( $node['brand']['name'] ) ) {
		$node['brand']['name'] = nura_schema_tidy_brand_name( $node['brand']['name'] );
	}
	if ( isset( $node['@id'] ) && is_string( $node['@id'] ) && '#logo' === substr( $node['@id'], -5 ) && isset( $node['caption'] ) ) {
		$node['caption'] = 'NURA Beauty';
	}
	if ( isset( $node['@type'] ) && 'ProductGroup' === $node['@type'] && ! empty( $node['additionalProperty'] ) && is_array( $node['additionalProperty'] ) ) {
		$names = array();
		foreach ( $node['additionalProperty'] as $p ) {
			if ( isset( $p['name'] ) ) {
				$names[ (string) $p['name'] ] = true;
			}
		}
		$alias = array( 'pa_hair-type' => 'hair-type', 'pa_texture' => 'texture', 'pa_length' => 'length', 'pa_lace' => 'lace-type' );
		$keep  = array();
		foreach ( $node['additionalProperty'] as $p ) {
			$n = isset( $p['name'] ) ? (string) $p['name'] : '';
			if ( in_array( $n, array( 'pa_colour', 'colour', 'color', 'pa_color' ), true ) ) {
				continue;
			}
			if ( isset( $alias[ $n ] ) && isset( $names[ $alias[ $n ] ] ) ) {
				continue;
			}
			$keep[] = $p;
		}
		$node['additionalProperty'] = $keep;
	}
	foreach ( $node as $k => &$child ) {
		if ( is_array( $child ) && 'seller' !== $k ) {
			nura_schema_tidy_walk( $child, $org_id );
		}
	}
	unset( $child );
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! apply_filters( 'nura_schema_tidy', true ) ) {
		return $data;
	}
	$org_id = home_url( '/' ) . '#organization';
	foreach ( $data as $key => &$node ) {
		if ( is_array( $node ) ) {
			nura_schema_tidy_walk( $node, $org_id );
		}
	}
	unset( $node );
	return $data;
}, 999 );
