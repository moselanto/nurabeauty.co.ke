<?php
/**
 * NURA SEO fixes (v1.41.0) - corrections found in the Oct 2026 live audit.
 *
 * Live site runs Rank Math, so Rank Math owns the main schema graph. This file
 * corrects that graph instead of adding a second, competing one:
 *   1. Organization email/phone always match the theme's Brand settings
 *      (live graph was declaring care@nura.co.ke).
 *   2. Homepage no longer declares itself an Article written by a Person.
 *   3. Product schema name is the plain product name (no "| Nura beauty").
 *   4. Filtered, sorted and price-range shop URLs are noindex,follow so Google
 *      spends its time on real category and product pages, not thousands of
 *      near-duplicate filter combinations.
 *   5. wp-admin notice listing products whose Hair Type is blend/synthetic but
 *      whose text still says "100% human hair" (accuracy for shoppers + Merchant Center).
 *
 * Switch off with: add_filter( 'nura_seo_fixes_enabled', '__return_false' );
 *
 * @package NURA_Beauty
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! apply_filters( 'nura_seo_fixes_enabled', true ) ) {
	return;
}

/** Brand contact details from NURA Options > Brand (single source of truth). */
function nura_seo_brand_contact() {
	$email = function_exists( 'nura_opt' ) ? sanitize_email( (string) nura_opt( 'nura_email' ) ) : '';
	$phone = function_exists( 'nura_opt' ) ? (string) nura_opt( 'nura_phone' ) : '';
	$phone = preg_replace( '/[^\d+]/', '', $phone );
	return array(
		'email'     => is_email( $email ) ? $email : 'care@nurabeauty.co.ke',
		'telephone' => $phone ? $phone : '+254714994898',
	);
}

/** Does a schema node have one of these types? */
function nura_seo_node_is( $node, $types ) {
	if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
		return false;
	}
	$t = (array) $node['@type'];
	return (bool) array_intersect( $t, (array) $types );
}

/** 1-3: correct Rank Math's JSON-LD graph. */
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$contact = nura_seo_brand_contact();
	$front   = is_front_page();

	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		// 1. Organization / LocalBusiness contact details.
		if ( nura_seo_node_is( $node, array( 'Organization', 'LocalBusiness', 'HealthAndBeautyBusiness', 'Store', 'OnlineStore' ) ) ) {
			$data[ $key ]['email']     = $contact['email'];
			$data[ $key ]['telephone'] = $contact['telephone'];
		}
		// 2. Homepage is a shop front, not a blog article.
		if ( $front && nura_seo_node_is( $node, array( 'Article', 'BlogPosting', 'NewsArticle', 'Person' ) ) ) {
			unset( $data[ $key ] );
			continue;
		}
		if ( $front && nura_seo_node_is( $node, array( 'WebPage' ) ) ) {
			unset( $data[ $key ]['author'] );
		}
		// 3. Plain product name.
		if ( nura_seo_node_is( $node, array( 'Product', 'ProductGroup' ) ) && function_exists( 'is_product' ) && is_product() ) {
			$p = wc_get_product( get_queried_object_id() );
			if ( $p ) {
				$data[ $key ]['name'] = wp_strip_all_tags( $p->get_name() );
			}
		}
	}
	return $data;
}, 99 );

/** Should the current URL be kept out of Google's index (filters, sorting, price ranges)? */
function nura_seo_is_filtered_url() {
	if ( is_admin() || empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return false;
	}
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification
		$k = (string) $k;
		if ( 0 === strpos( $k, 'filter_' ) || 0 === strpos( $k, 'query_type_' )
			|| in_array( $k, array( 'orderby', 'min_price', 'max_price', 'rating_filter', 'add-to-cart', 'per_page', 'nura_budget', 'stock_status' ), true ) ) {
			return true;
		}
	}
	return false;
}

/** 4. noindex,follow on filtered URLs - Rank Math and plain WordPress. */
add_filter( 'rank_math/frontend/robots', function ( $robots ) {
	if ( nura_seo_is_filtered_url() || is_search() ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
	}
	return $robots;
}, 99 );
add_filter( 'wp_robots', function ( $robots ) {
	if ( nura_seo_is_filtered_url() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
	}
	return $robots;
}, 99 );

/** 5. Products whose text overstates the hair type. */
function nura_seo_hair_claim_conflicts() {
	$cached = get_transient( 'nura_seo_hair_conflicts_v1' );
	if ( false !== $cached ) {
		return $cached;
	}
	$out = array();
	if ( function_exists( 'wc_get_products' ) ) {
		$ids = wc_get_products( array( 'limit' => 400, 'status' => 'publish', 'return' => 'ids' ) );
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$type = strtolower( $p->get_attribute( 'pa_hair-type' ) . ' ' . $p->get_attribute( 'hair-type' ) );
			if ( ! preg_match( '/blend|semi|synthetic|fibre|fiber|heat/', $type ) ) {
				continue;
			}
			$text = $p->get_name() . ' ' . $p->get_short_description() . ' ' . $p->get_description();
			if ( preg_match( '/100\s*%\s*(virgin\s+)?human\s+hair|pure\s+human\s+hair/i', wp_strip_all_tags( $text ) ) ) {
				$out[] = array( 'id' => $id, 'name' => $p->get_name(), 'type' => trim( $type ) );
			}
		}
	}
	set_transient( 'nura_seo_hair_conflicts_v1', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}
add_action( 'save_post_product', function () {
	delete_transient( 'nura_seo_hair_conflicts_v1' );
} );
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'edit_products' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && ! in_array( $screen->id, array( 'dashboard', 'edit-product', 'product' ), true ) ) {
		return;
	}
	$rows = nura_seo_hair_claim_conflicts();
	if ( empty( $rows ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>NURA - product accuracy:</strong> these products are set to a blend/synthetic Hair Type but their title or description says "100% human hair". Please correct the wording (or the Hair Type) so shoppers and Google see the same thing:</p><ul style="list-style:disc;margin-left:20px">';
	foreach ( array_slice( $rows, 0, 30 ) as $r ) {
		printf( '<li><a href="%s">%s</a> (%s)</li>', esc_url( get_edit_post_link( $r['id'] ) ), esc_html( $r['name'] ), esc_html( $r['type'] ) );
	}
	echo '</ul></div>';
} );
