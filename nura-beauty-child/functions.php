<?php
/**
 * NURA Beauty Child - functions.
 *
 * Enqueues the parent + child stylesheets. Add your PHP customisations here;
 * they load after the parent, so you can safely override with add_filter /
 * remove_action without editing the parent theme.
 *
 * @package NURA_Beauty_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the child stylesheet after the parent's main stylesheet.
 */
function nura_child_enqueue() {
	// Parent 'nura-main' is registered in the parent theme; depend on it so order is correct.
	wp_enqueue_style(
		'nura-child',
		get_stylesheet_uri(),
		array( 'nura-main' ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'nura_child_enqueue', 30 );

/* ---------------------------------------------------------
 * Add custom PHP below. Examples:
 *
 * // Change products-per-page on the shop.
 * add_filter( 'loop_shop_per_page', function () { return 9; }, 30 );
 *
 * // Add an extra menu location.
 * // register_nav_menus( array( 'promo' => 'Promo Menu' ) );
 * ------------------------------------------------------- */
