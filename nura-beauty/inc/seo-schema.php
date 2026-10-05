<?php
/**
 * Structured data + social metadata.
 *
 * Emits JSON-LD for Organization, WebSite (with SearchAction), Product, BreadcrumbList,
 * and FAQPage, plus Open Graph and Twitter Card tags. Skips output when a dedicated SEO
 * plugin (Yoast, Rank Math, SEOPress) is active so you never get duplicate schema.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nura_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * Open Graph + Twitter tags.
 */
function nura_social_meta() {
	if ( nura_seo_plugin_active() ) {
		return;
	}
	$title = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$url   = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
	$image = '';

	if ( is_singular() ) {
		$desc  = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 30 );
		$url   = get_permalink();
		if ( has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'nura-hero' );
		}
	}
	if ( ! $image ) {
		$image = get_theme_mod( 'nura_default_share_image', '' );
	}

	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:type" content="%s" />' . "\n", is_singular( 'product' ) ? 'product' : 'website' );
	printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
	if ( $image ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
	}
	printf( '<meta name="twitter:card" content="%s" />' . "\n", $image ? 'summary_large_image' : 'summary' );
	printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
	printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $desc ) );
	if ( $image ) {
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
	}
}
add_action( 'wp_head', 'nura_social_meta', 5 );

/**
 * Organization + WebSite JSON-LD (site-wide).
 */
function nura_org_schema() {
	if ( nura_seo_plugin_active() || ! is_front_page() ) {
		return;
	}
	$org = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'logo'     => wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ),
		'address'  => array(
			'@type'           => 'PostalAddress',
			'addressLocality' => get_theme_mod( 'nura_city', 'Nairobi' ),
			'addressCountry'  => 'KE',
			'streetAddress'   => get_theme_mod( 'nura_address', '' ),
		),
		'contactPoint' => array(
			'@type'       => 'ContactPoint',
			'telephone'   => get_theme_mod( 'nura_phone', '' ),
			'contactType' => 'customer service',
			'areaServed'  => 'KE',
		),
		'sameAs' => array_values( array_filter( array(
			get_theme_mod( 'nura_instagram', '' ),
			get_theme_mod( 'nura_tiktok', '' ),
			get_theme_mod( 'nura_facebook', '' ),
		) ) ),
	);
	$website = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'WebSite',
		'name'            => get_bloginfo( 'name' ),
		'url'             => home_url( '/' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $org ) . '</script>' . "\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $website ) . '</script>' . "\n";
}
add_action( 'wp_head', 'nura_org_schema', 6 );

/**
 * Product JSON-LD on single product pages.
 */
function nura_product_schema() {
	if ( nura_seo_plugin_active() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	global $product;
	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( get_the_ID() );
	}
	if ( ! $product ) {
		return;
	}
	$data = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => $product->get_name(),
		'description' => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
		'sku'         => $product->get_sku(),
		'image'       => wp_get_attachment_image_url( $product->get_image_id(), 'full' ),
		'brand'       => array( '@type' => 'Brand', 'name' => 'NURA' ),
		'offers'      => array(
			'@type'         => 'Offer',
			'price'         => $product->get_price(),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			'url'           => get_permalink( $product->get_id() ),
		),
	);
	if ( $product->get_review_count() > 0 ) {
		$data['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $product->get_average_rating(),
			'reviewCount' => $product->get_review_count(),
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
}
add_action( 'wp_head', 'nura_product_schema', 7 );

/**
 * Breadcrumb JSON-LD helper, called from the visible breadcrumb template tag.
 *
 * @param array $crumbs Array of [name, url].
 */
function nura_breadcrumb_schema( $crumbs ) {
	if ( nura_seo_plugin_active() || empty( $crumbs ) ) {
		return;
	}
	$items = array();
	foreach ( $crumbs as $i => $c ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $c['name'],
			'item'     => $c['url'],
		);
	}
	$data = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
}


/**
 * LocalBusiness JSON-LD for local SEO (front page). Complements the Organization node.
 */
function nura_localbusiness_schema() {
	if ( nura_seo_plugin_active() || ! is_front_page() ) {
		return;
	}
	$data = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'HealthAndBeautyBusiness',
		'name'       => get_bloginfo( 'name' ),
		'url'        => home_url( '/' ),
		'image'      => wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ),
		'telephone'  => get_theme_mod( 'nura_phone', '' ),
		'priceRange' => 'KES',
		'areaServed' => 'Kenya',
		'address'    => array_filter( array(
			'@type'           => 'PostalAddress',
			'addressLocality' => get_theme_mod( 'nura_city', 'Nairobi' ),
			'addressCountry'  => 'KE',
			'streetAddress'   => get_theme_mod( 'nura_address', '' ),
		) ),
	);
	$hours = get_theme_mod( 'nura_hours', '' );
	if ( $hours ) {
		$data['openingHours'] = $hours;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array_filter( $data ) ) . '</script>' . "\n";
}
add_action( 'wp_head', 'nura_localbusiness_schema', 6 );
