<?php
/**
 * Inner pages: classic editorial design system (theme 1.91.0).
 *
 * Adds body class `nura-inner` to content pages, Journal posts, the blog and
 * category archives, and loads assets/css/nura-inner.css there. Never on the
 * homepage, shop, product, cart, checkout or My Account pages, so the
 * buying flow is untouched. Off: add_filter( 'nura_inner_design', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_is_inner() {
	if ( is_admin() || is_front_page() || ! apply_filters( 'nura_inner_design', true ) ) {
		return false;
	}
	if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		return false;
	}
	return is_page() || is_singular( 'post' ) || is_home() || is_category() || is_tag() || is_author() || is_search();
}

add_filter( 'body_class', function ( $classes ) {
	if ( nura_is_inner() ) {
		$classes[] = 'nura-inner';
	}
	return $classes;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( nura_is_inner() ) {
		wp_enqueue_style( 'nura-inner', NURA_URI . 'assets/css/nura-inner.css', array( 'nura-main' ), NURA_VERSION );
	}
}, 40 );

/**
 * 1.92.0: WordPress auto-paragraphs (wpautop) were injecting <br /> and <p> tags
 * inside the generated price list (/wig-prices-kenya/, 361 <br>) and the town
 * delivery pages' product grid (wigs-*), which broke the grid rows: one-word-per-line
 * wig names, two cards plus an orphan, misaligned columns. Clean them out of these
 * components only (never touches Journal prose).
 */
function nura_inner_unautop( $html ) {
	$start = false;
	foreach ( array( '<div class="npg">', '<div class="ncp">' ) as $m ) {
		$p = strpos( $html, $m );
		if ( false !== $p && ( false === $start || $p < $start ) ) {
			$start = $p;
		}
	}
	if ( false === $start ) {
		return $html;
	}
	$head = substr( $html, 0, $start );
	$body = substr( $html, $start );
	$tags = 'a|div|section|span|img|ul|ol|li|article|header|footer|nav|table|thead|tbody|tr|td|th|form|label|input|button|select|option|details|summary|h[1-6]|p|strong|small|svg|path';
	$body = preg_replace( '#(<(?:' . $tags . ')\b[^>]*>|</(?:' . $tags . ')>)\s*<br\s*/?>#i', '$1', $body );
	$body = preg_replace( '#<br\s*/?>\s*(</?(?:' . $tags . ')\b)#i', '$1', $body );
	$body = preg_replace( '#<p>\s*(<(?:a|div|section|span|ul|ol|article|table|nav|form|details|h[1-6])\b)#i', '$1', $body );
	$body = preg_replace( '#(</(?:a|div|section|span|ul|ol|article|table|nav|form|details|h[1-6])>)\s*</p>#i', '$1', $body );
	$body = preg_replace( '#<p>\s*</p>#i', '', $body );
	return $head . $body;
}
add_filter( 'the_content', function ( $content ) {
	return ( is_page() && ( false !== strpos( $content, 'class="npg"' ) || false !== strpos( $content, 'class="ncp"' ) ) ) ? nura_inner_unautop( $content ) : $content;
}, 999 );

/* 1.93.0: homepage polish layer (front page only). Off: add_filter( 'nura_home_design', '__return_false' ). */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_front_page() && apply_filters( 'nura_home_design', true ) ) {
		wp_enqueue_style( 'nura-home', NURA_URI . 'assets/css/nura-home.css', array( 'nura-main' ), NURA_VERSION );
	}
}, 41 );


/* =========================================================================
 * 1.97.0: professional formatting for every content page (body.nura-inner).
 * - Removes the repeated page title at the top of the text (the hero already shows it).
 * - Help pages (delivery, returns, warranty, FAQ, help, privacy, terms, payments,
 *   Lipa Mdogo Mdogo, bulk orders): two-column layout with a sticky "On this page"
 *   contents list, a "last updated" line and a "Still need help?" contact panel.
 * - FAQ / help questions become open-and-close panels.
 * - Lists made only of links become a tidy two-column link list.
 * - Stale FAQ wording (cash on delivery) corrected on the fly.
 * Off: add_filter( 'nura_inner_format', '__return_false' ).
 * ========================================================================= */

function nura_policy_slugs() {
	return (array) apply_filters( 'nura_policy_slugs', array( 'shipping-delivery', 'delivery', 'returns-refunds', 'refund_returns', 'warranty', 'warranty-provenance', 'faq', 'help-support', 'privacy-policy', 'terms-and-conditions', 'terms-conditions', 'payment-information', 'payments', 'lipa-mdogo-mdogo-terms', 'lipa-mdogo-mdogo', 'bulk-corporate-orders', 'cookie-policy' ) );
}

