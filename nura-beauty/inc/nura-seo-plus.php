<?php
/**
 * NURA SEO round 3 (v1.42.0) - richer search snippets, data-driven category facts,
 * Merchant-ready shipping/returns schema, Kenyan language tag and /llms.txt for AI search.
 *
 * Everything here is generated from real store data (prices, stock, attributes,
 * WooCommerce shipping zones), never invented, and only fills gaps: anything you
 * type into Rank Math for a product/category always wins.
 *
 * Switch off with: add_filter( 'nura_seo_plus_enabled', '__return_false' );
 *
 * @package NURA_Beauty
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! apply_filters( 'nura_seo_plus_enabled', true ) ) {
	return;
}

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

/** Cache version - bumped whenever a product is saved so all cached facts refresh. */
function nura_seo_cache_v() {
	return (int) get_option( 'nura_seo_cache_v', 1 );
}
add_action( 'save_post_product', function () {
	update_option( 'nura_seo_cache_v', nura_seo_cache_v() + 1, false );
} );

/** "KSh 4,500" */
function nura_seo_ksh( $n ) {
	return 'KSh ' . number_format( (float) $n, 0, '.', ',' );
}

/** Lowest delivery cost to Kenya from the real WooCommerce shipping zones (null if unknown). */
function nura_seo_ke_shipping_rate() {
	$key = 'nura_seo_ship_' . nura_seo_cache_v();
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return '' === $hit ? null : (float) $hit;
	}
	$min = null;
	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		foreach ( WC_Shipping_Zones::get_zones() as $z ) {
			$ke = false;
			foreach ( (array) $z['zone_locations'] as $loc ) {
				$code = is_object( $loc ) ? $loc->code : ( $loc['code'] ?? '' );
				if ( 0 === strpos( (string) $code, 'KE' ) ) {
					$ke = true;
				}
			}
			if ( ! $ke ) {
				continue;
			}
			foreach ( (array) $z['shipping_methods'] as $m ) {
				if ( 'yes' !== $m->enabled ) {
					continue;
				}
				if ( 'flat_rate' === $m->id || 'local_pickup' === $m->id ) {
					$c = trim( (string) $m->get_option( 'cost' ) );
					if ( '' === $c ) {
						$c = '0';
					}
					if ( is_numeric( $c ) ) {
						$min = null === $min ? (float) $c : min( $min, (float) $c );
					}
				}
			}
		}
	}
	set_transient( $key, null === $min ? '' : (string) $min, 12 * HOUR_IN_SECONDS );
	return $min;
}

/** Real price range, count and hair types for a product category (cached). */
function nura_seo_term_facts( $term ) {
	$key = 'nura_seo_tf_' . $term->term_id . '_' . nura_seo_cache_v();
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return $hit;
	}
	$facts = array( 'count' => 0, 'min' => null, 'max' => null, 'types' => array(), 'in_stock' => 0 );
	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products( array(
			'status'   => 'publish',
			'limit'    => 300,
			'category' => array( $term->slug ),
		) );
		foreach ( $products as $p ) {
			if ( 'hidden' === $p->get_catalog_visibility() ) {
				continue;
			}
			$facts['count']++;
			if ( $p->is_in_stock() ) {
				$facts['in_stock']++;
			}
			if ( $p->is_type( 'variable' ) ) {
				$lo = (float) $p->get_variation_price( 'min', true );
				$hi = (float) $p->get_variation_price( 'max', true );
			} else {
				$lo = $hi = (float) $p->get_price();
			}
			if ( $lo > 0 ) {
				$facts['min'] = null === $facts['min'] ? $lo : min( $facts['min'], $lo );
			}
			if ( $hi > 0 ) {
				$facts['max'] = null === $facts['max'] ? $hi : max( $facts['max'], $hi );
			}
			foreach ( array_map( 'trim', explode( ',', (string) $p->get_attribute( 'pa_hair-type' ) ) ) as $t ) {
				if ( '' !== $t ) {
					$facts['types'][ $t ] = true;
				}
			}
		}
	}
	$facts['types'] = array_keys( $facts['types'] );
	set_transient( $key, $facts, 12 * HOUR_IN_SECONDS );
	return $facts;
}

