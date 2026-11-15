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

/* 1.161.4: delivery line keeps one height from first paint. The countdown script
   rewrites .nura-eta__t after load; hidden copies of the longest texts share the
   same grid cell, so the box is already as tall as any text it can show and the
   short description + Specifications box below never move. */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16-eta">.nura-eta__w{display:grid;flex:1 1 auto;min-width:0}.nura-eta__w>span{grid-area:1/1}.nura-eta__s{visibility:hidden;pointer-events:none;user-select:none}.woocommerce-product-details__short-description{overflow-anchor:none}</style>' . "\n";
}, 3 );

/* 1.161.5: add-to-bag area keeps its final height from first paint (desktop CLS on variable products).
   Measured on CSCR Lace Tint: (1) the "Order on WhatsApp" button is styled by nura-wa-order.css, which loads
   deferred; unstyled it sits inline (row 52 px), styled it is a full-width 52 px block below (row 124 px), so the
   pay row and promise box jump. (2) the shade pills are display:none until main.js adds .nura-var-enhanced, so
   the options table grows 43 -> 94 px and pushes .single_variation_wrap and .nura-payrow down.
   Fix: the button's layout rules and the pill/select swap are applied in the head, before any script runs. */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16-atc">.summary .nwo-pdp{flex:1 1 100%;width:100%;margin:.65rem 0 0;clear:both}.summary .nwo-btn--pdp{display:flex;align-items:center;justify-content:center;gap:.55rem;width:100%;box-sizing:border-box;min-height:52px;padding:.75rem 1.2rem;font-size:.82rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;line-height:1.2;border-radius:6px;text-decoration:none}.summary .nwo-btn--pdp .nwo-ico{flex:none}.summary form.variations_form .nura-swatches{display:flex}.summary form.variations_form td.value:has(.nura-swatches) select{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}</style>' . "\n";
}, 3 );

/* 1.161.6 (a): gallery images carry an explicit CSS aspect-ratio.
   Chrome treats an <img> as "unsized" (DevTools/PSI "Unsized image element") unless its computed style has
   both width and height fixed/percent, or a plain aspect-ratio (type "ratio") plus one of them. The width/height
   attributes only give "aspect-ratio: auto W/H", and the theme's width:100%;height:auto drops the height, so
   every gallery image was flagged even though it never changed size. An inline aspect-ratio from the
   attributes makes it count as sized; layout is unchanged. */
add_filter( 'woocommerce_single_product_image_thumbnail_html', function ( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<img' ) ) {
		return $html;
	}
	return preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) {
		$tag = $m[0];
		if ( ! preg_match( '/\swidth="(\d+)"/i', $tag, $w ) || ! preg_match( '/\sheight="(\d+)"/i', $tag, $h ) || ! (int) $w[1] || ! (int) $h[1] ) {
			return $tag;
		}
		if ( false !== stripos( $tag, 'aspect-ratio' ) ) {
			return $tag;
		}
		$ar = 'aspect-ratio:' . (int) $w[1] . '/' . (int) $h[1];
		if ( preg_match( '/\sstyle="([^"]*)"/i', $tag ) ) {
			return preg_replace( '/\sstyle="([^"]*)"/i', ' style="$1;' . $ar . '"', $tag, 1 );
		}
		return preg_replace( '/^<img\b/i', '<img style="' . $ar . '"', $tag, 1 );
	}, $html );
}, 40 );

/* 1.161.6 (b): phones - before the slider starts, only the first photo shows.
   WooCommerce's own rule (.woocommerce div.product div.images .woocommerce-product-gallery__image:nth-child(n+2)
   {display:inline-block;width:25%}) out-ranked the theme's hide rule, so photo 2+ showed as a thumbnail row under
   the main photo AND the 1.161.2 strip placeholder (::after) reserved a second row. When the slider started the
   extra row vanished and the product summary jumped up (~100 px, CLS 0.017 on 1:1-photo care products).
   This rule has higher specificity and only matches the pre-slider markup (wrapper still a direct child). */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16-gal2">.woocommerce div.product div.images.woocommerce-product-gallery>.woocommerce-product-gallery__wrapper>.woocommerce-product-gallery__image:nth-child(n+2){display:none}</style>' . "\n";
}, 3 );

/* 1.161.7 (a): buy box on variable products has its final size from first paint (desktop CLS).
   Measured on the Detangling Brush: the colour pills (.nura-pill, min-height 42px), the quantity box and the
   Add to bag button (52px) are sized by deferred stylesheets (woocommerce.css / main.css). At first paint the
   pills were 21px and the buttons 21-27px, so the options table grew 50 -> 94px and the button row 117 -> 124px,
   pushing .single_variation_wrap, .nura-payrow and .nura-promise down (PSI 0.014). The label cell also widened
   79 -> 134px when main.js appended ": Pink", squeezing the pills; on desktop the label column now has a fixed width. */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<style id="nura-r16-buybox">.summary form.cart .variations td,.summary form.cart .variations th{padding:.35rem 0}.summary .nura-swatches{flex-wrap:wrap;gap:.5rem;margin:.15rem 0 .4rem}.summary .nura-swatch,.summary .nura-pill{box-sizing:border-box;font-family:var(--font-ui);background:#fff;border:1px solid var(--nura-line,#e5ddd0);border-radius:2px;line-height:normal}.summary .nura-pill{padding:.6em 1.1em;font-size:.8rem;letter-spacing:.06em;min-height:42px}.summary .nura-swatch{display:inline-flex;align-items:center;gap:.5rem;padding:.4em .8em .4em .45em;font-size:.8rem;min-height:42px}.summary .nura-swatch__dot{width:20px;height:20px;border-radius:50%;flex:none}.summary .woocommerce-variation-add-to-cart,.summary form.cart:not(.variations_form){display:flex;flex-wrap:wrap;align-items:stretch;gap:.6rem}.summary form.cart .quantity{flex:none;margin:0}.summary form.cart .quantity input.qty{box-sizing:border-box;width:72px;height:52px;min-height:52px;font-size:16px}.summary form.cart .single_add_to_cart_button{flex:1 1 180px;min-height:52px;margin:0;white-space:nowrap;display:inline-flex;align-items:center;justify-content:center;gap:.55rem;font-size:.85rem;letter-spacing:.14em;text-transform:uppercase}@media(min-width:861px){.summary table.variations{table-layout:fixed;width:100%}.summary table.variations th.label{width:10.5rem;text-align:left}}</style>' . "\n";
}, 3 );

/* 1.161.7 (b): Lighthouse "Agentic Browsing" / accessibility tree: the closed mobile menu
   (aside.nura-drawer, aria-hidden="true") contained focusable links and buttons ("ARIA hidden element must not be
   focusable or contain focusable elements"). The drawer is now also inert while closed (attribute in header.php),
   and this script keeps inert in step with aria-hidden whenever main.js opens or closes the drawer.
   1.161.8: the cart drawer (aside.nura-cartdrawer) gets the same treatment. */
add_action( 'wp_footer', function () {
	echo "<script id=\"nura-r16-drawer-inert\">(function(d){[].forEach.call(d.querySelectorAll('[data-nura-drawer-panel],[data-nura-cartdrawer]'),function(a){function s(){if(a.getAttribute('aria-hidden')==='false'){a.removeAttribute('inert');}else{a.setAttribute('inert','');}}s();try{new MutationObserver(s).observe(a,{attributes:true,attributeFilter:['aria-hidden']});}catch(e){}});})(document);</script>\n";
}, 99 );