function nura_is_policy_page() {
	if ( ! is_page() ) {
		return false;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	return in_array( $slug, nura_policy_slugs(), true );
}

add_filter( 'body_class', function ( $classes ) {
	if ( nura_is_inner() && nura_is_policy_page() ) {
		$classes[] = 'nura-policy-page';
	}
	return $classes;
} );

function nura_fmt_text( $html ) {
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/** Drop a first H1/H2 that only repeats the page title. */
function nura_fmt_drop_title( $html ) {
	$title = strtolower( nura_fmt_text( get_the_title( get_queried_object_id() ) ) );
	return preg_replace_callback( '/^\s*(?:<div[^>]*>\s*)?<(h[12])[^>]*>(.*?)<\/\1>/is', function ( $m ) use ( $title ) {
		$t = strtolower( nura_fmt_text( $m[2] ) );
		$same = ( $t === $title ) || ( str_replace( array( '&', 'and' ), '', $t ) === str_replace( array( '&', 'and' ), '', $title ) ) || ( 'faq' === $title && false !== strpos( $t, 'frequently asked' ) );
		return $same ? str_replace( $m[0], ( 0 === strpos( ltrim( $m[0] ), '<div' ) ? substr( ltrim( $m[0] ), 0, strpos( ltrim( $m[0] ), '>' ) + 1 ) : '' ), $m[0] ) : $m[0];
	}, $html, 1 );
}

/** Give each H2 an id; return [ html, toc items ]. */
function nura_fmt_ids( $html ) {
	$toc  = array();
	$used = array();
	$html = preg_replace_callback( '/<h2([^>]*)>(.*?)<\/h2>/is', function ( $m ) use ( &$toc, &$used ) {
		$text = nura_fmt_text( $m[2] );
		if ( '' === $text ) {
			return $m[0];
		}
		if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $m[1], $idm ) ) {
			$id = $idm[1];
			$attrs = $m[1];
		} else {
			$id = sanitize_title( $text );
			$id = $id ? $id : 'section';
			$base = $id;
			$n = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . $n++;
			}
			$attrs = $m[1] . ' id="' . esc_attr( $id ) . '"';
		}
		$used[ $id ] = true;
		$toc[]       = array( $id, $text );
		return '<h2' . $attrs . '>' . $m[2] . '</h2>';
	}, $html );
	return array( $html, $toc );
}

/** <h3>Question?</h3> + answer  ->  <details> panel (FAQ and help pages). */
function nura_fmt_faq( $html ) {
	$parts = preg_split( '/(<h[23][^>]*>.*?<\/h[23]>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	$out   = '';
	$count = count( $parts );
	for ( $i = 0; $i < $count; $i++ ) {
		$p = $parts[ $i ];
		if ( preg_match( '/^<h3[^>]*>(.*?)<\/h3>$/is', $p, $m ) && '?' === substr( nura_fmt_text( $m[1] ), -1 ) ) {
			$answer = '';
			if ( $i + 1 < $count && ! preg_match( '/^<h[23]/i', $parts[ $i + 1 ] ) ) {
				$answer = $parts[ ++$i ];
			}
			$out .= '<details class="nura-faq-item"><summary><h3>' . $m[1] . '</h3></summary><div class="nura-faq-item__a">' . $answer . '</div></details>';
			continue;
		}
		$out .= $p;
	}
	return $out;
}

/** Lists whose items are only links -> class nura-linklist. */
function nura_fmt_linklists( $html ) {
	return preg_replace_callback( '/<ul(?![^>]*class=)([^>]*)>(.*?)<\/ul>/is', function ( $m ) {
		if ( ! preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $m[2], $lis ) || count( $lis[1] ) < 3 ) {
			return $m[0];
		}
		foreach ( $lis[1] as $li ) {
			if ( ! preg_match( '/^\s*<a\s[^>]*>.*?<\/a>\s*$/is', $li ) ) {
				return $m[0];
			}
		}
		return '<ul class="nura-linklist"' . $m[1] . '>' . $m[2] . '</ul>';
	}, $html );
}

