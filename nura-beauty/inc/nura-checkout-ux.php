<?php
/**
 * NURA Beauty 1.129.0 - checkout clarity (owner request, 11 Nov 2026).
 *
 * Fixes from the logged-out checkout walkthrough:
 * 1. One button wording: "Add to bag" everywhere (main button, cards, sticky bar).
 *    Sticky bar Adding... state and chosen colour live in main.js.
 * 2. Delivery choices explained: "Nairobi CBD delivery" says pay on delivery,
 *    the general Nairobi rider rate says it covers the rest of Nairobi and is
 *    paid before dispatch. Text only - rate IDs, costs and the CBD detection
 *    (label contains "CBD") are unchanged.
 * 3. Pay on delivery for Nairobi CBD: see woocommerce-support.php (rate label
 *    from session + block checkout toggle reads the chosen rate).
 * 4. Totals stay visible (last known value, dimmed, "Updating totals...") while
 *    block checkout recalculates.
 * 5. The same-day cut-off line depends on the Nairobi time (before 5 PM Mon-Sat:
 *    today; after: tomorrow / Monday). Rendered on the server and refreshed in
 *    the browser so a cached page never shows the wrong day.
 * 6. Studio pickup shows its address once: long pickup names that repeat the
 *    address become "NURA studio pickup".
 * Off switch for 2, 4, 5, 6: add_filter( 'nura_checkout_ux', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_checkout_ux_on() {
	return (bool) apply_filters( 'nura_checkout_ux', true );
}

/* 1. Same button wording everywhere. ------------------------------------- */
function nura_bag_wording( $text ) {
	return in_array( $text, array( 'Add to cart', __( 'Add to cart', 'woocommerce' ) ), true ) ? __( 'Add to bag', 'nura-beauty' ) : $text;
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'nura_bag_wording', 20 );
add_filter( 'woocommerce_product_add_to_cart_text', 'nura_bag_wording', 20 );

/* 5. Time-aware cut-off. --------------------------------------------------- */

/** Cut-off hour (24 h) from the business facts ("5 PM" -> 17). */
function nura_cutoff_hour() {
	$c = function_exists( 'nura_fact' ) ? (string) nura_fact( 'cutoff', '5 PM' ) : '5 PM';
	if ( preg_match( '/(\d{1,2})(?::\d{2})?\s*(am|pm)?/i', $c, $m ) ) {
		$h = (int) $m[1];
		if ( isset( $m[2] ) && 'pm' === strtolower( $m[2] ) && $h < 12 ) {
			$h += 12;
		}
		return $h;
	}
	return 17;
}

/** "Order before 5pm for delivery today" / "Order now for delivery tomorrow" / "... on Monday". */
function nura_cutoff_line() {
	$tz  = new DateTimeZone( 'Africa/Nairobi' );
	$now = new DateTime( 'now', $tz );
	$dow = (int) $now->format( 'N' ); // 1 Mon .. 7 Sun.
	$hr  = (int) $now->format( 'G' );
	$cut = nura_cutoff_hour();
	$lbl = strtolower( str_replace( ' ', '', function_exists( 'nura_fact' ) ? (string) nura_fact( 'cutoff', '5 PM' ) : '5 PM' ) );
	if ( $dow <= 6 && $hr < $cut ) {
		/* translators: %s: cut-off time, e.g. 5pm. */
		return sprintf( __( 'Order before %s for delivery today', 'nura-beauty' ), $lbl );
	}
	if ( $dow <= 5 ) {
		return __( 'Order now for delivery tomorrow', 'nura-beauty' );
	}
	return __( 'Order now for delivery on Monday', 'nura-beauty' );
}

/* 2 + 6. Delivery and pickup labels. -------------------------------------- */
add_filter( 'woocommerce_package_rates', function ( $rates ) {
	if ( ! nura_checkout_ux_on() ) {
		return $rates;
	}
	foreach ( (array) $rates as $id => $rate ) {
		if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_label' ) || ! method_exists( $rate, 'set_label' ) ) {
			continue;
		}
		$label = (string) $rate->get_label();
		if ( function_exists( 'nura_is_pickup_method' ) && nura_is_pickup_method( $id ) ) {
			if ( preg_match( '/stanbank|room|floor|,/i', $label ) ) {
				$rate->set_label( __( 'NURA studio pickup', 'nura-beauty' ) );
			}
			continue;
		}
		if ( function_exists( 'nura_is_cbd_rate' ) && nura_is_cbd_rate( $id, $label ) ) {
			if ( false === stripos( $label, 'pay on delivery' ) ) {
				$rate->set_label( __( 'Nairobi CBD delivery - pay on delivery (cash or M-Pesa)', 'nura-beauty' ) );
			}
			continue;
		}
		if ( preg_match( '/rider/i', $label ) && preg_match( '/nairobi/i', $label ) ) {
			$rate->set_label( __( 'Rider delivery - rest of Nairobi, pay before dispatch', 'nura-beauty' ) );
		}
	}
	return $rates;
}, 120 );

