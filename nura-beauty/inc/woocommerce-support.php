<?php
/**
 * WooCommerce integration: layout, badges, product loop tuning, cart fragments.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Products per page and columns tuned for a luxury 3-up shop grid.
add_filter( 'loop_shop_per_page', function () { return 12; }, 20 );
add_filter( 'loop_shop_columns', function () { return 3; }, 20 );

// Related products render 4-up (see the matching grid override in assets/css/woocommerce.css).
add_filter( 'woocommerce_related_products_args', function ( $args ) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
} );

/**
 * Image clarity fixes (v1.7.0).
 *
 * The uploaded product masters are modest resolution, so WooCommerce's default
 * 600px "single" image was being upscaled into the ~590px gallery box and looked
 * soft until the hover-zoom swapped in the full file. Render the full-size file as
 * the main gallery image so what you see matches the zoom, and serve the larger
 * "single" size (instead of the 300px thumbnail) in product loops so the shop,
 * related and homepage grids stay crisp at 3- and 4-up.
 */
add_filter( 'woocommerce_gallery_image_size', function ( $size ) {
	return 'woocommerce_single' === $size ? 'full' : $size;
} );
add_filter( 'single_product_archive_thumbnail_size', function () {
	return 'woocommerce_single';
} );

// Variation selection should swap in the full-resolution image too (not the 600px single).
add_filter( 'woocommerce_available_variation', function ( $data, $product, $variation ) {
	$image_id = $variation->get_image_id();
	if ( $image_id ) {
		$full = wp_get_attachment_image_src( $image_id, 'full' );
		if ( $full ) {
			$data['image']['src']      = $full[0];
			$data['image']['src_w']    = $full[1];
			$data['image']['src_h']    = $full[2];
			$data['image']['full_src'] = $full[0];
			$srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
			$sizes  = wp_get_attachment_image_sizes( $image_id, 'full' );
			if ( $srcset ) {
				$data['image']['srcset'] = $srcset;
			}
			if ( $sizes ) {
				$data['image']['sizes'] = $sizes;
			}
		}
	}
	return $data;
}, 10, 3 );

/**
 * Show "From {min price}" on variable products instead of a full price range,
 * in loops and on the product page - clearer and more compelling.
 */
add_filter( 'woocommerce_variable_price_html', function ( $price, $product ) {
	$min = $product->get_variation_price( 'min', true );
	$max = $product->get_variation_price( 'max', true );
	if ( '' === $min ) {
		return $price;
	}
	if ( $min === $max ) {
		return wc_price( $min );
	}
	/* translators: %s: lowest variation price */
	return sprintf( esc_html__( 'From %s', 'nura-beauty' ), wc_price( $min ) );
}, 10, 2 );

// Remove the default WooCommerce wrappers so we control markup.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

add_action( 'woocommerce_before_main_content', function () {
	echo '<main id="primary" class="site-main woo-main"><div class="nura-container">';
}, 10 );
add_action( 'woocommerce_after_main_content', function () {
	echo '</div></main>';
}, 10 );

// Full-width shop & product pages: remove the default WooCommerce sidebar
// (the leftover Search / Recent Posts / Recent Comments widget column).
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
add_filter( 'body_class', function ( $classes ) {
	if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		$classes[] = 'nura-woo-fullwidth';
	}
	return $classes;
} );

// Add a sale / new badge on product cards.
add_action( 'woocommerce_before_shop_loop_item_title', 'nura_product_badges', 9 );
function nura_product_badges() {
	global $product;
	if ( ! $product ) {
		return;
	}
	echo '<div class="nura-badges">';
	if ( $product->is_on_sale() ) {
		echo '<span class="nura-badge nura-badge--sale">' . esc_html__( 'Sale', 'nura-beauty' ) . '</span>';
	}
	$created = strtotime( get_the_date( 'c', $product->get_id() ) );
	if ( $created && ( time() - $created ) < ( 30 * DAY_IN_SECONDS ) ) {
		echo '<span class="nura-badge nura-badge--new">' . esc_html__( 'New', 'nura-beauty' ) . '</span>';
	}
	// One category-derived badge (Human Hair / Glueless) where genuinely applicable.
	$nura_cat_badges = array(
		'human-hair-wigs'       => __( 'Human Hair', 'nura-beauty' ),
		'human-hair-blend-wigs' => __( 'Human Hair', 'nura-beauty' ),
		'glueless-wigs'         => __( 'Glueless', 'nura-beauty' ),
	);
	foreach ( $nura_cat_badges as $nura_slug => $nura_label ) {
		if ( has_term( $nura_slug, 'product_cat', $product->get_id() ) ) {
			echo '<span class="nura-badge nura-badge--hair">' . esc_html( $nura_label ) . '</span>';
			break;
		}
	}
	echo '</div>';
}

