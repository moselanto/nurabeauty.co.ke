<?php
/**
 * Theme 1.133.0: full-site audit fixes (13 Nov 2026).
 *
 * Audit of all 131 sitemap URLs + 47 internal links + 292 images found:
 *  1. Product SEO titles/descriptions (Rank Math meta written by NURA Desk catalogue) still
 *     said "Heat-Resistant Fibre" / "100% Human Hair" / "Human Hair Blend".
 *  2. Leftover product text: variation descriptions and local attributes on Semi Human Closure
 *     ("semi-human blend", hair-type "Human Hair Blend"), "100% human hair ..." at sentence start
 *     (Human Hair Bob), and "1-3 days" countrywide delivery on SDD Bob (rule: same-day dispatch).
 *  3. Six deleted products still linked from guides (404). 301 to the closest live page and
 *     the links in the guides are rewritten.
 *  4. Journal, My account and the Wig buying guides category had no meta description.
 * One-time data part runs on first admin wp-admin load (option nura_af_2026_done, log
 * nura_af_2026_log on Tools > NURA images).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Deleted product slug => live path. */
function nura_af_gone() {
	return array(
		'deep-curls-14-inches-wig' => '/product/12-inches-wig/',
		'jerry-curl-14-inches-wig' => '/product/12-inches-wig/',
		'22-inches-water-curl-wig' => '/product/pixie-water-curl-wig/',
		'afro-wig'                 => '/product-category/wigs/',
		'coily-headband-wig'       => '/product-category/wigs/headband-wigs/',
		'bubbles-headband-wig'     => '/product-category/wigs/headband-wigs/',
	);
}

add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
	if ( preg_match( '#^/product/([a-z0-9-]+)/?$#', $path, $m ) ) {
		$g = nura_af_gone();
		if ( isset( $g[ $m[1] ] ) ) {
			wp_safe_redirect( home_url( $g[ $m[1] ] ), 301 );
			exit;
		}
	}
}, 1 );

/** Meta descriptions for pages that had none. */
add_filter( 'rank_math/frontend/description', function ( $desc ) {
	if ( '' !== trim( (string) $desc ) ) {
		return $desc;
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return 'Sign in to your NURA Beauty account to track orders, download receipts, rebook installs and manage your Lipa Mdogo Mdogo payments.';
	}
	if ( is_category( 'wig-buying-guides' ) ) {
		return 'Wig buying guides from NURA Beauty, Nairobi: Pure Human vs Semi Human, lace types, sizes, prices in Kenya and how to choose your first wig.';
	}
	if ( is_home() || is_page( 'journal' ) ) {
		return 'The NURA Beauty journal: wig care, styling, prices in Kenya and honest buying advice from our Nairobi CBD studio.';
	}
	return $desc;
}, 99 );

function nura_af_hair_label( $key ) {
	$l = array( 'pure' => 'Pure Human', 'semi' => 'Semi Human', 'blend' => 'Human Blend' );
	return isset( $l[ $key ] ) ? $l[ $key ] : '';
}

function nura_af_fix( $text, $key ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return $text;
	}
	$lab = nura_af_hair_label( $key );
	$rep = array(
		'/Heat-Resistant Fibre |Premium Luxury Fibre |Luxury Fibre (?=\d|Headband|Lace|2x2|4x4|13x4)/' => '' !== $lab ? $lab . ' ' : '',
		'/100% Human Hair /'                                           => 'Pure Human ',
		'/\bSemi-Human\b/'                                             => 'Semi Human',
		'/ \((?:semi-human blend|human hair blend)\)/i'               => '',
		'/crafted from (?:a )?human hair blend/i'                      => 'Semi Human: human hair mixed with good-quality fibre',
		'/(?<![\w(])[Hh]uman [Hh]air [Bb]lend/'                        => 'semi' === $key ? 'Semi Human' : ( 'blend' === $key ? 'Human Blend' : 'Semi Human' ),
		'/(^|>|\. )100% human hair /'                                  => '$1Pure Human (100% human hair) ',
		'/(?:courier|parcel) in 1\s*(?:-|–|&#8211;)\s*3 days/'         => 'courier, dispatched the same day',
		'/in 1\s*(?:-|–|&#8211;)\s*3 (?:working |business )?days/'     => 'with same-day dispatch',
	);
	if ( 'none' === $key ) {
		$rep['/a heat-resistant fibre /i'] = 'a ';
	}
	foreach ( $rep as $re => $to ) {
		$n = preg_replace( $re, $to, $text );
		if ( null !== $n ) {
			$text = $n;
		}
	}
	return $text;
}

