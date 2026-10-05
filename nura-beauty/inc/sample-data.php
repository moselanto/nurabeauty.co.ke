<?php
/**
 * One-click sample-data importer.
 *
 * Builds a complete, editable store: pages (from demo/pages), static homepage,
 * WooCommerce pages, product categories, global variation attributes, demo
 * products with per-variation SKU/price/stock, and navigation menus. Idempotent
 * - safe to re-run; it finds existing items by slug instead of duplicating.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NURA_DIR . 'inc/sample-content.php';

/**
 * On activation: create the page structure + menus automatically (no products,
 * since WooCommerce may not be active yet), then send the admin to setup.
 */
function nura_after_switch() {
	nura_run_import( false );
	set_transient( 'nura_activated', 1, 30 );
}
add_action( 'after_switch_theme', 'nura_after_switch' );

function nura_activation_redirect() {
	if ( get_transient( 'nura_activated' ) && ! isset( $_GET['activated'] ) === false ) {
		delete_transient( 'nura_activated' );
		if ( current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'themes.php?page=nura-welcome' ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'nura_activation_redirect' );

/**
 * Admin-post handler for the "Import sample data" button (full import).
 */
function nura_handle_import_sample() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'nura_import_sample' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'nura-beauty' ) );
	}
	@set_time_limit( 300 );
	nura_run_import( true );
	update_option( 'nura_sample_imported', 1 );
	wp_safe_redirect( admin_url( 'themes.php?page=nura-welcome&nura_import=done' ) );
	exit;
}
add_action( 'admin_post_nura_import_sample', 'nura_handle_import_sample' );

/**
 * Master import routine.
 *
 * @param bool $with_products Whether to also create WooCommerce categories/products.
 */
function nura_run_import( $with_products = true ) {
	$page_ids = nura_import_pages();

	// Static front page -> the theme's front-page.php via a "Home" page.
	$home_id = nura_get_or_create_page( 'Home', 'home', '<!-- The homepage layout is rendered by the theme (front-page.php). -->' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );
	$blog_id = nura_get_or_create_page( 'Journal', 'journal', '' );
	update_option( 'page_for_posts', $blog_id );

	if ( $with_products && class_exists( 'WooCommerce' ) ) {
		nura_assign_woocommerce_pages();
		$cat_ids = nura_import_categories();
		nura_import_attributes();
		nura_import_products( $cat_ids );
	}

	nura_build_menus( $page_ids );

	// Flush rewrite rules so newly created pages (Virtual Try-On, AI Wig Finder,
	// NURA Circle, Journal, etc.) resolve immediately instead of returning 404.
	flush_rewrite_rules();
}

/**
 * Create/update a single page. Returns the page ID.
 */
function nura_get_or_create_page( $title, $slug, $content, $template = '' ) {
	$existing = get_page_by_path( $slug );
	$args = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);
	if ( $existing ) {
		$args['ID'] = $existing->ID;
		$id = wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
	}
	if ( $template && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
}

/**
 * Import all manifest pages from their HTML templates. Returns [slug => id].
 */
function nura_import_pages() {
	$ids  = array();
	$subs = nura_content_placeholders();
	foreach ( nura_sample_pages() as $p ) {
		$file = NURA_DIR . 'demo/pages/' . $p['file'] . '.html';
		$html = file_exists( $file ) ? file_get_contents( $file ) : '';
		$html = strtr( $html, $subs );
		$id   = nura_get_or_create_page( $p['title'], $p['slug'], $html );
		$ids[ $p['slug'] ] = array( 'id' => $id, 'title' => $p['title'], 'menu' => $p['menu'] );
	}
	return $ids;
}

/**
 * Point WooCommerce's core pages at sensible defaults (WC creates them; we just
 * make sure the account/shop pages exist).
 */
function nura_assign_woocommerce_pages() {
	if ( function_exists( 'wc_create_page' ) ) {
		// WooCommerce creates its pages on install; ensure My Account exists for the portal.
		$account = wc_get_page_id( 'myaccount' );
		if ( $account <= 0 ) {
			wc_create_page( 'my-account', 'woocommerce_myaccount_page_id', __( 'My Account', 'nura-beauty' ), '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->' );
		}
	}
}

/**
 * Create product categories. Returns [key => term_id].
 */
