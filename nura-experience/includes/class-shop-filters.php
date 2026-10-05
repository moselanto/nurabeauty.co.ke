<?php
/**
 * NURA Shop Filters + product-card upgrades.
 *
 * Adds a faceted filter sidebar (Category, Price, Hair Type, Construction,
 * Texture, Length, Colour, Lace Type, Cap Size) beside a 4-per-row product grid
 * on the shop and product archives, a wishlist heart (client-side, localStorage),
 * a Try-On shortcut and a mobile filter drawer. Filtering uses WooCommerce's
 * native layered-nav (filter_*) and price (min_price/max_price) query vars, so it
 * works without any extra plugin.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Shop_Filters {

	/** Attribute facets: attribute slug (without pa_) => label. */
	private $facets = array(
		'hair-type'    => 'Hair Type',
		'construction' => 'Construction',
		'texture'      => 'Texture',
		'length'       => 'Length',
		'colour'       => 'Colour',
		'lace'         => 'Lace Type',
		'cap-size'     => 'Cap Size',
	);

	public function __construct() {
		// 4-per-row grid (overrides the theme's 3 at a later priority).
		add_filter( 'loop_shop_columns', function () { return 4; }, 30 );

		// Two-column layout: open sidebar + products column, then close.
		add_action( 'woocommerce_before_shop_loop', array( $this, 'open_layout' ), 5 );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'close_layout' ), 50 );

		// Product-card upgrades.
		add_action( 'woocommerce_before_shop_loop_item', array( $this, 'wishlist_button' ), 5 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'tryon_button' ), 16 );

		// Front-end assets (script is tiny; load site-wide so the wishlist badge works everywhere).
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	private function is_shop_context() {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
	}

	/** Base URL of the current archive (shop page or the current term). */
	private function base_url() {
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$link = get_term_link( get_queried_object() );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
		return wc_get_page_permalink( 'shop' );
	}

	/** Current query params, minus pagination, sanitised. */
	private function current_params() {
		$params = array();
		foreach ( (array) $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'paged' === $k ) {
				continue;
			}
			$key = sanitize_key( $k );
			$params[ $key ] = is_array( $v ) ? array_map( 'sanitize_text_field', wp_unslash( $v ) ) : sanitize_text_field( wp_unslash( $v ) );
		}
		return $params;
	}

	/** Chosen term slugs for an attribute facet. */
	private function chosen( $slug ) {
		$key = 'filter_' . $slug;
		if ( empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return array();
		}
		return array_filter( array_map( 'sanitize_title', explode( ',', sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** URL that toggles a single attribute term on/off, preserving other filters. */
	private function toggle_url( $slug, $term_slug ) {
		$params = $this->current_params();
		$key    = 'filter_' . $slug;
		$chosen = $this->chosen( $slug );
		if ( in_array( $term_slug, $chosen, true ) ) {
			$chosen = array_diff( $chosen, array( $term_slug ) );
		} else {
			$chosen[] = $term_slug;
		}
		if ( $chosen ) {
			$params[ $key ]                  = implode( ',', $chosen );
			$params[ 'query_type_' . $slug ] = 'or';
		} else {
			unset( $params[ $key ], $params[ 'query_type_' . $slug ] );
		}
		return esc_url( add_query_arg( $params, $this->base_url() ) );
	}

	public function open_layout() {
		if ( ! $this->is_shop_context() ) {
			return;
		}
		echo '<button type="button" class="nura-filter-toggle" data-nura-filter-toggle>' . esc_html__( 'Filters', 'nura-experience' ) . '</button>';
		echo '<div class="nura-shop-layout">';
		echo '<aside class="nura-shop-sidebar" data-nura-sidebar>';
		echo '<button type="button" class="nura-sidebar-close" data-nura-filter-toggle aria-label="' . esc_attr__( 'Close filters', 'nura-experience' ) . '">&times;</button>';
		$this->render_sidebar();
		echo '</aside>';
		echo '<div class="nura-shop-main">';
	}

	public function close_layout() {
		if ( ! $this->is_shop_context() ) {
			return;
		}
		echo '</div></div>'; // .nura-shop-main + .nura-shop-layout
	}

	private function render_sidebar() {
		$base        = $this->base_url();
		$has_filters = false;
		$watch       = array( 'min_price', 'max_price' );
		foreach ( array_keys( $this->facets ) as $s ) {
			$watch[] = 'filter_' . $s;
		}
		foreach ( $watch as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$has_filters = true;
				break;
			}
		}

		echo '<div class="nura-fh"><span>' . esc_html__( 'Filter', 'nura-experience' ) . '</span>';
		if ( $has_filters ) {
			echo '<a class="nura-fclear" href="' . esc_url( $base ) . '">' . esc_html__( 'Clear all', 'nura-experience' ) . '</a>';
		}
		echo '</div>';

		$this->render_categories();
		$this->render_price();

		foreach ( $this->facets as $slug => $label ) {
			$tax = wc_attribute_taxonomy_name( $slug );
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$chosen = $this->chosen( $slug );
			echo '<details class="nura-facet"' . ( $chosen ? ' open' : '' ) . '><summary>' . esc_html( $label ) . '</summary><ul>';
			foreach ( $terms as $t ) {
				$active = in_array( $t->slug, $chosen, true );
				echo '<li><a class="' . ( $active ? 'is-active' : '' ) . '" href="' . $this->toggle_url( $slug, $t->slug ) . '"><span class="nura-check" aria-hidden="true"></span>' . esc_html( $t->name ) . '</a></li>';
			}
			echo '</ul></details>';
		}
	}

	private function render_categories() {
		$parents = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		) );
		if ( is_wp_error( $parents ) || empty( $parents ) ) {
			return;
		}
		$current = ( function_exists( 'is_product_category' ) && is_product_category() ) ? get_queried_object() : null;
		$current_id     = $current ? (int) $current->term_id : 0;
		$current_parent = $current ? (int) $current->parent : 0;

		echo '<details class="nura-facet" open><summary>' . esc_html__( 'Category', 'nura-experience' ) . '</summary><ul>';
		foreach ( $parents as $p ) {
			$active = ( $current_id === (int) $p->term_id );
			echo '<li><a class="' . ( $active ? 'is-active' : '' ) . '" href="' . esc_url( get_term_link( $p ) ) . '">' . esc_html( $p->name ) . '</a>';
			// Expand children only for the branch the shopper is in.
			$expand = ( $current_id === (int) $p->term_id ) || ( $current_parent === (int) $p->term_id );
			if ( $expand ) {
				$kids = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => (int) $p->term_id ) );
				if ( ! is_wp_error( $kids ) && $kids ) {
					echo '<ul class="nura-subcats">';
					foreach ( $kids as $k ) {
						$kactive = ( $current_id === (int) $k->term_id );
						echo '<li><a class="' . ( $kactive ? 'is-active' : '' ) . '" href="' . esc_url( get_term_link( $k ) ) . '">' . esc_html( $k->name ) . '</a></li>';
					}
					echo '</ul>';
				}
			}
			echo '</li>';
		}
		echo '</ul></details>';
	}

	private function render_price() {
		$base = $this->base_url();
		$min  = isset( $_GET['min_price'] ) ? (int) $_GET['min_price'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$max  = isset( $_GET['max_price'] ) ? (int) $_GET['max_price'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<details class="nura-facet" open><summary>' . esc_html__( 'Price (KES)', 'nura-experience' ) . '</summary>';
		echo '<form class="nura-price-form" method="get" action="' . esc_url( $base ) . '">';
		foreach ( $this->current_params() as $k => $v ) {
			if ( 'min_price' === $k || 'max_price' === $k ) {
				continue;
			}
			if ( is_array( $v ) ) {
				foreach ( $v as $vv ) {
					echo '<input type="hidden" name="' . esc_attr( $k ) . '[]" value="' . esc_attr( $vv ) . '">';
				}
			} else {
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
			}
		}
		echo '<div class="nura-price-row">';
		echo '<input type="number" name="min_price" inputmode="numeric" min="0" placeholder="' . esc_attr__( 'Min', 'nura-experience' ) . '" value="' . esc_attr( $min ) . '">';
		echo '<span>&ndash;</span>';
		echo '<input type="number" name="max_price" inputmode="numeric" min="0" placeholder="' . esc_attr__( 'Max', 'nura-experience' ) . '" value="' . esc_attr( $max ) . '">';
		echo '</div>';
		echo '<button type="submit" class="nura-price-go nura-btn nura-btn--gold">' . esc_html__( 'Apply', 'nura-experience' ) . '</button>';
		echo '</form></details>';
	}

	/** Heart button (client-side wishlist) - rendered inside the card, outside the product link. */
	public function wishlist_button() {
		global $product;
		if ( ! $product ) {
			return;
		}
		$svg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-9.3-8.6C1.2 8.6 2.6 5.5 5.7 5.5c1.9 0 3.1 1.1 3.9 2.3.8-1.2 2-2.3 3.9-2.3 3.1 0 4.5 3.1 3 5.9C19 15.6 12 20 12 20z"/></svg>';
		printf(
			'<button type="button" class="nura-wish" data-id="%1$d" aria-pressed="false" aria-label="%2$s">%3$s</button>',
			(int) $product->get_id(),
			esc_attr__( 'Save to wishlist', 'nura-experience' ),
			$svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/** Try-On shortcut on the product card. */
	public function tryon_button() {
		global $product;
		if ( ! $product ) {
			return;
		}
		printf(
			'<a class="nura-tryon-link" href="%1$s">%2$s</a>',
			esc_url( add_query_arg( 'tryon', '1', $product->get_permalink() ) ),
			esc_html__( 'Try on', 'nura-experience' )
		);
	}

	public function assets() {
		if ( is_admin() ) {
			return;
		}
		wp_enqueue_script( 'nurax-shop', NURAX_URL . 'assets/js/nura-shop.js', array(), NURAX_VERSION, true );
	}
}
