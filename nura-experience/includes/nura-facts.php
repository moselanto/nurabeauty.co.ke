<?php
/**
 * NURA business facts - the single source of truth for the shop address, map,
 * opening hours, visit policy, delivery cut-off, returns and payment facts
 * (theme 1.74.0 / plugin 1.80.0).
 *
 * An identical copy lives in nura-beauty/inc/nura-facts.php and
 * nura-experience/includes/nura-facts.php. Whichever loads first defines the
 * functions; keep both copies identical.
 *
 * Change a value without code: save an array in the option
 * `nura_business_facts` (only the keys you want to override), or use the
 * `nura_business_facts` filter. Saving the option purges the AI summaries.
 *
 * Confirmed by the owner on 1 Nov 2026: Stanbank House, 2nd Floor, Room 20
 * (between Moi Avenue and Tom Mboya Street, Plus Code PR8G+554), Mon-Sat
 * 9:00-19:30, walk-ins and appointments, same-day Nairobi cut-off 5 PM,
 * 48-hour returns, pick-up at the shop, international shipping on request.
 * Postal code not confirmed, so it is left empty and omitted from schema.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nura_facts' ) ) {

	/** All facts (defaults merged with the saved option and the filter). */
	function nura_facts() {
		static $facts = null;
		if ( null !== $facts && ! doing_filter( 'nura_business_facts' ) ) {
			return $facts;
		}
		$defaults = array(
			'building'       => 'Stanbank House',
			'floor'          => '2nd Floor',
			'room'           => 'Room 20',
			'street'         => 'Between Moi Avenue and Tom Mboya Street',
			'area'           => 'Nairobi CBD',
			'locality'       => 'Nairobi',
			'region'         => 'Nairobi County',
			'country'        => 'KE',
			'postcode'       => '',
			'address_short'  => 'Stanbank House, 2nd Floor, Room 20, Nairobi CBD',
			'address_full'   => 'Stanbank House, 2nd Floor, Room 20, between Moi Avenue and Tom Mboya Street, Nairobi CBD, Kenya',
			'street_address' => 'Stanbank House, 2nd Floor, Room 20, between Moi Avenue and Tom Mboya Street',
			'plus_code'      => 'PR8G+554 Nairobi',
			'lat'            => '-1.2843125',
			'lng'            => '36.8255969',
			'place_id'       => 'ChIJGx-_W1sRLxgRxNUNZe1z9ug', // Google Business Profile listing (1.78.0).
			'map_url'        => 'https://www.google.com/maps/search/?api=1&query=NURA+Beauty+Stanbank+House+Nairobi&query_place_id=ChIJGx-_W1sRLxgRxNUNZe1z9ug',
			'map_embed'      => 'https://www.google.com/maps?q=-1.2843125,36.8255969&z=17&output=embed',
			'directions_url' => 'https://www.google.com/maps/dir/?api=1&destination=-1.2843125,36.8255969',
			'days'           => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
			'opens'          => '09:00',
			'closes'         => '19:30',
			'hours_text'     => 'Mon–Sat 9:00am–7:30pm',
			'hours_long'     => 'Monday to Saturday, 9:00am to 7:30pm (East Africa Time)',
			'visits'         => 'Walk-ins welcome; appointments available',
			'cutoff'         => '5 PM',
			'returns_window' => '48 hours',
			'returns_days'   => 2,
			'returns_text'   => 'Unworn, unaltered wigs in their original condition can be returned within 48 hours of delivery. Worn, cut or installed wigs cannot be returned for hygiene reasons.',
			'pickup'         => true,
			'international'  => 'International shipping is available on request. Message us on WhatsApp for a quote.',
			'cod'            => false,
			'till'           => '1679269',
			'default_brand'  => 'NURA Beauty',
		);
		$saved = get_option( 'nura_business_facts', array() );
		$facts = apply_filters( 'nura_business_facts', array_merge( $defaults, is_array( $saved ) ? $saved : array() ) );
		return $facts;
	}

	/** One fact by key. */
	function nura_fact( $key, $fallback = '' ) {
		$f = nura_facts();
		return isset( $f[ $key ] ) ? $f[ $key ] : $fallback;
	}

	/** PostalAddress node built only from confirmed facts. */
	function nura_fact_postal_address() {
		$node = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => (string) nura_fact( 'street_address' ),
			'addressLocality' => (string) nura_fact( 'locality' ),
			'addressRegion'   => (string) nura_fact( 'region' ),
			'addressCountry'  => (string) nura_fact( 'country' ),
		);
		$pc = trim( (string) nura_fact( 'postcode' ) );
		if ( '' !== $pc ) {
			$node['postalCode'] = $pc;
		}
		return $node;
	}

	/** GeoCoordinates node, or null when coordinates are not confirmed. */
	function nura_fact_geo() {
		$lat = trim( (string) nura_fact( 'lat' ) );
		$lng = trim( (string) nura_fact( 'lng' ) );
		return ( '' !== $lat && '' !== $lng ) ? array( '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng ) : null;
	}

	/** OpeningHoursSpecification list. */
	function nura_fact_hours_spec() {
		return array(
			array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => (array) nura_fact( 'days' ),
				'opens'     => (string) nura_fact( 'opens' ),
				'closes'    => (string) nura_fact( 'closes' ),
			),
		);
	}

	/**
	 * Brand for a product: WooCommerce Brands (product_brand) term, then a
	 * "brand" attribute, then the NURA own label (filter nura_product_brand).
	 * Tag supplier-brand products in Products > Brands so they are not shown
	 * as NURA Beauty.
	 */
	function nura_product_brand( $product ) {
		$id = is_object( $product ) && method_exists( $product, 'get_id' ) ? (int) $product->get_id() : (int) $product;
		$p  = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( $p && $p->is_type( 'variation' ) ) {
			$id = (int) $p->get_parent_id();
			$p  = wc_get_product( $id );
		}
		$name = '';
		if ( $id && taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $id, 'product_brand' );
			if ( is_array( $terms ) && $terms ) {
				$name = $terms[0]->name;
			}
		}
		if ( '' === $name && $p ) {
			foreach ( array( 'pa_brand', 'brand' ) as $attr ) {
				$v = trim( (string) $p->get_attribute( $attr ) );
				if ( '' !== $v ) {
					$name = $v;
					break;
				}
			}
		}
		if ( '' === $name ) {
			$name = (string) nura_fact( 'default_brand', 'NURA Beauty' );
		}
		return (string) apply_filters( 'nura_product_brand', $name, $id );
	}

	/* ---- Old values saved in the Customizer must not resurface ---- */

	function nura_facts_is_stale( $v ) {
		$v = strtolower( (string) $v );
		return false !== strpos( $v, 'imenti' ) || false !== strpos( $v, '8:00–20:00' ) || false !== strpos( $v, '8:00-20:00' ) || false !== strpos( $v, '8:00am to 8:00pm' );
	}
	add_filter( 'theme_mod_nura_address', function ( $v ) {
		return ( '' === trim( (string) $v ) || nura_facts_is_stale( $v ) ) ? nura_fact( 'address_short' ) : $v;
	} );
	add_filter( 'theme_mod_nura_hours', function ( $v ) {
		return ( '' === trim( (string) $v ) || nura_facts_is_stale( $v ) ) ? nura_fact( 'hours_text' ) : $v;
	} );
	add_filter( 'theme_mod_nura_inst_facts', function ( $v ) {
		return str_replace( array( 'Mon–Sat 8:00–20:00', 'Mon-Sat 8:00-20:00' ), nura_fact( 'hours_text' ), (string) $v );
	} );

	/** Saving facts purges AI summaries and caches so every channel updates at once. */
	function nura_facts_purge() {
		delete_transient( 'nura_llms' );
		delete_transient( 'nura_llms_full' );
		do_action( 'litespeed_purge_all' );
	}
	add_action( 'update_option_nura_business_facts', 'nura_facts_purge' );
	add_action( 'add_option_nura_business_facts', 'nura_facts_purge' );

	/* ---- One-time content migration (pages, posts, products in the database) ---- */

	/** Exact replacements for stored content: old address, hours, returns window, cut-off, COD wording. */
	function nura_facts_replacements() {
		$addr = (string) nura_fact( 'address_short' );
		return array(
			'Imenti House, Moi Avenue, Nairobi CBD, Kenya' => $addr . ', Kenya',
			'Imenti House, Moi Avenue, Nairobi CBD'        => $addr,
			'Imenti House, Moi Avenue, Nairobi'            => 'Stanbank House, 2nd Floor, Room 20, Nairobi',
			'Imenti House, Nairobi CBD'                    => $addr,
			"NURA's Imenti House studio"                   => "NURA's Stanbank House studio",
			'Imenti House studio'                          => 'Stanbank House studio (2nd Floor, Room 20)',
			'Imenti House'                                 => 'Stanbank House, 2nd Floor, Room 20',
			'within <strong>7 days</strong> of delivery'   => 'within <strong>48 hours</strong> of delivery',
			'within 7 days of delivery'                    => 'within 48 hours of delivery',
			'unworn unit, within 7 days'                   => 'unworn unit, within 48 hours',
			'wigs within 7 days'                           => 'wigs within 48 hours',
			'7-day returns'                                => '48-hour returns',
			'Monday to Saturday, 8:00am to 8:00pm'         => 'Monday to Saturday, 9:00am to 7:30pm',
			'Monday to Saturday, 8am to 8pm'               => 'Monday to Saturday, 9am to 7:30pm',
			'Mon–Sat 8:00–20:00'                           => (string) nura_fact( 'hours_text' ),
			'before 2:00 PM'                               => 'before 5:00 PM',
			'before <strong>2:00 PM</strong>'              => 'before <strong>5:00 PM</strong>',
			'(M-Pesa, card or cash on delivery)'           => '(M-Pesa or card; no cash on delivery)',
			'bank transfer, or cash on delivery in Nairobi' => 'bank transfer, or M-Pesa Till 1679269 for WhatsApp orders. No cash on delivery',
			'by appointment)'                              => 'walk-ins welcome)',
		);
	}

	add_action( 'admin_init', function () {
		$ver = '1.80.0';
		if ( get_option( 'nura_facts_migrated' ) === $ver || ! current_user_can( 'manage_options' ) || ! apply_filters( 'nura_facts_migrate', true ) ) {
			return;
		}
		global $wpdb;
		$map  = nura_facts_replacements();
		$like = array( '%Imenti%', '%7 days of delivery%', '%<strong>7 days</strong>%', '%8:00am to 8:00pm%', '%8am to 8pm%', '%8:00–20:00%', '%2:00 PM%', '%cash on delivery in Nairobi%', '%card or cash on delivery%', '%7-day returns%' );
		$where = implode( ' OR ', array_fill( 0, count( $like ), 'post_content LIKE %s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future') AND post_type IN ('page','post','product','nura_guide','nura_journal') AND ( {$where} ) LIMIT 500", $like ) );
		$changed = array();
		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$new = strtr( $post->post_content, $map );
			$exc = strtr( $post->post_excerpt, $map );
			if ( $new !== $post->post_content || $exc !== $post->post_excerpt ) {
				wp_update_post( array( 'ID' => $post->ID, 'post_content' => $new, 'post_excerpt' => $exc ) ); // Keeps a revision of the old text.
				$changed[] = (int) $post->ID;
			}
		}
		update_option( 'nura_facts_migrated', $ver, false );
		update_option( 'nura_facts_migrated_ids', $changed, false );
		nura_facts_purge();
	} );
}
