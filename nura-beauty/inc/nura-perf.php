<?php
/**
 * Staged speed plan (theme 1.88.0). Safe by design:
 * - Cart, checkout, My Account and the order-received page are NEVER changed
 *   (purchase / conversion tracking and payment scripts load exactly as before).
 * - GA4 (Google tag GT-MK5TWKVD / G-FK5L7NBCZ4) is never delayed, so every visit counts.
 * - Each stage is switchable; nothing is removed, only loaded later.
 *
 * Stages (cumulative):
 *   0 = off (behaviour of 1.87.0)
 *   1 = (default) lightbox / brands / select2 stylesheets load without blocking the
 *       first paint on shop, product and content pages; main product photo is fetched first.
 *   2 = + Google Ads tag (AW-18515352802) waits for the first tap/scroll or 5 s.
 *   3 = + Facebook pixel waits for the first tap/scroll or 8 s (was 3.5 s).
 *
 * Test a stage on one page view:   https://nurabeauty.co.ke/?nura_perf=2
 * Make a stage the default (admin): https://nurabeauty.co.ke/?nura_perf_set=2  (logged in as admin)
 * Run PageSpeed with ?nura_perf=N to compare; check GA4 Realtime and Meta Events Manager.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Pages where nothing in this plan applies. */
function nura_perf_protected() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return true;
	}
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) ) {
		return true;
	}
	return false;
}

