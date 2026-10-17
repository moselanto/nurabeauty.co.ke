<?php
/**
 * NURA product variants schema (theme 1.65.0).
 *
 * Turns Rank Math's single Product node for a variable product into a Google
 * "ProductGroup" with one Product per colour / length option, each with its own
 * price, stock status, image and pre-selecting URL, as described in Google's
 * product-variants documentation. Simple products are not touched.
 *
 * Switch off with: add_filter( 'nura_schema_variants', '__return_false' );
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Map a WooCommerce attribute key to a schema.org property Google understands. */
function nura_variant_property( $attr ) {
	$a = str_replace( array( 'attribute_', 'pa_' ), '', strtolower( (string) $attr ) );
	if ( in_array( $a, array( 'colour', 'color', 'shade' ), true ) ) {
		return 'color';
	}
	if ( in_array( $a, array( 'length', 'size', 'inch', 'inches' ), true ) ) {
		return 'size';
	}
	return '';
}

/** Readable value of a variation attribute (term name, not slug). */
function nura_variant_value( $attr, $value ) {
	$tax = str_replace( 'attribute_', '', (string) $attr );
	if ( taxonomy_exists( $tax ) ) {
		$term = get_term_by( 'slug', $value, $tax );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term->name;
		}
	}
	return wp_strip_all_tags( (string) $value );
}

/** Schema availability URL for a product. */
function nura_variant_availability( $product ) {
	if ( $product->is_on_backorder() ) {
		return 'https://schema.org/BackOrder';
	}
	return $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! apply_filters( 'nura_schema_variants', true ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $data;
	}
	$parent = wc_get_product( get_queried_object_id() );
	if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
		return $data;
	}

	// Which attributes really vary, and which of them Google can name.
	$varies = array();
	foreach ( array_keys( $parent->get_variation_attributes() ) as $attr ) {
		$prop = nura_variant_property( $attr );
		if ( $prop ) {
			$varies[ $prop ] = 'https://schema.org/' . $prop;
		}
	}
	if ( ! $varies ) {
		return $data; // Only texture/density style options: keep the plain Product.
	}

	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) || ! in_array( 'Product', (array) $node['@type'], true ) ) {
			continue;
		}
		$url      = get_permalink( $parent->get_id() );
		$group_id = isset( $node['@id'] ) ? $node['@id'] : $url . '#product';
		$gid      = $parent->get_sku() ? $parent->get_sku() : 'NURA-' . $parent->get_id();

		// Shipping + returns already built for the parent offer; reuse on each variant.
		$base_offer = array();
		if ( isset( $node['offers'] ) && is_array( $node['offers'] ) ) {
			$o          = isset( $node['offers']['@type'] ) ? $node['offers'] : reset( $node['offers'] );
			$base_offer = is_array( $o ) ? array_intersect_key( $o, array_flip( array( 'shippingDetails', 'hasMerchantReturnPolicy', 'seller', 'itemCondition' ) ) ) : array();
		}

		$variants = array();
		foreach ( array_slice( $parent->get_children(), 0, 60 ) as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || ! $v->is_purchasable() || '' === $v->get_price() || 'publish' !== get_post_status( $vid ) ) {
				continue;
			}
			$item = array(
				'@type'               => 'Product',
				'@id'                 => $url . '#variant-' . $vid,
				'name'                => wp_strip_all_tags( $v->get_name() ),
				'sku'                 => $v->get_sku() ? $v->get_sku() : $gid . '-' . $vid,
				'url'                 => $v->get_permalink(),
				'inProductGroupWithID' => $gid,
				'isVariantOf'         => array( '@id' => $group_id ),
			);
			$img = $v->get_image_id() ? wp_get_attachment_image_url( $v->get_image_id(), 'woocommerce_single' ) : '';
			if ( $img ) {
				$item['image'] = $img;
			} elseif ( ! empty( $node['image'] ) ) {
				$item['image'] = $node['image'];
			}
			foreach ( $v->get_variation_attributes() as $attr => $val ) {
				$prop = nura_variant_property( $attr );
				if ( $prop && '' !== $val ) {
					$item[ $prop ] = nura_variant_value( $attr, $val );
				}
			}
			$offer = array_merge( $base_offer, array(
				'@type'         => 'Offer',
				'price'         => wc_format_decimal( $v->get_price(), 2 ),
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => nura_variant_availability( $v ),
				'url'           => $v->get_permalink(),
				'itemCondition' => 'https://schema.org/NewCondition',
			) );
			if ( $v->is_on_sale() && $v->get_date_on_sale_to() ) {
				$offer['priceValidUntil'] = $v->get_date_on_sale_to()->date( 'Y-m-d' );
			}
			$item['offers'] = $offer;
			$variants[]     = $item;
		}
		if ( count( $variants ) < 2 ) {
			return $data; // Nothing meaningful to group.
		}

		$group = $node;
		$group['@type']          = 'ProductGroup';
		$group['@id']            = $group_id;
		$group['productGroupID'] = $gid;
		$group['variesBy']       = array_values( $varies );
		$group['hasVariant']     = $variants;
		$group['url']            = $url;
		unset( $group['offers'], $group['sku'], $group['color'], $group['gtin'], $group['mpn'] );
		$data[ $key ] = $group;
		break;
	}
	return $data;
}, 200 );