/**
 * Sticky "Add to cart" bar data on single product (rendered by JS in main.js).
 */
add_action( 'woocommerce_after_single_product_summary', 'nura_sticky_atc', 5 );
function nura_sticky_atc() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$label = $product->is_in_stock()
		? ( $product->is_type( 'variable' ) ? __( 'Choose options', 'nura-beauty' ) : __( 'Add to bag', 'nura-beauty' ) )
		: __( 'Sold out', 'nura-beauty' );
	printf(
		'<div class="nura-sticky-atc" data-product="%1$d" aria-hidden="true"><span class="nura-sticky-atc__info"><span class="nura-sticky-atc__title">%2$s</span><span class="nura-sticky-atc__price">%3$s</span></span><span class="nura-sticky-atc__btn">%4$s</span></div>',
		absint( $product->get_id() ),
		esc_html( $product->get_name() ),
		wp_kses_post( $product->get_price_html() ),
		esc_html( $label )
	);
}

// Refresh the floating mini-cart count without a reload.
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	ob_start();
	?>
	<span class="nura-cart-count" data-count="<?php echo esc_attr( $count ); ?>"><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['.nura-cart-count'] = ob_get_clean();
	return $fragments;
} );

/**
 * Data-driven hair claim for a product, derived from the global Hair Type attribute.
 * Never asserts human hair on a synthetic or blended unit (see brief #13, #29).
 *
 * @param WC_Product $product Product.
 * @return string Claim text, or '' when the data does not support any claim.
 */
function nura_product_hair_claim( $product ) {
	if ( ! $product || ! function_exists( 'wc_get_product_terms' ) ) {
		return '';
	}
	$terms = wc_get_product_terms( $product->get_id(), 'pa_hair-type', array( 'fields' => 'names' ) );
	$name  = ! empty( $terms ) ? strtolower( (string) $terms[0] ) : '';
	if ( '' === $name ) {
		return '';
	}
	if ( false !== strpos( $name, 'blend' ) ) {
		return __( 'Premium human-hair blend', 'nura-beauty' );
	}
	if ( false !== strpos( $name, 'human hair' ) ) {
		return __( '100% human hair, quality guaranteed', 'nura-beauty' );
	}
	if ( false !== strpos( $name, 'synthetic' ) ) {
		return __( 'Premium synthetic fibre', 'nura-beauty' );
	}
	return '';
}

/**
 * Trust strip under the add-to-cart button (KE-specific reassurance).
 * The hair-quality line reflects the actual product; it is omitted when the data
 * does not support a claim, so we never overstate a unit.
 */
