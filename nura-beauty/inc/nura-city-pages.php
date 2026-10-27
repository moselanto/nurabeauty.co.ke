<?php
/**
 * NURA city delivery pages (theme 1.68.0): Mombasa, Kisumu, Nakuru, Eldoret.
 *
 * Each page answers "where can I buy wigs in <city>" with information that is
 * specific and live for that county: the real delivery fee and time from the
 * WhatsApp-order delivery calculator (WooCommerce shipping zones first), how to
 * pay outside Nairobi, popular in-stock wigs, live prices and a short FAQ.
 * Pages are created once and never overwritten; the body is rendered live.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** slug => [ city, county, nearby towns ] */
function nura_city_list() {
	return apply_filters( 'nura_city_pages', array(
		'wigs-mombasa' => array( 'Mombasa', 'Mombasa', 'Nyali, Bamburi, Likoni, Mtwapa and Mombasa Island' ),
		'wigs-kisumu'  => array( 'Kisumu', 'Kisumu', 'Kisumu CBD, Milimani, Mamboleo and Kondele' ),
		'wigs-nakuru'  => array( 'Nakuru', 'Nakuru', 'Nakuru town, Milimani, Lanet and Naivasha' ),
		'wigs-eldoret' => array( 'Eldoret', 'Uasin Gishu', 'Eldoret town, Elgon View, Kapsoya and Langas' ),
	) );
}

/** Live delivery facts for a county. */
function nura_city_delivery( $county ) {
	$out = array( 'fee' => '', 'eta' => __( 'Delivered in 1-3 business days', 'nura-beauty' ), 'free_over' => 0 );
	if ( class_exists( 'NURAX_WA_Order' ) && method_exists( 'NURAX_WA_Order', 'ship_quote' ) ) {
		$q = NURAX_WA_Order::ship_quote( $county, 0 );
		if ( ! empty( $q['eta'] ) ) {
			$out['eta'] = $q['eta'];
		}
		if ( ! empty( $q['known'] ) ) {
			$out['fee'] = (float) $q['fee'] > 0 ? 'KSh ' . number_format( (float) $q['fee'] ) : __( 'Free', 'nura-beauty' );
		}
		$out['free_over'] = isset( $q['free_over'] ) ? (float) $q['free_over'] : 0;
	}
	return $out;
}

function nura_city_faqs( $city, $d ) {
	$fee = '' !== $d['fee'] ? sprintf( __( 'Delivery to %1$s costs %2$s and takes %3$s.', 'nura-beauty' ), $city, $d['fee'], strtolower( $d['eta'] ) ) : sprintf( __( 'Delivery to %1$s takes %2$s; we confirm the exact fee on WhatsApp before you pay.', 'nura-beauty' ), $city, strtolower( $d['eta'] ) );
	return array(
		array( sprintf( __( 'Do you deliver wigs to %s?', 'nura-beauty' ), $city ), __( 'Yes.', 'nura-beauty' ) . ' ' . $fee ),
		array( sprintf( __( 'How do I pay for a wig delivered to %s?', 'nura-beauty' ), $city ), __( 'Pay by M-Pesa or card (Visa, Mastercard) securely through Paystack, or by bank transfer, or to our M-Pesa Buy Goods Till 1679269 for WhatsApp orders. We do not offer cash on delivery.', 'nura-beauty' ) ),
		array( sprintf( __( 'Can I return a wig from %s?', 'nura-beauty' ), $city ), __( 'Yes. Unworn, unaltered wigs can be returned within 7 days of delivery by courier, or at our Imenti House studio in Nairobi CBD. Worn, cut or installed wigs cannot be returned for hygiene reasons.', 'nura-beauty' ) ),
		array( __( 'Can I pay in instalments?', 'nura-beauty' ), __( 'Yes, with Lipa Mdogo Mdogo: a 50% deposit today and the balance within 1 or 2 weeks. We send the wig once it is fully paid.', 'nura-beauty' ) ),
	);
}

