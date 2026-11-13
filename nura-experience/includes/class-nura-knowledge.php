<?php
/**
 * NURA customer-question engine (plugin 1.81.0).
 *
 * One shared brain for "what customers actually ask": matches a message in
 * English, Kiswahili or Sheng to the closest question in data/wig-questions.php,
 * returns NURA's honest answer (live prices and policies filled in) and three
 * real products from the live WooCommerce catalogue that fit the answer and the
 * shopper's own words (budget, hair type, construction, texture, colour, length).
 *
 * Used by: the website NURA Stylist chat (guided mode and AI mode), the NURA AI
 * brain (website + WhatsApp bot system prompt), the theme's /wig-questions/ hub,
 * "Ask the stylist" chips and [nura_stylist_picks]. WooCommerce stays the source
 * of truth for names, prices, variations and stock; this file only explains.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

final class NURAX_Knowledge {

	/** @var array|null */
	private static $kb = null;

	/** Knowledge base (filterable). */
	public static function kb() {
		if ( null === self::$kb ) {
			$file     = dirname( __DIR__ ) . '/data/wig-questions.php';
			$data     = file_exists( $file ) ? include $file : array();
			self::$kb = (array) apply_filters( 'nurax_knowledge_base', is_array( $data ) ? $data : array() );
		}
		return self::$kb;
	}

	/** Intent groups. */
	public static function categories() {
		$kb = self::kb();
		return isset( $kb['cats'] ) ? (array) $kb['cats'] : array();
	}

	/* ------------------------------------------------------------ catalogue */

	/** Hair group of a product: human | blend | fibre. */
	private static function hair_of( $p ) {
		$t = strtolower( trim( (string) $p->get_attribute( 'pa_hair-type' ) ) . ' ' . $p->get_name() );
		if ( false !== strpos( $t, 'blend' ) || false !== strpos( $t, 'semi' ) ) {
			return 'blend';
		}
		if ( false !== strpos( $t, 'human' ) ) {
			return 'human';
		}
		return 'fibre';
	}

	/** Cached live catalogue rows used for answers and picks. */
	public static function catalogue() {
		$key  = 'nurax_kb_rows_' . (int) get_option( 'nura_seo_cache_v', 1 );
		$rows = get_transient( $key );
		if ( is_array( $rows ) ) {
			return $rows;
		}
		$rows = array();
		if ( function_exists( 'wc_get_products' ) ) {
			foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => 300, 'visibility' => 'catalog' ) ) as $p ) {
				$lo = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $p );
				if ( $lo <= 0 ) {
					continue;
				}
				$attrs = array();
				foreach ( array( 'pa_construction', 'pa_texture', 'pa_length', 'pa_style', 'pa_occasion', 'pa_colour', 'pa_lace-type' ) as $a ) {
					$attrs[] = (string) $p->get_attribute( $a );
				}
				$rows[] = array(
					'id'    => $p->get_id(),
					'name'  => wp_strip_all_tags( $p->get_name() ),
					'price' => $lo,
					'hair'  => self::hair_of( $p ),
					'text'  => strtolower( wp_strip_all_tags( $p->get_name() ) . ' | ' . implode( ' | ', $attrs ) ),
					'stock' => $p->is_in_stock(),
				);
			}
		}
		set_transient( $key, $rows, 6 * HOUR_IN_SECONDS );
		return $rows;
	}

	private static function ksh( $n ) {
		return 'KSh ' . number_format( (float) $n, 0, '.', ',' );
	}

	/** Live values for answer tokens. */
	public static function tokens() {
		static $t = null;
		if ( null !== $t ) {
			return $t;
		}
		$by = array( 'human' => array(), 'blend' => array(), 'fibre' => array() );
		$all = array();
		foreach ( self::catalogue() as $r ) {
			$by[ $r['hair'] ][] = $r['price'];
			$all[]              = $r['price'];
		}
		$f    = function ( $k, $d = '' ) {
			return function_exists( 'nura_fact' ) ? (string) nura_fact( $k, $d ) : $d;
		};
		$rate = function_exists( 'nura_seo_ke_countrywide_rate' ) ? nura_seo_ke_countrywide_rate() : null;
		$t    = array(
			'{fibre_min}'   => $by['fibre'] ? self::ksh( min( $by['fibre'] ) ) : 'our lowest price',
			'{fibre_max}'   => $by['fibre'] ? self::ksh( max( $by['fibre'] ) ) : '',
			'{blend_min}'   => $by['blend'] ? self::ksh( min( $by['blend'] ) ) : 'a mid-range price',
			'{human_min}'   => $by['human'] ? self::ksh( min( $by['human'] ) ) : '',
			'{human_max}'   => $by['human'] ? self::ksh( max( $by['human'] ) ) : '',
			'{price_min}'   => $all ? self::ksh( min( $all ) ) : '',
			'{price_max}'   => $all ? self::ksh( max( $all ) ) : '',
			'{address}'     => $f( 'address_short', 'Stanbank House, 2nd Floor, Room 20, Nairobi CBD' ) . ', Moi Avenue',
			'{hours}'       => $f( 'hours_long', 'Monday to Saturday, 9:00am to 7:30pm' ),
			'{cutoff}'      => $f( 'cutoff', '5 PM' ),
			'{till}'        => $f( 'till', '1679269' ),
			'{returns}'     => $f( 'returns_text', 'Unworn, unaltered wigs can be returned within 48 hours of delivery.' ),
			'{delivery_ke}' => $rate ? self::ksh( $rate ) : 'charged at the rate shown at checkout',
			'{rider}'       => 'KSh 300',
			'{free_over}'   => 'KSh 10,000',
		);
		$t = (array) apply_filters( 'nurax_knowledge_tokens', $t );
		return $t;
	}

	/** Answer text with tokens filled. */
	public static function fill( $text ) {
		$out = strtr( (string) $text, self::tokens() );
		return trim( preg_replace( '/\s+/', ' ', str_replace( array( ' to .', ' to ,' ), array( '.', ',' ), $out ) ) );
	}

	/* --------------------------------------------------------------- match */

	/** Translate Kiswahili / Sheng phrases into English keywords. */
	public static function normalise( $text ) {
		$t  = ' ' . strtolower( wp_strip_all_tags( (string) $text ) ) . ' ';
		$kb = self::kb();
		$sw = isset( $kb['swahili'] ) ? (array) $kb['swahili'] : array();
		uksort( $sw, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );
		foreach ( $sw as $from => $to ) {
			if ( false !== strpos( $t, ' ' . $from . ' ' ) || ( strlen( $from ) > 5 && false !== strpos( $t, $from ) ) ) {
				$t .= ' ' . $to . ' ';
			}
		}
		$t = preg_replace( '/\b(\d{1,2})\s*k\b/', '$1000 under', $t );
		return preg_replace( '/[^a-z0-9\s\-]/', ' ', $t );
	}

	private static function words( $s ) {
		static $stop = array( 'the', 'a', 'an', 'i', 'my', 'me', 'is', 'are', 'do', 'does', 'you', 'your', 'can', 'which', 'what', 'how', 'to', 'for', 'of', 'in', 'on', 'and', 'or', 'it', 'with', 'be', 'that', 'this', 'get', 'want', 'have', 'there', 'will', 'should', 'would', 'look', 'wig', 'wigs', 'nura', 'please', 'hi', 'hello', 'much', 'any' );
		$out = array();
		foreach ( preg_split( '/\s+/', preg_replace( '/[^a-z0-9\s\-]/', ' ', strtolower( (string) $s ) ) ) as $w ) {
			$w = trim( $w, '-' );
			if ( strlen( $w ) > 1 && ! in_array( $w, $stop, true ) ) {
				$out[ rtrim( $w, 's' ) ] = true;
			}
		}
		return $out;
	}

	/**
	 * Best matching question.
	 *
	 * @return array|null {cat, cat_title, q, a, h, guides, score}
	 */
	public static function match( $text ) {
		$norm = self::normalise( $text );
		$w    = self::words( $norm );
		if ( ! $w ) {
			return null;
		}
		$best = null;
		foreach ( self::categories() as $c ) {
			$ck = self::words( $c['kw'] );
			$cs = count( array_intersect_key( $w, $ck ) );
			foreach ( (array) $c['q'] as $q ) {
				$qw    = self::words( $q[0] );
				$hit   = count( array_intersect_key( $w, $qw ) );
				$score = $hit * 2 + $cs + ( $qw ? $hit / count( $qw ) : 0 );
				if ( $hit > 0 && ( null === $best || $score > $best['score'] ) ) {
					$best = array(
						'cat'       => $c['id'],
						'cat_title' => $c['title'],
						'q'         => $q[0],
						'a'         => self::fill( $q[1] ),
						'h'         => array_merge( (array) $c['h'], (array) $q[2] ),
						'guides'    => (array) $c['guides'],
						'score'     => $score,
					);
				}
			}
		}
		return ( $best && $best['score'] >= 3 ) ? $best : null;
	}

	/** Product hints from the shopper's own words (they win over question hints). */
	public static function hints_from_text( $text ) {
		$t = self::normalise( $text );
		$h = array();
		if ( preg_match( '/(?:under|below|less than|max|budget|up ?to|within|around|ya)\s*(?:kes|ksh|sh)?\s*([\d,]{3,6})/', $t, $m ) || preg_match( '/\b(\d{4,6})\s*(?:under|bob|kes|ksh|shillings)/', $t, $m ) || preg_match( '/(?:kes|ksh|sh)\s*([\d,]{3,6})/', $t, $m ) ) {
			$n = (int) str_replace( ',', '', $m[1] );
			if ( $n >= 500 ) {
				$h['max'] = $n;
			}
		}
		$map = array(
			'hair'         => array( 'semi' => 'blend', 'blend' => 'blend', 'human' => 'human', 'synthetic' => 'fibre', 'fibre' => 'fibre', 'fiber' => 'fibre' ),
			'construction' => array( 'headband' => 'headband', 'glueless' => 'headband', 'without glue' => 'headband', 'closure' => 'closure', '4x4' => 'closure', 'frontal' => 'lace', 'lace front' => 'lace', 'lace' => 'lace' ),
			'texture'      => array( 'jerry' => 'jerry', 'deep curl' => 'deep curl', 'water' => 'water', 'bubble' => 'bubble', 'yaki' => 'yaki', 'kinky straight' => 'yaki', 'afro' => 'afro', 'kinky' => 'kinky', 'loose wave' => 'loose wave', 'body wave' => 'wave', 'bone straight' => 'bone straight', 'straight' => 'straight', 'curly' => 'curl', 'curl' => 'curl' ),
			'colour'       => array( 'burgundy' => 'burgundy', 'honey' => 'honey', 'blonde' => 'blonde', 'ginger' => 'ginger', 'copper' => 'copper', 'ombre' => 'ombre', 'highlight' => 'highlight', 'brown' => 'brown', '1b' => 'natural black', 'black' => 'black' ),
			'style'        => array( 'bob' => 'bob', 'short' => 'bob' ),
			'occasion'     => array( 'wedding' => 'wedding', 'church' => 'church', 'office' => 'work', 'work' => 'work' ),
		);
		foreach ( $map as $k => $pairs ) {
			foreach ( $pairs as $kw => $v ) {
				if ( false !== strpos( $t, $kw ) ) {
					$h[ $k ] = $v;
					break;
				}
			}
		}
		if ( preg_match( '/\b(8|10|12|14|16|18|20|22)\s*(?:inch|inches|in)\b/', $t, $m ) ) {
			$h['length'] = $m[1];
		}
		if ( preg_match( '/\b(cheap|affordable|budget|bei rahisi)\b/', $t ) ) {
			$h['sort'] = 'cheap';
		}
		return $h;
	}

	/**
	 * Up to $n live product IDs that fit the hints. Hard filters: max price.
	 * Soft scoring: hair, construction, texture, colour, style, occasion, length.
	 */
	public static function pick( $hints, $n = 3 ) {
		$rows = self::catalogue();
		if ( ! $rows ) {
			return array();
		}
		$max  = isset( $hints['max'] ) ? (float) $hints['max'] : 0;
		$min  = isset( $hints['min'] ) ? (float) $hints['min'] : 0; // 1.81.2: budget bands from the 5-step finder.
		$pool = array_filter( $rows, function ( $r ) use ( $max, $min ) {
			return $r['stock'] && ( ! $max || $r['price'] <= $max * 1.05 ) && ( ! $min || $r['price'] >= $min );
		} );
		if ( ! $pool ) {
			$pool = array_filter( $rows, function ( $r ) {
				return $r['stock'];
			} );
		}
		$cons = array( 'headband' => 'headband', 'closure' => 'closure', 'lace' => 'lace front' );
		foreach ( $pool as &$r ) {
			$s = 0;
			if ( ! empty( $hints['hair'] ) && $r['hair'] === $hints['hair'] ) {
				$s += 5;
			}
			if ( ! empty( $hints['construction'] ) && false !== strpos( $r['text'], $cons[ $hints['construction'] ] ?? $hints['construction'] ) ) {
				$s += 4;
			}
			foreach ( array( 'texture' => 3, 'colour' => 2, 'style' => 3, 'occasion' => 2 ) as $k => $wt ) {
				if ( ! empty( $hints[ $k ] ) && false !== strpos( $r['text'], (string) $hints[ $k ] ) ) {
					$s += $wt;
				}
			}
			if ( ! empty( $hints['length'] ) && false !== strpos( $r['text'], $hints['length'] . '"' ) ) {
				$s += 3;
			}
			if ( ! empty( $hints['lenset'] ) ) {
				foreach ( (array) $hints['lenset'] as $l ) {
					if ( false !== strpos( $r['text'], (string) $l ) ) {
						$s += 3;
						break;
					}
				}
			}
			$r['score'] = $s;
		}
		unset( $r );
		if ( ! array_filter( wp_list_pluck( $pool, 'score' ) ) && empty( $hints['max'] ) ) {
			// No product signal (e.g. a delivery or location question): show one entry wig per hair type.
			usort( $pool, function ( $a, $b ) {
				return $a['price'] <=> $b['price'];
			} );
			$seen = array();
			$ids  = array();
			foreach ( $pool as $r ) {
				if ( empty( $seen[ $r['hair'] ] ) ) {
					$seen[ $r['hair'] ] = true;
					$ids[]              = $r['id'];
				}
			}
			return array_slice( $ids, 0, max( 1, (int) $n ) );
		}
		$cheap = ! empty( $hints['sort'] ) && 'cheap' === $hints['sort'];
		usort( $pool, function ( $a, $b ) use ( $cheap ) {
			if ( $a['score'] !== $b['score'] ) {
				return $b['score'] <=> $a['score'];
			}
			return $cheap ? $a['price'] <=> $b['price'] : $b['price'] <=> $a['price'];
		} );
		return array_slice( wp_list_pluck( $pool, 'id' ), 0, max( 1, (int) $n ) );
	}

	/** Product cards (name, price, hair type, image, url, stock). */
	public static function cards( $ids ) {
		$labels = array( 'human' => 'Pure Human', 'blend' => 'Semi Human', 'fibre' => 'Luxury fibre' );
		$out    = array();
		foreach ( (array) $ids as $id ) {
			$p = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $p ) {
				continue;
			}
			$img   = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
			$out[] = array(
				'id'    => $p->get_id(),
				'name'  => $p->get_name(),
				'price' => wp_strip_all_tags( $p->get_price_html() ? $p->get_price_html() : wc_price( wc_get_price_to_display( $p ) ) ),
				'hair'  => $labels[ self::hair_of( $p ) ],
				'img'   => $img ? $img : wc_placeholder_img_src(),
				'url'   => get_permalink( $p->get_id() ),
				'stock' => $p->is_in_stock(),
			);
		}
		return $out;
	}

	/**
	 * Plain-language reasons a product fits the hints (for "why this wig").
	 *
	 * @return string[]
	 */
	public static function why( $id, $hints ) {
		$row = null;
		foreach ( self::catalogue() as $r ) {
			if ( (int) $r['id'] === (int) $id ) {
				$row = $r;
				break;
			}
		}
		if ( ! $row ) {
			return array();
		}
		$t   = $row['text'];
		$out = array();
		if ( ! empty( $hints['max'] ) || ! empty( $hints['min'] ) ) {
			$out[] = sprintf( __( 'In your budget at %s', 'nura-experience' ), self::ksh( $row['price'] ) );
		}
		if ( false !== strpos( $t, 'headband' ) ) {
			$out[] = __( 'No glue or lace: on in five minutes', 'nura-experience' );
		} elseif ( false !== strpos( $t, 'closure' ) ) {
			$out[] = __( '4x4 closure: natural parting, usually worn glueless', 'nura-experience' );
		} elseif ( false !== strpos( $t, 'lace front' ) || false !== strpos( $t, 'frontal' ) ) {
			$out[] = __( 'Lace front for the most natural hairline (we can install it)', 'nura-experience' );
		}
		$hair = array( 'human' => __( 'Pure Human: heat-style, colour, lasts about a year or more', 'nura-experience' ), 'blend' => __( 'Semi Human: softer than fibre for less than human hair', 'nura-experience' ), 'fibre' => __( 'Luxury fibre: styled out of the box, easy care', 'nura-experience' ) );
		$out[] = $hair[ $row['hair'] ];
		if ( ! empty( $hints['texture'] ) && false !== strpos( $t, (string) $hints['texture'] ) ) {
			$out[] = __( 'Matches the texture you want', 'nura-experience' );
		}
		if ( ! empty( $hints['occasion'] ) && false !== strpos( $t, (string) $hints['occasion'] ) ) {
			$out[] = __( 'Suited to the occasion you chose', 'nura-experience' );
		}
		return array_slice( $out, 0, 3 );
	}

	/** One full stylist turn: answer + follow-up + 3 products. */
	public static function respond( $text ) {
		$m     = self::match( $text );
		$hints = array_merge( $m ? $m['h'] : array(), self::hints_from_text( $text ) );
		$reply = $m ? $m['a'] : '';
		if ( $m && in_array( $m['cat'], array( 'prices', 'face', 'lifestyle', 'occasions', 'glueless' ), true ) && empty( $hints['max'] ) ) {
			$reply .= ' ' . __( 'What budget in KES should I work with? I can narrow these down for you.', 'nura-experience' );
		}
		return array(
			'match'    => $m,
			'reply'    => $reply,
			'products' => self::cards( self::pick( $hints, 3 ) ),
		);
	}

	/**
	 * WhatsApp add-on for a reply (1.82.0): up to 3 live picks as text and the
	 * booking link for installation / service questions. Empty when nothing fits.
	 */
	public static function whatsapp_extras( $text ) {
		$m     = self::match( $text );
		$hints = array_merge( $m ? $m['h'] : array(), self::hints_from_text( $text ) );
		$out   = '';
		$svc   = $m && 'installation' === $m['cat'];
		if ( ! $svc && ( $hints || ( $m && in_array( $m['cat'], array( 'prices', 'glueless', 'hair-types', 'textures', 'colours', 'occasions', 'lifestyle', 'face' ), true ) ) ) ) {
			$cards = self::cards( self::pick( $hints, 3 ) );
			if ( $cards ) {
				$out .= "\n\n" . __( 'Stylist picks:', 'nura-experience' );
				foreach ( $cards as $c ) {
					$out .= "\n- " . $c['name'] . ' (' . $c['price'] . ', ' . $c['hair'] . '): ' . $c['url'];
				}
			}
		}
		if ( $svc || preg_match( '/\b(install|book|appointment|revamp|relac|colour|color|styling)/', self::normalise( $text ) ) ) {
			$book = function_exists( 'home_url' ) ? home_url( '/book-appointment/?service=installation' ) : '';
			$out .= "\n\n" . sprintf( __( 'Book a studio slot (mornings, Stanbank House, 2nd Floor, Room 20): %s', 'nura-experience' ), $book );
		}
		$finder = home_url( '/find-your-wig/' );
		if ( ! $svc && ! $hints && $m && in_array( $m['cat'], array( 'prices', 'face', 'lifestyle' ), true ) ) {
			$out .= "\n\n" . sprintf( __( 'Or find your wig in 5 quick questions: %s', 'nura-experience' ), $finder );
		}
		return $out;
	}

	/** Flat FAQ list for pages, schema and llms.txt. */
	public static function faq( $cat = '' ) {
		$out = array();
		foreach ( self::categories() as $c ) {
			if ( '' !== $cat && $c['id'] !== $cat ) {
				continue;
			}
			foreach ( (array) $c['q'] as $q ) {
				$out[] = array( $q[0], self::fill( $q[1] ), $c['id'] );
			}
		}
		return $out;
	}

	/* -------------------------------------------------------- AI prompt */

	/** Master instructions + knowledge for the LLM (website and WhatsApp). */
	public static function prompt_block( $text = '' ) {
		$p  = "\n\nNURA STYLIST RULES\n";
		$p .= "You are NURA Stylist, NURA Beauty's AI wig consultant. Understand English, Kiswahili and Sheng (for example 'Ni wig gani iko poa?', 'Nataka wig ya daily use', 'Bei ya human hair ni ngapi?', 'Nataka wig isihitaji glue', 'Mnaweka wig?', 'Mko CBD?', 'Nataka wig ya 3k') and reply in the customer's language mix.\n";
		$p .= "- Ask for the budget in KES before recommending if it is unknown, then construction, hair type, texture/length, occasion and whether they need installation. Ask one or two questions at a time.\n";
		$p .= "- Recommend at most three products, only from the catalogue facts, with exact name, price, hair type, construction and why it fits. Never invent products, prices, discounts, stock or lifespans beyond the answers below.\n";
		$p .= "- Never call fibre or blend wigs human hair. Distinguish Pure Human, Semi Human and luxury fibre.\n";
		$p .= "- Distinguish buying a wig, installing a NURA wig and installing the customer's own wig. Never say an appointment is confirmed; send them to the booking page or WhatsApp.\n";
		$p .= "- Be warm and never pushy. Face-shape advice is a suggestion, not a rule.\n";
		$p .= "\nNURA ANSWERS TO COMMON QUESTIONS (use these facts):\n";
		$m = '' !== $text ? self::match( $text ) : null;
		if ( $m ) {
			$p .= 'Best match for this message: Q: ' . $m['q'] . ' A: ' . $m['a'] . "\n";
		}
		foreach ( self::categories() as $c ) {
			$p .= '# ' . $c['title'] . "\n";
			foreach ( array_slice( (array) $c['q'], 0, 6 ) as $q ) {
				$p .= 'Q: ' . $q[0] . ' A: ' . self::fill( $q[1] ) . "\n";
			}
		}
		return $p;
	}
}