add_action( 'woocommerce_single_product_summary', 'nura_trust_strip', 35 );
function nura_trust_strip() {
	global $product;
	if ( ! $product ) {
		return;
	}
	$items = array();
	$hair  = nura_product_hair_claim( $product );
	if ( $hair ) {
		$items[] = $hair;
	}
	$items[] = __( 'Same-day delivery in Nairobi', 'nura-beauty' );
	$nura_pay = function_exists( 'nura_payment_summary' )
		? nura_payment_summary( __( 'M-Pesa, Cash on Delivery & Bank Transfer', 'nura-beauty' ) )
		: __( 'M-Pesa, Cash on Delivery & Bank Transfer', 'nura-beauty' );
	/* translators: %s: list of accepted payment methods */
	$items[] = sprintf( __( 'Pay with %s', 'nura-beauty' ), $nura_pay );
	$items[] = __( 'Free virtual wig consultation', 'nura-beauty' );
	echo '<ul class="nura-trust">';
	foreach ( $items as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	echo '</ul>';
}

/**
 * At-a-glance spec chips on the single product summary (v1.13.0).
 *
 * The key global attributes (Texture, Length, Hair Type, Lace) shown as elegant
 * pills so shoppers grasp the unit instantly. Data-driven (brief #29): only the
 * attributes the product actually has are rendered.
 */
add_action( 'woocommerce_single_product_summary', 'nura_product_spec_chips', 25 );
function nura_product_spec_chips() {
	global $product;
	if ( ! $product || ! function_exists( 'wc_get_product_terms' ) ) {
		return;
	}
	$specs = array(
		'pa_texture'   => __( 'Texture', 'nura-beauty' ),
		'pa_length'    => __( 'Length', 'nura-beauty' ),
		'pa_hair-type' => __( 'Hair Type', 'nura-beauty' ),
		'pa_lace'      => __( 'Lace', 'nura-beauty' ),
	);
	$chips = array();
	foreach ( $specs as $tax => $label ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$names = wc_get_product_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
		if ( ! empty( $names ) ) {
			$chips[ $label ] = implode( ', ', $names );
		}
	}
	if ( empty( $chips ) ) {
		return;
	}
	echo '<ul class="nura-specchips">';
	foreach ( $chips as $label => $value ) {
		printf(
			'<li class="nura-specchip"><span class="nura-specchip__k">%1$s</span><span class="nura-specchip__v">%2$s</span></li>',
			esc_html( $label ),
			esc_html( $value )
		);
	}
	echo '</ul>';
}

/**
 * Build the "Complete Your NURA Look" product list, data-first (only ever returns
 * products that actually exist), in priority order:
 *   1. The product's configured WooCommerce cross-sells.
 *   2. Complementary products (care / accessories / extensions / services).
 *   3. Related products sharing this unit's Texture (fallback within the wig range).
 * The current product is always excluded.
 *
 * @param WC_Product $product The product being viewed.
 * @param int        $limit   Max products to return.
 * @return int[] Product IDs.
 */
function nura_complete_look_ids( $product, $limit = 4 ) {
	$exclude = array( $product->get_id() );
	$ids     = array_values( array_diff( (array) $product->get_cross_sell_ids(), $exclude ) );

	$vis = array( 'taxonomy' => 'product_visibility', 'field' => 'slug', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' );

	// 2. Complementary categories.
	if ( count( $ids ) < $limit ) {
		$complement = array( 'wig-care', 'hair-care', 'care', 'accessories', 'hair-extensions', 'extensions', 'services', 'installation-services' );
		$q = new WP_Query( array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit * 2,
			'post__not_in'        => array_merge( $exclude, $ids ),
			'orderby'             => 'rand',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
			'tax_query'           => array(
				$vis,
				array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $complement, 'operator' => 'IN' ),
			),
		) );
		$ids = array_values( array_unique( array_merge( $ids, $q->posts ) ) );
		wp_reset_postdata();
	}

	// 3. Fallback: related by shared texture.
	if ( count( $ids ) < $limit && taxonomy_exists( 'pa_texture' ) ) {
		$textures = wc_get_product_terms( $product->get_id(), 'pa_texture', array( 'fields' => 'slugs' ) );
		if ( ! empty( $textures ) ) {
			$q = new WP_Query( array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => $limit * 2,
				'post__not_in'        => array_merge( $exclude, $ids ),
				'orderby'             => 'rand',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'fields'              => 'ids',
				'tax_query'           => array(
					$vis,
					array( 'taxonomy' => 'pa_texture', 'field' => 'slug', 'terms' => $textures, 'operator' => 'IN' ),
				),
			) );
			$ids = array_values( array_unique( array_merge( $ids, $q->posts ) ) );
			wp_reset_postdata();
		}
	}

	return array_slice( array_values( array_diff( $ids, $exclude ) ), 0, $limit );
}

