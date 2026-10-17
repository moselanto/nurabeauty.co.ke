<?php
/**
 * NURA Desk - Social (Facebook, Instagram, TikTok) (NURA Experience 1.61.0).
 *
 *  - Product catalogue feeds for Meta Commerce Manager (Facebook + Instagram
 *    Shop, Advantage+ catalogue ads) and TikTok Ads catalogues. One row per
 *    sellable variation, live price + stock, tracked links.
 *  - Social sales analysis from WooCommerce order attribution (utm source,
 *    referrer) plus WhatsApp orders: which platform actually sells.
 *  - Post kit per wig: photos to download, ready captions for Instagram,
 *    Facebook and TikTok, hashtag sets and tracked links.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Social {

	public static function hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'feed' ) );
	}

	public static function key() {
		$k = get_option( 'nurax_feed_key' );
		if ( ! $k ) {
			$k = wp_generate_password( 16, false, false );
			update_option( 'nurax_feed_key', $k, false );
		}
		return $k;
	}

	public static function feed_url( $platform ) {
		return add_query_arg( array( 'nura_feed' => $platform, 'k' => self::key() ), home_url( '/' ) );
	}

	public static function track( $url, $source, $medium = 'social', $campaign = 'organic' ) {
		return add_query_arg( array( 'utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign ), $url );
	}

	/* ------------------------------------------------------------------ */
	/* Catalogue feeds                                                     */
	/* ------------------------------------------------------------------ */

	public static function feed() {
		if ( empty( $_GET['nura_feed'] ) || ! function_exists( 'wc_get_products' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$plat = 'tiktok' === $_GET['nura_feed'] ? 'tiktok' : 'meta'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$k    = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! hash_equals( self::key(), $k ) ) {
			status_header( 403 );
			exit;
		}
		$cache = get_transient( 'nurax_feed_' . $plat );
		if ( false === $cache ) {
			$cache = self::build( $plat );
			set_transient( 'nurax_feed_' . $plat, $cache, HOUR_IN_SECONDS );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: inline; filename=nura-' . $plat . '-catalogue.csv' );
		echo $cache; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV.
		exit;
	}

	private static function csv_line( $a ) {
		$out = array();
		foreach ( $a as $v ) {
			// Security (1.73.0): neutralise spreadsheet formulas (CSV injection).
			$v     = NURAX_Desk::csv_cell( str_replace( array( "\r", "\n" ), ' ', (string) $v ) );
			$out[] = '"' . str_replace( '"', '""', $v ) . '"';
		}
		return implode( ',', $out ) . "\n";
	}

	private static function build( $plat ) {
		$src  = 'tiktok' === $plat ? 'tiktok' : 'facebook';
		$head = array( 'tiktok' === $plat ? 'sku_id' : 'id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'link', 'image_link', 'additional_image_link', 'brand', 'item_group_id', 'color', 'size', 'product_type' );
		$csv  = self::csv_line( $head );
		$cur  = get_woocommerce_currency();
		$ids  = wc_get_products( array( 'status' => 'publish', 'limit' => 3000, 'return' => 'ids', 'visibility' => 'catalog' ) );
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$cats  = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
			$type  = is_array( $cats ) ? implode( ' > ', array_slice( $cats, 0, 3 ) ) : '';
			$desc  = trim( wp_strip_all_tags( $p->get_short_description() ? $p->get_short_description() : $p->get_description() ) );
			$desc  = mb_substr( '' !== $desc ? $desc : $p->get_name() . ' from NURA Beauty, Nairobi.', 0, 4900 );
			$gal   = array();
			foreach ( array_slice( $p->get_gallery_image_ids(), 0, 5 ) as $gid ) {
				$u = wp_get_attachment_image_url( $gid, 'large' );
				if ( $u ) {
					$gal[] = $u;
				}
			}
			$rows = $p->is_type( 'variable' ) ? $p->get_children() : array( $id );
			foreach ( $rows as $vid ) {
				$v = (int) $vid === (int) $id ? $p : wc_get_product( $vid );
				if ( ! $v || '' === $v->get_price() ) {
					continue;
				}
				$img   = wp_get_attachment_image_url( $v->get_image_id() ? $v->get_image_id() : $p->get_image_id(), 'large' );
				if ( ! $img ) {
					continue; // Catalogues reject items without an image.
				}
				$attrs = $v->is_type( 'variation' ) ? $v->get_attributes() : array();
				$color = '';
				$size  = '';
				foreach ( $attrs as $an => $av ) {
					$label = wc_attribute_label( str_replace( 'attribute_', '', $an ) );
					$val   = $av;
					$term  = taxonomy_exists( $an ) ? get_term_by( 'slug', $av, $an ) : null;
					if ( $term ) {
						$val = $term->name;
					}
					if ( false !== stripos( $label, 'colo' ) ) {
						$color = $val;
					} elseif ( false !== stripos( $label, 'length' ) || false !== stripos( $label, 'size' ) || false !== stripos( $label, 'inch' ) ) {
						$size = $val;
					}
				}
				$title = $p->get_name() . ( $v->is_type( 'variation' ) ? ' - ' . wp_strip_all_tags( wc_get_formatted_variation( $v, true, false, false ) ) : '' );
				$reg   = (float) $v->get_regular_price();
				$sale  = $v->is_on_sale() ? (float) $v->get_sale_price() : 0;
				$csv  .= self::csv_line( array(
					$v->get_sku() ? $v->get_sku() : 'NURA-' . $v->get_id(),
					mb_substr( $title, 0, 150 ),
					$desc,
					$v->is_in_stock() ? 'in stock' : 'out of stock',
					'new',
					number_format( $reg > 0 ? $reg : (float) $v->get_price(), 2, '.', '' ) . ' ' . $cur,
					$sale > 0 ? number_format( $sale, 2, '.', '' ) . ' ' . $cur : '',
					self::track( $v->get_permalink(), $src, 'paid_social', 'catalogue' ),
					$img,
					implode( ',', $gal ),
					'NURA Beauty',
					$p->is_type( 'variable' ) ? 'NURA-' . $id : '',
					$color,
					$size,
					$type,
				) );
			}
		}
		return $csv;
	}

	/* ------------------------------------------------------------------ */
	/* Analysis                                                            */
	/* ------------------------------------------------------------------ */

	public static function source_of( $o ) {
		$s = strtolower( (string) $o->get_meta( '_wc_order_attribution_utm_source' ) . ' ' . (string) $o->get_meta( '_wc_order_attribution_referrer' ) . ' ' . (string) $o->get_meta( '_nura_channel' ) );
		if ( preg_match( '/tiktok/', $s ) ) {
			return 'tiktok';
		}
		if ( preg_match( '/instagram|\big\b|l\.instagram/', $s ) ) {
			return 'instagram';
		}
		if ( preg_match( '/facebook|\bfb\b|fbclid|m\.facebook|l\.facebook/', $s ) ) {
			return 'facebook';
		}
		if ( preg_match( '/whatsapp|wa\.me/', $s ) ) {
			return 'whatsapp';
		}
		if ( preg_match( '/google|bing/', $s ) ) {
			return 'search';
		}
		if ( preg_match( '/walk-in|phone/', $s ) ) {
			return 'studio';
		}
		$t = (string) $o->get_meta( '_wc_order_attribution_source_type' );
		return 'typein' === $t ? 'direct' : ( '' === trim( $s ) ? 'unknown' : 'other' );
	}

	public static function labels() {
		return array( 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'whatsapp' => 'WhatsApp', 'search' => 'Google / search', 'direct' => 'Typed the address', 'studio' => 'Studio (walk-in / phone)', 'other' => 'Other websites', 'unknown' => 'Not tracked' );
	}

	private static function analysis( $days ) {
		$from = gmdate( 'Y-m-d', strtotime( NURAX_Desk::today() . ' -' . ( $days - 1 ) . ' days' ) );
		$res  = NURAX_Desk_POS::orders( $from, NURAX_Desk::today(), 3000 );
		$res  = is_object( $res ) ? $res->orders : (array) $res;
		$out  = array();
		foreach ( array_keys( self::labels() ) as $k ) {
			$out[ $k ] = array( 'n' => 0, 'sum' => 0 );
		}
		foreach ( $res as $o ) {
			if ( ! NURAX_Desk_POS::is_paid( $o ) ) {
				continue;
			}
			$k = self::source_of( $o );
			$out[ $k ]['n']++;
			$out[ $k ]['sum'] += (float) $o->get_total();
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Captions                                                            */
	/* ------------------------------------------------------------------ */

	public static function hashtags() {
		return array(
			'core'  => '#NURABeauty #WearYourCrown #WigsKenya #WigsNairobi #NairobiWigs #HumanHairWigsKenya',
			'style' => '#LaceFrontWig #BobWig #CurlyWig #BodyWave #BoneStraight #HeadbandWig #ClosureWig',
			'local' => '#NairobiBaddies #KenyanWomen #NairobiFashion #254 #KenyanTikTok #NairobiStyle',
			'offer' => '#LipaMdogoMdogo #MpesaPay #SameDayDeliveryNairobi',
		);
	}

	public static function captions( $p ) {
		$s     = NURAX_Desk::settings();
		$price = (float) wc_get_price_to_display( $p );
		if ( $p->is_type( 'variable' ) ) {
			$price = (float) $p->get_variation_price( 'min', true );
		}
		$from  = $p->is_type( 'variable' ) ? 'from ' : '';
		$lmm   = $price >= (float) $s['lmm_min'] ? ceil( ( $price - ceil( $price * (int) $s['lmm_deposit'] / 100 ) ) / max( NURAX_Desk::week_options() ) ) : 0;
		$name  = $p->get_name();
		$link  = $p->get_permalink();
		$h     = self::hashtags();
		$lm    = $lmm ? "Lipa Mdogo Mdogo: " . (int) $s['lmm_deposit'] . "% deposit, then about " . NURAX_Desk::money( $lmm ) . " a week. No interest.\n" : '';
		return array(
			'instagram' => "Your new crown just landed. \n\n" . $name . "\n" . NURAX_Desk::money( $price ) . ' ' . $from . "| M-Pesa | Same-day delivery in Nairobi\n" . $lm . "\nTap the link in our bio or DM \"CROWN\" to order.\n.\n.\n" . $h['core'] . ' ' . $h['style'] . ' ' . $h['local'],
			'facebook'  => $name . " - " . $from . NURAX_Desk::money( $price ) . "\n\nHand-finished in Nairobi, quality-checked and photographed on our own display, so what you see is what you get.\n" . $lm . "Same-day delivery in Nairobi before 5pm, countrywide in 1-3 days. Pay with M-Pesa.\n\nShop: " . self::track( $link, 'facebook', 'social', 'organic' ) . "\nWhatsApp: wa.me/" . NURAX_WA_Order::whatsapp_number(),
			'tiktok'    => "POV: you found your forever wig \n" . $name . ' ' . $from . NURAX_Desk::money( $price ) . ( $lmm ? ' | Lipa Mdogo Mdogo ' . NURAX_Desk::money( $lmm ) . '/week' : '' ) . "\nLink in bio | Nairobi same-day delivery\n" . '#WigsKenya #NairobiTikTok #KenyanTikTok #WigTransformation #LaceFrontWig #NURABeauty #fyp',
			'status'    => $name . "\n" . $from . NURAX_Desk::money( $price ) . ( $lmm ? "\nOr Lipa Mdogo Mdogo " . NURAX_Desk::money( $lmm ) . '/week' : '' ) . "\nOrder: " . self::track( $link, 'whatsapp', 'status', 'organic' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		$days = isset( $_GET['d'] ) ? max( 7, min( 365, absint( $_GET['d'] ) ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pid  = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		NURAX_Desk::header( NURAX_Desk::P_SOC, 'Social - Facebook, Instagram, TikTok' );

		// 1. Analysis.
		$a     = function_exists( 'wc_get_orders' ) ? self::analysis( $days ) : array();
		$total = 0;
		foreach ( $a as $r ) {
			$total += $r['sum'];
		}
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Which platform brings sales</h2><nav class="nd-pills">';
		foreach ( array( 7, 30, 90 ) as $dd ) {
			echo '<a class="nd-pill' . ( $dd === $days ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_SOC, array( 'd' => $dd ) ) ) . '">' . (int) $dd . ' days</a>';
		}
		echo '</nav></header><div class="nd-bars">';
		foreach ( self::labels() as $k => $lbl ) {
			if ( empty( $a[ $k ]['n'] ) ) {
				continue;
			}
			$pct = $total > 0 ? round( $a[ $k ]['sum'] / $total * 100 ) : 0;
			echo '<div class="nd-bar nd-bar--' . esc_attr( $k ) . '"><span class="nd-bar__l">' . esc_html( $lbl ) . '</span><span class="nd-bar__t"><span style="width:' . (int) max( 2, $pct ) . '%"></span></span><span class="nd-bar__v"><b>' . esc_html( NURAX_Desk::money( $a[ $k ]['sum'] ) ) . '</b> ' . (int) $a[ $k ]['n'] . ' sales - ' . (int) $pct . '%</span></div>';
		}
		if ( ! $total ) {
			echo '<p class="nd-empty-s">No paid sales in this period yet.</p>';
		}
		echo '</div><p class="nd-mut nd-small">Based on WooCommerce order attribution (where the customer came from) and the channel you pick at the till. Always share the tracked links below so every sale is credited to the right platform.</p></section>';

		// 2. Connect catalogues.
		$meta_px = class_exists( 'NURAX_Retargeting' ) ? (string) ( NURAX_Retargeting::instance()->settings()['meta_pixel_id'] ?? '' ) : '';
		echo '<div class="nd-grid2">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Facebook &amp; Instagram Shop</h2>' . NURAX_Desk::badge( $meta_px ? 'confirmed' : 'request', $meta_px ? 'Pixel connected' : 'Pixel not set' ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p class="nd-mut">Your full catalogue, updated every hour with live prices and stock. Use it for the Instagram/Facebook Shop and for catalogue ads that show each person the wigs they looked at.</p>';
		self::copy_row( self::feed_url( 'meta' ) );
		echo '<ol class="nd-steps"><li>Open <a href="https://business.facebook.com/commerce" target="_blank" rel="noopener">Meta Commerce Manager</a> &gt; Catalogue &gt; Data sources &gt; Data feed &gt; Scheduled feed.</li><li>Paste the link above, choose currency KES, update daily.</li><li>Connect the catalogue to your Facebook Page and Instagram account, then tag products in posts and reels.</li><li>' . ( $meta_px ? 'Pixel is set - also add the Conversions API token so ad sales are counted accurately.' : 'Add your Meta Pixel ID under WooCommerce &gt; NURA Retargeting.' ) . '</li></ol></section>';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>TikTok catalogue</h2>' . NURAX_Desk::badge( 'muted', 'Ads catalogue' ) . '</header>';
		echo '<p class="nd-mut">For TikTok catalogue / video shopping ads. TikTok Shop is not open to Kenyan sellers yet, so customers tap through to your website or WhatsApp.</p>';
		self::copy_row( self::feed_url( 'tiktok' ) );
		echo '<ol class="nd-steps"><li>Open <a href="https://ads.tiktok.com" target="_blank" rel="noopener">TikTok Ads Manager</a> &gt; Assets &gt; Catalogues &gt; Create &gt; Data feed (scheduled).</li><li>Paste the link above, currency KES, update daily.</li><li>Install the TikTok Pixel on the site so TikTok learns who buys.</li></ol></section>';
		echo '</div>';

		// 3. Post kit.
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Post kit - everything to post a wig in 1 minute</h2></header>';
		echo '<form method="get" class="nd-form nd-inrow"><input type="hidden" name="page" value="' . esc_attr( NURAX_Desk::P_SOC ) . '"><select class="wc-product-search" name="product" data-placeholder="Pick a wig..." data-action="woocommerce_json_search_products" style="min-width:380px">';
		if ( $pid && ( $pp = wc_get_product( $pid ) ) ) {
			echo '<option value="' . (int) $pid . '" selected>' . esc_html( wp_strip_all_tags( $pp->get_formatted_name() ) ) . '</option>';
		}
		echo '</select><button class="nd-btn nd-btn--primary">Build post kit</button></form>';
		$p = $pid && function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
		if ( $p ) {
			$imgs = array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) );
			echo '<div class="nd-kit"><div class="nd-kit__imgs">';
			foreach ( array_slice( $imgs, 0, 8 ) as $iid ) {
				$full = wp_get_attachment_image_url( $iid, 'full' );
				$th   = wp_get_attachment_image_url( $iid, 'medium' );
				echo '<a href="' . esc_url( $full ) . '" download target="_blank" rel="noopener" title="Download"><img src="' . esc_url( $th ) . '" alt=""></a>';
			}
			echo '</div><div class="nd-kit__caps">';
			$names = array( 'instagram' => 'Instagram post / reel', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'status' => 'WhatsApp status' );
			foreach ( self::captions( $p ) as $k => $cap ) {
				echo '<div class="nd-kit__cap"><header><strong>' . esc_html( $names[ $k ] ) . '</strong><button type="button" class="nd-ico-btn" data-nd-copy="' . esc_attr( $cap ) . '">Copy</button></header><pre>' . esc_html( $cap ) . '</pre></div>';
			}
			echo '</div></div><h3 class="nd-sub">Tracked links (put these in bios, stories and ads)</h3><div class="nd-stack">';
			foreach ( array( 'instagram' => 'Instagram bio / story', 'tiktok' => 'TikTok bio', 'facebook' => 'Facebook post' ) as $k => $l ) {
				echo '<div class="nd-copyrow"><span>' . esc_html( $l ) . '</span>';
				self::copy_row( self::track( $p->get_permalink(), $k ) );
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo '<p class="nd-mut">Pick any wig to get its photos, ready captions for each platform, hashtags and tracked links.</p>';
		}
		echo '</section>';

		// 4. Hashtags.
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Hashtag sets</h2></header><div class="nd-stack">';
		foreach ( self::hashtags() as $k => $tags ) {
			echo '<div class="nd-copyrow"><span>' . esc_html( ucfirst( $k ) ) . '</span><code>' . esc_html( $tags ) . '</code><button type="button" class="nd-ico-btn" data-nd-copy="' . esc_attr( $tags ) . '">Copy</button></div>';
		}
		echo '</div></section>';
		NURAX_Desk::footer();
	}

	private static function copy_row( $url ) {
		echo '<div class="nd-copyrow"><input type="text" readonly value="' . esc_attr( $url ) . '" onclick="this.select()"><button type="button" class="nd-ico-btn" data-nd-copy="' . esc_attr( $url ) . '">Copy</button></div>';
	}
}
