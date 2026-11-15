<?php
/**
 * 1.161.17 Local search: studio as a physical wig shop, "Visit our Nairobi studio"
 * homepage section, and a live Kenya wig price guide on the Wigs category.
 *
 * Hours, address and map come from nura_facts() (owner-confirmed 1 Nov 2026:
 * Mon-Sat 9:00-19:30, Stanbank House, 2nd Floor, Room 20).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Google Business Profile listing URL (from the confirmed place ID). */
function nura_gbp_url() {
	$pid = function_exists( 'nura_fact' ) ? (string) nura_fact( 'place_id' ) : '';
	return $pid ? 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode( $pid ) : '';
}

/* 1. Studio schema: a physical store with its Google Business Profile. */
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || empty( $data['nuraStudio'] ) || ! is_array( $data['nuraStudio'] ) ) {
		return $data;
	}
	$s          = $data['nuraStudio'];
	$s['@type'] = array( 'HealthAndBeautyBusiness', 'Store' );
	$gbp        = nura_gbp_url();
	if ( $gbp ) {
		$s['sameAs'] = array_values( array_unique( array_merge( isset( $s['sameAs'] ) ? (array) $s['sameAs'] : array(), array( $gbp ) ) ) );
		if ( empty( $s['hasMap'] ) ) {
			$s['hasMap'] = $gbp;
		}
	}
	if ( empty( $s['openingHoursSpecification'] ) && function_exists( 'nura_fact_hours_spec' ) ) {
		$s['openingHoursSpecification'] = nura_fact_hours_spec();
	}
	if ( empty( $s['geo'] ) && function_exists( 'nura_fact_geo' ) && nura_fact_geo() ) {
		$s['geo'] = nura_fact_geo();
	}
	$s['keywords']      = 'wigs in Kenya, wig shop Nairobi, human hair wigs Nairobi, wig installation Nairobi CBD';
	$data['nuraStudio'] = $s;
	return $data;
}, 999 );

/* 2. Homepage section. */
function nura_home_visit_section() {
	if ( ! function_exists( 'nura_fact' ) ) {
		return;
	}
	$book  = get_page_by_path( 'book-appointment' );
	$book  = ( $book && 'publish' === $book->post_status ) ? get_permalink( $book ) : '';
	$gbp   = nura_gbp_url();
	$dir   = (string) nura_fact( 'directions_url' );
	?>
	<section class="section nura-visit" id="visit-studio" aria-labelledby="nura-visit-h">
		<div class="nura-container">
			<div class="nura-visit__card">
				<p class="nura-eyebrow"><?php esc_html_e( 'Wig shop in Nairobi CBD', 'nura-beauty' ); ?></p>
				<h2 id="nura-visit-h"><?php esc_html_e( 'Visit our Nairobi studio', 'nura-beauty' ); ?></h2>
				<p class="nura-visit__lede"><?php esc_html_e( 'See and try wigs in person, get fitted, and have your wig installed or revamped by our stylists. Walk-ins are welcome and appointments are available.', 'nura-beauty' ); ?></p>
				<dl class="nura-visit__facts">
					<div><dt><?php esc_html_e( 'Address', 'nura-beauty' ); ?></dt><dd><?php echo esc_html( (string) nura_fact( 'address_full' ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Opening hours', 'nura-beauty' ); ?></dt><dd><?php echo esc_html( (string) nura_fact( 'hours_long' ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Landmark', 'nura-beauty' ); ?></dt><dd><?php esc_html_e( 'Between Moi Avenue and Tom Mboya Street (Plus Code PR8G+554)', 'nura-beauty' ); ?></dd></div>
				</dl>
				<p class="nura-visit__cta">
					<?php if ( $dir ) : ?><a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $dir ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'nura-beauty' ); ?></a><?php endif; ?>
					<?php if ( $gbp ) : ?><a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $gbp ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See us on Google Maps', 'nura-beauty' ); ?></a><?php endif; ?>
					<?php if ( $book ) : ?><a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $book ); ?>"><?php esc_html_e( 'Book a fitting', 'nura-beauty' ); ?></a><?php endif; ?>
				</p>
			</div>
		</div>
	</section>
	<?php
}