function nura_city_render( $slug ) {
	$list = nura_city_list();
	if ( ! isset( $list[ $slug ] ) ) {
		return '';
	}
	list( $city, $county, $towns ) = $list[ $slug ];
	$d    = nura_city_delivery( $county );
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$wa   = function_exists( 'nura_opt' ) && nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
	$wa  .= ( false === strpos( $wa, '?' ) ? '?' : '&' ) . 'text=' . rawurlencode( sprintf( 'Hello NURA, I am in %s and would like help choosing a wig.', $city ) );
	$top  = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'limit' => 6, 'orderby' => 'popularity', 'stock_status' => 'instock', 'visibility' => 'catalog' ) ) : array();
	$prices = get_page_by_path( 'wig-prices-kenya' );
	ob_start();
	?>
	<div class="ncp">
		<p class="npg-answer"><strong><?php esc_html_e( 'Short answer:', 'nura-beauty' ); ?></strong> <?php echo esc_html( sprintf( __( 'yes, NURA Beauty delivers wigs to %1$s and across %2$s County. Order online or on WhatsApp, pay by M-Pesa or card, and your wig is %3$s.', 'nura-beauty' ), $city, $county, strtolower( $d['eta'] ) ) ); ?></p>

		<ul class="npg-facts ncp-facts">
			<li><strong><?php esc_html_e( 'Delivery fee', 'nura-beauty' ); ?></strong><span><?php echo esc_html( '' !== $d['fee'] ? $d['fee'] : __( 'Confirmed on WhatsApp', 'nura-beauty' ) ); ?><?php echo $d['free_over'] > 0 ? esc_html( ' - ' . sprintf( __( 'free over KSh %s', 'nura-beauty' ), number_format( $d['free_over'] ) ) ) : ''; ?></span></li>
			<li><strong><?php esc_html_e( 'Delivery time', 'nura-beauty' ); ?></strong><span><?php echo esc_html( $d['eta'] ); ?></span></li>
			<li><strong><?php esc_html_e( 'Payment', 'nura-beauty' ); ?></strong><span><?php esc_html_e( 'M-Pesa, card or bank transfer', 'nura-beauty' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Areas', 'nura-beauty' ); ?></strong><span><?php echo esc_html( $towns ); ?></span></li>
		</ul>

		<?php if ( $top ) : ?>
			<section class="ncp-top">
				<h2><?php echo esc_html( sprintf( __( 'Popular wigs delivered to %s', 'nura-beauty' ), $city ) ); ?></h2>
				<div class="ncp-grid">
					<?php foreach ( $top as $p ) :
						$img = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
						$hair = function_exists( 'nura_hub_hair' ) ? nura_hub_hair( $p ) : '';
						$lab  = function_exists( 'nura_hub_labels' ) ? nura_hub_labels() : array();
						?>
						<a class="ncp-card" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>">
							<?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $p->get_name() ) ); ?>" loading="lazy" decoding="async" width="300" height="300"><?php endif; ?>
							<span class="ncp-card__name"><?php echo esc_html( wp_strip_all_tags( $p->get_name() ) ); ?></span>
							<?php if ( $hair && isset( $lab[ $hair ] ) ) : ?><span class="ncp-card__hair"><?php echo esc_html( $lab[ $hair ] ); ?></span><?php endif; ?>
							<span class="ncp-card__price"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section>
			<h2><?php echo esc_html( sprintf( __( 'Wig prices for %s', 'nura-beauty' ), $city ) ); ?></h2>
			<?php echo function_exists( 'nura_hub_price_table' ) ? nura_hub_price_table() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<?php if ( $prices ) : ?><p><a href="<?php echo esc_url( get_permalink( $prices ) ); ?>"><?php esc_html_e( 'See the full live price list', 'nura-beauty' ); ?> &rarr;</a></p><?php endif; ?>
		</section>

		<section class="ncp-how">
			<h2><?php echo esc_html( sprintf( __( 'How to order from %s', 'nura-beauty' ), $city ) ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Choose your wig online, or send us a photo of the look you want on WhatsApp.', 'nura-beauty' ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Select %s County at checkout to see the delivery fee.', 'nura-beauty' ), $county ) ); ?></li>
				<li><?php esc_html_e( 'Pay by M-Pesa or card. We confirm your order and send the tracking details.', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Collect from the courier or receive it at your address.', 'nura-beauty' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'Every wig is quality-checked before dispatch and clearly labelled as 100% human hair, human-hair blend or heat-resistant fibre. Need a fitting? Ask for a free video consultation on WhatsApp.', 'nura-beauty' ); ?></p>
		</section>

		<section class="npg-faq">
			<h2><?php echo esc_html( sprintf( __( 'Wigs in %s: questions', 'nura-beauty' ), $city ) ); ?></h2>
			<?php foreach ( nura_city_faqs( $city, $d ) as $qa ) : ?>
				<details><summary><?php echo esc_html( $qa[0] ); ?></summary><p><?php echo esc_html( $qa[1] ); ?></p></details>
			<?php endforeach; ?>
		</section>

		<section class="npg-cta">
			<div><h2><?php echo esc_html( sprintf( __( 'Shopping from %s?', 'nura-beauty' ), $city ) ); ?></h2><p><?php esc_html_e( 'Tell us your budget and style and a NURA stylist will help you choose on WhatsApp.', 'nura-beauty' ); ?></p></div>
			<div class="npg-cta__btns"><a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $wa ); ?>"><?php esc_html_e( 'Chat on WhatsApp', 'nura-beauty' ); ?></a><a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'Shop all wigs', 'nura-beauty' ); ?></a></div>
		</section>
	</div>
	<?php
	return (string) ob_get_clean();
}

