<?php
/**
 * Homepage - NURA "focused luxury" v1.6.0.
 *
 * Deliberately lean. Sections, in order: hero, trust bar, Shop NURA (4 pillars),
 * Fresh Arrivals, Real NURA Women (social proof), House Favourites, brand story,
 * featured Wig Care, the NURA Experience (services summary) and the NURA Stylist
 * CTA. The full catalogue architecture - every texture, construction, price band,
 * care line and service - lives BEHIND the homepage in Shop / Category / Filter
 * pages, not on it. Category tiles resolve to their archive when it exists and
 * fall back to the shop page otherwise, so no link is ever broken.
 *
 * @package NURA_Beauty
 */
get_header();

$shop_url   = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
$wa         = nura_opt( 'nura_whatsapp' );
$finder_url = home_url( '/ai-wig-finder/' );

// Contact details for the "Talk to NURA" section below. Customizer values win;
// the published NURA details are a graceful fallback so the block is never empty.
$nura_phone = nura_opt( 'nura_phone' );
if ( ! $nura_phone ) { $nura_phone = '+254 714 994 898'; }
$nura_email = nura_opt( 'nura_email' );
if ( ! $nura_email ) { $nura_email = 'care@nurabeauty.co.ke'; }
$nura_tel   = preg_replace( '/[^0-9+]/', '', $nura_phone );
if ( ! $wa ) { $wa = 'https://wa.me/254714994898'; }

/** Resolve a product-category URL by slug, with a safe shop fallback. */
if ( ! function_exists( 'nura_home_cat_url' ) ) {
	function nura_home_cat_url( $slug ) {
		if ( taxonomy_exists( 'product_cat' ) ) {
			$t = get_term_by( 'slug', sanitize_title( $slug ), 'product_cat' );
			if ( $t && ! is_wp_error( $t ) ) {
				$l = get_term_link( $t );
				if ( ! is_wp_error( $l ) ) {
					return $l;
				}
			}
		}
		return function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
	}
}
/** Category thumbnail URL by slug, or '' when none is set. */
if ( ! function_exists( 'nura_home_cat_img' ) ) {
	function nura_home_cat_img( $slug ) {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return '';
		}
		$t = get_term_by( 'slug', sanitize_title( $slug ), 'product_cat' );
		if ( $t && ! is_wp_error( $t ) ) {
			$id = get_term_meta( $t->term_id, 'thumbnail_id', true );
			if ( $id ) {
				return wp_get_attachment_image_url( $id, 'medium_large' );
			}
		}
		return '';
	}
}
/** Render a grid of tiles: each item = [ label, url, (sub), (img) ]. */
if ( ! function_exists( 'nura_home_tiles' ) ) {
	function nura_home_tiles( $items, $mod = 'nura-tiles--4' ) {
		echo '<div class="nura-tiles ' . esc_attr( $mod ) . '">';
		foreach ( $items as $it ) {
			$img   = ! empty( $it['img'] ) ? $it['img'] : '';
			$style = $img ? ' style="background-image:url(\'' . esc_url( $img ) . '\')"' : '';
			$cls   = 'nura-tile' . ( $img ? ' has-img' : '' );
			echo '<a class="' . esc_attr( $cls ) . '"' . $style . ' href="' . esc_url( $it['url'] ) . '">';
			echo '<span class="nura-tile__label">' . esc_html( $it['label'] );
			if ( ! empty( $it['sub'] ) ) {
				echo '<small>' . esc_html( $it['sub'] ) . '</small>';
			}
			echo '</span></a>';
		}
		echo '</div>';
	}
}

$wigs_url = nura_home_cat_url( 'wigs' );

/*
 * Curated hero. Copy is set here (not the Customizer) so the homepage's primary
 * message stays consistent and on-brand. Every slide drives the same two clear
 * actions - Shop Wigs and Find Your Wig - so the hero stays single-minded.
 */
