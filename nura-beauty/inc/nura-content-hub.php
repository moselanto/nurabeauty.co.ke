<?php
/**
 * NURA content hub (theme 1.66.0): pages that answer what Kenyans search for.
 *
 * 1. [nura_price_table] - live price table from the shop, grouped by hair type
 *    (add list="1" for every wig, hair="human|blend|fibre", cat="slug").
 * 2. Live price table under each collection's buying guide.
 * 3. "Good to know" box on every wig: who it's for, hair type, length, lasts about, care.
 * 4. One-time import of 12 buying guides (content/guides/*.html) and the
 *    "Wig prices in Kenya" page. Existing slugs are never overwritten.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** human | blend | fibre | '' for a product, from its hair-type attribute or name. */
function nura_hub_hair( $p ) {
	$t = strtolower( trim( (string) $p->get_attribute( 'pa_hair-type' ) ) . ' ' . $p->get_name() );
	if ( false !== strpos( $t, 'blend' ) || false !== strpos( $t, 'semi' ) ) {
		return 'blend';
	}
	if ( false !== strpos( $t, 'human' ) ) {
		return 'human';
	}
	if ( false !== strpos( $t, 'fibre' ) || false !== strpos( $t, 'fiber' ) || false !== strpos( $t, 'synthetic' ) ) {
		return 'fibre';
	}
	return '';
}

function nura_hub_labels() {
	return array(
		'human' => __( '100% human hair', 'nura-beauty' ),
		'blend' => __( 'Human-hair blend (semi-human)', 'nura-beauty' ),
		'fibre' => __( 'Heat-resistant fibre', 'nura-beauty' ),
	);
}

/** Cached rows: id, name, url, hair, lo, hi, stock, cats. */
function nura_hub_rows() {
	$rows = get_transient( 'nura_hub_rows' );
	if ( false !== $rows ) {
		return $rows;
	}
	$rows = array();
	if ( function_exists( 'wc_get_products' ) ) {
		foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => 400, 'visibility' => 'catalog' ) ) as $p ) {
			$lo = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) $p->get_price();
			$hi = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'max', true ) : $lo;
			if ( $lo <= 0 ) {
				continue;
			}
			$rows[] = array(
				'id'    => $p->get_id(),
				'name'  => wp_strip_all_tags( $p->get_name() ),
				'url'   => get_permalink( $p->get_id() ),
				'hair'  => nura_hub_hair( $p ),
				'lo'    => $lo,
				'hi'    => $hi,
				'stock' => $p->is_in_stock(),
				'cats'  => wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'slugs' ) ),
			);
		}
	}
	set_transient( 'nura_hub_rows', $rows, 6 * HOUR_IN_SECONDS );
	return $rows;
}
add_action( 'save_post_product', function () {
	delete_transient( 'nura_hub_rows' );
} );

function nura_hub_ksh( $n ) {
	return 'KSh ' . number_format( (float) $n );
}

