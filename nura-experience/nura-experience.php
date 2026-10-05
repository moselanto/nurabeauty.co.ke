<?php
/**
 * Plugin Name:       NURA Experience
 * Plugin URI:        https://nurabeauty.co.ke
 * Description:       NURA's exclusive features: AI Wig Finder, Virtual Try-On, and The NURA Circle luxury client portal (order history, care schedule, warranty certificates, maintenance reminders, loyalty points, VIP membership), plus the NURA catalogue architecture, a catalogue-driven mega menu, mobile bottom navigation, a faceted shop experience and an upgraded product page. Requires WooCommerce.
 * Version:           1.35.3
 * Author:            NURA - The House of Radiant Confidence
 * License:           GPL-2.0-or-later
 * Text Domain:       nura-experience
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NURAX_VERSION', '1.35.3' );
define( 'NURAX_DIR', plugin_dir_path( __FILE__ ) );
define( 'NURAX_URL', plugin_dir_url( __FILE__ ) );

require_once NURAX_DIR . 'includes/class-ai-wig-finder.php';
require_once NURAX_DIR . 'includes/class-virtual-tryon.php';
require_once NURAX_DIR . 'includes/class-nura-circle.php';
require_once NURAX_DIR . 'includes/class-settings.php';
require_once NURAX_DIR . 'includes/class-ai-stylist.php';
require_once NURAX_DIR . 'includes/class-shop-enhance.php';
require_once NURAX_DIR . 'includes/class-shop-filters.php';
require_once NURAX_DIR . 'includes/class-product-page.php';
// Variation swatches are provided by the active theme (data-nura-swatches);
// the plugin's own swatch layer is retired to avoid a duplicate, conflicting UI
// that corrupted the variation selection and blocked AJAX add-to-cart.
// require_once NURAX_DIR . 'includes/class-variation-swatches.php';
require_once NURAX_DIR . 'includes/class-care-reminders.php';
require_once NURAX_DIR . 'includes/class-journal.php';
require_once NURAX_DIR . 'includes/class-wig-attributes.php';
require_once NURAX_DIR . 'includes/class-mega-menu.php';

// AI + WhatsApp assistant (multi-provider brain shared by web + WhatsApp).
require_once NURAX_DIR . 'includes/class-nura-ai-providers.php';
require_once NURAX_DIR . 'includes/class-nura-ai.php';
require_once NURAX_DIR . 'includes/class-nura-whatsapp.php';
require_once NURAX_DIR . 'includes/class-nura-whatsapp-bot.php';

// Growth: marketing consent, abandoned-cart recovery, follow-ups, retargeting.
require_once NURAX_DIR . 'includes/class-nura-consent.php';
require_once NURAX_DIR . 'includes/class-nura-abandoned-cart.php';
require_once NURAX_DIR . 'includes/class-nura-followup.php';
require_once NURAX_DIR . 'includes/class-nura-retargeting.php';

// Storefront: Find-Your-Wig quiz, trust layer (badges + reviews), policy pages.
require_once NURAX_DIR . 'includes/class-nura-wig-quiz.php';
require_once NURAX_DIR . 'includes/class-nura-trust.php';
require_once NURAX_DIR . 'includes/class-nura-pages.php';

// SEO structured data, safe security hardening, and performance wins.
require_once NURAX_DIR . 'includes/class-nura-schema.php';
require_once NURAX_DIR . 'includes/class-nura-google-reviews.php';
require_once NURAX_DIR . 'includes/class-nura-security.php';
require_once NURAX_DIR . 'includes/class-nura-perf.php';

// Admin housekeeping: tidy third-party admin list-table columns.
require_once NURAX_DIR . 'includes/class-admin-tidy.php';

/**
 * Boot.
 */
function nurax_init() {
	new NURAX_AI_Wig_Finder();
	new NURAX_Virtual_TryOn();
	new NURAX_Circle();
	new NURAX_Settings();
	new NURAX_AI_Stylist();
	new NURAX_Shop_Enhance();
	new NURAX_Shop_Filters();
	new NURAX_Product_Page();
	// new NURAX_Variation_Swatches(); // Retired: the theme now renders + drives variation swatches.
	new NURAX_Care_Reminders();
	new NURAX_Journal();
	new NURAX_Wig_Attributes();
	new NURAX_Mega_Menu();

	// AI brain + inbound WhatsApp assistant. Dormant until an AI key and the
	// WhatsApp Cloud API webhook are configured; safe to load unconditionally.
	NURAX_AI::instance();
	new NURAX_WhatsApp_Bot();

	// Growth modules. Each installs its own storage and stays dormant until
	// enabled in its own settings screen (all OFF by default).
	NURAX_Consent::instance()->boot();
	NURAX_Abandoned_Cart::instance()->boot();
	NURAX_Followup::instance()->boot();
	NURAX_Retargeting::instance()->boot();

	// Storefront conversion helpers. The two shortcodes render only where they
	// are placed; the trust settings and page importer live in the admin. All
	// dormant by default - zero effect on the live site until used.
	new NURAX_Wig_Quiz();
	NURAX_Trust::instance()->boot();
	new NURAX_Pages();

	// SEO structured data, safe security hardening, and performance wins.
	new NURAX_Schema();
	new NURAX_Google_Reviews();
	new NURAX_Security();
	new NURAX_Perf();

	// Admin housekeeping (admin-only): keep third-party SEO columns from
	// breaking the WooCommerce product list layout.
	if ( is_admin() ) {
		new NURAX_Admin_Tidy();
	}
}
add_action( 'plugins_loaded', 'nurax_init' );

/**
 * Shared front-end assets.
 */
function nurax_assets() {
	wp_enqueue_style( 'nurax', NURAX_URL . 'assets/css/nurax.css', array(), NURAX_VERSION );
	wp_enqueue_style( 'nurax-ux', NURAX_URL . 'assets/css/nura-ux.css', array( 'nurax' ), NURAX_VERSION );
	wp_enqueue_script( 'nurax', NURAX_URL . 'assets/js/nurax.js', array(), NURAX_VERSION, true );
	wp_localize_script( 'nurax', 'NURAX', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'nurax' ),
		'rest'    => esc_url_raw( rest_url( 'nurax/v1/' ) ),
		'shopUrl' => ( function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' ) ),
	) );
}
add_action( 'wp_enqueue_scripts', 'nurax_assets' );

/**
 * Admin notice if WooCommerce is missing.
 */
function nurax_wc_notice() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="notice notice-warning"><p><strong>NURA Experience</strong> needs WooCommerce active for the client portal, try-on cart actions and product recommendations.</p></div>';
	}
}
add_action( 'admin_notices', 'nurax_wc_notice' );