add_action( 'admin_init', 'nura_af_migrate', 32 );
function nura_af_migrate() {
	if ( get_option( 'nura_af_2026_done' ) || ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'nura_ht_map' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	update_option( 'nura_af_2026_done', time(), false );
	$log = array();
	foreach ( nura_ht_map() as $id => $key ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$ch = array();
		foreach ( array( 'rank_math_title', 'rank_math_description' ) as $mk ) {
			$v  = (string) get_post_meta( $id, $mk, true );
			$nv = nura_af_fix( $v, $key );
			if ( 'none' === $key ) {
				$nv = preg_replace( '/^Fibre (?=Luxury Fibre)/', '', $nv );
			}
			if ( $nv !== $v ) {
				update_post_meta( $id, $mk, $nv );
				$ch[] = $mk;
			}
		}
		$sd = nura_af_fix( $p->get_short_description(), $key );
		$de = nura_af_fix( $p->get_description(), $key );
		if ( $sd !== $p->get_short_description() || $de !== $p->get_description() ) {
			$p->set_short_description( $sd );
			$p->set_description( $de );
			$ch[] = 'text';
		}
		// Local (non-taxonomy) attributes, e.g. hair-type "Human Hair Blend", texture "(semi-human blend)".
		$attrs = $p->get_attributes();
		$adirty = false;
		foreach ( $attrs as $k => $a ) {
			if ( ! $a instanceof WC_Product_Attribute || $a->is_taxonomy() ) {
				continue;
			}
			$opts = $a->get_options();
			if ( preg_match( '/hair[\s_-]*type/i', $a->get_name() ) ) {
				$new = '' !== nura_af_hair_label( $key ) ? array( nura_af_hair_label( $key ) ) : array();
				if ( ! $new ) {
					unset( $attrs[ $k ] );
					$adirty = true;
					continue;
				}
			} else {
				$new = array_map( function ( $o ) use ( $key ) { return trim( nura_af_fix( $o, $key ) ); }, $opts );
			}
			if ( $new !== $opts ) {
				$a->set_options( $new );
				$attrs[ $k ] = $a;
				$adirty      = true;
			}
		}
		if ( $adirty ) {
			$p->set_attributes( $attrs );
			$ch[] = 'attributes';
		}
		$p->save();
		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v ) {
					$d  = $v->get_description();
					$nd = nura_af_fix( $d, $key );
					if ( $nd !== $d ) {
						$v->set_description( $nd );
						$v->save();
						$ch[] = 'variation ' . $vid;
					}
				}
			}
		}
		if ( $ch ) {
			wc_delete_product_transients( $id );
			$log[] = $id . ': ' . implode( ',', array_unique( $ch ) );
		}
	}
	// Guide links to deleted products.
	global $wpdb;
	foreach ( nura_af_gone() as $slug => $to ) {
		$like = '%' . $wpdb->esc_like( '/product/' . $slug . '/' ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page') AND post_content LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $ids as $pid ) {
			$c = (string) get_post_field( 'post_content', $pid );
			$n = preg_replace( '#(https?://nurabeauty\.co\.ke)?/product/' . preg_quote( $slug, '#' ) . '/#', home_url( $to ), $c );
			if ( $n && $n !== $c ) {
				kses_remove_filters();
				wp_update_post( wp_slash( array( 'ID' => (int) $pid, 'post_content' => $n ) ) );
				kses_init_filters();
				$log[] = 'link ' . $slug . ' fixed in ' . $pid;
			}
		}
	}
	update_option( 'nura_af_2026_log', $log, false );
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['page'] ) || 'nura-images' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$log = (array) get_option( 'nura_af_2026_log', array() );
	if ( $log ) {
		echo '<div class="notice notice-info"><p><strong>Audit fixes applied:</strong> ' . esc_html( implode( ' | ', $log ) ) . '</p></div>';
	}
} );

