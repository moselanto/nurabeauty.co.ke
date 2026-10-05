<?php
/**
 * NURA Variation Swatches.
 *
 * Turns WooCommerce variation dropdowns into visual swatches: colour dots for
 * the Colour attribute and text pills (length, construction, density, cap size,
 * etc.) for the rest. The native <select> is kept in the DOM and hidden, and
 * every swatch click drives it, so WooCommerce's own variations.js still powers
 * price/image/stock updates and availability. Unavailable options are greyed
 * out in sync with the select's disabled state.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Variation_Swatches {

	public function __construct() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	/** Common NURA colour name (slug) => swatch background. */
	private function colour_map() {
		return array(
			'natural-black' => '#1a1a1a',
			'jet-black'     => '#000000',
			'off-black'     => '#242424',
			'brown'         => '#6b4324',
			'highlighted'   => 'linear-gradient(135deg,#3a2a1a 0%,#c79a54 100%)',
			'blonde'        => '#e4c887',
			'honey-blonde'  => '#d9a441',
			'ginger'        => '#b4551d',
			'ombre'         => 'linear-gradient(180deg,#1f1f1f 0%,#b4551d 100%)',
			'burgundy'      => '#5a1a2b',
		);
	}

	/**
	 * Append a swatch UI to the variation dropdown markup.
	 *
	 * @param string $html Dropdown HTML.
	 * @param array  $args Dropdown args (options, attribute, product, selected).
	 * @return string
	 */
	public function render( $html, $args ) {
		$options   = isset( $args['options'] ) ? $args['options'] : array();
		$product   = isset( $args['product'] ) ? $args['product'] : false;
		$attribute = isset( $args['attribute'] ) ? $args['attribute'] : '';
		$selected  = isset( $args['selected'] ) ? $args['selected'] : '';

		if ( empty( $options ) || ! $product || ! $attribute ) {
			return $html;
		}

		$is_colour = ( 'pa_colour' === $attribute );
		$wrap_cls  = 'nura-swatches' . ( $is_colour ? ' nura-swatches--colour' : '' );
		$buttons   = '';

		if ( taxonomy_exists( $attribute ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->slug, $options, true ) ) {
					continue;
				}
				$buttons .= $this->button( $term->slug, $term->name, $is_colour, $selected );
			}
		} else {
			foreach ( $options as $opt ) {
				$buttons .= $this->button( $opt, $opt, $is_colour, $selected );
			}
		}

		if ( '' === $buttons ) {
			return $html;
		}

		$swatches = '<div class="' . esc_attr( $wrap_cls ) . '" role="listbox">' . $buttons . '</div>';
		return $swatches . $html;
	}

	private function button( $value, $label, $is_colour, $selected ) {
		$active = ( '' !== $selected && sanitize_title( (string) $selected ) === sanitize_title( (string) $value ) ) ? ' is-active' : '';

		if ( $is_colour ) {
			$map   = $this->colour_map();
			$slug  = sanitize_title( $value );
			$bg    = isset( $map[ $slug ] ) ? $map[ $slug ] : '#cfc3b0';
			return '<button type="button" class="nura-swatch nura-swatch--dot' . $active . '" data-value="' . esc_attr( $value ) . '" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '"><span style="background:' . esc_attr( $bg ) . '"></span></button>';
		}

		return '<button type="button" class="nura-swatch' . $active . '" data-value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</button>';
	}

	public function assets() {
		if ( is_admin() ) {
			return;
		}
		// Quick View can surface a variation form on ANY product loop - shop and
		// taxonomy archives, the front-page carousels, and the related-products
		// grid on a single product - so the swatch driver AND WooCommerce's own
		// variation engine must be present there too, not only on single-product
		// pages. Without the engine, variation_id is never set and Quick View
		// 'Add to cart' is inert; without the driver the swatches never bind.
		$load = ( function_exists( 'is_product' ) && is_product() )
			|| ( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
			|| is_front_page()
			|| is_home();
		$load = (bool) apply_filters( 'nurax_swatches_load_assets', $load );
		if ( $load === false ) {
			return;
		}
		$deps = array( 'jquery' );
		// WooCommerce registers this on the front end but only enqueues it on
		// single-product pages; pull it in wherever Quick View may run.
		if ( wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
			wp_enqueue_script( 'wc-add-to-cart-variation' );
			$deps[] = 'wc-add-to-cart-variation';
		}
		wp_enqueue_script( 'nurax-variations', NURAX_URL . 'assets/js/nura-variations.js', $deps, NURAX_VERSION, true );
	}
}
