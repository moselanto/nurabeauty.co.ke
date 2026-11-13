<?php
/**
 * Theme 1.131.0: hair types in words Kenyan shoppers use (owner, 13 Nov 2026).
 *
 *  Pure Human  - 100% human hair.
 *  Semi Human  - human hair mixed with good-quality fibre. Low heat is fine.
 *  Human Blend - kept as its own label (Bob Wavy). Low heat is fine.
 *  Two bone straight units carry no hair type (owner's choice) and are renamed.
 *
 * One-time data update, run the first time an admin opens wp-admin after this
 * theme is installed: Hair Type attribute, product names, product text (short
 * description, description, Rank Math title/description) and the hair-type
 * categories. A log is kept in the option nura_ht_2026_log.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Product ID => hair type key: pure | semi | blend | none. */
function nura_ht_map() {
	return array(
		2010 => 'pure', 255 => 'pure', 2009 => 'pure', 318 => 'pure', 265 => 'pure', 396 => 'pure',
		2011 => 'semi', 386 => 'semi', 377 => 'semi', 360 => 'semi', 2012 => 'semi', 356 => 'semi',
		416 => 'semi', 404 => 'semi', 389 => 'semi', 250 => 'semi', 364 => 'semi', 419 => 'semi',
		359 => 'semi', 271 => 'semi', 342 => 'semi', 243 => 'semi',
		266 => 'blend',
		274 => 'none', 251 => 'none',
	);
}

function nura_ht_term_names() {
	return array( 'pure' => 'Pure Human', 'semi' => 'Semi Human', 'blend' => 'Human Blend' );
}

/** Text replacements per hair type (order matters: longest first). */
function nura_ht_replacements( $key ) {
	$heat = array( 'with low to medium heat' => 'with low heat', 'Use low to medium heat only' => 'Use low heat only' );
	if ( 'pure' === $key ) {
		return array(
			'>Premium heat-resistant fibre<' => '>Pure Human (100% human hair)<',
			'>100% human hair<'              => '>Pure Human (100% human hair)<',
			'a premium heat-resistant fibre' => 'a Pure Human (100% human hair)',
			'A premium heat-resistant fibre' => 'A Pure Human (100% human hair)',
			'Premium heat-resistant fibre'   => 'Pure Human (100% human hair)',
			'premium heat-resistant fibre'   => 'Pure Human (100% human hair)',
			'a 100% human hair'              => 'a Pure Human (100% human hair)',
			'A 100% human hair'              => 'A Pure Human (100% human hair)',
		);
	}
	if ( 'semi' === $key ) {
		return array(
			'>Premium heat-resistant fibre<'  => '>Semi Human (human hair mixed with good-quality fibre)<',
			'>Human-hair blend (semi-human)<' => '>Semi Human (human hair mixed with good-quality fibre)<',
			'a premium heat-resistant fibre'  => 'a Semi Human',
			'A premium heat-resistant fibre'  => 'A Semi Human',
			'Premium heat-resistant fibre'    => 'Semi Human',
			'premium heat-resistant fibre'    => 'Semi Human',
			'a human-hair blend (semi-human)' => 'a Semi Human',
			'A human-hair blend (semi-human)' => 'A Semi Human',
			'Human-hair blend (semi-human)'   => 'Semi Human',
			'human-hair blend (semi-human)'   => 'Semi Human',
		) + $heat;
	}
	if ( 'blend' === $key ) {
		return array(
			'>Human-hair blend (semi-human)<' => '>Human Blend<',
			'a human-hair blend (semi-human)' => 'a Human Blend',
			'A human-hair blend (semi-human)' => 'A Human Blend',
			'Human-hair blend (semi-human)'   => 'Human Blend',
			'human-hair blend (semi-human)'   => 'Human Blend',
		) + $heat;
	}
	return array(
		'a premium heat-resistant fibre' => 'a luxury',
		'A premium heat-resistant fibre' => 'A luxury',
		'Premium heat-resistant fibre'   => 'Luxury',
		'premium heat-resistant fibre'   => 'luxury',
	);
}

function nura_ht_renames() {
	return array(
		274 => array( 'Bone Straight Frontal', 'Luxury Fibre Bone Straight Frontal' ),
		251 => array( '4×4 Bone Straight Closure Wig', '4×4 Luxury Bone Straight Closure Wig' ),
	);
}

function nura_ht_fix_text( $text, $key, $id ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return $text;
	}
	$r = nura_ht_renames();
	if ( isset( $r[ $id ] ) ) {
		$old  = $r[ $id ][0];
		$text = str_replace( array( $old, str_replace( '×', '&#215;', $old ), str_replace( '×', 'x', $old ) ), $r[ $id ][1], $text );
		$text = str_replace( 'Luxury Fibre Luxury Fibre', 'Luxury Fibre', $text );
		// No hair type row for these two (owner).
		$text = preg_replace( '#<tr[^>]*>\s*<t[hd][^>]*>\s*(<strong>)?\s*Hair type\s*(</strong>)?\s*</t[hd]>\s*<t[hd][^>]*>.*?</t[hd]>\s*</tr>#is', '', $text );
	}
	return strtr( $text, nura_ht_replacements( $key ) );
}

