<?php
/**
 * Social media touchpoints (v1.56.0).
 *
 * TikTok and Facebook appear where they help a shopper decide or come back:
 * - announcement bar (desktop, all pages) - added in template-tags.php;
 * - homepage "Real women. Real crowns." follow buttons - front-page.php;
 * - single product page, under the buy buttons: "See it styled";
 * - order thank-you page: "Share your crown";
 * - footer and contact page icons (existing, via nura_social_links()).
 * Profile URLs are edited in Appearance > Customize > NURA Options > Brand.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TikTok / Facebook / Instagram links only, in brand order.
 *
 * @return array[]
 */
function nura_social_primary() {
	if ( ! function_exists( 'nura_social_links' ) ) {
		return array();
	}
	$out = array();
	foreach ( nura_social_links() as $soc ) {
		if ( in_array( $soc['key'], array( 'tiktok', 'facebook', 'instagram' ), true ) ) {
			$out[] = $soc;
		}
	}
	return $out;
}

/**
 * Render a compact follow strip.
 *
 * @param string $title   Heading text.
 * @param string $text    Supporting line.
 * @param string $context CSS modifier.
 */
function nura_social_strip( $title, $text, $context = 'product' ) {
	$links = nura_social_primary();
	if ( ! $links ) {
		return;
	}
	echo '<div class="nura-social-strip nura-social-strip--' . esc_attr( $context ) . '">';
	echo '<div class="nura-social-strip__copy"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $text ) . '</span></div>';
	echo '<div class="nura-social-strip__links">';
	foreach ( $links as $soc ) {
		/* translators: %s: social network name. */
		echo '<a class="nura-social-strip__link nura-social-strip__link--' . esc_attr( $soc['key'] ) . '" href="' . esc_url( $soc['url'] ) . '" target="_blank" rel="noopener me" aria-label="' . esc_attr( sprintf( __( 'NURA on %s', 'nura-beauty' ), $soc['label'] ) ) . '">' . nura_social_icon( $soc['key'] ) . '<span>' . esc_html( $soc['label'] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped; icon is static SVG.
	}
	echo '</div></div>';
}

add_action( 'woocommerce_single_product_summary', function () {
	nura_social_strip(
		__( 'See it styled', 'nura-beauty' ),
		__( 'Watch this look on real clients at @nurabeautyke.', 'nura-beauty' ),
		'product'
	);
}, 45 );

add_action( 'woocommerce_thankyou', function () {
	nura_social_strip(
		__( 'Share your crown', 'nura-beauty' ),
		__( 'Follow @nurabeautyke and tag us when your wig arrives - we feature our clients every week.', 'nura-beauty' ),
		'thankyou'
	);
}, 20 );
