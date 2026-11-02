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

/* ------------------------------------------------------------------
 * 1.84.0: lead events for GA4 (mark as key events in GA4 Admin):
 *   whatsapp_click       - any click to WhatsApp (wa.me, api.whatsapp.com, whatsapp:)
 *   finder_use           - 5-step AI Stylist finder submitted (results shown)
 *   installation_booking - studio appointment request accepted by the booking API
 *   stylist_question     - a question sent to the NURA Stylist chat
 * Inline, dependency-free; sends through the gtag() already on the page.
 * No personal data (names, phones) is ever sent.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$finder = array();
	foreach ( array( 'b' => 'budget', 't' => 'wig_type', 'l' => 'look', 'i' => 'installation', 'n' => 'length' ) as $k => $label ) {
		if ( isset( $_GET[ 'nsf_' . $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only analytics.
			$finder[ $label ] = sanitize_key( wp_unslash( $_GET[ 'nsf_' . $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	?>
<script id="nura-ga4-leads">(function(w,d){function ev(n,p){p=p||{};p.page_path=location.pathname;if(typeof w.gtag==='function'){w.gtag('event',n,p);}else{(w.dataLayer=w.dataLayer||[]).push(['event',n,p]);}}
d.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a){return;}var h=a.getAttribute('href')||'';if(/wa\.me|api\.whatsapp\.com|whatsapp:/i.test(h)){ev('whatsapp_click',{link_text:(a.textContent||'').trim().slice(0,60),link_location:a.closest('header')?'header':(a.closest('footer')?'footer':(a.closest('[data-nurax-stylist]')?'stylist_chat':(a.closest('.product,.single-product')?'product':'page')))});}},true);
d.addEventListener('submit',function(e){var f=e.target;if(f&&f.matches&&f.matches('[data-stylist-form]')){ev('stylist_question',{});}},true);
var F=<?php echo wp_json_encode( (object) $finder ); ?>;if(F&&F.budget){ev('finder_use',F);}
if(w.fetch){var of=w.fetch;w.fetch=function(u,o){var p=of.apply(this,arguments);try{var url=typeof u==='string'?u:(u&&u.url)||'';if(/stylist-booking/.test(url)&&o&&String(o.method||'').toUpperCase()==='POST'){var svc='';try{svc=(JSON.parse(o.body||'{}').service)||'';}catch(x){}p.then(function(r){if(r&&r.ok){ev('installation_booking',{service:String(svc).slice(0,40)});}}).catch(function(){});}}catch(x){}return p;};}
})(window,document);</script>
	<?php
}, 98 );
