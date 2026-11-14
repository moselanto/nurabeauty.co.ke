<?php
/**
 * R8 (theme 1.153.0): answer-engine coverage for searches that already show NURA but had no direct answer page,
 * and clearer facts for AI assistants in /llms.txt.
 * - /water-curl-wig-price-kenya/ (live prices from the water curl products in the shop)
 * - /types-of-wigs-and-prices-kenya/
 * Both reuse the R4 answer-page renderer (short answer, sections, stylist picks, FAQ + FAQPage schema, llms listing).
 * - /llms.txt: every studio service with its price, early appointments and the Google review link.
 * Off: add_filter( 'nura_r8_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r8_on() {
	return (bool) apply_filters( 'nura_r8_on', true );
}

/** Live water curl prices (cached 6 h). */
function nura_r8_water_curl() {
	$c = get_transient( 'nura_r8_wc' );
	if ( is_array( $c ) ) {
		return $c;
	}
	$c = array( 'min' => 0, 'max' => 0, 'n' => 0 );
	if ( function_exists( 'wc_get_products' ) ) {
		foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => 40, 's' => 'water curl' ) ) as $p ) {
			$pr = (float) $p->get_price();
			if ( $pr <= 0 || false === stripos( $p->get_name(), 'water' ) ) {
				continue;
			}
			$c['n']++;
			$c['min'] = $c['min'] ? min( $c['min'], $pr ) : $pr;
			$c['max'] = max( $c['max'], $pr );
		}
	}
	set_transient( 'nura_r8_wc', $c, 6 * HOUR_IN_SECONDS );
	return $c;
}
add_action( 'save_post_product', function () {
	delete_transient( 'nura_r8_wc' );
} );

function nura_r8_ksh( $n ) {
	return 'KSh ' . number_format( (float) $n );
}

add_filter( 'nura_r4_pages', function ( $pages ) {
	if ( ! nura_r8_on() ) {
		return $pages;
	}
	$a  = function ( $path, $label ) {
		return '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
	};
	$wc = nura_r8_water_curl();
	$wr = $wc['n'] ? ( $wc['min'] === $wc['max'] ? nura_r8_ksh( $wc['min'] ) : nura_r8_ksh( $wc['min'] ) . ' to ' . nura_r8_ksh( $wc['max'] ) ) : 'shown on each product';
	$inst = 'KSh 1,000';

	$pages['water-curl-wig-price-kenya'] = array(
		'Water Curl Wig Price in Kenya',
		'Water Curl Wig Price in Kenya (Live Prices) | NURA Beauty',
		'Water curl wig prices in Kenya at NURA Beauty: ' . $wr . ', honest hair-type labels, installation from ' . $inst . ', same-day Nairobi delivery and M-Pesa.',
		'water curl wig price in kenya',
		'en',
		'At NURA Beauty, water curl wigs cost ' . $wr . ' right now (live shop prices). Every unit is labelled Pure Human, Semi Human or luxury fibre, installation at the studio is ' . $inst . ', and Nairobi orders paid before {cutoff} arrive the same day.',
		array(
			'Water curl wig prices at NURA' => '<p>Current price range: <strong>' . esc_html( $wr ) . '</strong>. The price rises with length, lace size (headband, closure or 13x4 frontal) and hair type. See every unit in ' . $a( '/shop/?s=water+curl&post_type=product', 'water curl wigs' ) . ' or the full ' . $a( '/wig-prices-kenya/', 'wig price list' ) . '.</p>',
			'What a water curl wig is' => '<p>Water curl is a tight, defined, wet-look curl pattern. It looks full and bouncy, holds its curl after washing and suits both short pixie cuts and longer units.</p>',
			'How to choose' => '<ul><li><strong>Headband:</strong> the easiest, no glue, on and off in minutes.</li><li><strong>13x4 frontal:</strong> the most natural hairline and parting.</li><li><strong>Pure Human vs Semi Human vs fibre:</strong> human hair takes heat and lasts longest; see ' . $a( '/human-hair-vs-semi-human-vs-fibre-wigs-kenya/', 'the comparison' ) . '.</li></ul>',
			'Care so the curls last' => '<p>Wash every 2 to 3 weeks, finger-detangle, use a light leave-in or mousse, and air dry. Avoid brushing dry curls. Our studio revamp (wash and restyle) is KSh 1,500.</p>',
			'Buy, install and delivery' => '<p><strong>Studio:</strong> {address}, {hours}. <strong>Online:</strong> M-Pesa or card at checkout, or WhatsApp and Till {till}. Nairobi same day before {cutoff}; countrywide by shuttle or courier. ' . $a( '/book-appointment/', 'Book installation' ) . '.</p>',
		),
		array(
			array( 'How much is a water curl wig in Kenya?', 'At NURA Beauty, water curl wigs currently cost ' . $wr . ', depending on length, lace and hair type.' ),
			array( 'Is a water curl wig human hair?', 'It can be either. At NURA every unit states its hair type (Pure Human, Semi Human or luxury fibre) on the product page.' ),
			array( 'Where can I buy a water curl wig in Nairobi?', 'At NURA Beauty, {address}, or online at nurabeauty.co.ke with same-day Nairobi delivery before {cutoff}.' ),
			array( 'How much is water curl wig installation?', 'Installation at the NURA studio is KSh 1,000 (mid part or side part).' ),
		),
		'water curl',
	);

	$pages['types-of-wigs-and-prices-kenya'] = array(
		'Types of Wigs and Their Prices in Kenya',
		'Types of Wigs and Their Prices in Kenya (2026 Guide) | NURA Beauty',
		'Every wig type explained with Kenya prices: headband, closure, 13x4 frontal, glueless, bob, pixie; Pure Human vs Semi Human vs fibre. Shop and install at NURA Beauty, Nairobi.',
		'types of wigs and their prices',
		'en',
		'Wigs differ by cap (headband, closure, 13x4 frontal, glueless) and by hair (Pure Human, Semi Human, luxury fibre). At NURA Beauty, Pure Human wigs run from {human_min} to {human_max}, Semi Human from {blend_min} and fibre from {fibre_min}; installation is KSh 1,000.',
		array(
			'By hair type' => '<ul><li><strong>Pure Human:</strong> real human hair, takes heat and colour, lasts longest. {human_min} to {human_max}.</li><li><strong>Semi Human (blend):</strong> human hair mixed with fibre; softer and cheaper. From {blend_min}.</li><li><strong>Luxury fibre:</strong> heat-resistant fibre, pre-styled, lowest price. From {fibre_min}.</li></ul><p>More: ' . $a( '/human-hair-vs-semi-human-vs-fibre-wigs-kenya/', 'human hair vs semi human vs fibre' ) . '.</p>',
			'By cap and lace' => '<ul><li><strong>Headband wig:</strong> no glue, no lace; fastest to wear.</li><li><strong>Closure wig (4x4, 5x5):</strong> lace at the parting only; easy and natural.</li><li><strong>13x4 frontal:</strong> lace ear to ear for the most natural hairline and free parting.</li><li><strong>Glueless:</strong> combs and band, no adhesive.</li></ul><p>More: ' . $a( '/frontal-vs-closure-wig/', 'frontal vs closure' ) . ' and ' . $a( '/closure-sizes-2x2-4x4-5x5-vs-13x4-frontal/', 'closure sizes' ) . '.</p>',
			'By style' => '<p>Bob, pixie, straight (bone straight), body wave, water curl, deep wave, kinky curl, jerry curl and coloured units (blonde, burgundy, ombre). See ' . $a( '/body-wave-vs-water-wave-vs-deep-wave/', 'curl patterns compared' ) . ' and ' . $a( '/wig-lengths-and-textures-explained/', 'lengths and textures' ) . '.</p>',
			'Studio services and prices' => '[nura_service_prices]',
			'Buy and install' => '<p><strong>Studio:</strong> {address}, {hours}; early appointments from 6:00am by booking. <strong>Online:</strong> M-Pesa or card, same-day Nairobi delivery before {cutoff}. ' . $a( '/book-appointment/', 'Book a fitting' ) . '.</p>',
		),
		array(
			array( 'What are the main types of wigs?', 'By cap: headband, closure, 13x4 frontal and glueless. By hair: Pure Human, Semi Human and luxury fibre.' ),
			array( 'How much do wigs cost in Kenya?', 'At NURA Beauty, Pure Human wigs cost {human_min} to {human_max}, Semi Human from {blend_min} and fibre from {fibre_min}.' ),
			array( 'Which wig type is best for beginners?', 'A headband or glueless closure wig: no glue, quick to wear and easy to remove.' ),
			array( 'Which wig looks most natural?', 'A 13x4 frontal in Pure Human hair gives the most natural hairline and free parting.' ),
		),
		'',
	);
	return $pages;
}, 20 );

