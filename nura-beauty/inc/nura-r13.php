<?php
/**
 * R13 (theme 1.158.0): shorter shop and product pages on first load (owner-approved, 14 Nov 2026).
 * 1. Shop, category, tag and product search pages show 12 wigs per page; the next page's wigs are added
 *    automatically as the visitor scrolls near the end of the grid (normal page links stay in the HTML for
 *    search engines and as a "Load more" fallback).
 * 2. Product pages: "Complete the look", related / upsell wigs, the "Before you buy" guide links,
 *    shopper questions and the wig-terms box are delivered inside <template> and added to the page when the
 *    visitor scrolls near them. Reviews stay in the page (search engines read them) but are not laid out
 *    until scrolled into view (content-visibility).
 * Off: add_filter( 'nura_r13_on', '__return_false' ) or ?nura_r13_off=1 on one page view.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_r13_on() {
	return (bool) apply_filters( 'nura_r13_on', true ) && ! is_admin() && empty( $_GET['nura_r13_off'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		&& ! ( function_exists( 'nura_perf_protected' ) && nura_perf_protected() );
}

/* 1. 12 per page on product listings. */
add_filter( 'loop_shop_per_page', function ( $n ) {
	return nura_r13_on() ? (int) apply_filters( 'nura_r13_per_page', 12 ) : $n;
}, 99 );

add_action( 'wp_footer', function () {
	if ( ! nura_r13_on() || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) {
		return;
	}
	?>
<style id="nura-r13-shop">.nura-more{display:flex;justify-content:center;margin:1.4rem 0 2rem}.nura-more[hidden]{display:none}.nura-more__btn{min-width:200px}.nura-more.is-js+.woocommerce-pagination,.nura-more.is-js~.woocommerce-pagination{display:none}</style>
<script id="nura-r13-shop-js">(function(d,w){var busy=0,io=null;
function grid(){return d.querySelector("ul.products");}
function nextLink(){var a=d.querySelector(".woocommerce-pagination a.next");return a?a.href:"";}
function ensure(){var g=grid();if(!g){return;}var m=d.querySelector(".nura-more");var nx=nextLink();
 if(!m){m=d.createElement("div");m.className="nura-more is-js";m.innerHTML='<a class="nura-btn nura-btn--ghost nura-more__btn" href="#">Show more wigs</a>';g.parentNode.insertBefore(m,g.nextSibling);
  m.querySelector("a").addEventListener("click",function(e){e.preventDefault();load();});}
 m.hidden=!nx;if(nx){m.querySelector("a").href=nx;}
 if("IntersectionObserver" in w){if(io){io.disconnect();}io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){load();}});},{rootMargin:"900px 0px"});if(nx){io.observe(m);}}}
function load(){var url=nextLink();if(busy||!url){return;}busy=1;var m=d.querySelector(".nura-more");if(m){m.setAttribute("aria-busy","true");}
 fetch(url,{credentials:"same-origin"}).then(function(r){return r.ok?r.text():"";}).then(function(h){busy=0;if(m){m.removeAttribute("aria-busy");}if(!h){return;}
  var doc=new DOMParser().parseFromString(h,"text/html"),g=grid(),ng=doc.querySelector("ul.products");if(!g||!ng){return;}
  var added=[];[].slice.call(ng.children).forEach(function(li){var x=d.importNode(li,true);g.appendChild(x);added.push(x);});
  var pg=d.querySelector(".woocommerce-pagination"),npg=doc.querySelector(".woocommerce-pagination");if(pg){if(npg){pg.parentNode.replaceChild(d.importNode(npg,true),pg);}else{pg.parentNode.removeChild(pg);}}
  added.forEach(function(li){[].forEach.call(li.querySelectorAll("img[data-src]:not(.nura-loop-2nd)"),function(im){var s=im.getAttribute("src")||"";if(!s||s.indexOf("data:")===0){im.src=im.getAttribute("data-src");var ss=im.getAttribute("data-srcset");if(ss){im.srcset=ss;}}});});
  try{d.dispatchEvent(new CustomEvent("nura:panel-loaded",{detail:{panel:g}}));}catch(e){}
  if(w.jQuery){w.jQuery(d.body).trigger("nura_panel_loaded",[g]);}
  ensure();}).catch(function(){busy=0;if(m){m.removeAttribute("aria-busy");}});}
function start(){ensure();try{new MutationObserver(function(){if(grid()&&!d.querySelector(".nura-more")){ensure();}}).observe(d.body,{childList:true,subtree:true});}catch(e){}
 d.addEventListener("nura:shop-swapped",ensure);}
