<?php
/**
 * Product gallery colour names (v1.55.8).
 * Shows each gallery photo's caption (e.g. "Colour 33", "613 Blonde") under the
 * photo on product pages, and uses it in the image alt text when alt is empty.
 * Set or edit a caption in Media > Library > (photo) > Caption.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_single_product_image_thumbnail_html', function ( $html, $attachment_id ) {
	$cap = trim( (string) wp_get_attachment_caption( $attachment_id ) );
	if ( '' === $cap || false !== strpos( $html, 'nura-gcap' ) ) {
		return $html;
	}
	$label = '<span class="nura-gcap">' . esc_html( $cap ) . '</span>';
	$pos   = strrpos( $html, '</div>' );
	return false === $pos ? $html . $label : substr_replace( $html, $label . '</div>', $pos, 6 );
}, 20, 2 );

add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! empty( $attr['alt'] ) || ! $attachment || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $attr;
	}
	$cap = trim( (string) wp_get_attachment_caption( $attachment->ID ) );
	if ( '' !== $cap ) {
		$attr['alt'] = get_the_title( get_queried_object_id() ) . ' - ' . $cap;
	}
	return $attr;
}, 25, 2 );

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-gcap-css">.woocommerce-product-gallery__image .nura-gcap{display:block;text-align:center;padding:.55rem .5rem .2rem;font-family:var(--font-ui,"Jost",sans-serif);font-size:.82rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#0e0e0e}.woocommerce-product-gallery__image .nura-gcap::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:#c9a24b;margin-right:.45rem;vertical-align:middle}</style>' . "\n";
}, 40 );