add_action( 'admin_init', 'nura_ht_migrate', 30 );
function nura_ht_migrate() {
	if ( get_option( 'nura_ht_2026_done' ) || ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'wc_get_product' ) || ! taxonomy_exists( 'pa_hair-type' ) ) {
		return;
	}
	update_option( 'nura_ht_2026_done', time(), false ); // Set first: never runs twice, even on a timeout.
	$log   = array();
	$names = nura_ht_term_names();
	$tids  = array();
	foreach ( $names as $k => $n ) {
		$t = get_term_by( 'name', $n, 'pa_hair-type' );
		if ( ! $t ) {
			$res = wp_insert_term( $n, 'pa_hair-type' );
			$tid = is_wp_error( $res ) ? 0 : (int) $res['term_id'];
		} else {
			$tid = (int) $t->term_id;
		}
		$tids[ $k ] = $tid;
	}
	$cat_human = get_term_by( 'slug', 'human-hair-wigs', 'product_cat' );
	$cat_blend = get_term_by( 'slug', 'human-hair-blend-wigs', 'product_cat' );
	$cat_fibre = get_term_by( 'name', 'High Fibre (Heat-Resistant) Wigs', 'product_cat' );
	if ( $cat_human ) {
		wp_update_term( $cat_human->term_id, 'product_cat', array( 'name' => 'Pure Human Wigs' ) );
	}
	if ( $cat_blend ) {
		wp_update_term( $cat_blend->term_id, 'product_cat', array( 'name' => 'Semi Human Wigs' ) );
	}
	$renames = nura_ht_renames();
	foreach ( nura_ht_map() as $id => $key ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			$log[] = $id . ': not found';
			continue;
		}
		// Hair Type attribute.
		$attrs = $p->get_attributes();
		if ( isset( $attrs['pa_hair-type'] ) && $attrs['pa_hair-type']->get_variation() ) {
			$log[] = $id . ': hair type is a variation attribute, left as is';
		} elseif ( 'none' === $key ) {
			unset( $attrs['pa_hair-type'] );
			$p->set_attributes( $attrs );
			wp_set_object_terms( $id, array(), 'pa_hair-type' );
		} elseif ( ! empty( $tids[ $key ] ) ) {
			if ( isset( $attrs['pa_hair-type'] ) ) {
				$a = $attrs['pa_hair-type'];
			} else {
				$a = new WC_Product_Attribute();
				$a->set_id( wc_attribute_taxonomy_id_by_name( 'pa_hair-type' ) );
				$a->set_name( 'pa_hair-type' );
				$a->set_visible( true );
				$a->set_variation( false );
			}
			$a->set_options( array( (int) $tids[ $key ] ) );
			$attrs['pa_hair-type'] = $a;
			$p->set_attributes( $attrs );
		}
		if ( isset( $renames[ $id ] ) ) {
			$p->set_name( $renames[ $id ][1] );
		}
		$p->set_short_description( nura_ht_fix_text( $p->get_short_description(), $key, $id ) );
		$p->set_description( nura_ht_fix_text( $p->get_description(), $key, $id ) );
		$p->save();
		if ( 'none' !== $key && ! empty( $tids[ $key ] ) ) {
			wp_set_object_terms( $id, array( (int) $tids[ $key ] ), 'pa_hair-type' );
		}
		foreach ( array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' ) as $mk ) {
			$v = (string) get_post_meta( $id, $mk, true );
			if ( '' !== $v ) {
				$nv = nura_ht_fix_text( $v, $key, $id );
				if ( $nv !== $v ) {
					update_post_meta( $id, $mk, $nv );
				}
			}
		}
		// Hair-type categories.
		$add = array();
		$rem = array();
		if ( 'pure' === $key ) {
			$add = array( $cat_human );
			$rem = array( $cat_blend, $cat_fibre );
		} elseif ( 'semi' === $key || 'blend' === $key ) {
			$add = array( $cat_blend );
			$rem = array( $cat_human, $cat_fibre );
		} elseif ( 251 === $id ) {
			$rem = array( $cat_human, $cat_blend, $cat_fibre );
		}
		foreach ( array_filter( $add ) as $c ) {
			wp_set_object_terms( $id, array( (int) $c->term_id ), 'product_cat', true );
		}
		foreach ( array_filter( $rem ) as $c ) {
			wp_remove_object_terms( $id, array( (int) $c->term_id ), 'product_cat' );
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
		$log[] = $id . ': ' . $key . ' ok';
	}
	foreach ( array( $cat_human, $cat_blend, $cat_fibre ) as $c ) {
		if ( $c ) {
			wp_update_term_count_now( array( (int) $c->term_id ), 'product_cat' );
		}
	}
	update_option( 'nura_ht_2026_log', $log, false );
	delete_transient( 'nurax_knowledge_tokens' );
	if ( function_exists( 'wc_delete_product_transients' ) ) {
		wc_delete_product_transients();
	}
	do_action( 'nura_ht_migrated' );
}

/** Small admin notice so the owner (and the next developer) can see it ran. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['page'] ) || 'nura-images' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$log = (array) get_option( 'nura_ht_2026_log', array() );
	if ( $log ) {
		echo '<div class="notice notice-info"><p><strong>Hair types updated:</strong> ' . esc_html( implode( ' | ', $log ) ) . '</p></div>';
	}
} );