// Replace the default related-products rail with the curated "Complete Your Look".
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
add_action( 'woocommerce_after_single_product_summary', 'nura_complete_the_look', 20 );
function nura_complete_the_look() {
	global $product;
	if ( ! $product || ! function_exists( 'woocommerce_product_loop_start' ) ) {
		return;
	}
	$main_product = $product; // Preserve to restore the global after our custom loop.
	$ids          = nura_complete_look_ids( $product, 4 );
	if ( empty( $ids ) ) {
		return;
	}
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
	echo '<section class="nura-complete-look">';
	echo '<div class="nura-complete-look__head"><h2>' . esc_html__( 'Complete Your NURA Look', 'nura-beauty' ) . '</h2><p>' . esc_html__( 'Curated pairings to finish your style.', 'nura-beauty' ) . '</p></div>';
	woocommerce_product_loop_start();
	while ( $loop->have_posts() ) {
		$loop->the_post();
		wc_get_template_part( 'content', 'product' );
	}
	woocommerce_product_loop_end();
	echo '</section>';
	wp_reset_postdata();
	$GLOBALS['product'] = $main_product; // Restore for any later single-product hooks.
}


/* ================= NURA v1.17.0 : AJAX cart drawer ================= */

/**
 * Load WooCommerce's cart-fragment + variation scripts site-wide so the slide-in
 * cart drawer, the live header count and Quick View variation forms work on every
 * page (home rails, category, single product).
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	wp_enqueue_script( 'wc-add-to-cart' );
	wp_enqueue_script( 'wc-cart-fragments' );
	wp_enqueue_script( 'wc-add-to-cart-variation' );
}, 20 );

// We provide our own drawer CTAs (Proceed to checkout / Continue shopping), so
// drop the default mini-cart buttons to avoid duplicates.
add_action( 'wp_loaded', function () {
	remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
	remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20 );
} );

// Keep the drawer's mini-cart body fresh through WooCommerce's fragment system.
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	if ( ! function_exists( 'woocommerce_mini_cart' ) ) {
		return $fragments;
	}
	ob_start();
	echo '<div class="widget_shopping_cart_content">';
	woocommerce_mini_cart();
	echo '</div>';
	$fragments['div.widget_shopping_cart_content'] = ob_get_clean();
	return $fragments;
} );

/**
 * Render the slide-in cart drawer once, in the footer. Opens on add-to-cart from
 * anywhere (see assets/js/main.js); the inner .widget_shopping_cart_content is a
 * WooCommerce fragment so it refreshes live without a reload.
 */
add_action( 'wp_footer', 'nura_cart_drawer' );
function nura_cart_drawer() {
	if ( is_admin() || ! class_exists( 'WooCommerce' ) || ! function_exists( 'woocommerce_mini_cart' ) ) {
		return;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return; // Don't overlay the checkout itself.
	}
	?>
	<div class="nura-cart-overlay" data-nura-cart-overlay></div>
	<aside class="nura-cartdrawer" data-nura-cartdrawer aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping cart', 'nura-beauty' ); ?>">
		<div class="nura-cartdrawer__head">
			<span class="nura-cartdrawer__title"><?php esc_html_e( 'Your Cart', 'nura-beauty' ); ?></span>
			<button type="button" class="nura-cartdrawer__close" data-nura-cart-close aria-label="<?php esc_attr_e( 'Close cart', 'nura-beauty' ); ?>">&times;</button>
		</div>
		<div class="nura-cartdrawer__body">
			<div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div>
		</div>
		<div class="nura-cartdrawer__foot">
			<a class="nura-btn nura-btn--gold nura-cartdrawer__checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Proceed to checkout', 'nura-beauty' ); ?></a>
			<button type="button" class="nura-btn nura-btn--ghost" data-nura-cart-close><?php esc_html_e( 'Continue shopping', 'nura-beauty' ); ?></button>
		</div>
	</aside>
	<?php
}


/* ================= NURA v1.22.0 : user-flow upgrades ================= */