/** Has the owner typed a custom Rank Math title/description? Then we never override it. */
function nura_seo_has_custom_meta( $field ) {
	if ( is_singular() ) {
		return '' !== trim( (string) get_post_meta( get_queried_object_id(), 'rank_math_' . $field, true ) );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		return '' !== trim( (string) get_term_meta( get_queried_object_id(), 'rank_math_' . $field, true ) );
	}
	return true;
}

/* ---------------------------------------------------------------------------
 * 1. Search-result titles and descriptions that earn the click
 * ------------------------------------------------------------------------- */

function nura_seo_product_snippet_parts( $p ) {
	$parts = array();
	foreach ( array( 'pa_hair-type', 'pa_texture', 'pa_length', 'pa_lace-type' ) as $a ) {
		$v = trim( (string) $p->get_attribute( $a ) );
		if ( '' !== $v ) {
			$parts[] = preg_replace( '/\s*,\s*/', ' / ', $v );
		}
	}
	return array_slice( $parts, 0, 3 );
}

add_filter( 'rank_math/frontend/title', function ( $title ) {
	if ( nura_seo_has_custom_meta( 'title' ) ) {
		return $title;
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$price = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) $p->get_price();
			$name  = wp_strip_all_tags( $p->get_name() );
			return $price > 0
				/* translators: 1: product name, 2: price */
				? sprintf( __( '%1$s - %2$s | NURA Beauty Kenya', 'nura-beauty' ), $name, nura_seo_ksh( $price ) )
				/* translators: %s: product name */
				: sprintf( __( '%s | NURA Beauty Kenya', 'nura-beauty' ), $name );
		}
	}
	if ( function_exists( 'is_product_category' ) && is_product_category() && absint( get_query_var( 'paged' ) ) < 2 ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term ) {
			$f    = nura_seo_term_facts( $t );
			$name = $t->name;
			$kenya = false === stripos( $name, 'kenya' ) ? $name . ' in Kenya' : $name;
			return $f['min']
				/* translators: 1: category name, 2: lowest price */
				? sprintf( __( '%1$s - Prices from %2$s | NURA Beauty', 'nura-beauty' ), $kenya, nura_seo_ksh( $f['min'] ) )
				/* translators: %s: category name */
				: sprintf( __( '%s | NURA Beauty', 'nura-beauty' ), $kenya );
		}
	}
	return $title;
}, 20 );

add_filter( 'rank_math/frontend/description', function ( $desc ) {
	if ( nura_seo_has_custom_meta( 'description' ) ) {
		return $desc;
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$price = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) $p->get_price();
			$parts = nura_seo_product_snippet_parts( $p );
			$out   = wp_strip_all_tags( $p->get_name() ) . ( $parts ? ': ' . implode( ', ', $parts ) . '.' : '.' );
			if ( $price > 0 ) {
				$out .= ' ' . ( $p->is_type( 'variable' ) ? 'From ' : '' ) . nura_seo_ksh( $price ) . '.';
			}
			$out .= ' ' . __( 'Same-day Nairobi delivery, countrywide shipping. Pay by M-Pesa or card.', 'nura-beauty' );
			return wp_html_excerpt( $out, 158, '' );
		}
	}
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$t = get_queried_object();
		if ( $t instanceof WP_Term && function_exists( 'nura_category_description_text' ) ) {
			$f   = nura_seo_term_facts( $t );
			$lead = $f['count'] && $f['min']
				/* translators: 1: count, 2: category, 3: price */
				? sprintf( __( '%1$d %2$s from %3$s.', 'nura-beauty' ), $f['count'], strtolower( $t->name ), nura_seo_ksh( $f['min'] ) ) . ' '
				: '';
			return wp_html_excerpt( $lead . nura_category_description_text( $t ), 158, '' );
		}
	}
	return $desc;
}, 20 );

/* ---------------------------------------------------------------------------
 * 2. Visible "quick facts" on category pages - real numbers Google and shoppers trust
 * ------------------------------------------------------------------------- */
