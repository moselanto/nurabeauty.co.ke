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

/**
 * 1.83.0 (live Rich Results / validator audit, 2 Nov 2026):
 * - OnlineStore does not accept priceRange, currenciesAccepted or paymentAccepted
 *   (33 validator warnings); they are removed from the online-store entity and kept
 *   on the physical studio (LocalBusiness), where "Cash" is labelled as pay-in-person
 *   at studio pick-up only (no cash on delivery).
 * - Each colour variant gets the brand and a short description (Merchant listings
 *   warning), and the ProductGroup gets productGroupID matching the variants'
 *   inProductGroupWithID.
 */
function nura_schema_tidy_r3( &$node ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
	if ( in_array( 'OnlineStore', $types, true ) && ! array_intersect( $types, array( 'LocalBusiness', 'HealthAndBeautyBusiness', 'Store', 'BeautySalon' ) ) ) {
		unset( $node['priceRange'], $node['currenciesAccepted'], $node['paymentAccepted'] );
	}
	if ( array_intersect( $types, array( 'HealthAndBeautyBusiness', 'LocalBusiness', 'BeautySalon', 'Store' ) ) && isset( $node['paymentAccepted'] ) ) {
		$node['paymentAccepted'] = 'M-Pesa, Visa, Mastercard, Bank Transfer, Cash (in person at studio pick-up only; no cash on delivery)';
	}
	if ( in_array( 'ProductGroup', $types, true ) && ! empty( $node['hasVariant'] ) && is_array( $node['hasVariant'] ) ) {
		$brand = isset( $node['brand'] ) ? $node['brand'] : array( '@type' => 'Brand', 'name' => 'NURA Beauty' );
		$gname = isset( $node['name'] ) ? wp_strip_all_tags( (string) $node['name'] ) : '';
		$gdesc = isset( $node['description'] ) ? wp_strip_all_tags( (string) $node['description'] ) : '';
		$gid   = '';
		foreach ( $node['hasVariant'] as &$v ) {
			if ( ! is_array( $v ) ) {
				continue;
			}
			if ( empty( $v['brand'] ) ) {
				$v['brand'] = $brand;
			}
			if ( empty( $v['description'] ) ) {
				$colour = isset( $v['color'] ) ? (string) $v['color'] : '';
				$base   = $gdesc ? $gdesc : $gname;
				$v['description'] = trim( ( $colour ? $gname . ' in ' . $colour . '. ' : '' ) . wp_trim_words( $base, 40, '...' ) );
			}
			if ( '' === $gid && ! empty( $v['inProductGroupWithID'] ) ) {
				$gid = (string) $v['inProductGroupWithID'];
			}
		}
		unset( $v );
		if ( empty( $node['productGroupID'] ) ) {
			$node['productGroupID'] = '' !== $gid ? $gid : ( ! empty( $node['sku'] ) ? (string) $node['sku'] : (string) get_queried_object_id() );
		}
	}
	foreach ( $node as $k => &$child ) {
		if ( is_array( $child ) && 'hasVariant' !== $k ) {
			nura_schema_tidy_r3( $child );
		}
	}
	unset( $child );
}
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( is_array( $data ) && apply_filters( 'nura_schema_tidy', true ) ) {
		foreach ( $data as $key => &$node ) {
			nura_schema_tidy_r3( $node );
		}
		unset( $node );
	}
	return $data;
}, 1000 );
