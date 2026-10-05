<?php
/**
 * NURA SEO structured data (schema.org JSON-LD).
 *
 * Adds the structured data WooCommerce does NOT already provide, without
 * duplicating it. WooCommerce core emits Product, Review, BreadcrumbList and a
 * basic WebSite node itself, so this module intentionally leaves those alone and
 * adds:
 *   - Organization + LocalBusiness (HealthAndBeautyBusiness) with full NAP,
 *     opening hours, logo and social profiles (sameAs) - site-wide.
 *   - WebSite with a SearchAction (sitelinks search box) - front page.
 *   - FAQPage - only on the FAQ page, for FAQ rich results.
 *
 * All values are grounded in NURA's real published details and are filterable.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Schema {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/** Whether Rank Math, Yoast or SEOPress is active and already owns Organization/WebSite schema. */
	private function seo_plugin_active() {
		if ( function_exists( 'nura_seo_plugin_active' ) ) {
			return nura_seo_plugin_active();
		}
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' );
	}

	private function print_jsonld( $data ) {
		if ( empty( $data ) ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json">'
			. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	}

	public function output() {
		$home = home_url( '/' );
		$seo  = $this->seo_plugin_active();

		// --- Organization + LocalBusiness (site-wide) ---
		$logo   = '';
		$log_id = (int) get_theme_mod( 'custom_logo' );
		if ( $log_id ) {
			$src = wp_get_attachment_image_url( $log_id, 'full' );
			if ( $src ) {
				$logo = $src;
			}
		}
		if ( ! $logo && function_exists( 'get_site_icon_url' ) ) {
			$logo = get_site_icon_url( 512 );
		}

		$same_as = apply_filters( 'nurax_schema_sameas', array(
			'https://www.instagram.com/nurabeauty',
			'https://www.tiktok.com/@nurabeauty',
		) );

		$business = array(
			'@context'    => 'https://schema.org',
			// With Rank Math/Yoast/SEOPress active it already declares the Organization entity,
			// so emit only a complementary LocalBusiness node here to avoid duplicate Organization.
			'@type'       => $seo ? 'HealthAndBeautyBusiness' : array( 'Organization', 'HealthAndBeautyBusiness' ),
			'@id'         => $seo ? ( $home . '#localbusiness' ) : ( $home . '#business' ),
			'name'        => get_bloginfo( 'name' ),
			'legalName'   => 'NURA CROWN & BEAUTY',
			'url'         => $home,
			'description' => get_bloginfo( 'description' ),
			'email'       => apply_filters( 'nurax_schema_email', 'care@nurabeauty.co.ke' ),
			'telephone'   => apply_filters( 'nurax_schema_phone', '+254714994898' ),
			'priceRange'  => apply_filters( 'nurax_schema_pricerange', 'KSh' ),
			'currenciesAccepted' => 'KES',
			'paymentAccepted'    => 'M-Pesa, Visa, Mastercard, PayPal, Cash on Delivery',
			'areaServed'  => 'Kenya',
			'address'     => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Imenti House, Moi Avenue, Nairobi CBD',
				'addressLocality' => 'Nairobi',
				'addressRegion'   => 'Nairobi County',
				'postalCode'      => '00100',
				'addressCountry'  => 'KE',
			),
			'geo'         => array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => '-1.2837',
				'longitude' => '36.8272',
			),
			'openingHoursSpecification' => array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
					'opens'     => '09:00',
					'closes'    => '18:00',
				),
			),
		);
		if ( $logo ) {
			$business['logo']  = $logo;
			$business['image'] = $logo;
		}
		if ( ! empty( $same_as ) ) {
			$business['sameAs'] = array_values( $same_as );
		}
		$this->print_jsonld( $business );

		// --- WebSite + SearchAction (front page only) ---
		if ( false === $seo && ( is_front_page() || is_home() ) ) {
			$this->print_jsonld( array(
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => get_bloginfo( 'name' ),
				'publisher'       => array( '@id' => $home . '#business' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}',
					),
					'query-input' => 'required name=search_term_string',
				),
			) );
		}

		// --- FAQPage (FAQ page only) ---
		if ( is_page( 'faq' ) ) {
			$this->print_jsonld( $this->faq_schema() );
		}
	}

	private function faq_schema() {
		$qas = apply_filters( 'nurax_schema_faq', array(
			array(
				'Are NURA wigs real human hair?',
				'Yes. Our premium units are verified human hair with a provenance certificate and written guarantee. We never sell synthetic hair as human hair.',
			),
			array(
				'How do I pay?',
				'M-Pesa, Visa/Mastercard, PayPal, and pay-on-delivery in Nairobi. NURA Flex is available on units above KES 15,000.',
			),
			array(
				'How fast is delivery?',
				'Same-day within Nairobi on orders before 2:00pm, 1 to 3 business days countrywide, and 3 to 10 business days internationally with tracking.',
			),
			array(
				'Can I return or exchange a wig?',
				'Unworn, unaltered units can be returned within 48 hours of delivery. Worn, cut or installed units cannot be returned for hygiene reasons.',
			),
			array(
				'Do you install wigs?',
				'Yes - we offer installation, styling, colouring, revamp and repairs at our Nairobi studio, and at home for VIP clients.',
			),
		) );

		$items = array();
		foreach ( $qas as $qa ) {
			if ( empty( $qa[0] ) || empty( $qa[1] ) ) {
				continue;
			}
			$items[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $qa[0] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $qa[1] ),
				),
			);
		}
		if ( empty( $items ) ) {
			return array();
		}
		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $items,
		);
	}
}