function nura_import_categories() {
	$ids = array();
	foreach ( nura_sample_categories() as $slug => $name ) {
		$term = term_exists( $slug, 'product_cat' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		}
		if ( ! is_wp_error( $term ) ) {
			$ids[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		}
	}
	return $ids;
}

/**
 * Register global attributes + terms so variations have proper taxonomies.
 */
function nura_import_attributes() {
	if ( ! function_exists( 'wc_create_attribute' ) ) {
		return;
	}
	foreach ( nura_sample_attributes() as $slug => $data ) {
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		$id = wc_attribute_taxonomy_id_by_name( $slug );
		if ( ! $id ) {
			$id = wc_create_attribute( array(
				'name'         => $data['label'],
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			) );
			// Register the taxonomy now so we can insert terms in the same request.
			register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false ) );
		}
		foreach ( $data['terms'] as $t ) {
			if ( ! term_exists( $t, $taxonomy ) ) {
				wp_insert_term( $t, $taxonomy );
			}
		}
	}
	delete_transient( 'wc_attribute_taxonomies' );
}

/**
 * Create the demo products (simple + variable with per-variation SKU/price/stock).
 */
function nura_import_products( $cat_ids ) {
	if ( ! class_exists( 'WC_Product_Variable' ) ) {
		return;
	}
	$idx = 0;
	foreach ( nura_sample_products() as $spec ) {
		$idx++;
		// Skip if a product with this title already exists.
		$existing = get_page_by_title( $spec['name'], OBJECT, 'product' );
		if ( $existing ) {
			continue;
		}

		$cat_term_ids = array();
		foreach ( (array) $spec['cats'] as $ck ) {
			if ( isset( $cat_ids[ $ck ] ) ) {
				$cat_term_ids[] = $cat_ids[ $ck ];
			}
		}

		if ( 'simple' === $spec['type'] ) {
			$product = new WC_Product_Simple();
			$product->set_regular_price( $spec['price'] );
			$product->set_sku( 'NURA-' . str_pad( $idx, 3, '0', STR_PAD_LEFT ) );
		} else {
			$product = new WC_Product_Variable();
		}

		$product->set_name( $spec['name'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_short_description( $spec['short'] );
		$product->set_description( $spec['desc'] . "\n\nVerified human hair. Ships with provenance certificate and written guarantee. Pay with M-Pesa, card, PayPal or on delivery. NURA Flex instalments available." );
		$product->set_category_ids( $cat_term_ids );
		if ( ! empty( $spec['featured'] ) ) {
			$product->set_featured( true );
		}
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );

		if ( 'variable' === $spec['type'] ) {
			$attributes = array();
			foreach ( $spec['attributes'] as $slug => $options ) {
				$taxonomy = wc_attribute_taxonomy_name( $slug );
				$term_ids = array();
				foreach ( $options as $opt ) {
					$term = term_exists( $opt, $taxonomy );
					if ( ! $term ) {
						$term = wp_insert_term( $opt, $taxonomy );
					}
					if ( ! is_wp_error( $term ) ) {
						$term_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
					}
				}
				$attr = new WC_Product_Attribute();
				$attr->set_id( wc_attribute_taxonomy_id_by_name( $slug ) );
				$attr->set_name( $taxonomy );
				$attr->set_options( $term_ids );
				$attr->set_visible( true );
				$attr->set_variation( true );
				$attributes[] = $attr;
			}
			$product->set_attributes( $attributes );
			$parent_id = $product->save();

			// Build variations across the (capped) cartesian product of attributes.
			$attr_slugs = array_keys( $spec['attributes'] );
			$combos = nura_cartesian( $spec['attributes'] );
			$combos = array_slice( $combos, 0, 24 ); // keep the demo lean
			$v = 0;
			foreach ( $combos as $combo ) {
				$v++;
				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $parent_id );
				$va = array();
				$price = 0;
				foreach ( $combo as $slug => $value ) {
					$taxonomy = wc_attribute_taxonomy_name( $slug );
					$va[ $taxonomy ] = sanitize_title( $value );
					if ( 'length' === $slug && isset( $spec['variation_price'][ $value ] ) ) {
						$price = $spec['variation_price'][ $value ];
					}
				}
				if ( ! $price ) {
					$price = is_array( $spec['variation_price'] ) ? reset( $spec['variation_price'] ) : 12000;
				}
				$variation->set_attributes( $va );
				$variation->set_regular_price( (string) $price );
				$variation->set_sku( 'NURA-' . str_pad( $idx, 3, '0', STR_PAD_LEFT ) . '-' . str_pad( $v, 2, '0', STR_PAD_LEFT ) );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( wp_rand( 3, 25 ) );
				$variation->set_stock_status( 'instock' );
				$variation->save();
			}
			$product = wc_get_product( $parent_id );
			$product->save();
		} else {
			$product->save();
		}
	}
	// Flush so shop shows products.
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}
}

