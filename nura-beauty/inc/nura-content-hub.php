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
	$rows = get_transient( 'nura_hub_rows2' );
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
				'img'   => $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '',
				'style' => nura_hub_style( $p->get_name() ),
				'var'   => $p->is_type( 'variable' ),
			);
		}
	}
	set_transient( 'nura_hub_rows2', $rows, 6 * HOUR_IN_SECONDS );
	return $rows;
}
add_action( 'save_post_product', function () {
	delete_transient( 'nura_hub_rows' );
	delete_transient( 'nura_hub_rows2' );
} );

/** Construction from the product name: Headband, Closure, Lace front, Frontal or Wig. */
function nura_hub_style( $name ) {
	$n = strtolower( (string) $name );
	if ( false !== strpos( $n, 'headband' ) ) {
		return __( 'Headband', 'nura-beauty' );
	}
	if ( false !== strpos( $n, '13x4' ) || false !== strpos( $n, 'frontal' ) ) {
		return __( '13x4 frontal', 'nura-beauty' );
	}
	if ( false !== strpos( $n, 'closure' ) ) {
		return __( '4x4 closure', 'nura-beauty' );
	}
	if ( false !== strpos( $n, 'lace front' ) ) {
		return __( 'Lace front', 'nura-beauty' );
	}
	return __( 'Wig', 'nura-beauty' );
}

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

/* ------------------------------------------------ Wig Prices in Kenya page */

