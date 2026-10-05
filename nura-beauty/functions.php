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

define( 'NURA_VERSION', '1.21.0' );
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

// Commerce + storefront.
nura_require( 'woocommerce-support' );
nura_require( 'nura-commerce' );
nura_require( 'nura-filters' );
nura_require( 'nura-product-ux' );
nura_require( 'nura-search' );

// Discoverability.
nura_require( 'seo-schema' );
nura_require( 'nura-seo-content' );

// Admin / onboarding.
nura_require( 'required-plugins' );
nura_require( 'sample-data' );
nura_require( 'customizer' );
nura_require( 'dynamic-css' );

// Template helpers.
nura_require( 'template-tags' );
nura_require( 'shortcodes' );
