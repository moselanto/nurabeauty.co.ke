<?php
/**
 * NURA Gtag Once - load the Google tag library once when Site Kit already loads it (1.137.2).
 *
 * Google for WooCommerce (GLA) prints its own <script src="gtag/js?id=AW-..."> loader because it only
 * reuses a library added by "Google Analytics for WooCommerce", not Site Kit (GLA issue #4041; fix in
 * GLA PR #4153, unreleased). Site Kit already loads gtag.js (handle google_gtagjs), so the page
 * downloaded the library twice.
 *
 * This wraps GLA's wp_head output (priority 999999) in a small buffer. When Site Kit's google_gtagjs
 * has already printed on this page, it removes only GLA's loader <script src=...gtag/js?id=AW-...> and
 * its repeated gtag('js', new Date()) call. Everything else GLA prints stays: consent defaults,
 * developer ID, gtag('config', 'AW-...', {groups: 'GLA'}), enhanced-conversion user_data, and the
 * purchase / add-to-cart events (printed later). Those all push to the same dataLayer, which Site Kit's
 * library processes, so conversions still reach Google Ads.
 *
 * If Site Kit's tag did not print (not connected, tracking excluded for this visitor), nothing changes.
 * Off: add_filter( 'nurax_gtag_once', '__return_false' );
 * When GLA ships PR #4153 this becomes a no-op (GLA no longer prints its loader there).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Gtag_Once {

	private $buffering = false;

	public function __construct() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'start' ), 999998 );
		add_action( 'wp_head', array( $this, 'finish' ), 1000001 );
	}

	public function start() {
		if ( ! apply_filters( 'nurax_gtag_once', true ) ) {
			return;
		}
		$this->buffering = true;
		ob_start();
	}

	public function finish() {
		if ( ! $this->buffering ) {
			return;
		}
		$this->buffering = false;
		$html = (string) ob_get_clean();
		echo self::strip( $html, wp_script_is( 'google_gtagjs', 'done' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' own head markup, passed through.
	}

	/** Removes GLA's own AW loader (and its repeated gtag('js')) when Site Kit's library is on the page. */
	public static function strip( $html, $site_kit_printed ) {
		if ( ! $site_kit_printed || false === strpos( $html, 'Google for WooCommerce' ) ) {
			return $html;
		}
		$out = preg_replace( '#<script\b[^>]*\bsrc=["\']https://www\.googletagmanager\.com/gtag/js\?id=AW-[^"\']*["\'][^>]*>\s*</script>#i', '<!-- NURA: Google tag already loaded by Site Kit; Google for WooCommerce config below uses it -->', $html, 1, $n );
		if ( $n && is_string( $out ) ) {
			$out = preg_replace( '#gtag\(\s*[\'"]js[\'"]\s*,\s*new Date\(\)\s*\);#', '', $out, 1 );
		}
		return is_string( $out ) ? $out : $html;
	}
}

new NURAX_Gtag_Once();
