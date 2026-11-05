<?php
/**
 * NURA wig encyclopedia (theme 1.119.0, Sprint 2 of the growth plan).
 *
 * [nura_encyclopedia] renders an A-Z of wig terms grouped by topic, each with
 * an anchor (#term-<slug>) and DefinedTermSet / DefinedTerm JSON-LD so search
 * engines and AI assistants can quote exact definitions. A one-time importer
 * creates the /wig-encyclopedia/ page and any new guides listed in
 * content/guides/index.json that do not exist yet (never overwrites).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Terms: group, term, slug, definition. Filter: nura_encyclopedia_terms. */
function nura_encyclopedia_terms() {
	return (array) apply_filters( 'nura_encyclopedia_terms', array(
		array( 'Hair types', '100% human hair', 'human-hair', 'Real human hair collected from donors and sewn or ventilated onto a wig cap. It looks and moves most naturally, can be washed, heat-styled and coloured, and lasts about a year or more with good care. It is the most expensive type.' ),
		array( 'Hair types', 'Human-hair blend (semi-human)', 'semi-human', 'A mix of real human hair and heat-resistant fibre. Softer and more natural than fibre at a lower price than human hair; low to medium heat only. Lasts about 6 to 9 months of regular wear.' ),
		array( 'Hair types', 'Heat-resistant fibre (synthetic)', 'fibre', 'Man-made fibre styled at the factory, so the curl or straightness holds out of the box. The most affordable type, light and low maintenance; use low heat only. Lasts about 3 to 6 months.' ),
		array( 'Hair types', 'Kanekalon', 'kanekalon', 'A brand of modacrylic fibre widely used for braids and cheaper synthetic wigs. It melts under high heat and is not the same as human hair.' ),
		array( 'Grades and processing', 'Remy hair', 'remy', 'Human hair collected with the cuticles kept in one direction, root to tip, which reduces tangling. Remy describes how the hair was collected, not how much it was processed.' ),
		array( 'Grades and processing', 'Virgin hair', 'virgin', 'Human hair that has never been dyed, permed or chemically processed. It can be coloured once and usually lasts longest.' ),
		array( 'Grades and processing', 'Raw hair', 'raw', 'Unprocessed hair from a single donor, sold close to its natural state. The most expensive and least uniform type.' ),
		array( 'Grades and processing', 'Non-remy hair', 'non-remy', 'Human hair gathered from mixed sources with cuticles running in different directions. It is usually acid-treated and coated with silicone, so it tangles once the coating washes out.' ),
		array( 'Grades and processing', 'Hair grades (8A, 10A, 12A)', 'grades', 'Number labels used by sellers to suggest quality. No independent body defines or certifies them, so one seller\'s 10A is not another\'s. Judge the hair itself, not the number.' ),
		array( 'Grades and processing', 'Double drawn', 'double-drawn', 'Hair sorted so most strands run the full length, giving thick ends instead of thin, wispy tips. It costs more than single drawn hair.' ),
		array( 'Lace and construction', 'Lace front (frontal)', 'lace-front', 'A wig with sheer lace across the front hairline, usually 13x4 inches (13 inches ear to ear, 4 inches deep). It gives the most natural hairline and free parting at the front; best fitted by a stylist.' ),
		array( 'Lace and construction', '13x4 / 13x6 frontal', 'frontal-size', 'The size of the lace area on a frontal: 13 inches across the forehead and 4 or 6 inches back. Deeper lace (13x6) allows deeper side and middle parts.' ),
		array( 'Lace and construction', 'Closure (2x2, 4x4, 5x5)', 'closure', 'A small square of lace at the crown or parting area. 2x2 is the smallest and cheapest, 4x4 the most common, 5x5 gives more parting space. Easier to wear than a frontal and often glueless.' ),
		array( 'Lace and construction', 'Headband wig', 'headband', 'A wig with no lace and a fabric band at the front. No glue and no lace cutting, so it goes on in minutes. The easiest option for beginners.' ),
		array( 'Lace and construction', 'Glueless wig', 'glueless', 'A wig held with combs, an adjustable band or elastic instead of glue or tape. Faster to put on and kinder to your edges.' ),
		array( 'Lace and construction', 'Full lace / 360 lace', 'full-lace', 'Lace all around the hairline (360) or over the whole cap (full lace), so you can wear high ponytails and buns. The most expensive construction.' ),
		array( 'Lace and construction', 'T-part and U-part', 't-part', 'A T-part has a narrow strip of lace in a T shape for a middle part. A U-part leaves a U-shaped opening so your own hair blends with the wig.' ),
		array( 'Lace and construction', 'HD lace', 'hd-lace', 'A very thin, fine lace that melts into most skin tones with little tinting. Looks the most invisible but is more delicate and costs more.' ),
		array( 'Lace and construction', 'Transparent lace', 'transparent-lace', 'A clear-ish Swiss lace that is a little thicker and stronger than HD lace. Usually needs a little tint to match deeper skin tones.' ),
		array( 'Lace and construction', 'Pre-plucked hairline', 'pre-plucked', 'A hairline thinned at the factory so it looks like natural hair growth. It saves time but may still need light plucking.' ),
		array( 'Lace and construction', 'Bleached knots', 'bleached-knots', 'Knots on the lace lightened so the hair looks like it grows from your scalp. Done carefully so the hair does not shed.' ),
		array( 'Lace and construction', 'Baby hair', 'baby-hair', 'Short, fine hairs along the front of a lace wig that mimic natural edges and can be laid with edge gel.' ),
		array( 'Textures', 'Straight / bone straight', 'straight', 'Sleek, flat hair. Bone straight is extra silky and pressed flat with high shine.' ),
		array( 'Textures', 'Kinky straight (Yaki)', 'yaki', 'Straight hair with a slightly coarse, relaxed-hair texture. Looks closest to blow-dried natural African hair.' ),
		array( 'Textures', 'Body wave', 'body-wave', 'Large, loose S-shaped waves. Soft and glamorous; easy to keep.' ),
		array( 'Textures', 'Water wave', 'water-wave', 'Defined, wet-look ripples smaller than body wave. Looks best when refreshed with water and leave-in.' ),
		array( 'Textures', 'Deep wave', 'deep-wave', 'Tight, defined waves with lots of volume. Needs regular moisture to keep the pattern.' ),
		array( 'Textures', 'Jerry curl', 'jerry-curl', 'Small, springy curls with high volume. Needs moisture and finger-detangling rather than brushing.' ),
		array( 'Textures', 'Kinky curly / afro', 'kinky-curly', 'Tight coils that match natural 4A-4C hair. The most natural look for many Kenyan women.' ),
		array( 'Textures', 'Pixie and bob', 'pixie-bob', 'Short cuts: a pixie sits close to the head; a bob ends around the jaw or chin. Light, cool and easy to manage.' ),
		array( 'Colours', '1B (natural black)', '1b', 'Off-black, the most common and natural-looking wig colour. 1 is jet black, slightly harsher.' ),
		array( 'Colours', '27, 30 and 33', '27-30-33', 'Colour codes for honey blonde (27), light auburn or copper (30) and dark auburn (33). Often used as highlights such as 1B/27 or 1B/30.' ),
		array( 'Colours', '99J', '99j', 'A deep burgundy or wine red.' ),
		array( 'Colours', '613', '613', 'Light platinum blonde. The easiest shade to dye into other colours.' ),
		array( 'Colours', 'Ombre', 'ombre', 'Hair that fades from one colour at the root (usually 1B) to another at the ends.' ),
		array( 'Fit and density', 'Density (130%, 150%, 180%)', 'density', 'How much hair is on the cap. 130% to 150% looks natural for everyday wear; 180% and above looks full and glamorous.' ),
		array( 'Fit and density', 'Cap size', 'cap-size', 'Measured around the head from the hairline, above the ears and round the nape. Most wigs are medium (about 22 to 22.5 inches) with adjustable straps.' ),
		array( 'Fit and density', 'Length in inches', 'length', 'Measured from the top of the wig to the ends of straight hair. Curly hair looks shorter than its stated length.' ),
		array( 'Install and care', 'Wig installation', 'installation', 'Fitting a wig securely and naturally: cutting the lace, laying the hairline and styling. At NURA it is done in our Nairobi CBD studio.' ),
		array( 'Install and care', 'Wig revamp', 'revamp', 'A deep wash, condition and restyle that brings a tired wig back to life.' ),
		array( 'Install and care', 'Lace melting spray', 'melting-spray', 'A holding spray that flattens the lace onto the skin so the hairline looks seamless.' ),
		array( 'Install and care', 'Lace tint', 'lace-tint', 'A spray or mousse that colours the lace to match your skin tone.' ),
		array( 'Install and care', 'Wig glue and bond remover', 'glue', 'Adhesive that holds a lace wig in place, and the solvent that removes it safely without damaging your edges.' ),
		array( 'Install and care', 'Wig grip and wig cap', 'wig-grip', 'A wig grip is a soft band that stops a wig from slipping; a wig cap flattens your own hair underneath.' ),
	) );
}

