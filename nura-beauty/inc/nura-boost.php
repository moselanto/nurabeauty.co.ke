<?php
/**
 * NURA boost (v1.60.0): speed, category landing pages, Lipa Mdogo Mdogo,
 * WhatsApp cart reminders, TikTok "See it styled" videos and Give/Get referrals.
 *
 * Each part can be switched off with a filter, e.g.
 * add_filter( 'nura_boost_referrals', '__return_false' );
 * Parts: speed, category_pages, lipa_mdogo, wa_carts, tiktok, referrals.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Feature switch. */
function nura_boost_on( $part ) {
	return (bool) apply_filters( 'nura_boost_' . $part, true );
}

/** "KSh 1,500" */
function nura_boost_ksh( $n ) {
	return 'KSh ' . number_format_i18n( (float) $n, 0 );
}

/* ===========================================================================
 * 1. SPEED
 * ======================================================================== */

/* Lazy-load every image after the first few on the page, and decode off the main thread. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr ) {
	static $seen = 0;
	if ( is_admin() || ! nura_boost_on( 'speed' ) ) {
		return $attr;
	}
	$seen++;
	if ( empty( $attr['decoding'] ) ) {
		$attr['decoding'] = 'async';
	}
	if ( empty( $attr['loading'] ) && empty( $attr['fetchpriority'] ) && $seen > 4 ) {
		$attr['loading'] = 'lazy';
	}
	return $attr;
}, 20 );

/* On phones and tablets, below-the-fold homepage sections are painted only when scrolled near. */
add_action( 'wp_head', function () {
	if ( ! nura_boost_on( 'speed' ) ) {
		return;
	}
	echo '<style id="nura-boost-speed">@media (max-width:1024px){body.home main>section:nth-of-type(n+4),body.home .site-main>section:nth-of-type(n+4),.nura-cat-guide,.nura-rv,footer.site-footer{content-visibility:auto;contain-intrinsic-size:auto 900px}}</style>' . "\n";
}, 30 );

/* ===========================================================================
 * 2. CATEGORY LANDING PAGES
 * ======================================================================== */

/** Lowest/highest in-stock price in a category (cached). */
function nura_boost_cat_prices( $term ) {
	$key    = 'nura_cat_px_' . (int) $term->term_id;
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$out = array();
	if ( function_exists( 'wc_get_products' ) ) {
		$ids = wc_get_products( array( 'limit' => 300, 'status' => 'publish', 'stock_status' => 'instock', 'category' => array( $term->slug ), 'return' => 'ids' ) );
		$lo  = 0;
		$hi  = 0;
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$a = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) $p->get_price();
			$b = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'max', true ) : $a;
			if ( $a > 0 ) {
				$lo = $lo ? min( $lo, $a ) : $a;
				$hi = max( $hi, $b );
			}
		}
		if ( $lo ) {
			$out = array( 'lo' => $lo, 'hi' => $hi, 'count' => count( $ids ) );
		}
	}
	set_transient( $key, $out, 6 * HOUR_IN_SECONDS );
	return $out;
}
add_action( 'save_post_product', function ( $post_id ) {
	$tids = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $tids ) ) {
		return;
	}
	foreach ( (array) $tids as $tid ) {
		delete_transient( 'nura_cat_px_' . (int) $tid );
	}
} );

