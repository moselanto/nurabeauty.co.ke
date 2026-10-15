<?php
/**
 * NURA schema graph (v1.55.0): one connected business entity + service and
 * category enrichments, layered onto Rank Math's JSON-LD graph (Rank Math stays
 * the single owner of the graph; this only corrects and enriches its nodes).
 *
 * 1. Organization node = Organization + OnlineStore + HealthAndBeautyBusiness, with the
 *    studio address, hours, price range, payments and services. Address comes from
 *    Customizer > NURA Options > Brand (nura_address) so a relocation is one edit.
 * 2. Installation page: drop the Article/Person nodes Rank Math adds to pages and
 *    describe the real services (from the Installation page settings) as Service +
 *    Offer nodes provided by the same business.
 * 3. Shop / category pages: CollectionPage gets an ItemList of the products shown.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Default studio address (current). Override with the Customizer field nura_address. */
function nura_schema_address() {
	$default = 'Imenti House, Moi Avenue, Nairobi CBD';
	$street  = trim( (string) get_theme_mod( 'nura_address', '' ) );
	$street  = '' !== $street ? $street : $default;
	return array(
		'is_default' => ( $street === $default ),
		'node'       => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $street,
			'addressLocality' => 'Nairobi',
			'addressRegion'   => 'Nairobi County',
			'postalCode'      => '00100',
			'addressCountry'  => 'KE',
		),
	);
}

/** Parse "From KSh 1,500" / "KSh 1,000 - 2,500" into min/max numbers. */
function nura_schema_parse_price( $text ) {
	preg_match_all( '/\d[\d,]*/', (string) $text, $m );
	$nums = array_map( function ( $n ) {
		return (float) str_replace( ',', '', $n );
	}, $m[0] );
	$nums = array_values( array_filter( $nums ) );
	if ( ! $nums ) {
		return array();
	}
	return array( 'min' => min( $nums ), 'max' => count( $nums ) > 1 ? max( $nums ) : null );
}

