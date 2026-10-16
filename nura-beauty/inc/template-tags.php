<?php
/**
 * Reusable presentation helpers.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessible, schema-aware breadcrumb.
 */
function nura_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}
	$crumbs = array( array( 'name' => __( 'Home', 'nura-beauty' ), 'url' => home_url( '/' ) ) );

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$crumbs[] = array( 'name' => __( 'Shop', 'nura-beauty' ), 'url' => get_permalink( wc_get_page_id( 'shop' ) ) );
	} elseif ( is_singular( 'product' ) ) {
		$crumbs[] = array( 'name' => __( 'Shop', 'nura-beauty' ), 'url' => get_permalink( wc_get_page_id( 'shop' ) ) );
		$crumbs[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_singular() ) {
		$crumbs[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_category() || is_tax() ) {
		$crumbs[] = array( 'name' => single_term_title( '', false ), 'url' => '' );
	} elseif ( is_search() ) {
		$crumbs[] = array( 'name' => __( 'Search results', 'nura-beauty' ), 'url' => '' );
	}

	echo '<nav class="nura-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'nura-beauty' ) . '"><ol>';
	$last = count( $crumbs ) - 1;
	foreach ( $crumbs as $i => $c ) {
		if ( $i === $last || empty( $c['url'] ) ) {
			echo '<li aria-current="page">' . esc_html( $c['name'] ) . '</li>';
		} else {
			echo '<li><a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a></li>';
		}
	}
	echo '</ol></nav>';

	if ( function_exists( 'nura_breadcrumb_schema' ) ) {
		nura_breadcrumb_schema( $crumbs );
	}
}

/**
 * Mini floating cart button (used in header + as a floating action).
 */
function nura_cart_button() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$icon  = '<span class="nura-cart-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-.8 11.2a1.6 1.6 0 0 1-1.6 1.5H8.4a1.6 1.6 0 0 1-1.6-1.5L6 8Z"></path><path d="M9 8V6.5a3 3 0 0 1 6 0V8"></path></svg></span>';
	printf(
		'<a class="nura-cart-btn" href="%1$s" data-nura-cart-toggle aria-label="%2$s">%4$s<span class="nura-cart-count" data-count="%3$d">%3$d</span></a>',
		esc_url( wc_get_cart_url() ),
		esc_attr__( 'View cart', 'nura-beauty' ),
		absint( $count ),
		$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, safe inline SVG.
	);
}

/**
 * Announcement bar (edit text in the Customizer).
 */
function nura_announcement_bar() {
	$text = get_theme_mod( 'nura_announcement', __( 'Same-Day Nairobi Delivery • M-Pesa Available • Shop with Confidence', 'nura-beauty' ) );
	if ( ! $text ) {
		return;
	}
	$social = '';
	if ( function_exists( 'nura_social_links' ) ) {
		foreach ( nura_social_links() as $soc ) {
			if ( ! in_array( $soc['key'], array( 'tiktok', 'facebook', 'instagram' ), true ) ) {
				continue;
			}
			/* translators: %s: social network name. */
			$social .= '<a href="' . esc_url( $soc['url'] ) . '" target="_blank" rel="noopener me" aria-label="' . esc_attr( sprintf( __( 'NURA on %s', 'nura-beauty' ), $soc['label'] ) ) . '">' . nura_social_icon( $soc['key'] ) . '</a>';
		}
	}
	echo '<div class="nura-announce"><div class="nura-container nura-announce__inner"><p>' . wp_kses_post( $text ) . '</p>';
	if ( $social ) {
		echo '<div class="nura-announce__social">' . $social . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; icons are static SVG.
	}
	echo '</div></div>';
}

/**
 * Fallback collection-tile image, used when a product category has no thumbnail.
 * Keyword-matched so it is meaningful, and overridden the moment a real category
 * image is set under Products -> Categories.
 */
function nura_cat_fallback_image( $name ) {
	$b = NURA_URI . 'assets/images/cats/';
	$n = strtolower( (string) $name );
	$map = array(
		'bridal'=>'bridal-occasion.webp','occasion'=>'bridal-occasion.webp','wedding'=>'bridal-occasion.webp',
		'lace'=>'lace-front-hd.webp','hd'=>'lace-front-hd.webp','closure'=>'lace-front-hd.webp','frontal'=>'lace-front-hd.webp',
		'ready'=>'ready-to-wear.webp','glueless'=>'ready-to-wear.webp','headband'=>'ready-to-wear.webp','everyday'=>'ready-to-wear.webp',
		'confidence'=>'confidence-line.webp','signature'=>'confidence-line.webp',
	);
	foreach ( $map as $needle => $file ) {
		if ( false !== strpos( $n, $needle ) ) { return $b . $file; }
	}
	return $b . 'wigs.webp';
}


