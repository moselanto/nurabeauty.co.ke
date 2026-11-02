<?php
/**
 * NURA Beauty brand identity (theme 1.82.0).
 *
 * Applies the owner's official logo kit (NURA Beauty Logo Kit v2: Compact logo,
 * Full colour: Nura Gold #C9972C, Deep Bronze #3A2208) across the site:
 * header and menu drawer logo, browser tab / phone home-screen icons (emblem),
 * structured-data logo (Google), and a social share image. Files live in
 * assets/images and are served from the theme, so no Media Library upload or
 * Customizer step is needed and the logo survives theme updates.
 *
 * Turn the theme logo off (use the Customizer logo again):
 * add_filter( 'nura_brand_logo', '__return_false' );
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Logo files (name => width x height). */
function nura_brand_files() {
	return array(
		'logo'    => array( 'nura-logo.webp', 640, 276 ),
		'large'   => array( 'nura-logo-large.png', 1200, 518 ),
		'png'     => array( 'nura-logo.png', 640, 276 ),
		'svg'     => array( 'nura-logo.svg', 567, 245 ),
		'emblem'  => array( 'nura-monogram.png', 512, 512 ),
		'og'      => array( 'nura-og.jpg', 1200, 630 ),
	);
}

function nura_brand_url( $key ) {
	$f = nura_brand_files();
	return isset( $f[ $key ] ) && defined( 'NURA_URI' ) ? NURA_URI . 'assets/images/' . $f[ $key ][0] : '';
}

/** Header logo markup: sharp, light (55 KB WebP), with PNG fallback for old browsers. */
function nura_brand_logo_html( $class = 'nura-brand-logo', $priority = true ) {
	$f   = nura_brand_files();
	$alt = esc_attr( (string) apply_filters( 'nura_brand_name', get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'NURA Beauty' ) );
	return '<a class="custom-logo-link ' . esc_attr( $class ) . '" href="' . esc_url( home_url( '/' ) ) . '" rel="home" aria-label="' . $alt . '"><picture><source type="image/webp" srcset="' . esc_url( nura_brand_url( 'logo' ) ) . '"><img class="custom-logo" src="' . esc_url( nura_brand_url( 'png' ) ) . '" alt="' . $alt . '" width="' . (int) $f['logo'][1] . '" height="' . (int) $f['logo'][2] . '" decoding="async"' . ( $priority ? ' fetchpriority="high"' : ' loading="lazy"' ) . '></picture></a>';
}

function nura_brand_on() {
	return (bool) apply_filters( 'nura_brand_logo', true );
}

/** Customizer-independent: the_custom_logo() and get_custom_logo() output the brand logo. */
add_filter( 'get_custom_logo', function ( $html ) {
	return nura_brand_on() ? nura_brand_logo_html() : $html;
}, 99 );

/** Favicon and home-screen icons from the emblem (replaces the default WordPress icon). */
add_filter( 'get_site_icon_url', function ( $url, $size ) {
	if ( ! nura_brand_on() || ! defined( 'NURA_URI' ) ) {
		return $url;
	}
	$d    = NURA_URI . 'assets/images/';
	$size = (int) $size;
	if ( $size > 0 && $size <= 32 ) {
		return $d . 'favicon-32.png';
	}
	if ( $size > 0 && $size <= 180 ) {
		return $d . 'apple-touch-icon.png';
	}
	if ( $size > 0 && $size <= 192 ) {
		return $d . 'favicon-192.png';
	}
	return $d . 'nura-monogram.png';
}, 99, 2 );

