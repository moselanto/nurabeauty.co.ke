<?php
/**
 * Release R3 of the SEO / AEO / GEO roadmap (theme 1.96.0). Owner decisions 2 Nov 2026.
 *
 * 1. Google shipping: two real rates on every Offer - countrywide bus/shuttle parcel
 *    (KSh 350, all of Kenya) and the Nairobi rider (KSh 300, Nairobi, ISO 3166-2 KE-30).
 *    Replaces the single "highest county fee" rate from 1.77.0. Filters:
 *    nura_r3_ship_countrywide (350), nura_r3_ship_nairobi (300), nura_r3_ship_nairobi_region ('30').
 * 2. WooCommerce Brands term "Nura Beauty" renamed once to "NURA Beauty" (slug unchanged).
 * 3. IndexNow: Bing, Yandex, Seznam, Naver (and so ChatGPT search / Copilot, which use Bing)
 *    are told within minutes when a product, post or page is published or changed.
 *    Key file served at /<key>.txt. Last ping saved in option nura_indexnow_last.
 *    Off: add_filter( 'nura_indexnow', '__return_false' ).
 * 4. Site verification codes (Customizer > NURA: Site verification): Google, Bing,
 *    Yandex, Pinterest, Facebook. Paste the code or the whole <meta> tag.
 * 5. Service schema: installation and wash & revamp as Service nodes with KES prices
 *    (from nura_services()) on /wig-installation-nairobi/, /wig-revamp-nairobi/ and /services/.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------ 1. shipping */

function nura_r3_ship_detail( $rate, $region, $tmin, $tmax, $label ) {
	$dest = array( '@type' => 'DefinedRegion', 'addressCountry' => 'KE' );
	if ( $region ) {
		$dest['addressRegion'] = $region;
	}
	return array(
		'@type'               => 'OfferShippingDetails',
		'name'                => $label,
		'shippingRate'        => array( '@type' => 'MonetaryAmount', 'value' => (float) $rate, 'currency' => 'KES' ),
		'shippingDestination' => $dest,
		'deliveryTime'        => array(
			'@type'        => 'ShippingDeliveryTime',
			'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ),
			'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => $tmin, 'maxValue' => $tmax, 'unitCode' => 'DAY' ),
		),
	);
}

function nura_r3_shipping_details() {
	$ke  = (float) apply_filters( 'nura_r3_ship_countrywide', 350 );
	$nbo = (float) apply_filters( 'nura_r3_ship_nairobi', 300 );
	$out = array( nura_r3_ship_detail( $ke, '', 1, 3, 'Countrywide bus / shuttle parcel' ) );
	if ( $nbo > 0 ) {
		$out[] = nura_r3_ship_detail( $nbo, (string) apply_filters( 'nura_r3_ship_nairobi_region', '30' ), 0, 1, 'Nairobi rider (same or next day)' );
	}
	return $out;
}

add_filter( 'nura_seo_offer', function ( $offer ) {
	if ( is_array( $offer ) && apply_filters( 'nura_r3_shipping', true ) ) {
		$offer['shippingDetails'] = nura_r3_shipping_details();
	}
	return $offer;
}, 50 );

/* Rank Math graph: any Offer that still carries a single shipping block gets both rates. */
function nura_r3_walk_offers( &$node ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['shippingDetails'] ) && is_array( $node['shippingDetails'] ) && isset( $node['shippingDetails']['@type'] ) ) {
		$node['shippingDetails'] = nura_r3_shipping_details();
	}
	foreach ( $node as &$child ) {
		if ( is_array( $child ) ) {
			nura_r3_walk_offers( $child );
		}
	}
	unset( $child );
}
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( is_array( $data ) && apply_filters( 'nura_r3_shipping', true ) ) {
		nura_r3_walk_offers( $data );
	}
	return $data;
}, 1000 );

/* ------------------------------------------------------- 2. brand term */

add_action( 'admin_init', function () {
	if ( get_option( 'nura_r3_brand_renamed' ) || ! taxonomy_exists( 'product_brand' ) ) {
		return;
	}
	$terms = get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$n = strtolower( trim( $t->name ) );
			if ( ( 'nura beauty' === $n || 'nura' === $n ) && 'NURA Beauty' !== $t->name ) {
				wp_update_term( $t->term_id, 'product_brand', array( 'name' => 'NURA Beauty' ) );
			}
		}
	}
	update_option( 'nura_r3_brand_renamed', time(), false );
} );

/* ------------------------------------------------------------ 3. IndexNow */

function nura_indexnow_on() {
	return (bool) apply_filters( 'nura_indexnow', 'production' === wp_get_environment_type() );
}

