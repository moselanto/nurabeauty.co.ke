<?php
/**
 * SEO content layer for product-category archives (#20).
 *
 * Two additions to WooCommerce product-category archives:
 *   1. A styled, keyword-rich intro block at the top of the archive. Uses the
 *      category's own description when set, and a curated Kenya-focused default
 *      otherwise, so every pillar/type page ships with real, indexable intro
 *      copy even before a description is written in wp-admin. Shown on page 1
 *      only (mirrors WooCommerce's own behaviour).
 *   2. CollectionPage + ItemList + BreadcrumbList JSON-LD describing the listing
 *      so search engines can read the category as a structured collection.
 *
 * The schema is skipped when a dedicated SEO plugin is active (see
 * nura_seo_plugin_active in inc/seo-schema.php); the visible intro copy always
 * renders, since SEO plugins manage meta, not on-page category copy.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curated fallback intros, keyed by product-category slug. Honest and
 * Kenya-focused; used only when the category has no description of its own.
 *
 * @return array<string,string>
 */
function nura_category_intro_defaults() {
	return array(
		'wigs'            => __( 'Shop premium wigs in Kenya at NURA — human hair, human-hair blends and quality synthetic units in lace front, closure, glueless and headband styles. Every wig is quality-checked, with same-day delivery in Nairobi, countrywide shipping and payment by M-Pesa, card or on delivery.', 'nura-beauty' ),
		'human-hair-wigs' => __( 'Discover 100% human-hair wigs at NURA Kenya — natural movement, heat-friendly styling and a flawless hairline. Perfect for everyday elegance, work and special occasions, with expert fitting and countrywide delivery.', 'nura-beauty' ),
		'lace-front-wigs' => __( 'HD and transparent lace front wigs for an invisible, melt-into-skin hairline. Hand-finished for a natural parting and photograph-ready finish, with fitting and styling available in Nairobi.', 'nura-beauty' ),
		'closure-wigs'    => __( '4x4, 5x5 and 6x6 closure wigs that give a natural crown with easy, protective installation — a versatile, everyday-friendly choice, quality-checked and ready to wear.', 'nura-beauty' ),
		'headband-wigs'   => __( 'Throw-on-and-go headband wigs for effortless, protective everyday style — no glue, no lace, no fuss. A NURA favourite for busy mornings, in curly, kinky, water-wave and more.', 'nura-beauty' ),
		'bob-wigs'        => __( 'Chic bob wigs — from blunt cuts to layered and wavy — for a sharp, modern look. Lightweight, comfortable and beginner-friendly, with fitting available in Nairobi.', 'nura-beauty' ),
		'curly-wigs'      => __( 'Curly wigs with bounce, body and definition, from soft waves to tight coils. Quality-checked units that hold their pattern, with a full care guide on every order.', 'nura-beauty' ),
		'body-wave-wigs'  => __( 'Body wave wigs with soft, glamorous S-shaped waves — a timeless, versatile texture for every occasion. Available across lengths and lace types, with same-day Nairobi delivery.', 'nura-beauty' ),
		'hair-extensions' => __( 'Bundles, closures and frontals to add length and volume, matched to your texture. Quality-checked hair for a seamless blend, with expert guidance from the NURA team.', 'nura-beauty' ),
		'wig-care'        => __( 'Keep your crown radiant with NURA wig care — sulphate-free shampoos, conditioners, sprays and tools to wash, treat and maintain your unit and extend its life.', 'nura-beauty' ),
		'beauty'          => __( 'Complete your look with NURA beauty essentials and accessories, curated to pair with your wig for a polished, confident finish.', 'nura-beauty' ),
		'bridal-occasion' => __( 'Bridal and occasion wigs, hand-finished for your big day — flawless HD-lace hairlines and photograph-ready styling. Book a NURA bridal fitting for a look tailored to your dress and face shape.', 'nura-beauty' ),
	);
}

/**
 * Resolve the best plain-text description for a category term:
 * its own description, else a curated default, else a generic Kenya line.
 *
 * @param WP_Term $term Product-category term.
 * @return string Plain text.
 */
function nura_category_description_text( $term ) {
	$own = trim( wp_strip_all_tags( (string) term_description( $term->term_id, $term->taxonomy ) ) );
	if ( '' !== $own ) {
		return $own;
	}
	$defaults = nura_category_intro_defaults();
	if ( isset( $defaults[ $term->slug ] ) ) {
		return $defaults[ $term->slug ];
	}
	/* translators: %s: category name */
	return sprintf( __( 'Shop %s at NURA — quality-checked units with same-day Nairobi delivery, countrywide shipping and payment by M-Pesa, card or on delivery.', 'nura-beauty' ), $term->name );
}

/**
 * Styled intro block on product-category archives, replacing WooCommerce's
 * plain term-description output. Uses the term's own (HTML) description when set,
 * otherwise the curated default. Page 1 only.
 */
function nura_category_intro() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	if ( absint( get_query_var( 'paged' ) ) > 1 ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	$html = term_description( $term->term_id, $term->taxonomy );
	if ( '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
		$html = wpautop( esc_html( nura_category_description_text( $term ) ) );
	}
	echo '<div class="nura-cat-intro"><div class="nura-cat-intro__inner">' . wp_kses_post( $html ) . '</div></div>';
}

/**
 * Swap WooCommerce's default term-description output for our styled, SEO-defaulted
 * intro. Done on init so WooCommerce's own hook is registered first.
 */
add_action( 'init', function () {
	if ( function_exists( 'is_product_category' ) || class_exists( 'WooCommerce' ) ) {
		remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
		add_action( 'woocommerce_archive_description', 'nura_category_intro', 10 );
	}
}, 20 );

/**
 * CollectionPage + ItemList + BreadcrumbList JSON-LD on product-category archives.
 */
function nura_category_schema() {
	if ( nura_seo_plugin_active() || ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	$term_link = get_term_link( $term );
	$term_link = is_wp_error( $term_link ) ? home_url( '/' ) : $term_link;

	$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
	$crumbs   = array(
		array( 'name' => __( 'Home', 'nura-beauty' ), 'url' => home_url( '/' ) ),
		array( 'name' => __( 'Shop', 'nura-beauty' ), 'url' => $shop_url ),
		array( 'name' => $term->name, 'url' => $term_link ),
	);
	$breadcrumb_items = array();
	foreach ( $crumbs as $i => $c ) {
		$breadcrumb_items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $c['name'],
			'item'     => $c['url'],
		);
	}

	$items = array();
	$pos   = 1;
	global $wp_query;
	if ( ! empty( $wp_query->posts ) ) {
		foreach ( $wp_query->posts as $p ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'url'      => get_permalink( $p ),
				'name'     => wp_strip_all_tags( get_the_title( $p ) ),
			);
			$pos++;
			if ( $pos > 24 ) {
				break;
			}
		}
	}

	$collection = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'CollectionPage',
		'name'        => $term->name,
		'description' => nura_category_description_text( $term ),
		'url'         => $term_link,
	);
	if ( ! empty( $items ) ) {
		$collection['mainEntity'] = array(
			'@type'           => 'ItemList',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
	}

	$breadcrumb = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $breadcrumb_items,
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $collection ) . '</script>' . "\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb ) . '</script>' . "\n";
}
add_action( 'wp_head', 'nura_category_schema', 7 );
