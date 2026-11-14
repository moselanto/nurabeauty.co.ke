<?php
/**
 * NURA R6 (theme 1.150.0, 14 Nov 2026) - Search Console clean-up and speed.
 *
 * 1. 404s (Search Console export, 14 Nov): removed products bubbles-full-frontal-wig and
 *    rich-short-wavy-wig get a 301 to the closest live page; paged archives that no longer
 *    exist (/shop/page/3/, /product-tag/x/page/5/) 301 to page 1 of the same archive; literal
 *    robots-pattern URLs Google picked up (/*, /wp-content/*, /wp-*.php) 301 to the home page.
 * 2. 5xx: every 5xx URL was a /shop/ filter combination (3-6 filters at once), crawled in bursts
 *    and timing out on the host. Filter, sort and price URLs are already noindex; they are now
 *    also disallowed for every crawler (they were only disallowed for AI bots).
 * 3. Merchant listings "Missing field validFrom" (6 items): every Offer gets validFrom = the real
 *    sale start date when a sale is scheduled, otherwise the date the product was published in
 *    the store (WooCommerce date_created). Nothing invented.
 * 4. Speed (Lighthouse, 14 Nov): the Google tag (GT-), Google Ads (AW-) and Meta pixel were the
 *    biggest main-thread blockers on phones. They now load on the first tap/scroll/key or after the
 *    fallback timeout on shop, product, category and content pages (stage 3). Their inline config
 *    still runs immediately and queues into dataLayer / fbq, so nothing is lost for visitors who
 *    interact. Cart, checkout, My Account and thank-you pages are untouched.
 * 5. /shop/ desktop CLS 0.09: the header's first row was 66 px under the critical CSS and 54 px
 *    after main.css, and the results bar floats arrived late. Both are fixed in the first paint.
 *
 * Off switches: add_filter( 'nura_r6_<part>', '__return_false' ) with part = redirects, robots,
 * validfrom, delay_ga, cls.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r6_on( $part ) {
	return (bool) apply_filters( 'nura_r6_' . $part, true );
}

/* ---- 1. 404 -> 301 ---- */
add_filter( 'nura_removed_product_redirects', function ( $map ) {
	$map['bubbles-full-frontal-wig'] = '/product-category/wigs/human-hair-wigs/curly-wigs/';
	$map['rich-short-wavy-wig']      = '/product/bob-wavy-wig/';
	$map['coily-headband-wig']       = '/product-category/wigs/headband-wigs/';
	return $map;
} );

add_action( 'template_redirect', function () {
	if ( ! nura_r6_on( 'redirects' ) || ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = rawurldecode( $path );
	if ( false !== strpos( $path, '*' ) ) {
		wp_safe_redirect( home_url( '/' ), 301, 'NURA' );
		exit;
	}
	if ( preg_match( '#^(/.*?)/page/\d+/?$#', $path, $m ) ) {
		wp_safe_redirect( home_url( trailingslashit( $m[1] ) ), 301, 'NURA' );
		exit;
	}
}, 6 );

/* ---- 2. robots.txt: shop filter URLs out of every crawler's path ---- */
add_filter( 'robots_txt', function ( $out, $public ) {
	if ( ! nura_r6_on( 'robots' ) || ! $public ) {
		return $out;
	}
	return rtrim( $out ) . "\n\n# NURA 1.150.0: shop filter / sort / price combinations (noindex; they caused 5xx when crawled in bursts)\nUser-agent: *\nDisallow: /*?*filter_\nDisallow: /*?*query_type_\nDisallow: /*?*min_price=\nDisallow: /*?*max_price=\nDisallow: /*?*orderby=\nDisallow: /*?*instock=\nDisallow: /*?*wc-ajax=\n";
}, 99, 2 );

/* ---- 3. validFrom on every Offer ---- */
function nura_r6_valid_from( $product ) {
	if ( ! $product ) {
		return '';
	}
	if ( $product->is_on_sale() && $product->get_date_on_sale_from() ) {
		return $product->get_date_on_sale_from()->date( 'c' );
	}
	$p = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$p = $p ? $p : $product;
	return $p->get_date_created() ? $p->get_date_created()->date( 'c' ) : '';
}
function nura_r6_offer_walk( &$node, $fallback ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$type = isset( $node['@type'] ) ? (array) $node['@type'] : array();
	if ( in_array( 'Offer', $type, true ) && empty( $node['validFrom'] ) ) {
		$vf  = '';
		$url = isset( $node['url'] ) ? (string) $node['url'] : '';
		if ( $url && false !== strpos( $url, 'variation_id=' ) && preg_match( '/variation_id=(\d+)/', $url, $m ) ) {
			$vf = nura_r6_valid_from( wc_get_product( (int) $m[1] ) );
		}
		$vf = $vf ? $vf : $fallback;
		if ( $vf ) {
			$node['validFrom'] = $vf;
		}
	}
	foreach ( $node as $k => &$child ) {
		if ( is_array( $child ) ) {
			nura_r6_offer_walk( $child, $fallback );
		}
	}
	unset( $child );
}
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! nura_r6_on( 'validfrom' ) || ! function_exists( 'is_product' ) || ! is_product() || ! is_array( $data ) ) {
		return $data;
	}
	$fallback = nura_r6_valid_from( wc_get_product( get_queried_object_id() ) );
	nura_r6_offer_walk( $data, $fallback );
	return $data;
}, 998 );

/* ---- 4. Google tag, Google Ads and Meta load on first interaction (stage 3) ---- */
add_filter( 'nura_perf_stage', function ( $s ) {
	return nura_r6_on( 'delay_ga' ) ? max( 3, (int) $s ) : $s;
}, 20 );
add_filter( 'nura_delay_tag_hosts', function ( $hosts ) {
	if ( nura_r6_on( 'delay_ga' ) && function_exists( 'nura_perf_protected' ) && ! nura_perf_protected() ) {
		$hosts[] = 'googletagmanager.com/gtag/js';
	}
	return array_values( array_unique( $hosts ) );
}, 30 );
add_action( 'template_redirect', function () {
	if ( ! nura_r6_on( 'delay_ga' ) || is_admin() || ( function_exists( 'nura_perf_protected' ) && nura_perf_protected() ) || is_feed() ) {
		return;
	}
	// Inline / hard-coded gtag loaders that do not pass through script_loader_tag.
	ob_start( function ( $html ) {
		return preg_replace( '#<script([^>]*?)\s+src=(["\'])(https://www\.googletagmanager\.com/gtag/js\?id=[^"\']+)\2([^>]*)>\s*</script>#i', '<script type="text/plain" data-nura-src="$3"></script>', $html );
	} );
}, 98 );

/* ---- 5. First-paint layout that matches main.css (desktop header row, results bar) ---- */
add_action( 'wp_head', function () {
	if ( ! nura_r6_on( 'cls' ) ) {
		return;
	}
	// 1.150.1: the header row lock only on shop / category / tag archives (on product pages it caused a 0.77 shift).
	if ( ! ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) {
		return;
	}
	echo '<style id="nura-r6-cls">@media(min-width:861px){html body.archive .nura-header-inner{grid-template-rows:54px auto}html body.archive .nura-header-inner .nura-logo-wrap{line-height:0;align-self:center}}'
		. '.nura-shop-results .woocommerce-result-count{float:left;margin:0 20px 0 0;line-height:40px}.nura-shop-results .woocommerce-ordering{float:right;margin:0 0 1em}</style>' . "\n";
}, 3 );