/* The R4 renderer outputs section bodies through wp_kses_post: run the price-table shortcode inside them. */
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	return 'nura_r4' === $tag ? do_shortcode( $out ) : $out;
}, 10, 2 );

/* One-time creation of the new pages (never overwrites an existing slug). */
add_action( 'admin_init', function () {
	if ( ! nura_r8_on() || ! current_user_can( 'manage_options' ) || '1.153.0' === get_option( 'nura_r8_pages' ) || ! function_exists( 'nura_r4_pages' ) ) {
		return;
	}
	$all = nura_r4_pages();
	foreach ( array( 'water-curl-wig-price-kenya', 'types-of-wigs-and-prices-kenya' ) as $slug ) {
		if ( empty( $all[ $slug ] ) || get_page_by_path( $slug ) ) {
			continue;
		}
		$p  = $all[ $slug ];
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $p[0], 'post_status' => 'publish', 'post_content' => '[nura_r4 page="' . $slug . '"]' ), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', $p[1] );
			update_post_meta( $id, 'rank_math_description', $p[2] );
			update_post_meta( $id, 'rank_math_focus_keyword', $p[3] );
			if ( function_exists( 'nura_indexnow_queue_post' ) ) {
				nura_indexnow_queue_post( $id );
			}
		}
	}
	update_option( 'nura_r8_pages', '1.153.0', false );
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );

/* /llms.txt: services with prices, booking rules and the review link. */
add_filter( 'nura_llms_extra', function ( $lines ) {
	if ( ! nura_r8_on() || ! function_exists( 'nura_services' ) ) {
		return $lines;
	}
	$lines[] = '## Studio services and prices (Kenya Shillings)';
	foreach ( nura_services() as $s ) {
		$lines[] = '- ' . $s[0] . ': ' . ( function_exists( 'nura_service_price_text' ) ? wp_strip_all_tags( nura_service_price_text( $s ) ) : '' ) . '. ' . $s[3];
	}
	$lines[] = '- Booking: ' . home_url( '/book-appointment/' ) . ' . Appointments Monday to Saturday from 6:00am; bookings before 8:00am add KSh 1,000. A 50% deposit (minimum KSh 500) secures the slot; it is non-refundable for no-shows, and late clients wait if another client is being served.';
	$lines[] = '- Google reviews: ' . home_url( '/review/' );
	$lines[] = '';
	return $lines;
}, 5 );