/* 1.134.0: installation price list from the owner's banner (13 Nov 2026). */
function nura_inst_2026_services() {
	return "Wig installation | KSh 1,000 | Mid part or side part.\nHalf up half down | KSh 1,500 | Half up, half down installation.\nTonging | KSh 800 | Soft curls and waves.\nFlat ironing | KSh 500 | Sleek, straight finish.\nRevamp | KSh 1,500 | Washing and styling to bring your unit back to life.\nBridal styling | KSh 3,500 | Your crown for the big day.\nColouring | KSh 2,500 - 3,500 | Custom colour for human hair units only.\nRe-lacing / repair | KSh 2,000 | New lace or repairs so your unit fits and lasts.";
}
add_action( 'admin_init', function () {
	if ( get_option( 'nura_inst_2026_done' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	update_option( 'nura_inst_2026_done', time(), false );
	set_theme_mod( 'nura_inst_services', nura_inst_2026_services() );
	$lede = (string) get_theme_mod( 'nura_inst_lede', '' );
	if ( '' !== $lede ) {
		set_theme_mod( 'nura_inst_lede', str_replace( 'install, styling, colouring or revamp', 'install, tonging, flat ironing, revamp or bridal styling', $lede ) );
	}
	$log = array( 'services price list set' );
	$rep = array(
		'Installation is from KSh 1,500'     => 'Installation is from KSh 1,000',
		'Installation starts from KSh 1,500' => 'Installation starts from KSh 1,000',
		'installation from KSh 1,500'        => 'installation from KSh 1,000',
		'Installation from KSh 1,500'        => 'Installation from KSh 1,000',
		'Prices from KSh 1,500 &'            => 'Prices from KSh 1,000 &',
		'Nairobi CBD from KSh 1,500:'        => 'Nairobi CBD from KSh 1,000:',
		'Installation inaanza KSh 1,500'     => 'Installation inaanza KSh 1,000',
		'installation kuanzia KSh 1,500'     => 'installation kuanzia KSh 1,000',
		'(from KSh 1,500)'                   => '(from KSh 1,000)',
	);
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page') AND post_content LIKE '%KSh 1,500%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $ids as $pid ) {
		$c = (string) get_post_field( 'post_content', $pid );
		$n = strtr( $c, $rep );
		if ( $n !== $c ) {
			kses_remove_filters();
			wp_update_post( wp_slash( array( 'ID' => (int) $pid, 'post_content' => $n ) ) );
			kses_init_filters();
			$log[] = 'page ' . $pid;
		}
	}
	foreach ( (array) $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('rank_math_title','rank_math_description') AND meta_value LIKE '%KSh 1,500%'" ) as $pid ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( array( 'rank_math_title', 'rank_math_description' ) as $mk ) {
			$v = (string) get_post_meta( $pid, $mk, true );
			$n = strtr( $v, $rep );
			if ( $n !== $v ) {
				update_post_meta( $pid, $mk, $n );
				$log[] = $mk . ' ' . $pid;
			}
		}
	}
	update_option( 'nura_inst_2026_log', $log, false );
}, 33 );
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['page'] ) || 'nura-images' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$log = (array) get_option( 'nura_inst_2026_log', array() );
	if ( $log ) {
		echo '<div class="notice notice-info"><p><strong>Installation prices updated:</strong> ' . esc_html( implode( ' | ', $log ) ) . '</p></div>';
	}
} );

