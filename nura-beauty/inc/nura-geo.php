<?php
/**
 * NURA GEO / AEO (theme 1.65.0): make NURA easy for AI assistants and answer
 * engines to understand, quote and recommend.
 *
 * 1. /llms.txt       - short, factual Markdown summary of the business (llmstxt.org format).
 * 2. /llms-full.txt  - the same plus the live catalogue (name, hair type, price, stock, URL).
 * 3. Answer-ready facts added to the homepage FAQ (visible text + FAQPage schema):
 *    registration, returns at the studio or by courier, Lipa Mdogo Mdogo, bookings.
 *
 * Everything is generated from live WooCommerce and Customizer data, cached for 6 hours
 * and refreshed when a product is saved.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Page URL by slug, or '' when the page is not published. */
function nura_geo_page( $slug ) {
	$p = get_page_by_path( $slug );
	return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
}

/** Price text for a product: "KSh 2,500" or "KSh 2,500 - 3,500". */
function nura_geo_price( $p ) {
	if ( $p->is_type( 'variable' ) ) {
		$lo = (float) $p->get_variation_price( 'min', true );
		$hi = (float) $p->get_variation_price( 'max', true );
		return $hi > $lo ? 'KSh ' . number_format( $lo ) . ' - ' . number_format( $hi ) : 'KSh ' . number_format( $lo );
	}
	return 'KSh ' . number_format( (float) $p->get_price() );
}

/** Build the Markdown summary. */
function nura_geo_llms( $full = false ) {
	$c     = function_exists( 'nura_seo_brand_contact' ) ? nura_seo_brand_contact() : array( 'telephone' => '+254714994898', 'email' => 'care@nurabeauty.co.ke' );
	$home  = home_url( '/' );
	$range = function_exists( 'nura_schema_price_range' ) ? nura_schema_price_range() : '';
	$o     = array();

	$o[] = '# NURA Beauty';
	$o[] = '';
	$o[] = '> NURA Beauty is a Nairobi, Kenya wig shop and studio ("The House of Radiant Confidence"). It sells 100% human hair, human-hair blend (semi-human) and heat-resistant fibre wigs online across Kenya, with clear Kenya Shilling prices, same-day Nairobi delivery and a fitting and installation studio in Nairobi CBD.';
	$o[] = '';
	$o[] = '## Business facts';
	$o[] = '- Trading name: NURA Beauty. Registered business name: NURA CROWN & BEAUTY (Kenya Business Registration Service, Business No. BN-MJS7ALRV).';
	$o[] = '- Website: ' . $home;
	$o[] = '- Studio: Imenti House, Moi Avenue, Nairobi CBD, Kenya (visits by appointment). Hours: Monday to Saturday, 8:00 to 20:00 (EAT).';
	$o[] = '- Phone and WhatsApp: ' . $c['telephone'] . '. Email: ' . $c['email'] . '.';
	if ( $range ) {
		$o[] = '- Price range: ' . $range . ' (Kenya Shillings).';
	}
	$o[] = '- Delivery: same day in Nairobi for orders before 5:00 PM; 1 to 3 days to the rest of Kenya (Mombasa, Kisumu, Nakuru, Eldoret and more); rider from KSh 300; free Nairobi delivery over KSh 10,000; international shipping on request.';
	$o[] = '- Payment: M-Pesa, Visa and Mastercard (via Paystack), M-Pesa Buy Goods Till 1679269 for WhatsApp orders, bank transfer. No cash on delivery; customers collecting from the studio may pay in person.';
	$o[] = '- Lipa Mdogo Mdogo (pay in instalments): 50% deposit, balance within 1 or 2 weeks, wig released when fully paid.';
	$o[] = '- Returns: unworn, unaltered wigs within 7 days of delivery, returned at the studio or by courier. Worn, cut or installed wigs cannot be returned for hygiene reasons.';
	$o[] = '- Services: wig consultation, fitting, installation, revamp, styling and bridal units at the studio (morning appointments, deposit secures a slot); free video and WhatsApp consultations.';
	$o[] = '- Every product states its hair type (100% human hair, human-hair blend or heat-resistant fibre) so shoppers know exactly what they are buying.';
	$o[] = '';

	$o[] = '## Key pages';
	$pages = array(
		'Shop all wigs' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
		'About NURA'    => nura_geo_page( 'about' ) ? nura_geo_page( 'about' ) : nura_geo_page( 'about-us' ),
		'Contact'       => nura_geo_page( 'contact' ) ? nura_geo_page( 'contact' ) : nura_geo_page( 'contact-us' ),
		'Book a studio appointment' => nura_geo_page( 'book-appointment' ),
		'Installation and revamp services' => nura_geo_page( 'installation' ),
		'AI Wig Finder' => nura_geo_page( 'ai-wig-finder' ),
		'FAQ'           => nura_geo_page( 'faq' ),
		'Delivery'      => nura_geo_page( 'shipping-delivery' ) ? nura_geo_page( 'shipping-delivery' ) : nura_geo_page( 'delivery' ),
		'Returns'       => nura_geo_page( 'returns' ) ? nura_geo_page( 'returns' ) : nura_geo_page( 'returns-refunds' ),
		'Warranty'      => nura_geo_page( 'warranty' ),
		'Track an order' => nura_geo_page( 'track-order' ),
		'Wig prices in Kenya (live)' => nura_geo_page( 'wig-prices-kenya' ),
		'Wigs in Mombasa' => nura_geo_page( 'wigs-mombasa' ),
		'Wigs in Kisumu'  => nura_geo_page( 'wigs-kisumu' ),
		'Wigs in Nakuru'  => nura_geo_page( 'wigs-nakuru' ),
		'Wigs in Eldoret' => nura_geo_page( 'wigs-eldoret' ),
	);
	foreach ( $pages as $label => $url ) {
		if ( $url ) {
			$o[] = '- [' . $label . '](' . $url . ')';
		}
	}
	$o[] = '';

	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 30 ) );
	if ( ! is_wp_error( $cats ) && $cats ) {
		$o[] = '## Collections';
		foreach ( $cats as $t ) {
			if ( 'uncategorized' === $t->slug ) {
				continue;
			}
			$desc = trim( wp_strip_all_tags( (string) $t->description ) );
			$desc = $desc ? ': ' . wp_trim_words( $desc, 22, '...' ) : '';
			$o[]  = '- [' . $t->name . '](' . get_term_link( $t ) . ') (' . (int) $t->count . ' products)' . $desc;
		}
		$o[] = '';
	}

	$guides = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 15 ) );
	if ( $guides ) {
		$o[] = '## Guides';
		foreach ( $guides as $g ) {
			$o[] = '- [' . wp_strip_all_tags( get_the_title( $g ) ) . '](' . get_permalink( $g ) . ')';
		}
		$o[] = '';
	}

	if ( function_exists( 'nura_home_faqs' ) ) {
		$o[] = '## Frequently asked questions';
		foreach ( nura_home_faqs() as $qa ) {
			$o[] = '### ' . wp_strip_all_tags( $qa[0] );
			$o[] = wp_strip_all_tags( $qa[1] );
			$o[] = '';
		}
	}

	if ( $full && function_exists( 'wc_get_products' ) ) {
		$o[] = '## Catalogue (live)';
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => 400, 'orderby' => 'title', 'order' => 'ASC', 'visibility' => 'catalog' ) );
		foreach ( $products as $p ) {
			$hair  = trim( (string) $p->get_attribute( 'pa_hair-type' ) );
			$stock = $p->is_in_stock() ? 'in stock' : 'out of stock';
			$o[]   = '- [' . wp_strip_all_tags( $p->get_name() ) . '](' . get_permalink( $p->get_id() ) . ') - ' . nura_geo_price( $p ) . ( $hair ? ' - ' . $hair : '' ) . ' - ' . $stock;
		}
		$o[] = '';
	}
	$o[] = 'Last updated: ' . wp_date( 'j F Y' ) . '. Prices and stock change; the product page is always the source of truth.';
	return implode( "\n", $o ) . "\n";
}

