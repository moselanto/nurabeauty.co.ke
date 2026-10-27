<?php
/**
 * NURA Beauty theme bootstrap.
 *
 * This parent theme is intentionally lean. Presentation lives in assets/css/main.css,
 * behaviour in assets/js/main.js, and all logic is split into small includes under /inc.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NURA_VERSION', '1.73.0' );
define( 'NURA_DIR', trailingslashit( get_template_directory() ) );
define( 'NURA_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Load a theme include with a graceful guard.
 *
 * @param string $relative Relative path under /inc without extension.
 */
function nura_require( $relative ) {
	$file = NURA_DIR . 'inc/' . $relative . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

// Core setup and assets.
nura_require( 'setup' );
nura_require( 'enqueue' );

// Security & anti-abuse hardening.
nura_require( 'nura-security' );
nura_require( 'nura-login' );
nura_require( 'nura-comments' );
nura_require( 'nura-empty-cats' );
nura_require( 'nura-account-login' );
nura_require( 'nura-mobile-polish' );
nura_require( 'nura-guide-links' );
nura_require( 'nura-schema-graph' );
nura_require( 'nura-ga4-events' );

// Commerce + storefront.
nura_require( 'woocommerce-support' );
nura_require( 'nura-commerce' );
nura_require( 'nura-filters' );
nura_require( 'nura-product-ux' );
nura_require( 'nura-search' );
nura_require( 'nura-polish' );
nura_require( 'nura-speed' );

// Discoverability.
nura_require( 'seo-schema' );
nura_require( 'nura-seo-content' );
nura_require( 'nura-seo' );
nura_require( 'nura-seo-fixes' );
nura_require( 'nura-seo-plus' );
nura_require( 'nura-schema-variants' ); // 1.65.0 ProductGroup + variants.
nura_require( 'nura-geo' );
nura_require( 'nura-content-hub' );
nura_require( 'nura-city-pages' );        // 1.68.0 Mombasa, Kisumu, Nakuru, Eldoret.       // 1.66.0 guides, live price tables, product box.             // 1.65.0 llms.txt, AI answer facts.

// Admin / onboarding.
nura_require( 'required-plugins' );
nura_require( 'sample-data' );
nura_require( 'customizer' );
nura_require( 'dynamic-css' );

// Template helpers.
nura_require( 'template-tags' );
nura_require( 'shortcodes' );
nura_require( 'nura-tidy' );
nura_require( 'nura-new-content' );
nura_require( 'nura-gallery-captions' );
nura_require( 'nura-social' );
nura_require( 'nura-growth' );
nura_require( 'nura-autofix' );
nura_require( 'nura-boost' );

/**
 * Contact page design (v1.45.0): use page-contact.php for the contact page
 * whatever its slug is (WordPress only matches page-contact.php to "contact").
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_page( array( 'contact', 'contact-us', 'contacts', 'get-in-touch' ) ) && ! is_page_template() ) {
		$custom = locate_template( 'page-contact.php' );
		if ( $custom ) {
			return $custom;
		}
	}
	return $template;
}, 20 );

/**
 * About and AI Wig Finder page designs (v1.48.0): use the matching templates
 * whatever the page slug is, unless a template was picked manually.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_page_template() ) {
		return $template;
	}
	$map = array(
		'page-about.php'         => array( 'about', 'about-us', 'our-story' ),
		'page-ai-wig-finder.php' => array( 'ai-wig-finder', 'wig-finder' ),
	);
	foreach ( $map as $file => $slugs ) {
		if ( is_page( $slugs ) ) {
			$custom = locate_template( $file );
			if ( $custom ) {
				return $custom;
			}
		}
	}
	return $template;
}, 20 );
