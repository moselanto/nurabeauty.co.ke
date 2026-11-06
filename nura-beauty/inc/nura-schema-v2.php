<?php
/**
 * 1.124.0 structured-data fixes found by the Nov 2026 validation of key templates.
 *
 * 1. BreadcrumbList on every singular page (products, pages, guides) when Rank Math
 *    has not emitted one (product-category archives already get one from
 *    nura-seo-content.php). WebPage/ItemPage nodes are linked to it via "breadcrumb".
 * 2. One business entity: any nested reference to /#organization is reduced to a
 *    bare {"@id"} so it cannot carry a conflicting name or @type
 *    (e.g. "NURA Beauty stylist team" as an author name).
 *
 * @package nura-beauty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crumbs for the current singular view: array of [name, url].
 *
 * @return array
 */
function nura_schema_v2_crumbs() {
	$home   = home_url( '/' );
	$crumbs = array( array( 'name' => 'Home', 'url' => $home ) );
	$id     = get_queried_object_id();
	if ( ! $id ) {
		return array();
	}
	$type = get_post_type( $id );
	if ( 'product' === $type ) {
		$shop = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
		if ( $shop > 0 ) {
			$crumbs[] = array( 'name' => get_the_title( $shop ), 'url' => get_permalink( $shop ) );
		}
		$terms = get_the_terms( $id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			// Deepest category = most specific.
			usort( $terms, function ( $a, $b ) {
				return count( get_ancestors( $b->term_id, 'product_cat' ) ) - count( get_ancestors( $a->term_id, 'product_cat' ) );
			} );
			$term  = $terms[0];
			$chain = array_reverse( get_ancestors( $term->term_id, 'product_cat' ) );
			$chain[] = $term->term_id;
			foreach ( $chain as $tid ) {
				$t = get_term( $tid, 'product_cat' );
				if ( $t && ! is_wp_error( $t ) && 'uncategorized' !== $t->slug ) {
					$crumbs[] = array( 'name' => html_entity_decode( $t->name ), 'url' => get_term_link( $t ) );
				}
			}
		}
	} elseif ( 'page' === $type ) {
		foreach ( array_reverse( get_post_ancestors( $id ) ) as $pid ) {
			$crumbs[] = array( 'name' => get_the_title( $pid ), 'url' => get_permalink( $pid ) );
		}
	} elseif ( 'post' === $type ) {
		$cats = get_the_category( $id );
		if ( $cats ) {
			$crumbs[] = array( 'name' => html_entity_decode( $cats[0]->name ), 'url' => get_category_link( $cats[0] ) );
		}
	}
	$crumbs[] = array( 'name' => wp_strip_all_tags( html_entity_decode( get_the_title( $id ) ) ), 'url' => get_permalink( $id ) );
	return $crumbs;
}

/**
 * Reduce nested /#organization references to a bare @id.
 *
 * @param mixed  $node   Node.
 * @param string $org_id Organization @id.
 * @param int    $depth  Depth (0 = top-level graph node).
 * @return mixed
 */
function nura_schema_v2_org_refs( $node, $org_id, $depth ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	if ( $depth > 0 && isset( $node['@id'] ) && $org_id === $node['@id'] ) {
		return array( '@id' => $org_id );
	}
	foreach ( $node as $k => $v ) {
		if ( is_array( $v ) ) {
			$node[ $k ] = nura_schema_v2_org_refs( $v, $org_id, $depth + 1 );
		}
	}
	return $node;
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$org_id = home_url( '/' ) . '#organization';
	foreach ( $data as $key => $node ) {
		$data[ $key ] = nura_schema_v2_org_refs( $node, $org_id, 0 );
	}

	if ( ! is_singular() || is_front_page() ) {
		return $data;
	}
	foreach ( $data as $node ) {
		if ( is_array( $node ) && ! empty( $node['@type'] ) && in_array( 'BreadcrumbList', (array) $node['@type'], true ) ) {
			return $data;
		}
	}
	$crumbs = nura_schema_v2_crumbs();
	if ( count( $crumbs ) < 2 ) {
		return $data;
	}
	$items = array();
	foreach ( array_values( $crumbs ) as $i => $c ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $c['name'],
			'item'     => $c['url'],
		);
	}
	$bc_id                  = get_permalink( get_queried_object_id() ) . '#breadcrumb';
	$data['nuraBreadcrumb'] = array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $bc_id,
		'itemListElement' => $items,
	);
	foreach ( $data as $key => $node ) {
		if ( is_array( $node ) && ! empty( $node['@type'] ) && array_intersect( (array) $node['@type'], array( 'WebPage', 'ItemPage', 'CollectionPage', 'FAQPage', 'AboutPage', 'ContactPage' ) ) && empty( $node['breadcrumb'] ) ) {
			$data[ $key ]['breadcrumb'] = array( '@id' => $bc_id );
		}
	}
	return $data;
}, 99 );