/** Active stage (0-3). */
function nura_perf_stage() {
	static $s = null;
	if ( null !== $s ) {
		return $s;
	}
	$s = (int) get_option( 'nura_perf_stage', 1 );
	if ( isset( $_GET['nura_perf'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only test switch.
		$s = (int) $_GET['nura_perf']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$s = max( 0, min( 3, (int) apply_filters( 'nura_perf_stage', $s ) ) );
	return $s;
}

/** Admin-only: ?nura_perf_set=N saves the default stage. */
add_action( 'init', function () {
	if ( isset( $_GET['nura_perf_set'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		update_option( 'nura_perf_stage', max( 0, min( 3, (int) $_GET['nura_perf_set'] ) ), false ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		do_action( 'litespeed_purge_all' );
	}
} );

/* Stage 1a: non-critical stylesheets (lightbox, brands, select2) no longer block the first paint. */
add_filter( 'nura_async_styles', function ( $handles ) {
	if ( nura_perf_stage() < 1 || nura_perf_protected() ) {
		return $handles;
	}
	return array_merge( (array) $handles, array( 'photoswipe', 'photoswipe-default-skin', 'brands-styles', 'wc-brands-styles', 'select2' ) );
} );

/* Stage 1b: the main product photo is the first image the browser fetches. */
add_filter( 'woocommerce_gallery_image_html_attachment_image_params', function ( $params, $attachment_id = 0, $image_size = '', $main_image = false ) {
	if ( nura_perf_stage() >= 1 && $main_image ) {
		$params['fetchpriority'] = 'high';
		$params['loading']       = 'eager';
		$params['decoding']      = 'async';
	}
	return $params;
}, 20, 4 );

/* Stage 2: Google Ads tag joins the existing interaction-delay list (GA4 untouched). */
add_filter( 'nura_delay_tag_hosts', function ( $hosts ) {
	if ( nura_perf_stage() >= 2 && ! nura_perf_protected() ) {
		$hosts[] = 'id=AW-';
	}
	return $hosts;
} );

/* Stage 3: longer fallback before delayed tags load without interaction. */
add_filter( 'nura_delay_tags_timeout', function ( $ms ) {
	return ( nura_perf_stage() >= 3 && ! nura_perf_protected() ) ? 8000 : ( nura_perf_stage() >= 2 ? 5000 : $ms );
} );

/* Delayed tags never apply on protected pages (checkout, cart, account, thank-you). */
add_filter( 'nura_delay_tags', function ( $on ) {
	return nura_perf_protected() ? false : $on;
}, 99 );

/* 1.89.0: product gallery - only the first photo loads up front; srcset never offers
 * anything wider than 1024 px (the originals are 470-560 KB each). */
add_filter( 'woocommerce_gallery_image_html_attachment_image_params', function ( $params, $attachment_id = 0, $image_size = '', $main_image = false ) {
	if ( ! $main_image ) {
		$params['loading']       = 'lazy';
		$params['fetchpriority'] = 'low';
	}
	return $params;
}, 21, 4 );
add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array = array(), $image_src = '', $image_meta = array() ) {
	if ( ! is_array( $sources ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $sources;
	}
	// 1.90.0: the originals (e.g. 318-02.jpg, 768 or 896 px wide but 470-560 KB, uncompressed)
	// slipped under the 1024 px cap and phones picked them; never offer the original file.
	$orig = ! empty( $image_meta['file'] ) ? wp_basename( (string) $image_meta['file'] ) : '';
	foreach ( $sources as $w => $s ) {
		$url = isset( $s['url'] ) ? (string) $s['url'] : '';
		if ( (int) $w > 1024 || ( '' !== $orig && wp_basename( $url ) === $orig && count( $sources ) > 1 ) ) {
			unset( $sources[ $w ] );
		}
	}
	return $sources;
}, 20, 4 );

/* 1.89.0: the 'Join The House' pop-up can never paint unless it was opened. */
add_action( 'wp_head', function () {
	echo '<style id="nura-nhs-guard">.nhs[hidden],.nhs-bar[hidden],.nhs-tab[hidden]{display:none!important}</style>' . "\n";
}, 2 );

/* 1.90.0: a visible 'Join The House' tap point in the footer (opens the pop-up on tap only;
 * hidden automatically when the pop-up is switched off). */
add_action( 'wp_head', function () {
	echo '<style id="nura-join-css">.nura-join-house{margin:.2rem 0 .9rem;cursor:pointer}body:not(:has(.nhs)) .nura-join-house{display:none}</style>' . "\n";
}, 3 );


/* 1.100.0: product page LCP. WooCommerce prints the gallery with inline opacity:0 and only
 * reveals it after the deferred gallery script (flexslider) has downloaded and run - on a phone
 * on Slow 4G that held the main photo back to ~7.8 s. Now: the gallery is visible from the
 * first paint (only the first photo shows until the slider starts, so nothing jumps), and the
 * first photo is preloaded at the displayed size (same srcset/sizes as the <img>).
 * Off: add_filter( 'nura_pdp_lcp', '__return_false' ). */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! apply_filters( 'nura_pdp_lcp', true ) ) {
		return;
	}
	echo '<style id="nura-pdp-lcp">.woocommerce-product-gallery{opacity:1!important}'
		. '.woocommerce-product-gallery>.woocommerce-product-gallery__wrapper>.woocommerce-product-gallery__image:nth-child(n+2){display:none}'
		. '.woocommerce-product-gallery>.woocommerce-product-gallery__wrapper>.woocommerce-product-gallery__image:first-child img{width:100%;height:auto}</style>' . "\n";
	$product = wc_get_product( get_queried_object_id() );
	$id      = $product ? (int) $product->get_image_id() : 0;
	if ( ! $id ) {
		return;
	}
	$size   = apply_filters( 'woocommerce_gallery_image_size', 'woocommerce_single' );
	$src    = wp_get_attachment_image_src( $id, $size );
	if ( ! $src ) {
		return;
	}
	$srcset = wp_get_attachment_image_srcset( $id, $size );
	$sizes  = wp_get_attachment_image_sizes( $id, $size );
	// 1.101.0: when the WebP copies exist, preload exactly what the <img> uses.
	if ( function_exists( 'nura_webp_srcset' ) && nura_webp_on() && '' !== nura_webp_srcset( $id ) ) {
		$src    = array( nura_webp_src( $id ) );
		$srcset = nura_webp_srcset( $id );
		$sizes  = nura_webp_sizes();
	}
	printf(
		'<link rel="preload" as="image" href="%1$s"%2$s%3$s fetchpriority="high">' . "\n",
		esc_url( $src[0] ),
		$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
		( $srcset && $sizes ) ? ' imagesizes="' . esc_attr( $sizes ) . '"' : ''
	);
}, 1 );


/* 1.102.0: product-page Total Blocking Time. PageSpeed (Minky closure wig, TBT 280 ms) showed the
 * blocking long tasks were third-party: Meta Pixel (fbevents.js + config, ~300 ms; loaded by two
 * identical inline Pixel snippets) and the Google Ads tag AW-... (~190 ms; printed directly by
 * Google for WooCommerce, so the 1.69.1 delay list never caught it). On product pages:
 * - a standard fbq queue stub is defined first, so both inline Pixel snippets queue their
 *   init/PageView instead of loading fbevents.js; fbevents.js then loads on the first tap /
 *   scroll / key press (nura-speed.php #13) and replays the queue - no events are lost;
 * - the Google Ads <script src=...gtag/js?id=AW-...> becomes a delayed placeholder too
 *   (gtag() calls already queue into dataLayer);
 * - the no-interaction fallback for these tags is 15 s instead of 8 s.
 * GA4 / Site Kit (GT-...) is NOT delayed (kept from 1.75.0). Cart, checkout and account untouched.
 * Off: add_filter( 'nura_tbt_product', '__return_false' ). */
function nura_tbt_on() {
	return function_exists( 'is_product' ) && is_product() && ! nura_perf_protected() && apply_filters( 'nura_delay_tags', true ) && apply_filters( 'nura_tbt_product', true );
}
add_action( 'wp_head', function () {
	if ( ! nura_tbt_on() ) {
		return;
	}
	echo "<script id=\"nura-fbq-stub\">!function(f){if(f.fbq)return;var n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[]}(window);</script>\n";
	echo '<script type="text/plain" data-nura-src="https://connect.facebook.net/en_US/fbevents.js"></script>' . "\n";
}, 1 );
add_action( 'template_redirect', function () {
	if ( ! nura_tbt_on() ) {
		return;
	}
	ob_start( function ( $html ) {
		return preg_replace( '#<script\s+async\s+src="(https://www\.googletagmanager\.com/gtag/js\?id=AW-[^"]+)"\s*>\s*</script>#i', '<script type="text/plain" data-nura-src="$1"></script>', $html );
	} );
}, 99 );
add_filter( 'nura_delay_tags_timeout', function ( $ms ) {
	return ( function_exists( 'is_product' ) && is_product() && apply_filters( 'nura_tbt_product', true ) ) ? 15000 : $ms;
}, 20 );
