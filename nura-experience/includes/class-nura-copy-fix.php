<?php
/**
 * NURA Experience 1.49.0 - one-time hair-type copy correction on live pages.
 *
 * The FAQ, About, Warranty and Bulk Orders pages were published with lines saying
 * every NURA wig is "verified human hair". The catalogue also sells human-hair
 * blend and heat-resistant fibre units, so those lines were inaccurate (a
 * consumer-protection risk). Published pages live in the database, so changing the
 * templates alone would not fix them. This runs once in wp-admin, rewrites only the
 * specific inaccurate sentences (everything else on each page is left as it is),
 * and shows a notice listing any page that still mentions "verified human hair"
 * so it can be edited by hand.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Copy_Fix {

	const OPTION = 'nurax_copyfix_hairtype_v2'; // v2 (1.50.1): looser match for the Warranty and Bulk lines

	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/** Slugs of the pages that carried the claim. */
	private static function slugs() {
		return array( 'faq', 'about-us', 'about', 'warranty', 'bulk-corporate-orders' );
	}

	/** Regex => replacement pairs. Dashes may be stored as -, en/em dash or entities. */
	private static function rules() {
		$d     = '\s*(?:-|&#8211;|&#8212;|&ndash;|&mdash;|\x{2013}|\x{2014})\s*';
		$types = '100% human hair, human-hair blend (semi-human) or heat-resistant fibre';
		return array(
			// FAQ answer.
			'#Yes\.\s*Our premium units are verified human hair with a provenance certificate and (?:a )?written guarantee\.\s*We never sell synthetic hair as human hair\.#iu'
				=> 'We sell three types, and every product is clearly labelled with its type: 100% human hair, human-hair blend (semi-human) and heat-resistant fibre. Only units labelled 100% human hair are made from human hair. If you are unsure which type a unit is, ask us on WhatsApp before you order.',
			// About / FAQ "Honest hair" bullets (theme and plugin wording).
			'#Our premium units are verified human hair(?:,? (?:supplied )?with a provenance certificate and a written guarantee| with a written guarantee)\.\s*We never sell synthetic hair as human hair\.#iu'
				=> 'Every unit is clearly labelled as ' . $types . ', so you know exactly what you are paying for. We never sell a blend or fibre unit as human hair.',
			// About: "Every NURA wig is sewn from verified human hair ..." (any ending).
			'#Every NURA (?:wig|unit) is (?:sewn|made|crafted) from verified human hair[^.<]*\.#iu'
				=> 'Every NURA wig is clearly labelled as ' . $types . ', and each one is quality-checked before it ships.',
			// About intro sentence.
			'#Every premium unit we sell is verified human hair, hand-finished and personally curated in Nairobi\.#iu'
				=> 'We sell 100% human hair, human-hair blend and heat-resistant fibre wigs - every unit clearly labelled with its hair type, quality-checked and finished in Nairobi.',
			// Warranty lede.
			'#Every premium NURA unit is verified human hair and ships with#iu'
				=> 'Every NURA unit is sold exactly as labelled (' . $types . '), and premium units ship with',
			// Warranty bullet.
			'#<strong>Verified human hair:</strong>\s*Your unit is genuine human hair as described' . $d . 'never a synthetic substitute sold as human hair\.#iu'
				=> '<strong>Hair type as labelled:</strong> Your unit is exactly the hair type on its label - ' . $types . '. We never sell a blend or fibre unit as human hair.',
			// Bulk orders bullet.
			'#Verified human hair with provenance and guarantee' . $d . 'protect your own reputation\.#iu'
				=> 'Every unit labelled with its true hair type (100% human hair, blend or fibre) - protect your own reputation.',
			// v2: tolerant versions (any dash, non-breaking spaces, markup around the label).
			'#(?:<strong>)?\s*Verified human hair\s*:?\s*(?:</strong>)?\s*:?\s*Your unit is genuine human hair as described.*?sold as human hair\.#isu'
				=> '<strong>Hair type as labelled:</strong> Your unit is exactly the hair type on its label - ' . $types . '. We never sell a blend or fibre unit as human hair.',
			'#Verified human hair with provenance and guarantee.*?reputation\.#isu'
				=> 'Every unit labelled with its true hair type (100% human hair, blend or fibre) - protect your own reputation.',
			// Generic headline wording on About.
			'#Kenya(?:&#8217;|\x{2019}|\')s home for premium human hair wigs#iu' => 'Kenya&#8217;s home for premium wigs',
			'#house of premium <strong>human hair wigs in Kenya</strong>#iu'      => 'house of premium <strong>wigs in Kenya</strong>',
			'#<strong>Premium human hair wigs</strong>' . $d . 'lace front#iu'     => '<strong>Premium wigs</strong> - 100% human hair, blend and fibre: lace front',
		);
	}

	public function maybe_run() {
		if ( get_option( self::OPTION ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$changed = array();
		foreach ( self::slugs() as $slug ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			$content = (string) $page->post_content;
			$new     = $content;
			foreach ( self::rules() as $re => $rep ) {
				$out = preg_replace( $re, $rep, $new );
				if ( null !== $out ) {
					$new = $out;
				}
			}
			if ( $new !== $content ) {
				$res = wp_update_post( array( 'ID' => (int) $page->ID, 'post_content' => $new ), true );
				if ( $res && ! is_wp_error( $res ) ) {
					$changed[] = $page->post_title;
				}
			}
		}
		update_option( self::OPTION, array( 'time' => time(), 'changed' => $changed ), false );
	}

	public function notice() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$left = array();
		foreach ( self::slugs() as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && preg_match( '#verified human hair|real human hair\?\s*</(?:summary|h3)>\s*<p>\s*Yes#iu', (string) $page->post_content ) ) {
				$left[] = '<a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '">' . esc_html( $page->post_title ) . '</a>';
			}
		}
		if ( ! $left ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>NURA:</strong> ' . esc_html__( 'These pages still say all wigs are human hair. Please edit them so they match your product listings (human hair, blend and fibre):', 'nura-experience' ) . ' ' . implode( ', ', $left ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
}
