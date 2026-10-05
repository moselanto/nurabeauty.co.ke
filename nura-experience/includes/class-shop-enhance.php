<?php
/**
 * NURA Shop Enhancements.
 *
 * Adds a category filter bar, a Quick View modal (REST-powered), and hooks for
 * reviews/related styling to the WooCommerce shop - to lift the browsing
 * experience toward best-in-class without editing WooCommerce core.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Shop_Enhance {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'category_pills' ), 4 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'qv_button' ), 15 );
		add_action( 'wp_footer', array( $this, 'modal' ) );
	}

	public function routes() {
		register_rest_route( 'nurax/v1', '/quickview', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'quickview' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
		) );
	}

	/** Filter pills across top-level product categories. */
	public function category_pills() {
		if ( ! function_exists( 'is_shop' ) ) {
			return;
		}
		if ( ! ( is_shop() || is_product_category() || is_product_taxonomy() ) ) {
			return;
		}
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		$current = is_product_category() ? get_queried_object_id() : 0;
		echo '<nav class="nura-shop-pills" aria-label="' . esc_attr__( 'Product categories', 'nura-experience' ) . '">';
		echo '<a class="' . ( $current ? '' : 'is-active' ) . '" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'All', 'nura-experience' ) . '</a>';
		foreach ( $terms as $t ) {
			$cls = ( (int) $current === (int) $t->term_id ) ? 'is-active' : '';
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
		}
		echo '</nav>';
	}

	/** Quick View button inside each product card. */
	public function qv_button() {
		global $product;
		if ( ! $product ) {
			return;
		}
		echo '<button type="button" class="nura-qv" data-qv="' . esc_attr( $product->get_id() ) . '">' . esc_html__( 'Quick view', 'nura-experience' ) . '</button>';
	}

	/** Quick View data endpoint. */
	public function quickview( WP_REST_Request $req ) {
		$id      = absint( $req->get_param( 'id' ) );
		$product = $id ? wc_get_product( $id ) : null;
		if ( ! $product ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		$img = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
		if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
			$add = sprintf(
				'<a href="%1$s" class="button add_to_cart_button ajax_add_to_cart nura-btn nura-btn--gold" data-product_id="%2$d" data-quantity="1" rel="nofollow">%3$s</a>',
				esc_url( $product->add_to_cart_url() ),
				$product->get_id(),
				esc_html( $product->add_to_cart_text() )
			);
		} else {
			// Render the real single-product add-to-cart area (including the variation
			// form for variable products) so Quick View lets shoppers choose options in
			// place instead of bouncing them to the product page.
			$prev = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
			$GLOBALS['product'] = $product;
			ob_start();
			woocommerce_template_single_add_to_cart();
			$add = trim( ob_get_clean() );
			$GLOBALS['product'] = $prev;
			if ( '' === $add ) {
				$add = sprintf(
					'<a href="%1$s" class="nura-btn nura-btn--gold">%2$s</a>',
					esc_url( $product->get_permalink() ),
					esc_html__( 'Choose options', 'nura-experience' )
				);
			}
		}
		return rest_ensure_response( array(
			'id'      => $product->get_id(),
			'title'   => $product->get_name(),
			'price'   => $product->get_price_html(),
			'image'   => $img ? $img : wc_placeholder_img_src(),
			'excerpt' => wp_kses_post( wpautop( $product->get_short_description() ) ),
			'url'     => $product->get_permalink(),
			'add'     => $add,
		) );
	}

	/** Quick View modal shell (rendered once). */
	public function modal() {
		if ( is_admin() ) {
			return;
		}
		?>
		<div class="nura-qv-modal" data-qv-modal hidden>
			<div class="nura-qv-modal__overlay" data-qv-close></div>
			<div class="nura-qv-modal__box" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Product quick view', 'nura-experience' ); ?>">
				<button type="button" class="nura-qv-modal__close" data-qv-close aria-label="<?php esc_attr_e( 'Close', 'nura-experience' ); ?>">&times;</button>
				<div class="nura-qv-modal__body" data-qv-body></div>
			</div>
		</div>
		<?php
	}
}