/** Hand-written buying guides for the categories Kenyans search for most. */
function nura_boost_cat_guides() {
	return apply_filters( 'nura_cat_guides', array(
		'wigs' => array(
			'title'   => __( 'Wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'NURA is a Nairobi wig shop serving women across Kenya. Every unit is clearly labelled as 100% human hair, human-hair blend (semi-human) or heat-resistant fibre, so you always know what you are paying for. Choose by hair type, lace, length and texture, then pay by M-Pesa or card.', 'nura-beauty' ),
			'tips'    => array(
				__( 'First wig? Headband and closure wigs are the easiest to wear - no glue and very little lace to cut.', 'nura-beauty' ),
				__( 'Want the most natural look? Choose a lace front wig in human hair or a human-hair blend.', 'nura-beauty' ),
				__( 'On a budget? Heat-resistant fibre wigs hold their style out of the box and cost the least.', 'nura-beauty' ),
			),
			'faqs'    => array(
				array( __( 'Where can I buy wigs in Nairobi?', 'nura-beauty' ), __( 'Order online at nurabeauty.co.ke or on WhatsApp, or visit our studio at Imenti House, Moi Avenue, Nairobi CBD by appointment.', 'nura-beauty' ) ),
				array( __( 'How fast is delivery?', 'nura-beauty' ), __( 'Same day in Nairobi on orders placed before 5pm, and 1 to 3 days to the rest of Kenya.', 'nura-beauty' ) ),
			),
			'related' => array( 'human-hair-wigs', 'headband-wigs', 'closure-wigs', 'lace-front-wigs', 'bob-wigs' ),
		),
		'human-hair-wigs' => array(
			'title'   => __( 'Human hair wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( '100% human hair wigs look and move the most naturally. They can be straightened, curled and coloured, and with good care they last far longer than fibre units. Shop human hair wigs online or at our Nairobi CBD studio, with same-day Nairobi delivery.', 'nura-beauty' ),
			'tips'    => array(
				__( 'Longer lengths cost more because they use more hair - a 14-16 inch unit is a great everyday length.', 'nura-beauty' ),
				__( 'Wash every 6-8 weeks with sulfate-free shampoo and always use a heat protectant.', 'nura-beauty' ),
				__( 'Spread the cost with Lipa Mdogo Mdogo on wigs from KSh 7,000.', 'nura-beauty' ),
			),
			'faqs'    => array(
				array( __( 'Can I colour or straighten a human hair wig?', 'nura-beauty' ), __( 'Yes. Human hair can be heat-styled and coloured. For big colour changes, book a revamp at our studio so the lace and hair stay healthy.', 'nura-beauty' ) ),
				array( __( 'How long does a human hair wig last?', 'nura-beauty' ), __( 'With regular washing, conditioning and storage in its satin bag, a human hair wig can be worn for a year or more.', 'nura-beauty' ) ),
			),
			'related' => array( 'jerry-curl-wigs', 'curly-wigs', 'lace-front-wigs', 'human-hair-blend-wigs' ),
		),
		'human-hair-blend-wigs' => array(
			'title'   => __( 'Semi-human (human-hair blend) wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Human-hair blend wigs - often called semi-human - mix human hair with premium fibre. You get softer movement and a more natural shine than fibre, at a lower price than 100% human hair.', 'nura-beauty' ),
			'tips'    => array(
				__( 'Use low to medium heat only, and test on a small section first.', 'nura-beauty' ),
				__( 'A great step up if you are moving from fibre wigs and want a more natural look.', 'nura-beauty' ),
			),
			'faqs'    => array(
				array( __( 'Is semi-human hair the same as human hair?', 'nura-beauty' ), __( 'No. It is a blend of human hair and fibre. Every NURA product name and label states its exact hair type.', 'nura-beauty' ) ),
			),
			'related' => array( 'human-hair-wigs', 'lace-front-wigs', 'closure-wigs' ),
		),
		'headband-wigs' => array(
			'title'   => __( 'Headband wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Headband wigs are the quickest glueless wigs to wear: no glue, no lace to cut, ready in minutes. Perfect for beginners, busy mornings and protecting your natural hair.', 'nura-beauty' ),
			'tips'    => array(
				__( 'Slide it on, adjust the combs and straps, and tie the headband - done.', 'nura-beauty' ),
				__( 'Change the headband colour to match your outfit for a fresh look every day.', 'nura-beauty' ),
			),
			'faqs'    => array(
				array( __( 'Are headband wigs good for beginners?', 'nura-beauty' ), __( 'Yes. They are the easiest wigs to wear because there is no lace to blend and no glue.', 'nura-beauty' ) ),
			),
			'related' => array( 'closure-wigs', 'bob-wigs', 'curly-wigs', 'wigs' ),
		),
		'closure-wigs' => array(
			'title'   => __( '4x4 closure wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Closure wigs have a 4x4 lace piece at the parting, giving a natural-looking part with less lace work than a frontal. A smart, low-maintenance choice for everyday wear.', 'nura-beauty' ),
			'tips'    => array(
				__( 'Wear it glueless with the combs and adjustable strap, or book a NURA install for a flawless finish.', 'nura-beauty' ),
				__( 'Middle or side part? A 4x4 closure gives you a little room to move the parting.', 'nura-beauty' ),
			),
			'faqs'    => array(
				array( __( 'Closure or lace front - which should I choose?', 'nura-beauty' ), __( 'A closure is easier and cheaper. A lace front gives a more natural hairline from ear to ear and more styling freedom.', 'nura-beauty' ) ),
			),
			'related' => array( 'lace-front-wigs', 'headband-wigs', 'bob-wigs' ),
		),
		'lace-front-wigs' => array(
			'title'   => __( 'Lace front wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Lace front (frontal) wigs give the most natural hairline from ear to ear, so you can wear your hair back, to the side or in a sleek middle part. Book a NURA installation in Nairobi CBD for a melted, photograph-ready finish.', 'nura-beauty' ),
			'tips'    => array(
				__( 'Cut the lace a little at a time and follow your natural hairline.', 'nura-beauty' ),
				__( 'Not confident installing? Our stylists install and style in the studio.', 'nura-beauty' ),
			),
			'faqs'    => array(
				array( __( 'Do you install lace front wigs?', 'nura-beauty' ), __( 'Yes. Book an installation at our Nairobi CBD studio - for your NURA unit or one you already own.', 'nura-beauty' ) ),
			),
			'related' => array( 'human-hair-wigs', 'closure-wigs', 'body-wave-wigs', 'bone-straight-wigs' ),
		),
		'bob-wigs' => array(
			'title'   => __( 'Bob wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Bob wigs are light, cool and easy to manage - ideal for Nairobi weather, the office and first-time wig wearers. Choose sleek straight bobs or curly bobs.', 'nura-beauty' ),
			'tips'    => array( __( 'Short lengths need less care and tangle less - great for everyday wear.', 'nura-beauty' ) ),
			'faqs'    => array(),
			'related' => array( 'pixie-wigs', 'headband-wigs', 'closure-wigs' ),
		),
		'curly-wigs' => array(
			'title'   => __( 'Curly wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'From water curls to deep curls and jerry curls, curly wigs give volume and a soft, natural texture. Refresh curls with a little water and leave-in conditioner.', 'nura-beauty' ),
			'tips'    => array( __( 'Detangle curls with your fingers or a wide-tooth comb, never a brush.', 'nura-beauty' ) ),
			'faqs'    => array(),
			'related' => array( 'jerry-curl-wigs', 'human-hair-wigs', 'headband-wigs' ),
		),
		'jerry-curl-wigs' => array(
			'title'   => __( 'Jerry curl wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Jerry curl wigs have tight, defined curls with lots of body. Our human hair jerry curls come in lengths up to 22 inches.', 'nura-beauty' ),
			'tips'    => array( __( 'Curly textures look shorter once styled - lengths are measured straightened.', 'nura-beauty' ) ),
			'faqs'    => array(),
			'related' => array( 'curly-wigs', 'human-hair-wigs', 'lace-front-wigs' ),
		),
		'body-wave-wigs' => array(
			'title'   => __( 'Body wave wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Body wave wigs have soft, glamorous S-waves that suit every face shape - from the office to weddings.', 'nura-beauty' ),
			'tips'    => array( __( 'Scrunch with a light mousse to bring the waves back between washes.', 'nura-beauty' ) ),
			'faqs'    => array(),
			'related' => array( 'lace-front-wigs', 'bone-straight-wigs', 'human-hair-wigs' ),
		),
		'bone-straight-wigs' => array(
			'title'   => __( 'Bone straight wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Bone straight wigs are sleek, glossy and dead straight - a polished look that photographs beautifully.', 'nura-beauty' ),
			'tips'    => array( __( 'A light serum keeps the hair glossy and stops frizz.', 'nura-beauty' ) ),
			'faqs'    => array(),
			'related' => array( 'lace-front-wigs', 'body-wave-wigs', 'closure-wigs' ),
		),
		'pixie-wigs' => array(
			'title'   => __( 'Pixie wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Pixie wigs give a bold, short, low-maintenance look that is ready to wear in minutes.', 'nura-beauty' ),
			'tips'    => array(),
			'faqs'    => array(),
			'related' => array( 'bob-wigs', 'curly-wigs' ),
		),
		'afro-wigs' => array(
			'title'   => __( 'Afro wigs in Kenya', 'nura-beauty' ),
			'intro'   => __( 'Afro and kinky wigs celebrate natural texture - full, soft and protective of your own hair.', 'nura-beauty' ),
			'tips'    => array(),
			'faqs'    => array(),
			'related' => array( 'curly-wigs', 'headband-wigs' ),
		),
	) );
}

/** Guide for the current category, with price facts and shared FAQs. */
function nura_boost_current_guide() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo ? $memo : null;
	}
	$memo = false;
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return null;
	}
	$term   = get_queried_object();
	$guides = nura_boost_cat_guides();
	if ( ! $term || empty( $term->slug ) || empty( $guides[ $term->slug ] ) ) {
		return null;
	}
	$g         = $guides[ $term->slug ];
	$g['term'] = $term;
	$px        = nura_boost_cat_prices( $term );
	if ( $px ) {
		$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term->name ) : strtolower( $term->name );
		array_unshift( $g['faqs'], array(
			/* translators: %s: category name. */
			sprintf( __( 'How much do %s cost?', 'nura-beauty' ), $name ),
			/* translators: 1: category, 2: low price, 3: high price. */
			sprintf( __( 'At NURA, %1$s currently cost from %2$s to %3$s. Prices are in Kenya Shillings and you can pay by M-Pesa or card.', 'nura-beauty' ), $name, nura_boost_ksh( $px['lo'] ), nura_boost_ksh( $px['hi'] ) ),
		) );
	}
	$g['faqs'][] = array( __( 'Can I return a wig?', 'nura-beauty' ), __( 'Unworn, unaltered wigs in original condition can be returned within 7 days of delivery, either at our Imenti House studio in Nairobi CBD or by courier. Worn, cut or installed wigs cannot be returned for hygiene reasons.', 'nura-beauty' ) );
	$memo        = $g;
	return $g;
}

