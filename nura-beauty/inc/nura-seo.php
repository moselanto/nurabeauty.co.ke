<?php
/**
 * NURA SEO layer (v1.32.0) - built for how Kenyans search for wigs.
 *
 * Target searches: wigs in Kenya, wigs in Nairobi, human hair wigs Kenya,
 * semi human hair wigs, frontal / lace front wigs, glueless wigs, bob wigs,
 * headband wigs, wig prices in Kenya, wig shop Nairobi CBD.
 *
 * 1. Homepage "Buy wigs online in Kenya" guide with internal links, and a
 *    visible FAQ with matching FAQPage schema.
 * 2. Product schema enrichment (brand, condition, Kenya shipping, 7-day
 *    return policy) for Rank Math and plain WooCommerce output.
 * 3. Descriptive alt text on product images that have none.
 *
 * @package NURA_Beauty
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/** Link to a product category by slug (falls back to the shop). */
function nura_seo_cat_link( $slug, $label ) {
	$url  = '';
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $term && !is_wp_error( $term ) ) {
		$link = get_term_link( $term );
		$url  = is_wp_error( $link ) ? '' : $link;
	}
	if ( '' === $url ) {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/** Homepage FAQ - the same Q&As are shown on the page and in the schema. */
function nura_home_faqs() {
	$phone = function_exists( 'nura_opt' ) ? nura_opt( 'nura_phone' ) : '+254 714 994 898';
	return apply_filters( 'nura_home_faqs', array(
		array(
			__( 'How much is a wig in Kenya?', 'nura-beauty' ),
			__( 'At NURA, heat-resistant fibre wigs start from about KSh 1,500, human-hair blend (semi-human) wigs from about KSh 4,500, and 100% human hair lace front wigs from about KSh 7,500 up to around KSh 17,000 for longer lengths. Every price is shown in Kenya Shillings on the product page.', 'nura-beauty' ),
		),
		array(
			__( 'Where can I buy wigs in Nairobi?', 'nura-beauty' ),
			/* translators: %s: phone number */
			sprintf( __( 'Shop online at nurabeauty.co.ke or visit our studio at Imenti House, Moi Avenue, Nairobi CBD (by appointment). You can also order on WhatsApp at %s.', 'nura-beauty' ), $phone ),
		),
		array(
			__( 'Do you deliver wigs countrywide in Kenya?', 'nura-beauty' ),
			__( 'Yes. We deliver the same day in Nairobi on orders placed before 5pm, and to Mombasa, Kisumu, Nakuru, Eldoret and the rest of Kenya in 1 to 3 days.', 'nura-beauty' ),
		),
		array(
			__( 'What is the difference between human hair, semi-human and synthetic wigs?', 'nura-beauty' ),
			__( '100% human hair wigs look and move most naturally and can be heat-styled and coloured. Human-hair blend wigs (often called semi-human) mix human hair with fibre for a lower price. Heat-resistant fibre (synthetic) wigs are the most affordable and hold their style. Every NURA product name states its hair type.', 'nura-beauty' ),
		),
		array(
			__( 'How do I pay for my wig?', 'nura-beauty' ),
			__( 'Pay with M-Pesa or card (Visa, Mastercard) securely through Paystack, by bank transfer, or cash on delivery within Nairobi.', 'nura-beauty' ),
		),
		array(
			__( 'Which wig is best for beginners?', 'nura-beauty' ),
			__( 'Glueless and headband wigs are the easiest - no glue, no lace cutting, ready to wear in minutes. Bob wigs are also light and easy to manage. Not sure? Take the Wig Finder quiz or ask NURA Stylist.', 'nura-beauty' ),
		),
		array(
			__( 'Can I return a wig?', 'nura-beauty' ),
			__( 'Unworn, unaltered wigs in original condition can be returned within 7 days of delivery. Worn, cut or installed wigs cannot be returned for hygiene reasons.', 'nura-beauty' ),
		),
	) );
}

/** Homepage "Buy wigs online in Kenya" guide, internal links and FAQ. */
function nura_home_seo_section() {
	$faqs = nura_home_faqs();
	?>
	<section class="section nura-seo-home" id="wigs-in-kenya">
		<div class="nura-container nura-seo-home__grid">
			<div class="nura-seo-home__intro">
				<p class="nura-eyebrow"><?php esc_html_e( 'Your wig guide', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Buy wigs online in Kenya', 'nura-beauty' ); ?></h2>
				<p><?php
					printf(
						/* translators: 1-6: links to wig categories */
						esc_html__( 'NURA is a Nairobi wig shop serving women across Kenya. Choose from %1$s, %2$s, %3$s, %4$s, %5$s and %6$s - in straight, body wave, water wave, jerry curl, kinky and deep curl textures, from short bobs to 22-inch lengths.', 'nura-beauty' ),
						nura_seo_cat_link( 'human-hair-wigs', __( '100% human hair wigs', 'nura-beauty' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						nura_seo_cat_link( 'lace-front-wigs', __( 'HD lace front (frontal) wigs', 'nura-beauty' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						nura_seo_cat_link( 'closure-wigs', __( '4x4 closure wigs', 'nura-beauty' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						nura_seo_cat_link( 'headband-wigs', __( 'headband wigs', 'nura-beauty' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						nura_seo_cat_link( 'bob-wigs', __( 'bob wigs', 'nura-beauty' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						nura_seo_cat_link( 'curly-wigs', __( 'curly wigs', 'nura-beauty' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
					);
				?></p>
				<p><?php esc_html_e( 'Every wig is quality-checked and clearly labelled as human hair, human-hair blend (semi-human) or heat-resistant fibre, so you know exactly what you are paying for. Prices are in Kenya Shillings, with same-day delivery in Nairobi before 5pm, countrywide delivery in 1-3 days, and payment by M-Pesa, card or cash on delivery.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-faq nura-seo-home__faq">
				<h2><?php esc_html_e( 'Wigs in Kenya: your questions answered', 'nura-beauty' ); ?></h2>
				<?php foreach ( $faqs as $qa ) : ?>
				<details>
					<summary><?php echo esc_html( $qa[0] ); ?></summary>
					<p><?php echo esc_html( $qa[1] ); ?></p>
				</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	$items = array();
	foreach ( $faqs as $qa ) {
		$items[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $qa[0] ),
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $qa[1] ) ),
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ) ) . '</script>' . "\n";
}

/** Kenya shipping, 7-day return policy, condition and seller on one Offer. */
function nura_seo_enrich_offer( $offer ) {
	if ( !is_array( $offer ) ) {
		return $offer;
	}
	$offer['itemCondition'] = 'https://schema.org/NewCondition';
	if ( empty( $offer['seller'] ) ) {
		$offer['seller'] = array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) );
	}
	if ( empty( $offer['priceValidUntil'] ) ) {
		$offer['priceValidUntil'] = gmdate( 'Y' ) . '-12-31';
	}
	$offer['shippingDetails'] = array(
		'@type'               => 'OfferShippingDetails',
		'shippingDestination' => array( '@type' => 'DefinedRegion', 'addressCountry' => 'KE' ),
		'deliveryTime'        => array(
			'@type'        => 'ShippingDeliveryTime',
			'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ),
			'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 3, 'unitCode' => 'DAY' ),
		),
	);
	$offer['hasMerchantReturnPolicy'] = array(
		'@type'                => 'MerchantReturnPolicy',
		'applicableCountry'    => 'KE',
		'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'   => 7,
		'returnMethod'         => 'https://schema.org/ReturnByMail',
		'returnFees'           => 'https://schema.org/ReturnShippingFees',
	);
	return $offer;
}

/** Enrich a Product schema array (single Offer, list of Offers or AggregateOffer). */
function nura_seo_enrich_product( $data ) {
	if ( !is_array( $data ) ) {
		return $data;
	}
	if ( empty( $data['brand'] ) ) {
		$data['brand'] = array( '@type' => 'Brand', 'name' => 'NURA' );
	}
	if ( isset( $data['offers'] ) && is_array( $data['offers'] ) ) {
		if ( isset( $data['offers']['@type'] ) || isset( $data['offers']['price'] ) || isset( $data['offers']['lowPrice'] ) ) {
			$data['offers'] = nura_seo_enrich_offer( $data['offers'] );
		} else {
			foreach ( $data['offers'] as $k => $o ) {
				$data['offers'][ $k ] = nura_seo_enrich_offer( $o );
			}
		}
	}
	return $data;
}
add_filter( 'woocommerce_structured_data_product', 'nura_seo_enrich_product', 20 );
add_filter( 'rank_math/snippet/rich_snippet_product_entity', 'nura_seo_enrich_product', 20 );

/** Descriptive alt text on product images that have none. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( '' !== trim( (string) ( isset( $attr['alt'] ) ? $attr['alt'] : '' ) ) ) {
		return $attr;
	}
	$parent = ( $attachment && isset( $attachment->post_parent ) ) ? (int) $attachment->post_parent : 0;
	$pid    = 0;
	if ( $parent && 'product' === get_post_type( $parent ) ) {
		$pid = $parent;
	} elseif ( 'product' === get_post_type() ) {
		$pid = (int) get_the_ID();
	}
	if ( $pid ) {
		/* translators: %s: product name */
		$attr['alt'] = sprintf( __( '%s - wig in Kenya | NURA Beauty', 'nura-beauty' ), get_the_title( $pid ) );
	}
	return $attr;
}, 20, 2 );