function nura_city_current() {
	foreach ( array_keys( nura_city_list() ) as $slug ) {
		if ( is_page( $slug ) ) {
			return $slug;
		}
	}
	return '';
}

add_filter( 'the_content', function ( $content ) {
	$slug = nura_city_current();
	if ( '' === $slug || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$html = nura_city_render( $slug );
	return '' !== $html ? $html : $content;
}, 5 );

add_action( 'wp_head', function () {
	$slug = nura_city_current();
	if ( '' === $slug ) {
		return;
	}
	$list  = nura_city_list();
	$d     = nura_city_delivery( $list[ $slug ][1] );
	$items = array();
	foreach ( nura_city_faqs( $list[ $slug ][0], $d ) as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ) ) . '</script>' . "\n";
	// Shared styles with the price page, plus the product grid.
	echo '<style id="nura-city-css">.ncp{max-width:1100px;margin:0 auto}.ncp h2{font-size:clamp(1.4rem,2.5vw,1.9rem);margin:2.2rem 0 .8rem}.npg-answer{font-size:1.08rem;line-height:1.7;background:#FAF7F0;border-left:3px solid #C9A24B;padding:1.1rem 1.3rem;border-radius:0 12px 12px 0}.npg-facts{list-style:none;padding:0;margin:1.4rem 0;display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem}.npg-facts li{background:#FAF7F0;border-radius:12px;padding:.9rem 1rem;display:flex;flex-direction:column;gap:.2rem}.npg-facts strong{font-size:.9rem}.npg-facts span{font-size:.84rem;color:#555;line-height:1.45}.ncp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}.ncp-card{border:1px solid #ECE4D3;border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column;background:#fff;transition:transform .2s,box-shadow .2s}.ncp-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(14,14,14,.08)}.ncp-card img{width:100%;aspect-ratio:1/1;object-fit:cover}.ncp-card__name{font-weight:600;padding:.8rem .9rem 0;line-height:1.35}.ncp-card__hair{font-size:.78rem;color:#8a6d1f;padding:.25rem .9rem 0}.ncp-card__price{padding:.4rem .9rem .9rem;font-weight:700}.ncp-how ol{padding-left:1.2rem;line-height:1.8}.npg-faq details{border-bottom:1px solid #ECE4D3;padding:1rem 0}.npg-faq summary{cursor:pointer;font-weight:600}.npg-faq details p{margin:.6rem 0 0;color:#555;line-height:1.7}.npg-cta{background:#0E0E0E;color:#fff;border-radius:20px;padding:2rem;display:grid;grid-template-columns:1fr auto;gap:1rem 2rem;align-items:center;margin:2.4rem 0 1rem}.npg-cta h2{color:#fff;margin:0 0 .4rem}.npg-cta p{margin:0;color:#d9d4c8}.npg-cta__btns{display:flex;flex-wrap:wrap;gap:.6rem}.npg-cta .nura-btn--ghost{color:#fff;border-color:rgba(255,255,255,.5)}@media(max-width:900px){.npg-facts{grid-template-columns:repeat(2,1fr)}.ncp-grid{grid-template-columns:repeat(2,1fr)}.npg-cta{grid-template-columns:1fr}}@media(max-width:520px){.npg-facts{grid-template-columns:1fr}}</style>' . "\n";
}, 21 );

/** Create the four pages once (never overwrites an existing slug). */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.68.0' === get_option( 'nura_city_import' ) ) {
		return;
	}
	update_option( 'nura_city_import', '1.68.0', false );
	foreach ( nura_city_list() as $slug => $c ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => sprintf( 'Wigs in %s', $c[0] ),
			'post_status'  => 'publish',
			'post_content' => sprintf( '<p>NURA Beauty delivers human hair, semi-human and fibre wigs to %1$s and across %2$s County. This page shows live delivery fees, prices and popular wigs.</p>', $c[0], $c[1] ),
		), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', sprintf( 'Wigs in %s: Delivery, Prices & Payment | NURA Beauty', $c[0] ) );
			update_post_meta( $id, 'rank_math_description', sprintf( 'Buy human hair, semi-human and fibre wigs in %s. Live prices, delivery fee and time to %s County, M-Pesa and card payment, 7-day returns. Order online or on WhatsApp.', $c[0], $c[1] ) );
			update_post_meta( $id, 'rank_math_focus_keyword', strtolower( 'wigs in ' . $c[0] ) );
		}
	}
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );
