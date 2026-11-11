<?php
/**
 * Product AI visibility (theme 1.126.0): makes every product - today's and any added
 * later - easy for Google AI, ChatGPT, Perplexity, Copilot and Gemini to understand,
 * match and recommend. Nothing here is typed per product; it all reads the product.
 *
 * 1. Brand: supplier brands (Luodais, Ghost Bond, CSCR, ASTYLSST, Asstylist, IKT ...)
 *    are detected from the product name and win over the "NURA Beauty" default. On save
 *    (and once for existing products) the matching WooCommerce Brands term is set, so new
 *    products get the right brand automatically. Add brands with the nura_supplier_brands
 *    filter, or tick a brand under Products > Brands (any non-NURA term always wins).
 * 2. Product schema: brand corrected on Product, ProductGroup and every variant; the
 *    description uses the full product description when the existing one is short.
 * 3. Product questions: wigs and hair keep "Questions about this wig"; every other
 *    product (wig care, beauty, tools) gets its own questions built from its name,
 *    brand, description, live price and options, the business facts and the till -
 *    shown on the page and in the matching FAQPage schema. Before this, wig questions
 *    ("Is it human hair? No, fibre") were shown on wig-care products.
 * 4. /llms.txt and /llms-full.txt: a "Products" section listing every catalogue
 *    product with brand, price, link and a one-line summary (refreshed on product save).
 *
 * Off switches: nura_pai_brand, nura_pai_schema, nura_pai_questions, nura_pai_llms
 * (add_filter( 'nura_pai_llms', '__return_false' ) etc.).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ helpers */

function nura_pai_on( $k ) {
	return (bool) apply_filters( 'nura_pai_' . $k, true );
}

function nura_pai_supplier_brands() {
	return (array) apply_filters( 'nura_supplier_brands', array( 'Luodais', 'Ghost Bond', 'CSCR', 'ASTYLSST', 'Asstylist', 'IKT' ) );
}

function nura_pai_is_nura( $name ) {
	return in_array( strtolower( trim( (string) $name ) ), array( 'nura beauty', 'nura', '' ), true );
}

/** Supplier brand found at the start of a product name, or ''. */
function nura_pai_detect_brand( $name ) {
	$name = trim( wp_strip_all_tags( (string) $name ) );
	foreach ( nura_pai_supplier_brands() as $b ) {
		$b = trim( (string) $b );
		if ( '' !== $b && preg_match( '/^' . preg_quote( $b, '/' ) . '\b/i', $name ) ) {
			return $b;
		}
	}
	return '';
}

function nura_pai_product( $product ) {
	if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
		$p = $product;
	} else {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product ) : null;
	}
	if ( $p && $p->is_type( 'variation' ) ) {
		$p = wc_get_product( $p->get_parent_id() );
	}
	return $p ? $p : null;
}

/** Plain-text description: full description, else short description. */
function nura_pai_text( $p ) {
	$raw = (string) $p->get_description();
	if ( '' === trim( wp_strip_all_tags( $raw ) ) ) {
		$raw = (string) $p->get_short_description();
	}
	$t = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $raw ) ), ENT_QUOTES, 'UTF-8' );
	return trim( (string) preg_replace( '/\s+/u', ' ', $t ) );
}

function nura_pai_sentences( $text ) {
	$s = preg_split( '/(?<=[.!?])\s+(?=[A-Z0-9])/u', (string) $text );
	return array_values( array_filter( array_map( 'trim', (array) $s ) ) );
}

function nura_pai_cap( $text, $max ) {
	$text = trim( (string) $text );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
		$cut  = mb_substr( $text, 0, $max );
		$pos  = strrpos( $cut, ' ' );
		$text = rtrim( false !== $pos ? substr( $cut, 0, $pos ) : $cut, " ,;:-" ) . '...';
	}
	return $text;
}

function nura_pai_price( $p ) {
	if ( function_exists( 'nura_geo_price' ) ) {
		return (string) nura_geo_price( $p );
	}
	$v = (float) wc_get_price_to_display( $p );
	return $v > 0 ? 'KSh ' . number_format( $v ) : '';
}

