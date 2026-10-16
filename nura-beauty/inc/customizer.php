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
		'nura_bio'         => array( 'default' => "East Africa's house of radiant confidence. Premium wigs, hand-finished in Nairobi.", 'label' => __( 'One-line bio (footer)', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'textarea' ),
		'nura_announcement'=> array( 'default' => 'Same-day delivery in Nairobi on orders before 5 PM · Pay with M-Pesa or cash on delivery in Nairobi', 'label' => __( 'Announcement bar', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'textarea' ),
		'nura_phone'       => array( 'default' => '+254 714 994 898', 'label' => __( 'Phone', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_whatsapp'    => array( 'default' => 'https://wa.me/254714994898', 'label' => __( 'WhatsApp link (wa.me/...)', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_email'       => array( 'default' => 'care@nurabeauty.co.ke', 'label' => __( 'Support email', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'sanitize_email' ),
		'nura_address'     => array( 'default' => 'Imenti House, Moi Avenue, Nairobi CBD', 'label' => __( 'Store address', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_city'        => array( 'default' => 'Nairobi', 'label' => __( 'City', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_hours'       => array( 'default' => 'Mon–Sat 8:00–20:00', 'label' => __( 'Opening hours', 'nura-beauty' ), 'section' => 'nura_brand' ),
		'nura_instagram'   => array( 'default' => '', 'label' => __( 'Instagram URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_tiktok'      => array( 'default' => 'https://www.tiktok.com/@nurabeautyke', 'label' => __( 'TikTok URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_facebook'    => array( 'default' => 'https://www.facebook.com/nurabeautyke/', 'label' => __( 'Facebook URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_youtube'     => array( 'default' => '', 'label' => __( 'YouTube URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_pinterest'   => array( 'default' => '', 'label' => __( 'Pinterest URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_x'           => array( 'default' => '', 'label' => __( 'X (Twitter) URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),
		'nura_threads'     => array( 'default' => '', 'label' => __( 'Threads URL', 'nura-beauty' ), 'section' => 'nura_brand', 'sanitize' => 'esc_url_raw' ),

		// ---- Homepage hero ----
		'nura_hero_eyebrow'  => array( 'default' => 'The House of Radiant Confidence', 'label' => __( 'Hero eyebrow', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_title'    => array( 'default' => 'Luxury Human Hair Wigs, Made for You', 'label' => __( 'Hero title', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_subtitle' => array( 'default' => 'Human hair, blend and fibre wigs - HD lace and glueless units, clearly labelled, finished in Nairobi and delivered across Kenya.', 'label' => __( 'Hero subtitle', 'nura-beauty' ), 'section' => 'nura_home', 'control' => 'textarea' ),
		'nura_hero_cta'      => array( 'default' => 'Shop the Collection', 'label' => __( 'Hero primary button', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_cta2'     => array( 'default' => 'Book a Consultation', 'label' => __( 'Hero secondary button', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_hero_image'    => array( 'default' => '', 'label' => __( 'Hero background image', 'nura-beauty' ), 'section' => 'nura_home', 'control' => 'image', 'sanitize' => 'esc_url_raw' ),

		// ---- Trust bar (4 editable promises) ----
		'nura_trust_1' => array( 'default' => 'Quality-checked, clearly labelled hair', 'label' => __( 'Trust item 1', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_trust_2' => array( 'default' => 'Same-day Nairobi delivery', 'label' => __( 'Trust item 2', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_trust_3' => array( 'default' => 'M-Pesa, card & pay on delivery', 'label' => __( 'Trust item 3', 'nura-beauty' ), 'section' => 'nura_home' ),
		'nura_trust_4' => array( 'default' => 'Written longevity guarantee', 'label' => __( 'Trust item 4', 'nura-beauty' ), 'section' => 'nura_home' ),

		// ---- Payment badges (comma separated, fully editable) ----
		'nura_payments' => array( 'default' => 'M-Pesa, Cash on Delivery, Bank Transfer', 'label' => __( 'Payment badges (comma separated)', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'textarea' ),

		// ---- Installation page (v1.47.0). Lists: one item per line, parts separated by | ----
		'nura_inst_eyebrow' => array( 'default' => 'NURA Studio', 'label' => __( 'Eyebrow above the title', 'nura-beauty' ), 'section' => 'nura_install' ),
		'nura_inst_lede'    => array( 'default' => 'Book your install, styling, colouring or revamp with our atelier. Studio appointments, and at-home service for VIP clients.', 'label' => __( 'Intro text', 'nura-beauty' ), 'section' => 'nura_install', 'control' => 'textarea' ),
		'nura_inst_facts'   => array( 'default' => 'Studio in Nairobi CBD, At-home service for VIP clients, Mon–Sat 8:00–20:00', 'label' => __( 'Fact chips under the intro (comma separated)', 'nura-beauty' ), 'section' => 'nura_install' ),
		'nura_inst_cta'     => array( 'default' => 'Book installation', 'label' => __( 'Main button text', 'nura-beauty' ), 'section' => 'nura_install' ),
		'nura_inst_services_title' => array( 'default' => 'Services & guide prices', 'label' => __( 'Services heading', 'nura-beauty' ), 'section' => 'nura_install' ),
		'nura_inst_services' => array(
			'default' => "Wig installation | From KSh 1,500 | A secure, natural-looking install of your NURA unit or your own.\nStyling / restyle | KSh 1,000 - 2,500 | A fresh cut, curls or a new parting to change your look.\nWash & revamp | From KSh 1,500 | Deep wash, condition and restyle to bring a tired unit back to life.\nColouring | KSh 2,500 - 5,000 | Custom colour for human hair units.\nRe-lacing / repair | From KSh 2,000 | New lace or repairs so your unit fits and lasts.",
			'label'   => __( 'Services - one per line: Name | Price | Short description', 'nura-beauty' ),
			'section' => 'nura_install',
			'control' => 'textarea',
		),
		'nura_inst_price_note' => array( 'default' => 'Prices are a guide and confirmed at consultation based on length, density and the work required. Members of The NURA Circle enjoy preferential rates and scheduled care.', 'label' => __( 'Note under the prices', 'nura-beauty' ), 'section' => 'nura_install', 'control' => 'textarea' ),
		'nura_inst_steps_title' => array( 'default' => 'How it works', 'label' => __( 'Steps heading', 'nura-beauty' ), 'section' => 'nura_install' ),
		'nura_inst_steps' => array(
			'default' => "Book | Book an appointment for your preferred service and time.\nVisit the studio | Come to the studio, or arrange at-home service if you are a VIP client.\nYour stylist gets to work | We install, style or restore your unit to perfection.\nLeave radiant | Walk out confident and book your next care visit.",
			'label'   => __( 'Steps - one per line: Title | Text', 'nura-beauty' ),
			'section' => 'nura_install',
			'control' => 'textarea',
		),
		'nura_inst_care_title' => array( 'default' => 'Aftercare & revamps', 'label' => __( 'Aftercare heading', 'nura-beauty' ), 'section' => 'nura_install' ),
		'nura_inst_care_text'  => array( 'default' => 'A well-maintained unit lasts far longer. We recommend a wash & revamp every 6-8 weeks of regular wear. Join The NURA Circle for automatic care reminders and priority booking.', 'label' => __( 'Aftercare text', 'nura-beauty' ), 'section' => 'nura_install', 'control' => 'textarea' ),
		'nura_inst_circle_url' => array( 'default' => '', 'label' => __( 'The NURA Circle link (leave empty to hide the button)', 'nura-beauty' ), 'section' => 'nura_install', 'sanitize' => 'esc_url_raw' ),
		'nura_inst_faq' => array(
			'default' => "Can you install a wig I bought elsewhere? | Yes. Tell us about the unit when you book and we will advise what it needs.\nDo you offer at-home service? | Yes, for VIP clients. Message us on WhatsApp to arrange it.\nHow often should I revamp my wig? | We recommend a wash and revamp every 6-8 weeks of regular wear.\nAre the prices fixed? | They are a guide. We confirm the price at consultation based on length, density and the work required.",
			'label'   => __( 'Questions - one per line: Question | Answer (leave empty to hide)', 'nura-beauty' ),
			'section' => 'nura_install',
			'control' => 'textarea',
		),

		// ---- Default social share image ----
		'nura_default_share_image' => array( 'default' => '', 'label' => __( 'Default social share image', 'nura-beauty' ), 'section' => 'nura_brand', 'control' => 'image', 'sanitize' => 'esc_url_raw' ),
	);
}

/**
 * Helper: turn a "one item per line, parts separated by |" setting into rows.
 *
 * @param string $id    Setting id.
 * @param int    $parts Number of parts per row (missing parts become '').
 * @return array[]
 */
function nura_lines( $id, $parts = 2 ) {
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) nura_opt( $id ) ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$cells = array_map( 'trim', explode( '|', $line, $parts ) );
		$rows[] = array_pad( $cells, $parts, '' );
	}
	return $rows;
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
		'nura_install' => __( 'Installation page', 'nura-beauty' ),
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

/**
 * Social profiles, in display order. Only profiles with a URL saved in
 * Appearance > Customize > NURA Options > Brand are returned, so empty ones
 * never show as dead icons. Used by the footer icons and the schema sameAs.
 *
 * @return array[] Each: key, label, url.
 */
function nura_social_links() {
	$networks = array(
		'instagram' => 'Instagram',
		'tiktok'    => 'TikTok',
		'facebook'  => 'Facebook',
		'youtube'   => 'YouTube',
		'pinterest' => 'Pinterest',
		'x'         => 'X',
		'threads'   => 'Threads',
	);
	$out = array();
	foreach ( $networks as $key => $label ) {
		$url = trim( (string) nura_opt( 'nura_' . $key ) );
		if ( '' !== $url ) {
			$out[] = array( 'key' => $key, 'label' => $label, 'url' => $url );
		}
	}
	return apply_filters( 'nura_social_links', $out );
}

/** Plain list of social profile URLs (for schema sameAs). */
function nura_social_urls() {
	return wp_list_pluck( nura_social_links(), 'url' );
}

/** Inline SVG icon for a social network (stroke icons, inherit currentColor). */
function nura_social_icon( $key ) {
	$paths = array(
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/>',
		'tiktok'    => '<path d="M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-2.6-2.6c.27 0 .53.04.78.12V9.75a5.8 5.8 0 0 0-.78-.05 5.75 5.75 0 1 0 5.75 5.75V9.1a7.4 7.4 0 0 0 4.3 1.38V7.37a4.3 4.3 0 0 1-3.25-1.57z" fill="currentColor" stroke="none"/>',
		'facebook'  => '<path d="M13.5 21v-7.5H16l.4-3h-2.9V8.6c0-.87.25-1.46 1.5-1.46h1.55V4.45A20 20 0 0 0 14.3 4.3c-2.2 0-3.7 1.34-3.7 3.8v2.4H8.1v3h2.5V21z" fill="currentColor" stroke="none"/>',
		'youtube'   => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="M10 9l5 3-5 3z" fill="currentColor"/>',
		'pinterest' => '<circle cx="12" cy="12" r="9"/><path d="M11 21l2-8"/><path d="M9.5 13.5c-1-2.5.5-6 3.5-6 2.5 0 3.5 1.8 3.5 3.5 0 2.5-1.5 4.5-3.5 4.5-1 0-1.6-.6-1.5-1.5"/>',
		'x'         => '<path d="M4 4l16 16M20 4L4 20"/>',
		'threads'   => '<path d="M16.5 11c-.3-2.7-2-4-4.4-4-2.7 0-4.6 2-4.6 5s1.9 5 4.6 5c2 0 3.4-1 3.4-2.6 0-1.9-2-2.6-4.2-2.2-1.4.3-2 1.2-1.6 2.1"/><path d="M12 21a9 9 0 1 1 9-9c0 3-1.5 5-4 5"/>',
	);
	if ( empty( $paths[ $key ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $key ] . '</svg>';
}