/* 3. Wigs category: live price guide, title, description. */
function nura_wig_price_tiers() {
	if ( ! function_exists( 'nura_boost_cat_prices' ) ) {
		return array();
	}
	$defs = array(
		array( __( 'Luxury fibre (synthetic) wigs', 'nura-beauty' ), array( 'high-fibre-wigs' ), __( 'Lowest cost; style holds out of the box; not for heat styling.', 'nura-beauty' ) ),
		array( __( 'Semi Human (human hair blend) wigs', 'nura-beauty' ), array( 'human-hair-blend-wigs', 'human-blend-wigs' ), __( 'More natural look and feel than fibre at a mid-range price.', 'nura-beauty' ) ),
		array( __( '100% human hair wigs', 'nura-beauty' ), array( 'human-hair-wigs' ), __( 'Most natural; can be heat-styled and coloured; lasts a year or more with care.', 'nura-beauty' ) ),
	);
	$out = array();
	foreach ( $defs as $d ) {
		$lo = 0;
		$hi = 0;
		$n  = 0;
		foreach ( $d[1] as $slug ) {
			$t = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $t ) {
				continue;
			}
			$px = nura_boost_cat_prices( $t );
			if ( ! $px ) {
				continue;
			}
			$lo = $lo ? min( $lo, $px['lo'] ) : $px['lo'];
			$hi = max( $hi, $px['hi'] );
			$n += (int) $px['count'];
		}
		if ( $lo ) {
			$out[] = array( 'name' => $d[0], 'lo' => $lo, 'hi' => $hi, 'n' => $n, 'note' => $d[2] );
		}
	}
	return $out;
}

function nura_is_wigs_cat() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category( 'wigs' ) ) {
		return false;
	}
	return absint( get_query_var( 'paged' ) ) < 2;
}