if ( function_exists( 'nura_rail' ) === false ) {
	/**
	 * Output a horizontal product rail (heading row + scrollable WooCommerce products).
	 */
	function nura_rail( $eyebrow, $title, $shortcode, $view_all_url = '', $view_all_label = '' ) {
		if ( class_exists( 'WooCommerce' ) === false ) { return; }
		echo '<section class="section nura-rail-section"><div class="nura-container">';
		echo '<div class="nura-shead nura-shead--row nura-reveal"><div>';
		if ( $eyebrow ) { echo '<p class="nura-eyebrow">' . esc_html( $eyebrow ) . '</p>'; }
		echo '<h2>' . esc_html( $title ) . '</h2></div>';
		if ( $view_all_url ) {
			$label = $view_all_label ? $view_all_label : __( 'View all', 'nura-beauty' );
			echo '<a class="nura-rail__viewall" href="' . esc_url( $view_all_url ) . '">' . esc_html( $label ) . ' &#8594;</a>';
		}
		echo '</div>';
		echo '<div class="nura-rail nura-reveal" data-nura-rail>';
		echo '<button type="button" class="nura-rail__nav nura-rail__nav--prev" data-rail-prev aria-label="' . esc_attr__( 'Scroll left', 'nura-beauty' ) . '">&#8249;</button>';
		echo '<div class="nura-rail__track">' . do_shortcode( $shortcode ) . '</div>';
		echo '<button type="button" class="nura-rail__nav nura-rail__nav--next" data-rail-next aria-label="' . esc_attr__( 'Scroll right', 'nura-beauty' ) . '">&#8250;</button>';
		echo '</div></div></section>';
	}
}