/** Wigs and hair (closures, frontals, bundles) vs everything else. */
function nura_pai_is_hair( $p ) {
	$p = nura_pai_product( $p );
	if ( ! $p ) {
		return false;
	}
	$slugs = array();
	foreach ( (array) $p->get_category_ids() as $cid ) {
		foreach ( array_merge( array( (int) $cid ), get_ancestors( (int) $cid, 'product_cat' ) ) as $tid ) {
			$t = get_term( $tid, 'product_cat' );
			if ( $t && ! is_wp_error( $t ) ) {
				$slugs[] = $t->slug;
			}
		}
	}
	$care = (array) apply_filters( 'nura_pai_non_hair_cats', array( 'wig-care', 'beauty', 'accessories', 'tools' ) );
	if ( array_intersect( $care, $slugs ) ) {
		$hair = false;
	} elseif ( '' !== trim( (string) $p->get_attribute( 'pa_hair-type' ) ) || '' !== trim( (string) $p->get_attribute( 'hair-type' ) ) ) {
		$hair = true;
	} elseif ( preg_grep( '/wig|hair-extension|closure|frontal|bundle|weave/', $slugs ) ) {
		$hair = true;
	} else {
		$n    = strtolower( $p->get_name() );
		$hair = (bool) preg_match( '/\b(wig|closure|frontal|bundles?|bob|headband)\b/', $n )
			&& ! preg_match( '/\b(serum|spray|gel|glue|remover|wax|mousse|brush|comb|conditioner|shampoo|oil|cream|tint|tape|cap|stand|band)\b/', $n );
	}
	return (bool) apply_filters( 'nura_pai_is_hair', $hair, $p );
}

/* ---------------------------------------------------------------- 1. brand */

add_filter( 'nura_product_brand', function ( $name, $id ) {
	if ( ! nura_pai_on( 'brand' ) || ! nura_pai_is_nura( $name ) ) {
		return $name;
	}
	$p = nura_pai_product( $id );
	$b = $p ? nura_pai_detect_brand( $p->get_name() ) : '';
	return '' !== $b ? $b : $name;
}, 20, 2 );

function nura_pai_sync_brand_term( $id ) {
	if ( ! nura_pai_on( 'brand' ) || ! taxonomy_exists( 'product_brand' ) ) {
		return;
	}
	$b = nura_pai_detect_brand( get_the_title( $id ) );
	if ( '' === $b ) {
		return;
	}
	$names = wp_get_object_terms( $id, 'product_brand', array( 'fields' => 'names' ) );
	if ( is_wp_error( $names ) ) {
		return;
	}
	foreach ( $names as $n ) {
		if ( ! nura_pai_is_nura( $n ) ) {
			return; // The owner chose a brand by hand: keep it.
		}
	}
	$t = term_exists( $b, 'product_brand' );
	if ( ! $t ) {
		$t = wp_insert_term( $b, 'product_brand' );
	}
	if ( is_wp_error( $t ) || empty( $t['term_id'] ) ) {
		return;
	}
	wp_set_object_terms( $id, array( (int) $t['term_id'] ), 'product_brand', false );
}

add_action( 'save_post_product', function ( $id ) {
	if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) {
		return;
	}
	nura_pai_sync_brand_term( $id );
}, 30 );

add_action( 'admin_init', function () {
	if ( get_option( 'nura_pai_brands_v1' ) || ! taxonomy_exists( 'product_brand' ) || ! current_user_can( 'edit_products' ) ) {
		return;
	}
	foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		nura_pai_sync_brand_term( (int) $id );
	}
	update_option( 'nura_pai_brands_v1', time(), false );
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );


/* ------------------------------------------------ 1b. alternate (old) names */

/**
 * 1.129.3: product titles were shortened (11 Nov 2026). Shoppers and AI assistants
 * still search the long supplier names, so keep them as schema alternateName,
 * in llms.txt and in the "Where can I buy" answer. Filter: nura_pai_alt_names.
 */