add_action( 'woocommerce_archive_description', function () {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() || absint( get_query_var( 'paged' ) ) > 1 ) {
		return;
	}
	$t = get_queried_object();
	if ( ! $t instanceof WP_Term ) {
		return;
	}
	$f = nura_seo_term_facts( $t );
	if ( ! $f['count'] ) {
		return;
	}
	$items = array();
	/* translators: 1: count, 2: category name */
	$items[] = sprintf( _n( '%1$d %2$s style', '%1$d %2$s styles', $f['count'], 'nura-beauty' ), $f['count'], esc_html( preg_replace( '/wigs$/', 'wig', strtolower( $t->name ) ) ) );
	if ( $f['min'] && $f['max'] && $f['max'] > $f['min'] ) {
		$items[] = esc_html( nura_seo_ksh( $f['min'] ) . ' - ' . nura_seo_ksh( $f['max'] ) );
	} elseif ( $f['min'] ) {
		$items[] = esc_html( sprintf( __( 'From %s', 'nura-beauty' ), nura_seo_ksh( $f['min'] ) ) );
	}
	if ( $f['types'] ) {
		$items[] = esc_html( implode( ', ', array_slice( $f['types'], 0, 3 ) ) );
	}
	$items[] = esc_html__( 'Same-day Nairobi delivery', 'nura-beauty' );
	$items[] = esc_html__( 'M-Pesa or card', 'nura-beauty' );
	echo '<ul class="nura-cat-facts" aria-label="' . esc_attr__( 'At a glance', 'nura-beauty' ) . '"><li>' . implode( '</li><li>', $items ) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
}, 15 );

/* ---------------------------------------------------------------------------
 * 3. Schema upgrades on Rank Math's graph
 * ------------------------------------------------------------------------- */

/** Return policy object shared by Organization and Offers. */
function nura_seo_return_policy() {
	$rp      = get_page_by_path( 'returns' );
	$returns = ( $rp && 'publish' === $rp->post_status ) ? get_permalink( $rp ) : '';
	return array_filter( array(
		'@type'                => 'MerchantReturnPolicy',
		'applicableCountry'    => 'KE',
		'returnPolicyCountry'  => 'KE',
		'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'   => 7,
		'returnMethod'         => array( 'https://schema.org/ReturnInStore', 'https://schema.org/ReturnByMail' ),
		'returnFees'           => 'https://schema.org/ReturnFeesCustomerResponsibility',
		'refundType'           => 'https://schema.org/FullRefund',
		'merchantReturnLink'   => $returns ? $returns : null,
	) );
}

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$home = home_url( '/' );
	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		$types = (array) $node['@type'];

		// Organization: online store details Google now reads at business level.
		if ( array_intersect( $types, array( 'Organization', 'OnlineStore' ) ) ) {
			if ( ! in_array( 'OnlineStore', $types, true ) ) {
				$types[] = 'OnlineStore';
				$data[ $key ]['@type'] = array_values( array_unique( $types ) );
			}
			$data[ $key ]['hasMerchantReturnPolicy'] = nura_seo_return_policy();
			$data[ $key ]['areaServed']   = array( '@type' => 'Country', 'name' => 'Kenya' );
			$data[ $key ]['knowsLanguage'] = array( 'en', 'sw' );
			$c = function_exists( 'nura_seo_brand_contact' ) ? nura_seo_brand_contact() : array();
			if ( $c ) {
				$data[ $key ]['contactPoint'] = array(
					'@type'             => 'ContactPoint',
					'contactType'       => 'customer service',
					'telephone'         => $c['telephone'],
					'email'             => $c['email'],
					'areaServed'        => 'KE',
					'availableLanguage' => array( 'English', 'Swahili' ),
				);
			}
		}

		// Product: material, colours, audience and real delivery cost on every offer.
		if ( array_intersect( $types, array( 'Product', 'ProductGroup' ) ) && function_exists( 'is_product' ) && is_product() ) {
			$p = wc_get_product( get_queried_object_id() );
			if ( ! $p ) {
				continue;
			}
			$material = trim( (string) $p->get_attribute( 'pa_hair-type' ) );
			if ( $material ) {
				$data[ $key ]['material'] = $material;
			}
			$colour = trim( (string) $p->get_attribute( 'pa_colour' ) );
			if ( $colour ) {
				$data[ $key ]['color'] = $colour;
			}
			if ( has_term( array( 'wigs' ), 'product_cat', $p->get_id() ) || $material ) {
				$data[ $key ]['audience'] = array( '@type' => 'PeopleAudience', 'suggestedGender' => 'female', 'suggestedMinAge' => 13 );
			}
			$cats = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $cats ) && $cats ) {
				$data[ $key ]['category'] = implode( ' > ', array_slice( $cats, 0, 2 ) );
			}
			if ( $p->is_type( 'variable' ) ) {
				$lo = (float) $p->get_variation_price( 'min', true );
				$hi = (float) $p->get_variation_price( 'max', true );
				if ( $lo > 0 && $hi > $lo && isset( $data[ $key ]['offers'] ) && is_array( $data[ $key ]['offers'] ) && isset( $data[ $key ]['offers']['@type'] ) ) {
					$offer = $data[ $key ]['offers'];
					$offer['@type']      = 'AggregateOffer';
					$offer['lowPrice']   = $lo;
					$offer['highPrice']  = $hi;
					$offer['offerCount'] = count( $p->get_visible_children() );
					unset( $offer['price'] );
					$data[ $key ]['offers'] = $offer;
				}
			}
		}
	}
	return $data;
}, 100 );