if ( function_exists( 'nura_recent_reviews' ) === false ) {
	/**
	 * Fetch recent approved WooCommerce product reviews for on-site social proof.
	 *
	 * @param int $limit      Max reviews to return.
	 * @param int $min_rating Minimum star rating to include (1-5).
	 * @return array List of [author, rating, text, product, url, date].
	 */
	function nura_recent_reviews( $limit = 6, $min_rating = 4 ) {
		if ( class_exists( 'WooCommerce' ) === false ) {
			return array();
		}
		$comments = get_comments( array(
			'status'    => 'approve',
			'post_type' => 'product',
			'type'      => 'review',
			'number'    => 40,
		) );
		$out = array();
		foreach ( $comments as $c ) {
			$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
			if ( $rating && $rating < $min_rating ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( $c->comment_content ) );
			if ( '' === $text ) {
				continue;
			}
			$out[] = array(
				'author'  => $c->comment_author ? $c->comment_author : __( 'NURA client', 'nura-beauty' ),
				'rating'  => $rating ? $rating : 5,
				'text'    => $text,
				'product' => get_the_title( $c->comment_post_ID ),
				'url'     => get_permalink( $c->comment_post_ID ),
				'date'    => $c->comment_date,
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
}


if ( function_exists( 'nura_query_rail' ) === false ) {
	/**
	 * Horizontal product rail built from a live query and shuffled on every page load
	 * so the homepage stays fresh ("pull random products to avoid boredom"). Pulls a
	 * pool via $pool_args, shuffles, shows $show, and renders real WooCommerce product
	 * cards inside a scroll-snap track with working prev/next arrows (wired in main.js).
	 *
	 * @param string $eyebrow        Small eyebrow label.
	 * @param string $title          Rail heading.
	 * @param array  $pool_args      Extra WP_Query args defining the pool (orderby, meta, ...).
	 * @param int    $show           How many products to display.
	 * @param string $view_all_url   Optional "view all" URL.
	 * @param string $view_all_label Optional "view all" label.
	 */
	function nura_query_rail( $eyebrow, $title, $pool_args = array(), $show = 10, $view_all_url = '', $view_all_label = '' ) {
		if ( class_exists( 'WooCommerce' ) === false || function_exists( 'woocommerce_product_loop_start' ) === false ) {
			return;
		}
		$show     = max( 4, (int) $show );
		$defaults = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => max( $show * 2, 16 ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'tax_query'           => array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'slug',
					'terms'    => array( 'exclude-from-catalog' ),
					'operator' => 'NOT IN',
				),
			),
		);
		// v1.22.0: never repeat a product that an earlier rail on this page already
		// showed (Best Sellers was mirroring New Arrivals), and allow a fallback pool
		// for rails whose primary signal (e.g. sales) has no data yet.
		$pool_args = (array) $pool_args;
		$fallback  = isset( $pool_args['nura_fallback'] ) ? (array) $pool_args['nura_fallback'] : array();
		unset( $pool_args['nura_fallback'] );
		$shown = isset( $GLOBALS['nura_rail_shown'] ) ? (array) $GLOBALS['nura_rail_shown'] : array();
		if ( $shown ) {
			$defaults['post__not_in'] = $shown;
		}
		$pool = new WP_Query( array_merge( $defaults, $pool_args ) );
		$ids  = $pool->posts;
		wp_reset_postdata();
		if ( count( $ids ) < 4 && $fallback ) {
			$fb  = new WP_Query( array_merge( $defaults, $fallback, array( 'post__not_in' => array_merge( $shown, $ids ) ) ) );
			$ids = array_merge( $ids, $fb->posts );
			wp_reset_postdata();
		}
		if ( empty( $ids ) ) {
			return;
		}
		shuffle( $ids );
		// v1.55.5: render a larger pool; main.js shows a random $show on every page load
		// (works even when the page is served from cache).
		$ids  = array_slice( $ids, 0, $show * 2 );
		$GLOBALS['nura_rail_shown'] = array_merge( $shown, $ids );
		$loop = new WP_Query( array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		if ( ! $loop->have_posts() ) {
			wp_reset_postdata();
			return;
		}

		echo '<section class="section nura-rail-section"><div class="nura-container">';
		echo '<div class="nura-shead nura-shead--row nura-reveal"><div>';
		if ( $eyebrow ) {
			echo '<p class="nura-eyebrow">' . esc_html( $eyebrow ) . '</p>';
		}
		echo '<h2>' . esc_html( $title ) . '</h2></div>';
		if ( $view_all_url ) {
			$label = $view_all_label ? $view_all_label : __( 'View all', 'nura-beauty' );
			echo '<a class="nura-rail__viewall" href="' . esc_url( $view_all_url ) . '">' . esc_html( $label ) . ' &#8594;</a>';
		}
		echo '</div>';
		echo '<div class="nura-rail nura-reveal" data-nura-rail>';
		echo '<button type="button" class="nura-rail__nav nura-rail__nav--prev" data-rail-prev aria-label="' . esc_attr__( 'Scroll left', 'nura-beauty' ) . '">&#8249;</button>';
		echo '<div class="nura-rail__track" data-nura-shuffle data-show="' . esc_attr( $show ) . '"><div class="woocommerce">';
		woocommerce_product_loop_start();
		while ( $loop->have_posts() ) {
			$loop->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		echo '</div></div>';
		echo '<button type="button" class="nura-rail__nav nura-rail__nav--next" data-rail-next aria-label="' . esc_attr__( 'Scroll right', 'nura-beauty' ) . '">&#8250;</button>';
		echo '</div></div></section>';
		wp_reset_postdata();
	}
}


if ( function_exists( 'nura_home_product_ids' ) === false ) {
	/**
	 * v1.33.0 Product IDs for a homepage grid. In-stock and catalogue-visible only,
	 * with an optional fallback pool when the primary signal (e.g. sales) is thin.
	 *
	 * @param array $args     Extra WP_Query args.
	 * @param int   $limit    How many IDs to return.
	 * @param array $fallback Fallback WP_Query args.
	 * @return int[]
	 */
	function nura_home_product_ids( $args, $limit = 8, $fallback = array() ) {
		$base = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'tax_query'           => array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'slug',
					'terms'    => array( 'exclude-from-catalog', 'outofstock' ),
					'operator' => 'NOT IN',
				),
			),
		);
		$q   = new WP_Query( array_merge( $base, (array) $args ) );
		$ids = array_map( 'intval', $q->posts );
		wp_reset_postdata();
		if ( count( $ids ) < $limit && $fallback ) {
			$fb  = new WP_Query( array_merge( $base, (array) $fallback, array( 'post__not_in' => $ids, 'posts_per_page' => $limit - count( $ids ) ) ) );
			$ids = array_merge( $ids, array_map( 'intval', $fb->posts ) );
			wp_reset_postdata();
		}
		return array_slice( array_unique( $ids ), 0, $limit );
	}
}