function nura_fmt_help_panel() {
	$f    = function ( $k, $d = '' ) {
		return function_exists( 'nura_fact' ) ? nura_fact( $k, $d ) : $d;
	};
	$wa   = 'https://wa.me/254714994898';
	$addr = $f( 'address_short', 'Stanbank House, 2nd Floor, Room 20, Moi Avenue, Nairobi CBD' );
	$hrs  = $f( 'hours_text', '' );
	$map  = $f( 'directions_url', $f( 'map_url', '' ) );
	$h  = '<aside class="nura-helpcard" aria-label="' . esc_attr__( 'Contact NURA Beauty', 'nura-beauty' ) . '">';
	$h .= '<p class="nura-helpcard__k">' . esc_html__( 'Still need help?', 'nura-beauty' ) . '</p>';
	$h .= '<p class="nura-helpcard__h">' . esc_html__( 'Our stylists reply fastest on WhatsApp.', 'nura-beauty' ) . '</p>';
	$h .= '<ul class="nura-helpcard__grid">';
	$h .= '<li><span>' . esc_html__( 'WhatsApp', 'nura-beauty' ) . '</span><a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">+254 714 994 898</a></li>';
	$h .= '<li><span>' . esc_html__( 'Call', 'nura-beauty' ) . '</span><a href="tel:+254714994898">+254 714 994 898</a></li>';
	$h .= '<li><span>' . esc_html__( 'Email', 'nura-beauty' ) . '</span><a href="mailto:care@nurabeauty.co.ke">care@nurabeauty.co.ke</a></li>';
	$h .= '<li><span>' . esc_html__( 'Visit', 'nura-beauty' ) . '</span>' . ( $map ? '<a href="' . esc_url( $map ) . '" target="_blank" rel="noopener">' . esc_html( $addr ) . '</a>' : esc_html( $addr ) ) . ( $hrs ? '<small>' . esc_html( $hrs ) . '</small>' : '' ) . '</li>';
	$h .= '</ul><p class="nura-helpcard__cta"><a class="nura-btn nura-btn--gold" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Chat on WhatsApp', 'nura-beauty' ) . '</a> <a class="nura-btn nura-btn--ghost" href="' . esc_url( home_url( '/book-appointment/' ) ) . '">' . esc_html__( 'Book a visit', 'nura-beauty' ) . '</a></p></aside>';
	return $h;
}

add_filter( 'the_content', function ( $content ) {
	if ( ! nura_is_inner() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() || ! apply_filters( 'nura_inner_format', true ) || false !== strpos( $content, 'nura-policy__toc' ) ) {
		return $content;
	}
	$slug    = get_post_field( 'post_name', get_queried_object_id() );
	$content = nura_fmt_drop_title( $content );
	// Stale wording kept in old page text (no cash on delivery since 1.73.0).
	$content = preg_replace( '/,?\s*and cash on delivery within Nairobi/i', ', and the M-Pesa Buy Goods Till 1679269 for WhatsApp orders (no cash on delivery)', $content );
	$content = nura_fmt_linklists( $content );
	if ( in_array( $slug, array( 'faq', 'help-support', 'warranty', 'warranty-provenance' ), true ) ) {
		$content = nura_fmt_faq( $content );
	}
	if ( ! nura_is_policy_page() ) {
		return $content;
	}
	// Pages written as one H2 title + H3 sections: the sections become the H2 outline.
	if ( substr_count( strtolower( $content ), '<h2' ) < 2 ) {
		$content = preg_replace( '/(?<!<summary>)<h3([^>]*)>(.*?)<\/h3>/is', '<h2$1>$2</h2>', $content );
	}
	list( $content, $toc ) = nura_fmt_ids( $content );
	$nav = '';
	if ( count( $toc ) >= 2 ) {
		$nav = '<nav class="nura-policy__toc" aria-label="' . esc_attr__( 'On this page', 'nura-beauty' ) . '"><p>' . esc_html__( 'On this page', 'nura-beauty' ) . '</p><ol>';
		foreach ( $toc as $t ) {
			$nav .= '<li><a href="#' . esc_attr( $t[0] ) . '">' . esc_html( $t[1] ) . '</a></li>';
		}
		$nav .= '</ol></nav>';
	}
	$upd = '<p class="nura-policy__updated">' . esc_html( sprintf( __( 'Last updated %s', 'nura-beauty' ), get_the_modified_date( 'j F Y', get_queried_object_id() ) ) ) . '</p>';
	return '<div class="nura-policy' . ( $nav ? ' has-toc' : '' ) . '">' . $nav . '<div class="nura-policy__body">' . $upd . $content . nura_fmt_help_panel() . '</div></div>';
}, 30 );
