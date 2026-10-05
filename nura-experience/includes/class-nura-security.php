<?php
/**
 * NURA security hardening - safe, reversible defaults.
 *
 * Conservative front-end hardening that does not risk the live site:
 *   - Security response headers (nosniff, SAMEORIGIN, Referrer-Policy, a
 *     Permissions-Policy that still ALLOWS same-origin camera for Try-On).
 *   - XML-RPC disabled and the pingback header removed (common attack + spam
 *     vector) - re-enable with the nurax_disable_xmlrpc filter if you use the
 *     WP mobile app or Jetpack.
 *   - The WordPress generator version is hidden.
 *   - User enumeration via ?author=N is blocked, and the REST users endpoint is
 *     closed to anonymous requests (both are reconnaissance vectors).
 *   - A light rate limiter on our own public REST endpoints (nurax/v1 POST)
 *     to blunt spam/abuse of the finder, stylist and forms.
 *
 * Heavier, host-level protection (WAF, a security plugin, HSTS, a strict CSP,
 * login 2FA) is documented in docs/nura-performance-security.md.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Security {

	public function __construct() {
		add_action( 'send_headers', array( $this, 'headers' ) );

		if ( apply_filters( 'nurax_disable_xmlrpc', true ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'strip_pingback' ) );
			add_filter( 'xmlrpc_methods', array( $this, 'strip_pingback_methods' ) );
		}

		// Hide the WP version.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		// Block user enumeration and close the REST users endpoint to guests.
		add_action( 'template_redirect', array( $this, 'block_author_scan' ) );
		add_filter( 'rest_endpoints', array( $this, 'close_user_endpoints' ) );

		// Light rate limit on our own public REST namespace.
		add_filter( 'rest_pre_dispatch', array( $this, 'rate_limit' ), 10, 3 );
	}

	public function headers() {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		// The NURA Beauty theme ships a more complete security-header set (it
		// also sends COOP + HSTS). When that theme is active, defer to it so the
		// response never carries two conflicting Permissions-Policy headers.
		if ( defined( 'NURA_VERSION' ) ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		// Camera is allowed for same-origin so the Virtual Try-On camera works.
		header( 'Permissions-Policy: camera=(self), microphone=(), geolocation=(self), interest-cohort=()' );
	}

	public function strip_pingback( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	public function strip_pingback_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	public function block_author_scan() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		// ?author=1 style enumeration on the front end.
		if ( isset( $_GET['author'] ) && '' !== $_GET['author'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = sanitize_text_field( wp_unslash( $_GET['author'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_numeric( $raw ) ) {
				wp_safe_redirect( home_url( '/' ), 301 );
				exit;
			}
		}
	}

	public function close_user_endpoints( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
			if ( isset( $endpoints[ $route ] ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Throttle POSTs to our own nurax/v1 endpoints per IP. Generous enough not
	 * to affect a real shopper, tight enough to stop scripted spam.
	 */
	public function rate_limit( $result, $server, $request ) {
		if ( ! is_null( $result ) ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/nurax/v1' ) ) {
			return $result;
		}
		if ( 'POST' !== strtoupper( (string) $request->get_method() ) ) {
			return $result;
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return $result;
		}
		$window = (int) apply_filters( 'nurax_rate_window', 600 ); // seconds
		$max    = (int) apply_filters( 'nurax_rate_max', 30 );      // requests per window
		$key    = 'nurax_rl_' . md5( $ip );
		$hits   = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return new WP_Error(
				'nurax_rate_limited',
				__( 'Too many requests. Please slow down and try again shortly.', 'nura-experience' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $hits + 1, $window );
		return $result;
	}
}
