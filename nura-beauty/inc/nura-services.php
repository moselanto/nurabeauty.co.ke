<?php
/**
 * R6 (theme 1.85.0): services with prices, Journal authorship, installation page,
 * old-category redirects and the Kiswahili questions page.
 *
 * 1. Service prices (owner-supplied guide prices) as one list used by the
 *    installation page, the studio's structured data (OfferCatalog) and nothing else:
 *    edit nura_services() to change a price everywhere.
 * 2. Journal guides: visible "By the NURA Beauty stylist team, updated <real date>"
 *    line and matching Article author/dateModified in the structured data.
 * 3. /wig-installation-nairobi/ : prices, steps, booking link, FAQ (from the
 *    stylist knowledge base) with FAQPage markup. Created once on admin load.
 * 4. Empty legacy product categories 301 to the closest live collection (never
 *    a category that has products). Filter: nura_legacy_category_map.
 * 5. /maswali-ya-wigi/ : the most common questions in Kiswahili, lang="sw-KE".
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------- 1. services */

/** key => [ name, from, to (0 = "from" price), description ]. Guide prices from the owner. */
function nura_services() {
	// 1.107.0: owner's service list (3 Nov 2026): installation, styling, laundry, customization, repair, bridal styling.
	// A price of 0 means "quoted after consultation" (owner has not set a guide price yet).
	return apply_filters( 'nura_services', array(
		'installation'  => array( 'Wig installation', 1500, 0, 'A secure, natural-looking install of your NURA unit or your own.' ),
		'styling'       => array( 'Wig styling', 1000, 2500, 'A fresh cut, curls, a new parting or a sleek finish to change your look.' ),
		'revamp'        => array( 'Wig laundry (wash & revamp)', 1500, 0, 'Deep wash, condition and restyle to bring a tired unit back to life.' ),
		'customization' => array( 'Wig customization', 0, 0, 'Plucked hairline, bleached knots, cut-to-shape and other changes so a unit looks made for you.' ),
		'colouring'     => array( 'Colouring (human hair units)', 2500, 5000, 'Custom colour for 100% human hair units.' ),
		'relacing'      => array( 'Wig repair & re-lacing', 2000, 0, 'New lace or repairs so your unit fits and lasts.' ),
		'bridal'        => array( 'Bridal styling', 0, 0, 'Wig installation and styling for your wedding day, planned with you in advance.' ),
	) );
}

function nura_service_price_text( $s ) {
	$f = function ( $n ) {
		return 'KSh ' . number_format( (float) $n );
	};
	if ( empty( $s[1] ) ) {
		return __( 'Price agreed after consultation', 'nura-beauty' );
	}
	return $s[2] ? $f( $s[1] ) . ' - ' . number_format( (float) $s[2] ) : sprintf( __( 'From %s', 'nura-beauty' ), $f( $s[1] ) );
}

add_shortcode( 'nura_service_prices', function () {
	$book = home_url( '/book-appointment/' );
	$h    = '<div class="nura-svc"><table class="nura-svc__t"><thead><tr><th>' . esc_html__( 'Service', 'nura-beauty' ) . '</th><th>' . esc_html__( 'Guide price', 'nura-beauty' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( nura_services() as $k => $s ) {
		$h .= '<tr><td><strong>' . esc_html( $s[0] ) . '</strong><br><span>' . esc_html( $s[3] ) . '</span></td><td>' . esc_html( nura_service_price_text( $s ) ) . '</td><td><a class="nura-btn nura-btn--ghost" href="' . esc_url( add_query_arg( 'service', 'relacing' === $k ? 'installation' : $k, $book ) ) . '">' . esc_html__( 'Book this', 'nura-beauty' ) . '</a></td></tr>';
	}
	return $h . '</tbody></table><p class="nura-svc__note">' . esc_html__( 'Guide prices; your stylist confirms the final price for your wig. Studio visits are mornings, by booking.', 'nura-beauty' ) . '</p></div>';
} );

/** Studio structured data: the services as an OfferCatalog (prices from nura_services()). */
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$items = array();
	foreach ( nura_services() as $s ) {
		if ( empty( $s[1] ) ) {
			$items[] = array( '@type' => 'Offer', 'itemOffered' => array( '@type' => 'Service', 'name' => $s[0], 'description' => $s[3], 'provider' => array( '@id' => home_url( '/' ) . '#studio' ) ) );
			continue;
		}
		$spec = array( '@type' => 'PriceSpecification', 'minPrice' => (string) $s[1], 'priceCurrency' => 'KES' );
		if ( $s[2] ) {
			$spec['maxPrice'] = (string) $s[2];
		}
		$items[] = array( '@type' => 'Offer', 'priceSpecification' => $spec, 'itemOffered' => array( '@type' => 'Service', 'name' => $s[0], 'description' => $s[3], 'provider' => array( '@id' => home_url( '/' ) . '#studio' ) ) );
	}
	foreach ( $data as $k => &$node ) {
		if ( is_array( $node ) && isset( $node['@id'] ) && '#studio' === substr( (string) $node['@id'], -7 ) ) {
			$node['hasOfferCatalog'] = array( '@type' => 'OfferCatalog', 'name' => 'NURA Beauty studio services', 'itemListElement' => $items );
		}
	}
	unset( $node );
	return $data;
}, 1002 );

