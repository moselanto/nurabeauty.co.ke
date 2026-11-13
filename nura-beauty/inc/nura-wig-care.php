<?php
/**
 * 1.108.0 Wig Care: product map, pairings, service and guide cross-links,
 * and the shuffled one-row homepage rail.
 *
 * Products are looked up by slug, so a link only shows once that product is
 * published (nothing breaks if a slug is missing). Edit nura_care_products()
 * and nura_care_sets() to change names, links or pairings.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** slug => [ services it supports, topics ]. */
function nura_care_products() {
	return apply_filters( 'nura_care_products', array(
		'cscr-lace-melting-spray'                       => array( array( 'installation' ), array( 'lace' ) ),
		'ghost-bond-glue-remover'                       => array( array( 'installation', 'relacing' ), array( 'lace' ) ),
		'ghost-bond-xl-wig-glue'                        => array( array( 'installation' ), array( 'lace' ) ),
		'nylon-bristle-detangling-brush'                => array( array( 'styling', 'revamp' ), array( 'detangle' ) ),
		'luodais-classic-curl-keeper-repair-serum'      => array( array( 'styling', 'revamp' ), array( 'curly' ) ),
		'luodais-curl-keeper-smooth-shine-conditioner'  => array( array( 'styling', 'revamp' ), array( 'curly', 'wash' ) ),
		'luodais-wig-weave-repair-serum'                => array( array( 'relacing', 'styling', 'revamp' ), array( 'shine' ) ),
		'ikt-hair-wax-stick'                            => array( array( 'styling', 'bridal' ), array( 'edges' ) ),
		'asstylist-edge-gel'                            => array( array( 'installation', 'styling', 'bridal' ), array( 'edges' ) ),
		'astylsst-texture-setting-spray'                => array( array( 'styling', 'bridal' ), array( 'hold' ) ),
		'astylsst-hair-mousse'                          => array( array( 'styling', 'revamp' ), array( 'curly' ) ),
		'cscr-lace-tint-spray-medium-brown'             => array( array( 'installation', 'customization' ), array( 'lace' ) ),
	) );
}

/** Named pairings shown on each member's product page. */
function nura_care_sets() {
	return apply_filters( 'nura_care_sets', array(
		array( __( 'Remove it safely', 'nura-beauty' ), array( 'cscr-lace-melting-spray', 'ghost-bond-glue-remover' ) ),
		array( __( 'Apply it and remove it', 'nura-beauty' ), array( 'ghost-bond-xl-wig-glue', 'ghost-bond-glue-remover' ) ),
		array( __( 'Wash and style', 'nura-beauty' ), array( 'luodais-curl-keeper-smooth-shine-conditioner', 'luodais-classic-curl-keeper-repair-serum' ) ),
		array( __( 'Edges and flyaways', 'nura-beauty' ), array( 'asstylist-edge-gel', 'ikt-hair-wax-stick' ) ),
		array( __( 'Finish and hold', 'nura-beauty' ), array( 'astylsst-texture-setting-spray', 'ikt-hair-wax-stick', 'asstylist-edge-gel' ) ),
		array( __( 'Curly wig care', 'nura-beauty' ), array( 'astylsst-hair-mousse', 'luodais-classic-curl-keeper-repair-serum', 'luodais-curl-keeper-smooth-shine-conditioner' ) ),
		array( __( 'Lace install', 'nura-beauty' ), array( 'cscr-lace-tint-spray-medium-brown', 'cscr-lace-melting-spray', 'ghost-bond-xl-wig-glue' ) ),
	) );
}

/** Published product for a slug, or null. Cached per request. */
function nura_care_product( $slug ) {
	static $cache = array();
	if ( ! array_key_exists( $slug, $cache ) ) {
		$cache[ $slug ] = null;
		if ( function_exists( 'wc_get_product' ) ) {
			$post = get_page_by_path( $slug, OBJECT, 'product' );
			$id   = ( $post && 'publish' === $post->post_status ) ? $post->ID : 0;
			if ( ! $id && function_exists( 'wc_get_product_id_by_sku' ) ) {
				$id = (int) wc_get_product_id_by_sku( $slug ); // 1.108.1: products carry the slug as their SKU.
				$id = ( $id && 'publish' === get_post_status( $id ) ) ? $id : 0;
			}
			if ( $id ) {
				$cache[ $slug ] = wc_get_product( $id );
			}
		}
	}
	return $cache[ $slug ];
}

