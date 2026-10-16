<?php
/**
 * One-time content and settings update (v1.55.4). Runs once in wp-admin:
 *  - creates the "Wig Revamp in Nairobi" page and the semi-human wigs guide
 *    (skipped if the slug already exists, so nothing is overwritten);
 *  - turns on star-rated product reviews (moderated, verified-owner label);
 *  - makes the phone number required at checkout.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_content_file( $name ) {
	$f = NURA_DIR . 'content/' . $name . '.html';
	return file_exists( $f ) ? (string) file_get_contents( $f ) : '';
}

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.55.4' === get_option( 'nura_content_update' ) ) {
		return;
	}
	update_option( 'nura_content_update', '1.55.4', false );

	$wa   = function_exists( 'nura_opt' ) && nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
	$cta  = '<p class="nura-cta-row"><a class="nura-btn nura-btn--gold" href="' . esc_url( $wa ) . '">Send us a photo on WhatsApp</a> '
		. '<a class="nura-btn nura-btn--ghost" href="' . esc_url( home_url( '/book-appointment/' ) ) . '">Book an appointment</a></p>';

	$items = array(
		array(
			'type'  => 'page',
			'slug'  => 'wig-revamp-nairobi',
			'title' => 'Wig Revamp in Nairobi',
			'html'  => nura_content_file( 'wig-revamp-nairobi' ) . $cta,
			'seo'   => array( 'Wig Revamp in Nairobi | Wash & Restyle from KSh 1,500 | NURA Beauty', 'Wig wash, revamp and restyle at our Nairobi CBD studio, from KSh 1,500. Human hair, semi-human and high fibre wigs. Open Mon–Sat. Book on WhatsApp.', 'wig revamp nairobi' ),
		),
		array(
			'type'  => 'post',
			'slug'  => 'semi-human-wigs-kenya-guide',
			'title' => 'Semi-Human Wigs in Kenya: What They Are and Who They Suit',
			'html'  => nura_content_file( 'semi-human-wigs-kenya-guide' ),
			'seo'   => array( 'Semi-Human Wigs in Kenya: Prices, Care & Who They Suit | NURA Beauty', 'What a semi-human (human hair blend) wig is, how it compares with human hair and high fibre, how to care for it, and prices in Kenya from KSh 1,800.', 'semi-human wigs kenya' ),
		),
	);
	foreach ( $items as $it ) {
		if ( '' === $it['html'] || get_page_by_path( $it['slug'], OBJECT, $it['type'] ) ) {
			continue;
		}
		$args = array(
			'post_type'    => $it['type'],
			'post_name'    => $it['slug'],
			'post_title'   => $it['title'],
			'post_content' => $it['html'],
			'post_status'  => 'publish',
		);
		if ( 'post' === $it['type'] ) {
			$ref = get_page_by_path( 'best-wigs-for-beginners-kenya', OBJECT, 'post' );
			if ( $ref ) {
				$args['post_category'] = wp_get_post_categories( $ref->ID );
				$args['post_author']   = (int) $ref->post_author;
			}
		}
		$id = wp_insert_post( $args, true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', $it['seo'][0] );
			update_post_meta( $id, 'rank_math_description', $it['seo'][1] );
			update_post_meta( $id, 'rank_math_focus_keyword', $it['seo'][2] );
		}
	}

	// Product reviews with star ratings.
	update_option( 'woocommerce_enable_reviews', 'yes' );
	update_option( 'woocommerce_enable_review_rating', 'yes' );
	update_option( 'woocommerce_review_rating_required', 'yes' );
	update_option( 'woocommerce_review_rating_verification_label', 'yes' );
	update_option( 'comment_moderation', '1' );
	global $wpdb;
	$wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'open' WHERE post_type = 'product' AND comment_status <> 'open'" );

	// Phone number required at checkout (block checkout reads this option).
	update_option( 'woocommerce_checkout_phone_field', 'required' );
} );