/** Real Kenya delivery cost + full return policy on each Offer (extends nura-seo.php). */
add_filter( 'nura_seo_offer', function ( $offer ) {
	$rate = nura_seo_ke_shipping_rate();
	if ( null !== $rate && isset( $offer['shippingDetails'] ) && is_array( $offer['shippingDetails'] ) ) {
		$offer['shippingDetails']['shippingRate'] = array(
			'@type'    => 'MonetaryAmount',
			'value'    => $rate,
			'currency' => 'KES',
		);
	}
	$offer['hasMerchantReturnPolicy'] = nura_seo_return_policy();
	return $offer;
} );

/* ---------------------------------------------------------------------------
 * 4. Tell Google the site is written for Kenya (en-KE), not the USA
 * ------------------------------------------------------------------------- */
add_filter( 'language_attributes', function ( $out ) {
	return str_replace( 'lang="en-US"', 'lang="en-KE"', $out );
} );
add_filter( 'rank_math/opengraph/facebook/og_locale', function () {
	return 'en_KE';
} );
add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		printf( '<link rel="alternate" hreflang="en-KE" href="%1$s" />' . "\n" . '<link rel="alternate" hreflang="x-default" href="%1$s" />' . "\n", esc_url( home_url( '/' ) ) );
	}
}, 2 );

/* ---------------------------------------------------------------------------
 * 5. /llms.txt - a plain summary for AI assistants (ChatGPT, Perplexity, Gemini,
 *    Google AI Overviews) so they describe and recommend NURA accurately.
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : ''; // phpcs:ignore
	if ( '/llms.txt' !== $path ) {
		return;
	}
	$c   = function_exists( 'nura_seo_brand_contact' ) ? nura_seo_brand_contact() : array( 'email' => '', 'telephone' => '' );
	$out = "# NURA Beauty\n\n> Online wig shop in Nairobi, Kenya. Human hair, human-hair blend (semi-human) and heat-resistant fibre wigs - lace front, closure, glueless, headband, bob and curly styles - plus hair extensions and wig care. Prices in Kenya Shillings; pay by M-Pesa or card via Paystack. Same-day delivery in Nairobi, countrywide delivery across Kenya. Studio at Imenti House, Moi Avenue, Nairobi CBD (by appointment).\n\n";
	$out .= "Every product page states its hair type (100% human hair, human-hair blend or fibre). Contact: " . $c['telephone'] . ' / ' . $c['email'] . "\n\n## Shop by category\n\n";
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 40, 'orderby' => 'count', 'order' => 'DESC' ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( 'uncategorized' === $t->slug ) {
				continue;
			}
			$f    = nura_seo_term_facts( $t );
			$note = $f['min'] ? ' - ' . $f['count'] . ' styles from ' . nura_seo_ksh( $f['min'] ) : '';
			$out .= '- [' . $t->name . '](' . get_term_link( $t ) . ')' . $note . "\n";
		}
	}
	$out .= "\n## Help\n\n";
	foreach ( array( 'about' => 'About NURA', 'faq' => 'FAQ', 'delivery' => 'Delivery', 'returns' => 'Returns', 'contact' => 'Contact', 'book-appointment' => 'Book a fitting or installation' ) as $slug => $label ) {
		$pg = get_page_by_path( $slug );
		if ( $pg && 'publish' === $pg->post_status ) {
			$out .= '- [' . $label . '](' . get_permalink( $pg ) . ")\n";
		}
	}
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$out .= '- [All products](' . wc_get_page_permalink( 'shop' ) . ")\n";
	}
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 1 );
