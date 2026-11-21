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
 * Filters: nura_meta_from_label, nura_meta_is_wig. custom_label_4 is left alone
 * (already used in the catalogue).
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Meta_Labels {

	public function __construct() {
		add_filter( 'facebook_for_woocommerce_integration_prepare_product', array( $this, 'labels' ), 50, 2 );
		add_action( 'admin_post_nurax_meta_labels_preview', array( $this, 'preview' ) );
		add_action( 'admin_menu', array( $this, 'cleanup_menu' ), 99 );
		add_action( 'admin_post_nurax_meta_cleanup', array( $this, 'cleanup_run' ) );
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
		if ( '' !== $from ) {
			$data['custom_label_0'] = $from;
		}
		$data['custom_label_1'] = self::is_wig( $p ) ? 'wig' : 'not-wig';
		return $data;
	}

	/**
	 * 1.124.1: Tools > NURA catalogue cleanup. Deletes the old switched-off Loose Wave
	 * Frontal options that the variation Remove link would not delete. Only touches IDs
	 * in this list, only when each is a disabled (private) variation of product 364.
	 */
	private static function cleanup_ids() {
		return array( 366, 370, 371, 374, 812, 813 );
	}

	public function cleanup_menu() {
		add_management_page( 'NURA catalogue cleanup', 'NURA catalogue cleanup', 'manage_woocommerce', 'nurax-meta-cleanup', array( $this, 'cleanup_page' ) );
	}

	public function cleanup_page() {
		echo '<div class="wrap"><h1>NURA catalogue cleanup</h1>';
		if ( isset( $_GET['done'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ) . '</p></div>'; // phpcs:ignore
		}
		echo '<p>Old switched-off Loose Wave Frontal options (product 364):</p><ul>';
		foreach ( self::cleanup_ids() as $id ) {
			$v = wc_get_product( $id );
			echo '<li>' . esc_html( $id . ': ' . ( $v ? $v->get_name() . ' - status ' . get_post_status( $id ) . ' - parent ' . $v->get_parent_id() : 'not found (already deleted)' ) ) . '</li>';
		}
		echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nurax_meta_cleanup">';
		wp_nonce_field( 'nurax_meta_cleanup' );
		echo '<button class="button button-primary">Delete these old options</button></form></div>';
	}

	public function cleanup_run() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'nurax_meta_cleanup' ) ) {
			wp_die( 'No permission' );
		}
		$out = array();
		foreach ( self::cleanup_ids() as $id ) {
			$v = wc_get_product( $id );
			if ( ! $v ) {
				$out[] = $id . ' already gone';
				continue;
			}
			if ( ! $v->is_type( 'variation' ) || 364 !== (int) $v->get_parent_id() || 'private' !== get_post_status( $id ) ) {
				$out[] = $id . ' skipped (not a disabled Loose Wave option)';
				continue;
			}
			do_action( 'woocommerce_before_delete_product_variation', $id );
			$v->delete( true );
			$out[] = $id . ' deleted';
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( 364 );
		}
		WC_Product_Variable::sync( 364 );
		wp_safe_redirect( add_query_arg( array( 'page' => 'nurax-meta-cleanup', 'done' => rawurlencode( implode( '; ', $out ) ) ), admin_url( 'tools.php' ) ) );
		exit;
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
			echo esc_html( $id . ' | ' . $p->get_name() . ' | ' . self::from_label( $p ) . ' | ' . ( self::is_wig( $p ) ? 'wig' : 'not-wig' ) ) . "\n";
		}
		exit;
	}
}