/** Structured data: the Organization logo is the official logo file (Google needs a crawlable image of at least 112 px). */
add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! nura_brand_on() ) {
		return $data;
	}
	$f = nura_brand_files();
	foreach ( $data as $k => &$node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( isset( $node['@id'] ) && is_string( $node['@id'] ) && '#logo' === substr( $node['@id'], -5 ) ) {
			$node['url']        = nura_brand_url( 'large' );
			$node['contentUrl'] = nura_brand_url( 'large' );
			$node['width']      = $f['large'][1];
			$node['height']     = $f['large'][2];
			$node['caption']    = 'NURA Beauty';
		}
		if ( isset( $node['logo'] ) && is_array( $node['logo'] ) && ( isset( $node['logo']['@type'] ) || isset( $node['logo']['url'] ) ) ) {
			$node['logo']['url']        = nura_brand_url( 'large' );
			$node['logo']['contentUrl'] = nura_brand_url( 'large' );
			$node['logo']['width']      = $f['large'][1];
			$node['logo']['height']     = $f['large'][2];
		}
		// 1.83.0: Organization / studio 'image' still pointed at the old uploaded logo.
		if ( isset( $node['image'] ) && is_string( $node['image'] ) && false !== stripos( $node['image'], 'logo' ) ) {
			$node['image'] = nura_brand_url( 'large' );
		} elseif ( isset( $node['image']['url'] ) && is_string( $node['image']['url'] ) && false !== stripos( $node['image']['url'], 'logo' ) ) {
			$node['image']['url'] = nura_brand_url( 'large' );
		}
	}
	unset( $node );
	return $data;
}, 1001 );

/** Social share image fallback (Facebook, WhatsApp, LinkedIn): logo on cream, 1200x630. */
add_filter( 'rank_math/opengraph/facebook/image', function ( $img ) {
	return ( nura_brand_on() && empty( $img ) ) ? nura_brand_url( 'og' ) : $img;
}, 20 );
add_filter( 'rank_math/opengraph/twitter/image', function ( $img ) {
	return ( nura_brand_on() && empty( $img ) ) ? nura_brand_url( 'og' ) : $img;
}, 20 );

/** Sizing: clean, balanced, never stretched. Wide lockup, so height-driven.
 * 1.82.1: when logged in, the WordPress admin bar (fixed, 32px / 46px) covered the top of the
 * sticky header while scrolling; the header now sticks just below it. Visitors are unaffected.
 * 1.82.2: homepage category tiles were short landscape boxes centred on the photo, cutting off
 * the top of each wig; they are now taller and anchored near the top so the whole wig shows. */
add_action( 'wp_head', function () {
	if ( ! nura_brand_on() ) {
		return;
	}
	echo '<style id="nura-brand-css">html body .nura-logo-wrap .custom-logo-link{display:inline-flex;align-items:center;line-height:0}html body .nura-logo-wrap img,html body .nura-logo-wrap .custom-logo{display:block;height:auto;width:auto;max-height:58px;max-width:min(150px,42vw);object-fit:contain}.nura-drawer__logo img{max-height:54px;width:auto}.login h1 a{background-image:url(' . esc_url( nura_brand_url( 'png' ) ) . ')!important;background-size:contain!important;width:240px!important;height:104px!important}html body .nura-tile.has-img{background-position:center 15%}html body .nura-pillars-section .nura-tile.has-img{min-height:clamp(300px,27vw,400px)}@media(max-width:900px){html body .nura-pillars-section .nura-tile.has-img{min-height:clamp(260px,42vw,340px)}}@media(max-width:560px){html body .nura-pillars-section .nura-tile.has-img{min-height:clamp(220px,62vw,300px)}}html body.admin-bar .site-header{top:32px}@media screen and (max-width:782px){html body.admin-bar .site-header{top:46px}}@media screen and (max-width:600px){html body.admin-bar .site-header{top:0}}@media(max-width:900px){html body .nura-logo-wrap img,html body .nura-logo-wrap .custom-logo{max-height:46px;max-width:min(128px,40vw)}}@media(max-width:520px){html body .nura-logo-wrap img,html body .nura-logo-wrap .custom-logo{max-height:40px;max-width:min(112px,38vw)}}</style>' . "\n";
	echo '<link rel="preload" as="image" type="image/webp" href="' . esc_url( nura_brand_url( 'logo' ) ) . '" fetchpriority="high">' . "\n";
}, 3 );
