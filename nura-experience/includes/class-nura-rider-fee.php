<?php
/**
 * NURA Experience 1.121.0 - Nairobi rider fee calculated from the customer's location.
 *
 * Owner rules (20 Nov 2026):
 *  - Rider delivery is for Nairobi and nearby areas only.
 *  - KSh 300 within 5 km of our CBD studio; Kilimani KSh 500; Karen KSh 800;
 *    other areas KSh 300 + KSh 50 per km beyond the first 5 km (road distance
 *    from Stanbank House, rounded up to the next km).
 *  - Free delivery on orders over KSh 10,000 applies ONLY to shuttle parcels and
 *    to deliveries within 5 km of the CBD (the CBD rate and the rider rate there).
 *
 * How: on woocommerce_package_rates the shipping address (address lines + town)
 * is matched against the area table (WooCommerce > Rider delivery fees, editable).
 * The rider rate gets the area fee and a label naming the area and distance; a
 * rider rate is added for nearby areas outside Nairobi County (e.g. Ruaka,
 * Kitengela). If no area is recognised, the rider rate keeps KSh 300 and asks the
 * customer to type their estate/area. The generic Free shipping rate is removed;
 * free delivery is applied by the rules above. The area and km are saved on the
 * order's shipping line. Distances: Google driving distance from Stanbank House
 * (20 Nov 2026).
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Rider_Fee {

	const OPT  = 'nurax_rider_fee';
	const PAGE = 'nurax-rider-fees';

	public function __construct() {
		add_filter( 'woocommerce_package_rates', array( $this, 'rates' ), 130, 2 );
		add_filter( 'woocommerce_package_rates', array( $this, 'promo' ), 140, 2 ); // 1.125.0 free Nairobi delivery week.
		add_filter( 'theme_mod_nura_announcement', array( $this, 'promo_bar' ), 20 );
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_post_nurax_rider_fee_save', array( $this, 'save' ) );
	}

	/* --------------------------------------------------------------- settings */

	public static function default_table() {
		return <<<'TXT'
Nairobi CBD | 0 | cbd, nairobi cbd, city centre, city center, town centre, nairobi central, moi avenue, moi ave, tom mboya, river road, kenyatta avenue, kenyatta ave, luthuli, ronald ngala, haile selassie, koinange, muindi mbingu, biashara street, kimathi street, accra road, latema, taveta road, stanbank, gikomba, globe roundabout | 
Upper Hill | 2.9 | upper hill, upperhill | 
Westlands | 3.1 | westlands, westland | 
Parklands | 3.9 | parklands | 
Ngara | 4.2 | ngara | 
Pangani | 4.5 | pangani | 
Sarit Centre | 4.6 | sarit | 
Riverside | 4.7 | riverside | 
Madaraka | 4.8 | madaraka | 
Kilimani | 5.3 | kilimani, yaya centre, yaya center, argwings kodhek | KSh 500
South B | 5.3 | south b | 
Muthaiga | 5.4 | muthaiga | 
Eastleigh | 5.4 | eastleigh | 
South C | 5.8 | south c | 
Nyayo Highrise | 6.0 | nyayo highrise, nyayo high rise | 
Kileleshwa | 6.2 | kileleshwa | 
Nairobi West | 6.2 | nairobi west | 
Makadara | 6.3 | makadara | 
Adams Arcade | 6.6 | adams arcade, adams | 
Lavington | 6.7 | lavington | 
Hurlingham | 7.3 | hurlingham | 
Buruburu | 7.6 | buruburu, buru buru | 
Valley Arcade | 7.6 | valley arcade | 
Kibera | 7.7 | kibera, kibra | 
Huruma | 7.8 | huruma | 
Brookside | 8.1 | brookside | 
Industrial Area | 8.1 | industrial area | 
Ridgeways | 8.3 | ridgeways, ridgeway | 
Gigiri | 8.5 | gigiri | 
Jamhuri | 8.7 | jamhuri | 
Village Market | 8.9 | village market | 
Otiende | 9.4 | otiende | 
Spring Valley | 9.5 | spring valley | 
Dagoretti Corner | 9.5 | dagoretti corner, dagoretti | 
Savannah | 9.6 | savannah | 
Baba Dogo | 9.7 | baba dogo | 
Ruaraka | 9.7 | ruaraka | 
Umoja | 9.8 | umoja | 
Runda | 10.0 | runda | 
Donholm | 10.1 | donholm | 
Kariobangi | 10.1 | kariobangi | 
Garden Estate | 10.2 | garden estate | 
Kitisuru | 10.2 | kitisuru, kitusuru | 
Imara Daima | 10.4 | imara daima | 
Woodley | 10.5 | woodley | 
Mathare | 11.3 | mathare | 
Riruta | 11.4 | riruta | 
Kawangware | 11.8 | kawangware | 
Roysambu | 12.0 | roysambu | 
Kayole | 12.1 | kayole | 
Bomas | 12.2 | bomas | 
Thome | 12.3 | thome | 
Thindigua | 12.4 | thindigua | 
Kasarani | 12.5 | kasarani |  | generic
Loresho | 12.5 | loresho | 
Langata | 12.6 | langata, lang ata |  | generic
Embakasi | 12.8 | embakasi |  | generic
Two Rivers | 12.8 | two rivers | 
Rosslyn | 13.1 | rosslyn | 
Dandora | 13.4 | dandora | 
Garden City | 13.6 | garden city | 
Kangemi | 13.0 | kangemi | 
Pipeline | 13.8 | pipeline | 
Nyari | 13.8 | nyari | 
Ruaka | 14.2 | ruaka | 
Zimmerman | 14.4 | zimmerman, zimmermann | 
Kiambu Town | 14.5 | kiambu town, kiambu |  | generic
Gachie | 15.0 | gachie | 
Mirema | 15.2 | mirema | 
Lucky Summer | 15.4 | lucky summer | 
Githurai | 15.8 | githurai | 
Mountain View | 16.0 | mountain view | 
Mwiki | 16.2 | mwiki | 
Karen | 16.2 | karen, hardy, kerarapon, bogani | KSh 800
Komarock | 16.5 | komarock, koma rock | 
Tassia | 11.3 | tassia | 
JKIA | 16.9 | jkia, jomo kenyatta international airport | 
Banana Hill | 17.4 | banana hill, banana | 
Fedha | 11.2 | fedha | 
Kahawa Wendani | 18.3 | kahawa wendani, wendani | 
Kahawa West | 18.5 | kahawa west | 
Ndenderu | 18.5 | ndenderu | 
Waithaka | 18.6 | waithaka | 
Kahawa Sukari | 19.1 | kahawa sukari, sukari | 
Njiru | 19.6 | njiru | 
Kinoo | 19.9 | kinoo | 
Syokimau | 19.9 | syokimau | 
Mlolongo | 20.0 | mlolongo | 
Tatu City | 20.2 | tatu city, tatu | 
Ongata Rongai | 20.4 | ongata rongai, rongai | 
Uthiru | 20.5 | uthiru | 
Membley | 22.0 | membley | 
Kikuyu | 22.1 | kikuyu | 
Ngong Town | 23.6 | ngong town, ngong |  | generic
Ruiru | 23.7 | ruiru | 
Kabete | 24.3 | kabete |  | generic
Wangige | 24.3 | wangige | 
Mihango | 24.6 | mihango | 
Utawala | 25.1 | utawala | 
Ruai | 25.8 | ruai | 
Kamiti | 26.3 | kamiti | 
Kiserian | 27.1 | kiserian | 
Matasia | 27.7 | matasia | 
Athi River | 31.2 | athi river, mavoko | 
Kitengela | 31.5 | kitengela | 
Juja | 31.8 | juja | 
Limuru | 37.1 | limuru | 
Joska | 37.7 | joska | 
Thika | 44.2 | thika | 
TXT;
	}

	public static function settings() {
		$s = get_option( self::OPT, array() );
		$s = is_array( $s ) ? $s : array();
		return array_merge(
			array(
				'base'     => 300,
				'base_km'  => 5,
				'per_km'   => 50,
				'free_min' => 10000,
				'max_km'   => 45,
				'table'    => self::default_table(),
			),
			$s
		);
	}

	/** Parsed table: list of [name, km, aliases[], fixed|null, generic]. */
	public static function areas() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::settings()['table'] ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) );
			if ( count( $p ) < 2 || '' === $p[0] || 0 === strpos( $p[0], '#' ) ) {
				continue;
			}
			$aliases = array();
			foreach ( explode( ',', isset( $p[2] ) && '' !== $p[2] ? $p[2] : $p[0] ) as $a ) {
				$a = self::norm( $a );
				if ( '' !== $a ) {
					$aliases[] = $a;
				}
			}
			$fixed = null;
			if ( isset( $p[3] ) && preg_match( '/(\d[\d,]*)/', $p[3], $m ) ) {
				$fixed = (float) str_replace( ',', '', $m[1] );
			}
			$out[] = array( $p[0], (float) $p[1], $aliases, $fixed, isset( $p[4] ) && false !== stripos( $p[4], 'generic' ) );
		}
		$cache = $out;
		return $out;
	}

	public static function norm( $s ) {
		$s = strtolower( remove_accents( (string) $s ) );
		$s = str_replace( array( "'", '`', "\u{2019}" ), ' ', $s );
		$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}

	/** Best area for a free-text location, or null. */
	public static function match( $text ) {
		$t = ' ' . self::norm( $text ) . ' ';
		if ( '  ' === $t ) {
			return null;
		}
		$best  = null;
		$score = -1;
		foreach ( self::areas() as $a ) {
			foreach ( $a[2] as $alias ) {
				$re = '/(?<![a-z0-9])' . preg_quote( $alias, '/' ) . '(?![a-z0-9])(?!\s+(?:road|rd|avenue|ave|street|st|drive|dr|way|lane|ln|highway|hwy|bypass|by pass)\b)/';
				if ( preg_match( $re, $t ) ) {
					$sc = ( $a[4] ? 0 : 1000 ) + strlen( $alias ); // A specific area always beats a generic one (Ruaka beats Kiambu).
					if ( $sc > $score ) {
						$score = $sc;
						$best  = $a;
					}
				}
			}
		}
		return $best;
	}

	/** Fee for an area: [fee, km, name]. */
	public static function fee_for( $area ) {
		$s  = self::settings();
		$km = (float) $area[1];
		if ( null !== $area[3] ) {
			$fee = (float) $area[3];
		} elseif ( $km <= (float) $s['base_km'] ) {
			$fee = (float) $s['base'];
		} else {
			$fee = (float) $s['base'] + (float) $s['per_km'] * ceil( $km - (float) $s['base_km'] - 1e-9 );
		}
		return array( $fee, $km, $area[0] );
	}

	/**
	 * Quote for free text (WhatsApp order pop-up): null when no area is recognised.
	 * Same rules and table as checkout.
	 */
	public static function quote( $text, $sub = 0 ) {
		$s    = self::settings();
		$area = self::match( $text );
		if ( ! $area ) {
			return null;
		}
		list( $fee, $km, $name ) = self::fee_for( $area );
		if ( $km > (float) $s['max_km'] ) {
			return null;
		}
		$within = $km <= (float) $s['base_km'];
		$free   = $within && (float) $s['free_min'] > 0 && (float) $sub >= (float) $s['free_min'];
		if ( null !== $area[3] ) {
			$rule = $name . ' fixed rider fee ' . self::money( $area[3] );
		} elseif ( $within ) {
			$rule = 'Within ' . $s['base_km'] . ' km of the CBD: ' . self::money( $s['base'] );
		} else {
			$extra = (int) ceil( $km - (float) $s['base_km'] - 1e-9 );
			$rule  = self::money( $s['base'] ) . ' + ' . $extra . ' km x ' . self::money( $s['per_km'] ) . ' = ' . self::money( $fee );
		}
		return array(
			'known'     => true,
			'rider'     => true,
			'fee'       => $free ? 0 : $fee,
			'full_fee'  => $fee,
			'area'      => $name,
			'km'        => round( $km, 1 ),
			'within'    => $within,
			'free_over' => $within && ! $free ? (float) $s['free_min'] : 0,
			'rule'      => $rule . ( $free ? ' - free on orders over ' . self::money( $s['free_min'] ) : '' ),
		);
	}

	/* ------------------------------------------------------------------ promo */

	/**
	 * 1.125.0 - Owner offer: free Nairobi delivery this week only (Mon 23 Nov to Sun 29 Nov 2026,
	 * Nairobi time). Rider delivery inside Nairobi County and the Nairobi CBD rate cost KSh 0
 * (1.125.1: towns outside the county, e.g. Ruaka and Kitengela, keep their normal rider fee).
	 * Shuttle/courier countrywide fees are unchanged. Window filter: nurax_free_nairobi_window
	 * (array of two 'Y-m-d H:i:s' Africa/Nairobi times); off: return array().
	 */
	public static function promo_window() {
		return (array) apply_filters( 'nurax_free_nairobi_window', array( '2026-11-23 00:00:00', '2026-11-29 23:59:59' ) );
	}

	public static function promo_on() {
		$w = self::promo_window();
		if ( count( $w ) < 2 ) {
			return false;
		}
		try {
			$tz  = new DateTimeZone( 'Africa/Nairobi' );
			$now = new DateTime( 'now', $tz );
			return $now >= new DateTime( $w[0], $tz ) && $now <= new DateTime( $w[1], $tz );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/** Areas in the rider table that are outside Nairobi County (normal fee during the offer). */
	public static function promo_outside_county() {
		return (array) apply_filters( 'nurax_free_nairobi_outside', array(
			'Ruaka', 'Kiambu Town', 'Gachie', 'Banana Hill', 'Ndenderu', 'Thindigua', 'Kahawa Wendani',
			'Kahawa Sukari', 'Kinoo', 'Syokimau', 'Mlolongo', 'Tatu City', 'Ongata Rongai', 'Uthiru',
			'Membley', 'Kikuyu', 'Ngong Town', 'Ruiru', 'Kabete', 'Wangige', 'Kamiti', 'Kiserian',
			'Matasia', 'Athi River', 'Kitengela', 'Juja', 'Limuru', 'Joska', 'Thika',
		) );
	}

	public static function promo_in_county( $area ) {
		foreach ( self::promo_outside_county() as $out ) {
			if ( 0 === strcasecmp( trim( (string) $area ), $out ) ) {
				return false;
			}
		}
		return true;
	}

	public static function promo_text() {
		return 'FREE delivery in Nairobi County this week only, until Sunday 29 November';
	}

	public function promo( $rates, $package ) {
		if ( ! is_array( $rates ) || ! self::promo_on() ) {
			return $rates;
		}
		foreach ( $rates as $id => $rate ) {
			if ( ! is_object( $rate ) ) {
				continue;
			}
			$label = (string) $rate->get_label();
			$cbd   = function_exists( 'nura_is_cbd_rate' ) ? nura_is_cbd_rate( $id, $label ) : (bool) preg_match( '/\bcbd\b/i', $label );
			if ( $cbd || self::is_rider( $rate ) ) {
				if ( ! $cbd ) {
					// 1.125.1: Nairobi County only. Recognised areas outside the county (Ruaka,
					// Kitengela, Rongai...) and unrecognised areas keep their normal rider fee.
					$meta = $rate->get_meta_data();
					$area = isset( $meta['Delivery area'] ) ? (string) $meta['Delivery area'] : '';
					if ( '' === $area || 0 === strpos( $area, 'Not recognised' ) ) {
						$rate->set_label( $label . ' (free delivery this week applies in Nairobi County: type your estate to check)' );
						continue;
					}
					if ( ! self::promo_in_county( $area ) ) {
						continue;
					}
				}
				self::set_cost( $rate, 0 );
				$label = preg_replace( '/ - (FREE|free) on orders over KSh [0-9,]+/', '', $label );
				$label = preg_replace( '/\. Type your estate or area in the address to see your exact fee/', '', $label );
				$label = preg_replace( '/ - from KSh [0-9,]+/', '', $label );
				$rate->set_label( $label . ' - ' . self::promo_text() );
				$rate->add_meta_data( 'Offer', self::promo_text() );
			}
		}
		return $rates;
	}

	public function promo_bar( $text ) {
		return self::promo_on() ? self::promo_text() . ' - same-day delivery on orders before 5 PM, pay with M-Pesa or card' : $text;
	}

	/* ------------------------------------------------------------------ rates */

	private static function is_rider( $rate ) {
		$label = (string) $rate->get_label();
		$cbd   = function_exists( 'nura_is_cbd_rate' ) ? nura_is_cbd_rate( $rate->get_id(), $label ) : (bool) preg_match( '/\bcbd\b/i', $label );
		return ! $cbd && ( 'nura_rider' === $rate->get_method_id() || preg_match( '/\brider\b/i', $label ) );
	}

	private static function set_cost( $rate, $cost ) {
		$rate->set_cost( (string) max( 0, (float) $cost ) );
		$taxes = array();
		if ( function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() && $cost > 0 && class_exists( 'WC_Tax' ) && 'taxable' === get_option( 'woocommerce_shipping_tax_class_never', 'taxable' ) ) {
			$taxes = WC_Tax::calc_shipping_tax( $cost, WC_Tax::get_shipping_tax_rates() );
		}
		$rate->set_taxes( $taxes );
	}

	private static function money( $n ) {
		return 'KSh ' . number_format( (float) $n, 0 );
	}

	public function rates( $rates, $package ) {
		if ( ! is_array( $rates ) || ! apply_filters( 'nurax_rider_fee_on', true ) ) {
			return $rates;
		}
		$s    = self::settings();
		$dest = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		if ( isset( $dest['country'] ) && '' !== $dest['country'] && 'KE' !== $dest['country'] ) {
			return $rates;
		}
		$text = implode( ' ', array_filter( array(
			isset( $dest['address'] ) ? $dest['address'] : ( isset( $dest['address_1'] ) ? $dest['address_1'] : '' ),
			isset( $dest['address_2'] ) ? $dest['address_2'] : '',
			isset( $dest['city'] ) ? $dest['city'] : '',
		) ) );
		$area = self::match( $text );
		$sub  = isset( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0;
		$free = (float) $s['free_min'] > 0 && $sub >= (float) $s['free_min'];

		$in_nairobi = isset( $dest['state'] ) && in_array( strtoupper( (string) $dest['state'] ), array( 'KE30', '30', 'NAIROBI' ), true );
		$rider_id   = null;
		foreach ( $rates as $id => $rate ) {
			if ( ! is_object( $rate ) ) {
				continue;
			}
			if ( 'free_shipping' === $rate->get_method_id() ) {
				unset( $rates[ $id ] ); // Free delivery is applied by the rules below instead.
				continue;
			}
			if ( null === $rider_id && self::is_rider( $rate ) ) {
				$rider_id = $id;
			}
		}

		$fee = null;
		if ( $area ) {
			list( $fee, $km, $name ) = self::fee_for( $area );
			if ( $km > (float) $s['max_km'] ) {
				$area = null;
				$fee  = null;
			}
		}

		// Nearby areas outside Nairobi County: add a rider rate when none exists.
		if ( null === $rider_id && $area && class_exists( 'WC_Shipping_Rate' ) ) {
			$r = new WC_Shipping_Rate( 'nura_rider:1', 'Rider delivery', 0, array(), 'nura_rider', 1 );
			$rates     = array( 'nura_rider:1' => $r ) + $rates;
			$rider_id  = 'nura_rider:1';
		}

		if ( null !== $rider_id ) {
			$rate = $rates[ $rider_id ];
			if ( $area ) {
				$within = $km <= (float) $s['base_km'];
				$cost   = ( $free && $within ) ? 0 : $fee;
				self::set_cost( $rate, $cost );
				if ( $within ) {
					$where = 'Nairobi CBD' === $name ? 'Nairobi CBD' : $name . ' (' . number_format( $km, 1 ) . ' km)';
					$label = 'Rider delivery to ' . $where . ' - within 5 km of CBD, pay on delivery or online';
				} else {
					// No "CBD" in this label: pay on delivery is only for deliveries within 5 km.
					$label = 'Rider delivery to ' . $name . ' (' . number_format( $km, 1 ) . ' km from our studio) - pay before dispatch';
				}
				if ( 0.0 === (float) $cost ) {
					$label .= ' - FREE on orders over ' . self::money( $s['free_min'] );
				}
				$rate->set_label( $label );
				$rate->add_meta_data( 'Delivery area', $name );
				$rate->add_meta_data( 'Distance from studio', number_format( $km, 1 ) . ' km' );
				$rate->add_meta_data( 'Rider fee rule', null !== $area[3] ? 'Fixed area fee ' . self::money( $area[3] ) : ( $within ? self::money( $s['base'] ) . ' within ' . $s['base_km'] . ' km' : self::money( $s['base'] ) . ' + ' . self::money( $s['per_km'] ) . ' per km beyond ' . $s['base_km'] . ' km' ) );
			} else {
				self::set_cost( $rate, (float) $s['base'] );
				$rate->set_label( 'Rider delivery (Nairobi and nearby) - from ' . self::money( $s['base'] ) . '. Type your estate or area in the address to see your exact fee' );
				$rate->add_meta_data( 'Delivery area', 'Not recognised - confirm fee on WhatsApp' );
			}
		}

		foreach ( $rates as $id => $rate ) {
			if ( ! is_object( $rate ) || $id === $rider_id ) {
				continue;
			}
			$label = (string) $rate->get_label();
			$cbd   = function_exists( 'nura_is_cbd_rate' ) ? nura_is_cbd_rate( $id, $label ) : (bool) preg_match( '/\bcbd\b/i', $label );
			if ( $cbd ) {
				// Area recognised: the rider rate above already covers it (within 5 km it allows
				// pay on delivery), so drop the duplicate zone CBD rate.
				if ( $area && null !== $rider_id ) {
					unset( $rates[ $id ] );
					continue;
				}
				if ( $free ) {
					self::set_cost( $rate, 0 );
				}
				continue;
			}
			if ( $free && preg_match( '/shuttle|\bbus\b/i', $label ) ) {
				self::set_cost( $rate, 0 );
				if ( false === stripos( $label, 'free' ) ) {
					$rate->set_label( $label . ' - free on orders over ' . self::money( $s['free_min'] ) );
				}
			}
		}
		return $rates;
	}

	/* ------------------------------------------------------------------ admin */

	public function menu() {
		add_submenu_page( 'woocommerce', 'Rider delivery fees', 'Rider delivery fees', 'manage_woocommerce', self::PAGE, array( $this, 'page' ) );
	}

	public function page() {
		$s = self::settings();
		echo '<div class="wrap"><h1>Rider delivery fees</h1>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		}
		echo '<p>Checkout reads the customer\'s address and town, finds their area below and sets the rider fee. Fee = base within the base distance; otherwise base + per-km for every km beyond it (rounded up). A fixed fee (e.g. <code>KSh 500</code>) overrides the formula. Free delivery over the minimum applies only within the base distance and to shuttle parcels.</p>';
		echo '<p><strong>Try an address:</strong> ';
		$test = isset( $_GET['test'] ) ? sanitize_text_field( wp_unslash( $_GET['test'] ) ) : ''; // phpcs:ignore
		echo '<form method="get" style="display:inline"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="text" name="test" value="' . esc_attr( $test ) . '" placeholder="e.g. Kileleshwa, Othaya Road" class="regular-text"> <button class="button">Check fee</button></form>';
		if ( '' !== $test ) {
			$a = self::match( $test );
			if ( $a ) {
				list( $fee, $km, $name ) = self::fee_for( $a );
				echo '<br>' . esc_html( $name . ' - ' . number_format( $km, 1 ) . ' km - rider fee ' . self::money( $fee ) );
			} else {
				echo '<br>No area recognised - checkout shows ' . esc_html( self::money( $s['base'] ) ) . ' and asks for the estate/area.';
			}
		}
		echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nurax_rider_fee_save">';
		wp_nonce_field( 'nurax_rider_fee_save' );
		echo '<table class="form-table">';
		foreach ( array( 'base' => 'Base fee (KSh)', 'base_km' => 'Base distance (km)', 'per_km' => 'Per km beyond base (KSh)', 'free_min' => 'Free delivery minimum (KSh)', 'max_km' => 'Furthest rider distance (km)' ) as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input type="number" step="0.1" min="0" name="s[' . esc_attr( $k ) . ']" value="' . esc_attr( $s[ $k ] ) . '"></td></tr>';
		}
		echo '<tr><th>Areas</th><td><p class="description">One per line: <code>Area | km | aliases, comma separated | fixed fee (optional) | generic (optional, loses ties)</code></p><textarea name="s[table]" rows="24" class="large-text code">' . esc_textarea( $s['table'] ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button( 'Save rider fees' );
		echo '</form></div>';
	}

	public function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'nurax_rider_fee_save' );
		$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore
		$out = array();
		foreach ( array( 'base', 'base_km', 'per_km', 'free_min', 'max_km' ) as $k ) {
			if ( isset( $in[ $k ] ) && '' !== $in[ $k ] ) {
				$out[ $k ] = max( 0, (float) $in[ $k ] );
			}
		}
		if ( isset( $in['table'] ) ) {
			$out['table'] = sanitize_textarea_field( $in['table'] );
		}
		update_option( self::OPT, $out, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&saved=1' ) );
		exit;
	}
}