add_action( 'woocommerce_after_shop_loop', function () {
	if ( ! nura_boost_on( 'category_pages' ) ) {
		return;
	}
	$g = nura_boost_current_guide();
	if ( ! $g ) {
		return;
	}
	echo '<section class="nura-cat-guide" aria-labelledby="nura-cat-guide-h">';
	echo '<div class="nura-cat-guide__main"><p class="nura-eyebrow">' . esc_html__( 'Buying guide', 'nura-beauty' ) . '</p>';
	echo '<h2 id="nura-cat-guide-h">' . esc_html( $g['title'] ) . '</h2>';
	echo '<p class="nura-cat-guide__intro">' . esc_html( $g['intro'] ) . '</p>';
	if ( ! empty( $g['tips'] ) ) {
		echo '<ul class="nura-cat-guide__tips">';
		foreach ( $g['tips'] as $tip ) {
			echo '<li>' . esc_html( $tip ) . '</li>';
		}
		echo '</ul>';
	}
	echo '<div class="nura-cat-guide__faq"><h3>' . esc_html__( 'Questions women ask us', 'nura-beauty' ) . '</h3>';
	foreach ( $g['faqs'] as $f ) {
		echo '<details><summary>' . esc_html( $f[0] ) . '</summary><p>' . esc_html( $f[1] ) . '</p></details>';
	}
	echo '</div></div>';
	$links = array();
	foreach ( (array) $g['related'] as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $t && ! is_wp_error( $t ) && $t->count > 0 ) {
			$l = get_term_link( $t );
			if ( ! is_wp_error( $l ) ) {
				$links[] = '<li><a href="' . esc_url( $l ) . '">' . esc_html( $t->name ) . ' <span>(' . absint( $t->count ) . ')</span></a></li>';
			}
		}
	}
	echo '<aside class="nura-cat-guide__side">';
	if ( $links ) {
		echo '<h3>' . esc_html__( 'Explore more styles', 'nura-beauty' ) . '</h3><ul class="nura-cat-guide__links">' . implode( '', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	$wa = function_exists( 'nura_opt' ) ? nura_opt( 'nura_whatsapp' ) : '';
	echo '<div class="nura-cat-guide__help"><strong>' . esc_html__( 'Not sure which one?', 'nura-beauty' ) . '</strong><span>' . esc_html__( 'Send us a photo and we will match you in minutes.', 'nura-beauty' ) . '</span>';
	if ( $wa ) {
		echo '<a class="nura-btn nura-btn--gold" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ask a stylist on WhatsApp', 'nura-beauty' ) . '</a>';
	}
	echo '<a class="nura-cat-guide__quiz" href="' . esc_url( home_url( '/ai-wig-finder/' ) ) . '">' . esc_html__( 'Or take the quick Wig Finder', 'nura-beauty' ) . ' &#8594;</a></div>';
	echo '</aside></section>';

	$items = array();
	foreach ( $g['faqs'] as $f ) {
		$items[] = array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ), JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
}, 40 );