/** Serve /llms.txt and /llms-full.txt. */
add_action( 'parse_request', function () {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) ) : '';
	$path = untrailingslashit( (string) $path );
	if ( '/llms.txt' !== $path && '/llms-full.txt' !== $path ) {
		return;
	}
	$full = '/llms-full.txt' === $path;
	$key  = $full ? 'nura_llms_full' : 'nura_llms';
	$body = get_transient( $key );
	if ( false === $body ) {
		$body = nura_geo_llms( $full );
		set_transient( $key, $body, 6 * HOUR_IN_SECONDS );
	}
	status_header( 200 );
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' ); // Readable by AI tools, kept out of Google's result pages.
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text built from sanitised data.
	exit;
}, 0 );

add_action( 'save_post_product', function () {
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );

/** Answer-ready facts on the homepage FAQ (visible and in FAQPage schema). */
add_filter( 'nura_home_faqs', function ( $faqs ) {
	foreach ( $faqs as $i => $qa ) {
		if ( false !== stripos( $qa[0], 'return' ) ) {
			$faqs[ $i ][1] = __( 'Yes. Unworn, unaltered wigs in their original condition can be returned within 7 days of delivery, either at our Imenti House studio in Nairobi CBD or by courier. Worn, cut or installed wigs cannot be returned for hygiene reasons.', 'nura-beauty' );
		}
	}
	$faqs[] = array(
		__( 'Is NURA Beauty a registered business in Kenya?', 'nura-beauty' ),
		__( 'Yes. NURA Beauty trades under the registered business name NURA CROWN & BEAUTY (Business No. BN-MJS7ALRV, Registration of Business Names Act, Kenya), with its studio at Imenti House, Moi Avenue, Nairobi CBD.', 'nura-beauty' ),
	);
	$faqs[] = array(
		__( 'Can I pay for a wig in instalments in Kenya?', 'nura-beauty' ),
		__( 'Yes, with Lipa Mdogo Mdogo: pay a 50% deposit today and the balance within 1 or 2 weeks. Your wig is reserved and released once it is fully paid.', 'nura-beauty' ),
	);
	$faqs[] = array(
		__( 'Do you install wigs in Nairobi?', 'nura-beauty' ),
		__( 'Yes. Book a morning appointment at our Nairobi CBD studio for fitting, installation, revamp or styling. A deposit secures your slot; video and WhatsApp consultations are free.', 'nura-beauty' ),
	);
	return $faqs;
}, 99 );

/** Point AI tools to the summary. */
add_action( 'wp_head', function () {
	echo '<link rel="alternate" type="text/markdown" title="NURA Beauty summary for AI assistants" href="' . esc_url( home_url( '/llms.txt' ) ) . '">' . "\n";
}, 3 );
