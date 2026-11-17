<?php
/**
 * Theme 1.161.24: lengths and prices (owner, 17 Nov 2026).
 *
 *  Human Hair Bob (#318): every existing colour now comes in 10-20 inches.
 *    10" 7,000 | 12" 8,000 | 14" 9,200 | 16" 10,500 | 18" 11,800 | 20" 13,000.
 *    The existing colour variations are kept (photos, buying price, order history)
 *    and become the 12 inch option; the other lengths are added per colour.
 *  Black Jerry Curl (#265): simple -> variable with lengths
 *    12" 9,000 | 16" 10,500 | 18" 12,000 | 20" 16,500.
 *
 * One-time data update, run the first time an admin opens wp-admin after this
 * theme is installed (option nura_len_2026_done, log nura_len_2026_log).
 * To re-run, delete the nura_len_2026_done option.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Product ID => array( inches => price KES ). */
function nura_len_2026_prices() {
	return array(
		318 => array( 10 => 7000, 12 => 8000, 14 => 9200, 16 => 10500, 18 => 11800, 20 => 13000 ),
		265 => array( 12 => 9000, 16 => 10500, 18 => 12000, 20 => 16500 ),
	);
}

/** Get or create the pa_length term "N inches". */
function nura_len_2026_term( $n ) {
	$name = $n . ' inches';
	$t    = get_term_by( 'name', $name, 'pa_length' );
	if ( ! $t ) {
		$r = wp_insert_term( $name, 'pa_length' );
		if ( is_wp_error( $r ) ) {
			return null;
		}
		$t = get_term( $r['term_id'], 'pa_length' );
	}
	return ( $t && ! is_wp_error( $t ) ) ? $t : null;
}

/** Put the given Length terms on the product as a variation attribute. */
function nura_len_2026_set_attr( $product, $terms ) {
	$attrs = $product->get_attributes();
	foreach ( $attrs as $key => $a ) {
		if ( 'pa_length' !== $key && 'length' === strtolower( (string) $key ) ) {
			unset( $attrs[ $key ] ); // Old local Length attribute.
		}
	}
	$ids = array();
	foreach ( $terms as $t ) {
		$ids[] = (int) $t->term_id;
	}
	$is_new = ! isset( $attrs['pa_length'] );
	$a      = $is_new ? new WC_Product_Attribute() : $attrs['pa_length'];
	$a->set_id( wc_attribute_taxonomy_id_by_name( 'pa_length' ) );
	$a->set_name( 'pa_length' );
	$a->set_options( $ids );
	$a->set_visible( true );
	$a->set_variation( true );
	if ( $is_new ) {
		$a->set_position( count( $attrs ) );
	}
	$attrs['pa_length'] = $a;
	$product->set_attributes( $attrs );
	wp_set_object_terms( $product->get_id(), $ids, 'pa_length' );
}

/** Human Hair Bob: lengths for every colour. */
function nura_len_2026_bob( &$log ) {
	$pid    = 318;
	$prices = nura_len_2026_prices()[ $pid ];
	$p      = wc_get_product( $pid );
	if ( ! $p || ! $p->is_type( 'variable' ) ) {
		$log[] = '#318 not found or not variable - skipped';
		return;
	}
	$terms = array();
	foreach ( $prices as $n => $price ) {
		$t = nura_len_2026_term( $n );
		if ( ! $t ) {
			$log[] = '#318 could not create term ' . $n . ' inches - skipped';
			return;
		}
		$terms[ $n ] = $t;
	}
	$children = $p->get_children();
	nura_len_2026_set_attr( $p, $terms );
	$p->save();

	$ci = 0;
	foreach ( $children as $vid ) {
		$v = wc_get_product( $vid );
		if ( ! $v || ! $v->is_type( 'variation' ) ) {
			continue;
		}
		$va = $v->get_attributes();
		if ( ! empty( $va['pa_length'] ) ) {
			continue; // Already has a length.
		}
		$cost  = get_post_meta( $vid, '_nura_cost', true );
		$li    = 0;
		foreach ( $prices as $n => $price ) {
			$attrs              = $va;
			$attrs['pa_length'] = $terms[ $n ]->slug;
			if ( 12 === $n ) {
				$v->set_attributes( $attrs );
				$v->set_regular_price( (string) $price );
				$v->set_sale_price( '' );
				$v->set_menu_order( $ci * 10 + $li );
				$v->save();
				$log[] = '#318 variation ' . $vid . ' (' . implode( '/', $va ) . ') -> 12 inches KSh ' . $price;
			} else {
				$nv = new WC_Product_Variation();
				$nv->set_parent_id( $pid );
				$nv->set_attributes( $attrs );
				$nv->set_regular_price( (string) $price );
				$nv->set_status( 'publish' );
				$nv->set_image_id( $v->get_image_id() );
				$nv->set_description( $v->get_description() );
				$nv->set_manage_stock( false );
				$nv->set_stock_status( 'instock' );
				$nv->set_menu_order( $ci * 10 + $li );
				$nid = $nv->save();
				if ( $nid && '' !== $cost ) {
					update_post_meta( $nid, '_nura_cost', $cost );
				}
				$log[] = '#318 new variation ' . $nid . ' (' . implode( '/', $va ) . ') ' . $n . ' inches KSh ' . $price;
			}
			$li++;
		}
		$ci++;
	}
	WC_Product_Variable::sync( $pid );
	wc_delete_product_transients( $pid );
}