/* Search titles and descriptions for guided categories (only when none was set by hand in Rank Math). */
add_filter( 'rank_math/frontend/title', function ( $title ) {
	$g = nura_boost_on( 'category_pages' ) ? nura_boost_current_guide() : null;
	if ( ! $g || ( function_exists( 'nura_seo_has_custom_meta' ) && nura_seo_has_custom_meta( 'title' ) ) ) {
		return $title;
	}
	$px = nura_boost_cat_prices( $g['term'] );
	if ( $px ) {
		/* translators: 1: guide title, 2: low price. */
		return sprintf( __( '%1$s from %2$s | Same-Day Nairobi Delivery | NURA', 'nura-beauty' ), $g['title'], nura_boost_ksh( $px['lo'] ) );
	}
	/* translators: %s: guide title. */
	return sprintf( __( '%s | Same-Day Nairobi Delivery | NURA', 'nura-beauty' ), $g['title'] );
}, 30 );
add_filter( 'rank_math/frontend/description', function ( $desc ) {
	$g = nura_boost_on( 'category_pages' ) ? nura_boost_current_guide() : null;
	if ( ! $g || ( function_exists( 'nura_seo_has_custom_meta' ) && nura_seo_has_custom_meta( 'description' ) ) ) {
		return $desc;
	}
	$px = nura_boost_cat_prices( $g['term'] );
	$d  = $g['intro'];
	if ( $px ) {
		/* translators: 1: count, 2: low, 3: high. */
		$d = sprintf( __( 'Shop %1$d styles from %2$s to %3$s.', 'nura-beauty' ), $px['count'], nura_boost_ksh( $px['lo'] ), nura_boost_ksh( $px['hi'] ) ) . ' ' . $d;
	}
	return wp_trim_words( $d, 30, '...' );
}, 30 );

/* ===========================================================================
 * 3. LIPA MDOGO MDOGO (pay in instalments, arranged on WhatsApp)
 * ======================================================================== */

/** Plan settings: minimum price, deposit %, week options. */
function nura_lmm_settings() {
	return apply_filters( 'nura_lmm_settings', array( 'min' => 7000, 'deposit' => 50, 'weeks' => array( 1, 2 ) ) );
}

/** Lowest display price of a product. */
function nura_lmm_price( $product ) {
	return $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $product );
}

add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! nura_boost_on( 'lipa_mdogo' ) || ! $product instanceof WC_Product || ! $product->is_in_stock() ) {
		return;
	}
	$s     = nura_lmm_settings();
	$price = nura_lmm_price( $product );
	$wa    = function_exists( 'nura_opt' ) ? (string) nura_opt( 'nura_whatsapp' ) : '';
	if ( $price < $s['min'] || '' === $wa ) {
		return;
	}
	$weeks = array_map( 'absint', (array) $s['weeks'] );
	$max   = max( $weeks );
	$dep   = ceil( $price * $s['deposit'] / 100 );
	$wk    = ceil( ( $price - $dep ) / $max );
	echo '<div class="nura-lmm" data-nura-lmm data-price="' . esc_attr( $price ) . '" data-deposit="' . esc_attr( (int) $s['deposit'] ) . '" data-wa="' . esc_url( $wa ) . '" data-name="' . esc_attr( $product->get_name() ) . '" data-url="' . esc_url( get_permalink( $product->get_id() ) ) . '" data-img="' . esc_url( (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ) . '" data-sku="' . esc_attr( (string) $product->get_sku() ) . '" data-pid="' . esc_attr( $product->get_id() ) . '">';
	echo '<div class="nura-lmm__head"><span class="nura-lmm__badge">' . esc_html__( 'Lipa Mdogo Mdogo', 'nura-beauty' ) . '</span>';
	/* translators: 1: deposit, 2: weekly amount, 3: weeks. */
	echo '<p class="nura-lmm__lead" data-nura-lmm-lead>' . esc_html( sprintf( __( 'Pay a 50%% deposit of %1$s today, then the balance of %2$s within %3$s.', 'nura-beauty' ), nura_boost_ksh( $dep ), nura_boost_ksh( $price - $dep ), 1 === $max ? __( '1 week', 'nura-beauty' ) : sprintf( __( '%d weeks', 'nura-beauty' ), $max ) ) ) . '</p></div>';
	echo '<div class="nura-lmm__weeks" role="radiogroup" aria-label="' . esc_attr__( 'Pay over', 'nura-beauty' ) . '">';
	foreach ( $weeks as $w ) {
		/* translators: %d: weeks. */
		echo '<button type="button" class="nura-lmm__wk' . ( $w === $max ? ' is-on' : '' ) . '" data-weeks="' . esc_attr( $w ) . '" role="radio" aria-checked="' . ( $w === $max ? 'true' : 'false' ) . '">' . esc_html( 1 === $w ? __( '1 week', 'nura-beauty' ) : sprintf( __( '%d weeks', 'nura-beauty' ), $w ) ) . '</button>';
	}
	echo '</div>';
	echo '<ol class="nura-lmm__steps"><li>' . esc_html__( 'Pay a 50% deposit by M-Pesa to reserve your wig.', 'nura-beauty' ) . '</li><li>' . esc_html__( 'Clear the balance within 2 weeks - any amount, any time, no interest.', 'nura-beauty' ) . '</li><li>' . esc_html__( 'Collect or get it delivered once fully paid. Imported units can take up to 7 days to arrive.', 'nura-beauty' ) . '</li></ol>';
	echo '<a class="nura-lmm__cta" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener" data-nura-lmm-cta>' . esc_html__( 'Start my plan on WhatsApp', 'nura-beauty' ) . '</a>';
	echo '<p class="nura-lmm__note">' . esc_html__( 'No interest, no extra fees. The deposit is non-refundable if you cancel.', 'nura-beauty' ) . ' <a href="' . esc_url( home_url( '/lipa-mdogo-mdogo-terms/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Read the full terms', 'nura-beauty' ) . '</a></p>';
	echo '</div>';
}, 33 );

