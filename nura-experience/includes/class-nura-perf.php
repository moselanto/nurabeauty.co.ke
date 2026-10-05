<?php
/**
 * NURA performance - safe front-end speed wins.
 *
 * Conservative optimisations that will not break the theme or WooCommerce:
 *   - Defer NURA's own scripts (they run on DOMContentLoaded, so deferring is
 *     safe and removes render-blocking requests).
 *   - Drop the WordPress emoji script/styles and, for logged-out visitors,
 *     dashicons - dead weight on a storefront.
 *   - Add preconnect / dns-prefetch resource hints for the font and tag
 *     domains the site actually uses.
 *   - Mark attachment images decoding=async (WordPress already lazy-loads).
 *
 * The biggest wins (full-page cache, image CDN/compression, CSS/JS minify,
 * HTTP/2, PHP 8.x, object cache) are host-level and are documented in
 * docs/nura-performance-security.md.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Perf {

	public function __construct() {
		add_filter( 'script_loader_tag', array( $this, 'defer_own' ), 10, 3 );
		add_action( 'init', array( $this, 'kill_emojis' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'trim_assets' ), 100 );
		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'img_async' ) );
	}

	/** Defer our own scripts (safe: they already wait for DOMContentLoaded). */
	public function defer_own( $tag, $handle, $src ) {
		$ours = apply_filters( 'nurax_defer_handles', array( 'nurax', 'nurax-shop' ) );
		if ( in_array( $handle, (array) $ours, true ) && false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' async' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}
		return $tag;
	}

	/** Remove the emoji detection script and related styles. */
	public function kill_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/** Dequeue dashicons for logged-out visitors (only needed in wp-admin/toolbar). */
	public function trim_assets() {
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	}

	/** Preconnect / dns-prefetch the domains the site really uses. */
	public function resource_hints( $hints, $relation ) {
		if ( 'preconnect' === $relation ) {
			$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
			$hints[] = 'https://fonts.googleapis.com';
		}
		if ( 'dns-prefetch' === $relation ) {
			$hints[] = 'https://www.googletagmanager.com';
			$hints[] = 'https://connect.facebook.net';
		}
		return $hints;
	}

	/** Async-decode attachment images (WordPress already adds loading="lazy"). */
	public function img_async( $attr ) {
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}
}