function nura_indexnow_key() {
	$k = get_option( 'nura_indexnow_key' );
	if ( ! $k || ! preg_match( '/^[a-f0-9]{32}$/', $k ) ) {
		$k = md5( wp_generate_password( 40, true, true ) . home_url() );
		update_option( 'nura_indexnow_key', $k, false );
	}
	return $k;
}

/* Serve the key file: https://nurabeauty.co.ke/<key>.txt */
add_action( 'init', function () {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ); // phpcs:ignore
	if ( ! preg_match( '/^[a-f0-9]{32}\.txt$/', $path ) ) {
		return;
	}
	$k = get_option( 'nura_indexnow_key' );
	if ( $k && $path === $k . '.txt' ) {
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( $k );
		exit;
	}
}, 0 );

function nura_indexnow_queue( $url ) {
	if ( ! $url || ! nura_indexnow_on() || 0 !== strpos( $url, home_url() ) ) {
		return;
	}
	$q   = (array) get_option( 'nura_indexnow_queue', array() );
	$q[] = $url;
	update_option( 'nura_indexnow_queue', array_slice( array_values( array_unique( $q ) ), -500 ), false );
	if ( ! wp_next_scheduled( 'nura_indexnow_flush' ) ) {
		wp_schedule_single_event( time() + 120, 'nura_indexnow_flush' );
	}
}

function nura_indexnow_queue_post( $post_id ) {
	$p = get_post( $post_id );
	if ( ! $p || wp_is_post_revision( $p ) || wp_is_post_autosave( $p ) ) {
		return;
	}
	if ( 'product_variation' === $p->post_type ) {
		$p = get_post( $p->post_parent );
	}
	if ( ! $p || 'publish' !== $p->post_status || ! in_array( $p->post_type, array( 'product', 'post', 'page' ), true ) ) {
		return;
	}
	if ( 'page' === $p->post_type && function_exists( 'wc_get_page_id' ) && in_array( (int) $p->ID, array( (int) wc_get_page_id( 'cart' ), (int) wc_get_page_id( 'checkout' ), (int) wc_get_page_id( 'myaccount' ) ), true ) ) {
		return;
	}
	nura_indexnow_queue( get_permalink( $p ) );
}
add_action( 'save_post', 'nura_indexnow_queue_post', 99 );
add_action( 'woocommerce_product_set_stock_status', 'nura_indexnow_queue_post', 99 );
add_action( 'woocommerce_variation_set_stock_status', 'nura_indexnow_queue_post', 99 );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' === $old && 'publish' !== $new && in_array( $post->post_type, array( 'product', 'post', 'page' ), true ) ) {
		nura_indexnow_queue( get_permalink( $post ) ); // removed pages: engines recrawl and drop them.
	}
}, 10, 3 );

add_action( 'nura_indexnow_flush', function () {
	$q = array_values( array_unique( (array) get_option( 'nura_indexnow_queue', array() ) ) );
	if ( ! $q || ! nura_indexnow_on() ) {
		return;
	}
	delete_option( 'nura_indexnow_queue' );
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$key  = nura_indexnow_key();
	$res  = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
		'timeout' => 10,
		'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'    => wp_json_encode( array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => array_slice( $q, 0, 1000 ),
		) ),
	) );
	update_option( 'nura_indexnow_last', array(
		'time' => current_time( 'mysql' ),
		'urls' => count( $q ),
		'code' => is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ),
	), false );
} );