/** Compact linked list: "Name (KSh 500), ...". */
function nura_care_links_html( $slugs, $class = 'nura-care-links' ) {
	$items = array();
	foreach ( array_unique( $slugs ) as $slug ) {
		$p = nura_care_product( $slug );
		if ( $p ) {
			$items[] = '<li><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $p->get_name() ) . '</a> <span>' . wp_kses_post( $p->get_price_html() ) . '</span></li>';
		}
	}
	return $items ? '<ul class="' . esc_attr( $class ) . '">' . implode( '', $items ) . '</ul>' : '';
}

/** Slugs for a service key or topic. */
function nura_care_for( $key, $kind = 0 ) {
	$out = array();
	foreach ( nura_care_products() as $slug => $m ) {
		if ( in_array( $key, (array) $m[ $kind ], true ) ) {
			$out[] = $slug;
		}
	}
	return $out;
}

/* Product page: "Pairs well with" sets this product belongs to. */
add_action( 'woocommerce_after_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	// 1.108.2: imported products keep a name-based slug; the map key is their SKU.
	$keys = array_keys( nura_care_products() );
	$slug = in_array( $product->get_sku(), $keys, true ) ? $product->get_sku() : $product->get_slug();
	$html = '';
	foreach ( nura_care_sets() as $set ) {
		if ( ! in_array( $slug, $set[1], true ) ) {
			continue;
		}
		$list = nura_care_links_html( array_diff( $set[1], array( $slug ) ) );
		if ( $list ) {
			$html .= '<div class="nura-care-set"><p class="nura-care-set__h">' . esc_html( $set[0] ) . '</p>' . $list . '</div>';
		}
	}
	if ( $html ) {
		echo '<section class="nura-care-pair"><h2>' . esc_html__( 'Pairs well with', 'nura-beauty' ) . '</h2>' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
}, 12 );

/* Service price table: care products for each service, under the table. */
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( 'nura_service_prices' !== $tag || ! function_exists( 'nura_services' ) ) {
		return $out;
	}
	$rows = '';
	foreach ( nura_services() as $key => $s ) {
		$list = nura_care_links_html( nura_care_for( $key ) );
		if ( $list ) {
			$rows .= '<div class="nura-care-svc__row"><p class="nura-care-svc__h">' . esc_html( $s[0] ) . '</p>' . $list . '</div>';
		}
	}
	if ( $rows ) {
		$out .= '<div class="nura-care-svc"><h3>' . esc_html__( 'Wig care products we use and recommend', 'nura-beauty' ) . '</h3>' . $rows . '<p><a href="' . esc_url( home_url( '/product-category/wig-care/' ) ) . '">' . esc_html__( 'Shop all wig care', 'nura-beauty' ) . ' &#8594;</a></p></div>';
	}
	return $out;
}, 10, 2 );

/* Journal care guides: a "Care products for this guide" box at the end. */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$slug = (string) get_post_field( 'post_name', get_the_ID() );
	if ( false === strpos( $slug, 'care' ) && false === strpos( $slug, 'last' ) && false === strpos( $slug, 'lace-front' ) && false === strpos( $slug, 'installation' ) ) {
		return $content;
	}
	$list = nura_care_links_html( array_keys( nura_care_products() ), 'nura-care-links nura-care-links--grid' );
	if ( ! $list ) {
		return $content;
	}
	return $content . '<aside class="nura-care-guide"><h2>' . esc_html__( 'Wig care products from NURA', 'nura-beauty' ) . '</h2><p>' . esc_html__( 'The products we use in the studio for washing, styling, edges and lace installs.', 'nura-beauty' ) . '</p>' . $list . '</aside>';
}, 20 );

