<?php
/**
 * NURA Beauty - security & anti-abuse hardening.
 *
 * Layered, dependency-free hardening that runs on every request:
 *  - removes common WordPress attack surface (XML-RPC, version/user
 *    fingerprinting, REST user enumeration, ?author= scans, dashboard file
 *    editing, pingbacks/trackbacks),
 *  - sends browser security headers,
 *  - tells AI training/scraping crawlers to stay out (robots.txt) and hard-blocks
 *    the worst-behaved scrapers that ignore robots,
 *  - masks login errors so usernames can't be probed,
 *  - protects the newsletter opt-in with a honeypot + time-trap + rate limit,
 *  - bins link-stuffed comment spam.
 *
 * NOTE: no site is "unhackable". This closes the common holes; pair it with an
 * edge/host WAF (e.g. Cloudflare), a login-security plugin, two-factor auth,
 * strong unique admin passwords and prompt core/plugin updates. Mirror the
 * headers at the server (.htaccess) so cached responses are covered too - see
 * the deploy notes.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------------
 * 1. Disable dashboard file editing so a compromised admin session can't edit
 *    theme/plugin code straight from the browser.
 * -------------------------------------------------------------------------- */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/* -----------------------------------------------------------------------------
 * 2. Kill XML-RPC + pingbacks/trackbacks - a top brute-force amplification and
 *    comment-spam vector that this store does not use.
 * -------------------------------------------------------------------------- */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', function () {
	return array();
} );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );
add_filter( 'pings_open', '__return_false' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

/* -----------------------------------------------------------------------------
 * 3. Stop software/version fingerprinting in the page head and feeds.
 * -------------------------------------------------------------------------- */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/* -----------------------------------------------------------------------------
 * 4. Block user enumeration: the REST users endpoint for guests, and the
 *    classic ?author=N redirect probe.
 * -------------------------------------------------------------------------- */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
		if ( isset( $endpoints[ $route ] ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
} );
add_action( 'template_redirect', function () {
	if ( ! is_admin() && ! is_user_logged_in() && isset( $_GET['author'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* -----------------------------------------------------------------------------
 * 5. Browser security headers. These apply to dynamic (uncached) responses;
 *    mirror them at the edge/.htaccess so cached HTML is covered as well.
 * -------------------------------------------------------------------------- */
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: geolocation=(), microphone=(), camera=(self), browsing-topics=()' );
	header( 'Cross-Origin-Opener-Policy: same-origin' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
}, 1 );

/* -----------------------------------------------------------------------------
 * 6. Keep AI training/scraping crawlers out via robots.txt (Google/Bing search
 *    are untouched) and keep private/transactional paths out of every index.
 *    Only applies when a static robots.txt file is NOT present in web root.
 * -------------------------------------------------------------------------- */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) {
		return $output; // Respect "discourage search engines" in Settings > Reading.
	}
	$ai_bots = array(
		'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'CCBot', 'ClaudeBot', 'Claude-Web',
		'anthropic-ai', 'Google-Extended', 'Applebot-Extended', 'PerplexityBot',
		'Amazonbot', 'Bytespider', 'Diffbot', 'ImagesiftBot', 'Omgili', 'Omgilibot',
		'FacebookBot', 'meta-externalagent', 'cohere-ai', 'YouBot', 'Timpibot',
		'DataForSeoBot', 'magpie-crawler', 'Scrapy',
	);
	$extra = "\n# NURA: block AI training / scraping crawlers\n";
	foreach ( $ai_bots as $bot ) {
		$extra .= "User-agent: {$bot}\nDisallow: /\n\n";
	}
	$extra .= "# NURA: keep private / transactional paths out of all indexes\n";
	$extra .= "User-agent: *\n";
	$extra .= "Disallow: /wp-admin/\n";
	$extra .= "Allow: /wp-admin/admin-ajax.php\n";
	$extra .= "Disallow: /wp-login.php\n";
	$extra .= "Disallow: /cart/\n";
	$extra .= "Disallow: /checkout/\n";
	$extra .= "Disallow: /my-account/\n";
	$extra .= "Disallow: /*add-to-cart=\n";
	$extra .= "Disallow: /*?*orderby=\n";
	$extra .= "Disallow: /?s=\n";
	return $output . $extra;
}, 10, 2 );

/* -----------------------------------------------------------------------------
 * 7. Hard-block the worst-behaved scraper/AI user-agents that ignore robots.txt.
 *    Deliberately EXCLUDES generic HTTP clients (curl/python/Go) and REST/cron
 *    so server-to-server callbacks (M-Pesa / Daraja, payment webhooks, WP-Cron,
 *    uptime monitors) keep working. Extend via `nura_blocked_user_agents`.
 * -------------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( is_admin()
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if ( '' === $ua ) {
		return;
	}
	$blocked = apply_filters( 'nura_blocked_user_agents', array(
		'Bytespider', 'GPTBot', 'CCBot', 'ClaudeBot', 'anthropic-ai', 'PerplexityBot',
		'Amazonbot', 'Diffbot', 'ImagesiftBot', 'DataForSeoBot', 'MJ12bot', 'DotBot',
		'PetalBot', 'BLEXBot', 'Scrapy', 'magpie-crawler', 'Barkrowler', 'serpstatbot',
	) );
	foreach ( $blocked as $needle ) {
		if ( false !== stripos( $ua, $needle ) ) {
			status_header( 403 );
			nocache_headers();
			exit( 'Forbidden' );
		}
	}
}, 0 );

/* -----------------------------------------------------------------------------
 * 8. Don't reveal whether a username exists on a failed login.
 * -------------------------------------------------------------------------- */
add_filter( 'login_errors', function () {
	return __( 'Invalid login details.', 'nura-beauty' );
} );

/* -----------------------------------------------------------------------------
 * 9. Newsletter opt-in anti-spam: honeypot + time-trap + per-IP rate limit.
 *    Runs BEFORE the real nura_subscribe handler (priority 0) and quietly
 *    bounces bots back without ever touching the mailing list.
 * -------------------------------------------------------------------------- */
function nura_subscribe_spam_guard() {
	$bounce = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	// Honeypot: a hidden field real users never fill.
	if ( ! empty( $_POST['nura_hp'] ) ) {
		wp_safe_redirect( $bounce );
		exit;
	}
	// Time-trap: a submit within 2s of the page rendering is a bot.
	$ts = isset( $_POST['nura_sub_ts'] ) ? absint( $_POST['nura_sub_ts'] ) : 0;
	if ( $ts && ( time() - $ts ) < 2 ) {
		wp_safe_redirect( $bounce );
		exit;
	}
	// Per-IP rate limit: at most 5 submissions per 10 minutes.
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR'] ) : '0';
	$key  = 'nura_sub_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		wp_safe_redirect( $bounce );
		exit;
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );
}
add_action( 'admin_post_nopriv_nura_subscribe', 'nura_subscribe_spam_guard', 0 );
add_action( 'admin_post_nura_subscribe', 'nura_subscribe_spam_guard', 0 );

/* -----------------------------------------------------------------------------
 * 10. Light comment-spam guard: reject link-stuffed comments (WooCommerce
 *     product reviews are unaffected in normal use). Bots love pasting links.
 * -------------------------------------------------------------------------- */
add_filter( 'preprocess_comment', function ( $commentdata ) {
	$content = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
	if ( preg_match_all( '#https?://#i', $content ) > 2 ) {
		wp_die(
			esc_html__( 'Your comment looks like spam and was blocked.', 'nura-beauty' ),
			esc_html__( 'Comment blocked', 'nura-beauty' ),
			array( 'response' => 403, 'back_link' => true )
		);
	}
	return $commentdata;
} );


/* -----------------------------------------------------------------------------
 * 11. Contact / appointment form anti-spam: honeypot + time-trap + per-IP rate
 *     limit, mirroring the newsletter guard (section 9). A valid nonce alone
 *     does not stop bots that scrape it off the page, and nura_handle_contact
 *     emails on every valid submit - so guard it before the handler runs
 *     (priority 0) and silently bounce bots without ever emailing anyone.
 * -------------------------------------------------------------------------- */
function nura_contact_spam_guard() {
	$bounce = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	// Honeypot: a hidden field real users never fill.
	$hp = isset( $_POST['nura_hp'] ) ? trim( (string) wp_unslash( $_POST['nura_hp'] ) ) : '';
	if ( strlen( $hp ) > 0 ) {
		wp_safe_redirect( $bounce );
		exit;
	}
	// Time-trap: a submit within 2s of the page rendering is a bot.
	$ts = isset( $_POST['nura_ct_ts'] ) ? absint( $_POST['nura_ct_ts'] ) : 0;
	if ( $ts && ( time() - $ts ) < 2 ) {
		wp_safe_redirect( $bounce );
		exit;
	}
	// Per-IP rate limit: at most 5 submissions per 10 minutes.
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	$key  = 'nura_ct_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		wp_safe_redirect( $bounce );
		exit;
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );
}
add_action( 'admin_post_nopriv_nura_contact', 'nura_contact_spam_guard', 0 );
add_action( 'admin_post_nura_contact', 'nura_contact_spam_guard', 0 );
