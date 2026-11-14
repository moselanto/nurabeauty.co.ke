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
			<div class="nura-search-modal__default" data-nura-search-default>
				<div class="nura-sresult__group" data-nura-search-recent hidden>
					<p class="nura-sresult__label"><?php esc_html_e( 'Recent searches', 'nura-beauty' ); ?> <button type="button" class="nura-sresult__clear" data-nura-search-recent-clear><?php esc_html_e( 'Clear', 'nura-beauty' ); ?></button></p>
					<div class="nura-sresult__chips" data-nura-search-recent-list></div>
				</div>
				<?php
				$nura_popular = array(
					__( 'Bob', 'nura-beauty' ),
					__( 'Water curl', 'nura-beauty' ),
					__( 'Headband', 'nura-beauty' ),
					__( '4x4 closure', 'nura-beauty' ),
					__( 'Lace front', 'nura-beauty' ),
					__( 'Body wave', 'nura-beauty' ),
				);
				?>
				<div class="nura-sresult__group">
					<p class="nura-sresult__label"><?php esc_html_e( 'Popular searches', 'nura-beauty' ); ?></p>
					<div class="nura-sresult__chips">
						<?php foreach ( $nura_popular as $nura_term ) : ?>
							<a class="nura-sresult__chip" data-nura-search-term="<?php echo esc_attr( $nura_term ); ?>" href="<?php echo esc_url( add_query_arg( array( 's' => $nura_term, 'post_type' => 'product' ), home_url( '/' ) ) ); ?>"><span><?php echo esc_html( $nura_term ); ?></span></a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
				$nura_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC', 'number' => 6, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) );
				if ( ! is_wp_error( $nura_cats ) && $nura_cats ) :
					?>
				<div class="nura-sresult__group">
					<p class="nura-sresult__label"><?php esc_html_e( 'Shop by category', 'nura-beauty' ); ?></p>
					<div class="nura-sresult__chips">
						<?php foreach ( $nura_cats as $nura_cat ) : $nura_link = get_term_link( $nura_cat ); if ( is_wp_error( $nura_link ) ) { continue; } ?>
							<a class="nura-sresult__chip" href="<?php echo esc_url( $nura_link ); ?>"><span><?php echo esc_html( $nura_cat->name ); ?></span><em><?php echo absint( $nura_cat->count ); ?></em></a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="nura-search-modal__results" data-nura-search-results aria-live="polite"></div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'nura_render_search_overlay' );


/* -------------------------------------------------------------------------- */
/* v1.144.0 Instant search: a small product index (JSON file in uploads) that   */
/* the browser loads once in the background, so suggestions appear on every    */
/* keystroke without waiting for the server. Rebuilt whenever a product,       */
/* price, stock, category or attribute changes. Falls back to the AJAX search. */
/* -------------------------------------------------------------------------- */

/** Index file path and URL. */
function nura_search_index_loc() {
	$u = wp_upload_dir( null, false );
	return array(
		trailingslashit( $u['basedir'] ) . 'nura/search-index.json',
		set_url_scheme( trailingslashit( $u['baseurl'] ) . 'nura/search-index.json' ),
	);
}

/** Build the index: every published, searchable product with name, link, price, small photo and search words. */
function nura_search_index_build() {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return false;
	}
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'slug',
				'terms'    => array( 'exclude-from-search' ),
				'operator' => 'NOT IN',
			),
		),
	) );
	$rows = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$words = array();
		foreach ( array( 'product_cat', 'product_tag', 'product_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$t = wc_get_product_terms( $id, $tax, array( 'fields' => 'names' ) );
				$words = array_merge( $words, is_array( $t ) ? $t : array() );
			}
		}
		foreach ( $p->get_attributes() as $att ) {
			if ( ! is_object( $att ) ) {
				continue;
			}
			if ( $att->is_taxonomy() ) {
				$t = wc_get_product_terms( $id, $att->get_name(), array( 'fields' => 'names' ) );
				$words = array_merge( $words, is_array( $t ) ? $t : array() );
			} else {
				$words = array_merge( $words, (array) $att->get_options() );
			}
		}
		$img    = get_the_post_thumbnail_url( $id, 'woocommerce_gallery_thumbnail' );
		$rows[] = array(
			't' => wp_strip_all_tags( html_entity_decode( $p->get_name(), ENT_QUOTES, 'UTF-8' ) ),
			'u' => get_permalink( $id ),
			'p' => $p->get_price_html(),
			'i' => $img ? $img : '',
			's' => (string) $p->get_sku(),
			'k' => wp_strip_all_tags( html_entity_decode( implode( ' ', array_unique( array_map( 'strval', $words ) ) ), ENT_QUOTES, 'UTF-8' ) ),
			'o' => $p->is_in_stock() ? 1 : 0,
			'n' => (int) $p->get_total_sales(),
		);
	}
	list( $path ) = nura_search_index_loc();
	if ( ! wp_mkdir_p( dirname( $path ) ) ) {
		return false;
	}
	$v    = time();
	$json = wp_json_encode( array( 'v' => $v, 'items' => $rows ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json || false === file_put_contents( $path, $json, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false;
	}
	update_option( 'nura_search_index_v', $v, true );
	delete_option( 'nura_search_index_dirty' );
	return true;
}

/** Mark the index out of date; it is rebuilt once at the end of the request. */
function nura_search_index_dirty() {
	if ( ! get_option( 'nura_search_index_dirty' ) ) {
		update_option( 'nura_search_index_dirty', 1, true );
	}
}
foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_save_product_variation', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'edited_product_cat', 'edited_product_tag' ) as $nura_hk ) {
	add_action( $nura_hk, 'nura_search_index_dirty' );
}
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $post && 'product' === $post->post_type && $new !== $old ) {
		nura_search_index_dirty();
	}
}, 10, 3 );
add_action( 'deleted_post', function ( $id ) {
	if ( 'product' === get_post_type( $id ) ) {
		nura_search_index_dirty();
	}
} );
add_action( 'shutdown', function () {
	if ( get_option( 'nura_search_index_dirty' ) ) {
		nura_search_index_build();
	}
} );

/** Tell the page where the index is (built on the first visit if missing). */
add_action( 'wp_head', function () {
	if ( is_admin() || ! apply_filters( 'nura_instant_search', true ) ) {
		return;
	}
	list( $path, $url ) = nura_search_index_loc();
	if ( ! file_exists( $path ) ) {
		nura_search_index_dirty();
	}
	$v = (int) get_option( 'nura_search_index_v', 0 );
	if ( ! $v ) {
		return;
	}
	echo '<script>window.NURA_SIDX=' . wp_json_encode( add_query_arg( 'v', $v, $url ) ) . ';</script>' . "\n";
}, 3 );
