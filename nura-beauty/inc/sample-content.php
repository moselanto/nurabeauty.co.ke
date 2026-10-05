<?php
/**
 * Sample content manifest.
 *
 * Single source of truth for the one-click importer: which pages to create,
 * how menus are grouped, the product categories, and the demo products (with
 * variations). Everything created is fully editable afterwards.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pages to create. 'file' is a template under demo/pages/. 'menu' groups the
 * page into a footer menu location. Placeholder tokens in the HTML are swapped
 * for live shortcodes at import time.
 */
function nura_sample_pages() {
	return array(
		array( 'title' => 'About Us',              'slug' => 'about-us',              'file' => 'about-us',              'menu' => 'company' ),
		array( 'title' => 'Contact Us',            'slug' => 'contact-us',            'file' => 'contact-us',            'menu' => 'company' ),
		array( 'title' => 'Track Order',           'slug' => 'track-order',           'file' => 'track-order',           'menu' => 'help' ),
		array( 'title' => 'Shipping & Delivery',   'slug' => 'shipping-delivery',     'file' => 'shipping-delivery',     'menu' => 'help' ),
		array( 'title' => 'Delivery',              'slug' => 'delivery',              'file' => 'delivery',              'menu' => 'help' ),
		array( 'title' => 'Returns & Refunds',     'slug' => 'returns-refunds',       'file' => 'returns-refunds',       'menu' => 'help' ),
		array( 'title' => 'Warranty',              'slug' => 'warranty',              'file' => 'warranty',              'menu' => 'help' ),
		array( 'title' => 'FAQ',                   'slug' => 'faq',                   'file' => 'faq',                   'menu' => 'help' ),
		array( 'title' => 'Help & Support',        'slug' => 'help-support',          'file' => 'help-support',          'menu' => 'help' ),
		array( 'title' => 'Installation',          'slug' => 'installation',          'file' => 'installation',          'menu' => 'company' ),
		array( 'title' => 'Bulk & Corporate Orders','slug' => 'bulk-corporate-orders','file' => 'bulk-corporate-orders', 'menu' => 'company' ),
		array( 'title' => 'Book Appointment',      'slug' => 'book-appointment',      'file' => 'book-appointment',      'menu' => 'company' ),
		array( 'title' => 'AI Wig Finder',         'slug' => 'ai-wig-finder',         'file' => 'ai-wig-finder',         'menu' => 'company' ),
		array( 'title' => 'Virtual Try-On',        'slug' => 'virtual-try-on',        'file' => 'virtual-try-on',        'menu' => 'company' ),
		array( 'title' => 'The NURA Circle',       'slug' => 'nura-circle',           'file' => 'nura-circle',           'menu' => 'company' ),
	);
}

/**
 * Placeholder tokens -> shortcodes, replaced when the page is imported.
 */
function nura_content_placeholders() {
	return array(
		'[contact-form-7-placeholder]' => '[nura_contact_form]',
		'[booking-form-placeholder]'   => '[nura_booking_form]',
	);
}

/**
 * Product categories (top-level collections).
 */
function nura_sample_categories() {
	return array(
		'ready-to-wear'   => 'Ready-to-Wear',
		'lace-hd'         => 'Lace Front & HD Lace',
		'custom-bespoke'  => 'Custom & Bespoke',
		'extensions'      => 'Hair Extensions & Bundles',
		'confidence-line' => 'The Confidence Line',
		'bridal'          => 'Bridal & Occasion',
		'accessories'     => 'Accessories',
		'pre-loved'       => 'Certified Pre-Loved',
	);
}

/**
 * Global product attributes -> terms. These power unlimited variations, each
 * with its own SKU / price / stock / gallery.
 */
function nura_sample_attributes() {
	return array(
		'length'       => array( 'label' => 'Length',       'terms' => array( '10"','12"','14"','16"','18"','20"','22"','24"','26"','28"','30"' ) ),
		'density'      => array( 'label' => 'Density',      'terms' => array( '130%','150%','180%','200%' ) ),
		'texture'      => array( 'label' => 'Texture',      'terms' => array( 'Straight','Body Wave','Deep Wave','Loose Wave','Water Wave','Curly','Kinky Curly','Afro Curly' ) ),
		'color'        => array( 'label' => 'Colour',       'terms' => array( 'Natural Black','1B','Brown','Blonde','Ombre','Burgundy','613' ) ),
		'cap-size'     => array( 'label' => 'Cap Size',     'terms' => array( 'Small','Medium','Large' ) ),
		'cap-construction' => array( 'label' => 'Cap Construction', 'terms' => array( '13x4','13x6','360 Lace','HD Lace','Full Lace','Glueless' ) ),
		'lace-color'   => array( 'label' => 'Lace Colour',  'terms' => array( 'Transparent','HD','Medium Brown','Light Brown' ) ),
	);
}

