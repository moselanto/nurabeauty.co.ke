<?php
/**
 * NURA smart search (v1.12.0).
 *
 * A luxury search overlay with live AJAX product results plus "understanding":
 * the query is matched against the real attribute facets (Texture, Colour, Length,
 * Hair Type, Lace) and product categories, and recognised terms are offered as
 * one-tap links into the faceted shop. So "body wave 18 inch burgundy" surfaces the
 * Body Wave / Long / Burgundy filters, not just a keyword string.
 *
 * No-JS safe: the header trigger and overlay form fall back to a normal WooCommerce
 * product search (?s=...&post_type=product), which search.php renders as a grid.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shop archive URL (no filters).
 */
function nura_search_shop_url() {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$id = wc_get_page_id( 'shop' );
		if ( $id && get_post( $id ) ) {
			return get_permalink( $id );
		}
	}
	return home_url( '/' );
}

/**
 * Recognise attribute terms and categories inside a search query and turn them into
 * deep links to the faceted shop.
 *
 * @param string $q Raw query.
 * @return array[] Each: [ 'label' => string, 'context' => string, 'url' => string ]
 */
function nura_search_recognize( $q ) {
	$q = strtolower( trim( (string) $q ) );
	if ( strlen( $q ) < 2 ) {
		return array();
	}

	$shop        = nura_search_shop_url();
	$suggestions = array();
	$seen        = array();

	// Attribute facets (Texture, Colour, Length, Hair Type, Lace).
	if ( function_exists( 'nura_filter_attributes' ) ) {
		foreach ( nura_filter_attributes() as $tax => $label ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$name = str_replace( 'pa_', '', $tax );
			foreach ( $terms as $term ) {
				$tn = strtolower( $term->name );
				$hit = ( false !== strpos( $q, $tn ) ) || ( strlen( $q ) >= 3 && false !== strpos( $tn, $q ) );
				if ( ! $hit ) {
					continue;
				}
				$key = $tax . ':' . $term->slug;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = 1;
				$suggestions[] = array(
					'label'   => $term->name,
					'context' => $label,
					'url'     => add_query_arg(
						array( 'filter_' . $name => $term->slug, 'query_type_' . $name => 'or' ),
						$shop
					),
				);
			}
		}
	}

	// Length expressed in inches, e.g. "18 inch" / "18\"" -> Length band.
	if ( taxonomy_exists( 'pa_length' ) && preg_match( '/(\d{1,2})\s*(?:inch|inches|in|")/', $q, $m ) ) {
		$inch = (int) $m[1];
		$band = ( $inch <= 12 ) ? 'short' : ( ( $inch <= 16 ) ? 'medium' : 'long' );
		$lterms = get_terms( array( 'taxonomy' => 'pa_length', 'hide_empty' => true ) );
		if ( ! is_wp_error( $lterms ) ) {
			foreach ( $lterms as $t ) {
				if ( 0 === strpos( $t->slug, $band ) ) {
					$key = 'pa_length:' . $t->slug;
					if ( ! isset( $seen[ $key ] ) ) {
						$seen[ $key ] = 1;
						$suggestions[] = array(
							'label'   => $t->name,
							'context' => __( 'Length', 'nura-beauty' ),
							'url'     => add_query_arg(
								array( 'filter_length' => $t->slug, 'query_type_length' => 'or' ),
								$shop
							),
						);
					}
				}
			}
		}
	}

	// Product categories.
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
		foreach ( $cats as $cat ) {
			if ( 'uncategorized' === $cat->slug ) {
				continue;
			}
			$cn = strtolower( $cat->name );
			$hit = ( false !== strpos( $q, $cn ) ) || ( strlen( $q ) >= 4 && false !== strpos( $cn, $q ) );
			if ( ! $hit ) {
				continue;
			}
			$key = 'cat:' . $cat->slug;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$link = get_term_link( $cat );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$seen[ $key ] = 1;
			$suggestions[] = array(
				'label'   => $cat->name,
				'context' => __( 'Category', 'nura-beauty' ),
				'url'     => $link,
			);
		}
	}

	return array_slice( $suggestions, 0, 8 );
}

/**
 * AJAX: live search results (products + recognised facet suggestions).
 */
function nura_ajax_search() {
	check_ajax_referer( 'nura_nonce', 'nonce' );

	$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$out = array(
		'query'       => $q,
		'products'    => array(),
		'suggestions' => array(),
		'viewAllUrl'  => '',
	);

	if ( strlen( $q ) < 2 || ! function_exists( 'wc_get_product' ) ) {
		wp_send_json_success( $out );
	}

	$out['suggestions'] = nura_search_recognize( $q );
	$out['viewAllUrl']  = add_query_arg( array( 's' => rawurlencode( $q ), 'post_type' => 'product' ), home_url( '/' ) );

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		's'              => $q,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'slug',
				'terms'    => array( 'exclude-from-search' ),
				'operator' => 'NOT IN',
			),
		),
	);

	$query = new WP_Query( $args );
	foreach ( $query->posts as $post ) {
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			continue;
		}
		$out['products'][] = array(
			'title'   => $product->get_name(),
			'url'     => get_permalink( $product->get_id() ),
			'price'   => $product->get_price_html(),
			'img'     => get_the_post_thumbnail_url( $product->get_id(), 'woocommerce_thumbnail' ),
			'sku'     => $product->get_sku(),
			'inStock' => $product->is_in_stock(),
		);
	}
	wp_reset_postdata();

	wp_send_json_success( $out );
}
add_action( 'wp_ajax_nura_search', 'nura_ajax_search' );
add_action( 'wp_ajax_nopriv_nura_search', 'nura_ajax_search' );

/**
 * Render the search overlay markup in the footer (hidden until opened).
 */
function nura_render_search_overlay() {
	if ( is_admin() ) {
		return;
	}
	?>
	<div class="nura-search-modal" data-nura-search-modal aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search', 'nura-beauty' ); ?>">
		<div class="nura-search-modal__backdrop" data-nura-search-close></div>
		<div class="nura-search-modal__panel">
			<form role="search" method="get" class="nura-search-modal__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="hidden" name="post_type" value="product">
				<span class="nura-search-modal__icon" aria-hidden="true"></span>
				<label class="screen-reader-text" for="nura-search-input"><?php esc_html_e( 'Search wigs', 'nura-beauty' ); ?></label>
				<input type="search" id="nura-search-input" name="s" autocomplete="off" placeholder="<?php esc_attr_e( 'Search by name, texture, colour, length&hellip;', 'nura-beauty' ); ?>" data-nura-search-input>
				<button type="button" class="nura-search-modal__close" data-nura-search-close aria-label="<?php esc_attr_e( 'Close search', 'nura-beauty' ); ?>">&times;</button>
			</form>
			<div class="nura-search-modal__results" data-nura-search-results aria-live="polite"></div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'nura_render_search_overlay' );
