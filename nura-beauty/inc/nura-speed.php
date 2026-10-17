<?php
/**
 * NURA Beauty v1.39.0 - front-end speed.
 *
 * Safe, reversible trims aimed at a 1-3 second load on Kenyan mobile data:
 * - Homepage: preload the first hero image at high priority (it is the largest paint).
 * - Load Contact Form 7 files only on pages that actually contain a CF7 form.
 * - Defer non-critical footer scripts (order pop-up, WooCommerce attribution).
 * - Drop WordPress emoji scripts/styles and the oEmbed script for visitors.
 * - Drop Dashicons and the block-library CSS for logged-out visitors on shop pages
 *   that never use blocks (the theme styles everything itself).
 * Every trim can be switched off with: add_filter( 'nura_speed_enabled', '__return_false' );
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() ) {
	return;
}

function nura_speed_on() {
	return (bool) apply_filters( 'nura_speed_enabled', true );
}

/* 1. Preload the first hero slide on the homepage. */
add_action( 'wp_head', function () {
	if ( ! nura_speed_on() || ! is_front_page() || ! defined( 'NURA_URI' ) ) {
		return;
	}
	echo '<link rel="preload" as="image" href="' . esc_url( NURA_URI . 'assets/images/hero.webp' ) . '" fetchpriority="high">' . "\n";
}, 1 );

/* 2. Contact Form 7 only where a CF7 form is used. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || ! defined( 'WPCF7_VERSION' ) ) {
		return;
	}
	$needs = false;
	if ( is_singular() ) {
		$post  = get_post();
		$needs = $post && ( has_shortcode( (string) $post->post_content, 'contact-form-7' ) || false !== strpos( (string) $post->post_content, 'wp:contact-form-7' ) );
	}
	if ( ! apply_filters( 'nura_load_cf7', $needs ) ) {
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'swv' );
		wp_dequeue_style( 'contact-form-7' );
	}
}, 100 );

/* 3. Defer non-critical scripts. */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( ! nura_speed_on() ) {
		return $tag;
	}
	$defer = (array) apply_filters( 'nura_defer_handles', array( 'nura-main', 'nurax-wa-order', 'sourcebuster-js', 'wc-order-attribution' ) );
	if ( in_array( $handle, $defer, true ) && false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' async' ) ) {
		$tag = str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}, 20, 2 );