$slides = array(
	array(
		'img'     => NURA_URI . 'assets/images/hero.webp',
		'eyebrow' => __( 'Premium Wigs • Beauty • Care • Confidence', 'nura-beauty' ),
		'title'   => __( 'Wear Your Crown', 'nura-beauty' ),
		'sub'     => __( 'Discover human-hair wigs and beauty essentials curated for the modern woman.', 'nura-beauty' ),
	),
	array(
		'img'     => NURA_URI . 'assets/images/hero-2.webp',
		'eyebrow' => __( 'Bridal & Occasion', 'nura-beauty' ),
		'title'   => __( 'Your Crown for the Big Day', 'nura-beauty' ),
		'sub'     => __( 'Bespoke bridal units, hand-finished for a flawless, photograph-ready finish.', 'nura-beauty' ),
	),
	array(
		'img'     => NURA_URI . 'assets/images/hero-3.webp',
		'eyebrow' => __( 'The Confidence Line', 'nura-beauty' ),
		'title'   => __( 'Statement HD-Lace Units', 'nura-beauty' ),
		'sub'     => __( 'An invisible hairline and undeniable presence, hand-crafted in Nairobi.', 'nura-beauty' ),
	),
);
?>
<main id="primary" class="site-main">

	<!-- HERO SLIDER -->
	<section class="nura-hero-slider" data-nura-slider role="region" aria-roledescription="<?php esc_attr_e( 'carousel', 'nura-beauty' ); ?>" aria-label="<?php esc_attr_e( 'Featured collections', 'nura-beauty' ); ?>">
		<div class="nura-slides">
			<?php $nura_total = count( $slides ); ?>
			<?php foreach ( $slides as $i => $s ) : ?>
				<div class="nura-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" role="group" aria-roledescription="<?php esc_attr_e( 'slide', 'nura-beauty' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: current slide number, 2: total slides. */ __( '%1$d of %2$d', 'nura-beauty' ), $i + 1, $nura_total ) ); ?>">
					<div class="nura-slide__media" style="background-image:url('<?php echo esc_url( $s['img'] ); ?>')" aria-hidden="true"></div>
					<div class="nura-container nura-slide__inner">
						<p class="nura-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></p>
						<h1><?php echo esc_html( $s['title'] ); ?></h1>
						<p class="nura-lede"><?php echo esc_html( $s['sub'] ); ?></p>
						<div class="nura-hero__cta">
							<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $wigs_url ); ?>"><?php esc_html_e( 'Shop Wigs', 'nura-beauty' ); ?></a>
							<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $finder_url ); ?>"><?php esc_html_e( 'Find Your Wig', 'nura-beauty' ); ?></a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="nura-slider-nav nura-slider-nav--prev" data-slider-prev aria-label="<?php esc_attr_e( 'Previous slide', 'nura-beauty' ); ?>">&#8249;</button>
		<button type="button" class="nura-slider-nav nura-slider-nav--next" data-slider-next aria-label="<?php esc_attr_e( 'Next slide', 'nura-beauty' ); ?>">&#8250;</button>
		<div class="nura-slider-dots" data-slider-dots></div>
	</section>

	<!-- TRUST BAR -->
	<div class="nura-trustbar">
		<div class="nura-container">
			<ul>
				<li><?php esc_html_e( 'Quality-Verified Wigs', 'nura-beauty' ); ?></li>
				<?php
				// Delivery + payment reflect the store's real WooCommerce shipping
				// zones and enabled gateways (see inc/nura-commerce.php), with the
				// curated Kenya promises as a graceful fallback.
				$nura_delivery = function_exists( 'nura_delivery_lines' )
					? nura_delivery_lines( 2 )
					: array( __( 'Same-Day Nairobi Delivery', 'nura-beauty' ), __( 'Kenya-Wide Delivery', 'nura-beauty' ) );
				foreach ( $nura_delivery as $nura_line ) {
					echo '<li>' . esc_html( $nura_line ) . '</li>';
				}
				$nura_pay = function_exists( 'nura_payment_summary' )
					? nura_payment_summary( __( 'M-Pesa & Pay on Delivery', 'nura-beauty' ) )
					: __( 'M-Pesa & Pay on Delivery', 'nura-beauty' );
				?>
				<li><?php echo esc_html( $nura_pay ); ?></li>
			</ul>
		</div>
	</div>

	<!-- WIG FINDER PROMPT (secondary path for visitors not ready to browse) -->
	<div class="nura-finderbar">
		<div class="nura-container nura-finderbar__inner">
			<p class="nura-finderbar__text"><strong><?php esc_html_e( 'Not sure which wig is right for you?', 'nura-beauty' ); ?></strong> <?php esc_html_e( 'Answer a few quick questions and NURA Stylist will match you.', 'nura-beauty' ); ?></p>
			<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $finder_url ); ?>"><?php esc_html_e( 'Find My Wig', 'nura-beauty' ); ?> &#8594;</a>
		</div>
	</div>

	<!-- SHOP NURA - four pillars -->
	<section class="section" id="shop-nura">
		<div class="nura-container">
			<div class="nura-shead nura-reveal">
				<p class="nura-eyebrow"><?php esc_html_e( 'Shop NURA', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Everything for your crown', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Wigs, hair, care and beauty - the complete NURA house.', 'nura-beauty' ); ?></p>
			</div>
			<?php
			nura_home_tiles(
				array(
					array( 'label' => __( 'Wigs', 'nura-beauty' ),              'sub' => __( 'Human Hair • Lace • Glueless', 'nura-beauty' ), 'url' => nura_home_cat_url( 'wigs' ),            'img' => ( nura_home_cat_img( 'wigs' ) ?: NURA_URI . 'assets/images/cats/pillar-wigs.webp' ) ),
					array( 'label' => __( 'Hair & Extensions', 'nura-beauty' ), 'sub' => __( 'Bundles • Closures • Frontals', 'nura-beauty' ), 'url' => nura_home_cat_url( 'hair-extensions' ), 'img' => ( nura_home_cat_img( 'hair-extensions' ) ?: NURA_URI . 'assets/images/cats/pillar-hair-extensions.webp' ) ),
					array( 'label' => __( 'Wig Care', 'nura-beauty' ),          'sub' => __( 'Wash • Treat • Maintain', 'nura-beauty' ),     'url' => nura_home_cat_url( 'wig-care' ),        'img' => ( nura_home_cat_img( 'wig-care' ) ?: NURA_URI . 'assets/images/cats/pillar-wig-care.webp' ) ),
					array( 'label' => __( 'Beauty', 'nura-beauty' ),            'sub' => __( 'Makeup • Accessories', 'nura-beauty' ),           'url' => nura_home_cat_url( 'beauty' ),          'img' => ( nura_home_cat_img( 'beauty' ) ?: NURA_URI . 'assets/images/cats/pillar-beauty.webp' ) ),
				),
				'nura-tiles--4'
			);
			?>
		</div>
	</section>

	<!-- WHY NURA (value propositions) -->
	<section class="section section--ivory" id="why-nura">
		<div class="nura-container">
			<div class="nura-shead nura-reveal">
				<p class="nura-eyebrow"><?php esc_html_e( 'Why NURA', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'The NURA promise', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Premium hair, honest advice and a service that stays with you long after checkout.', 'nura-beauty' ); ?></p>
			</div>
			<?php
			$nura_why = array(
				array(
					'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8l4 3 5-6 5 6 4-3-2 11H5L3 8z"/></svg>',
					't'   => __( '100% Human Hair', 'nura-beauty' ),
					'd'   => __( 'Premium human-hair and HD-lace units, quality-checked before they ship.', 'nura-beauty' ),
				),
				array(
					'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 7h11v8H3z"/><path d="M14 10h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>',
					't'   => __( 'Same-Day Nairobi', 'nura-beauty' ),
					'd'   => __( 'Order before 5pm for same-day Nairobi delivery; 1-3 days countrywide.', 'nura-beauty' ),
				),
				array(
					'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 9.5h19"/></svg>',
					't'   => __( 'M-Pesa & Pay on Delivery', 'nura-beauty' ),
					'd'   => __( 'Pay by M-Pesa, card or on delivery in Nairobi - whatever suits you.', 'nura-beauty' ),
				),
			);
			echo '<div class="nura-why nura-reveal"><div class="nura-why__grid">';
			foreach ( $nura_why as $w ) {
				echo '<div class="nura-why__item"><span class="nura-why__ic" aria-hidden="true">' . $w['svg'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG
				echo '<span class="nura-why__t">' . esc_html( $w['t'] ) . '</span>';
				echo '<span class="nura-why__d">' . esc_html( $w['d'] ) . '</span></div>';
			}
			echo '</div></div>';
			?>
		</div>
	</section>

	<!-- SHOP BY WIG TYPE (discovery) -->
	<section class="section" id="shop-by-type">
		<div class="nura-container">
			<div class="nura-shead nura-reveal">
				<p class="nura-eyebrow"><?php esc_html_e( 'Find your match', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Shop by wig type', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Browse by construction, texture and hair type - however you like to shop.', 'nura-beauty' ); ?></p>
			</div>
			<?php
			$nura_types = array(
				array( 'label' => __( 'Human Hair', 'nura-beauty' ), 'slug' => 'human-hair-wigs' ),
				array( 'label' => __( 'Lace Front', 'nura-beauty' ), 'slug' => 'lace-front-wigs' ),
				array( 'label' => __( 'Closure', 'nura-beauty' ), 'slug' => 'closure-wigs' ),
				array( 'label' => __( 'Headband', 'nura-beauty' ), 'slug' => 'headband-wigs' ),
				array( 'label' => __( 'Bob', 'nura-beauty' ), 'slug' => 'bob-wigs' ),
				array( 'label' => __( 'Curly', 'nura-beauty' ), 'slug' => 'curly-wigs' ),
				array( 'label' => __( 'Body Wave', 'nura-beauty' ), 'slug' => 'body-wave-wigs' ),
				array( 'label' => __( 'Jerry Curl', 'nura-beauty' ), 'slug' => 'jerry-curl-wigs' ),
			);
			echo '<div class="nura-typegrid nura-reveal">';
			foreach ( $nura_types as $ty ) {
				echo '<a class="nura-typechip" href="' . esc_url( nura_home_cat_url( $ty['slug'] ) ) . '">' . esc_html( $ty['label'] ) . '</a>';
			}
			echo '<a class="nura-typechip nura-typechip--all" href="' . esc_url( $wigs_url ) . '">' . esc_html__( 'All wigs', 'nura-beauty' ) . ' &#8594;</a>';
			echo '</div>';
			?>
		</div>
	</section>

	<!-- RAIL: FRESH ARRIVALS (4 products) -->
	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
	<?php nura_query_rail( __( 'New in', 'nura-beauty' ), __( 'New Arrivals', 'nura-beauty' ), array( 'orderby' => 'date', 'order' => 'DESC', 'posts_per_page' => 18 ), 10, $shop_url, __( 'View all new arrivals', 'nura-beauty' ) ); ?>
	<?php endif; ?>

	<!-- REAL NURA WOMEN (social proof, moved high) -->
	<?php $ig = nura_opt( 'nura_instagram' ); $ig_url = $ig ? $ig : $wa; ?>
	<section class="section section--ink text-center" id="real-women">
		<div class="nura-container nura-reveal">
			<p class="nura-eyebrow">@nurabeauty</p>
			<h2><?php esc_html_e( 'Real women. Real crowns.', 'nura-beauty' ); ?></h2>
			<p class="nura-lede" style="max-width:640px;margin-inline:auto"><?php esc_html_e( 'Tag @nurabeauty in your crown to be featured here - real women, real hair, real confidence, across Kenya and beyond.', 'nura-beauty' ); ?></p>
			<p style="margin-top:1.4rem">
				<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $ig_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See the NURA community', 'nura-beauty' ); ?> &#8594;</a>
			</p>
		</div>
	</section>

	<!-- RAIL: HOUSE FAVOURITES (4 products) -->
	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
	<?php nura_query_rail( __( 'Most loved', 'nura-beauty' ), __( 'Best Sellers', 'nura-beauty' ), array( 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'posts_per_page' => 18 ), 10, $shop_url, __( 'View all', 'nura-beauty' ) ); ?>
	<?php endif; ?>

	<!-- BRAND STORY (editorial split, condensed) -->
	<section class="section section--ivory">
		<div class="nura-container nura-split nura-reveal">
			<div class="nura-split__media">
				<img src="<?php echo esc_url( NURA_URI . 'assets/images/model-editorial.webp' ); ?>" alt="<?php echo esc_attr( nura_opt( 'nura_brand_name' ) ); ?>" loading="lazy">
			</div>
			<div class="nura-split__body">
				<p class="nura-eyebrow"><?php esc_html_e( 'The House of Radiant Confidence', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Hand-crafted in Nairobi. Made for you.', 'nura-beauty' ); ?></h2>
				<p class="nura-lede"><?php esc_html_e( 'Premium wigs, personalised service and a commitment to helping every woman feel confident in her crown.', 'nura-beauty' ); ?></p>
				<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php esc_html_e( 'Discover NURA', 'nura-beauty' ); ?> &#8594;</a>
			</div>
		</div>
	</section>

	<!-- WIG CARE (featured: 3 products + CTA) -->
	<section class="section" id="wig-care">
		<div class="nura-container">
			<div class="nura-shead nura-shead--row nura-reveal">
				<div>
					<p class="nura-eyebrow"><?php esc_html_e( 'Keep your crown radiant', 'nura-beauty' ); ?></p>
					<h2><?php esc_html_e( 'NURA Wig Care', 'nura-beauty' ); ?></h2>
					<p style="margin:.4rem 0 0"><?php esc_html_e( 'Everything your wig needs to stay beautiful.', 'nura-beauty' ); ?></p>
				</div>
				<a class="nura-rail__viewall" href="<?php echo esc_url( nura_home_cat_url( 'wig-care' ) ); ?>"><?php esc_html_e( 'Shop wig care', 'nura-beauty' ); ?> &#8594;</a>
			</div>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<div class="nura-care-featured nura-reveal">
				<?php echo do_shortcode( '[products limit="3" columns="3" category="wig-care" orderby="popularity" visibility="visible"]' ); ?>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- THE NURA EXPERIENCE (services summary + single CTA) -->
	<section class="section section--ink text-center" id="services">
		<div class="nura-container nura-reveal">
			<p class="nura-eyebrow"><?php esc_html_e( 'More than a wig', 'nura-beauty' ); ?></p>
			<h2><?php esc_html_e( 'The NURA Experience', 'nura-beauty' ); ?></h2>
			<p class="nura-lede" style="max-width:620px;margin-inline:auto"><?php esc_html_e( 'A complete beauty experience, handled end to end by our team.', 'nura-beauty' ); ?></p>
			<p style="margin:.8rem 0 0;letter-spacing:.16em;text-transform:uppercase;font-size:.8rem;color:var(--nura-gold-soft)"><?php esc_html_e( 'Installation • Revamp • Styling • Consultation', 'nura-beauty' ); ?></p>
			<p style="margin-top:1.6rem">
				<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( home_url( '/services/' ) ); ?>"><?php esc_html_e( 'Explore NURA Services', 'nura-beauty' ); ?> &#8594;</a>
			</p>
		</div>
	</section>

	<!-- TALK TO NURA (contact) -->
	<section class="section section--ivory" id="contact">
		<div class="nura-container nura-reveal">
			<div class="nura-shead">
				<p class="nura-eyebrow"><?php esc_html_e( 'We are here to help', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Talk to NURA', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Questions about a unit, sizing or an order? Reach us any way you like.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-contact-grid">
				<a class="nura-contact-card" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">
					<span class="nura-contact-card__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.2A8 8 0 1 1 20 11.5z"/></svg></span>
					<span class="nura-contact-card__k"><?php esc_html_e( 'WhatsApp', 'nura-beauty' ); ?></span>
					<span class="nura-contact-card__v"><?php echo esc_html( $nura_phone ); ?></span>
					<span class="nura-contact-card__cta"><?php esc_html_e( 'Chat now', 'nura-beauty' ); ?> &#8594;</span>
				</a>
				<a class="nura-contact-card" href="tel:<?php echo esc_attr( $nura_tel ); ?>">
					<span class="nura-contact-card__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.9.36 1.79.7 2.63a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.45-1.27a2 2 0 0 1 2.11-.45c.84.34 1.73.57 2.63.7A2 2 0 0 1 22 16.92z"/></svg></span>
					<span class="nura-contact-card__k"><?php esc_html_e( 'Call', 'nura-beauty' ); ?></span>
					<span class="nura-contact-card__v"><?php echo esc_html( $nura_phone ); ?></span>
					<span class="nura-contact-card__cta"><?php esc_html_e( 'Mon - Sat, 9 - 18', 'nura-beauty' ); ?></span>
				</a>
				<a class="nura-contact-card" href="mailto:<?php echo esc_attr( $nura_email ); ?>">
					<span class="nura-contact-card__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></span>
					<span class="nura-contact-card__k"><?php esc_html_e( 'Email', 'nura-beauty' ); ?></span>
					<span class="nura-contact-card__v"><?php echo esc_html( $nura_email ); ?></span>
					<span class="nura-contact-card__cta"><?php esc_html_e( 'We reply within a day', 'nura-beauty' ); ?></span>
				</a>
				<div class="nura-contact-card nura-contact-card--static">
					<span class="nura-contact-card__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-6-5.3-6-10a6 6 0 0 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg></span>
					<span class="nura-contact-card__k"><?php esc_html_e( 'Studio', 'nura-beauty' ); ?></span>
					<span class="nura-contact-card__v"><?php esc_html_e( 'Nairobi, Kenya', 'nura-beauty' ); ?></span>
					<span class="nura-contact-card__cta"><?php esc_html_e( 'Visits by appointment', 'nura-beauty' ); ?></span>
				</div>
			</div>
			<p class="nura-contact-note"><?php esc_html_e( 'Same-day delivery in Nairobi (order before 5pm) - Kenya-wide in 1-3 days - Worldwide shipping available.', 'nura-beauty' ); ?></p>
		</div>
	</section>

	<!-- NURA STYLIST (final conversion CTA) -->
	<section class="section text-center">
		<div class="nura-container nura-reveal">
			<p class="nura-eyebrow"><?php esc_html_e( 'Not sure what suits you?', 'nura-beauty' ); ?></p>
			<h2><?php esc_html_e( 'Meet NURA Stylist', 'nura-beauty' ); ?></h2>
			<p class="nura-lede" style="max-width:620px;margin-inline:auto"><?php esc_html_e( "Tell us what you're looking for and we'll help you find your perfect wig.", 'nura-beauty' ); ?></p>
			<p style="margin-top:1.4rem">
				<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $finder_url ); ?>"><?php esc_html_e( 'Find My Wig', 'nura-beauty' ); ?></a>
				<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $wa ); ?>"><?php esc_html_e( 'Chat on WhatsApp', 'nura-beauty' ); ?></a>
			</p>
		</div>
	</section>

</main>
<?php get_footer();
