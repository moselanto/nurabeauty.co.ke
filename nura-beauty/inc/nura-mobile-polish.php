<?php
/**
 * Mobile polish from Nura's phone screenshots (Oct 2026):
 * - closed side drawer no longer casts a grey shadow down the left edge;
 * - product card titles clamp neatly to 2 lines (no clipped text under the hair-type tag);
 * - "Order on WhatsApp" card button fits without being cut off;
 * - "Ask NURA Stylist" becomes a compact round button on phones so it never covers photos;
 * - long breadcrumbs stay on one line instead of pushing the product photo down.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	?>
	<style id="nura-mobile-polish-css">
	.nura-drawer:not(.is-open){box-shadow:none}
	@media (max-width:768px){
		html body .woocommerce ul.products li.product .woocommerce-loop-product__title,
		html body ul.products li.product .woocommerce-loop-product__title{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;line-clamp:2;overflow:hidden;align-items:initial;justify-content:initial;min-height:2.6em;max-height:2.6em;line-height:1.3;padding-top:0;padding-bottom:0}
		html body ul.products li.product .nura-hairtype{margin-top:.35rem}
		html body ul.products li.product .nwo-btn--card{padding:0 .35rem;gap:.3rem;font-size:.64rem;letter-spacing:.02em}
		html body ul.products li.product .nwo-btn--card span{overflow:visible;text-overflow:clip}
		.nurax-stylist .nurax-stylist__fab-label{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
		.woocommerce-breadcrumb,.nura-breadcrumbs ol{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.78rem}
		.nura-breadcrumbs ol{display:block}
		.nura-breadcrumbs li{display:inline}
	}
	</style>
	<?php
}, 33 );