/* 4. Emoji + oEmbed scripts are not used by the theme. */
add_action( 'init', function () {
	if ( ! nura_speed_on() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	add_filter( 'emoji_svg_url', '__return_false' );
} );

/* 5. Dashicons / block CSS for logged-out visitors on WooCommerce pages and the homepage. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || is_user_logged_in() ) {
		return;
	}
	wp_dequeue_style( 'dashicons' );
	$wc_page = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() );
	if ( is_front_page() || $wc_page ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'wc-blocks-style' );
	}
}, 100 );

/* ---------------------------------------------------------------------------
 * v1.46.0 - image loading and cart request
 * Live audit: 67 images on the homepage but only 1 lazy-loaded, product cards
 * asking for 600px-wide files at "100vw" on phones, and an extra uncached
 * cart AJAX call on every page view.
 * ------------------------------------------------------------------------- */

/* 5. Product images: lazy after the first two, and the right size for the slot. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment, $size ) {
	static $seen = 0;
	if ( ! nura_speed_on() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $attr;
	}
	$class   = isset( $attr['class'] ) ? (string) $attr['class'] : '';
	$is_card = false !== strpos( $class, 'attachment-woocommerce' ) || false !== strpos( $class, 'attachment-nura_card' ) || in_array( $size, array( 'woocommerce_thumbnail', 'woocommerce_single', 'nura_card' ), true );
	if ( ! $is_card ) {
		return $attr;
	}
	// The main gallery image on a product page stays as it is (it is the largest paint).
	$in_loop = function_exists( 'wc_get_loop_prop' ) && '' !== (string) wc_get_loop_prop( 'name' );
	if ( function_exists( 'is_product' ) && is_product() && ! $in_loop && ! did_action( 'woocommerce_after_single_product_summary' ) ) {
		return $attr;
	}
	$seen++;
	if ( $seen > 2 ) {
		$attr['loading']       = 'lazy';
		$attr['fetchpriority'] = 'low';
	}
	$attr['decoding'] = 'async';
	if ( ! empty( $attr['srcset'] ) ) {
		// Cards are 2 per row on phones, 3 on tablets, 4-5 on desktop.
		$attr['sizes'] = '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 300px';
		// 1.69.1: drop candidates wider than 800px so a phone never downloads the 600 KB original.
		$keep = array();
		foreach ( explode( ',', (string) $attr['srcset'] ) as $cand ) {
			if ( preg_match( '/\s(\d+)w\s*$/', trim( $cand ), $m ) && (int) $m[1] > 800 ) {
				continue;
			}
			$keep[] = trim( $cand );
		}
		if ( $keep ) {
			$attr['srcset'] = implode( ', ', $keep );
		}
	}
	return $attr;
}, 30, 3 );

/* 6. Skip WooCommerce's cart-refresh request when the visitor's cart is empty. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	if ( is_cart() || is_checkout() || ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( WC()->cart->is_empty() && apply_filters( 'nura_skip_cart_fragments', true ) ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}, 110 );


/* ---------------------------------------------------------------------------
 * v1.55.6 - logo and script weight
 * Live audit: the header logo was a 2548x808 PNG (1.7 MB) loaded on every page
 * at high priority, though it shows at 56px tall. Serve a 400px WebP (33 KB).
 * ------------------------------------------------------------------------- */

/* 7. Light header logo. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! nura_speed_on() || ! defined( 'NURA_URI' ) || ! defined( 'NURA_DIR' ) ) {
		return $attr;
	}
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $logo_id || ! $attachment || (int) $attachment->ID !== $logo_id || ! file_exists( NURA_DIR . 'assets/images/nura-logo.webp' ) ) {
		return $attr;
	}
	if ( ! apply_filters( 'nura_light_logo', true ) ) {
		return $attr;
	}
	$attr['src'] = NURA_URI . 'assets/images/nura-logo.webp';
	unset( $attr['srcset'], $attr['sizes'] );
	$attr['decoding'] = 'async';
	return $attr;
}, 40, 2 );

add_filter( 'get_custom_logo', function ( $html ) {
	if ( ! nura_speed_on() || false === strpos( $html, 'nura-logo.webp' ) ) {
		return $html;
	}
	return preg_replace( array( '/\swidth="\d+"/', '/\sheight="\d+"/' ), array( ' width="400"', ' height="127"' ), $html, 1 );
} );

/* 8. Defer the NURA Experience scripts too (they only run after the page is ready). */
add_filter( 'nura_defer_handles', function ( $handles ) {
	return array_merge( (array) $handles, array( 'nurax', 'nurax-shop', 'nurax-variations' ) );
} );


/* ---------------------------------------------------------------------------
 * v1.69.0 - PageSpeed (mobile 69): card images, render-blocking CSS, scripts
 * ------------------------------------------------------------------------- */

/* 9. A right-sized product-card image (440px wide, same shape as the original). */
add_action( 'after_setup_theme', function () {
	add_image_size( 'nura_card', 440, 0, false );
} );

/* 9b. Older uploads: create the card size the first time it is needed (max 8 per page view). */
add_filter( 'image_downsize', function ( $out, $id, $size ) {
	static $made = 0;
	if ( 'nura_card' !== $size || $made >= 8 || ! nura_speed_on() ) {
		return $out;
	}
	$meta = wp_get_attachment_metadata( $id );
	if ( ! is_array( $meta ) || isset( $meta['sizes']['nura_card'] ) || empty( $meta['width'] ) || (int) $meta['width'] <= 440 ) {
		return $out;
	}
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		return $out;
	}
	$ed = wp_get_image_editor( $file );
	if ( is_wp_error( $ed ) ) {
		return $out;
	}
	$ed->set_quality( 78 );
	if ( is_wp_error( $ed->resize( 440, null, false ) ) ) {
		return $out;
	}
	$saved = $ed->save( $ed->generate_filename( 'card' ) );
	if ( is_wp_error( $saved ) || empty( $saved['file'] ) ) {
		return $out;
	}
	$made++;
	$meta['sizes']['nura_card'] = array(
		'file'      => wp_basename( $saved['file'] ),
		'width'     => (int) $saved['width'],
		'height'    => (int) $saved['height'],
		'mime-type' => $saved['mime-type'],
	);
	wp_update_attachment_metadata( $id, $meta );
	return $out; // WordPress now finds the new size itself.
}, 10, 3 );

/* 10. Stylesheets not needed for the first screen load without blocking it. */
add_filter( 'style_loader_tag', function ( $tag, $handle ) {
	if ( ! nura_speed_on() || is_admin() ) {
		return $tag;
	}
	$async = (array) apply_filters( 'nura_async_styles', array( 'nura-fonts', 'nurax-wa-order', 'nurax-sub' ) );
	if ( ! in_array( $handle, $async, true ) || false !== strpos( $tag, 'onload=' ) ) {
		return $tag;
	}
	$swap = preg_replace( "/media=(['\"])[^'\"]*\\1/", "media='print' onload=\"this.media='all';this.onload=null\"", $tag, 1, $n );
	if ( ! $n ) {
		$swap = str_replace( ' href=', " media='print' onload=\"this.media='all';this.onload=null\" href=", $tag );
	}
	return $swap . '<noscript>' . trim( $tag ) . '</noscript>' . "\n";
}, 20, 2 );

