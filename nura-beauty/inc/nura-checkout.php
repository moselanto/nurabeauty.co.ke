<?php
/**
 * NURA checkout design (theme 1.115.0).
 *
 * Owner (5 Nov 2026): "this page does not look good, make it very professional".
 * Visual layer only - no change to fields, gateways, shipping rules or the
 * server-side pay-on-delivery checks in woocommerce-support.php.
 *   - body class `nura-co` on the checkout (not the order-received page)
 *   - slim title band instead of the tall black hero
 *   - wide container so WooCommerce's block checkout uses its two-column
 *     layout (form left, sticky order summary right) on desktop
 *   - 3-step progress bar (Bag > Details & payment > Confirmation)
 *   - card styling for steps, delivery/payment options, summary and button
 *   - trust strip (secure Paystack payment, CBD pay on delivery, returns, help)
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** True on the checkout form page only. */
function nura_co_is_form() {
	return function_exists( 'is_checkout' ) && is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) && ! is_wc_endpoint_url( 'order-pay' );
}

add_filter( 'body_class', function ( $c ) {
	if ( nura_co_is_form() ) {
		$c[] = 'nura-co';
	}
	return $c;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( nura_co_is_form() ) {
		wp_enqueue_style( 'nura-checkout', NURA_URI . 'assets/css/nura-checkout.css', array(), NURA_VERSION );
	}
}, 60 );

/** Progress bar above and trust strip below the checkout block. */
add_filter( 'the_content', function ( $html ) {
	if ( ! nura_co_is_form() || ! in_the_loop() || ! is_main_query() ) {
		return $html;
	}
	$cart  = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
	$steps = '<ol class="nura-co-steps" aria-label="' . esc_attr__( 'Checkout progress', 'nura-beauty' ) . '">'
		. '<li class="is-done"><a href="' . esc_url( $cart ) . '"><span>1</span>' . esc_html__( 'Bag', 'nura-beauty' ) . '</a></li>'
		. '<li class="is-current" aria-current="step"><span>2</span>' . esc_html__( 'Details & payment', 'nura-beauty' ) . '</li>'
		. '<li><span>3</span>' . esc_html__( 'Confirmation', 'nura-beauty' ) . '</li></ol>';
	$wa    = function_exists( 'nura_opt' ) && nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
	$items = array(
		array( 'lock', __( 'Secure payment', 'nura-beauty' ), __( 'M-Pesa, Visa and Mastercard through Paystack', 'nura-beauty' ) ),
		array( 'bike', __( 'Same-day Nairobi', 'nura-beauty' ), __( 'Order before 5pm. Pay on delivery in Nairobi CBD', 'nura-beauty' ) ),
		array( 'back', __( '48-hour returns', 'nura-beauty' ), __( 'Unworn wigs in their original condition', 'nura-beauty' ) ),
		array( 'chat', __( 'Need help?', 'nura-beauty' ), '<a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Chat with a stylist on WhatsApp', 'nura-beauty' ) . '</a>' ),
	);
	$icons = array(
		'lock' => '<path d="M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5z" />',
		'bike' => '<circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="M6 17l4-8h5l3 8M12 9l-1-3h-2"/>',
		'back' => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
		'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
	);
	$trust = '<ul class="nura-co-trust">';
	foreach ( $items as $it ) {
		$trust .= '<li><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[ $it[0] ] . '</svg><span><strong>' . esc_html( $it[1] ) . '</strong><small>' . wp_kses( $it[2], array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ) . '</small></span></li>';
	}
	$trust .= '</ul>';
	return $steps . $html . $trust;
}, 20 );