/* "or KSh X/week" under prices of eligible products in grids. */
add_action( 'woocommerce_after_shop_loop_item_title', function () {
	global $product;
	if ( ! nura_boost_on( 'lipa_mdogo' ) || ! $product instanceof WC_Product ) {
		return;
	}
	$s     = nura_lmm_settings();
	$price = nura_lmm_price( $product );
	if ( $price < $s['min'] ) {
		return;
	}
	$dep = ceil( $price * $s['deposit'] / 100 );
	$wk  = ceil( ( $price - $dep ) / max( array_map( 'absint', (array) $s['weeks'] ) ) );
	/* translators: %s: weekly amount. */
	echo '<span class="nura-lmm-chip">' . esc_html( sprintf( __( 'or %s/week with Lipa Mdogo Mdogo', 'nura-beauty' ), nura_boost_ksh( $wk ) ) ) . '</span>';
}, 11 );

/* ===========================================================================
 * 4. WHATSAPP CART REMINDERS (one tap, from your own WhatsApp)
 *    Fully automatic sending already exists in the NURA Experience plugin once
 *    the WhatsApp Cloud API is connected. This screen works today without it.
 * ======================================================================== */
add_action( 'admin_menu', function () {
	if ( ! nura_boost_on( 'wa_carts' ) ) {
		return;
	}
	add_submenu_page( 'woocommerce', __( 'WhatsApp cart reminders', 'nura-beauty' ), __( 'WhatsApp carts', 'nura-beauty' ), 'manage_woocommerce', 'nura-wa-carts', 'nura_boost_wa_carts_screen' );
}, 61 );

/** Kenyan number to international digits (07.. / 01.. -> 2547.. / 2541..). */
function nura_boost_ke_msisdn( $phone ) {
	$d = preg_replace( '/[^0-9]/', '', (string) $phone );
	if ( 10 === strlen( $d ) && '0' === $d[0] ) {
		$d = '254' . substr( $d, 1 );
	} elseif ( 9 === strlen( $d ) && in_array( $d[0], array( '7', '1' ), true ) ) {
		$d = '254' . $d;
	}
	return strlen( $d ) >= 11 ? $d : '';
}

function nura_boost_wa_carts_screen() {
	global $wpdb;
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$table = $wpdb->prefix . 'nurax_abandoned_carts';
	echo '<div class="wrap"><h1>' . esc_html__( 'WhatsApp cart reminders', 'nura-beauty' ) . '</h1>';
	echo '<p>' . esc_html__( 'Shoppers from the last 14 days who left items in their cart and gave a phone number. Tap "Send on WhatsApp" to open a ready-written reminder with their personal cart link in your WhatsApp, then "Mark sent". Completed carts are hidden automatically.', 'nura-beauty' ) . '</p>';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $exists !== $table ) {
		echo '<p>' . esc_html__( 'Cart capture is not active yet. Turn on cart recovery in the NURA Experience plugin settings.', 'nura-beauty' ) . '</p></div>';
		return;
	}
	if ( isset( $_GET['nura_done'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'nura_wa_cart_done' ) ) {
		$done_ids   = (array) get_option( 'nura_wa_carts_done', array() );
		$done_ids[] = absint( wp_unslash( $_GET['nura_done'] ) );
		update_option( 'nura_wa_carts_done', array_slice( array_unique( array_map( 'absint', $done_ids ) ), -500 ), false );
	}
	$done_ids = array_map( 'absint', (array) get_option( 'nura_wa_carts_done', array() ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, phone, subtotal, token, created_at FROM {$table} WHERE status IN ('pending','recovered') AND phone <> '' AND created_at >= %s ORDER BY created_at DESC LIMIT 100", gmdate( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS ) ) );
	$shown = 0;
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Shopper', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Phone', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Cart value', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Left', 'nura-beauty' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( (array) $rows as $r ) {
		if ( in_array( (int) $r->id, $done_ids, true ) ) {
			continue;
		}
		$msisdn = nura_boost_ke_msisdn( $r->phone );
		if ( ! $msisdn ) {
			continue;
		}
		$first   = $r->name ? strtok( (string) $r->name, ' ' ) : '';
		$recover = add_query_arg( 'nura_recover', rawurlencode( (string) $r->token ), home_url( '/' ) );
		/* translators: 1: first name with leading space, 2: cart link. */
		$msg  = sprintf( __( 'Hi%1$s, this is NURA Beauty. Your wig is still waiting in your cart - pick up where you left off here: %2$s . Same-day delivery in Nairobi if you order before 5pm. Questions about fit or colour? Just reply here.', 'nura-beauty' ), $first ? ' ' . $first : '', $recover );
		$link = 'https://wa.me/' . $msisdn . '?text=' . rawurlencode( $msg );
		$done = wp_nonce_url( add_query_arg( array( 'page' => 'nura-wa-carts', 'nura_done' => (int) $r->id ), admin_url( 'admin.php' ) ), 'nura_wa_cart_done' );
		$when = $r->created_at ? human_time_diff( strtotime( $r->created_at . ' UTC' ) ) : '-';
		echo '<tr><td>' . esc_html( $r->name ? $r->name : '-' ) . '</td><td>+' . esc_html( $msisdn ) . '</td><td>' . esc_html( nura_boost_ksh( $r->subtotal ) ) . '</td><td>' . esc_html( $when ) . '</td>';
		echo '<td><a class="button button-primary" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'Send on WhatsApp', 'nura-beauty' ) . '</a> <a class="button" href="' . esc_url( $done ) . '">' . esc_html__( 'Mark sent', 'nura-beauty' ) . '</a></td></tr>';
		$shown++;
	}
	if ( ! $shown ) {
		echo '<tr><td colspan="5">' . esc_html__( 'No carts waiting for a WhatsApp reminder right now.', 'nura-beauty' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* ===========================================================================
 * 5. TIKTOK "SEE IT STYLED" VIDEOS
 * ======================================================================== */

/** Extract a TikTok video ID from a URL. */
function nura_boost_tiktok_id( $url ) {
	return preg_match( '#tiktok\.com/(?:@[\w.\-]+/video|v|embed(?:/v2)?)/(\d{8,25})#', (string) $url, $m ) ? $m[1] : '';
}

/* Product edit box. */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'nura-tiktok', __( 'TikTok videos (See it styled)', 'nura-beauty' ), function ( $post ) {
		wp_nonce_field( 'nura_tiktok_save', 'nura_tiktok_nonce' );
		$v = (string) get_post_meta( $post->ID, '_nura_tiktok_urls', true );
		echo '<p>' . esc_html__( 'Paste one TikTok video link per line (TikTok > Share > Copy link), e.g. https://www.tiktok.com/@nurabeautyke/video/123...', 'nura-beauty' ) . '</p>';
		echo '<textarea name="nura_tiktok_urls" rows="4" style="width:100%">' . esc_textarea( $v ) . '</textarea>';
	}, 'product', 'side', 'default' );
} );
add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['nura_tiktok_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nura_tiktok_nonce'] ) ), 'nura_tiktok_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw  = isset( $_POST['nura_tiktok_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nura_tiktok_urls'] ) ) : '';
	$keep = array();
	foreach ( preg_split( '/\s+/', $raw ) as $u ) {
		$u = esc_url_raw( trim( $u ) );
		if ( $u && nura_boost_tiktok_id( $u ) ) {
			$keep[] = $u;
		}
	}
	$keep = array_slice( array_values( array_unique( $keep ) ), 0, 6 );
	update_post_meta( $post_id, '_nura_tiktok_urls', implode( "\n", $keep ) );
	// Remember when each video was first added (used as the video date for Google).
	$dates = get_post_meta( $post_id, '_nura_tiktok_dates', true );
	$dates = is_array( $dates ) ? $dates : array();
	foreach ( $keep as $u ) {
		$id = nura_boost_tiktok_id( $u );
		if ( empty( $dates[ $id ] ) ) {
			$dates[ $id ] = gmdate( 'c' );
		}
	}
	update_post_meta( $post_id, '_nura_tiktok_dates', $dates );
	delete_transient( 'nura_tt_' . $post_id );
} );