/** Full, designed price guide used on /wig-prices-kenya/ (always live). */
function nura_hub_price_page() {
	$rows = nura_hub_rows();
	if ( ! $rows ) {
		return '';
	}
	usort( $rows, function ( $x, $y ) {
		return $x['lo'] <=> $y['lo'];
	} );
	$labels = nura_hub_labels();
	$cats   = array( 'human' => 'human-hair-wigs', 'blend' => 'human-hair-blend-wigs', 'fibre' => 'wigs' );
	$blurb  = array(
		'human' => __( 'Most natural look and movement. Heat-style and colour. Lasts about a year or more.', 'nura-beauty' ),
		'blend' => __( 'Human hair mixed with fibre: softer and more natural than fibre, at a lower price.', 'nura-beauty' ),
		'fibre' => __( 'Most affordable. Holds its style out of the box and is easy to maintain.', 'nura-beauty' ),
	);
	$shop   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$wa     = function_exists( 'nura_opt' ) && nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
	$lmm    = get_page_by_path( 'lipa-mdogo-mdogo' ) ? get_page_by_path( 'lipa-mdogo-mdogo' ) : get_page_by_path( 'lipa-mdogo-mdogo-terms' );
	$guide  = get_page_by_path( 'human-hair-vs-semi-human-vs-fibre-wigs-kenya', OBJECT, 'post' );
	$lo_all = min( wp_list_pluck( $rows, 'lo' ) );
	$hi_all = max( wp_list_pluck( $rows, 'hi' ) );
	$today  = wp_date( 'j F Y' );
	ob_start();
	?>
	<div class="npg">
		<section class="npg-intro">
			<p class="npg-answer"><strong><?php esc_html_e( 'Short answer:', 'nura-beauty' ); ?></strong> <?php echo esc_html( sprintf( __( 'wigs at NURA cost from %1$s to %2$s. Heat-resistant fibre wigs are the most affordable, human-hair blend (semi-human) wigs sit in the middle, and 100%% human hair wigs cost the most, rising with length.', 'nura-beauty' ), nura_hub_ksh( $lo_all ), nura_hub_ksh( $hi_all ) ) ); ?></p>
			<p class="npg-updated"><span class="npg-dot" aria-hidden="true"></span><?php echo esc_html( sprintf( __( 'Live from our shop - %1$d wigs - updated %2$s', 'nura-beauty' ), count( $rows ), $today ) ); ?></p>
		</section>

		<section class="npg-tiers" aria-label="<?php esc_attr_e( 'Prices by hair type', 'nura-beauty' ); ?>">
			<?php foreach ( array( 'fibre', 'blend', 'human' ) as $k ) :
				$g = array_filter( $rows, function ( $r ) use ( $k ) {
					return $r['hair'] === $k;
				} );
				if ( ! $g ) {
					continue;
				}
				$t    = get_term_by( 'slug', $cats[ $k ], 'product_cat' );
				$link = $t && ! is_wp_error( $t ) ? get_term_link( $t ) : $shop;
				?>
				<article class="npg-tier npg-tier--<?php echo esc_attr( $k ); ?>">
					<p class="npg-tier__label"><?php echo esc_html( $labels[ $k ] ); ?></p>
					<p class="npg-tier__price"><span><?php esc_html_e( 'From', 'nura-beauty' ); ?></span> <?php echo esc_html( nura_hub_ksh( min( wp_list_pluck( $g, 'lo' ) ) ) ); ?></p>
					<p class="npg-tier__range"><?php echo esc_html( sprintf( __( 'Up to %1$s - %2$d styles', 'nura-beauty' ), nura_hub_ksh( max( wp_list_pluck( $g, 'hi' ) ) ), count( $g ) ) ); ?></p>
					<p class="npg-tier__text"><?php echo esc_html( $blurb[ $k ] ); ?></p>
					<a class="npg-tier__link" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Shop this range', 'nura-beauty' ); ?> &rarr;</a>
				</article>
			<?php endforeach; ?>
		</section>

		<ul class="npg-facts">
			<li><strong><?php esc_html_e( 'Same-day Nairobi', 'nura-beauty' ); ?></strong><span><?php esc_html_e( 'Order before 5pm. 1-3 days countrywide.', 'nura-beauty' ); ?></span></li>
			<li><strong><?php esc_html_e( 'M-Pesa, card or cash', 'nura-beauty' ); ?></strong><span><?php esc_html_e( 'Cash on delivery within Nairobi.', 'nura-beauty' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Lipa Mdogo Mdogo', 'nura-beauty' ); ?></strong><span><?php esc_html_e( '50% deposit, balance within 1-2 weeks.', 'nura-beauty' ); ?></span></li>
			<li><strong><?php esc_html_e( '7-day returns', 'nura-beauty' ); ?></strong><span><?php esc_html_e( 'Unworn wigs, at the studio or by courier.', 'nura-beauty' ); ?></span></li>
		</ul>

		<section class="npg-list" id="price-list" aria-labelledby="npg-list-h">
			<div class="npg-list__head">
				<h2 id="npg-list-h"><?php esc_html_e( 'Full price list', 'nura-beauty' ); ?></h2>
				<div class="npg-tools">
					<div class="npg-chips" role="group" aria-label="<?php esc_attr_e( 'Filter by hair type', 'nura-beauty' ); ?>">
						<button type="button" class="is-on" data-npg-f="all" aria-pressed="true"><?php esc_html_e( 'All', 'nura-beauty' ); ?></button>
						<?php foreach ( array( 'fibre', 'blend', 'human' ) as $k ) : ?>
							<button type="button" data-npg-f="<?php echo esc_attr( $k ); ?>" aria-pressed="false"><?php echo esc_html( 'human' === $k ? __( 'Human hair', 'nura-beauty' ) : ( 'blend' === $k ? __( 'Semi-human', 'nura-beauty' ) : __( 'Fibre', 'nura-beauty' ) ) ); ?></button>
						<?php endforeach; ?>
					</div>
					<label class="npg-search"><span class="screen-reader-text"><?php esc_html_e( 'Search wigs', 'nura-beauty' ); ?></span><input type="search" placeholder="<?php esc_attr_e( 'Search e.g. bob, jerry curl, 16 inch', 'nura-beauty' ); ?>" data-npg-q></label>
				</div>
			</div>
			<div class="npg-table" role="table" aria-label="<?php esc_attr_e( 'Wig prices', 'nura-beauty' ); ?>">
				<div class="npg-row npg-row--head" role="row">
					<span role="columnheader"><?php esc_html_e( 'Wig', 'nura-beauty' ); ?></span>
					<span role="columnheader"><?php esc_html_e( 'Hair type', 'nura-beauty' ); ?></span>
					<span role="columnheader"><?php esc_html_e( 'Style', 'nura-beauty' ); ?></span>
					<span role="columnheader"><?php esc_html_e( 'Price', 'nura-beauty' ); ?></span>
					<span role="columnheader" class="npg-sr"><?php esc_html_e( 'Shop', 'nura-beauty' ); ?></span>
				</div>
				<?php foreach ( $rows as $r ) :
					$price = $r['hi'] > $r['lo'] ? sprintf( __( 'From %s', 'nura-beauty' ), nura_hub_ksh( $r['lo'] ) ) : nura_hub_ksh( $r['lo'] );
					?>
					<a class="npg-row" role="row" href="<?php echo esc_url( $r['url'] ); ?>" data-hair="<?php echo esc_attr( $r['hair'] ); ?>" data-name="<?php echo esc_attr( strtolower( $r['name'] ) ); ?>">
						<span role="cell" class="npg-wig">
							<?php if ( $r['img'] ) : ?><img src="<?php echo esc_url( $r['img'] ); ?>" alt="" width="56" height="56" loading="lazy" decoding="async"><?php else : ?><span class="npg-noimg" aria-hidden="true"></span><?php endif; ?>
							<span class="npg-name"><?php echo esc_html( $r['name'] ); ?><?php if ( ! $r['stock'] ) : ?> <em class="npg-oos"><?php esc_html_e( 'Out of stock', 'nura-beauty' ); ?></em><?php endif; ?></span>
						</span>
						<span role="cell" class="npg-pill npg-pill--<?php echo esc_attr( $r['hair'] ? $r['hair'] : 'none' ); ?>"><?php echo esc_html( isset( $labels[ $r['hair'] ] ) ? ( 'blend' === $r['hair'] ? __( 'Semi-human', 'nura-beauty' ) : ( 'human' === $r['hair'] ? __( 'Human hair', 'nura-beauty' ) : __( 'Fibre', 'nura-beauty' ) ) ) : '-' ); ?></span>
						<span role="cell" class="npg-style"><?php echo esc_html( $r['style'] ); ?></span>
						<span role="cell" class="npg-price"><?php echo esc_html( $price ); ?></span>
						<span role="cell" class="npg-go" aria-hidden="true">&rarr;</span>
					</a>
				<?php endforeach; ?>
				<p class="npg-empty" hidden><?php esc_html_e( 'No wig matches that search. Try another word or ask us on WhatsApp.', 'nura-beauty' ); ?></p>
			</div>
			<p class="npg-note"><?php echo esc_html( sprintf( __( 'Prices in Kenya Shillings, including VAT where applicable, live from our shop on %s. "From" means the price depends on the colour or length you choose.', 'nura-beauty' ), $today ) ); ?></p>
		</section>

		<section class="npg-why" aria-labelledby="npg-why-h">
			<h2 id="npg-why-h"><?php esc_html_e( 'What affects the price of a wig?', 'nura-beauty' ); ?></h2>
			<div class="npg-why__grid">
				<div><h3><?php esc_html_e( 'Hair type', 'nura-beauty' ); ?></h3><p><?php esc_html_e( 'Human hair costs more than semi-human blend, and blend more than fibre.', 'nura-beauty' ); ?></p></div>
				<div><h3><?php esc_html_e( 'Length', 'nura-beauty' ); ?></h3><p><?php esc_html_e( 'Longer wigs use more hair. A 20-22 inch human hair unit costs about twice a 12-14 inch one.', 'nura-beauty' ); ?></p></div>
				<div><h3><?php esc_html_e( 'Lace and construction', 'nura-beauty' ); ?></h3><p><?php esc_html_e( 'Lace front and 13x4 frontal units cost more than 4x4 closure or headband wigs.', 'nura-beauty' ); ?></p></div>
			</div>
		</section>

		<section class="npg-faq" aria-labelledby="npg-faq-h">
			<h2 id="npg-faq-h"><?php esc_html_e( 'Wig prices: common questions', 'nura-beauty' ); ?></h2>
			<?php foreach ( nura_hub_price_faqs( $lo_all, $hi_all ) as $qa ) : ?>
				<details><summary><?php echo esc_html( $qa[0] ); ?></summary><p><?php echo esc_html( $qa[1] ); ?></p></details>
			<?php endforeach; ?>
		</section>

		<section class="npg-cta">
			<div>
				<h2><?php esc_html_e( 'Not sure which wig fits your budget?', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Tell us your budget and the look you want. A NURA stylist will suggest the best options on WhatsApp.', 'nura-beauty' ); ?></p>
			</div>
			<div class="npg-cta__btns">
				<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $wa ); ?>"><?php esc_html_e( 'Ask a stylist on WhatsApp', 'nura-beauty' ); ?></a>
				<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'Shop all wigs', 'nura-beauty' ); ?></a>
			</div>
			<?php if ( $lmm || $guide ) : ?>
				<p class="npg-cta__more">
					<?php if ( $lmm ) : ?><a href="<?php echo esc_url( get_permalink( $lmm ) ); ?>"><?php esc_html_e( 'How Lipa Mdogo Mdogo works', 'nura-beauty' ); ?></a><?php endif; ?>
					<?php if ( $guide ) : ?><a href="<?php echo esc_url( get_permalink( $guide ) ); ?>"><?php esc_html_e( 'Human hair vs semi-human vs fibre', 'nura-beauty' ); ?></a><?php endif; ?>
				</p>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** FAQs for the price page (visible + FAQPage schema). */
function nura_hub_price_faqs( $lo, $hi ) {
	return array(
		array( __( 'How much is a wig in Kenya?', 'nura-beauty' ), sprintf( __( 'At NURA, wigs cost from %1$s to %2$s. Fibre wigs are the most affordable, semi-human blends are mid-priced, and 100%% human hair wigs cost the most.', 'nura-beauty' ), nura_hub_ksh( $lo ), nura_hub_ksh( $hi ) ) ),
		array( __( 'What is the cheapest wig at NURA?', 'nura-beauty' ), __( 'Heat-resistant fibre wigs, especially headband and 4x4 closure styles, are our most affordable options. The list above is sorted from lowest price.', 'nura-beauty' ) ),
		array( __( 'Can I pay for a wig in instalments?', 'nura-beauty' ), __( 'Yes. With Lipa Mdogo Mdogo you pay a 50% deposit today and the balance within 1 or 2 weeks; the wig is released once fully paid.', 'nura-beauty' ) ),
		array( __( 'Does the price include delivery?', 'nura-beauty' ), __( 'Delivery is added at checkout: same day in Nairobi before 5pm (rider from KSh 300, free in Nairobi over KSh 10,000) and 1 to 3 days countrywide.', 'nura-beauty' ) ),
	);
}

/** Use the designed guide on the Wig Prices in Kenya page. */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'wig-prices-kenya' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$html = nura_hub_price_page();
	return '' !== $html ? $html : $content;
}, 5 );