/* Homepage rail: shuffle the order on every load (works with page cache) + arrow buttons. */
add_action( 'wp_footer', function () {
	if ( ! is_front_page() ) {
		return;
	}
	?>
<script>(function(){var r=document.querySelector('[data-nura-care-rail]');if(!r){return;}var ul=r.querySelector('ul.products');if(!ul){return;}var it=[].slice.call(ul.children);for(var i=it.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1));var t=it[i];it[i]=it[j];it[j]=t;}it.forEach(function(li){li.classList.remove('first','last');ul.appendChild(li);});[].forEach.call(r.querySelectorAll('.nura-care-rail__btn'),function(b){b.addEventListener('click',function(){var w=ul.clientWidth*0.85;ul.scrollBy({left:w*parseInt(b.getAttribute('data-dir'),10),behavior:'smooth'});});});function u(){var p=r.querySelector('.nura-care-rail__btn--prev'),n=r.querySelector('.nura-care-rail__btn--next');if(p){p.disabled=ul.scrollLeft<4;}if(n){n.disabled=ul.scrollLeft+ul.clientWidth>=ul.scrollWidth-4;}}ul.addEventListener('scroll',u,{passive:true});window.addEventListener('resize',u);window.addEventListener('load',u);setTimeout(u,600);u();})();</script>
	<?php
}, 6 );

/* NURA Stylist chat + WhatsApp bot: a Wig Care intent group (same knowledge base both use). */
add_filter( 'nurax_knowledge_base', function ( $kb ) {
	if ( ! is_array( $kb ) ) {
		return $kb;
	}
	$shop = home_url( '/product-category/wig-care/' );
	$kb['cats'][] = array(
		'id'     => 'wig-care',
		'title'  => 'Wig care products',
		'kw'     => 'care product products glue remover melt melting spray tint lace edges edge gel wax stick flyaways frizz mousse curl keeper curly conditioner serum shine brush detangle detangling hairspray hold spray gundi',
		'h'      => array(),
		'guides' => array( 'how-to-care-for-a-human-hair-wig', 'human-hair-wig-care-guide' ),
		'q'      => array(
			array( 'What do I need to melt or install lace?', 'For lace installs we use the CSCR Lace Melting Spray (KSh 1,000), Ghost Bond XL wig glue (KSh 400) and the CSCR Lace Tint Spray in Medium Brown (KSh 500) to match the lace to your skin. Remove glue gently with the Lace Glue Remover (KSh 400). Do a patch test first. Shop: ' . $shop, array() ),
			array( 'How do I lay my edges and control flyaways?', 'Use the Asstylist edge gel (KSh 500) to lay edges and baby hairs, and the IKT Hair Wax Stick (KSh 400) for flyaways. Finish with the ASTYLSST Texture Setting Spray (KSh 500) for hold and shine. Shop: ' . $shop, array() ),
			array( 'How do I keep a curly wig bouncy?', 'Wash every 2-3 weeks, condition with the Luodais Curl Keeper Smooth Shine Conditioner (KSh 350), then define curls with the Luodais Classic Curl Keeper and Repair Serum (KSh 350) or the ASTYLSST Hair Mousse (KSh 500, Lanolin or Jojoba). Shop: ' . $shop, array() ),
			array( 'Which brush should I use on my wig?', 'The Nylon Bristle Detangling Brush (KSh 400, pink, red, black or white) detangles wigs, weaves and natural hair gently. Start at the ends and work up. Shop: ' . $shop, array() ),
			array( 'My wig looks dry and dull, what can I use?', 'The Luodais Wig Serum (KSh 400) adds shine and moisture to human and synthetic wigs. For a deeper fix, book a wig laundry (wash and revamp) at the studio. Shop: ' . $shop, array() ),
		),
	);
	return $kb;
} );

/* 1.108.2 Footer legal links: real footer menus are assigned, so add Privacy + Terms to the Help column menu. */
add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	if ( empty( $args->theme_location ) || 'footer_help' !== $args->theme_location ) {
		return $items;
	}
	$priv  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	$priv  = $priv ? $priv : home_url( '/privacy-policy/' );
	$tid   = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
	$terms = $tid ? get_permalink( $tid ) : home_url( '/terms-conditions/' );
	if ( false === strpos( $items, $priv ) ) {
		$items .= '<li class="menu-item"><a href="' . esc_url( $priv ) . '">' . esc_html__( 'Privacy Policy', 'nura-beauty' ) . '</a></li>';
	}
	if ( false === strpos( $items, (string) $terms ) ) {
		$items .= '<li class="menu-item"><a href="' . esc_url( $terms ) . '">' . esc_html__( 'Terms & Conditions', 'nura-beauty' ) . '</a></li>';
	}
	return $items;
}, 20, 2 );
