<?php
/**
 * GA4 shopping-funnel events that Site Kit's WooCommerce tracking misses with NURA's
 * cart drawer and block checkout: add_to_cart and begin_checkout.
 * Sends through the gtag() that Site Kit already loads (no extra tag, no Tag Manager).
 * purchase stays with Site Kit (verified working on order #1743).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Queue an add_to_cart event in the shopper's session (works for AJAX and normal adds). */
add_action( 'woocommerce_add_to_cart', function ( $cart_item_key, $product_id, $quantity, $variation_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$p = wc_get_product( $variation_id ? $variation_id : $product_id );
	if ( ! $p ) {
		return;
	}
	$queue   = (array) WC()->session->get( 'nura_ga4_queue', array() );
	$queue[] = array(
		'item_id'   => (string) ( $p->get_sku() ? $p->get_sku() : $p->get_id() ),
		'item_name' => wp_strip_all_tags( $p->get_name() ),
		'price'     => (float) wc_get_price_to_display( $p ),
		'quantity'  => (int) $quantity,
	);
	WC()->session->set( 'nura_ga4_queue', array_slice( $queue, -10 ) );
}, 20, 4 );

/** Cart items in GA4 format. */
function nura_ga4_cart_items() {
	$items = array();
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $items;
	}
	foreach ( WC()->cart->get_cart() as $ci ) {
		$p = isset( $ci['data'] ) ? $ci['data'] : null;
		if ( ! $p ) {
			continue;
		}
		$items[] = array(
			'item_id'   => (string) ( $p->get_sku() ? $p->get_sku() : $p->get_id() ),
			'item_name' => wp_strip_all_tags( $p->get_name() ),
			'price'     => (float) wc_get_price_to_display( $p ),
			'quantity'  => (int) $ci['quantity'],
		);
	}
	return $items;
}

add_action( 'wp_footer', function () {
	if ( is_admin() || ! function_exists( 'WC' ) ) {
		return;
	}
	$events = array();

	// Pending add_to_cart events.
	if ( WC()->session ) {
		$queue = (array) WC()->session->get( 'nura_ga4_queue', array() );
		foreach ( $queue as $it ) {
			$events[] = array(
				'name'   => 'add_to_cart',
				'params' => array(
					'currency' => 'KES',
					'value'    => round( $it['price'] * $it['quantity'], 2 ),
					'items'    => array( $it ),
				),
			);
		}
		if ( $queue ) {
			WC()->session->set( 'nura_ga4_queue', array() );
		}
	}

	// begin_checkout on the checkout page (not the thank-you page).
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty() ) {
		$events[] = array(
			'name'   => 'begin_checkout',
			'params' => array(
				'currency' => 'KES',
				'value'    => (float) WC()->cart->get_total( 'edit' ),
				'items'    => nura_ga4_cart_items(),
			),
		);
	}

	if ( ! $events ) {
		return;
	}
	?>
	<script id="nura-ga4-events">
	(function (ev) {
		var tries = 0;
		function send() {
			if (typeof window.gtag === 'function') {
				ev.forEach(function (e) { window.gtag('event', e.name, e.params); });
			} else if (tries++ < 20) {
				setTimeout(send, 250);
			}
		}
		send();
	})(<?php echo wp_json_encode( $events ); ?>);
	</script>
	<?php
}, 99 );
