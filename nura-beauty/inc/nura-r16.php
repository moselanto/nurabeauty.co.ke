<?php
/**
 * R16 (theme 1.161.0): product page on phones - less work before the main photo is ready.
 * - The sticky add-to-bag bar stays hidden until the page has loaded and the browser is idle (it only
 *   appears on scroll anyway; its script still binds normally because the bar is in the page).
 * - The "why order from NURA", payment, delivery-promise and trust panels, the description/review tabs
 *   and the footer are not laid out until they are scrolled near (content-visibility). They look and work
 *   exactly the same; the phone simply skips drawing them while the photo loads.
 * Phones only (max-width 860px). Off: add_filter( 'nura_r16_on', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	if ( ! apply_filters( 'nura_r16_on', true ) || is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16">html:not(.nura-ready) .nura-sticky-atc{display:none!important}@media(max-width:860px){'
		. '.single-product .nura-why-buy,.single-product .nura-payrow,.single-product .nura-promise,.single-product .nura-trust{content-visibility:auto;contain-intrinsic-size:auto 120px}'
		. '.single-product .woocommerce-tabs{content-visibility:auto;contain-intrinsic-size:auto 700px}'
		. '.single-product footer.site-footer,.single-product #colophon{content-visibility:auto;contain-intrinsic-size:auto 1200px}}</style>' . "\n";
	echo "<script id=\"nura-r16-js\">(function(w,d){function go(){(w.requestIdleCallback||function(f){setTimeout(f,200);})(function(){d.documentElement.classList.add('nura-ready');},{timeout:3000});}if(d.readyState==='complete'){go();}else{w.addEventListener('load',go);}['scroll','touchstart','pointerdown'].forEach(function(e){w.addEventListener(e,function(){d.documentElement.classList.add('nura-ready');},{once:true,passive:true});});})(window,document);</script>\n";
}, 6 );

/* 1.161.1: PageSpeed (phone) layout shift 0.095 on product pages came from the PhotoSwipe lightbox markup
 * (div.pswp / .pswp__top-bar) in the footer: its stylesheet loads without blocking the first paint, so for a
 * moment the closed lightbox toolbar was laid out in the page and then removed. Keep it hidden from the
 * first paint until the lightbox is actually opened (same rule PhotoSwipe's own CSS applies). */
add_action( 'wp_head', function () {
	if ( ! apply_filters( 'nura_r16_on', true ) || is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16-pswp">.pswp{display:none}.pswp.pswp--open{display:block}</style>' . "\n";
}, 3 );

/* 1.161.2: product galleries with 2+ photos - the thumbnail strip (ol.flex-control-thumbs) is only added when the
 * gallery script starts, which pushed everything below it down on phones (PageSpeed CLS 0.104 on Pixie Water Curl).
 * Reserve the strip's space (one row of 4 square thumbnails = width/4; two rows for 5-8 photos) until it exists. */
add_action( 'wp_head', function () {
	if ( ! apply_filters( 'nura_r16_on', true ) || is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16-thumbs">.woocommerce-product-gallery:has(.woocommerce-product-gallery__image:nth-child(2)):not(:has(.flex-control-nav))::after{content:"";display:block;aspect-ratio:4/1}'
		. '.woocommerce-product-gallery:has(.woocommerce-product-gallery__image:nth-child(5)):not(:has(.flex-control-nav))::after{aspect-ratio:2/1}</style>' . "\n";
}, 3 );

/* 1.161.3: Pixie Water Curl phone CLS 0.104 (culprit ol.flex-control-thumbs). Measured: when the gallery slider
 * starts, the photo frame (.flex-viewport) is briefly as tall as all photos stacked (~5,000 px) with the
 * thumbnail strip at the bottom, then collapses to one photo and the strip jumps up into view.
 * - The photo frame now has the main photo's own proportions (from its saved width/height) from the first
 *   paint, so it never stretches.
 * - The thumbnail strip stays invisible (its space is kept) until the slider has finished starting. */
add_action( 'wp_head', function () {
	if ( ! apply_filters( 'nura_r16_on', true ) || is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	$id      = $product ? (int) $product->get_image_id() : 0;
	$meta    = $id ? wp_get_attachment_metadata( $id ) : array();
	$w       = ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
	$h       = ! empty( $meta['height'] ) ? (int) $meta['height'] : 0;
	$css     = '.woocommerce-product-gallery:not(.nura-gal-ready) .flex-control-nav{visibility:hidden}';
	if ( $w > 0 && $h > 0 ) {
		$css .= '.woocommerce-product-gallery .flex-viewport{aspect-ratio:' . $w . '/' . $h . ';height:auto!important;overflow:hidden}'
			. '.woocommerce-product-gallery .flex-viewport .woocommerce-product-gallery__image img{width:100%;height:auto}';
	}
	echo '<style id="nura-r16-gal">' . $css . '</style>' . "\n";
}, 3 );
add_action( 'wp_footer', function () {
	if ( ! apply_filters( 'nura_r16_on', true ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo "<script id=\"nura-r16-gal-js\">(function(w,d){function ready(g){if(!g||g.classList.contains('nura-gal-ready'))return;setTimeout(function(){requestAnimationFrame(function(){g.classList.add('nura-gal-ready');});},450);}"
		. "function bind(){if(w.jQuery){w.jQuery(d).on('wc-product-gallery-after-init','.woocommerce-product-gallery',function(){ready(this);});}"
		. "setTimeout(function(){[].forEach.call(d.querySelectorAll('.woocommerce-product-gallery'),function(g){if(g.querySelector('.flex-control-nav')||!g.querySelector('.woocommerce-product-gallery__image:nth-child(2)')){ready(g);}});},6000);}"
		. "if(d.readyState==='loading'){d.addEventListener('DOMContentLoaded',bind);}else{bind();}})(window,document);</script>\n";
}, 100 );