/** Services from the Installation page settings: [ name, price text, description ]. */
function nura_schema_services() {
	if ( ! function_exists( 'nura_lines' ) ) {
		return array();
	}
	$rows = nura_lines( 'nura_inst_services', 3 );
	return is_array( $rows ) ? $rows : array();
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$home   = home_url( '/' );
	$org_id = $home . '#organization';
	$addr   = nura_schema_address();
	$is_install = is_page( 'installation' );

	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		$types = (array) $node['@type'];

		// 1. One business entity.
		if ( array_intersect( $types, array( 'Organization', 'OnlineStore' ) ) ) {
			$types = array_values( array_unique( array_merge( $types, array( 'Organization', 'OnlineStore', 'HealthAndBeautyBusiness' ) ) ) );
			$data[ $key ]['@type']              = $types;
			$data[ $key ]['legalName']          = 'NURA CROWN & BEAUTY';
			$data[ $key ]['slogan']             = 'The House of Radiant Confidence';
			$logo_id = (int) get_theme_mod( 'custom_logo' );
			$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
			if ( $logo && empty( $data[ $key ]['image'] ) ) {
				$data[ $key ]['image'] = $logo;
			}
			$data[ $key ]['address']            = $addr['node'];
			$data[ $key ]['priceRange']         = 'KSh 1,500 - KSh 17,300';
			$data[ $key ]['currenciesAccepted'] = 'KES';
			$data[ $key ]['paymentAccepted']    = 'M-Pesa, Visa, Mastercard, Bank Transfer, Cash on Delivery (Nairobi)';
			$data[ $key ]['openingHoursSpecification'] = array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
					'opens'     => '09:00',
					'closes'    => '18:00',
				),
			);
			if ( $addr['is_default'] ) {
				$data[ $key ]['geo']    = array( '@type' => 'GeoCoordinates', 'latitude' => '-1.2837', 'longitude' => '36.8272' );
				$data[ $key ]['hasMap'] = 'https://www.google.com/maps/search/?api=1&query=Imenti+House+Moi+Avenue+Nairobi';
			}
			$data[ $key ]['knowsAbout'] = array(
				'Wigs', 'Human hair wigs', 'Human hair blend (semi-human) wigs', 'Heat-resistant fibre wigs',
				'Lace front wigs', '4x4 lace closure wigs', 'Headband wigs', 'Glueless wigs',
				'Wig installation', 'Wig revamp', 'Wig styling', 'Wig colouring', 'Wig repair', 'Wig care',
			);
			$data[ $key ]['areaServed'] = array(
				array( '@type' => 'City', 'name' => 'Nairobi' ),
				array( '@type' => 'Country', 'name' => 'Kenya' ),
			);
		}

		// 2. Installation page is a service page, not a blog article.
		if ( $is_install && array_intersect( $types, array( 'Article', 'BlogPosting', 'NewsArticle', 'Person' ) ) ) {
			unset( $data[ $key ] );
			continue;
		}
		if ( $is_install && array_intersect( $types, array( 'WebPage' ) ) ) {
			unset( $data[ $key ]['author'] );
			$data[ $key ]['about'] = array( '@id' => $home . 'installation/#services' );
		}

		// 3. Shop / category: list the products shown.
		if ( array_intersect( $types, array( 'CollectionPage' ) ) && ( ( function_exists( 'is_product_category' ) && is_product_category() ) || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
			global $wp_query;
			$items = array();
			$pos   = 1;
			if ( $wp_query && ! empty( $wp_query->posts ) ) {
				foreach ( $wp_query->posts as $p ) {
					if ( 'product' !== get_post_type( $p ) ) {
						continue;
					}
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'url'      => get_permalink( $p ),
						'name'     => wp_strip_all_tags( get_the_title( $p ) ),
					);
					if ( $pos > 30 ) {
						break;
					}
				}
			}
			if ( $items ) {
				$data[ $key ]['mainEntity'] = array(
					'@type'           => 'ItemList',
					'numberOfItems'   => count( $items ),
					'itemListElement' => $items,
				);
			}
		}
	}

	// 2b. Service catalogue on the Installation page.
	if ( $is_install ) {
		$services = array();
		foreach ( nura_schema_services() as $i => $svc ) {
			$name = isset( $svc[0] ) ? trim( wp_strip_all_tags( $svc[0] ) ) : '';
			if ( '' === $name ) {
				continue;
			}
			$s = array(
				'@type'       => 'Service',
				'@id'         => $home . 'installation/#service-' . ( $i + 1 ),
				'name'        => $name,
				'serviceType' => $name,
				'description' => isset( $svc[2] ) ? wp_strip_all_tags( $svc[2] ) : '',
				'provider'    => array( '@id' => $org_id ),
				'areaServed'  => array( '@type' => 'City', 'name' => 'Nairobi' ),
				'url'         => $home . 'installation/',
			);
			$price = nura_schema_parse_price( isset( $svc[1] ) ? $svc[1] : '' );
			if ( $price ) {
				$spec = array( '@type' => 'PriceSpecification', 'priceCurrency' => 'KES', 'minPrice' => $price['min'] );
				if ( $price['max'] ) {
					$spec['maxPrice'] = $price['max'];
				}
				$s['offers'] = array(
					'@type'              => 'Offer',
					'priceCurrency'      => 'KES',
					'price'              => $price['min'],
					'priceSpecification' => $spec,
					'availability'       => 'https://schema.org/InStock',
					'url'                => $home . 'installation/',
				);
			}
			$services[] = array_filter( $s );
		}
		if ( $services ) {
			$data['nuraServices'] = array(
				'@type'           => 'OfferCatalog',
				'@id'             => $home . 'installation/#services',
				'name'            => __( 'Wig installation, revamp and styling services in Nairobi', 'nura-beauty' ),
				'itemListElement' => $services,
			);
		}
	}
	return $data;
}, 40 );

/** Stronger, search-friendly H1s on the shop and installation pages. */
add_filter( 'woocommerce_page_title', function ( $title ) {
	return ( function_exists( 'is_shop' ) && is_shop() && ! is_search() ) ? __( 'Shop Wigs in Kenya', 'nura-beauty' ) : $title;
} );
