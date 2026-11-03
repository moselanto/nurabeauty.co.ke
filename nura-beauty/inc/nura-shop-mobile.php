<?php
/**
 * 1.106.0 Shop on phones + fresh product order on every visit.
 *
 * - All products fit on one page (48 per page) so there is no near-empty page 2.
 * - With the default sorting, the product grid is shuffled in the browser on each
 *   load, so a reload shows a different order. Done in JavaScript on purpose:
 *   LiteSpeed serves the same cached HTML to everyone, so a server-side random
 *   order would look frozen until the next purge. Sorting by price/popularity/etc.
 *   and filtered views are left exactly as chosen.
 * - Floating chat/WhatsApp buttons are hidden on the booking form and checkout so
 *   they never cover form controls.
 * Off switch: add_filter( 'nura_shop_shuffle', '__return_false' );
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'loop_shop_per_page', function () {
	return 48;
}, 30 );

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_category() || is_product_tag() ) || is_search() ) {
		return;
	}
	if ( ! apply_filters( 'nura_shop_shuffle', true ) ) {
		return;
	}
	?>
<script>(function(){try{var q=location.search;if(/[?&](orderby|filter_[a-z_]+|min_price|max_price|rating_filter|stock|s)=/.test(q)){return;}var lists=document.querySelectorAll('ul.products');for(var n=0;n<lists.length;n++){var ul=lists[n];if(ul.closest('.related,.upsells,.cross-sells')){continue;}var items=[].slice.call(ul.children).filter(function(li){return li.tagName==='LI';});for(var i=items.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=items[i];items[i]=items[j];items[j]=t;}items.forEach(function(li){li.classList.remove('first','last');ul.appendChild(li);});}}catch(e){}})();</script>
	<?php
}, 5 );

/** 1.106.1: mark form pages so floating chat/WhatsApp buttons can step aside (body classes differ per template). */
add_filter( 'body_class', function ( $classes ) {
	if ( is_page( array( 'book-appointment', 'contact', 'contact-us', 'bulk-orders', 'track-order' ) ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
		$classes[] = 'nura-form-page';
	}
	return $classes;
} );

/**
 * 1.110.0 Floating chat button on phones: smaller, sits above the bottom bar, and
 * slides out of the way while the visitor scrolls so it never covers product cards.
 */
add_action( 'wp_footer', function () {
	?>
<script>(function(){var t,b=document.body;if(!b){return;}
function over(){var f=document.querySelector('.nurax-stylist__fab');if(!f||window.innerWidth>860||!document.elementsFromPoint){b.classList.remove('nura-fab-over');return;}var st=f.closest('.nurax-stylist');if(st&&st.classList.contains('is-open')){b.classList.remove('nura-fab-over');return;}var r=f.getBoundingClientRect();if(!r.width){return;}var hit=false,pts=[[r.left+4,r.top+4],[r.right-4,r.top+4],[r.left+4,r.bottom-4],[r.right-4,r.bottom-4],[r.left+r.width/2,r.top+r.height/2]];for(var i=0;i<pts.length&&!hit;i++){var els=document.elementsFromPoint(pts[i][0],pts[i][1]);for(var j=0;j<els.length;j++){if(els[j].closest&&els[j].closest('li.product, .nura-care-rail, .single_add_to_cart_button, form.cart, .nura-sticky-buy, button, .button')&&!els[j].closest('.nurax-stylist')){hit=true;break;}}}b.classList.toggle('nura-fab-over',hit);}
window.addEventListener('scroll',function(){b.classList.add('nura-scrolling');clearTimeout(t);t=setTimeout(function(){b.classList.remove('nura-scrolling');over();},700);},{passive:true});
window.addEventListener('resize',over);window.addEventListener('load',over);setTimeout(over,800);})();</script>
	<?php
}, 7 );
