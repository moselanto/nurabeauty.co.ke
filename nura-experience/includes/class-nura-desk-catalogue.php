<?php
/**
 * NURA Desk - Catalogue sync (NURA Experience 1.87.0).
 *
 * Makes the website match the verified WhatsApp catalogue (data/wa-catalogue.php, 25 items
 * exported 2 Nov 2026): for each WhatsApp item the owner confirms a live product, then one click
 *  - sets the product title to the WhatsApp title (slug/URL unchanged, so links and SEO keep),
 *  - writes a short description (WhatsApp text + colours) and a full, structured description
 *    (about, why you will love it, colours, price & payment, delivery, help) - facts only,
 *  - sets the WhatsApp price on the product or on every variation (sale price cleared),
 *  - moves every other published product to Draft (owner can untick any).
 * Everything changed is backed up first; "Undo last sync" restores titles, descriptions,
 * prices and status exactly. Images, slugs, variations and stock are never touched.
 *
 * Owner-only (manage_options).
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Desk_Catalogue {

	const PAGE   = 'nurax-catalogue';
	const BACKUP = 'nurax_catalog_backup';

	public static function hooks() {
		add_action( 'admin_post_nurax_cat_apply', array( __CLASS__, 'h_apply' ) );
		add_action( 'admin_post_nurax_cat_undo', array( __CLASS__, 'h_undo' ) );
		add_action( 'admin_post_nurax_cat_refresh', array( __CLASS__, 'h_refresh' ) );
		add_action( 'admin_post_nurax_cat_colours', array( __CLASS__, 'h_colours' ) );
		add_action( 'admin_post_nurax_cat_colours_undo', array( __CLASS__, 'h_colours_undo' ) );
	}

	public static function items() {
		$f = NURAX_DIR . 'data/wa-catalogue.php';
		return (array) apply_filters( 'nurax_wa_catalogue', file_exists( $f ) ? require $f : array() );
	}

	/** Every product except trash, newest first: id => product. */
	public static function products() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$out = array();
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'draft', 'pending', 'private' ), 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$out[ $p->get_id() ] = $p;
		}
		return $out;
	}

	/* ------------------------------------------------------------ description */

	private static function has( $text, $re ) {
		return (bool) preg_match( $re, strtolower( $text ) );
	}

	/** Colour names from the product's colour attribute (used when WhatsApp lists none). */
	public static function product_colours( $product ) {
		if ( ! $product ) {
			return array();
		}
		foreach ( array( 'pa_colour', 'colour', 'Colour', 'pa_color' ) as $a ) {
			$v = trim( (string) $product->get_attribute( $a ) );
			if ( '' !== $v ) {
				return array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/[,|]/', $v ) ) ) ) );
			}
		}
		return array();
	}

	/** Current price of a product (lowest variation price for variable products). */
	public static function product_price( $product ) {
		if ( ! $product ) {
			return null;
		}
		$v = $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min', true ) : (float) $product->get_price();
		return $v > 0 ? $v : null;
	}

	/** [ kind, cap ] e.g. [ 'Pure Human', 'lace front bob wig' ]. */
	public static function classify( $it, $title, $product ) {
		$cats = $product ? wp_strip_all_tags( wc_get_product_category_list( $product->get_id(), ' ' ) ) : '';
		$attr = $product ? implode( ' ', array( $product->get_attribute( 'pa_hair-type' ), $product->get_attribute( 'pa_lace' ), $product->get_attribute( 'pa_texture' ), $product->get_attribute( 'Construction' ), $product->get_attribute( 'Hair Type' ) ) ) : '';
		$name  = strtolower( $title . ' ' . $it['text'] . ' ' . $cats . ' ' . $attr );
		$human = self::has( $name, '/100%\s*human|pure human|human hair wigs|human hair\b/' ) && ! self::has( $name, '/semi|blend|fibre|synthetic|heat-resistant/' );
		$semi  = self::has( $name, '/semi|blend/' );
		$kind  = $human ? 'Pure Human' : ( $semi ? 'Semi Human' : 'premium luxury fibre' );
		if ( self::has( $name, '/headband/' ) ) {
			$cap = 'headband wig';
		} elseif ( self::has( $name, '/13\s*[x\*×]\s*4|13 by 4|frontal/' ) ) {
			$cap = '13x4 lace frontal wig';
		} elseif ( self::has( $name, '/2\s*[x×]\s*2/' ) ) {
			$cap = '2x2 lace closure wig';
		} elseif ( self::has( $name, '/4\s*[x\*×]\s*4|closure/' ) ) {
			$cap = '4x4 lace closure wig';
		} elseif ( self::has( $name, '/lace front/' ) ) {
			$cap = 'lace front wig';
		} else {
			$cap = 'wig';
		}
		if ( self::has( $title, '/\bbob\b/' ) && false === strpos( $cap, 'bob' ) ) {
			$cap = str_replace( 'wig', 'bob wig', $cap );
		}
		return array( $kind, $cap, $human );
	}

	public static function describe( $it, $title, $price, $product ) {
		$text  = trim( (string) $it['text'] );
		$cols  = array_values( array_filter( (array) $it['colours'] ) );
		if ( ! $cols ) {
			$cols = self::product_colours( $product );
		}
		if ( ! $price ) {
			$price = self::product_price( $product );
		}
		list( $kind, $cap, $human ) = self::classify( $it, $title, $product );
		$name  = strtolower( $title . ' ' . $text . ' ' . $kind . ' ' . $cap );
		$why   = array();
		if ( self::has( $name, '/glueless|headband|wear and go|ready to wear/' ) ) {
			$why[] = '<strong>Glueless, wear and go</strong> - no glue or salon needed; put it on in minutes and take it off at night.';
		}
		if ( self::has( $name, '/takes heat|heat|straighten|curled|human hair/' ) || $human ) {
			$why[] = '<strong>Heat-friendly styling</strong> - can be straightened or curled with low to medium heat.';
		}
		if ( self::has( $name, '/frontal|lace/' ) ) {
			$why[] = '<strong>Natural-looking hairline</strong> - lace that blends at the front for a realistic finish.';
		}
		if ( self::has( $name, '/headband/' ) ) {
			$why[] = '<strong>Beginner friendly</strong> - the headband hides the hairline, no lace work at all.';
		}
		$why[] = '<strong>Checked in our Nairobi studio</strong> - every unit is inspected before it leaves, and photographed as it is.';
		$why[] = '<strong>Comfortable all day</strong> - adjustable straps and combs for a secure, light fit.';

		$p   = $price ? 'KSh ' . number_format( (float) $price ) : '';
		$h   = '<p class="nura-answer"><strong>In short:</strong> ' . esc_html( $title ) . ' - a ' . esc_html( $kind . ' ' . $cap ) . ( $p ? ' at ' . esc_html( $p ) : '' ) . ( $cols ? ', available in ' . esc_html( implode( ', ', $cols ) ) : '' ) . '. Same-day delivery in Nairobi for orders before 5 PM, same-day dispatch countrywide, pay by M-Pesa or card.</p>';
		$h  .= '<h2>About the ' . esc_html( $title ) . '</h2>';
		$h  .= '<p>' . esc_html( $text ? rtrim( $text, '.' ) . '.' : 'A ' . $kind . ' ' . $cap . ' from NURA Beauty, chosen for a natural look and easy everyday wear.' ) . '</p>';
		$h  .= '<h2>Why you will love it</h2><ul><li>' . implode( '</li><li>', $why ) . '</li></ul>';
		if ( $cols ) {
			$h .= '<h2>Available colours</h2><p>' . esc_html( implode( ', ', $cols ) ) . '. Ask us on WhatsApp for a live photo or video of any colour before you order.</p>';
		}
		$h .= '<h2>Details</h2><table><tbody>'
			. '<tr><th>Hair type</th><td>' . esc_html( ucfirst( $kind ) ) . '</td></tr>'
			. '<tr><th>Style</th><td>' . esc_html( ucfirst( $cap ) ) . '</td></tr>'
			. ( $cols ? '<tr><th>Colours</th><td>' . esc_html( implode( ', ', $cols ) ) . '</td></tr>' : '' )
			. ( $p ? '<tr><th>Price</th><td>' . esc_html( $p ) . '</td></tr>' : '' )
			. '<tr><th>Cap</th><td>Average, adjustable straps and combs</td></tr></tbody></table>';
		$h .= '<h2>Price, payment and delivery</h2><ul>'
			. ( $p ? '<li>Price: <strong>' . esc_html( $p ) . '</strong>.</li>' : '' )
			. '<li>Pay securely by M-Pesa or card at checkout, or order on WhatsApp and pay to our M-Pesa Buy Goods Till 1679269.</li>'
			. ( $price && $price >= 7000 ? '<li>Lipa Mdogo Mdogo: reserve it with a 50% deposit and clear the balance within 2 weeks, no interest.</li>' : '' )
			. '<li>Nairobi: same-day rider delivery for orders before 5 PM (from KSh 300). Countrywide: bus/shuttle parcel or courier, dispatched the same day.</li>'
			. '<li>Prefer to see it first? Visit our studio at Stanbank House, 2nd Floor, Room 20, Moi Avenue, Nairobi CBD.</li></ul>';
		$h .= '<h2>Need help choosing?</h2><p>Chat with a NURA stylist on WhatsApp (+254 714 994 898) or try the <a href="/ai-wig-finder/">Wig Finder</a>. We also install, style and revamp wigs at the studio.</p>';
		$short = esc_html( $text ? rtrim( $text, '.' ) . '.' : ucfirst( $kind . ' ' . $cap ) . '.' ) . ( $cols ? ' <strong>Colours:</strong> ' . esc_html( implode( ', ', $cols ) ) . '.' : '' );
		return array( $h, '<p>' . $short . '</p>' );
	}

	/**
	 * 1.88.0: search-engine title, description and focus keyword. The visible title stays the
	 * short WhatsApp name; Google gets the full descriptive phrase people search
	 * ("... Wig Kenya | Pure Human Lace Front Bob | KSh 7,500").
	 */
	public static function seo_meta( $product, $it, $title ) {
		list( $kind, $cap ) = self::classify( $it, $title, $product );
		$price = self::product_price( $product );
		$t     = $title . ( self::has( $title, '/wig/' ) ? '' : ( self::has( $cap, '/closure|frontal|headband/' ) ? '' : ' Wig' ) );
		$seo_t = $t . ' Kenya | ' . ucwords( $kind === 'premium luxury fibre' ? 'Luxury Fibre' : ( 0 === strpos( $kind, '100%' ) ? 'Pure Human' : 'Semi Human' ) ) . ' ' . ucwords( $cap ) . ( $price ? ' | KSh ' . number_format( $price ) : '' ) . ' | NURA Beauty';
		$cols  = array_values( array_filter( (array) $it['colours'] ) );
		$cols  = $cols ? $cols : self::product_colours( $product );
		$desc  = 'Buy the ' . $title . ', a ' . $kind . ' ' . $cap . ( $price ? ', KSh ' . number_format( $price ) : '' ) . ( $cols ? ', in ' . implode( ', ', array_slice( $cols, 0, 4 ) ) : '' ) . '. Same-day Nairobi delivery, same-day dispatch countrywide, M-Pesa. NURA Beauty, Nairobi CBD.';
		$product->update_meta_data( 'rank_math_title', mb_substr( $seo_t, 0, 120 ) );
		$product->update_meta_data( 'rank_math_description', mb_substr( $desc, 0, 160 ) );
		$product->update_meta_data( 'rank_math_focus_keyword', strtolower( trim( preg_replace( '/[()]/', '', $title ) ) ) . ( self::has( $title, '/wig/' ) ? '' : ' wig' ) . ' kenya' );
	}

	/** Guess a shop category for a new product from its style. */
	private static function cat_ids( $cap, $kind ) {
		$slugs = array();
		if ( false !== strpos( $cap, 'closure' ) ) {
			$slugs[] = 'closure-wigs';
		} elseif ( false !== strpos( $cap, 'headband' ) ) {
			$slugs[] = 'headband-wigs';
		} elseif ( false !== strpos( $cap, 'bob' ) ) {
			$slugs[] = 'bob-wigs';
		} else {
			$slugs[] = 'lace-front-wigs';
		}
		$slugs[] = 0 === strpos( $kind, '100%' ) ? 'human-hair-wigs' : 'wigs';
		$ids = array();
		foreach ( $slugs as $sl ) {
			$t = get_term_by( 'slug', $sl, 'product_cat' );
			if ( $t ) {
				$ids[] = (int) $t->term_id;
			}
		}
		return $ids;
	}

	/* ------------------------------------------------------------ backup / prices */

	private static function snap( $p ) {
		$v = array();
		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $cid ) {
				$c = wc_get_product( $cid );
				if ( $c ) {
					$v[ $cid ] = array( $c->get_regular_price( 'edit' ), $c->get_sale_price( 'edit' ) );
				}
			}
		}
		return array( 'name' => $p->get_name( 'edit' ), 'desc' => $p->get_description( 'edit' ), 'short' => $p->get_short_description( 'edit' ), 'status' => $p->get_status( 'edit' ), 'reg' => $p->get_regular_price( 'edit' ), 'sale' => $p->get_sale_price( 'edit' ), 'vars' => $v );
	}

	private static function set_price( $p, $price ) {
		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $cid ) {
				$c = wc_get_product( $cid );
				if ( $c ) {
					$c->set_regular_price( (string) $price );
					$c->set_sale_price( '' );
					$c->save();
				}
			}
			WC_Product_Variable::sync( $p->get_id() );
		} else {
			$p->set_regular_price( (string) $price );
			$p->set_sale_price( '' );
		}
	}


	/* ------------------------------------------------------------ colours (1.89.0) */

	const CBACKUP = 'nurax_catalog_colour_backup';

	/** Tidy a WhatsApp colour name: 'c 33' -> 'C33', '99j' -> '99J', 'red wine' -> 'Red Wine'. */
	public static function colour_name( $c ) {
		$c = trim( preg_replace( '/\s+/', ' ', (string) $c ), " .,&" );
		$c = preg_replace_callback( '/\b(\d+)j\b/i', function ( $m ) {
			return $m[1] . 'J';
		}, $c );
		if ( preg_match( '/^c\s*(\S.*)$/i', $c, $m ) && preg_match( '/^\d/', $m[1] ) ) {
			$c = 'C' . str_replace( ' ', '', $m[1] );
		}
		return preg_match( '/[a-z]/', $c ) ? ucwords( strtolower( $c ) ) : $c;
	}

	public static function wa_colours( $it ) {
		$out = array();
		foreach ( (array) $it['colours'] as $c ) {
			$n = self::colour_name( $c );
			if ( '' !== $n && ! in_array( strtolower( $n ), array_map( 'strtolower', $out ), true ) ) {
				$out[] = $n;
			}
		}
		return $out;
	}

	/** [ attribute key, WC_Product_Attribute|null ] for the variation colour attribute. */
	private static function colour_attr( $p ) {
		foreach ( $p->get_attributes() as $k => $a ) {
			if ( $a instanceof WC_Product_Attribute && false !== stripos( wc_attribute_label( $a->get_name() ), 'colo' ) && ( $a->get_variation() || ! $a->is_taxonomy() ) ) {
				return array( $k, $a );
			}
		}
		return array( '', null );
	}

	/** Current colour options customers can pick (active variations only). */
	public static function current_colours( $p ) {
		if ( ! $p->is_type( 'variable' ) ) {
			return array();
		}
		list( $k ) = self::colour_attr( $p );
		$out = array();
		foreach ( $p->get_children() as $cid ) {
			$v = wc_get_product( $cid );
			if ( $v && 'publish' === $v->get_status() && $k ) {
				$val = $v->get_attribute( $k );
				if ( '' !== $val ) {
					$out[] = $val;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** 1.90.0: number of live variations whose colour is "Any Colour" (empty value). */
	public static function wildcards( $p ) {
		if ( ! $p->is_type( 'variable' ) ) {
			return 0;
		}
		list( $k ) = self::colour_attr( $p );
		if ( ! $k ) {
			return 0;
		}
		$n = 0;
		foreach ( $p->get_children() as $cid ) {
			$v = wc_get_product( $cid );
			if ( $v && 'publish' === $v->get_status() ) {
				$va = $v->get_attributes();
				if ( ! isset( $va[ $k ] ) || '' === (string) $va[ $k ] ) {
					$n++;
				}
			}
		}
		return $n;
	}

	/** 1.90.0: copy of a product's attributes with fresh objects, so WooCommerce sees a change and saves it. */
	private static function attrs_copy( $p ) {
		$out = array();
		foreach ( $p->get_attributes() as $k => $a ) {
			$out[ $k ] = is_object( $a ) ? clone $a : $a;
		}
		return $out;
	}

	/** Products linked to a WhatsApp item (by sync key), with the item. */
	private static function linked() {
		$by = array();
		foreach ( self::items() as $it ) {
			$by[ $it['key'] ] = $it;
		}
		$out = array();
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'draft', 'private', 'pending' ), 'meta_key' => '_nura_wa_key' ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$k = (string) $p->get_meta( '_nura_wa_key' );
			if ( isset( $by[ $k ] ) ) {
				$out[ $p->get_id() ] = array( $p, $by[ $k ] );
			}
		}
		return $out;
	}

	/** Plan for one product: keep / add / hide. */
	public static function colour_plan( $p, $it ) {
		$wa   = self::wa_colours( $it );
		$cur  = self::current_colours( $p );
		$lcur = array_map( 'strtolower', $cur );
		$lwa  = array_map( 'strtolower', $wa );
		list( , $ca ) = self::colour_attr( $p );
		$opts = $ca ? array_map( 'strtolower', array_map( 'strval', $ca->get_options() ) ) : array();
		sort( $opts );
		$lwas = $lwa;
		sort( $lwas );
		return array(
			'wa'   => $wa,
			'cur'  => $cur,
			'any'  => self::wildcards( $p ),
			'opts' => ! $p->is_type( 'variable' ) || $opts === $lwas,
			'keep' => array_values( array_filter( $wa, function ( $c ) use ( $lcur ) { return in_array( strtolower( $c ), $lcur, true ); } ) ),
			'add'  => array_values( array_filter( $wa, function ( $c ) use ( $lcur ) { return ! in_array( strtolower( $c ), $lcur, true ); } ) ),
			'hide' => array_values( array_filter( $cur, function ( $c ) use ( $lwa ) { return ! in_array( strtolower( $c ), $lwa, true ); } ) ),
		);
	}

	public static function h_colours() {
		self::guard( 'nurax_cat_colours' );
		$pick   = isset( $_POST['col'] ) ? array_map( 'absint', (array) $_POST['col'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$links  = self::linked();
		$backup = array( 'time' => current_time( 'mysql' ), 'products' => array() );
		$done   = 0;
		$failed = array();
		foreach ( $pick as $pid ) {
			if ( ! isset( $links[ $pid ] ) ) {
				continue;
			}
			list( $p, $it ) = $links[ $pid ];
			$wa = self::wa_colours( $it );
			if ( ! $wa ) {
				continue;
			}
			$b = array( 'type' => $p->get_type(), 'attrs' => self::attrs_copy( $p ), 'defaults' => $p->is_type( 'variable' ) ? $p->get_default_attributes() : array(), 'status' => array(), 'created' => array(), 'reg' => $p->get_regular_price( 'edit' ) );
			$price = self::product_price( $p );
			if ( ! $p->is_type( 'variable' ) ) {
				if ( count( $wa ) < 2 ) {
					// One colour: keep it simple, just record the colour.
					$a = new WC_Product_Attribute();
					$a->set_name( 'Colour' );
					$a->set_options( $wa );
					$a->set_visible( true );
					$a->set_variation( false );
					$attrs = self::attrs_copy( $p );
					list( $ck ) = self::colour_attr( $p );
					$attrs[ $ck ? $ck : 'colour' ] = $a;
					$p->set_attributes( $attrs );
					$p->save();
					$backup['products'][ $pid ] = $b;
					$done++;
					continue;
				}
				wp_set_object_terms( $pid, 'variable', 'product_type' );
				wc_delete_product_transients( $pid );
				$p = new WC_Product_Variable( $pid );
			}
			list( $ck, $ca ) = self::colour_attr( $p );
			$attrs = self::attrs_copy( $p );
			// 1.90.0: never edit the attribute object in place - WooCommerce then sees "no change" and
			// silently keeps the old colour list, which made every new colour show as "Any Colour".
			$ca = ( $ca && ! $ca->is_taxonomy() ) ? clone $ca : null;
			if ( ! $ca ) {
				$ca = new WC_Product_Attribute();
				$ca->set_name( 'Colour' );
				$ck = 'colour';
			}
			$ca->set_options( $wa );
			$ca->set_visible( true );
			$ca->set_variation( true );
			$attrs[ $ck ] = $ca;
			$p->set_attributes( $attrs );
			$p->save();
			// 1.90.0: read it back; stop on this product if the colour list did not save.
			wc_delete_product_transients( $pid );
			$chk = wc_get_product( $pid );
			$got = array();
			if ( $chk ) {
				$ga = $chk->get_attributes();
				if ( isset( $ga[ $ck ] ) && is_object( $ga[ $ck ] ) ) {
					$got = array_map( 'strtolower', array_map( 'strval', $ga[ $ck ]->get_options() ) );
				}
			}
			if ( array_diff( array_map( 'strtolower', $wa ), $got ) ) {
				$failed[] = $p->get_name();
				continue;
			}
			$p = $chk;
			// Existing variations: keep matching colours (renamed to the WhatsApp spelling), hide the rest.
			$have = array();
			foreach ( $p->get_children() as $cid ) {
				$v = wc_get_product( $cid );
				if ( ! $v ) {
					continue;
				}
				$val = (string) $v->get_attribute( $ck );
				$b['status'][ $cid ] = array( $v->get_status(), $v->get_attributes() );
				$hit = null;
				foreach ( $wa as $c ) {
					if ( strtolower( $c ) === strtolower( $val ) ) {
						$hit = $c;
					}
				}
				if ( $hit && ! isset( $have[ strtolower( $hit ) ] ) ) {
					$va         = $v->get_attributes();
					$va[ $ck ]  = $hit;
					$v->set_attributes( $va );
					$v->set_status( 'publish' );
					$have[ strtolower( $hit ) ] = true;
				} else {
					$v->set_status( 'private' ); // hidden from customers, kept for old orders and Undo
				}
				$v->save();
			}
			foreach ( $wa as $c ) {
				if ( isset( $have[ strtolower( $c ) ] ) ) {
					continue;
				}
				$v = new WC_Product_Variation();
				$v->set_parent_id( $pid );
				$v->set_attributes( array( $ck => $c ) );
				if ( $price ) {
					$v->set_regular_price( (string) $price );
				}
				$v->set_stock_status( 'instock' );
				$v->set_status( 'publish' );
				$v->save();
				$b['created'][] = $v->get_id();
			}
			$p->set_default_attributes( array( $ck => $wa[0] ) );
			$p->save();
			WC_Product_Variable::sync( $pid );
			wc_delete_product_transients( $pid );
			$backup['products'][ $pid ] = $b;
			$done++;
		}
		update_option( self::CBACKUP, $backup, false );
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'catalogue', 'Colour sync: ' . $done . ' product(s) now offer the WhatsApp colours' );
		}
		do_action( 'litespeed_purge_all' );
		if ( $failed ) {
			set_transient( 'nurax_cat_colour_fail', $failed, HOUR_IN_SECONDS );
		}
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'colours' => $done, 'colour_fail' => count( $failed ) ) ) . '#colours' );
		exit;
	}

	public static function h_colours_undo() {
		self::guard( 'nurax_cat_colours_undo' );
		$b = get_option( self::CBACKUP );
		$n = 0;
		if ( is_array( $b ) && ! empty( $b['products'] ) ) {
			foreach ( $b['products'] as $pid => $s ) {
				foreach ( (array) $s['created'] as $cid ) {
					wp_delete_post( (int) $cid, true );
				}
				if ( 'variable' !== $s['type'] ) {
					wp_set_object_terms( $pid, $s['type'], 'product_type' );
					wc_delete_product_transients( $pid );
				}
				foreach ( (array) $s['status'] as $cid => $st ) {
					$v = wc_get_product( $cid );
					if ( $v ) {
						$v->set_status( $st[0] );
						$v->set_attributes( $st[1] );
						$v->save();
					}
				}
				$p = wc_get_product( $pid );
				if ( $p ) {
					$p->set_attributes( $s['attrs'] );
					if ( $p->is_type( 'variable' ) ) {
						$p->set_default_attributes( $s['defaults'] );
					} else {
						$p->set_regular_price( $s['reg'] );
					}
					$p->save();
					if ( $p->is_type( 'variable' ) ) {
						WC_Product_Variable::sync( $pid );
					}
				}
				$n++;
			}
			delete_option( self::CBACKUP );
			do_action( 'litespeed_purge_all' );
		}
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'colours_undone' => $n ) ) . '#colours' );
		exit;
	}

	private static function render_colours() {
		$links = self::linked();
		$b     = get_option( self::CBACKUP );
		echo '<section class="nd-card" id="colours"><div class="nc-colh"><h3>3. Colours - offer exactly the WhatsApp colours</h3>';
		$fail = get_transient( 'nurax_cat_colour_fail' );
		if ( $fail ) {
			delete_transient( 'nurax_cat_colour_fail' );
			echo '<div class="notice notice-error inline"><p>' . esc_html( 'These products were NOT changed because the colour list did not save: ' . implode( ', ', (array) $fail ) . '. Nothing else on them was touched.' ) . '</p></div>';
		}
		if ( is_array( $b ) && ! empty( $b['products'] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Put the colour options back as they were before ' . esc_attr( $b['time'] ) . '?\')">' . wp_nonce_field( 'nurax_cat_colours_undo', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_cat_colours_undo"><button class="nd-btn nd-btn--ghost nd-btn--sm">Undo colour sync</button></form>';
		}
		echo '</div><p class="na-muted">The colours a customer can pick on each product become exactly the colours in your WhatsApp catalogue. Matching colours keep their photo and stock; new ones are added at the product\'s price, in stock, using the main photo; colours not on WhatsApp are hidden (not deleted - old orders keep them and Undo brings them back). Products with 2+ colours that were single-option become colour-choice products.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Change the colour options on the ticked products to the WhatsApp colours?\')">';
		wp_nonce_field( 'nurax_cat_colours' );
		echo '<input type="hidden" name="action" value="nurax_cat_colours"><div class="nd-table-wrap"><table class="nd-table nc-t nc-ct"><thead><tr><th class="nc-on">Apply</th><th>Product</th><th>WhatsApp colours</th><th>Customers can pick now</th><th>What changes</th></tr></thead><tbody>';
		$rows = 0;
		foreach ( $links as $pid => $l ) {
			list( $p, $it ) = $l;
			$pl = self::colour_plan( $p, $it );
			if ( ! $pl['wa'] ) {
				echo '<tr class="nc-r--muted"><td class="nc-on">-</td><td>' . esc_html( $p->get_name() ) . '</td><td><span class="na-muted">None listed on WhatsApp</span></td><td>' . esc_html( $pl['cur'] ? implode( ', ', $pl['cur'] ) : '-' ) . '</td><td><span class="na-muted">Left as it is</span></td></tr>';
				continue;
			}
			$same = ! $pl['add'] && ! $pl['hide'] && ! $pl['any'] && $pl['opts'] && ( $p->is_type( 'variable' ) || count( $pl['wa'] ) < 2 );
			$chg  = array();
			if ( $pl['any'] ) {
				/* translators: %d: number of variations. */
				$chg[] = '<b class="nc-warn">' . esc_html( sprintf( _n( '%d "Any Colour" option on sale', '%d "Any Colour" options on sale', $pl['any'], 'nura-experience' ), $pl['any'] ) ) . '</b> - customers cannot pick a real colour; Apply hides it';
			}
			if ( ! $pl['opts'] ) {
				$chg[] = '<b>Fix colour list</b> on the product (it does not match WhatsApp)';
			}
			if ( ! $p->is_type( 'variable' ) && count( $pl['wa'] ) > 1 ) {
				$chg[] = 'becomes a colour-choice product';
			}
			if ( $pl['add'] ) {
				$chg[] = '<b>Add:</b> ' . esc_html( implode( ', ', $pl['add'] ) );
			}
			if ( $pl['hide'] ) {
				$chg[] = '<b>Hide:</b> ' . esc_html( implode( ', ', $pl['hide'] ) );
			}
			$rows++;
			echo '<tr><td class="nc-on"><input type="checkbox" name="col[]" value="' . (int) $pid . '"' . checked( ! $same, true, false ) . '></td><td><strong>' . esc_html( $p->get_name() ) . '</strong>' . ( 'publish' !== $p->get_status() ? '<small>' . esc_html( $p->get_status() ) . '</small>' : '' ) . '</td><td>' . esc_html( implode( ', ', $pl['wa'] ) ) . '</td><td>' . esc_html( $pl['cur'] ? implode( ', ', $pl['cur'] ) : ( $p->is_type( 'variable' ) ? '-' : 'single option' ) ) . '</td><td>' . ( $same ? '<span class="nc-b nc-b--high">Already matches</span>' : implode( '<br>', $chg ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table></div>';
		echo $rows ? '<p class="nc-go"><button class="nd-btn nd-btn--primary">Apply WhatsApp colours</button> <span class="na-muted">Backed up first - "Undo colour sync" restores the old options.</span></p>' : '';
		echo '</form></section>';
	}

	/* ------------------------------------------------------------ handlers */

	private static function guard( $n ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Only the owner can change the catalogue.', 403 );
		}
		check_admin_referer( $n );
	}

	public static function h_apply() {
		self::guard( 'nurax_cat_apply' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$rows   = isset( $_POST['row'] ) ? (array) wp_unslash( $_POST['row'] ) : array();
		$drafts = isset( $_POST['draft'] ) ? array_map( 'absint', (array) $_POST['draft'] ) : array();
		// phpcs:enable
		$items  = self::items();
		$backup = array( 'time' => current_time( 'mysql' ), 'user' => get_current_user_id(), 'products' => array() );
		$mapped = array();
		$done   = 0;
		foreach ( $rows as $i => $r ) {
			$i = (int) $i;
			if ( empty( $r['on'] ) || empty( $r['pid'] ) || ! isset( $items[ $i ] ) ) {
				continue;
			}
			$title = sanitize_text_field( isset( $r['title'] ) ? $r['title'] : $items[ $i ]['title'] );
			$price = isset( $r['price'] ) && '' !== trim( (string) $r['price'] ) ? (float) preg_replace( '/[^0-9.]/', '', (string) $r['price'] ) : null;
			if ( '' === $title ) {
				continue;
			}
			if ( 'new' === $r['pid'] ) {
				// 1.88.0: create the WhatsApp item as a new product - Draft until the photo is added.
				$p = new WC_Product_Simple();
				$p->set_name( $title );
				$p->set_status( 'draft' );
				$p->set_catalog_visibility( 'visible' );
				list( $kind, $cap ) = self::classify( $items[ $i ], $title, null );
				$p->set_category_ids( self::cat_ids( $cap, $kind ) );
				$p->update_meta_data( '_nura_wa_key', $items[ $i ]['key'] );
				$p->save();
				$pid     = $p->get_id();
				$created = isset( $created ) ? $created + 1 : 1;
				$backup['created'][] = $pid;
				list( $long, $short ) = self::describe( $items[ $i ], $title, $price, $p );
				$p->set_description( $long );
				$p->set_short_description( $short );
				if ( $price && $price > 0 ) {
					$p->set_regular_price( (string) $price );
				}
				self::seo_meta( $p, $items[ $i ], $title );
				$p->save();
				$mapped[ $pid ] = true;
				continue;
			}
			$pid = absint( $r['pid'] );
			$p   = wc_get_product( $pid );
			if ( ! $p || isset( $mapped[ $pid ] ) ) {
				continue; // never write two WhatsApp items onto one product
			}
			$backup['products'][ $pid ] = self::snap( $p );
			list( $long, $short ) = self::describe( $items[ $i ], $title, $price, $p );
			$p->set_name( $title );
			$p->set_description( $long );
			$p->set_short_description( $short );
			if ( $price && $price > 0 ) {
				self::set_price( $p, $price );
			}
			if ( 'publish' !== $p->get_status() ) {
				$p->set_status( 'publish' );
			}
			$p->update_meta_data( '_nura_wa_key', $items[ $i ]['key'] );
			self::seo_meta( $p, $items[ $i ], $title );
			$p->save();
			$mapped[ $pid ] = true;
			$done++;
		}
		$dr = 0;
		foreach ( $drafts as $pid ) {
			if ( isset( $mapped[ $pid ] ) ) {
				continue;
			}
			$p = wc_get_product( $pid );
			if ( $p && 'publish' === $p->get_status() ) {
				$backup['products'][ $pid ] = self::snap( $p );
				$p->set_status( 'draft' );
				$p->save();
				$dr++;
			}
		}
		$created = isset( $created ) ? $created : 0;
		update_option( self::BACKUP, $backup, false );
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'catalogue', 'Catalogue sync: ' . $done . ' product(s) updated from WhatsApp, ' . $dr . ' moved to draft' );
		}
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'done' => $done, 'drafted' => $dr, 'created' => $created ) ) );
		exit;
	}

	/** 1.88.0: rebuild description, short description and search titles of every synced product (titles/prices unchanged). */
	public static function h_refresh() {
		self::guard( 'nurax_cat_refresh' );
		$by = array();
		foreach ( self::items() as $it ) {
			$by[ $it['key'] ] = $it;
		}
		$backup = array( 'time' => current_time( 'mysql' ), 'user' => get_current_user_id(), 'products' => array() );
		$n      = 0;
		foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'draft', 'private', 'pending' ), 'meta_key' => '_nura_wa_key' ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$k = (string) $p->get_meta( '_nura_wa_key' );
			if ( ! isset( $by[ $k ] ) ) {
				continue;
			}
			$backup['products'][ $p->get_id() ] = self::snap( $p );
			list( $long, $short ) = self::describe( $by[ $k ], $p->get_name(), null, $p );
			$p->set_description( $long );
			$p->set_short_description( $short );
			self::seo_meta( $p, $by[ $k ], $p->get_name() );
			$p->save();
			$n++;
		}
		update_option( self::BACKUP, $backup, false );
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'refreshed' => $n ) ) );
		exit;
	}

	public static function h_undo() {
		self::guard( 'nurax_cat_undo' );
		$b = get_option( self::BACKUP );
		$n = 0;
		if ( is_array( $b ) && ! empty( $b['products'] ) ) {
			foreach ( (array) $b['products'] as $pid => $s ) {
				$p = wc_get_product( $pid );
				if ( ! $p ) {
					continue;
				}
				$p->set_name( $s['name'] );
				$p->set_description( $s['desc'] );
				$p->set_short_description( $s['short'] );
				$p->set_status( $s['status'] );
				if ( $p->is_type( 'variable' ) ) {
					foreach ( (array) $s['vars'] as $cid => $pr ) {
						$c = wc_get_product( $cid );
						if ( $c ) {
							$c->set_regular_price( $pr[0] );
							$c->set_sale_price( $pr[1] );
							$c->save();
						}
					}
					WC_Product_Variable::sync( $pid );
				} else {
					$p->set_regular_price( $s['reg'] );
					$p->set_sale_price( $s['sale'] );
				}
				$p->save();
				$n++;
			}
			delete_option( self::BACKUP );
			do_action( 'litespeed_purge_all' );
		}
		if ( is_array( $b ) && ! empty( $b['created'] ) ) {
			foreach ( $b['created'] as $cid ) {
				wp_trash_post( (int) $cid ); // products created by the sync go to Trash (restorable)
				$n++;
			}
			delete_option( self::BACKUP );
		}
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'undone' => $n ) ) );
		exit;
	}

	/* ------------------------------------------------------------ page */

	private static function price_of( $p ) {
		if ( $p->is_type( 'variable' ) ) {
			$lo = (float) $p->get_variation_regular_price( 'min' );
			$hi = (float) $p->get_variation_regular_price( 'max' );
			return $lo === $hi ? NURAX_Desk::money( $lo ) : NURAX_Desk::money( $lo ) . ' - ' . NURAX_Desk::money( $hi );
		}
		return '' !== $p->get_regular_price() ? NURAX_Desk::money( $p->get_regular_price() ) : '-';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Only the owner can open the catalogue sync.' );
		}
		$items = self::items();
		$all   = self::products();
		$b     = get_option( self::BACKUP );
		$act   = '';
		if ( is_array( $b ) && ! empty( $b['products'] ) ) {
			$act = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Restore every product changed by the last sync (titles, descriptions, prices, status)?\')">' . wp_nonce_field( 'nurax_cat_undo', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_cat_undo"><button class="nd-btn nd-btn--ghost">Undo last sync (' . esc_html( $b['time'] ) . ')</button></form>';
		}
		$act .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Rebuild the description and Google title of every synced product? Titles and prices stay as they are.\')">' . wp_nonce_field( 'nurax_cat_refresh', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_cat_refresh"><button class="nd-btn nd-btn--ghost">Refresh descriptions &amp; Google titles</button></form>';
		NURAX_Desk::header( self::PAGE, 'Catalogue sync', $act );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['done'] ) ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">Done: ' . (int) $_GET['done'] . ' product(s) updated from WhatsApp and ' . (int) ( $_GET['drafted'] ?? 0 ) . ' moved to draft. The page cache was cleared. Use "Undo last sync" if anything looks wrong.</div>';
		}
		if ( isset( $_GET['created'] ) && (int) $_GET['created'] ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">' . (int) $_GET['created'] . ' new product(s) created as <strong>Draft</strong>. Open each one under Products, add the photo (and colour options if needed), then Publish.</div>';
		}
		if ( isset( $_GET['refreshed'] ) ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">Refreshed the descriptions and Google titles of ' . (int) $_GET['refreshed'] . ' synced product(s). Titles and prices were not changed.</div>';
		}
		if ( isset( $_GET['colours'] ) ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">' . (int) $_GET['colours'] . ' product(s) now offer exactly the WhatsApp colours. New colours use the main photo - add a photo per colour under Products &gt; Variations when you can.</div>';
		}
		if ( isset( $_GET['colours_undone'] ) ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">Colour options restored on ' . (int) $_GET['colours_undone'] . ' product(s).</div>';
		}
		if ( isset( $_GET['undone'] ) ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">Restored ' . (int) $_GET['undone'] . ' product(s) to how they were before the sync.</div>';
		}
		// phpcs:enable
		$used = array();
		foreach ( $items as $it ) {
			if ( $it['pid'] ) {
				$used[ (int) $it['pid'] ] = true;
			}
		}
		echo '<div class="nc"><section class="nd-card nc-intro"><h2>Make the website match your WhatsApp catalogue</h2><p>' . count( $items ) . ' verified WhatsApp products. For each one, confirm the website product it replaces, check the title and price, then press <strong>Apply</strong>. Titles, descriptions and prices are updated; URLs, photos, colour options and stock stay as they are. Every other product moves to Draft (untick any you want to keep live). Everything is backed up first.</p>';
		echo '<p class="nc-legend"><span class="nc-b nc-b--high">Clear match</span> <span class="nc-b nc-b--check">Please confirm</span> <span class="nc-b nc-b--none">No product found - pick one or leave off</span></p></section>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Apply the WhatsApp titles, descriptions and prices, and move the ticked products to Draft?\')">';
		wp_nonce_field( 'nurax_cat_apply' );
		echo '<input type="hidden" name="action" value="nurax_cat_apply">';
		echo '<section class="nd-card"><h3>1. WhatsApp products</h3><div class="nd-table-wrap"><table class="nd-table nc-t"><thead><tr><th class="nc-on">Apply</th><th>WhatsApp title (editable)</th><th>Price (KSh)</th><th>Website product it replaces</th><th>Now on website</th></tr></thead><tbody>';
		foreach ( $items as $i => $it ) {
			$pid = (int) $it['pid'];
			$cur = $pid && isset( $all[ $pid ] ) ? $all[ $pid ] : null;
			$synced = $cur && (string) $cur->get_meta( '_nura_wa_key' ) === (string) $it['key'];
			if ( ! $pid ) {
				foreach ( $all as $aid => $ap ) {
					if ( (string) $ap->get_meta( '_nura_wa_key' ) === (string) $it['key'] ) {
						$pid    = (int) $aid;
						$cur    = $ap;
						$synced = true;
					}
				}
			}
			$on  = ! $synced && ( $cur ? 'none' !== $it['conf'] : true );
			echo '<tr class="nc-r nc-r--' . esc_attr( $it['conf'] ) . '"><td class="nc-on"><input type="checkbox" name="row[' . (int) $i . '][on]" value="1"' . checked( $on, true, false ) . ' aria-label="Apply this row"></td>';
			echo '<td><input class="nc-in" type="text" name="row[' . (int) $i . '][title]" value="' . esc_attr( $it['title'] ) . '"><small>' . esc_html( $it['colours'] ? 'Colours: ' . implode( ', ', $it['colours'] ) : 'No colours listed on WhatsApp' ) . '</small><span class="nc-b nc-b--' . esc_attr( $synced ? 'high' : $it['conf'] ) . '">' . esc_html( $synced ? 'Synced - live' : ( 'high' === $it['conf'] ? 'Clear match' : ( 'check' === $it['conf'] ? 'Please confirm' : 'New product' ) ) ) . '</span></td>';
			echo '<td><input class="nc-in nc-in--p" type="text" inputmode="numeric" name="row[' . (int) $i . '][price]" value="' . esc_attr( $it['price'] ? (string) $it['price'] : '' ) . '" placeholder="keep current"></td>';
			echo '<td><select class="nc-sel" name="row[' . (int) $i . '][pid]"><option value="">- choose a product -</option><option value="new"' . selected( ! $cur, true, false ) . '>+ Create as a new product (Draft until you add the photo)</option>';
			foreach ( $all as $id => $p ) {
				echo '<option value="' . (int) $id . '"' . selected( $pid, $id, false ) . '>' . esc_html( $p->get_name() . ( 'publish' !== $p->get_status() ? ' (' . $p->get_status() . ')' : '' ) ) . '</option>';
			}
			echo '</select></td><td>' . ( $cur ? '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '" target="_blank" rel="noopener">' . esc_html( $cur->get_name() ) . '</a><small>' . esc_html( self::price_of( $cur ) ) . '</small>' : '<span class="na-muted">-</span>' ) . '</td></tr>';
		}
		echo '</tbody></table></div></section>';
		echo '<section class="nd-card"><h3>2. Other live products - move to Draft</h3><p class="na-muted">These are live on the website but not in the WhatsApp catalogue. Ticked ones move to Draft (hidden, not deleted - you can publish them again any time). A product you choose in step 1 is never drafted.</p><ul class="nc-drafts">';
		$n = 0;
		foreach ( $all as $id => $p ) {
			if ( 'publish' !== $p->get_status() || isset( $used[ $id ] ) ) {
				continue;
			}
			$n++;
			echo '<li><label><input type="checkbox" name="draft[]" value="' . (int) $id . '" checked> <span>' . esc_html( $p->get_name() ) . '</span><small>' . esc_html( self::price_of( $p ) ) . '</small></label></li>';
		}
		echo '</ul>' . ( $n ? '' : '<p class="nd-empty">No other live products.</p>' ) . '</section>';
		echo '<p class="nc-go"><button class="nd-btn nd-btn--primary nd-btn--lg">Apply to the website</button> <span class="na-muted">Backed up first - "Undo last sync" restores everything.</span></p></form>';
		self::render_colours();
		echo '</div></div>';
	}
}