/** Videos for a product with title + thumbnail from TikTok oEmbed (cached 1 day). */
function nura_boost_product_videos( $post_id ) {
	$raw = trim( (string) get_post_meta( $post_id, '_nura_tiktok_urls', true ) );
	if ( '' === $raw ) {
		return array();
	}
	$cached = get_transient( 'nura_tt_' . $post_id );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$dates = get_post_meta( $post_id, '_nura_tiktok_dates', true );
	$dates = is_array( $dates ) ? $dates : array();
	$out   = array();
	foreach ( array_filter( explode( "\n", $raw ) ) as $url ) {
		$id = nura_boost_tiktok_id( $url );
		if ( ! $id ) {
			continue;
		}
		$v   = array( 'id' => $id, 'url' => $url, 'title' => '', 'thumb' => '', 'date' => isset( $dates[ $id ] ) ? $dates[ $id ] : '' );
		$res = wp_safe_remote_get( 'https://www.tiktok.com/oembed?url=' . rawurlencode( $url ), array( 'timeout' => 6 ) );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$j = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( is_array( $j ) ) {
				$v['title'] = isset( $j['title'] ) ? sanitize_text_field( $j['title'] ) : '';
				$v['thumb'] = isset( $j['thumbnail_url'] ) ? esc_url_raw( $j['thumbnail_url'] ) : '';
			}
		}
		$out[] = $v;
	}
	set_transient( 'nura_tt_' . $post_id, $out, DAY_IN_SECONDS );
	return $out;
}

/* Lightweight video cards: the TikTok player loads only when tapped. */
add_action( 'woocommerce_after_single_product_summary', function () {
	global $product;
	if ( ! nura_boost_on( 'tiktok' ) || ! $product instanceof WC_Product ) {
		return;
	}
	$videos = nura_boost_product_videos( $product->get_id() );
	if ( ! $videos ) {
		return;
	}
	echo '<section class="nura-tt" aria-labelledby="nura-tt-h"><div class="nura-tt__head"><p class="nura-eyebrow">@nurabeautyke</p><h2 id="nura-tt-h">' . esc_html__( 'See it styled', 'nura-beauty' ) . '</h2></div><div class="nura-tt__grid">';
	foreach ( $videos as $v ) {
		$label = $v['title'] ? $v['title'] : $product->get_name();
		/* translators: %s: video title. */
		echo '<button type="button" class="nura-tt__card" data-nura-tt="' . esc_attr( $v['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Play video: %s', 'nura-beauty' ), $label ) ) . '">';
		if ( $v['thumb'] ) {
			echo '<img src="' . esc_url( $v['thumb'] ) . '" alt="" loading="lazy" decoding="async" width="300" height="533">';
		}
		echo '<span class="nura-tt__play" aria-hidden="true"></span><span class="nura-tt__cap">' . esc_html( wp_trim_words( $label, 10, '...' ) ) . '</span></button>';
	}
	echo '</div></section>';
}, 15 );

