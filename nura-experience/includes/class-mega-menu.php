<?php
/**
 * NURA Mega Menu + Mobile Bottom Navigation.
 *
 * Replaces the small default header navigation with a full, catalogue-driven
 * mega menu (Wigs / Hair & Extensions / Beauty / Wig Care / Services / New In /
 * Sale / NURA) and adds a sticky mobile bottom navigation bar plus a nested
 * mobile drawer menu.
 *
 * The structure is defined in code so the complete NURA information
 * architecture is live immediately, before any WooCommerce category is
 * populated. Every category link resolves to its product-category archive when
 * that category exists and falls back to the shop page otherwise, so no link is
 * ever broken. Once you assign your own WordPress menu to the "primary"
 * location this code menu steps aside (it only renders when the location has no
 * assigned menu).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Mega_Menu {

	public function __construct() {
		add_filter( 'pre_wp_nav_menu', array( $this, 'maybe_render' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'bottom_nav' ), 20 );
	}

	/** Shop page URL. */
	public static function shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'shop' );
		}
		return home_url( '/shop/' );
	}

	/** Resolve a product-category URL by slug, falling back to the shop page. */
	public static function cat_url( $slug ) {
		if ( taxonomy_exists( 'product_cat' ) ) {
			$term = get_term_by( 'slug', sanitize_title( $slug ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					return $link;
				}
			}
		}
		return self::shop_url();
	}

	/** Resolve a page URL by slug, falling back to the home page. */
	public static function page_url( $slug ) {
		$page = get_page_by_path( sanitize_title( $slug ) );
		if ( $page ) {
			return get_permalink( $page );
		}
		return home_url( '/' . sanitize_title( $slug ) . '/' );
	}

	/**
	 * The mega-menu structure with resolved URLs.
	 */
	public static function structure() {
		$shop = self::shop_url();

		return array(
			'Home' => array(
				'url' => home_url( '/' ),
			),
			'Shop' => array(
				'url'     => $shop,
				'columns' => array(
					'Shop Wigs' => array(
						'All Wigs'          => self::cat_url( 'wigs' ),
						'New In'            => add_query_arg( 'orderby', 'date', $shop ),
						'Best Sellers'      => add_query_arg( 'orderby', 'popularity', $shop ),
						'Bridal & Occasion' => self::cat_url( 'bridal-occasion' ),
					),
					'By Hair Type' => array(
						'100% Human Hair'  => self::cat_url( 'human-hair-wigs' ),
						'Human-Hair Blend' => self::cat_url( 'human-hair-blend-wigs' ),
						'Curly Wigs'       => self::cat_url( 'curly-wigs' ),
						'Jerry Curl Wigs'  => self::cat_url( 'jerry-curl-wigs' ),
					),
					'By Construction' => array(
						'Lace Front Wigs' => self::cat_url( 'lace-front-wigs' ),
						'Closure Wigs'    => self::cat_url( 'closure-wigs' ),
						'Headband Wigs'   => self::cat_url( 'headband-wigs' ),
					),
					'By Style & Texture' => array(
						'Bob Wigs'           => self::cat_url( 'bob-wigs' ),
						'Pixie Wigs'         => self::cat_url( 'pixie-wigs' ),
						'Body Wave Wigs'     => self::cat_url( 'body-wave-wigs' ),
						'Afro Wigs'          => self::cat_url( 'afro-wigs' ),
						'Bone Straight Wigs' => self::cat_url( 'bone-straight-wigs' ),
					),
					'More from NURA' => array(
						'Hair & Extensions' => self::cat_url( 'hair-extensions' ),
						'Wig Care'          => self::cat_url( 'wig-care' ),
						'Beauty'            => self::cat_url( 'beauty' ),
					),
				),
				'feature' => array(
					'eyebrow' => 'Not sure what suits you?',
					'title'   => 'Find your perfect wig',
					'cta'     => 'Take the quick quiz',
					'url'     => self::page_url( 'ai-wig-finder' ),
					'img'     => defined( 'NURA_URI' ) ? NURA_URI . 'assets/images/cats/pillar-wigs.webp' : '',
				),
				'budget' => array(
					'Under KSh 3,000'      => add_query_arg( array( 'min_price' => 0, 'max_price' => 3000 ), $shop ),
					'KSh 3,000 - 7,000'    => add_query_arg( array( 'min_price' => 3000, 'max_price' => 7000 ), $shop ),
					'KSh 7,000 - 15,000'   => add_query_arg( array( 'min_price' => 7000, 'max_price' => 15000 ), $shop ),
					'KSh 15,000+'          => add_query_arg( array( 'min_price' => 15000 ), $shop ),
				),
			),
			'Installation' => array(
				'url' => self::page_url( 'installation' ),
			),
			'About' => array(
				'url'   => self::page_url( 'about-us' ),
				'links' => array(
					'About Us'        => self::page_url( 'about-us' ),
					'AI Wig Finder'   => self::page_url( 'ai-wig-finder' ),
					'The NURA Circle' => self::page_url( 'nura-circle' ),
					'Journal'         => self::page_url( 'journal' ),
				),
			),
			'Book Appointment' => array(
				'url' => self::page_url( 'book-appointment' ),
			),
			'Contact' => array(
				'url' => self::page_url( 'contact-us' ),
			),
		);
	}

	/**
	 * Short-circuit wp_nav_menu for the primary + mobile locations when no menu
	 * is assigned there. Returning a string replaces the default output; returning
	 * $output leaves WordPress to render an assigned menu normally.
	 */
	public function maybe_render( $output, $args ) {
		$args = (object) $args;
		$location = isset( $args->theme_location ) ? $args->theme_location : '';

		if ( 'primary' !== $location && 'mobile' !== $location ) {
			return $output;
		}

		// The built-in NURA mega menu is the canonical navigation. It renders
		// even when a menu is assigned under Appearance -> Menus. To fall back to
		// an assigned WordPress menu instead, disable it with:
		//   add_filter( 'nurax_use_code_menu', '__return_false' );
		if ( ! apply_filters( 'nurax_use_code_menu', true, $location ) ) {
			$locations = get_nav_menu_locations();
			if ( ! empty( $locations[ $location ] ) ) {
				return $output;
			}
		}

		return ( 'mobile' === $location ) ? $this->render_mobile() : $this->render_desktop();
	}

	/** v1.51.0 True when $url is the page being viewed (or, for sections, an ancestor of it). */
	private static function is_current( $url, $exact = false ) {
		$req  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$req  = trailingslashit( $req );
		$path = trailingslashit( $path );
		if ( '/' === $path || $exact ) {
			return $req === $path;
		}
		return 0 === strpos( $req, $path );
	}

	/** v1.51.0 Desktop mega menu: full-width panel, grouped columns, feature card, budget bar. */
	public function render_desktop() {
		$html = '<ul id="primary-menu" class="nura-mega">';
		foreach ( self::structure() as $label => $node ) {
			$wide      = ! empty( $node['columns'] );
			$has_panel = $wide || ! empty( $node['links'] );
			$cls       = 'nura-mega__item' . ( $has_panel ? ' menu-item-has-children has-mega' : '' ) . ( $wide ? ' has-mega--wide' : '' );
			if ( self::is_current( $node['url'], 'Home' === $label ) ) {
				$cls .= ' current-menu-ancestor';
			}
			$html .= '<li class="' . esc_attr( $cls ) . '">';
			$html .= '<a href="' . esc_url( $node['url'] ) . '"' . ( $has_panel ? ' aria-haspopup="true"' : '' ) . '>' . esc_html( $label ) . '</a>';

			if ( $wide ) {
				$html .= '<div class="nura-mega__panel nura-mega__panel--wide" role="region" aria-label="' . esc_attr( sprintf( '%s menu', $label ) ) . '">';
				$html .= '<div class="nura-mega__body"><div class="nura-mega__cols">';
				foreach ( $node['columns'] as $col_title => $links ) {
					$html .= '<div class="nura-mega__col"><p class="nura-mega__h">' . esc_html( $col_title ) . '</p><ul>';
					foreach ( $links as $name => $url ) {
						$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
					}
					$html .= '</ul></div>';
				}
				$html .= '</div>';
				if ( ! empty( $node['feature'] ) ) {
					$f     = $node['feature'];
					$style = ! empty( $f['img'] ) ? ' style="background-image:url(\'' . esc_url( $f['img'] ) . '\')"' : '';
					$html .= '<a class="nura-mega__feature' . ( ! empty( $f['img'] ) ? ' has-img' : '' ) . '" href="' . esc_url( $f['url'] ) . '"' . $style . '>'
						. '<span class="nura-mega__feature-eyebrow">' . esc_html( $f['eyebrow'] ) . '</span>'
						. '<span class="nura-mega__feature-title">' . esc_html( $f['title'] ) . '</span>'
						. '<span class="nura-mega__feature-cta">' . esc_html( $f['cta'] ) . ' &#8594;</span></a>';
				}
				$html .= '</div>';
				if ( ! empty( $node['budget'] ) ) {
					$html .= '<div class="nura-mega__foot"><span class="nura-mega__foot-h">Shop by budget</span><ul class="nura-mega__chips">';
					foreach ( $node['budget'] as $name => $url ) {
						$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
					}
					$html .= '</ul><a class="nura-mega__all" href="' . esc_url( $node['url'] ) . '">View all products &#8594;</a></div>';
				}
				$html .= '</div>';
			} elseif ( ! empty( $node['links'] ) ) {
				$html .= '<div class="nura-mega__panel nura-mega__panel--simple"><ul>';
				foreach ( $node['links'] as $name => $url ) {
					$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
				}
				$html .= '</ul></div>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/** v1.51.0 Mobile drawer: tap a section to open it; links grouped under the same headings as desktop. */
	public function render_mobile() {
		$html = '<ul class="nura-drawer-menu">';
		foreach ( self::structure() as $label => $node ) {
			$groups = array();
			if ( ! empty( $node['columns'] ) ) {
				$groups = $node['columns'];
				if ( ! empty( $node['budget'] ) ) {
					$groups['Shop by budget'] = $node['budget'];
				}
			} elseif ( ! empty( $node['links'] ) ) {
				$groups = array( '' => $node['links'] );
			}
			if ( ! $groups ) {
				$html .= '<li><a href="' . esc_url( $node['url'] ) . '">' . esc_html( $label ) . '</a></li>';
				continue;
			}
			$html .= '<li class="nura-drawer-menu__group"><details><summary>' . esc_html( $label ) . '</summary><div class="nura-drawer-menu__sub">';
			$html .= '<a class="nura-drawer-menu__all" href="' . esc_url( $node['url'] ) . '">' . esc_html( 'Shop' === $label ? 'View all products' : sprintf( 'All of %s', $label ) ) . '</a>';
			foreach ( $groups as $title => $links ) {
				if ( '' !== $title ) {
					$html .= '<p class="nura-drawer-menu__h">' . esc_html( $title ) . '</p>';
				}
				$html .= '<ul>';
				foreach ( $links as $name => $url ) {
					$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
				}
				$html .= '</ul>';
			}
			$html .= '</div></details></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/** Sticky mobile bottom navigation bar. */
	public function bottom_nav() {
		if ( is_admin() ) {
			return;
		}
		$home    = home_url( '/' );
		$shop    = self::shop_url();
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
		$cart    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		$count   = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$count = WC()->cart->get_cart_contents_count();
		}

		$ico_home  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/></svg>';
		$ico_shop  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h2l2.4 12.2a1 1 0 001 .8h8.2a1 1 0 001-.8L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>';
		$ico_heart = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 20s-7-4.4-9.3-8.6C1.2 8.6 2.6 5.5 5.7 5.5c1.9 0 3.1 1.1 3.9 2.3.8-1.2 2-2.3 3.9-2.3 3.1 0 4.5 3.1 3 5.9C19 15.6 12 20 12 20z"/></svg>';
		$ico_bag   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 016 0v2"/></svg>';
		$ico_menu  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';

		echo '<nav class="nura-bottom-nav" aria-label="' . esc_attr__( 'Mobile navigation', 'nura-experience' ) . '">';
		// v1.40.0: Home | Shop | WhatsApp | Cart | Menu. Search lives in the header
		// bar on every page, so the thumb zone goes to the actions Kenyan shoppers
		// use most: going home, browsing, and chatting on WhatsApp.
		$wa_raw = (string) get_theme_mod( 'nura_whatsapp', '' );
		$wa_url = $wa_raw ? $wa_raw : 'https://wa.me/254714994898';
		$wa_url = add_query_arg( 'text', rawurlencode( __( 'Hi NURA, I need help choosing a wig.', 'nura-experience' ) ), $wa_url );
		$ico_wa = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>';
		$is_home = is_front_page();
		$is_shop = function_exists( 'is_woocommerce' ) && ( is_shop() || is_product_taxonomy() || is_product() );
		echo '<a class="nbn-home' . ( $is_home ? ' is-active' : '' ) . '" href="' . esc_url( $home ) . '"' . ( $is_home ? ' aria-current="page"' : '' ) . '><span class="nbn-i">' . $ico_home . '</span><span class="nbn-t">' . esc_html__( 'Home', 'nura-experience' ) . '</span></a>';
		echo '<a class="nbn-shop' . ( $is_shop ? ' is-active' : '' ) . '" href="' . esc_url( $shop ) . '"><span class="nbn-i">' . $ico_shop . '</span><span class="nbn-t">' . esc_html__( 'Shop', 'nura-experience' ) . '</span></a>';
		echo '<a class="nbn-wa" href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener"><span class="nbn-i">' . $ico_wa . '</span><span class="nbn-t">' . esc_html__( 'WhatsApp', 'nura-experience' ) . '</span></a>';
		echo '<a class="nbn-cart" href="' . esc_url( $cart ) . '"><span class="nbn-i">' . $ico_bag . '</span><span class="nbn-t">' . esc_html__( 'Cart', 'nura-experience' ) . '</span>' . ( $count ? '<span class="nbn-badge">' . esc_html( $count ) . '</span>' : '' ) . '</a>';
		echo '<button type="button" class="nbn-menu" data-nura-drawer aria-label="' . esc_attr__( 'Open menu', 'nura-experience' ) . '"><span class="nbn-i">' . $ico_menu . '</span><span class="nbn-t">' . esc_html__( 'Menu', 'nura-experience' ) . '</span></button>';
		echo '</nav>';
	}
}