/**
 * Demo products. Prices in KES, based on the NURA launch price list.
 * 'variations' lists attribute combinations, each with its own SKU/price/stock.
 */
function nura_sample_products() {
	return array(
		array(
			'name' => 'Amara — HD Lace Front Wig',
			'type' => 'variable',
			'cats' => array( 'lace-hd', 'ready-to-wear' ),
			'short' => 'Undetectable HD lace, glueless-ready. Verified human hair, hand-finished in Nairobi.',
			'desc'  => 'Amara is our signature HD lace front — a barely-there hairline on a comfortable, wear-and-go cap. Verified human hair, ships with a provenance certificate and written guarantee.',
			'attributes' => array( 'length' => array('16"','18"','20"'), 'texture' => array('Body Wave','Straight'), 'lace-color' => array('HD','Transparent') ),
			'variation_price' => array( '16"' => 16500, '18"' => 18500, '20"' => 21000 ),
			'featured' => true,
		),
		array(
			'name' => 'Zuri — Glueless Bob',
			'type' => 'variable',
			'cats' => array( 'ready-to-wear', 'lace-hd' ),
			'short' => 'The effortless everyday bob. Glueless, beginner-friendly, ready in minutes.',
			'desc'  => 'Zuri is the wear-and-go bob for the woman on the move — glueless cap, natural density, styled and ready.',
			'attributes' => array( 'length' => array('10"','12"'), 'color' => array('Natural Black','1B','Brown') ),
			'variation_price' => array( '10"' => 8500, '12"' => 9500 ),
			'featured' => true,
		),
		array(
			'name' => 'Malaika — Body Wave Lace',
			'type' => 'variable',
			'cats' => array( 'lace-hd' ),
			'short' => 'Soft, romantic body wave with movement and shine.',
			'desc'  => 'Malaika brings glamorous body-wave movement in lengths up to 26". Verified human hair with our longevity guarantee.',
			'attributes' => array( 'length' => array('14"','16"','22"','26"'), 'density' => array('150%','180%') ),
			'variation_price' => array( '14"' => 12000, '16"' => 14000, '22"' => 22000, '26"' => 26000 ),
			'featured' => true,
		),
		array(
			'name' => 'Nia — Custom Bespoke Unit',
			'type' => 'variable',
			'cats' => array( 'custom-bespoke' ),
			'short' => 'Built to your measurements, texture, density and length. The NURA signature.',
			'desc'  => 'Nia is our atelier bespoke service — hand-built to your exact specification. A deposit begins your build; balance before delivery via NURA Flex.',
			'attributes' => array( 'cap-construction' => array('13x6','360 Lace','Full Lace'), 'length' => array('18"','22"','26"','30"') ),
			'variation_price' => array( '18"' => 25000, '22"' => 30000, '26"' => 36000, '30"' => 42000 ),
			'featured' => true,
		),
		array(
			'name' => 'Neema — The Confidence Unit (Medical)',
			'type' => 'variable',
			'cats' => array( 'confidence-line' ),
			'short' => 'Lightweight comfort cap for medical and hair-loss clients. Discreet and secure.',
			'desc'  => 'Neema is designed for comfort and security — a lightweight cap, gentle on sensitive scalps, styled to feel entirely your own. Fitted with discretion and care.',
			'attributes' => array( 'cap-size' => array('Small','Medium','Large'), 'length' => array('12"','16"') ),
			'variation_price' => array( '12"' => 19000, '16"' => 23000 ),
		),
		array(
			'name' => 'Raw Bundles — Vietnamese Straight',
			'type' => 'variable',
			'cats' => array( 'extensions' ),
			'short' => 'Premium raw bundles for sew-ins and custom builds.',
			'desc'  => 'Single-donor raw bundles, priced per bundle. Mix lengths for a full install.',
			'attributes' => array( 'length' => array('14"','18"','22"','26"') ),
			'variation_price' => array( '14"' => 3500, '18"' => 4500, '22"' => 5500, '26"' => 6000 ),
		),
		array(
			'name' => 'NURA Bridal Package',
			'type' => 'simple',
			'cats' => array( 'bridal' ),
			'short' => 'Unit + trial + install + touch-up, booked as one radiant experience.',
			'desc'  => 'Everything for your day: a premium unit, a styling trial, install on the morning, and a touch-up. Priced from — final quote at consultation.',
			'price' => 30000,
		),
		array(
			'name' => 'Wig Care Kit',
			'type' => 'simple',
			'cats' => array( 'accessories' ),
			'short' => 'Everything to keep your crown looking new — cap, edge control, stand.',
			'desc'  => 'The NURA care essentials: wig cap, edge control and a display stand. Add to any unit.',
			'price' => 1600,
		),
	);
}