/* VideoObject schema for each video (only with a thumbnail and date, as Google requires). */
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! nura_boost_on( 'tiktok' ) || ! function_exists( 'is_product' ) || ! is_product() || ! is_array( $data ) ) {
		return $data;
	}
	$pid = get_queried_object_id();
	foreach ( nura_boost_product_videos( $pid ) as $i => $v ) {
		if ( ! $v['thumb'] || ! $v['date'] ) {
			continue;
		}
		$data[ 'nura_video_' . $i ] = array(
			'@type'        => 'VideoObject',
			'name'         => $v['title'] ? $v['title'] : get_the_title( $pid ),
			/* translators: %s: product. */
			'description'  => $v['title'] ? $v['title'] : sprintf( __( '%s styled by NURA Beauty, Nairobi.', 'nura-beauty' ), get_the_title( $pid ) ),
			'thumbnailUrl' => $v['thumb'],
			'uploadDate'   => $v['date'],
			'embedUrl'     => 'https://www.tiktok.com/embed/v2/' . $v['id'],
			'url'          => $v['url'],
		);
	}
	return $data;
}, 140 );

/* ===========================================================================
 * 6. GIVE KSh 500, GET KSh 500 REFERRALS
 * ======================================================================== */

/** Referral settings. */
function nura_ref_settings() {
	return apply_filters( 'nura_ref_settings', array( 'amount' => 500, 'min_spend' => 3000, 'expiry_days' => 90 ) );
}

/** The customer's personal referral code (a WooCommerce coupon), created on first use. */
function nura_ref_code_for_user( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id || ! class_exists( 'WC_Coupon' ) || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
		return '';
	}
	$code = (string) get_user_meta( $user_id, '_nura_ref_code', true );
	if ( $code && wc_get_coupon_id_by_code( $code ) ) {
		return $code;
	}
	$u    = get_userdata( $user_id );
	$base = $u ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $u->first_name ? $u->first_name : $u->display_name ) ) ) : '';
	$base = substr( $base ? $base : 'NURA', 0, 8 );
	$s    = nura_ref_settings();
	for ( $i = 0; $i < 5; $i++ ) {
		$try = $base . '-' . strtoupper( wp_generate_password( 4, false, false ) );
		if ( wc_get_coupon_id_by_code( $try ) ) {
			continue;
		}
		$c = new WC_Coupon();
		$c->set_code( $try );
		$c->set_discount_type( 'fixed_cart' );
		$c->set_amount( $s['amount'] );
		$c->set_minimum_amount( $s['min_spend'] );
		$c->set_individual_use( true );
		$c->set_usage_limit_per_user( 1 );
		/* translators: %d: user ID. */
		$c->set_description( sprintf( __( 'NURA referral code of customer #%d (Give 500, Get 500).', 'nura-beauty' ), $user_id ) );
		$c->update_meta_data( '_nura_ref_owner', $user_id );
		$c->save();
		update_user_meta( $user_id, '_nura_ref_code', $try );
		return $try;
	}
	return '';
}

/** Is this coupon a referral code? Returns owner user ID or 0. */
function nura_ref_owner( $code ) {
	$id = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
	return $id ? absint( get_post_meta( $id, '_nura_ref_owner', true ) ) : 0;
}

/* Referral codes are for first orders by new customers only, never your own code. */
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || ! nura_boost_on( 'referrals' ) || ! $coupon instanceof WC_Coupon ) {
		return $valid;
	}
	$owner = absint( $coupon->get_meta( '_nura_ref_owner' ) );
	if ( ! $owner ) {
		return $valid;
	}
	$uid   = get_current_user_id();
	$email = '';
	if ( function_exists( 'WC' ) && WC()->customer ) {
		$email = strtolower( (string) WC()->customer->get_billing_email() );
	}
	$owner_user = get_userdata( $owner );
	if ( $uid === $owner || ( $owner_user && $email && strtolower( $owner_user->user_email ) === $email ) ) {
		throw new Exception( esc_html__( 'You cannot use your own referral code - share it with a friend instead.', 'nura-beauty' ) );
	}
	$has_orders = false;
	if ( $uid ) {
		$has_orders = (int) wc_get_customer_order_count( $uid ) > 0;
	} elseif ( $email && function_exists( 'wc_get_orders' ) ) {
		$has_orders = (bool) wc_get_orders( array( 'billing_email' => $email, 'limit' => 1, 'return' => 'ids', 'status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) );
	}
	if ( $has_orders ) {
		throw new Exception( esc_html__( 'Referral codes are for a first NURA order. Welcome back - ask us about NURA Circle rewards instead.', 'nura-beauty' ) );
	}
	return $valid;
}, 20, 2 );