/* 1.135.0: service prices everywhere match the owner's banner + colouring/re-lacing (13 Nov 2026). */
add_action( 'admin_init', function () {
	if ( get_option( 'nura_svc_2026_done' ) || ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'nura_inst_2026_services' ) ) {
		return;
	}
	update_option( 'nura_svc_2026_done', time(), false );
	set_theme_mod( 'nura_inst_services', nura_inst_2026_services() );
	$d   = '\s*(?:-|–|—|&\#8211;|&\#8212;|&ndash;|to)\s*';
	$c   = '(\s*</t[dh]>\s*<t[dh][^>]*>\s*)';
	$rep = array(
		'#(Installation' . $c . ')From KSh 1,500#i'                => '${1}KSh 1,000',
		'#(Wash &(?:amp;)? revamp[^<]{0,20}' . $c . ')From KSh 1,500#i'      => '${1}KSh 1,500',
		'#(re-lacing[^<]{0,20}' . $c . ')From KSh 2,000#i'                   => '${1}KSh 2,000',
		'#Styling / restyle(' . $c . ')KSh 1,000' . $d . '2,500#i' => 'Tonging / flat ironing${1}KSh 500 - 800',
		'#KSh 2,500' . $d . '5,000#'                               => 'KSh 2,500 - 3,500',
		'#2,500 to 5,000#'                                         => '2,500 to 3,500',
		'#installation at our studio starts from KSh 1,500#i'      => 'installation at our studio is KSh 1,000',
		'#[Ii]nstallation (is|starts) from KSh 1,500#'             => 'Installation $1 from KSh 1,000',
		'#a wash and revamp from KSh 1,500#'                       => 'a wash and revamp is KSh 1,500',
		'#[Ww]ash and revamp is from KSh 1,500#'                   => 'Wash and revamp is KSh 1,500',
		'#re-lacing or repair (is )?from KSh 2,000#'               => 're-lacing or repair is KSh 2,000',
		'#Re-lacing or repair is from KSh 2,000#'                  => 'Re-lacing or repair is KSh 2,000',
		'#[Ss]tyling or restyling[^.<]{0,60}costs KSh 1,000 to 2,500#' => 'Tonging is KSh 800 and flat ironing KSh 500',
		'#Revamps start from KSh 1,500#'                           => 'Revamps are KSh 1,500',
		'#CSCR Lace Melting Spray \(KSh 750\)#'                    => 'CSCR Lace Melting Spray (KSh 1,000)',
	);
	$fix = function ( $t ) use ( $rep ) {
		foreach ( $rep as $re => $to ) {
			$n = preg_replace( $re, $to, (string) $t );
			if ( null !== $n ) {
				$t = $n;
			}
		}
		return $t;
	};
	global $wpdb;
	$log = array( 'services price list set' );
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page') AND ( post_content LIKE '%KSh%' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $ids as $pid ) {
		$o = (string) get_post_field( 'post_content', $pid );
		$n = $fix( $o );
		if ( $n !== $o ) {
			kses_remove_filters();
			wp_update_post( wp_slash( array( 'ID' => (int) $pid, 'post_content' => $n ) ) );
			kses_init_filters();
			$log[] = 'page ' . $pid;
		}
	}
	foreach ( (array) $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('rank_math_title','rank_math_description') AND meta_value LIKE '%KSh%'" ) as $pid ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( array( 'rank_math_title', 'rank_math_description' ) as $mk ) {
			$v = (string) get_post_meta( $pid, $mk, true );
			$n = $fix( $v );
			if ( $n !== $v ) {
				update_post_meta( $pid, $mk, $n );
				$log[] = $mk . ' ' . $pid;
			}
		}
	}
	update_option( 'nura_svc_2026_log', $log, false );
}, 34 );
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['page'] ) || 'nura-images' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$log = (array) get_option( 'nura_svc_2026_log', array() );
	if ( $log ) {
		echo '<div class="notice notice-info"><p><strong>Service prices updated:</strong> ' . esc_html( implode( ' | ', $log ) ) . '</p></div>';
	}
} );