if ( function_exists( 'nura_home_product_grid' ) === false ) {
	/**
	 * v1.33.0 Render real WooCommerce product cards in a grid (2 per row on phones,
	 * 3 on tablets, 4 on desktop). Shown IDs are recorded so later rails never repeat them.
	 *
	 * @param int[] $ids Product IDs in display order.
	 */
	function nura_home_product_grid( $ids ) {
		if ( empty( $ids ) || function_exists( 'woocommerce_product_loop_start' ) === false ) {
			return;
		}
		$shown                      = isset( $GLOBALS['nura_rail_shown'] ) ? (array) $GLOBALS['nura_rail_shown'] : array();
		$GLOBALS['nura_rail_shown'] = array_unique( array_merge( $shown, $ids ) );
		$loop = new WP_Query( array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		if ( ! $loop->have_posts() ) {
			wp_reset_postdata();
			return;
		}
		if ( function_exists( 'wc_set_loop_prop' ) ) {
			wc_set_loop_prop( 'columns', 4 );
		}
		echo '<div class="woocommerce nura-home-grid">';
		woocommerce_product_loop_start();
		while ( $loop->have_posts() ) {
			$loop->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		echo '</div>';
		wp_reset_postdata();
	}
}

if ( function_exists( 'nura_home_product_tabs' ) === false ) {
	/**
	 * v1.33.0 Homepage "Shop now" block: New In / Best Sellers / Under KSh 5,000 tabs.
	 * All panels are server-rendered (works without JS: the first panel shows and the
	 * rest stay hidden); main.js switches tabs. Empty tabs are left out.
	 *
	 * @param string $shop_url Shop page URL for the "View all" links.
	 */
	function nura_home_product_tabs( $shop_url ) {
		$limit = (int) apply_filters( 'nura_home_grid_count', 8 );
		$pool  = (int) apply_filters( 'nura_home_grid_pool', $limit + 6 );
		$tabs  = array(
			array(
				'key'   => 'new',
				'label' => __( 'New In', 'nura-beauty' ),
				'ids'   => nura_home_product_ids( array( 'orderby' => 'date', 'order' => 'DESC' ), $pool ),
				'url'   => add_query_arg( 'orderby', 'date', $shop_url ),
			),
			array(
				'key'   => 'best',
				'label' => __( 'Best Sellers', 'nura-beauty' ),
				'ids'   => nura_home_product_ids(
					array(
						'meta_key'   => 'total_sales',
						'orderby'    => 'meta_value_num',
						'order'      => 'DESC',
						'meta_query' => array( array( 'key' => 'total_sales', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ),
					),
					$pool,
					array( 'orderby' => 'rand' )
				),
				'url'   => add_query_arg( 'orderby', 'popularity', $shop_url ),
			),
			array(
				'key'   => 'budget',
				'label' => __( 'Under KSh 5,000', 'nura-beauty' ),
				'ids'   => nura_home_product_ids(
					array(
						'orderby'    => 'date',
						'order'      => 'DESC',
						'meta_query' => array( array( 'key' => '_price', 'value' => 5000, 'compare' => '<=', 'type' => 'NUMERIC' ) ),
					),
					$pool
				),
				'url'   => add_query_arg( array( 'min_price' => 0, 'max_price' => 5000 ), $shop_url ),
			),
		);
		$tabs = array_values( array_filter( $tabs, function ( $t ) { return ! empty( $t['ids'] ); } ) );
		if ( empty( $tabs ) ) {
			return;
		}
		echo '<div class="nura-ptabs" data-nura-tabs>';
		if ( count( $tabs ) > 1 ) {
			echo '<div class="nura-ptabs__list" role="tablist" aria-label="' . esc_attr__( 'Product collections', 'nura-beauty' ) . '">';
			foreach ( $tabs as $i => $t ) {
				printf(
					'<button type="button" class="nura-ptab%1$s" role="tab" id="nura-ptab-%2$s" aria-controls="nura-ppanel-%2$s" aria-selected="%3$s" tabindex="%4$s" data-viewall="%5$s">%6$s</button>',
					0 === $i ? ' is-active' : '',
					esc_attr( $t['key'] ),
					0 === $i ? 'true' : 'false',
					0 === $i ? '0' : '-1',
					esc_url( $t['url'] ),
					esc_html( $t['label'] )
				);
			}
			echo '</div>';
		}
		foreach ( $tabs as $i => $t ) {
			printf(
				'<div class="nura-ppanel" role="tabpanel" id="nura-ppanel-%1$s" aria-labelledby="nura-ptab-%1$s"%2$s>',
				esc_attr( $t['key'] ),
				0 === $i ? '' : ' hidden'
			);
			echo '<div data-nura-shuffle data-show="' . esc_attr( $limit ) . '"' . ( 'new' === $t['key'] ? ' data-keep-order' : '' ) . '>';
			nura_home_product_grid( $t['ids'] );
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}
}