/** [nura_price_table hair="" cat="" list="0"] */
function nura_hub_price_table( $atts = array() ) {
	$a    = shortcode_atts( array( 'hair' => '', 'cat' => '', 'list' => '0' ), $atts, 'nura_price_table' );
	$rows = array_filter( nura_hub_rows(), function ( $r ) use ( $a ) {
		if ( '' !== $a['hair'] && $r['hair'] !== $a['hair'] ) {
			return false;
		}
		return '' === $a['cat'] || ( is_array( $r['cats'] ) && in_array( $a['cat'], $r['cats'], true ) );
	} );
	if ( ! $rows ) {
		return '';
	}
	$labels = nura_hub_labels();
	$out    = '<div class="nura-price-table"><table><thead><tr><th>' . esc_html__( 'Hair type', 'nura-beauty' ) . '</th><th>' . esc_html__( 'From', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Up to', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Styles', 'nura-beauty' ) . '</th></tr></thead><tbody>';
	foreach ( $labels as $k => $label ) {
		$g = array_filter( $rows, function ( $r ) use ( $k ) {
			return $r['hair'] === $k;
		} );
		if ( ! $g ) {
			continue;
		}
		$out .= '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( nura_hub_ksh( min( wp_list_pluck( $g, 'lo' ) ) ) ) . '</td><td>' . esc_html( nura_hub_ksh( max( wp_list_pluck( $g, 'hi' ) ) ) ) . '</td><td>' . count( $g ) . '</td></tr>';
	}
	$out .= '</tbody></table>';
	if ( '1' === (string) $a['list'] ) {
		usort( $rows, function ( $x, $y ) {
			return $x['lo'] <=> $y['lo'];
		} );
		$out .= '<table class="nura-price-table__list"><thead><tr><th>' . esc_html__( 'Wig', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Hair type', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Price', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Stock', 'nura-beauty' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$price = $r['hi'] > $r['lo'] ? nura_hub_ksh( $r['lo'] ) . ' - ' . number_format( $r['hi'] ) : nura_hub_ksh( $r['lo'] );
			$out  .= '<tr><td><a href="' . esc_url( $r['url'] ) . '">' . esc_html( $r['name'] ) . '</a></td><td>' . esc_html( isset( $labels[ $r['hair'] ] ) ? $labels[ $r['hair'] ] : '-' ) . '</td><td>' . esc_html( $price ) . '</td><td>' . esc_html( $r['stock'] ? __( 'In stock', 'nura-beauty' ) : __( 'Out of stock', 'nura-beauty' ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
	}
	$out .= '<p class="nura-price-table__note">' . esc_html( sprintf( __( 'Live prices from our shop in Kenya Shillings, updated %s.', 'nura-beauty' ), wp_date( 'j F Y' ) ) ) . '</p></div>';
	return $out;
}
add_shortcode( 'nura_price_table', 'nura_hub_price_table' );

/** Live price table under each collection's buying guide. */
add_action( 'woocommerce_after_shop_loop', function () {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	$term  = get_queried_object();
	$table = $term ? nura_hub_price_table( array( 'cat' => $term->slug ) ) : '';
	if ( $table ) {
		echo '<section class="nura-cat-prices"><h2>' . esc_html( sprintf( __( '%s prices in Kenya', 'nura-beauty' ), $term->name ) ) . '</h2>' . $table . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaping above.
	}
}, 12 );

/** "Good to know" box on wig product pages. */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product || ! apply_filters( 'nura_good_to_know', true, $product ) ) {
		return;
	}
	$hair = nura_hub_hair( $product );
	if ( '' === $hair ) {
		return;
	}
	$name = strtolower( $product->get_name() );
	$who  = __( 'Everyday wear with a natural look.', 'nura-beauty' );
	if ( false !== strpos( $name, 'headband' ) ) {
		$who = __( 'Beginners and busy days: no glue, on in minutes.', 'nura-beauty' );
	} elseif ( false !== strpos( $name, 'closure' ) ) {
		$who = __( 'A natural parting with little lace to manage; great first lace wig.', 'nura-beauty' );
	} elseif ( false !== strpos( $name, 'lace front' ) || false !== strpos( $name, 'frontal' ) ) {
		$who = __( 'The most natural hairline; ideal with a studio fitting.', 'nura-beauty' );
	} elseif ( false !== strpos( $name, 'bob' ) ) {
		$who = __( 'A light, easy everyday style.', 'nura-beauty' );
	}
	$life = array(
		'human' => __( 'About a year or more of regular wear with good care.', 'nura-beauty' ),
		'blend' => __( 'About 6 to 9 months of regular wear with good care.', 'nura-beauty' ),
		'fibre' => __( 'About 3 to 6 months of regular wear with good care.', 'nura-beauty' ),
	);
	$care = array(
		'human' => __( 'Sulphate-free shampoo every 6-8 weeks, condition, heat protectant before styling.', 'nura-beauty' ),
		'blend' => __( 'Cool wash, gentle detangling from the ends, low to medium heat only.', 'nura-beauty' ),
		'fibre' => __( 'Cool wash when needed, air dry on a stand, low heat only.', 'nura-beauty' ),
	);
	$length = trim( (string) $product->get_attribute( 'pa_length' ) );
	if ( '' === $length && preg_match( '/(\d{1,2})\s*(inch|")/i', $product->get_name(), $m ) ) {
		$length = $m[1] . ' inch';
	}
	$labels = nura_hub_labels();
	$rows   = array(
		__( 'Who it\'s for', 'nura-beauty' ) => $who,
		__( 'Hair type', 'nura-beauty' )    => $labels[ $hair ],
		__( 'Length', 'nura-beauty' )       => $length,
		__( 'Lasts about', 'nura-beauty' )  => $life[ $hair ],
		__( 'Care', 'nura-beauty' )         => $care[ $hair ],
	);
	echo '<div class="nura-good-to-know"><p class="nura-good-to-know__title">' . esc_html__( 'Good to know', 'nura-beauty' ) . '</p><dl>';
	foreach ( $rows as $k => $v ) {
		if ( '' !== $v ) {
			echo '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd>';
		}
	}
	echo '</dl></div>';
}, 27 );

add_action( 'wp_head', function () {
	echo '<style id="nura-hub-css">.nura-price-table{overflow-x:auto;margin:1.2em 0}.nura-price-table table{width:100%;border-collapse:collapse;font-size:.95em}.nura-price-table th,.nura-price-table td{border-bottom:1px solid #eee;padding:.55em .6em;text-align:left}.nura-price-table th{background:#faf7f0}.nura-price-table__list{margin-top:1em}.nura-price-table__note{font-size:.85em;color:#777}.nura-cat-prices{margin:2em 0}.nura-good-to-know{border:1px solid #eadfc6;border-radius:10px;padding:.9em 1.1em;margin:1em 0;background:#fffdf8}.nura-good-to-know__title{font-weight:700;margin:0 0 .4em}.nura-good-to-know dl{display:grid;grid-template-columns:auto 1fr;gap:.3em .9em;margin:0;font-size:.92em}.nura-good-to-know dt{font-weight:600}.nura-good-to-know dd{margin:0}.nura-answer{font-size:1.05em;background:#faf7f0;border-left:3px solid #c9a24b;padding:.8em 1em}</style>' . "\n";
}, 20 );

