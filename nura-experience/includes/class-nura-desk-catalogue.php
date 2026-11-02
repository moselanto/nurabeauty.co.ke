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

	public static function describe( $it, $title, $price, $product ) {
		$text  = trim( (string) $it['text'] );
		$cols  = array_values( array_filter( (array) $it['colours'] ) );
		$hair  = $product ? trim( (string) $product->get_attribute( 'pa_hair-type' ) ) : '';
		$name  = strtolower( $title . ' ' . $text . ' ' . $hair . ' ' . ( $product ? $product->get_name() : '' ) );
		$human = self::has( $name, '/human hair|100%/' ) && ! self::has( $name, '/semi|blend|fibre|synthetic/' );
		$semi  = self::has( $name, '/semi|blend/' );
		$kind  = $human ? '100% human hair' : ( $semi ? 'human-hair blend (semi-human)' : 'premium heat-resistant fibre' );
		$cap   = self::has( $name, '/headband/' ) ? 'headband wig' : ( self::has( $name, '/13\s*[x\*]\s*4|frontal/' ) ? '13x4 lace frontal wig' : ( self::has( $name, '/2\s*x\s*2/' ) ? '2x2 lace closure wig' : ( self::has( $name, '/4\s*[x\*]\s*4|closure/' ) ? '4x4 lace closure wig' : 'wig' ) ) );
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
		$h   = '<p class="nura-answer"><strong>In short:</strong> The ' . esc_html( $title ) . ' is a ' . esc_html( $kind . ' ' . $cap ) . ( $p ? ' at ' . esc_html( $p ) : '' ) . ( $cols ? ', available in ' . esc_html( implode( ', ', $cols ) ) : '' ) . '. Same-day delivery in Nairobi for orders before 5 PM, countrywide delivery in 1-3 days, pay by M-Pesa or card.</p>';
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
			. '<li>Nairobi: same-day rider delivery for orders before 5 PM (from KSh 300). Countrywide: bus/shuttle parcel or courier in 1-3 days.</li>'
			. '<li>Prefer to see it first? Visit our studio at Stanbank House, 2nd Floor, Room 20, Moi Avenue, Nairobi CBD.</li></ul>';
		$h .= '<h2>Need help choosing?</h2><p>Chat with a NURA stylist on WhatsApp (+254 714 994 898) or try the <a href="/ai-wig-finder/">Wig Finder</a>. We also install, style and revamp wigs at the studio.</p>';
		$short = esc_html( $text ? rtrim( $text, '.' ) . '.' : ucfirst( $kind . ' ' . $cap ) . '.' ) . ( $cols ? ' <strong>Colours:</strong> ' . esc_html( implode( ', ', $cols ) ) . '.' : '' );
		return array( $h, '<p>' . $short . '</p>' );
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
			$pid = absint( $r['pid'] );
			$p   = wc_get_product( $pid );
			if ( ! $p || isset( $mapped[ $pid ] ) ) {
				continue; // never write two WhatsApp items onto one product
			}
			$title = sanitize_text_field( isset( $r['title'] ) ? $r['title'] : $items[ $i ]['title'] );
			$price = isset( $r['price'] ) && '' !== trim( (string) $r['price'] ) ? (float) preg_replace( '/[^0-9.]/', '', (string) $r['price'] ) : null;
			if ( '' === $title ) {
				continue;
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
		update_option( self::BACKUP, $backup, false );
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'catalogue', 'Catalogue sync: ' . $done . ' product(s) updated from WhatsApp, ' . $dr . ' moved to draft' );
		}
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array( 'done' => $done, 'drafted' => $dr ) ) );
		exit;
	}

	public static function h_undo() {
		self::guard( 'nurax_cat_undo' );
		$b = get_option( self::BACKUP );
		$n = 0;
		if ( is_array( $b ) && ! empty( $b['products'] ) ) {
			foreach ( $b['products'] as $pid => $s ) {
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
		NURAX_Desk::header( self::PAGE, 'Catalogue sync', $act );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['done'] ) ) {
			echo '<div class="nd-alert nd-alert--ok nc-msg">Done: ' . (int) $_GET['done'] . ' product(s) updated from WhatsApp and ' . (int) ( $_GET['drafted'] ?? 0 ) . ' moved to draft. The page cache was cleared. Use "Undo last sync" if anything looks wrong.</div>';
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
			$on  = $cur && 'none' !== $it['conf'];
			echo '<tr class="nc-r nc-r--' . esc_attr( $it['conf'] ) . '"><td class="nc-on"><input type="checkbox" name="row[' . (int) $i . '][on]" value="1"' . checked( $on, true, false ) . ' aria-label="Apply this row"></td>';
			echo '<td><input class="nc-in" type="text" name="row[' . (int) $i . '][title]" value="' . esc_attr( $it['title'] ) . '"><small>' . esc_html( $it['colours'] ? 'Colours: ' . implode( ', ', $it['colours'] ) : 'No colours listed on WhatsApp' ) . '</small><span class="nc-b nc-b--' . esc_attr( $it['conf'] ) . '">' . esc_html( 'high' === $it['conf'] ? 'Clear match' : ( 'check' === $it['conf'] ? 'Please confirm' : 'No product found' ) ) . '</span></td>';
			echo '<td><input class="nc-in nc-in--p" type="text" inputmode="numeric" name="row[' . (int) $i . '][price]" value="' . esc_attr( $it['price'] ? (string) $it['price'] : '' ) . '" placeholder="keep current"></td>';
			echo '<td><select class="nc-sel" name="row[' . (int) $i . '][pid]"><option value="">- choose a product -</option>';
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
		echo '<p class="nc-go"><button class="nd-btn nd-btn--primary nd-btn--lg">Apply to the website</button> <span class="na-muted">Backed up first - "Undo last sync" restores everything.</span></p></form></div></div>';
	}
}