/* ------------------------------------------------ 2. journal authorship */

add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || false !== strpos( $content, 'nura-byline' ) ) {
		return $content;
	}
	$line = '<p class="nura-byline"><small>' . esc_html__( 'By the NURA Beauty stylist team, Nairobi.', 'nura-beauty' ) . ' ' . esc_html( sprintf( __( 'Updated %s.', 'nura-beauty' ), get_the_modified_date( 'j F Y' ) ) ) . '</small></p>';
	return $line . $content;
}, 5 );

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! is_singular( 'post' ) ) {
		return $data;
	}
	foreach ( $data as $k => &$node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) || ! array_intersect( (array) $node['@type'], array( 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
			continue;
		}
		$node['author']       = array( '@id' => home_url( '/' ) . '#organization' ); // 1.124.1: one entity, one name.
		$node['dateModified'] = get_the_modified_date( 'c' );
		if ( empty( $node['datePublished'] ) ) {
			$node['datePublished'] = get_the_date( 'c' );
		}
	}
	unset( $node );
	return $data;
}, 1003 );

/* --------------------------------------------- 4. legacy category redirects */

add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->slug ) || (int) $term->count > 0 ) {
		return;
	}
	if ( get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true, 'fields' => 'ids' ) ) ) {
		return;
	}
	$map  = apply_filters( 'nura_legacy_category_map', array(
		'lace-hd'              => 'human-hair-wigs',
		'lace-front-hd-lace'   => 'human-hair-wigs',
		'bridal'               => 'wigs',
		'extensions'           => 'wigs',
		'pre-loved'            => '',
		'custom-bespoke'       => '',
		'accessories'          => '',
		'confidence-line'      => '',
	) );
	if ( ! isset( $map[ $term->slug ] ) ) {
		return;
	}
	$to = $map[ $term->slug ];
	$t  = $to ? get_term_by( 'slug', $to, 'product_cat' ) : false;
	$u  = ( $t && ! is_wp_error( $t ) && (int) $t->count > 0 ) ? get_term_link( $t ) : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) );
	if ( $u && ! is_wp_error( $u ) ) {
		wp_safe_redirect( $u, 301 );
		exit;
	}
}, 3 );

/* --------------------------------------- 3 + 5. pages (created once) */

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.85.0' === get_option( 'nura_r6_pages' ) ) {
		return;
	}
	update_option( 'nura_r6_pages', '1.85.0', false );
	$pages = array(
		'wig-installation-nairobi' => array(
			'Wig Installation in Nairobi: Prices and Booking',
			'<p class="nura-answer"><strong>Short answer:</strong> NURA Beauty installs wigs at our studio at Stanbank House, 2nd Floor, Room 20, Moi Avenue, Nairobi CBD. Installation is from KSh 1,500 for your NURA wig or one you bought elsewhere. Book a morning appointment online; a deposit secures your slot and is deducted from your bill.</p>[nura_service_prices]<h2>How it works</h2><ol><li>Choose your service and a morning time on the <a href="{page:book-appointment}">booking page</a>.</li><li>Pay the deposit by M-Pesa and enter the code. We confirm on WhatsApp; a booking is only confirmed once we confirm it.</li><li>Come with clean, dry hair, braided flat if possible, and arrive on time (15-minute grace period).</li><li>Your stylist fits the wig, cuts the lace, and styles the hairline.</li></ol><h2>Questions about installation</h2>[nura_installation_faq]<p>Read <a href="{post:wig-installation-nairobi-what-to-expect}">what to expect at your installation</a>, <a href="{post:glueless-wigs-kenya}">glueless wigs</a> and <a href="{post:frontal-vs-closure-wig}">lace front vs closure</a>. Need a wig first? <a href="{page:find-your-wig}">Find your wig in 5 questions</a>.</p><p class="nura-cta-row"><a class="nura-btn nura-btn--gold" href="{page:book-appointment}">Book installation</a> <a class="nura-btn nura-btn--ghost" href="{wa}">Ask on WhatsApp</a></p>',
			'Wig Installation in Nairobi: Prices from KSh 1,500 & Booking | NURA Beauty',
			'Wig installation in Nairobi CBD from KSh 1,500: lace front, 4x4 closure and glueless fitting, styling, revamp, colouring and re-lacing. Book a morning slot at Stanbank House.',
			'wig installation nairobi',
		),
		'maswali-ya-wigi' => array(
			'Maswali ya Wigi Kenya: Majibu kutoka NURA Beauty',
			'[nura_maswali]',
			'Maswali ya Wigi Kenya: Bei, Installation, Delivery na Zaidi | NURA Beauty',
			'Majibu ya maswali ya kawaida kuhusu wigi Nairobi na Kenya nzima: bei ya human hair wig, wig isiyohitaji gundi, installation, delivery, M-Pesa na utunzaji.',
			'maswali ya wigi kenya',
		),
	);
	$made = array();
	foreach ( $pages as $slug => $p ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $p[0], 'post_status' => 'publish', 'post_content' => $p[1] ), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', $p[2] );
			update_post_meta( $id, 'rank_math_description', $p[3] );
			update_post_meta( $id, 'rank_math_focus_keyword', $p[4] );
			$made[] = $id;
		}
	}
	if ( function_exists( 'nura_hub_links' ) ) {
		foreach ( $made as $id ) {
			$post = get_post( $id );
			wp_update_post( array( 'ID' => $id, 'post_content' => nura_hub_links( $post->post_content ) ) );
		}
	}
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );

