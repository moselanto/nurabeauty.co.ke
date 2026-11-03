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