/**
 * Cartesian product helper for variation combinations.
 */
function nura_cartesian( $input ) {
	$result = array( array() );
	foreach ( $input as $key => $values ) {
		$append = array();
		foreach ( $result as $row ) {
			foreach ( $values as $value ) {
				$append[] = $row + array( $key => $value );
			}
		}
		$result = $append;
	}
	return $result;
}

/**
 * Build and assign primary + footer menus.
 */
function nura_build_menus( $page_ids ) {
	$shop_url = class_exists( 'WooCommerce' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );

	// Primary menu.
	$primary = nura_get_or_create_menu( 'NURA Primary' );
	if ( $primary ) {
		nura_reset_menu( $primary );
		nura_add_menu_link( $primary, 'Home', home_url( '/' ) );
		nura_add_menu_link( $primary, 'Shop', $shop_url );
		if ( isset( $page_ids['about-us'] ) ) { nura_add_menu_page( $primary, $page_ids['about-us']['id'] ); }
		if ( isset( $page_ids['installation'] ) ) { nura_add_menu_page( $primary, $page_ids['installation']['id'] ); }
		if ( isset( $page_ids['book-appointment'] ) ) { nura_add_menu_page( $primary, $page_ids['book-appointment']['id'] ); }
		if ( isset( $page_ids['contact-us'] ) ) { nura_add_menu_page( $primary, $page_ids['contact-us']['id'] ); }
	}

	// Footer menus by group.
	$help    = nura_get_or_create_menu( 'NURA Footer Help' );
	$company = nura_get_or_create_menu( 'NURA Footer Company' );
	$shop    = nura_get_or_create_menu( 'NURA Footer Shop' );
	foreach ( array( $help, $company, $shop ) as $m ) { if ( $m ) { nura_reset_menu( $m ); } }

	foreach ( $page_ids as $slug => $info ) {
		if ( 'help' === $info['menu'] && $help ) {
			nura_add_menu_page( $help, $info['id'] );
		} elseif ( 'company' === $info['menu'] && $company ) {
			nura_add_menu_page( $company, $info['id'] );
		}
	}
	if ( $shop ) {
		nura_add_menu_link( $shop, 'All Wigs', $shop_url );
		nura_add_menu_link( $shop, 'AI Wig Finder', home_url( '/ai-wig-finder/' ) );
		nura_add_menu_link( $shop, 'Virtual Try-On', home_url( '/virtual-try-on/' ) );
		nura_add_menu_link( $shop, 'The NURA Circle', home_url( '/nura-circle/' ) );
	}

	// Assign to theme locations.
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( $primary ) { $locations['primary'] = $primary; $locations['mobile'] = $primary; }
	if ( $shop )    { $locations['footer_shop'] = $shop; }
	if ( $help )    { $locations['footer_help'] = $help; }
	if ( $company ) { $locations['footer_company'] = $company; }
	set_theme_mod( 'nav_menu_locations', $locations );
}

function nura_get_or_create_menu( $name ) {
	$menu = wp_get_nav_menu_object( $name );
	if ( ! $menu ) {
		$id = wp_create_nav_menu( $name );
		return is_wp_error( $id ) ? 0 : $id;
	}
	return $menu->term_id;
}

function nura_reset_menu( $menu_id ) {
	$items = wp_get_nav_menu_items( $menu_id );
	if ( $items ) {
		foreach ( $items as $item ) {
			wp_delete_post( $item->ID, true );
		}
	}
}

function nura_add_menu_page( $menu_id, $page_id ) {
	wp_update_nav_menu_item( $menu_id, 0, array(
		'menu-item-title'     => get_the_title( $page_id ),
		'menu-item-object'    => 'page',
		'menu-item-object-id' => $page_id,
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
	) );
}

function nura_add_menu_link( $menu_id, $title, $url ) {
	wp_update_nav_menu_item( $menu_id, 0, array(
		'menu-item-title'  => $title,
		'menu-item-url'    => $url,
		'menu-item-type'   => 'custom',
		'menu-item-status' => 'publish',
	) );
}