/** Block checkout reads pickup location names from settings: keep them short. */
function nura_short_pickup_names( $locs ) {
	if ( ! nura_checkout_ux_on() || is_admin() || ! is_array( $locs ) ) {
		return $locs;
	}
	foreach ( $locs as $k => $loc ) {
		if ( is_array( $loc ) && isset( $loc['name'] ) && preg_match( '/stanbank|room|floor|,/i', (string) $loc['name'] ) ) {
			$locs[ $k ]['name'] = __( 'NURA studio pickup', 'nura-beauty' );
		}
	}
	return $locs;
}
add_filter( 'option_pickup_location_pickup_locations', 'nura_short_pickup_names', 120 );

/* 4 + 5. Checkout script: totals stay visible, cut-off refreshed by Nairobi time. */
add_action( 'wp_footer', function () {
	if ( ! nura_checkout_ux_on() || ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_cart() ) || is_order_received_page() ) {
		return;
	}
	$cut = nura_cutoff_hour();
	$lbl = strtolower( str_replace( ' ', '', function_exists( 'nura_fact' ) ? (string) nura_fact( 'cutoff', '5 PM' ) : '5 PM' ) );
	?>
	<style id="nura-co-ux">
	.wc-block-components-totals-item__value[data-nura-last]:empty::before,
	.wc-block-components-totals-footer-item-tax-value[data-nura-last]:empty::before{content:attr(data-nura-last);opacity:.55}
	html.nura-totals-updating .wc-block-components-totals-item__value .wc-block-components-skeleton__element,
	html.nura-totals-updating .wc-block-components-totals-footer-item-tax-value .wc-block-components-skeleton__element{display:none}
	html.nura-totals-updating .wc-block-components-totals-item__value[data-nura-last]:has(.wc-block-components-skeleton__element)::before{content:attr(data-nura-last);opacity:.55}
	html.nura-totals-updating .wc-block-components-totals-footer-item::after{content:"Updating totals...";display:block;width:100%;font-size:.8em;color:#7a5a12;margin-top:.3em}
	</style>
	<script>
	(function () {
		var SEL = '.wc-block-components-totals-item__value, .wc-block-components-totals-footer-item-tax-value';
		function scan() {
			var busy = false;
			document.querySelectorAll(SEL).forEach(function (el) {
				var t = (el.textContent || '').trim();
				var skel = el.querySelector('.wc-block-components-skeleton__element');
				if (t && !skel && /\d/.test(t)) { if (el.getAttribute('data-nura-last') !== t) { el.setAttribute('data-nura-last', t); } }
				else if (el.getAttribute('data-nura-last')) { busy = true; }
			});
			document.documentElement.classList.toggle('nura-totals-updating', busy);
		}
		var t;
		new MutationObserver(function () { clearTimeout(t); t = setTimeout(scan, 20); }).observe(document.body, { childList: true, subtree: true, characterData: true });
		scan();
		// Cut-off line by Nairobi time (cached pages stay correct).
		function cutoff() {
			try {
				var p = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Nairobi', weekday: 'short', hour: 'numeric', hour12: false }).formatToParts(new Date());
				var wd = '', hr = 0;
				p.forEach(function (x) { if (x.type === 'weekday') { wd = x.value; } if (x.type === 'hour') { hr = parseInt(x.value, 10) % 24; } });
				var dow = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 }[wd] || 1;
				var txt = (dow <= 6 && hr < <?php echo (int) $cut; ?>) ? 'Order before <?php echo esc_js( $lbl ); ?> for delivery today' : (dow <= 5 ? 'Order now for delivery tomorrow' : 'Order now for delivery on Monday');
				document.querySelectorAll('[data-nura-cutoff]').forEach(function (el) { el.textContent = txt; });
			} catch (e) {}
		}
		cutoff();
	})();
	</script>
	<?php
}, 40 );
