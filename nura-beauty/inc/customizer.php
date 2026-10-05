<?php
/**
 * Theme Customizer.
 *
 * Design principle: NOTHING is hardcoded. Every brand value - colours, fonts,
 * business name, tagline, contact details, socials, announcement, hero, trust
 * bar, feature blocks and payment badges - is editable here so the owner can
 * re-skin and re-brand the whole site without touching code. Sample/demo
 * content installed on activation is only a starting point and is fully editable.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry of every editable setting: [ id => [ default, label, section, type, sanitize, control ] ].
 * Keeping this in one array means main.css variables, template tags and the
 * Customizer UI all read from the same source of truth.
 */
function nura_settings_map() {
	return array(

		// ---- Colours (drive the CSS variables in main.css) ----
		'nura_color_ink'   => array( 'default' => '#0E0E0E', 'label' => __( 'Obsidian black (ink)', 'nura-beauty' ), 'section' => 'nura_colors', 'control' => 'color' ),
		'nura_color_gold'  => array( 'default' => '#C9A24B', 'label' => __( 'Champagne gold (signature)', 'nura-beauty' ), 'section' => 'nura_colors', 'control' => 'color' ),
		'nura_color_ivory' => array( 'default' => '#F3E9DC', 'label' => __( 'Warm ivory', 'nura-beauty' ), 'section' => 'nura_colors', 'control' => 'color' ),
		'nura_color_plum'  => array( 'default' => '#3A1E2E', 'label' => __( 'Aubergine (accent)', 'nura-beauty' ), 'section' => 'nura_colors', 'control' => 'color' ),
		'nura_color_nude'  => array( 'default' => '#D8B7A0', 'label' => __( 'Soft nude', 'nura-beauty' ), 'section' => 'nura_colors', 'control' => 'color' ),

		// ---- Typography (Google Font families - swap the whole type system without code) ----
		'nura_font_display' => array( 'default' => 'Playfair Display', 'label' => __( 'Display / headline font', 'nura-beauty' ), 'section' => 'nura_type' ),
		'nura_font_editorial' => array( 'default' => 'Cormorant Garamond', 'label' => __( 'Editorial serif font', 'nura-beauty' ), 'section' => 'nura_type' ),
		'nura_font_body'    => array( 'default' => 'Montserrat', 'label' => __( 'Body font', 'nura-beauty' ), 'section' => 'nura_type' ),
		'nura_font_ui'      => array( 'default' => 'Jost', 'label' => __( 'UI / label font', 'nura-beauty' ), 'section' => 'nura_type' ),

		// ---- Brand identity + contact ----
		'nura_brand_name'  => array( 'default' => 'NURA', 'label' => __( 'Brand name (text fallback if no logo)', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_tagline'     => array( 'default' => 'The House of Radiant Confidence', 'label' => __( 'Tagline', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_signoff'     => array( 'default' => 'Wear your crown. — NURA', 'label' => __( 'Signature sign-off', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_bio'         => array( 'default' => "East Africa's house of radiant confidence. Premium human-hair wigs, hand-crafted in Nairobi.", 'label' => __( 'One-line bio (footer)', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'textarea' ),
		'nura_announcement'=> array( 'default' => 'Free same-day delivery in Nairobi on orders over KES 10,000 · Pay with M-Pesa on delivery', 'label' => __( 'Announcement bar', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'textarea' ),
		'nura_phone'       => array( 'default' => '+254 714 994 898', 'label' => __( 'Phone', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_whatsapp'    => array( 'default' => 'https://wa.me/254714994898', 'label' => __( 'WhatsApp link (wa.me/...)', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_email'       => array( 'default' => 'care@nurabeauty.co.ke', 'label' => __( 'Support email', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'sanitize_email' ),
		'nura_address'     => array( 'default' => 'Imenti House, Moi Avenue, Nairobi CBD', 'label' => __( 'Store address', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_city'        => array( 'default' => 'Nairobi', 'label' => __( 'City', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_hours'       => array( 'default' => 'Mon–Sat 9:00–18:00', 'label' => __( 'Opening hours', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_instagram'   => array( 'default' => '', 'label' => __( 'Instagram URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_tiktok'      => array( 'default' => '', 'label' => __( 'TikTok URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_facebook'    => array( 'default' => '', 'label' => __( 'Facebook URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),

		// ---- Homepage hero ----
		'nura_hero_eyebrow'  => array( 'default' => 'The House of Radiant Confidence', 'label' => __( 'Hero eyebrow', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_title'    => array( 'default' => 'Luxury Human Hair Wigs, Made for You', 'label' => __( 'Hero title', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_subtitle' => array( 'default' => 'Verified human hair, HD lace and glueless units, hand-crafted in Nairobi and delivered across Kenya.', 'label' => __( 'Hero subtitle', 'nura-beauty' ), 'section' => 'nura_home', 'control' => 'textarea' ),
		'nura_hero_cta'      => array( 'default' => 'Shop the Collection', 'label' => __( 'Hero primary button', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_cta2'     => array( 'default' => 'Book a Consultation', 'label' => __( 'Hero secondary button', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_image'    => array( 'default' => '', 'label' => __( 'Hero background image', 'nura-beauty' ), 'section' => 'nura_home', 'control' => 'image', 'sanitize' => 'esc_url_raw' ),

		// ---- Trust bar (4 editable promises) ----
		'nura_trust_1' => array( 'default' => 'Verified human hair', 'label' => __( 'Trust item 1', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_trust_2' => array( 'default' => 'Same-day Nairobi delivery', 'label' => __( 'Trust item 2', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_trust_3' => array( 'default' => 'M-Pesa, card & pay on delivery', 'label' => __( 'Trust item 3', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_trust_4' => array( 'default' => 'Written longevity guarantee', 'label' => __( 'Trust item 4', 'nura-beauty' ), 'section' => 'nura_home' ),

		// ---- Payment badges (comma separated, fully editable) ----
		'nura_payments' => array( 'default' => 'M-Pesa, Cash on Delivery, Bank Transfer', 'label' => __( 'Payment badges (comma separated)', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'textarea' ),

		// ---- Default social share image ----
		'nura_default_share_image' => array( 'default' => '', 'label' => __( 'Default social share image', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'image', 'sanitize' => 'esc_url_raw' ),
	);
}

/**
 * Helper: read a setting with its registered default.
 */
function nura_opt( $id ) {
	$map = nura_settings_map();
	$default = isset( $map[ $id ]['default'] ) ? $map[ $id ]['default'] : '';
	return get_theme_mod( $id, $default );
}

/**
 * Guarantee the real NURA contact details show even if placeholder demo values
 * were saved into the Customizer by the sample data. A real value the owner
 * sets in Appearance > Customize > NURA Options always wins; only an empty or
 * known-placeholder value is replaced.
 */
add_filter( 'theme_mod_nura_phone', function ( $value ) {
	return ( '' === $value || '+254 700 000 000' === $value ) ? '+254 714 994 898' : $value;
} );
add_filter( 'theme_mod_nura_whatsapp', function ( $value ) {
	return ( '' === $value || 'https://wa.me/254700000000' === $value ) ? 'https://wa.me/254714994898' : $value;
} );

/**
 * Never render retired payment badges. NURA takes M-Pesa, Cash on Delivery and
 * Bank Transfer only, so Visa, Mastercard, PayPal, Lipa Later and generic "Card"
 * are stripped from whatever is stored - even a value saved into the Customizer
 * before this change - and the field falls back to the curated list if that
 * empties it. Guarantees these labels appear nowhere on the storefront.
 */
add_filter( 'theme_mod_nura_payments', function ( $value ) {
	// The Customizer previews settings by running the registered default through
	// this same filter, and that default can arrive as a non-string sentinel (an
	// stdClass, sometimes an array). Casting that to a string throws a fatal, so
	// only ever transform a real string value and pass anything else through.
	if ( ! is_string( $value ) ) {
		return $value;
	}
	$retired = array( 'visa', 'mastercard', 'master card', 'paypal', 'pay pal', 'lipa later', 'lipa-later', 'lipalater', 'credit card', 'card', 'debit card' );
	$items   = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
	$kept    = array();
	foreach ( $items as $item ) {
		if ( ! in_array( strtolower( $item ), $retired, true ) ) {
			$kept[] = $item;
		}
	}
	return $kept ? implode( ', ', $kept ) : 'M-Pesa, Cash on Delivery, Bank Transfer';
} );

function nura_customize_register( $wp_customize ) {
	$wp_customize->add_panel( 'nura_panel', array(
		'title'       => __( 'NURA Options', 'nura-beauty' ),
		'description' => __( 'Everything here is editable - re-brand the whole site without touching code.', 'nura-beauty' ),
		'priority'    => 20,
	) );

	$sections = array(
		'nura_colors' => __( 'Colours', 'nura-beauty' ),
		'nura_type'   => __( 'Typography', 'nura-beauty' ),
		'nura_brand'  => __( 'Brand & Contact', 'nura-beauty' ),
		'nura_home'   => __( 'Homepage', 'nura-beauty' ),
	);
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section( $id, array( 'title' => $title, 'panel' => 'nura_panel' ) );
	}

	foreach ( nura_settings_map() as $id => $args ) {
		$sanitize = isset( $args['sanitize'] ) ? $args['sanitize'] : ( ( isset( $args['control'] ) && 'textarea' === $args['control'] ) ? 'sanitize_textarea_field' : 'sanitize_text_field' );
		$wp_customize->add_setting( $id, array(
			'default'           => $args['default'],
			'sanitize_callback' => $sanitize,
			'transport'         => 'refresh',
		) );

		$control = isset( $args['control'] ) ? $args['control'] : 'text';
		if ( 'color' === $control ) {
			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, array(
				'label' => $args['label'], 'section' => $args['section'],
			) ) );
		} elseif ( 'image' === $control ) {
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $id, array(
				'label' => $args['label'], 'section' => $args['section'],
			) ) );
		} else {
			$wp_customize->add_control( $id, array(
				'label' => $args['label'], 'section' => $args['section'],
				'type'  => 'textarea' === $control ? 'textarea' : 'text',
			) );
		}
	}
}
add_action( 'customize_register', 'nura_customize_register' );