function nura_pai_alt_names( $id ) {
	$map = (array) apply_filters(
		'nura_pai_alt_names',
		array(
			2146 => array( 'Luodais Human Hair Wig and Weave Repair Serum' ),
			2144 => array( 'Luodais Classic Curl Keeper and Repair Serum' ),
			2145 => array( 'Luodais Curl Keeper Smooth Shine Conditioner', 'Luodais No5 Noir Shine Styling' ),
			2147 => array( 'IKT Hair Wax Stick, Strong Hold Styling Wax' ),
			2139 => array( 'Ghost Bond XL Wig Fixing Glue', 'Ghost Bond XL lace wig glue 38 ml' ),
			2138 => array( 'Lace Wig Glue and Tape Remover', 'lace wig bond and toupee tape remover' ),
			2131 => array( 'CSCR Professional Lace Melting Spray' ),
			2152 => array( 'CSCR Lace Tint Spray, Quick Drying, Natural Look' ),
			2133 => array( 'ASTYLSST Elastic and Fluffing Hair Mousse' ),
			2148 => array( 'Asstylist Hair Gel for Laying Edges' ),
			2149 => array( 'ASTYLSST Texture Setting Spray' ),
			2132 => array( 'Nylon Bristle Detangling Hair Brush' ),
		)
	);
	return isset( $map[ (int) $id ] ) ? array_values( array_filter( (array) $map[ (int) $id ] ) ) : array();
}

/* --------------------------------------------------------- 2. product schema */

function nura_pai_schema_walk( &$node, $brand ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( ! empty( $node['@type'] ) && array_intersect( (array) $node['@type'], array( 'Product', 'ProductGroup' ) ) ) {
		$node['brand'] = array( '@type' => 'Brand', 'name' => $brand );
	}
	foreach ( $node as &$child ) {
		if ( is_array( $child ) ) {
			nura_pai_schema_walk( $child, $brand );
		}
	}
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! nura_pai_on( 'schema' ) || ! is_array( $data ) || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $data;
	}
	$p = nura_pai_product( get_queried_object_id() );
	if ( ! $p ) {
		return $data;
	}
	$brand = function_exists( 'nura_product_brand' ) ? nura_product_brand( $p ) : 'NURA Beauty';
	$full  = nura_pai_cap( nura_pai_text( $p ), 1500 );
	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) || ! array_intersect( (array) $node['@type'], array( 'Product', 'ProductGroup' ) ) ) {
			continue;
		}
		nura_pai_schema_walk( $data[ $key ], $brand );
		$alts = nura_pai_alt_names( $p->get_id() );
		if ( $alts && empty( $data[ $key ]['alternateName'] ) ) {
			$data[ $key ]['alternateName'] = $alts; // 1.129.3.
		}
		$cur = isset( $node['description'] ) ? wp_strip_all_tags( (string) $node['description'] ) : '';
		if ( '' !== $full && strlen( $cur ) < 200 && strlen( $full ) > strlen( $cur ) ) {
			$data[ $key ]['description'] = $full;
		}
	}
	return $data;
}, 1001 );

/* ------------------------------------------------------ 3. product questions */