/* 11. jQuery Migrate is only for very old plugins; not loaded for shoppers. */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( is_admin() || ! nura_speed_on() || ! apply_filters( 'nura_drop_jquery_migrate', true ) || empty( $scripts->registered['jquery'] ) ) {
		return;
	}
	$scripts->registered['jquery']->deps = array_diff( (array) $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
} );

/* 12. Defer the remaining small theme/plugin scripts (all wait for the page anyway). */
add_filter( 'nura_defer_handles', function ( $handles ) {
	return array_merge( (array) $handles, array( 'nura-boost', 'nura-growth', 'nura-menu', 'nurax-sub' ) );
} );


/* ---------------------------------------------------------------------------
 * v1.69.1 - mobile LCP / TBT
 * ------------------------------------------------------------------------- */

/* 13. Third-party tags added by other plugins (Site Kit, Google for WooCommerce, Meta)
 *     load after the first tap/scroll or 3.5 s. Their inline config still runs and
 *     queues into dataLayer / fbq, so nothing is lost. Off: add_filter( 'nura_delay_tags', '__return_false' ). */
function nura_delay_hosts() {
	return (array) apply_filters( 'nura_delay_tag_hosts', array( 'googletagmanager.com/gtag/js', 'googletagmanager.com/gtm.js', 'connect.facebook.net', 'analytics.tiktok.com' ) );
}
add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {
	if ( ! nura_speed_on() || is_admin() || ! apply_filters( 'nura_delay_tags', true ) || ! $src ) {
		return $tag;
	}
	foreach ( nura_delay_hosts() as $h ) {
		if ( false !== strpos( $src, $h ) ) {
			return '<script type="text/plain" data-nura-src="' . esc_url( $src ) . '"></script>' . "\n";
		}
	}
	return $tag;
}, 50, 3 );
add_action( 'wp_head', function () {
	if ( ! nura_speed_on() || ! apply_filters( 'nura_delay_tags', true ) ) {
		return;
	}
	echo "<script>(function(){var d=0;function go(){if(d)return;d=1;document.querySelectorAll('script[data-nura-src]').forEach(function(o){var s=document.createElement('script');s.async=1;s.src=o.getAttribute('data-nura-src');document.head.appendChild(s);});}['pointerdown','keydown','scroll','touchstart'].forEach(function(e){addEventListener(e,go,{once:true,passive:true});});addEventListener('load',function(){setTimeout(go,3500);});})();</script>\n";
}, 0 );

/* 14. WooCommerce/WordPress helper scripts out of the <head> (they were render-blocking). */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nura_speed_on() || is_admin() || ! apply_filters( 'nura_scripts_to_footer', true ) ) {
		return;
	}
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) {
		return; // Leave checkout, cart and account exactly as WooCommerce ships them.
	}
	global $wp_scripts;
	foreach ( array( 'jquery', 'jquery-core', 'jquery-blockui', 'js-cookie', 'underscore', 'wp-util', 'woocommerce', 'wp-hooks', 'wc-add-to-cart' ) as $h ) {
		if ( isset( $wp_scripts->registered[ $h ] ) ) {
			$wp_scripts->add_data( $h, 'group', 1 );
		}
	}
}, 999 );

/* 15. Small CSS files: print inline instead of separate blocking requests. */
add_filter( 'style_loader_tag', function ( $tag, $handle, $href ) {
	if ( ! nura_speed_on() || is_admin() || ! in_array( $handle, (array) apply_filters( 'nura_inline_styles', array( 'nura-child', 'woocommerce-smallscreen', 'woocommerce-layout', 'nurax' ) ), true ) ) {
		return $tag;
	}
	$path = '';
	$url  = strtok( (string) $href, '?' );
	foreach ( array( content_url() => WP_CONTENT_DIR, plugins_url() => WP_PLUGIN_DIR ) as $u => $dir ) {
		if ( 0 === strpos( $url, $u ) ) {
			$path = $dir . substr( $url, strlen( $u ) );
			break;
		}
	}
	if ( ! $path || ! is_readable( $path ) || filesize( $path ) > 15000 ) {
		return $tag;
	}
	$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$base = trailingslashit( dirname( $url ) );
	$css  = preg_replace_callback( '/url\(\s*([\'"]?)(?!data:|https?:|\/)([^\'")]+)\1\s*\)/i', function ( $m ) use ( $base ) {
		return 'url(' . $m[1] . $base . $m[2] . $m[1] . ')';
	}, $css );
	$media = preg_match( "/media=['\"]([^'\"]+)['\"]/", $tag, $mm ) ? $mm[1] : 'all';
	return '<style id="' . esc_attr( $handle ) . '-inline" media="' . esc_attr( $media ) . '">' . str_replace( '</style', '<\/style', $css ) . "</style>\n";
}, 30, 3 );

/* 16. Preload the font files used above the fold is left to the browser; fonts already swap. */
