<?php
/**
 * NURA Hardening (plugin 1.73.0).
 *
 * Extra production hardening that complements:
 * - class-nura-security.php    (REST rate limit, XML-RPC off, user enumeration block)
 * - class-nura-login-guard.php (login lockout, bot traps, password policy, sessions, HTTPS, CORS)
 * - theme inc/nura-security.php (front-end security headers, form spam guards)
 *
 * What this file adds:
 * 1. Clickjacking + cross-domain headers on every response (front end, login, admin).
 * 2. "no-store" caching for NURA Desk screens and exports, so customer data is never
 *    kept by shared computers, browser back/forward cache or proxies.
 * 3. Safer uploads: SVG/HTML/JS/XML style files are refused for anyone without the
 *    unfiltered_html capability, and disguised files such as "photo.php.jpg" are refused.
 * 4. Rate limits on public admin-ajax endpoints that had none (delivery quote, live search).
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Hardening {

	/** Public admin-ajax actions => max requests per IP per 10 minutes. */
	const AJAX_LIMITS = array(
		'nurax_wa_ship' => 120,
		'nura_search'   => 180,
	);

	/** Upload extensions that can carry script and are refused for normal staff. */
	const RISKY_EXT = array( 'svg', 'svgz', 'html', 'htm', 'shtml', 'xhtml', 'js', 'mjs', 'xml', 'swf', 'php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat' );

	public function __construct() {
		add_action( 'send_headers', array( $this, 'headers' ), 2 );
		add_action( 'login_init', array( $this, 'headers' ) );
		add_action( 'admin_init', array( $this, 'admin_headers' ), 1 );

		add_filter( 'upload_mimes', array( $this, 'upload_mimes' ), 99 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'upload_prefilter' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'upload_prefilter' ) );

		foreach ( array_keys( self::AJAX_LIMITS ) as $action ) {
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'throttle_ajax' ), 1 );
			add_action( 'wp_ajax_' . $action, array( $this, 'throttle_ajax' ), 1 );
		}
	}

	/* ------------------------------------------------------------ headers */

	/** Headers that are safe everywhere and never break the shop. */
	public function headers() {
		if ( headers_sent() ) {
			return;
		}
		// Modern replacement for X-Frame-Options: only our own pages may frame us.
		header( "Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'" );
		header( 'X-Permitted-Cross-Domain-Policies: none' );
	}

	/** Admin: core headers plus no-store on NURA Desk screens and exports. */
	public function admin_headers() {
		if ( headers_sent() ) {
			return;
		}
		$this->headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 === strpos( $page, 'nurax' ) || 0 === strpos( $action, 'nurax_' ) ) {
			nocache_headers();
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		}
	}

	/* ------------------------------------------------------------ uploads */

	public function upload_mimes( $mimes ) {
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $mimes;
		}
		foreach ( $mimes as $exts => $type ) {
			foreach ( explode( '|', $exts ) as $e ) {
				if ( in_array( strtolower( $e ), self::RISKY_EXT, true ) ) {
					unset( $mimes[ $exts ] );
					break;
				}
			}
		}
		return $mimes;
	}

	/** Refuse names like "invoice.php.jpg" and files whose real type is not allowed. */
	public function upload_prefilter( $file ) {
		$name  = isset( $file['name'] ) ? strtolower( (string) $file['name'] ) : '';
		$parts = explode( '.', $name );
		array_shift( $parts ); // Base name.
		array_pop( $parts );   // Final extension, checked by WordPress itself.
		foreach ( $parts as $p ) {
			if ( in_array( $p, array( 'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'cgi', 'pl', 'py', 'sh', 'exe', 'js', 'html', 'htm', 'svg' ), true ) ) {
				$file['error'] = __( 'This file name is not allowed. Please rename the file and try again.', 'nura-experience' );
				return $file;
			}
		}
		if ( ! empty( $file['tmp_name'] ) && ! empty( $file['name'] ) ) {
			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
				$file['error'] = __( 'This file type is not allowed for security reasons.', 'nura-experience' );
			}
		}
		return $file;
	}

	/* --------------------------------------------------------- rate limit */

	public function throttle_ajax() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( self::AJAX_LIMITS[ $action ] ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$ip  = class_exists( 'NURAX_Login_Guard' ) ? NURAX_Login_Guard::ip() : '0.0.0.0';
		$key = 'nurax_ax_' . md5( $action . '|' . $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= (int) apply_filters( 'nurax_ajax_limit', self::AJAX_LIMITS[ $action ], $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a few minutes.', 'nura-experience' ) ), 429 );
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	}
}