/** [nura_encyclopedia] */
function nura_encyclopedia_html() {
	$terms  = nura_encyclopedia_terms();
	$groups = array();
	foreach ( $terms as $t ) {
		$groups[ $t[0] ][] = $t;
	}
	$url  = get_permalink();
	$out  = '<nav class="nura-enc-nav" aria-label="' . esc_attr__( 'Encyclopedia topics', 'nura-beauty' ) . '"><ul>';
	foreach ( array_keys( $groups ) as $g ) {
		$out .= '<li><a href="#topic-' . esc_attr( sanitize_title( $g ) ) . '">' . esc_html( $g ) . '</a></li>';
	}
	$out .= '</ul></nav>';
	$list = array();
	foreach ( $groups as $g => $items ) {
		$out .= '<section class="nura-enc-sec" id="topic-' . esc_attr( sanitize_title( $g ) ) . '"><h2>' . esc_html( $g ) . '</h2><dl class="nura-enc">';
		foreach ( $items as $t ) {
			$out   .= '<dt id="term-' . esc_attr( $t[2] ) . '">' . esc_html( $t[1] ) . '</dt><dd>' . esc_html( $t[3] ) . '</dd>';
			$list[] = array(
				'@type'            => 'DefinedTerm',
				'@id'              => $url . '#term-' . $t[2],
				'name'             => $t[1],
				'description'      => $t[3],
				'inDefinedTermSet' => $url . '#set',
			);
		}
		$out .= '</dl></section>';
	}
	$schema = array(
		'@context'       => 'https://schema.org',
		'@type'          => 'DefinedTermSet',
		'@id'            => $url . '#set',
		'name'           => 'NURA Beauty Wig Encyclopedia',
		'description'    => 'Plain-language definitions of wig hair types, grades, lace and construction, textures, colour codes, density, fit, installation and care, by the NURA Beauty stylist team in Nairobi.',
		'url'            => $url,
		'inLanguage'     => 'en-KE',
		'publisher'      => array( '@type' => 'Organization', 'name' => 'NURA Beauty', 'url' => home_url( '/' ) ),
		'hasDefinedTerm' => $list,
	);
	$out .= '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	return '<div class="nura-encyclopedia">' . $out . '</div>';
}
add_shortcode( 'nura_encyclopedia', 'nura_encyclopedia_html' );

