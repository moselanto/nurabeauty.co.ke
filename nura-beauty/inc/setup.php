<?php
/**
 * Theme setup: supports, menus, image sizes, editor styles.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nura_setup' ) ) {
	function nura_setup() {
		load_theme_textdomain( 'nura-beauty', NURA_DIR . 'languages' );

		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/editor.css' );

		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);

		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 260,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		// Editorial image sizes for luxury layouts.
		set_post_thumbnail_size( 1200, 1500, true );
		add_image_size( 'nura-portrait', 900, 1200, true );   // Product / editorial portrait.
		add_image_size( 'nura-hero', 1920, 1080, true );       // Full-bleed hero.
		add_image_size( 'nura-square', 900, 900, true );        // Grid / lookbook.
		add_image_size( 'nura-thumb', 400, 500, true );         // Mini-cart / related.

		register_nav_menus(
			array(
				'primary'   => __( 'Primary Menu', 'nura-beauty' ),
				'secondary' => __( 'Top Bar Menu', 'nura-beauty' ),
				'footer_shop'    => __( 'Footer - Shop', 'nura-beauty' ),
				'footer_help'    => __( 'Footer - Help & Support', 'nura-beauty' ),
				'footer_company' => __( 'Footer - Company', 'nura-beauty' ),
				'mobile'    => __( 'Mobile Menu', 'nura-beauty' ),
			)
		);

		// Let WooCommerce manage the gallery zoom/lightbox/slider.
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
		add_theme_support( 'woocommerce' );
	}
}
add_action( 'after_setup_theme', 'nura_setup' );

/**
 * Content width.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 1320;
}

/**
 * Widget areas.
 */
function nura_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Shop Sidebar', 'nura-beauty' ),
			'id'            => 'shop-sidebar',
			'description'   => __( 'Filters and widgets shown on shop and category pages.', 'nura-beauty' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
	for ( $i = 1; $i <= 4; $i++ ) {
		register_sidebar(
			array(
				/* translators: %d: footer column number */
				'name'          => sprintf( __( 'Footer Column %d', 'nura-beauty' ), $i ),
				'id'            => 'footer-' . $i,
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h4 class="widget-title">',
				'after_title'   => '</h4>',
			)
		);
	}
}
add_action( 'widgets_init', 'nura_widgets_init' );