/** Questions for non-hair products (wig care, beauty, tools). Used by nura_pq_items(). */
function nura_pai_items( $p ) {
	$p = nura_pai_product( $p );
	if ( ! $p || ! nura_pai_on( 'questions' ) ) {
		return array();
	}
	$name   = wp_strip_all_tags( $p->get_name() );
	$brand  = function_exists( 'nura_product_brand' ) ? nura_product_brand( $p ) : '';
	$price  = nura_pai_price( $p );
	$sent   = nura_pai_sentences( nura_pai_text( $p ) );
	$fact   = function ( $k, $d ) {
		return function_exists( 'nura_fact' ) ? (string) nura_fact( $k, $d ) : $d;
	};
	$addr   = $fact( 'address_short', 'Stanbank House, 2nd Floor, Room 20, Nairobi CBD' );
	$cutoff = $fact( 'cutoff', '5 PM' );
	$till   = $fact( 'till', '1679269' );
	$c      = function_exists( 'nura_seo_brand_contact' ) ? nura_seo_brand_contact() : array();
	$phone  = ! empty( $c['telephone'] ) ? $c['telephone'] : '+254 714 994 898';
	$q      = array();

	if ( $sent ) {
		$q[] = array( sprintf( __( 'What is the %s?', 'nura-beauty' ), $name ), nura_pai_cap( implode( ' ', array_slice( $sent, 0, 2 ) ), 420 ) );
		$use = array();
		foreach ( array_slice( $sent, 2 ) as $s ) {
			if ( preg_match( '/\b(apply|spray|massage|comb|brush|rinse|shake|leave it|work it|use it|use on|use before|use after|wipe|hold)\b/i', $s ) ) {
				$use[] = $s;
			}
			if ( count( $use ) >= 2 ) {
				break;
			}
		}
		if ( $use ) {
			$q[] = array( sprintf( __( 'How do I use the %s?', 'nura-beauty' ), $name ), nura_pai_cap( implode( ' ', $use ), 420 ) );
		}
	}
	$alts = nura_pai_alt_names( $p->get_id() );
	$q[]  = array(
		sprintf( __( 'Where can I buy the %s in Kenya?', 'nura-beauty' ), $alts ? $alts[0] : $name ),
		sprintf( __( 'NURA Beauty sells the %1$s%2$s online at nurabeauty.co.ke, on WhatsApp (%3$s) and at its Nairobi studio, %4$s. Delivery is same day in Nairobi for orders before %5$s, with same-day dispatch countrywide once paid.', 'nura-beauty' ), $name, $price ? ' for ' . $price : '', $phone, $addr, $cutoff ),
	);
	if ( $price ) {
		$opts = '';
		if ( $p->is_type( 'variable' ) ) {
			$vals = array();
			foreach ( (array) $p->get_variation_attributes() as $attr => $list ) {
				foreach ( (array) $list as $v ) {
					$term   = taxonomy_exists( $attr ) ? get_term_by( 'slug', $v, $attr ) : null;
					$vals[] = $term ? $term->name : $v;
				}
			}
			if ( $vals ) {
				$opts = ' ' . sprintf( __( 'Available options: %s.', 'nura-beauty' ), implode( ', ', array_unique( $vals ) ) );
			}
		}
		$q[] = array( sprintf( __( 'How much is the %s in Kenya?', 'nura-beauty' ), $name ), sprintf( __( '%s at NURA Beauty, in Kenya Shillings.', 'nura-beauty' ), $price ) . $opts );
	}
	if ( $brand && ! nura_pai_is_nura( $brand ) ) {
		$q[] = array( sprintf( __( 'Which brand makes the %s?', 'nura-beauty' ), $name ), sprintf( __( 'It is made by %s. NURA Beauty stocks it in Nairobi and delivers across Kenya.', 'nura-beauty' ), $brand ) );
	}
	$q[] = array(
		__( 'How do I pay?', 'nura-beauty' ),
		sprintf( __( 'Pay by M-Pesa or card at checkout, or order on WhatsApp and pay to M-Pesa Buy Goods Till %s (the M-Pesa message shows NURA CROWN & BEAUTY). Pay on delivery (cash or M-Pesa) is available within Nairobi CBD only.', 'nura-beauty' ), $till ),
	);
	return (array) apply_filters( 'nura_pai_items', $q, $p );
}

/* ------------------------------------------------------------- 4. llms.txt */

add_filter( 'nura_llms_extra', function ( $lines, $full = false ) {
	if ( ! nura_pai_on( 'llms' ) || ! function_exists( 'wc_get_products' ) ) {
		return $lines;
	}
	$lines   = (array) $lines;
	$lines[] = '## Products';
	$lines[] = 'Every product NURA Beauty sells, with brand, price in Kenya Shillings and a short summary. Same-day delivery in Nairobi; same-day dispatch countrywide.';
	foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'visibility' => 'catalog' ) ) as $p ) {
		$brand = function_exists( 'nura_product_brand' ) ? nura_product_brand( $p ) : 'NURA Beauty';
		$sent  = nura_pai_sentences( nura_pai_text( $p ) );
		$sum   = $sent ? nura_pai_cap( implode( ' ', array_slice( $sent, 0, $full ? 2 : 1 ) ), $full ? 320 : 180 ) : '';
		$price = nura_pai_price( $p );
		$aka     = nura_pai_alt_names( $p->get_id() );
		$lines[] = '- [' . wp_strip_all_tags( $p->get_name() ) . '](' . get_permalink( $p->get_id() ) . ')' . ( $aka ? ' (also listed as: ' . implode( '; ', $aka ) . ')' : '' ) . ' - Brand: ' . $brand . ( $price ? ' - ' . $price : '' ) . ( $sum ? ' - ' . $sum : '' );
	}
	$lines[] = '';
	return $lines;
}, 50, 2 );
