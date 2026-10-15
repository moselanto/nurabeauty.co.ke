<?php
/**
 * "Read the guide" callout on shop category pages, linking each category to the
 * matching Journal buying guide (helps shoppers decide and gives Google internal links).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_category_guides() {
	$g = array(
		'headband'  => array( '/headband-wigs-kenya-guide/', __( 'Headband wigs in Kenya: the easy, no-glue beginner\'s guide', 'nura-beauty' ) ),
		'lacefront' => array( '/lace-front-wigs-kenya-guide/', __( 'Lace front wigs in Kenya: how to choose, install and care for yours', 'nura-beauty' ) ),
		'care'      => array( '/human-hair-wig-care-guide/', __( 'How to care for a human hair wig', 'nura-beauty' ) ),
		'hvs'       => array( '/human-hair-vs-synthetic-wigs-kenya/', __( 'Human hair vs synthetic wigs: which should you buy?', 'nura-beauty' ) ),
		'beginners' => array( '/best-wigs-for-beginners-kenya/', __( 'Best wigs for beginners in Kenya', 'nura-beauty' ) ),
		'nairobi'   => array( '/buying-wigs-in-nairobi-guide/', __( 'Buying wigs in Nairobi: prices, styles and where to shop', 'nura-beauty' ) ),
	);
	$map = array(
		'headband-wigs'      => array( 'headband', 'beginners' ),
		'lace-front-wigs'    => array( 'lacefront', 'hvs' ),
		'human-hair-wigs'    => array( 'care', 'hvs' ),
		'curly-wigs'         => array( 'care', 'lacefront' ),
		'jerry-curl-wigs'    => array( 'care', 'lacefront' ),
		'closure-wigs'       => array( 'beginners', 'hvs' ),
		'bob-wigs'           => array( 'beginners', 'hvs' ),
		'pixie-wigs'         => array( 'beginners', 'lacefront' ),
		'afro-wigs'          => array( 'beginners', 'hvs' ),
		'body-wave-wigs'     => array( 'lacefront', 'hvs' ),
		'bone-straight-wigs' => array( 'lacefront', 'hvs' ),
		'wigs'               => array( 'nairobi', 'beginners' ),
	);
	return array( $g, $map );
}

add_action( 'woocommerce_archive_description', function () {
	if ( ! function_exists( 'is_product_category' ) || absint( get_query_var( 'paged' ) ) > 1 ) {
		return;
	}
	list( $g, $map ) = nura_category_guides();
	$keys = array();
	if ( is_product_category() ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term ) {
			$keys = isset( $map[ $t->slug ] ) ? $map[ $t->slug ] : array();
			if ( ! $keys ) {
				foreach ( get_ancestors( $t->term_id, 'product_cat' ) as $aid ) {
					$a = get_term( $aid, 'product_cat' );
					if ( $a && ! is_wp_error( $a ) && isset( $map[ $a->slug ] ) ) {
						$keys = $map[ $a->slug ];
						break;
					}
				}
			}
		}
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$keys = array( 'beginners', 'nairobi' );
	}
	if ( ! $keys ) {
		return;
	}
	echo '<aside class="nura-guidelink" aria-label="' . esc_attr__( 'Buying guides', 'nura-beauty' ) . '"><span class="nura-guidelink__eyebrow">' . esc_html__( 'Not sure which to choose?', 'nura-beauty' ) . '</span><ul>';
	foreach ( $keys as $k ) {
		if ( isset( $g[ $k ] ) ) {
			echo '<li><a href="' . esc_url( home_url( $g[ $k ][0] ) ) . '">' . esc_html__( 'Read the guide:', 'nura-beauty' ) . ' <strong>' . esc_html( $g[ $k ][1] ) . '</strong> &#8594;</a></li>';
		}
	}
	echo '</ul></aside>';
}, 25 );

add_action( 'wp_head', function () {
	if ( ! ( ( function_exists( 'is_product_category' ) && is_product_category() ) || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
		return;
	}
	?>
	<style id="nura-guidelink-css">
	.nura-guidelink{margin:1rem 0 1.4rem;padding:.9rem 1.1rem;border:1px solid rgba(201,162,75,.4);border-left:3px solid #c9a24b;border-radius:10px;background:#faf6f0;max-width:780px}
	.nura-guidelink__eyebrow{display:block;font-family:var(--font-ui,'Jost',sans-serif);font-size:.7rem;letter-spacing:.14em;text-transform:uppercase;color:#7a5a12;margin-bottom:.35rem}
	.nura-guidelink ul{list-style:none;margin:0;padding:0}
	.nura-guidelink li{margin:.2rem 0}
	.nura-guidelink a{color:#0e0e0e;text-decoration:none;font-size:.92rem;line-height:1.45}
	.nura-guidelink a strong{font-weight:600;text-decoration:underline;text-decoration-color:rgba(201,162,75,.7);text-underline-offset:3px}
	.nura-guidelink a:hover,.nura-guidelink a:hover strong{color:#7a5a12}
	</style>
	<?php
}, 34 );