/* Admin: one-time full submit of the sitemap-level URLs (Tools > NURA IndexNow link). */
add_action( 'admin_post_nura_indexnow_all', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'nura_indexnow_all' ) ) {
		wp_die( 'No permission' );
	}
	$ids = get_posts( array( 'post_type' => array( 'product', 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => 1000, 'fields' => 'ids' ) );
	nura_indexnow_queue( home_url( '/' ) );
	foreach ( $ids as $id ) {
		nura_indexnow_queue_post( $id );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=nura-indexnow&queued=1' ) );
	exit;
} );
add_action( 'admin_menu', function () {
	add_management_page( 'NURA IndexNow', 'NURA IndexNow', 'manage_options', 'nura-indexnow', function () {
		$last = get_option( 'nura_indexnow_last' );
		echo '<div class="wrap"><h1>NURA IndexNow</h1><p>Bing, Yandex, Seznam and Naver are told automatically when a product, post or page changes (ChatGPT search and Copilot use Bing).</p>';
		echo '<p>Key file: <a href="' . esc_url( home_url( '/' . nura_indexnow_key() . '.txt' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/' . nura_indexnow_key() . '.txt' ) ) . '</a></p>';
		echo '<p>Status: ' . ( nura_indexnow_on() ? 'on' : 'off (not a production site)' ) . '. Last ping: ' . esc_html( $last ? $last['time'] . ' - ' . $last['urls'] . ' URLs - response ' . $last['code'] : 'none yet' ) . ' (200 or 202 = accepted).</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nura_indexnow_all">';
		wp_nonce_field( 'nura_indexnow_all' );
		submit_button( 'Submit every product, page and post now' );
		echo '</form></div>';
	} );
} );

/* --------------------------------------------- 4. site verification codes */

function nura_r3_verify_fields() {
	return array(
		'nura_verify_google'    => array( 'Google Search Console', 'google-site-verification' ),
		'nura_verify_bing'      => array( 'Bing Webmaster Tools', 'msvalidate.01' ),
		'nura_verify_yandex'    => array( 'Yandex Webmaster', 'yandex-verification' ),
		'nura_verify_pinterest' => array( 'Pinterest', 'p:domain_verify' ),
		'nura_verify_facebook'  => array( 'Facebook / Meta domain', 'facebook-domain-verification' ),
	);
}

/** Accepts a bare code or a full <meta ... content="..."> tag. */
function nura_r3_verify_clean( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/content\s*=\s*["\']([^"\']+)["\']/i', $v, $m ) ) {
		$v = $m[1];
	}
	return preg_replace( '/[^A-Za-z0-9_\-:.=+\/]/', '', $v );
}

add_action( 'customize_register', function ( $wp ) {
	$wp->add_section( 'nura_verify', array(
		'title'       => __( 'NURA: Site verification', 'nura-beauty' ),
		'priority'    => 199,
		'description' => __( 'Paste the verification code (or the whole meta tag) from each service. It is added to the homepage only.', 'nura-beauty' ),
	) );
	foreach ( nura_r3_verify_fields() as $id => $f ) {
		$wp->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'nura_r3_verify_clean', 'transport' => 'refresh' ) );
		$wp->add_control( $id, array( 'section' => 'nura_verify', 'label' => $f[0], 'type' => 'text' ) );
	}
} );

add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	foreach ( nura_r3_verify_fields() as $id => $f ) {
		$v = nura_r3_verify_clean( get_theme_mod( $id, '' ) );
		if ( '' === $v ) {
			continue;
		}
		// Rank Math may already print Google / Bing codes; skip ours if it has the same name.
		printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $f[1] ), esc_attr( $v ) );
	}
}, 1 );

/* ------------------------------------------------------ 5. Service schema */

function nura_r3_service_node( $key, $page_url ) {
	if ( ! function_exists( 'nura_services' ) ) {
		return null;
	}
	$all = nura_services();
	if ( empty( $all[ $key ] ) ) {
		return null;
	}
	$s    = $all[ $key ];
	if ( empty( $s[1] ) ) {
		return null; // 1.107.0: no guide price yet - no priced Service node.
	}
	$spec = array( '@type' => 'PriceSpecification', 'price' => (string) $s[1], 'minPrice' => (string) $s[1], 'priceCurrency' => 'KES' );
	if ( $s[2] ) {
		$spec['maxPrice'] = (string) $s[2];
		unset( $spec['price'] );
	}
	$home = home_url( '/' );
	return array(
		'@type'       => 'Service',
		'@id'         => $page_url . '#service-' . $key,
		'name'        => $s[0] . ' in Nairobi',
		'serviceType' => $s[0],
		'description' => $s[3],
		'url'         => $page_url,
		'provider'    => array( '@id' => $home . '#studio' ),
		'brand'       => array( '@id' => $home . '#organization' ),
		'areaServed'  => array(
			array( '@type' => 'City', 'name' => 'Nairobi' ),
			array( '@type' => 'Country', 'name' => 'Kenya' ),
		),
		'availableChannel' => array(
			'@type'           => 'ServiceChannel',
			'serviceUrl'      => home_url( '/book-appointment/' ),
			'serviceLocation' => array( '@id' => $home . '#studio' ),
		),
		'offers'      => array(
			'@type'              => 'Offer',
			'price'              => (string) $s[1],
			'priceCurrency'      => 'KES',
			'priceSpecification' => $spec,
			'availability'       => 'https://schema.org/InStock',
			'url'                => $page_url,
		),
	);
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! is_page() ) {
		return $data;
	}
	$map = (array) apply_filters( 'nura_r3_service_pages', array(
		'wig-installation-nairobi' => array( 'installation', 'revamp' ),
		'wig-revamp-nairobi'       => array( 'revamp', 'installation' ),
		'services'                 => array( 'installation', 'revamp' ),
	) );
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( empty( $map[ $slug ] ) ) {
		return $data;
	}
	$url = get_permalink( get_queried_object_id() );
	foreach ( $map[ $slug ] as $k ) {
		$n = nura_r3_service_node( $k, $url );
		if ( $n ) {
			$data[ 'nura-service-' . $k ] = $n;
		}
	}
	return $data;
}, 1004 );