add_action( 'wp_head', function () {
	if ( ! is_page( 'wig-encyclopedia' ) ) {
		return;
	}
	echo '<style id="nura-enc-css">.nura-enc-nav ul{list-style:none!important;display:flex;flex-wrap:wrap;gap:8px;padding:0!important;margin:0 0 1.6rem!important}.nura-enc-nav a{display:inline-block;padding:7px 14px;border:1px solid #eadfc6;border-radius:999px;font-size:.85rem;text-decoration:none;color:#0e0e0e;background:#fffdf8}.nura-enc-sec{margin:0 0 2.2rem}.nura-enc{margin:0}.nura-enc dt{font-weight:700;margin:1.1rem 0 .25rem;color:#0e0e0e;scroll-margin-top:110px}.nura-enc dd{margin:0;padding:0 0 .9rem;border-bottom:1px solid #f0ebe0;line-height:1.7}</style>' . "\n";
}, 30 );

/** One-time: create /wig-encyclopedia/ and import new guides from index.json (existing slugs are skipped). */
add_action( 'admin_init', function () {
	$ver = '1.121.0'; // 1.121.0: imports the 3 new comparison guides (existing posts skipped).
	if ( ! current_user_can( 'manage_options' ) || get_option( 'nura_s2_import' ) === $ver || ! defined( 'NURA_DIR' ) ) {
		return;
	}
	update_option( 'nura_s2_import', $ver, false );
	$made = array();
	if ( ! get_page_by_path( 'wig-encyclopedia' ) ) {
		$intro = '<p class="nura-answer"><strong>Short answer:</strong> this is a plain-language A-Z of wig words - hair types, grades, lace, textures, colour codes, density, fit and care - written by the NURA Beauty stylist team in Nairobi so you can shop with confidence. Tap a topic to jump to it.</p>';
		$outro = '<p>Still unsure? Read our <a href="{post:best-wigs-in-kenya-buying-guide}">wig buying guide</a>, compare <a href="{post:hd-lace-vs-transparent-lace}">HD vs transparent lace</a> and <a href="{post:closure-sizes-2x2-4x4-5x5-vs-13x4-frontal}">closure vs frontal sizes</a>, or <a href="{wa}">ask a stylist on WhatsApp</a>.</p>';
		$id    = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => 'wig-encyclopedia',
			'post_title'   => 'Wig Encyclopedia: Every Wig Term Explained',
			'post_status'  => 'publish',
			'post_content' => $intro . '[nura_encyclopedia]' . $outro,
		), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', 'Wig Encyclopedia: Lace, Hair Types, Textures & Colour Codes Explained | NURA Beauty' );
			update_post_meta( $id, 'rank_math_description', 'Every wig term explained in plain language: human hair vs semi-human vs fibre, remy and virgin, 13x4 frontal vs 4x4 closure, HD lace, textures, 1B/27/99J colours, density and care.' );
			update_post_meta( $id, 'rank_math_focus_keyword', 'wig terms explained' );
			$made[] = $id;
		}
	}
	$dir   = NURA_DIR . 'content/guides/';
	$index = file_exists( $dir . 'index.json' ) ? json_decode( (string) file_get_contents( $dir . 'index.json' ), true ) : array();
	$ref   = get_page_by_path( 'best-wigs-in-kenya-buying-guide', OBJECT, 'post' );
	foreach ( (array) $index as $slug => $meta ) {
		if ( get_page_by_path( $slug, OBJECT, 'post' ) || ! file_exists( $dir . $slug . '.html' ) ) {
			continue;
		}
		$args = array(
			'post_type'    => 'post',
			'post_name'    => $slug,
			'post_title'   => $meta[0],
			'post_content' => (string) file_get_contents( $dir . $slug . '.html' ),
			'post_status'  => 'publish',
		);
		if ( $ref ) {
			$args['post_category'] = wp_get_post_categories( $ref->ID );
			$args['post_author']   = (int) $ref->post_author;
		}
		$id = wp_insert_post( $args, true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', $meta[1] );
			update_post_meta( $id, 'rank_math_description', $meta[2] );
			update_post_meta( $id, 'rank_math_focus_keyword', $meta[3] );
			$made[] = $id;
		}
	}
	foreach ( $made as $id ) {
		$p = get_post( $id );
		if ( function_exists( 'nura_hub_links' ) ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => nura_hub_links( $p->post_content ) ) );
		}
	}
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
	do_action( 'litespeed_purge_all' );
}, 25 );