if ( ! function_exists( 'nura_free_delivery_threshold' ) ) {
	/**
	 * Lowest "minimum order amount" on any enabled Free Shipping method, read from
	 * the real WooCommerce shipping zones. Returns 0 when no threshold is configured,
	 * in which case no progress bar is shown (we never promise what checkout won't give).
	 *
	 * @return float
	 */
	function nura_free_delivery_threshold() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return 0;
		}
		$min = 0;
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			$methods = isset( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array();
			foreach ( $methods as $m ) {
				if ( ! isset( $m->id ) || 'free_shipping' !== $m->id ) {
					continue;
				}
				$on = method_exists( $m, 'is_enabled' ) ? $m->is_enabled() : ( isset( $m->enabled ) && 'yes' === $m->enabled );
				if ( ! $on ) {
					continue;
				}
				$amt = (float) $m->get_option( 'min_amount', 0 );
				if ( $amt > 0 && ( 0 === $min || $amt < $min ) ) {
					$min = $amt;
				}
			}
		}
		return (float) $min;
	}
}

/**
 * Free-delivery progress bar markup (cart drawer + cart page).
 */
function nura_free_delivery_bar() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}
	$goal = nura_free_delivery_threshold();
	if ( $goal <= 0 || WC()->cart->is_empty() ) {
		return '<div class="nura-freebar" hidden></div>';
	}
	$total = (float) WC()->cart->get_displayed_subtotal();
	$pct   = min( 100, round( ( $total / $goal ) * 100 ) );
	if ( $total >= $goal ) {
		$msg = esc_html__( 'You have unlocked free delivery.', 'nura-beauty' );
	} else {
		/* translators: %s: amount left to spend for free delivery */
		$msg = sprintf( esc_html__( 'Add %s more for free delivery.', 'nura-beauty' ), wp_kses_post( wc_price( $goal - $total ) ) );
	}
	return '<div class="nura-freebar' . ( $pct >= 100 ? ' is-done' : '' ) . '"><p class="nura-freebar__msg">' . $msg . '</p><span class="nura-freebar__track"><span class="nura-freebar__fill" style="width:' . absint( $pct ) . '%"></span></span></div>';
}
add_action( 'woocommerce_before_mini_cart', function () {
	echo nura_free_delivery_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
} );
add_action( 'woocommerce_before_cart', function () {
	echo nura_free_delivery_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
} );

/**
 * v1.29.0 Simple Kenyan checkout.
 * Order of fields: Phone / M-Pesa number (required), First name, Last name,
 * Email, County, Town / Area, Delivery address, then optional delivery notes.
 * Removed: company, address line 2 and postcode (not used in Kenya).
 * Country defaults to Kenya; other countries still work for diaspora orders.
 */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	foreach ( array( 'billing', 'shipping' ) as $group ) {
		foreach ( array( 'company', 'address_2', 'postcode' ) as $f ) {
			unset( $fields[ $group ][ $group . '_' . $f ] );
		}
	}
	$b =& $fields['billing'];
	if ( isset( $b['billing_phone'] ) ) {
		$b['billing_phone']['required']          = true;
		$b['billing_phone']['priority']          = 5;
		$b['billing_phone']['label']             = __( 'Phone / M-Pesa number', 'nura-beauty' );
		$b['billing_phone']['placeholder']       = '07XX XXX XXX';
		$b['billing_phone']['description']       = __( 'We call or WhatsApp this number to confirm your order and delivery.', 'nura-beauty' );
		$b['billing_phone']['class']             = array( 'form-row-wide' );
		$b['billing_phone']['custom_attributes'] = array( 'inputmode' => 'tel', 'autocomplete' => 'tel' );
	}
	$map = array(
		'billing_first_name' => array( 10, __( 'First name', 'nura-beauty' ), '', array( 'form-row-first' ) ),
		'billing_last_name'  => array( 20, __( 'Last name', 'nura-beauty' ), '', array( 'form-row-last' ) ),
		'billing_email'      => array( 30, __( 'Email (for your receipt)', 'nura-beauty' ), 'you@example.com', array( 'form-row-wide' ) ),
		'billing_country'    => array( 40, __( 'Country', 'nura-beauty' ), '', array( 'form-row-wide', 'address-field', 'update_totals_on_change' ) ),
		'billing_state'      => array( 50, __( 'County', 'nura-beauty' ), '', array( 'form-row-first', 'address-field' ) ),
		'billing_city'       => array( 60, __( 'Town / Area', 'nura-beauty' ), __( 'e.g. Westlands, Thika, Kisumu', 'nura-beauty' ), array( 'form-row-last', 'address-field' ) ),
		'billing_address_1'  => array( 70, __( 'Delivery address', 'nura-beauty' ), __( 'Estate, street, building or landmark', 'nura-beauty' ), array( 'form-row-wide', 'address-field' ) ),
	);
	foreach ( $map as $key => $cfg ) {
		if ( isset( $b[ $key ] ) ) {
			$b[ $key ]['priority'] = $cfg[0];
			$b[ $key ]['label']    = $cfg[1];
			if ( '' !== $cfg[2] ) {
				$b[ $key ]['placeholder'] = $cfg[2];
			}
			$b[ $key ]['class'] = $cfg[3];
		}
	}
	if ( isset( $b['billing_email'] ) ) {
		$b['billing_email']['custom_attributes'] = array( 'inputmode' => 'email', 'autocomplete' => 'email' );
	}
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = __( 'Delivery notes (optional)', 'nura-beauty' );
		$fields['order']['order_comments']['placeholder'] = __( 'e.g. Call when at the gate, deliver after 2pm', 'nura-beauty' );
	}
	return $fields;
}, 20 );