if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",start);}else{start();}})(document,window);</script>
	<?php
}, 30 );

/* 2. Product pages: below-the-fold sections arrive when scrolled to. */
function nura_r13_wrap_block( $html, $open_pattern ) {
	if ( ! preg_match( $open_pattern, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$start = $m[0][1];
	if ( ! preg_match( '/^<(\w+)/', $m[0][0], $t ) ) {
		return $html;
	}
	$tag   = strtolower( $t[1] );
	$depth = 0;
	$pos   = $start;
	if ( ! preg_match_all( '#<(/?)' . $tag . '\b[^>]*>#i', $html, $all, PREG_OFFSET_CAPTURE, $start ) ) {
		return $html;
	}
	$end = -1;
	foreach ( $all[0] as $i => $hit ) {
		$depth += '' === $all[1][ $i ][0] ? 1 : -1;
		if ( 0 === $depth ) {
			$end = $hit[1] + strlen( $hit[0] );
			break;
		}
	}
	if ( $end < 0 ) {
		return $html;
	}
	$block = substr( $html, $start, $end - $start );
	if ( false !== stripos( $block, '<template' ) || false !== stripos( $block, '<script' ) ) {
		return $html; // keep anything with its own scripts/templates untouched.
	}
	$wrapped = '<div class="nura-lazysec" data-nura-lazysec><template>' . $block . '</template></div>';
	return substr( $html, 0, $start ) . $wrapped . substr( $html, $end );
}

add_action( 'template_redirect', function () {
	if ( ! nura_r13_on() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	ob_start( function ( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, '</main>' ) ) {
			return $html;
		}
		$patterns = (array) apply_filters( 'nura_r13_lazy_sections', array(
			'#<nav class="nura-hub-links nura-hub-links--pdp"[^>]*>#',
			'#<section class="nura-complete"[^>]*>#',
			'#<section class="nura-pq"[^>]*>#',
			'#<section class="nura-enc-box"[^>]*>#',
			'#<section class="nura-complete-look"[^>]*>#',
			'#<section class="related products"[^>]*>#',
			'#<section class="up-sells[^"]*"[^>]*>#',
		) );
		foreach ( $patterns as $p ) {
			$html = nura_r13_wrap_block( $html, $p );
		}
		return $html;
	} );
}, 97 );

add_action( 'wp_footer', function () {
	if ( ! nura_r13_on() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	?>
<style id="nura-r13-pdp">.nura-lazysec{min-height:240px}.nura-lazysec.is-in{min-height:0}#reviews,.woocommerce-Tabs-panel--reviews{content-visibility:auto;contain-intrinsic-size:auto 900px}</style>
<script id="nura-r13-pdp-js">(function(d,w){function show(box){if(box.classList.contains("is-in")){return;}var t=box.querySelector("template");if(!t){return;}box.classList.add("is-in");box.appendChild(d.importNode(t.content,true));t.parentNode.removeChild(t);
 [].forEach.call(box.querySelectorAll("img[data-src]:not(.nura-loop-2nd)"),function(im){var s=im.getAttribute("src")||"";if(!s||s.indexOf("data:")===0){im.src=im.getAttribute("data-src");var ss=im.getAttribute("data-srcset");if(ss){im.srcset=ss;}}});
 try{d.dispatchEvent(new CustomEvent("nura:panel-loaded",{detail:{panel:box}}));}catch(e){}if(w.jQuery){w.jQuery(d.body).trigger("nura_panel_loaded",[box]);}}
function start(){var boxes=[].slice.call(d.querySelectorAll("[data-nura-lazysec]"));if(!boxes.length){return;}
 if(!("IntersectionObserver" in w)){boxes.forEach(show);return;}
 var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){io.unobserve(e.target);show(e.target);}});},{rootMargin:"700px 0px"});boxes.forEach(function(b){io.observe(b);});
 w.addEventListener("beforeprint",function(){boxes.forEach(show);});
 if(location.hash){boxes.forEach(show);}}
if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",start);}else{start();}})(document,window);</script>
	<?php
}, 30 );

/* 1.158.1: desktop shop layout shift - the "Ask NURA Stylist" chip row grew from 55 to 66 px when the
 * full stylesheet arrived, pushing the (now shorter, fully visible) 12-wig grid down (CLS 0.33). Reserve it. */
add_action( 'wp_head', function () {
	if ( ! nura_r13_on() || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() || is_search() ) ) {
		return;
	}
	echo '<style id="nura-r13-cls">@media(min-width:861px){.nura-shop-results .nura-ask-row,.nura-ask-row{min-height:69px;align-content:flex-start}}</style>' . "\n";
}, 4 );