add_action( 'wp_head', function () {
	if ( ! is_page( 'wig-prices-kenya' ) ) {
		return;
	}
	$rows = nura_hub_rows();
	if ( $rows ) {
		$items = array();
		foreach ( nura_hub_price_faqs( min( wp_list_pluck( $rows, 'lo' ) ), max( wp_list_pluck( $rows, 'hi' ) ) ) as $qa ) {
			$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ) ) . '</script>' . "\n";
	}
	?>
<style id="nura-price-page">
.npg{--g:var(--nura-gold,#C9A24B);--ink:#0E0E0E;--cream:#FAF7F0;--line:#ECE4D3;max-width:1100px;margin:0 auto}
.npg h2{font-size:clamp(1.45rem,2.6vw,2rem);margin:0 0 .6em}
.npg-intro{margin:0 0 2rem}
.npg-answer{font-size:1.08rem;line-height:1.7;background:var(--cream);border-left:3px solid var(--g);padding:1.1rem 1.3rem;border-radius:0 12px 12px 0;margin:0 0 .8rem}
.npg-updated{display:flex;align-items:center;gap:.5rem;font-size:.82rem;letter-spacing:.04em;color:#6b6b6b;margin:0}
.npg-dot{width:8px;height:8px;border-radius:50%;background:#25a35a;box-shadow:0 0 0 4px rgba(37,163,90,.15)}
.npg-tiers{display:grid;grid-template-columns:repeat(3,1fr);gap:1.1rem;margin:0 0 1.6rem}
.npg-tier{position:relative;border:1px solid var(--line);border-radius:16px;padding:1.4rem 1.3rem 1.2rem;background:#fff;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
.npg-tier:hover{transform:translateY(-3px);box-shadow:0 14px 34px rgba(14,14,14,.08)}
.npg-tier--human{background:var(--ink);color:#fff;border-color:var(--ink)}
.npg-tier--human .npg-tier__text,.npg-tier--human .npg-tier__range{color:#d9d4c8}
.npg-tier__label{font-size:.72rem;text-transform:uppercase;letter-spacing:.22em;color:var(--g);margin:0 0 .7rem;font-weight:600}
.npg-tier__price{font-family:var(--font-display,serif);font-size:2rem;line-height:1.1;margin:0}
.npg-tier__price span{font-family:var(--font-ui,sans-serif);font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;opacity:.7}
.npg-tier__range{font-size:.88rem;color:#6b6b6b;margin:.35rem 0 .8rem}
.npg-tier__text{font-size:.92rem;line-height:1.6;color:#444;margin:0 0 1rem;flex:1}
.npg-tier__link{font-weight:600;font-size:.9rem;color:var(--g);text-decoration:none}
.npg-facts{list-style:none;padding:0;margin:0 0 2.6rem;display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem}
.npg-facts li{background:var(--cream);border-radius:12px;padding:.9rem 1rem;display:flex;flex-direction:column;gap:.15rem}
.npg-facts strong{font-size:.92rem}.npg-facts span{font-size:.82rem;color:#666;line-height:1.45}
.npg-list__head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}
.npg-list__head h2{margin:0}
.npg-tools{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.npg-chips{display:flex;flex-wrap:wrap;gap:.4rem}
.npg-chips button{border:1px solid var(--line);background:#fff;border-radius:999px;padding:.45rem .95rem;font-size:.85rem;cursor:pointer;color:var(--ink)}
.npg-chips button.is-on{background:var(--ink);color:#fff;border-color:var(--ink)}
.npg-search input{border:1px solid var(--line);border-radius:999px;padding:.5rem 1rem;font-size:.88rem;min-width:240px;background:#fff}
.npg-table{border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#fff}
.npg-row{display:grid;grid-template-columns:minmax(0,3fr) 1.1fr 1fr 1fr 32px;align-items:center;gap:1rem;padding:.75rem 1.1rem;border-top:1px solid #f1ece0;color:inherit;text-decoration:none;transition:background .15s}
.npg-row:first-child{border-top:0}
a.npg-row:hover{background:var(--cream)}
.npg-row--head{background:var(--cream);font-size:.72rem;text-transform:uppercase;letter-spacing:.16em;color:#6b6b6b;font-weight:600;padding:.8rem 1.1rem}
.npg-wig{display:flex;align-items:center;gap:.85rem;min-width:0}
.npg-wig img,.npg-noimg{width:56px;height:56px;border-radius:10px;object-fit:cover;flex:none;background:var(--cream)}
.npg-name{font-weight:600;font-size:.95rem;line-height:1.35}
.npg-oos{display:inline-block;font-style:normal;font-size:.72rem;font-weight:600;color:#b3261e;background:#fdecea;border-radius:999px;padding:.1rem .5rem;margin-left:.3rem}
.npg-pill{justify-self:start;font-size:.75rem;font-weight:600;border-radius:999px;padding:.25rem .7rem;white-space:nowrap}
.npg-pill--human{background:#0E0E0E;color:#fff}.npg-pill--blend{background:#F3E7C9;color:#6a5216}.npg-pill--fibre{background:#EEF0F2;color:#3b4148}.npg-pill--none{background:#f4f4f4;color:#777}
.npg-style{font-size:.88rem;color:#555}
.npg-price{font-weight:700;white-space:nowrap;font-size:.98rem}
.npg-go{color:var(--g);font-size:1.1rem;text-align:right}
.npg-sr{visibility:hidden}
.npg-empty{padding:1.2rem;margin:0;text-align:center;color:#666}
.npg-note{font-size:.82rem;color:#777;margin:.8rem 0 2.8rem}
.npg-why__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2.8rem}
.npg-why__grid div{border:1px solid var(--line);border-radius:14px;padding:1.2rem}
.npg-why__grid h3{font-size:1.1rem;margin:0 0 .4rem}.npg-why__grid p{margin:0;font-size:.92rem;color:#555;line-height:1.6}
.npg-faq{margin-bottom:2.8rem}
.npg-faq details{border-bottom:1px solid var(--line);padding:1rem 0}
.npg-faq summary{cursor:pointer;font-weight:600;list-style:none;display:flex;justify-content:space-between;gap:1rem}
.npg-faq summary::after{content:'+';color:var(--g);font-size:1.3rem;line-height:1}
.npg-faq details[open] summary::after{content:'\2212'}
.npg-faq details p{margin:.6rem 0 0;color:#555;line-height:1.7}
.npg-cta{background:var(--ink);color:#fff;border-radius:20px;padding:2rem;display:grid;grid-template-columns:1fr auto;gap:1.2rem 2rem;align-items:center;margin-bottom:1rem}
.npg-cta h2{color:#fff;margin:0 0 .4rem}.npg-cta p{margin:0;color:#d9d4c8}
.npg-cta__btns{display:flex;flex-wrap:wrap;gap:.6rem}
.npg-cta .nura-btn--ghost{color:#fff;border-color:rgba(255,255,255,.5)}
.npg-cta__more{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:1.4rem;font-size:.88rem}
.npg-cta__more a{color:var(--g)}
@media (max-width:900px){.npg-tiers{grid-template-columns:1fr}.npg-facts{grid-template-columns:repeat(2,1fr)}.npg-why__grid{grid-template-columns:1fr}.npg-cta{grid-template-columns:1fr}}
@media (max-width:700px){
.npg-row--head{display:none}
.npg-row{grid-template-columns:1fr auto;grid-template-areas:"wig price" "meta go";gap:.4rem .8rem;padding:.9rem 1rem}
.npg-wig{grid-area:wig}.npg-price{grid-area:price;align-self:start}.npg-go{grid-area:go}
.npg-pill{grid-area:meta;margin-left:68px}.npg-style{display:none}
.npg-search input{min-width:0;width:100%}.npg-tools,.npg-search{width:100%}
.npg-tier__price{font-size:1.7rem}}
</style>
<?php
}, 21 );

add_action( 'wp_footer', function () {
	if ( ! is_page( 'wig-prices-kenya' ) ) {
		return;
	}
	?>
<script>
(function(){var w=document.querySelector('.npg');if(!w){return;}var f='all',q='',rows=w.querySelectorAll('a.npg-row'),empty=w.querySelector('.npg-empty');
function run(){var n=0;rows.forEach(function(r){var ok=(f==='all'||r.getAttribute('data-hair')===f)&&(!q||r.getAttribute('data-name').indexOf(q)>-1);r.hidden=!ok;if(ok){n++;}});if(empty){empty.hidden=n>0;}}
w.querySelectorAll('[data-npg-f]').forEach(function(b){b.addEventListener('click',function(){f=b.getAttribute('data-npg-f');w.querySelectorAll('[data-npg-f]').forEach(function(x){var on=x===b;x.classList.toggle('is-on',on);x.setAttribute('aria-pressed',on?'true':'false');});run();});});
var s=w.querySelector('[data-npg-q]');if(s){s.addEventListener('input',function(){q=s.value.trim().toLowerCase();run();});}})();
</script>
<?php
}, 30 );
