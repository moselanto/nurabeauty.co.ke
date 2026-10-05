<?php
/**
 * Dynamic CSS + fonts driven by Customizer options.
 *
 * Outputs :root variable overrides so the palette and type system set in the
 * Customizer take effect site-wide. This is why nothing is hard-coded: main.css
 * ships sensible NURA defaults, and this inline layer lets the owner override
 * every colour and font from the admin.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the Google Fonts URL from the four configurable families.
 */
function nura_dynamic_fonts_url() {
	$families = array(
		nura_opt( 'nura_font_display' ) . ':wght@400;500;600;700;800',
		nura_opt( 'nura_font_editorial' ) . ':ital,wght@0,400;0,500;0,600;1,400',
		nura_opt( 'nura_font_body' ) . ':wght@300;400;500;600',
		nura_opt( 'nura_font_ui' ) . ':wght@300;400;500;600',
	);
	$parts = array();
	foreach ( $families as $f ) {
		$parts[] = 'family=' . str_replace( ' ', '+', $f );
	}
	return 'https://fonts.googleapis.com/css2?' . implode( '&', $parts ) . '&display=swap';
}

/**
 * Inline the variable overrides after main.css loads.
 */
function nura_dynamic_css() {
	$css = sprintf(
		':root{--nura-ink:%1$s;--nura-gold:%2$s;--nura-ivory:%3$s;--nura-plum:%4$s;--nura-nude:%5$s;' .
		'--font-display:"%6$s",Georgia,serif;--font-editorial:"%7$s",Georgia,serif;' .
		'--font-body:"%8$s",Arial,sans-serif;--font-ui:"%9$s","%8$s",Arial,sans-serif;}',
		sanitize_hex_color( nura_opt( 'nura_color_ink' ) ),
		sanitize_hex_color( nura_opt( 'nura_color_gold' ) ),
		sanitize_hex_color( nura_opt( 'nura_color_ivory' ) ),
		sanitize_hex_color( nura_opt( 'nura_color_plum' ) ),
		sanitize_hex_color( nura_opt( 'nura_color_nude' ) ),
		esc_html( nura_opt( 'nura_font_display' ) ),
		esc_html( nura_opt( 'nura_font_editorial' ) ),
		esc_html( nura_opt( 'nura_font_body' ) ),
		esc_html( nura_opt( 'nura_font_ui' ) )
	);
	wp_add_inline_style( 'nura-main', $css );
}
add_action( 'wp_enqueue_scripts', 'nura_dynamic_css', 20 );