/* When the friend's order is completed, the referrer gets a reward code by email. */
add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
	if ( ! nura_boost_on( 'referrals' ) || ! class_exists( 'WC_Coupon' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_nura_ref_rewarded' ) ) {
		return;
	}
	foreach ( $order->get_coupon_codes() as $code ) {
		$owner = nura_ref_owner( $code );
		if ( ! $owner ) {
			continue;
		}
		$ou = get_userdata( $owner );
		if ( ! $ou || strtolower( $ou->user_email ) === strtolower( (string) $order->get_billing_email() ) ) {
			continue;
		}
		$s      = nura_ref_settings();
		$reward = 'THANKS-' . strtoupper( wp_generate_password( 6, false, false ) );
		$c      = new WC_Coupon();
		$c->set_code( $reward );
		$c->set_discount_type( 'fixed_cart' );
		$c->set_amount( $s['amount'] );
		$c->set_usage_limit( 1 );
		$c->set_email_restrictions( array( $ou->user_email ) );
		$c->set_date_expires( time() + (int) $s['expiry_days'] * DAY_IN_SECONDS );
		/* translators: %d: order ID. */
		$c->set_description( sprintf( __( 'NURA referral reward for order #%d.', 'nura-beauty' ), $order_id ) );
		$c->save();
		$order->update_meta_data( '_nura_ref_rewarded', $reward );
		$order->save();
		$list   = get_user_meta( $owner, '_nura_ref_rewards', true );
		$list   = is_array( $list ) ? $list : array();
		$list[] = array( 'code' => $reward, 'order' => (int) $order_id, 'time' => time() );
		update_user_meta( $owner, '_nura_ref_rewards', $list );
		/* translators: %s: amount. */
		$subject = sprintf( __( 'You earned %s at NURA - thank you for sharing!', 'nura-beauty' ), nura_boost_ksh( $s['amount'] ) );
		$body    = '<p>' . esc_html__( 'A friend you referred just received her NURA order. As a thank you, here is your reward code:', 'nura-beauty' ) . '</p>';
		$body   .= '<p style="font-size:22px;font-weight:700;letter-spacing:2px">' . esc_html( $reward ) . '</p>';
		/* translators: 1: amount, 2: days. */
		$body   .= '<p>' . esc_html( sprintf( __( 'It takes %1$s off your next order and is valid for %2$d days. Keep sharing your code - there is no limit to how much you can earn.', 'nura-beauty' ), nura_boost_ksh( $s['amount'] ), (int) $s['expiry_days'] ) ) . '</p>';
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$m = WC()->mailer();
			$m->send( $ou->user_email, $subject, $m->wrap_message( __( 'Give 500, Get 500', 'nura-beauty' ), $body ), array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
		break;
	}
} );

/** Share card (My Account dashboard and order thank-you page). */
function nura_ref_share_card( $user_id ) {
	$code = nura_ref_code_for_user( $user_id );
	if ( ! $code ) {
		return;
	}
	$s   = nura_ref_settings();
	$amt = nura_boost_ksh( $s['amount'] );
	/* translators: 1: amount, 2: code, 3: min spend, 4: link. */
	$msg  = sprintf( __( 'I get my wigs from NURA Beauty in Nairobi. Use my code %2$s for %1$s off your first order over %3$s: %4$s', 'nura-beauty' ), $amt, $code, nura_boost_ksh( $s['min_spend'] ), home_url( '/' ) );
	$wa   = 'https://wa.me/?text=' . rawurlencode( $msg );
	$rew  = get_user_meta( $user_id, '_nura_ref_rewards', true );
	$earn = is_array( $rew ) ? count( $rew ) : 0;
	echo '<div class="nura-ref"><p class="nura-eyebrow">' . esc_html__( 'Give 500, Get 500', 'nura-beauty' ) . '</p>';
	/* translators: %s: amount. */
	echo '<h3>' . esc_html( sprintf( __( 'Share NURA. You both get %s.', 'nura-beauty' ), $amt ) ) . '</h3>';
	/* translators: 1: amount, 2: min spend. */
	echo '<p>' . esc_html( sprintf( __( 'Your friend gets %1$s off her first order over %2$s. When it is delivered, you get %1$s off your next one.', 'nura-beauty' ), $amt, nura_boost_ksh( $s['min_spend'] ) ) ) . '</p>';
	echo '<div class="nura-ref__code"><span>' . esc_html__( 'Your code', 'nura-beauty' ) . '</span><strong data-nura-copy>' . esc_html( $code ) . '</strong><button type="button" class="nura-ref__copy" data-nura-copy-btn>' . esc_html__( 'Copy', 'nura-beauty' ) . '</button></div>';
	echo '<a class="nura-ref__wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Share on WhatsApp', 'nura-beauty' ) . '</a>';
	if ( $earn ) {
		/* translators: %d: rewards earned. */
		echo '<p class="nura-ref__earned">' . esc_html( sprintf( _n( 'You have earned %d reward so far. Check your email for the code.', 'You have earned %d rewards so far. Check your email for the codes.', $earn, 'nura-beauty' ), $earn ) ) . '</p>';
	}
	echo '</div>';
}
add_action( 'woocommerce_account_dashboard', function () {
	if ( nura_boost_on( 'referrals' ) ) {
		nura_ref_share_card( get_current_user_id() );
	}
}, 5 );
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	if ( ! nura_boost_on( 'referrals' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( $order && $order->get_customer_id() && (int) $order->get_customer_id() === get_current_user_id() ) {
		nura_ref_share_card( $order->get_customer_id() );
	}
}, 25 );

/* Assets. */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'nura-boost', NURA_URI . 'assets/js/nura-boost.js', array(), NURA_VERSION, true );
	wp_localize_script( 'nura-boost', 'nuraBoost', array(
		/* translators: 1: deposit, 2: weekly, 3: weeks. */
		'lead'   => __( 'Pay a 50% deposit of %1$s today, then the balance of %2$s within %3$s.', 'nura-beauty' ),
		/* translators: 1: product, 2: deposit, 3: weekly, 4: weeks, 5: link. */
		'waMsg'  => __( 'Hi NURA, I would like to buy %1$s on Lipa Mdogo Mdogo: %2$s deposit, then %3$s a week for %4$s weeks. Colour/length: ____. %5$s', 'nura-beauty' ),
		'copied' => __( 'Copied', 'nura-beauty' ),
		'lmmTerms'  => ( get_page_by_path( 'lipa-mdogo-mdogo-terms' ) ? get_permalink( get_page_by_path( 'lipa-mdogo-mdogo-terms' ) ) : home_url( '/lipa-mdogo-mdogo-terms/' ) ),
		'lmmAjax'   => class_exists( 'NURAX_Desk_Plans' ) ? admin_url( 'admin-ajax.php' ) : '',
		'slots'     => class_exists( 'NURAX_Desk_Appts' ) ? rest_url( 'nurax/v1/slots' ) : '',
		'hoursText' => function_exists( 'nura_hours_text' ) ? nura_hours_text() : 'Mon-Sat 8:00-20:00',
	) );
}, 21 );