add_action( 'woocommerce_after_shop_loop', function () {
	if ( ! nura_is_wigs_cat() ) {
		return;
	}
	$tiers = nura_wig_price_tiers();
	if ( ! $tiers ) {
		return;
	}
	$ksh = function ( $n ) {
		return function_exists( 'nura_boost_ksh' ) ? nura_boost_ksh( $n ) : 'KSh ' . number_format( (float) $n );
	};
	echo '<section class="nura-priceguide" aria-labelledby="nura-pg-h"><h2 id="nura-pg-h">' . esc_html__( 'Wig prices in Kenya: a quick guide', 'nura-beauty' ) . '</h2>';
	echo '<p>' . esc_html__( 'What wigs cost in Kenya depends mostly on the hair type. These are the live price ranges for wigs in stock at NURA today.', 'nura-beauty' ) . '</p>';
	echo '<table class="nura-priceguide__t"><thead><tr><th scope="col">' . esc_html__( 'Hair type', 'nura-beauty' ) . '</th><th scope="col">' . esc_html__( 'Price range', 'nura-beauty' ) . '</th></tr></thead><tbody>';
	foreach ( $tiers as $t ) {
		$range = ( (int) $t['lo'] === (int) $t['hi'] ) ? $ksh( $t['lo'] ) : $ksh( $t['lo'] ) . ' - ' . $ksh( $t['hi'] );
		echo '<tr><th scope="row">' . esc_html( $t['name'] ) . '<span>' . esc_html( $t['note'] ) . '</span></th><td>' . esc_html( $range ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="nura-priceguide__more">' . esc_html__( 'Longer lengths, higher density and full lace cost more. Wig installation and revamps are done at our Nairobi CBD studio, and you can pay by M-Pesa or card.', 'nura-beauty' ) . '</p></section>';
}, 5 );

add_filter( 'rank_math/frontend/title', function ( $title ) {
	if ( ! nura_is_wigs_cat() || ( function_exists( 'nura_seo_has_custom_meta' ) && nura_seo_has_custom_meta( 'title' ) ) ) {
		return $title;
	}
	$t  = get_queried_object();
	$px = ( $t && function_exists( 'nura_boost_cat_prices' ) ) ? nura_boost_cat_prices( $t ) : array();
	$lo = $px ? ( function_exists( 'nura_boost_ksh' ) ? nura_boost_ksh( $px['lo'] ) : 'KSh ' . number_format( $px['lo'] ) ) : '';
	return $lo ? sprintf( __( 'Wigs in Kenya: Prices from %s | Human Hair & Lace Wigs | NURA', 'nura-beauty' ), $lo ) : __( 'Wigs in Kenya: Human Hair & Lace Wigs | NURA Beauty Nairobi', 'nura-beauty' );
}, 40 );

add_filter( 'rank_math/frontend/description', function ( $desc ) {
	if ( ! nura_is_wigs_cat() || ( function_exists( 'nura_seo_has_custom_meta' ) && nura_seo_has_custom_meta( 'description' ) ) ) {
		return $desc;
	}
	$tiers = nura_wig_price_tiers();
	$ksh   = function ( $n ) {
		return function_exists( 'nura_boost_ksh' ) ? nura_boost_ksh( $n ) : 'KSh ' . number_format( (float) $n );
	};
	$parts = array();
	foreach ( $tiers as $t ) {
		$short   = preg_replace( '/ wigs$/', '', $t['name'] );
		$short   = preg_replace( '/ \(.*\)/', '', $short );
		$parts[] = $short . ' from ' . $ksh( $t['lo'] );
	}
	$d = 'Wigs in Kenya at NURA, a Nairobi CBD wig shop: ' . ( $parts ? implode( ', ', $parts ) . '. ' : '' ) . 'Same-day Nairobi delivery, countrywide shipping, M-Pesa or card.';
	return $d;
}, 40 );

add_action( 'wp_head', function () {
	$home = is_front_page();
	$wigs = nura_is_wigs_cat();
	if ( ! $home && ! $wigs ) {
		return;
	}
	echo '<style id="nura-r17-css">';
	if ( $home ) {
		echo '.nura-visit__card{max-width:880px;margin:0 auto;padding:clamp(22px,4vw,40px);border:1px solid rgba(201,162,75,.45);border-radius:16px;background:#faf6f0;text-align:center}'
			. '.nura-visit h2{margin:.2em 0 .4em}.nura-visit__lede{max-width:640px;margin:0 auto 1.2em;line-height:1.6}'
			. '.nura-visit__facts{display:grid;gap:12px;margin:0 auto 1.4em;max-width:640px;text-align:left}'
			. '.nura-visit__facts div{display:grid;grid-template-columns:8.5em 1fr;gap:10px;padding:10px 0;border-top:1px solid rgba(0,0,0,.08)}'
			. '.nura-visit__facts dt{font-weight:600;color:#7a5a12}.nura-visit__facts dd{margin:0}'
			. '.nura-visit__cta{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:0}'
			. '@media(max-width:560px){.nura-visit__facts div{grid-template-columns:1fr;gap:2px}}';
	}
	if ( $wigs ) {
		echo '.nura-priceguide{max-width:880px;margin:2.2rem auto 1rem;padding:clamp(18px,3vw,30px);border:1px solid #eadfc6;border-radius:14px;background:#fffdf8}'
			. '.nura-priceguide h2{font-size:1.35rem;margin:0 0 .5em}.nura-priceguide p{margin:0 0 1em;line-height:1.6}'
			. '.nura-priceguide__t{width:100%;border-collapse:collapse;margin:0 0 1em}'
			. '.nura-priceguide__t th,.nura-priceguide__t td{padding:12px 8px;border-top:1px solid #f1e8d6;text-align:left;vertical-align:top}'
			. '.nura-priceguide__t thead th{border-top:0;font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;color:#7a5a12}'
			. '.nura-priceguide__t tbody th{font-weight:600}.nura-priceguide__t tbody th span{display:block;font-weight:400;font-size:.88em;color:#555;margin-top:3px}'
			. '.nura-priceguide__t td{white-space:nowrap;font-weight:600}';
	}
	echo '</style>';
}, 35 );
