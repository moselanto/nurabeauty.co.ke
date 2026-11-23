<?php
/**
 * NURA Experience 1.124.0 - labels for the Meta (Facebook / Instagram) catalogue.
 *
 * Adds two custom labels to every item WooCommerce by Meta sends to the catalogue
 * (each variation is its own item, so the labels are worked out from the parent):
 *
 *   custom_label_0 = "From KSh 7,000" - the cheapest option of that product
 *                    (variable products), or "KSh 3,000" when there is one price.
 *                    Carousel card description: {{product.custom_label_0}}.
 *   custom_label_1 = "wig" or "not-wig". Set rule "Wigs only": custom label 1 is "wig".
 *                    A wig = in the Wigs category tree, not in Wig care, and the name
 *                    does not say "Hair Closure" (the loose closures). Override per
 *                    product with the meta _nura_meta_wig = yes / no.
 *
 * 1.124.3: the scheduled feed file only carries custom_label_4 and internal_label, so the
 * From price is also sent as custom_label_4 and the wig flag as internal label nura_wig /
 * nura_not_wig. Carousel description: {{product.custom_label_4}}; set rule: internal label nura_wig.
 *
 * Filters: nura_meta_from_label, nura_meta_is_wig.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Meta_Labels {

	public function __construct() {
		add_filter( 'facebook_for_woocommerce_integration_prepare_product', array( $this, 'labels' ), 50, 2 );
		add_action( 'admin_post_nurax_meta_labels_preview', array( $this, 'preview' ) );
	}

	/** Parent product for a variation, or the product itself. */
	private static function base( $id ) {
		$p = wc_get_product( $id );
		if ( $p && $p->is_type( 'variation' ) ) {
			$p = wc_get_product( $p->get_parent_id() );
		}
		return $p;
	}

	public static function money( $n ) {
		return 'KSh ' . number_format( (float) $n, 0, '.', ',' );
	}

	/** "From KSh 7,000" / "KSh 3,000" / ''. */
	public static function from_label( $p ) {
		if ( ! $p ) {
			return '';
		}
		$label = '';
		if ( $p->is_type( 'variable' ) ) {
			$min = (float) $p->get_variation_price( 'min', true );
			$max = (float) $p->get_variation_price( 'max', true );
			if ( $min > 0 ) {
				$label = ( $max > $min ? 'From ' : '' ) . self::money( $min );
			}
		} else {
			$pr = (float) wc_get_price_to_display( $p );
			if ( $pr > 0 ) {
				$label = self::money( $pr );
			}
		}
		return (string) apply_filters( 'nura_meta_from_label', $label, $p );
	}

	/** Wig or not. */
	public static function is_wig( $p ) {
		if ( ! $p ) {
			return false;
		}
		$id  = $p->get_id();
		$ovr = strtolower( (string) get_post_meta( $id, '_nura_meta_wig', true ) );
		if ( in_array( $ovr, array( 'yes', 'no' ), true ) ) {
			return (bool) apply_filters( 'nura_meta_is_wig', 'yes' === $ovr, $p );
		}
		$wigs = get_term_by( 'slug', 'wigs', 'product_cat' );
		$care = get_term_by( 'slug', 'wig-care', 'product_cat' );
		$in   = function ( $root ) use ( $id ) {
			if ( ! $root ) {
				return false;
			}
			$ids = array_merge( array( (int) $root->term_id ), array_map( 'intval', (array) get_term_children( $root->term_id, 'product_cat' ) ) );
			return has_term( $ids, 'product_cat', $id );
		};
		$wig = false;
		if ( $wigs ) {
			$wig = $in( $wigs );
		} else {
			foreach ( (array) wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'slugs' ) ) as $slug ) {
				if ( preg_match( '/-wigs$/', (string) $slug ) ) {
					$wig = true;
				}
			}
		}
		if ( $wig && $in( $care ) ) {
			$wig = false;
		}
		if ( $wig && preg_match( '/hair\s+closure/i', $p->get_name() ) ) {
			$wig = false; // Loose closures (2x2 / 4x4 Hair Closure) are not wigs.
		}
		return (bool) apply_filters( 'nura_meta_is_wig', $wig, $p );
	}

	public function labels( $data, $id ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$p = self::base( $id );
		if ( ! $p ) {
			return $data;
		}
		$from = self::from_label( $p );
		$wig  = self::is_wig( $p );
		if ( '' !== $from ) {
			$data['custom_label_0'] = $from;
			// 1.124.3: the daily feed file from WooCommerce by Meta only has a custom_label_4
			// column (no 0-3), so the From price also goes in custom_label_4, which survives it.
			$data['custom_label_4'] = $from;
		}
		$data['custom_label_1'] = $wig ? 'wig' : 'not-wig';
		// 1.124.3: internal label (feed column internal_label, and the API): "nura_wig" / "nura_not_wig".
		$labels = isset( $data['internal_label'] ) && is_array( $data['internal_label'] ) ? $data['internal_label'] : array();
		$labels = array_values( array_filter( $labels, function ( $l ) {
			return '' !== (string) $l && 0 !== strpos( (string) $l, 'nura_' );
		} ) );
		$labels[] = $wig ? 'nura_wig' : 'nura_not_wig';
		$data['internal_label'] = $labels;
		return $data;
	}

	/** Admin check: wp-admin/admin-post.php?action=nurax_meta_labels_preview lists the labels for every published product. */
	public function preview() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'No permission' );
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		$ids = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ) );
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			echo esc_html( $id . ' | ' . $p->get_name() . ' | ' . self::from_label( $p ) . ' | ' . ( self::is_wig( $p ) ? 'nura_wig' : 'nura_not_wig' ) ) . "\n";
		}
		exit;
	}
}
