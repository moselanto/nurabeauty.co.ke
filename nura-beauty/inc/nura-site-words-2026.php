<?php
/**
 * Theme 1.132.0: site-wide hair-type words (owner, 13 Nov 2026) and a Human Blend category.
 *
 * Guides, price page, FAQ and other published posts/pages live in the database, so the
 * template changes alone do not reach them. This runs once on the first wp-admin load by
 * an admin and rewrites only the old hair-type phrases:
 *   100% human hair -> Pure Human, human-hair blend / semi-human -> Semi Human,
 *   heat-resistant fibre -> luxury fibre, and NURA price lines that said fibre is cheapest.
 * It also creates the "Human Blend Wigs" category (slug human-blend-wigs) and moves Bob Wavy
 * (266) there from Semi Human Wigs. Log: option nura_sw_2026_log (shown on Tools > NURA images).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_sw_rules() {
	return array(
		'/Human hair blend \(semi-human\) wigs/'                => 'Semi Human wigs',
		'/human[- ]hair blend \(semi[- ]human\)/i'               => 'Semi Human',
		'/semi-human blends/i'                                    => 'Semi Human wigs',
		'/semi-human blend/i'                                     => 'Semi Human',
		'/human[- ]hair blend/i'                                  => 'Semi Human',
		'/(?<!Pure Human \()100% human hair/i'                    => 'Pure Human',
		'/Heat-Resistant Fibre/'                                  => 'Luxury Fibre',
		'/Heat-resistant fibre/'                                  => 'Luxury fibre',
		'/heat-resistant fibre/'                                  => 'luxury fibre',
		'/Fibre \(heat-resistant\)/'                              => 'Luxury fibre',
		'/fibre \(heat-resistant\)/'                              => 'luxury fibre',
		'/(?<![-\/\'"\w{:=])[Ss]emi-[Hh]uman(?![-\w])/'           => 'Semi Human',
		'/Semi Human \(Semi Human\)/'                             => 'Semi Human',
		'/[Ll]uxury fibre wigs are the most affordable, Semi Human wigs sit in the middle, and Pure Human wigs cost the most/' => 'Semi Human wigs are the most affordable and Pure Human wigs cost the most',
		'/[Ll]uxury fibre wigs cost \{fibre_min\} to \{fibre_max\}, Semi Human wigs start at \{blend_min\},? and/' => 'Semi Human wigs start at {blend_min} and',
		'/human hair costs more than blend, and blend more than fibre\./' => 'Pure Human costs more than Semi Human or Human Blend.',
	);
}

function nura_sw_fix( $text ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return $text;
	}
	foreach ( nura_sw_rules() as $re => $to ) {
		$new = preg_replace( $re, $to, $text );
		if ( null !== $new ) {
			$text = $new;
		}
	}
	return $text;
}

add_action( 'admin_init', 'nura_sw_migrate', 31 );
function nura_sw_migrate() {
	if ( get_option( 'nura_sw_2026_done' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	update_option( 'nura_sw_2026_done', time(), false );
	global $wpdb;
	$log = array();
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post','page','nav_menu_item','wp_block') AND post_status IN ('publish','private','draft','future')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $ids as $id ) {
		$p = get_post( (int) $id );
		if ( ! $p ) {
			continue;
		}
		$upd = array( 'ID' => $p->ID );
		foreach ( array( 'post_content', 'post_title', 'post_excerpt' ) as $f ) {
			$n = nura_sw_fix( $p->$f );
			if ( $n !== $p->$f ) {
				$upd[ $f ] = $n;
			}
		}
		if ( count( $upd ) > 1 ) {
			kses_remove_filters();
			wp_update_post( wp_slash( $upd ) );
			kses_init_filters();
			$log[] = $p->ID . ' ' . $p->post_name;
		}
		foreach ( array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' ) as $mk ) {
			$v = (string) get_post_meta( $p->ID, $mk, true );
			if ( '' !== $v ) {
				$nv = nura_sw_fix( $v );
				if ( $nv !== $v ) {
					update_post_meta( $p->ID, $mk, $nv );
				}
			}
		}
	}
	// Category descriptions and names (not slugs).
	foreach ( (array) get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) {
		if ( ! $t instanceof WP_Term ) {
			continue;
		}
		$nd = nura_sw_fix( $t->description );
		if ( $nd !== $t->description ) {
			wp_update_term( $t->term_id, 'product_cat', array( 'description' => $nd ) );
		}
	}
	// Human Blend Wigs category for Bob Wavy.
	$hb = get_term_by( 'slug', 'human-blend-wigs', 'product_cat' );
	if ( ! $hb ) {
		$parent = 0;
		$semi   = get_term_by( 'slug', 'human-hair-blend-wigs', 'product_cat' );
		if ( $semi ) {
			$parent = (int) $semi->parent;
		}
		$res = wp_insert_term( 'Human Blend Wigs', 'product_cat', array( 'slug' => 'human-blend-wigs', 'parent' => $parent, 'description' => 'Human Blend wigs from NURA Beauty. Low heat is fine for straightening or curling. Same-day dispatch countrywide, pay by M-Pesa.' ) );
		$hb  = is_wp_error( $res ) ? null : get_term( (int) $res['term_id'], 'product_cat' );
	}
	if ( $hb && ! is_wp_error( $hb ) ) {
		wp_set_object_terms( 266, array( (int) $hb->term_id ), 'product_cat', true );
		$semi = get_term_by( 'slug', 'human-hair-blend-wigs', 'product_cat' );
		if ( $semi ) {
			wp_remove_object_terms( 266, array( (int) $semi->term_id ), 'product_cat' );
			wp_update_term_count_now( array( (int) $semi->term_id ), 'product_cat' );
		}
		wp_update_term_count_now( array( (int) $hb->term_id ), 'product_cat' );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( 266 );
		}
		$log[] = 'Human Blend Wigs category: Bob Wavy moved';
	}
	delete_transient( 'nurax_knowledge_tokens' );
	update_option( 'nura_sw_2026_log', $log, false );
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['page'] ) || 'nura-images' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$log = (array) get_option( 'nura_sw_2026_log', array() );
	if ( $log ) {
		echo '<div class="notice notice-info"><p><strong>Hair-type words updated on:</strong> ' . esc_html( implode( ' | ', $log ) ) . '</p></div>';
	}
} );