/** Installation FAQ (visible) from the stylist knowledge base + FAQPage markup. */
add_shortcode( 'nura_installation_faq', function () {
	if ( ! class_exists( 'NURAX_Knowledge' ) ) {
		return '';
	}
	$h = '';
	foreach ( NURAX_Knowledge::faq( 'installation' ) as $qa ) {
		$h .= '<details class="nura-wq-q"><summary><h3>' . esc_html( $qa[0] ) . '</h3></summary><p>' . esc_html( $qa[1] ) . '</p></details>';
	}
	return $h;
} );
add_action( 'wp_head', function () {
	if ( ! class_exists( 'NURAX_Knowledge' ) || ! is_page( 'wig-installation-nairobi' ) ) {
		return;
	}
	$items = array();
	foreach ( NURAX_Knowledge::faq( 'installation' ) as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => get_permalink() . '#faq', 'mainEntity' => $items ) ) . '</script>' . "\n";
}, 32 );

/* ------------------------------------------------------ 5. Kiswahili */

function nura_sw_items() {
	$f = function ( $k, $d = '' ) {
		return function_exists( 'nura_fact' ) ? (string) nura_fact( $k, $d ) : $d;
	};
	$hours = 'Jumatatu hadi Jumamosi, saa ' . $f( 'opens', '09:00' ) . ' hadi ' . $f( 'closes', '19:30' );
	return array(
		array( 'Ni wig gani poa ya kutumia kila siku (daily)?', 'Wigi ya headband au 4x4 closure. Hazihitaji gundi, unaivaa kwa dakika chache na kuiondoa jioni. Kwa matumizi ya kila siku kwa mwaka mmoja au zaidi, fikiria wigi ya 100% human hair.' ),
		array( 'Bei ya human hair wig ni ngapi?', 'Wigi zetu za 100% human hair zinauzwa {human_min} hadi {human_max}, bei ikipanda kulingana na urefu. Wigi za fibre zinaanza {fibre_min}, na za human-hair blend (semi-human) zinaanza {blend_min}.' ),
		array( 'Nataka wig ya budget ya 3k. Mnayo?', 'Ndiyo. Tuna wigi za fibre chini ya KSh 3,000, zikiwemo headband (hazihitaji gundi) na 4x4 closure za kinky straight (Yaki), loose wave na bob ya water curl.' ),
		array( 'Nataka wig isihitaji glue. Ni ipi?', 'Headband wig haihitaji gundi kabisa. 4x4 closure kwa kawaida huvaliwa bila gundi kwa kutumia kamba na vishikio vinavyoweza kurekebishwa.' ),
		array( 'Mnaweka wig (installation)?', 'Ndiyo. Installation inaanza KSh 1,500, kwa wigi ya NURA au ya mahali pengine. Tunafanya studio yetu: {address}. Weka miadi ya asubuhi mtandaoni.' ),
		array( 'Mnaweza kuniinstall leo?', 'Miadi ya studio ni asubuhi. Chagua muda wa mapema kwenye ukurasa wa kuweka miadi, au tuandikie WhatsApp kuangalia nafasi ya leo. Miadi inathibitishwa tu baada ya sisi kuthibitisha.' ),
		array( 'Mko CBD? Duka lenu liko wapi?', 'Ndiyo. Tuko {address}. Tunafungua ' . $hours . '. Unakaribishwa bila miadi ukitaka kuangalia wigi.' ),
		array( 'Mnafanya delivery?', 'Ndiyo. Nairobi tunaleta siku hiyo hiyo ukiagiza kabla ya {cutoff}. Nchi nzima ni siku 1 hadi 3 kwa courier, ada ni {delivery_ke}, na ada kamili inaonekana unapolipa.' ),
		array( 'Naweza kulipa pole pole?', 'Ndiyo, kupitia Lipa Mdogo Mdogo: lipa amana ya 50% kisha salio ndani ya wiki 1 au 2, na tunakutumia wigi yako.' ),
		array( 'Mnapokea M-Pesa?', 'Ndiyo. M-Pesa na kadi wakati wa kulipa mtandaoni, M-Pesa Buy Goods Till {till} kwa oda za WhatsApp, au bank transfer. Kulipa unapopokea (cash au M-Pesa) ni kwa usafirishaji ndani ya Nairobi CBD tu; oda nyingine zote hulipwa kabla ya kutumwa. Ukichukua wigi studio unaweza kulipa hapo.' ),
		array( 'Hii wig inaweza kudumu?', 'Ukiitunza vizuri: human hair hudumu mwaka mmoja au zaidi, semi-human miezi 6 hadi 9, na fibre miezi 3 hadi 6. Usilale na wigi, na ifunue kutoka chini kwa kitana chenye meno mapana.' ),
		array( 'Nataka bob fupi. Mko na?', 'Ndiyo. Tuna Human Hair Bob Lace Front na 8 Inch Water Curl 4x4 Closure Bob. Angalia bei na upatikanaji kwenye duka letu.' ),
		array( 'Mko na curls?', 'Ndiyo: jerry curl, deep curl, water curl na bubble curl, kwa human hair na fibre.' ),
		array( 'Nataka wig ionekane natural. Nichague ipi?', 'Kinky straight (Yaki) ya rangi ya natural black (1B) au dark brown inaonekana kama nywele zako za asili. Lace front hutoa mstari wa nywele wa asili zaidi, na tunaweza kuiweka kwa ajili yako.' ),
		array( 'Naweza kurudisha wig nikibadili mawazo?', 'Wigi ambayo haijavaliwa wala kubadilishwa inaweza kurudishwa ndani ya saa 48 baada ya kupokea, studio au kwa courier. Wigi iliyovaliwa, kukatwa au kuwekwa haiwezi kurudishwa kwa sababu za usafi.' ),
	);
}