/** Black Jerry Curl: simple -> variable with lengths. */
function nura_len_2026_jerry( &$log ) {
	$pid    = 265;
	$prices = nura_len_2026_prices()[ $pid ];
	$p      = wc_get_product( $pid );
	if ( ! $p ) {
		$log[] = '#265 not found - skipped';
		return;
	}
	$cost  = get_post_meta( $pid, '_nura_cost', true );
	$image = $p->get_image_id();
	if ( $p->is_type( 'simple' ) ) {
		wp_set_object_terms( $pid, 'variable', 'product_type' );
		wc_delete_product_transients( $pid );
		clean_post_cache( $pid );
		$p     = new WC_Product_Variable( $pid );
		$log[] = '#265 changed from simple to variable';
	}
	if ( ! $p->is_type( 'variable' ) ) {
		$log[] = '#265 is not variable - skipped';
		return;
	}
	if ( count( $p->get_children() ) ) {
		$log[] = '#265 already has variations - skipped';
		return;
	}
	$terms = array();
	foreach ( $prices as $n => $price ) {
		$t = nura_len_2026_term( $n );
		if ( ! $t ) {
			$log[] = '#265 could not create term ' . $n . ' inches - skipped';
			return;
		}
		$terms[ $n ] = $t;
	}
	nura_len_2026_set_attr( $p, $terms );
	$p->save();
	$i = 0;
	foreach ( $prices as $n => $price ) {
		$nv = new WC_Product_Variation();
		$nv->set_parent_id( $pid );
		$nv->set_attributes( array( 'pa_length' => $terms[ $n ]->slug ) );
		$nv->set_regular_price( (string) $price );
		$nv->set_status( 'publish' );
		$nv->set_image_id( $image );
		$nv->set_manage_stock( false );
		$nv->set_stock_status( 'instock' );
		$nv->set_menu_order( $i++ );
		$nid = $nv->save();
		if ( $nid && '' !== $cost ) {
			update_post_meta( $nid, '_nura_cost', $cost );
		}
		$log[] = '#265 new variation ' . $nid . ' ' . $n . ' inches KSh ' . $price;
	}
	delete_post_meta( $pid, '_regular_price' );
	delete_post_meta( $pid, '_sale_price' );
	WC_Product_Variable::sync( $pid );
	wc_delete_product_transients( $pid );
}

/** Add one "Lengths" line to the short description. */
function nura_len_2026_short_desc( $pid, &$log ) {
	$p = wc_get_product( $pid );
	if ( ! $p ) {
		return;
	}
	$sd = (string) $p->get_short_description();
	if ( false !== strpos( $sd, 'nura-len-2026' ) ) {
		return;
	}
	$parts = array();
	foreach ( nura_len_2026_prices()[ $pid ] as $n => $price ) {
		$parts[] = $n . ' inches - KSh ' . number_format( $price );
	}
	$sd .= "\n" . '<p class="nura-len-2026"><strong>Lengths:</strong> ' . esc_html( implode( '; ', $parts ) ) . '.</p>';
	$p->set_short_description( $sd );
	$p->save();
	$log[] = '#' . $pid . ' short description: lengths line added';
}

add_action( 'admin_init', 'nura_len_2026_migrate', 40 );
function nura_len_2026_migrate() {
	if ( get_option( 'nura_len_2026_done' ) || ! current_user_can( 'manage_woocommerce' ) || ! class_exists( 'WC_Product_Variation' ) || ! taxonomy_exists( 'pa_length' ) ) {
		return;
	}
	update_option( 'nura_len_2026_done', time(), false );
	$log = array();
	nura_len_2026_bob( $log );
	nura_len_2026_jerry( $log );
	nura_len_2026_short_desc( 318, $log );
	nura_len_2026_short_desc( 265, $log );
	update_option( 'nura_len_2026_log', $log, false );
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$log = get_option( 'nura_len_2026_log' );
	if ( ! is_array( $log ) || ! isset( $_GET['nura_len_log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	echo '<div class="notice notice-info"><p><strong>NURA lengths update (17 Nov 2026)</strong></p><ul>';
	foreach ( $log as $line ) {
		echo '<li>' . esc_html( (string) $line ) . '</li>';
	}
	echo '</ul></div>';
} );
