<?php
/**
 * Product page conversion (theme 1.103.0, release A of the shopping-experience plan).
 *
 * 1. Delivery countdown under the price: "Order within 3 h 12 min for same-day delivery in
 *    Nairobi" (Mon-Sat before the 5 PM cut-off, East Africa Time), otherwise "Order now - in
 *    Nairobi tomorrow / on Monday". Worked out in the visitor's browser, so it stays correct on
 *    cached pages; without JavaScript a plain sentence shows.
 * 2. Payment row right under Add to bag: M-Pesa, Visa / Mastercard, Lipa Mdogo Mdogo (wigs from
 *    KSh 7,000), pay at the shop on pick-up.
 * 3. "Why order from NURA" box: real photos, studio quality check, 48-hour returns on unworn wigs
 *    (from the business facts), stylist on WhatsApp, studio address.
 * 4. Sticky buy bar: a delivery line under the price.
 * Facts only - nothing here promises what checkout or the policies do not offer.
 * Off: add_filter( 'nura_pdp_convert', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_pdp_convert_on() {
	return function_exists( 'is_product' ) && is_product() && apply_filters( 'nura_pdp_convert', true );
}

function nura_pdp_cutoff_hour() {
	$c = function_exists( 'nura_fact' ) ? (string) nura_fact( 'cutoff', '5 PM' ) : '5 PM';
	if ( preg_match( '/(\d{1,2})(?::\d{2})?\s*(am|pm)?/i', $c, $m ) ) {
		$h = (int) $m[1];
		if ( isset( $m[2] ) && 'pm' === strtolower( $m[2] ) && $h < 12 ) {
			$h += 12;
		}
		return max( 8, min( 21, $h ) );
	}
	return 17;
}

/* 1. Delivery countdown under the price. */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! nura_pdp_convert_on() || ! $product || ! $product->is_in_stock() ) {
		return;
	}
	$h = nura_pdp_cutoff_hour();
	$t = ( $h > 12 ? ( $h - 12 ) . ' PM' : $h . ' AM' );
	echo '<div class="nura-eta" data-nura-eta="' . (int) $h . '"><span class="nura-eta__dot" aria-hidden="true"></span><span class="nura-eta__t">' . esc_html( sprintf( 'Same-day delivery in Nairobi when you order before %s (Mon-Sat). Countrywide in 1-3 days.', $t ) ) . '</span></div>';
}, 11 );

/* 2. Payment row under the add-to-bag button. */
add_action( 'woocommerce_after_add_to_cart_form', function () {
	global $product;
	if ( ! nura_pdp_convert_on() || ! $product ) {
		return;
	}
	$price = $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min', true ) : (float) $product->get_price();
	$pills = array( 'M-Pesa', 'Visa / Mastercard' );
	if ( $price >= 7000 ) {
		$pills[] = 'Lipa Mdogo Mdogo';
	}
	$pills[] = 'Pay at the shop on pick-up';
	echo '<div class="nura-payrow" aria-label="Ways to pay"><span class="nura-payrow__k">Pay with</span>';
	foreach ( $pills as $p ) {
		echo '<span class="nura-payrow__p">' . esc_html( $p ) . '</span>';
	}
	echo '</div>';
}, 5 );

/* 3. Why order from NURA. */
add_action( 'woocommerce_single_product_summary', function () {
	if ( ! nura_pdp_convert_on() ) {
		return;
	}
	$f    = function ( $k, $d ) {
		return function_exists( 'nura_fact' ) ? nura_fact( $k, $d ) : $d;
	};
	$days = (int) $f( 'returns_days', 2 );
	$ret  = $days > 0 ? ( $days * 24 ) . '-hour returns on unworn wigs' : 'Easy returns on unworn wigs';
	$rows = array(
		array( 'Real photos', 'Every wig is photographed as it is - ask for a live video on WhatsApp.' ),
		array( 'Checked in our studio', 'Inspected in Nairobi before it leaves us.' ),
		array( $ret, 'In original condition. Worn or installed wigs cannot be returned for hygiene.' ),
		array( 'A stylist on WhatsApp', 'Help choosing colour, length and fit: +254 714 994 898.' ),
		array( 'See it in person', $f( 'address_short', 'Stanbank House, 2nd Floor, Room 20, Moi Avenue, Nairobi CBD' ) ),
	);
	echo '<div class="nura-why-buy"><p class="nura-why-buy__h">Why order from NURA</p><ul>';
	foreach ( $rows as $r ) {
		echo '<li><strong>' . esc_html( $r[0] ) . '</strong><span>' . esc_html( $r[1] ) . '</span></li>';
	}
	echo '</ul></div>';
}, 38 );

/* 4. Sticky bar delivery line + countdown script (product pages only). */
add_action( 'wp_footer', function () {
	if ( ! nura_pdp_convert_on() ) {
		return;
	}
	?>
<script id="nura-eta-js">(function(){var els=document.querySelectorAll('[data-nura-eta]');if(!els.length)return;
function txt(h){var n=new Date(),e=new Date(n.getTime()+(n.getTimezoneOffset()+180)*60000),d=e.getDay(),m=e.getHours()*60+e.getMinutes(),c=h*60;
if(d!==0&&m<c){var l=c-m,hh=Math.floor(l/60),mm=l%60;return 'Order within '+(hh?hh+' h ':'')+mm+' min for <b>same-day delivery in Nairobi</b>. Countrywide in 1-3 days.';}
var nd=(d===6||d===0)?'on Monday':'tomorrow';if(d===5&&m>=c)nd='on Saturday';return 'Order now - delivered in Nairobi <b>'+nd+'</b>. Countrywide in 1-3 days.';}
function run(){els.forEach(function(el){var t=el.querySelector('.nura-eta__t');if(t)t.innerHTML=txt(parseInt(el.getAttribute('data-nura-eta'),10)||17);});
var s=document.querySelector('.nura-sticky-atc__info');if(s&&els[0]){var x=s.querySelector('.nura-sticky-atc__eta');if(!x){x=document.createElement('span');x.className='nura-sticky-atc__eta';s.appendChild(x);}x.innerHTML=txt(parseInt(els[0].getAttribute('data-nura-eta'),10)||17).replace(/ Countrywide.*$/,'');}}
run();setInterval(run,60000);})();</script>
	<?php
}, 30 );
