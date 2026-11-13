<?php
/**
 * Theme 1.86.0: product image descriptions and the review request flow.
 *
 * 1. Image search: every product image without alt text gets a descriptive one
 *    built from real product data: "<product name>, <hair type> wig[, <colour caption>]".
 *    Existing alt text written in the Media Library is never overwritten.
 * 2. Reviews (real reviews only, never invented):
 *    - nurabeauty.co.ke/review/ redirects to the Google "write a review" form for
 *      the NURA Beauty listing (easy to share on WhatsApp, receipts, cards, QR).
 *    - The "order completed" email ends with a short review request and that link.
 *    The NURA follow-up messages (WooCommerce > NURA Follow-ups) already send a
 *    review request 3 days after completion once the owner switches them on.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Hair type label for a product. */
function nura_alt_hair( $p ) {
	$t = strtolower( (string) $p->get_attribute( 'pa_hair-type' ) . ' ' . $p->get_name() );
	if ( false !== strpos( $t, 'blend' ) || false !== strpos( $t, 'semi' ) ) {
		return __( 'Semi Human', 'nura-beauty' );
	}
	if ( false !== strpos( $t, 'human' ) ) {
		return __( 'Pure Human', 'nura-beauty' );
	}
	return __( 'luxury fibre', 'nura-beauty' );
}

add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! empty( $attr['alt'] ) || ! $attachment || ! function_exists( 'wc_get_product' ) ) {
		return $attr;
	}
	$pid = (int) $attachment->post_parent;
	$p   = $pid ? wc_get_product( $pid ) : null;
	if ( ( ! $p || 'product' !== get_post_type( $pid ) ) ) {
		global $product;
		$p = ( $product instanceof WC_Product && ( (int) $product->get_image_id() === (int) $attachment->ID || in_array( (int) $attachment->ID, array_map( 'intval', $product->get_gallery_image_ids() ), true ) ) ) ? $product : null;
	}
	if ( ! $p ) {
		return $attr;
	}
	if ( $p->is_type( 'variation' ) && $p->get_parent_id() ) {
		$p = wc_get_product( $p->get_parent_id() );
	}
	$name = wp_strip_all_tags( $p->get_name() );
	$alt  = $name . ', ' . nura_alt_hair( $p ) . ( false === stripos( $name, 'wig' ) ? ' wig' : '' );
	$cap  = trim( (string) wp_get_attachment_caption( $attachment->ID ) );
	if ( '' !== $cap && false === stripos( $alt, $cap ) ) {
		$alt .= ', ' . $cap;
	}
	$attr['alt'] = $alt;
	return $attr;
}, 20, 2 );

/* ----------------------------------------------------------- reviews */

function nura_review_url() {
	if ( class_exists( 'NURAX_Community' ) && method_exists( 'NURAX_Community', 'google_review_url' ) && '' !== NURAX_Community::google_review_url() ) {
		return NURAX_Community::google_review_url();
	}
	return 'https://search.google.com/local/writereview?placeid=ChIJGx-_W1sRLxgRxNUNZe1z9ug';
}

/** nurabeauty.co.ke/review/ -> Google review form (only when no real page uses that slug). */
add_action( 'template_redirect', function () {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! in_array( rtrim( $path, '/' ), array( '/review', '/reviews/google', '/google-review' ), true ) || get_page_by_path( 'review' ) ) {
		return;
	}
	wp_redirect( nura_review_url(), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed Google URL.
	exit;
}, 1 );

/**
 * Completed-order email: review request (1.120.0: on-site product review first,
 * one "Review this wig" button per product linking to its #reviews form, then
 * the Google review as a second option).
 */
add_action( 'woocommerce_email_after_order_table', function ( $order, $sent_to_admin, $plain_text, $email = null ) {
	if ( $sent_to_admin || ! $email || 'customer_completed_order' !== $email->id || ! $order instanceof WC_Order ) {
		return;
	}
	$items = array();
	foreach ( $order->get_items() as $item ) {
		$pid = is_object( $item ) && method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
		if ( $pid && ! isset( $items[ $pid ] ) && 'publish' === get_post_status( $pid ) ) {
			$items[ $pid ] = array( wp_strip_all_tags( get_the_title( $pid ) ), get_permalink( $pid ) . '#reviews' );
		}
		if ( count( $items ) >= 4 ) {
			break;
		}
	}
	$google = home_url( '/review/' );
	$msg    = __( 'Loving your NURA wig? Please rate it on our website - your honest review (and a photo if you like) helps other women choose with confidence and means a lot to our small team.', 'nura-beauty' );
	$gmsg   = __( 'You can also leave us a Google review:', 'nura-beauty' );
	if ( $plain_text ) {
		echo "\n" . esc_html( $msg ) . "\n";
		foreach ( $items as $it ) {
			echo esc_html( $it[0] ) . ': ' . esc_url_raw( $it[1] ) . "\n";
		}
		echo esc_html( $gmsg ) . ' ' . esc_url_raw( $google ) . "\n";
		return;
	}
	echo '<div style="margin:24px 0;padding:18px 20px;border:1px solid #ECE4D3;border-radius:10px;background:#FAF7F0"><p style="margin:0 0 4px;font-weight:700">' . esc_html__( 'How is your new wig?', 'nura-beauty' ) . '</p><p style="margin:0 0 12px">' . esc_html( $msg ) . '</p>';
	foreach ( $items as $it ) {
		echo '<p style="margin:0 0 8px"><a href="' . esc_url( $it[1] ) . '" style="display:inline-block;background:#0E0E0E;color:#fff;padding:10px 18px;border-radius:999px;text-decoration:none">' . esc_html( sprintf( __( 'Review %s', 'nura-beauty' ), $it[0] ) ) . ' &#9733;</a></p>';
	}
	echo '<p style="margin:12px 0 0;font-size:13px;color:#555">' . esc_html( $gmsg ) . ' <a href="' . esc_url( $google ) . '" style="color:#8a6d1f">' . esc_html__( 'Review NURA on Google', 'nura-beauty' ) . '</a></p></div>';
}, 20, 4 );