/** Kenya address format: County + Town, no postcode, labels that make sense locally. */
add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
	$locale['KE'] = array_merge(
		isset( $locale['KE'] ) ? $locale['KE'] : array(),
		array(
			'postcode' => array( 'required' => false, 'hidden' => true ),
			'state'    => array( 'label' => __( 'County', 'nura-beauty' ), 'required' => true, 'priority' => 50 ),
			'city'     => array( 'label' => __( 'Town / Area', 'nura-beauty' ), 'required' => true, 'priority' => 60 ),
			'address_1' => array( 'label' => __( 'Delivery address', 'nura-beauty' ), 'priority' => 70 ),
		)
	);
	return $locale;
} );

/** Postcode never required anywhere at checkout (removed for every country). */
add_filter( 'woocommerce_default_address_fields', function ( $f ) {
	if ( isset( $f['postcode'] ) ) {
		$f['postcode']['required'] = false;
	}
	return $f;
} );

/** Default new shoppers to Kenya. */
add_filter( 'default_checkout_billing_country', function ( $c ) {
	return $c ? $c : 'KE';
} );
add_filter( 'default_checkout_shipping_country', function ( $c ) {
	return $c ? $c : 'KE';
} );

/** Phone must be a real Kenyan or international number. */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	$raw    = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
	$digits = preg_replace( '/\D+/', '', $raw );
	if ( '' === $digits ) {
		return; // WooCommerce already shows "Phone is required".
	}
	$ok = (bool) preg_match( '/^(0[17]\d{8}|254[17]\d{8})$/', $digits ) || ( strlen( $digits ) >= 9 && strlen( $digits ) <= 15 && 0 !== strpos( $digits, '0' ) && 0 !== strpos( $digits, '254' ) );
	if ( !$ok ) {
		$errors->add( 'validation', __( 'Please enter a valid phone number, e.g. 0712 345 678.', 'nura-beauty' ) );
	}
}, 10, 2 );

/**
 * Reassurance right above the payment options at checkout.
 */
add_action( 'woocommerce_review_order_before_payment', function () {
	$pay = function_exists( 'nura_payment_summary' ) ? nura_payment_summary( __( 'M-Pesa or Cash on Delivery', 'nura-beauty' ) ) : '';
	echo '<ul class="nura-checkout-trust">';
	echo '<li>' . esc_html__( 'Secure checkout - your details stay private', 'nura-beauty' ) . '</li>';
	if ( $pay ) {
		/* translators: %s: payment methods */
		echo '<li>' . esc_html( sprintf( __( 'Pay with %s', 'nura-beauty' ), $pay ) ) . '</li>';
	}
	echo '<li>' . esc_html__( 'We confirm every order by phone or WhatsApp', 'nura-beauty' ) . '</li>';
	echo '</ul>';
} );
