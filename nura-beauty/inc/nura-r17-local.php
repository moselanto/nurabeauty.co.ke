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

/* 2. Homepage section (1.161.22: richer card - live open/closed badge, services, WhatsApp + call). */
function nura_home_visit_section() {
	if ( ! function_exists( 'nura_fact' ) ) {
		return;
	}
	$book  = get_page_by_path( 'book-appointment' );
	$book  = ( $book && 'publish' === $book->post_status ) ? get_permalink( $book ) : '';
	$gbp   = nura_gbp_url();
	$dir   = (string) nura_fact( 'directions_url' );
	$wa    = function_exists( 'nura_opt' ) && nura_opt( 'nura_whatsapp' ) ? (string) nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
	$wa    = add_query_arg( 'text', rawurlencode( "Hi NURA, I'd like to visit the studio at Stanbank House. Is now a good time?" ), $wa );
	$phone = function_exists( 'nura_opt' ) && nura_opt( 'nura_phone' ) ? (string) nura_opt( 'nura_phone' ) : '+254 714 994 898';
	$tel   = preg_replace( '/[^0-9+]/', '', $phone );
	$plus  = (string) nura_fact( 'plus_code' );
	$opens = (string) nura_fact( 'opens', '09:00' );
	$close = (string) nura_fact( 'closes', '19:30' );
	$services = array(
		__( 'Try wigs on', 'nura-beauty' ),
		__( 'Wig fitting', 'nura-beauty' ),
		__( 'Installation', 'nura-beauty' ),
		__( 'Wig revamp', 'nura-beauty' ),
		__( 'Pick up online orders', 'nura-beauty' ),
	);
	?>
	<section class="section nura-visit" id="visit-studio" aria-labelledby="nura-visit-h">
		<div class="nura-container">
			<div class="nura-visit__card">
				<p class="nura-eyebrow"><?php esc_html_e( 'Wig shop in Nairobi CBD', 'nura-beauty' ); ?></p>
				<h2 id="nura-visit-h"><?php esc_html_e( 'Visit our Nairobi studio', 'nura-beauty' ); ?></h2>
				<p class="nura-visit__status" data-open="<?php echo esc_attr( $opens ); ?>" data-close="<?php echo esc_attr( $close ); ?>" aria-live="polite">
					<span class="nura-visit__dot" aria-hidden="true"></span>
					<span class="nura-visit__status-t"><?php echo esc_html( (string) nura_fact( 'hours_text' ) ); ?></span>
				</p>
				<p class="nura-visit__lede"><?php esc_html_e( 'See and try wigs in person, get fitted, and have your wig installed or revamped by our stylists. Walk-ins are welcome and appointments are available.', 'nura-beauty' ); ?></p>
				<ul class="nura-visit__chips" aria-label="<?php esc_attr_e( 'At the studio', 'nura-beauty' ); ?>">
					<?php foreach ( $services as $sv ) : ?><li><?php echo esc_html( $sv ); ?></li><?php endforeach; ?>
				</ul>
				<dl class="nura-visit__facts">
					<div><dt><?php esc_html_e( 'Address', 'nura-beauty' ); ?></dt><dd><?php echo esc_html( (string) nura_fact( 'address_full' ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Opening hours', 'nura-beauty' ); ?></dt><dd><?php echo esc_html( (string) nura_fact( 'hours_long' ) ); ?></dd></div>
					<?php if ( $plus ) : ?><div><dt><?php esc_html_e( 'Plus Code', 'nura-beauty' ); ?></dt><dd><?php echo esc_html( $plus ); ?> <span class="nura-visit__hint"><?php esc_html_e( '(type it into Google Maps for the exact door)', 'nura-beauty' ); ?></span></dd></div><?php endif; ?>
				</dl>
				<p class="nura-visit__cta">
					<?php if ( $dir ) : ?><a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $dir ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'nura-beauty' ); ?></a><?php endif; ?>
					<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Chat before you visit', 'nura-beauty' ); ?></a>
					<?php if ( $tel ) : ?><a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( sprintf( /* translators: phone */ __( 'Call %s', 'nura-beauty' ), $phone ) ); ?></a><?php endif; ?>
					<?php if ( $book ) : ?><a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $book ); ?>"><?php esc_html_e( 'Book a fitting', 'nura-beauty' ); ?></a><?php endif; ?>
				</p>
				<?php if ( $gbp ) : ?><p class="nura-visit__maps"><a href="<?php echo esc_url( $gbp ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See NURA Beauty on Google Maps', 'nura-beauty' ); ?></a></p><?php endif; ?>
			</div>
		</div>
	</section>
	<script>
	(function(){var el=document.querySelector('.nura-visit__status');if(!el||!window.Intl)return;
	try{var f=new Intl.DateTimeFormat('en-GB',{timeZone:'Africa/Nairobi',weekday:'short',hour:'2-digit',minute:'2-digit',hour12:false});
	var p={};f.formatToParts(new Date()).forEach(function(x){p[x.type]=x.value;});
	var m=function(t){var a=t.split(':');return (+a[0])*60+(+a[1]);};var now=m(p.hour+':'+p.minute)%1440;
	var o=m(el.getAttribute('data-open')),c=m(el.getAttribute('data-close'));
	var fmt=function(t){var a=t.split(':'),h=+a[0],mi=a[1],s=h>=12?'pm':'am';h=h%12||12;return h+(mi!=='00'?':'+mi:'')+s;};
	var sun=p.weekday==='Sun',sat=p.weekday==='Sat',t=el.querySelector('.nura-visit__status-t'),open=!sun&&now>=o&&now<c,msg;
	if(open){msg='Open now \u00b7 closes '+fmt(el.getAttribute('data-close'));}
	else if(!sun&&now<o){msg='Closed now \u00b7 opens today at '+fmt(el.getAttribute('data-open'));}
	else if(sat||sun){msg='Closed now \u00b7 opens Monday at '+fmt(el.getAttribute('data-open'));}
	else{msg='Closed now \u00b7 opens tomorrow at '+fmt(el.getAttribute('data-open'));}
	t.textContent=msg;el.classList.add(open?'is-open':'is-closed');}catch(e){}})();
	</script>
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
		echo '.nura-visit__card{max-width:880px;margin:0 auto;padding:clamp(22px,4vw,44px);border:1px solid rgba(201,162,75,.45);border-radius:18px;background:linear-gradient(180deg,#fbf7ef 0%,#f6efe2 100%);box-shadow:0 10px 30px rgba(122,90,18,.08);text-align:center}'
			. '.nura-visit h2{margin:.2em 0 .35em}.nura-visit__lede{max-width:640px;margin:0 auto 1em;line-height:1.6}'
			. '.nura-visit__status{display:inline-flex;align-items:center;gap:8px;margin:0 0 1em;padding:6px 14px;border-radius:999px;background:#fff;border:1px solid rgba(201,162,75,.4);font-size:.9rem;font-weight:600}'
			. '.nura-visit__dot{width:9px;height:9px;border-radius:50%;background:#c9a24b}.nura-visit__status.is-open .nura-visit__dot{background:#1f9d55;box-shadow:0 0 0 4px rgba(31,157,85,.15)}.nura-visit__status.is-open{color:#17663a}.nura-visit__status.is-closed .nura-visit__dot{background:#b54848}'
			. '.nura-visit__chips{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;list-style:none;margin:0 auto 1.4em;padding:0;max-width:660px}'
			. '.nura-visit__chips li{margin:0;padding:6px 14px;border-radius:999px;background:#fff;border:1px solid rgba(201,162,75,.35);font-size:.85rem;color:#5a4410}'
			. '.nura-visit__facts{display:grid;gap:0;margin:0 auto 1.4em;max-width:640px;text-align:left;background:#fff;border-radius:12px;padding:4px 18px;border:1px solid rgba(0,0,0,.05)}'
			. '.nura-visit__facts div{display:grid;grid-template-columns:8.5em 1fr;gap:10px;padding:12px 0;border-top:1px solid rgba(0,0,0,.07)}.nura-visit__facts div:first-child{border-top:0}'
			. '.nura-visit__facts dt{font-weight:600;color:#7a5a12}.nura-visit__facts dd{margin:0}.nura-visit__hint{color:#777;font-size:.88em}'
			. '.nura-visit__cta{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:0}'
			. '.nura-visit__maps{margin:1em 0 0;font-size:.9rem}.nura-visit__maps a{color:#7a5a12;text-decoration:underline;text-underline-offset:3px}'
			. '@media(max-width:560px){.nura-visit__facts div{grid-template-columns:1fr;gap:2px}.nura-visit__cta .nura-btn{flex:1 1 100%;text-align:center}}';
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