function nura_sw_fill( $t ) {
	return class_exists( 'NURAX_Knowledge' ) ? NURAX_Knowledge::fill( $t ) : $t;
}

add_shortcode( 'nura_maswali', function () {
	$h  = '<div class="nura-wq"><p class="nura-answer"><strong>Jibu fupi:</strong> ' . esc_html( nura_sw_fill( 'NURA Beauty ni studio na duka la wigi Nairobi CBD, {address}. Wigi za fibre zinaanza {fibre_min}, human hair {human_min} hadi {human_max}. Tunaweka wigi (installation kuanzia KSh 1,500) na tunaleta nchi nzima.' ) ) . '</p>';
	foreach ( nura_sw_items() as $qa ) {
		$h .= '<details class="nura-wq-q"><summary><h3>' . esc_html( $qa[0] ) . '</h3></summary><p>' . esc_html( nura_sw_fill( $qa[1] ) ) . '</p></details>';
	}
	$h .= '<p>' . wp_kses_post( sprintf( 'Unataka kuchagua wigi kwa maswali 5 tu? <a href="%1$s">Tafuta wigi yako</a>. Maswali zaidi kwa Kiingereza: <a href="%2$s">maswali 120+ ya wigi</a>. Karibu uandike WhatsApp kwa Kiswahili au Sheng.', esc_url( home_url( '/find-your-wig/' ) ), esc_url( home_url( '/wig-questions/' ) ) ) ) . '</p></div>';
	return $h;
} );
add_action( 'wp_head', function () {
	if ( ! is_page( 'maswali-ya-wigi' ) ) {
		return;
	}
	$items = array();
	foreach ( nura_sw_items() as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => nura_sw_fill( $qa[1] ) ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => get_permalink() . '#faq', 'inLanguage' => 'sw-KE', 'mainEntity' => $items ) ) . '</script>' . "\n";
}, 32 );
add_filter( 'language_attributes', function ( $out ) {
	return is_page( 'maswali-ya-wigi' ) ? preg_replace( '/lang="[^"]*"/', 'lang="sw-KE"', $out ) : $out;
}, 30 );
add_action( 'wp_head', function () {
	echo '<style>.nura-svc__t{width:100%;border-collapse:collapse}.nura-svc__t td,.nura-svc__t th{padding:12px 10px;border-bottom:1px solid rgba(0,0,0,.1);text-align:left;vertical-align:top}.nura-svc__t span{font-size:13px;opacity:.75}.nura-svc__note{font-size:13px;opacity:.7}.nura-byline{opacity:.7;margin:0 0 1em}</style>' . "\n";
}, 41 );