/* ------------------------------------------------------- one-time import */

/** Replace {shop} {wa} {cat:slug} {page:slug} {post:slug} with real links. */
function nura_hub_links( $html ) {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$wa   = function_exists( 'nura_opt' ) && nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
	$html = str_replace( array( '{shop}', '{wa}' ), array( esc_url( $shop ), esc_url( $wa ) ), $html );
	return preg_replace_callback( '/\{(cat|page|post):([a-z0-9\-]+)\}/', function ( $m ) use ( $shop ) {
		if ( 'cat' === $m[1] ) {
			$t = get_term_by( 'slug', $m[2], 'product_cat' );
			return esc_url( $t && ! is_wp_error( $t ) ? get_term_link( $t ) : $shop );
		}
		$p = get_page_by_path( $m[2], OBJECT, 'post' === $m[1] ? 'post' : 'page' );
		if ( 'page' === $m[1] && 'lipa-mdogo-mdogo' === $m[2] && ! $p ) {
			$p = get_page_by_path( 'lipa-mdogo-mdogo-terms' );
		}
		return esc_url( $p && 'publish' === $p->post_status ? get_permalink( $p ) : $shop );
	}, $html );
}

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.66.0' === get_option( 'nura_hub_import' ) || ! defined( 'NURA_DIR' ) ) {
		return;
	}
	update_option( 'nura_hub_import', '1.66.0', false );
	$dir   = NURA_DIR . 'content/guides/';
	$index = file_exists( $dir . 'index.json' ) ? json_decode( (string) file_get_contents( $dir . 'index.json' ), true ) : array();
	$ref   = get_page_by_path( 'semi-human-wigs-kenya-guide', OBJECT, 'post' );
	$made  = array();
	foreach ( (array) $index as $slug => $meta ) {
		if ( get_page_by_path( $slug, OBJECT, 'post' ) || ! file_exists( $dir . $slug . '.html' ) ) {
			continue;
		}
		$args = array(
			'post_type'    => 'post',
			'post_name'    => $slug,
			'post_title'   => $meta[0],
			'post_content' => (string) file_get_contents( $dir . $slug . '.html' ),
			'post_status'  => 'publish',
		);
		if ( $ref ) {
			$args['post_category'] = wp_get_post_categories( $ref->ID );
			$args['post_author']   = (int) $ref->post_author;
		}
		$id = wp_insert_post( $args, true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', $meta[1] );
			update_post_meta( $id, 'rank_math_description', $meta[2] );
			update_post_meta( $id, 'rank_math_focus_keyword', $meta[3] );
			$made[] = $id;
		}
	}
	if ( ! get_page_by_path( 'wig-prices-kenya' ) ) {
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => 'wig-prices-kenya',
			'post_title'   => 'Wig Prices in Kenya',
			'post_status'  => 'publish',
			'post_content' => '<p class="nura-answer"><strong>Short answer:</strong> at NURA, heat-resistant fibre wigs are the most affordable, human-hair blend (semi-human) wigs sit in the middle, and 100% human hair wigs cost the most, rising with length. The table below is pulled live from our shop, so the prices are always current.</p>[nura_price_table list="1"]<h2>What affects the price of a wig?</h2><ul><li><strong>Hair type:</strong> human hair costs more than blend, and blend more than fibre.</li><li><strong>Length:</strong> longer wigs use more hair.</li><li><strong>Lace:</strong> lace front units cost more than closure or headband wigs.</li></ul><p>Pay by M-Pesa, card or cash on delivery in Nairobi, or spread the cost with <a href="{page:lipa-mdogo-mdogo}">Lipa Mdogo Mdogo</a> (50% deposit, balance within 1 or 2 weeks). Read <a href="{post:human-hair-vs-semi-human-vs-fibre-wigs-kenya}">human hair vs semi-human vs fibre</a>.</p>',
		), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', 'Wig Prices in Kenya ' . wp_date( 'Y' ) . ': Live Price List | NURA Beauty' );
			update_post_meta( $id, 'rank_math_description', 'Current wig prices in Kenya: human hair, semi-human and fibre wigs from NURA, updated live from our shop. Same-day Nairobi delivery, M-Pesa and Lipa Mdogo Mdogo.' );
			update_post_meta( $id, 'rank_math_focus_keyword', 'wig prices in kenya' );
			$made[] = $id;
		}
	}
	// Resolve links now that every guide exists.
	foreach ( $made as $id ) {
		$p = get_post( $id );
		wp_update_post( array( 'ID' => $id, 'post_content' => nura_hub_links( $p->post_content ) ) );
	}
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );
