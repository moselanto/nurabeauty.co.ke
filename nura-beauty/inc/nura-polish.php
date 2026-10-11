<?php
/**
 * NURA Beauty v1.34.0 - storefront polish.
 *
 * - Hair-type label on every product card (100% Human Hair / Blend / Fibre), read from
 *   the Hair Type attribute first, then the category, then the product name. Products
 *   with no hair type (care, beauty) get an empty placeholder so cards stay aligned.
 * - Footer menus never repeat a link that an earlier footer column already shows.
 * - Community photos helper for the homepage "Real women. Real crowns." section.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nura_hair_types' ) ) {
	/**
	 * Hair types for a product, as keys: human, blend, fibre (in that order).
	 *
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	function nura_hair_types( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return array();
		}
		$id    = $product->get_id();
		$names = array();
		if ( taxonomy_exists( 'pa_hair-type' ) && function_exists( 'wc_get_product_terms' ) ) {
			$names = wc_get_product_terms( $id, 'pa_hair-type', array( 'fields' => 'names' ) );
		}
		if ( empty( $names ) ) {
			$raw = (string) $product->get_attribute( 'hair-type' );
			if ( '' !== $raw ) {
				$names = array_map( 'trim', preg_split( '/[,|]/', $raw ) );
			}
		}
		$found = array();
		foreach ( (array) $names as $n ) {
			$t = nura_hair_type_from_text( $n );
			if ( $t ) {
				$found[ $t ] = true;
			}
		}
		if ( empty( $found ) ) {
			if ( has_term( 'human-hair-blend-wigs', 'product_cat', $id ) ) {
				$found['blend'] = true;
			} elseif ( has_term( 'human-hair-wigs', 'product_cat', $id ) ) {
				$found['human'] = true;
			} else {
				$t = nura_hair_type_from_text( $product->get_name() );
				if ( $t ) {
					$found[ $t ] = true;
				}
			}
		}
		$order = array( 'human', 'blend', 'fibre' );
		return array_values( array_filter( $order, function ( $k ) use ( $found ) { return isset( $found[ $k ] ); } ) );
	}
}

if ( ! function_exists( 'nura_hair_type_from_text' ) ) {
	/** Map a label or product name to human / blend / fibre ('' when unclear). */
	function nura_hair_type_from_text( $text ) {
		$t = strtolower( (string) $text );
		if ( '' === $t ) {
			return '';
		}
		if ( preg_match( '/semi[\s-]*human|blend/', $t ) ) {
			return 'blend';
		}
		if ( false !== strpos( $t, 'human hair' ) || false !== strpos( $t, 'human-hair' ) ) {
			return 'human';
		}
		if ( preg_match( '/synthetic|fib(re|er)|heat[\s-]*(resistant|friendly)/', $t ) ) {
			return 'fibre';
		}
		return '';
	}
}

if ( ! function_exists( 'nura_hair_type_label_text' ) ) {
	function nura_hair_type_label_text( $key ) {
		$labels = array(
			'human' => __( '100% Human Hair', 'nura-beauty' ),
			'blend' => __( 'Human-Hair Blend', 'nura-beauty' ),
			'fibre' => __( 'Fibre', 'nura-beauty' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}
}

/** Card label, right above the price (dots are at 9, price at 10). */
add_action( 'woocommerce_after_shop_loop_item_title', 'nura_loop_hair_type', 8 );
function nura_loop_hair_type() {
	global $product;
	$types = nura_hair_types( $product );
	if ( empty( $types ) ) {
		echo '<div class="nura-hairtype nura-hairtype--empty" aria-hidden="true"></div>';
		return;
	}
	$parts = array();
	foreach ( $types as $k ) {
		$parts[] = '<span class="nura-hairtype__tag nura-hairtype__tag--' . esc_attr( $k ) . '">' . esc_html( nura_hair_type_label_text( $k ) ) . '</span>';
	}
	echo '<div class="nura-hairtype" title="' . esc_attr__( 'Hair type', 'nura-beauty' ) . '">' . implode( '', $parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}

/** Footer: drop a link an earlier footer column already showed (same URL or same label). */
add_filter( 'wp_nav_menu_objects', 'nura_footer_dedupe', 20, 2 );
function nura_footer_dedupe( $items, $args ) {
	static $seen = array();
	$loc = isset( $args->theme_location ) ? (string) $args->theme_location : '';
	if ( ! in_array( $loc, array( 'footer_shop', 'footer_help', 'footer_company' ), true ) ) {
		return $items;
	}
	$out = array();
	foreach ( (array) $items as $it ) {
		$path  = (string) wp_parse_url( (string) $it->url, PHP_URL_PATH );
		$path  = strtolower( untrailingslashit( $path ) );
		$title = 't:' . strtolower( trim( wp_strip_all_tags( (string) $it->title ) ) );
		$ukey  = 'u:' . $path . '?' . (string) wp_parse_url( (string) $it->url, PHP_URL_QUERY );
		if ( ( '' !== $path && isset( $seen[ $ukey ] ) ) || isset( $seen[ $title ] ) ) {
			continue;
		}
		if ( '' !== $path ) {
			$seen[ $ukey ] = true;
		}
		$seen[ $title ] = true;
		$out[]          = $it;
	}
	return $out;
}

if ( ! function_exists( 'nura_community_photo_ids' ) ) {
	/** Attachment IDs chosen under WooCommerce > NURA Reviews & Photos. */
	function nura_community_photo_ids( $limit = 8 ) {
		$ids = get_option( 'nurax_community_photos', array() );
		if ( is_string( $ids ) ) {
			$ids = explode( ',', $ids );
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ), function ( $id ) {
			return $id && wp_attachment_is_image( $id );
		} ) );
		return array_slice( $ids, 0, max( 1, (int) $limit ) );
	}
}
