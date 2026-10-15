<?php
/**
 * Template Name: NURA About
 *
 * About page - v1.49.0. Used automatically for pages with the slug about,
 * about-us or our-story (see functions.php), or pick "NURA About" under
 * Page > Template.
 *
 * Replaces the plain text layout with the same design language as the
 * Contact, Book Appointment and Installation pages: dark hero with facts and
 * buttons, story split with image, "why NURA" cards, services grid, values
 * band, studio card and a closing call to action. Contact details come from
 * NURA Options > Brand.
 *
 * @package NURA_Beauty
 */
get_header();

$na_phone = nura_opt( 'nura_phone' ) ? nura_opt( 'nura_phone' ) : '+254 714 994 898';
$na_tel   = preg_replace( '/[^0-9+]/', '', $na_phone );
$na_wa    = nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/' . ltrim( $na_tel, '+' );
$na_mail  = nura_opt( 'nura_email' ) ? nura_opt( 'nura_email' ) : 'care@nurabeauty.co.ke';
$na_addr  = nura_opt( 'nura_address' ) ? nura_opt( 'nura_address' ) : 'Imenti House, Moi Avenue, Nairobi CBD';
$na_hours = nura_opt( 'nura_hours' ) ? nura_opt( 'nura_hours' ) : 'Mon-Sat 8:00-20:00';

$na_page = function ( $slug, $fallback = '' ) {
	$p = get_page_by_path( $slug );
	return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : $fallback;
};
$na_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$na_cat  = function ( $slug, $fallback_path ) {
	$t = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $t && ! is_wp_error( $t ) ) {
		$l = get_term_link( $t );
		if ( ! is_wp_error( $l ) ) {
			return $l;
		}
	}
	return home_url( $fallback_path );
};

$na_icon = function ( $name ) {
	$p = array(
		'wa'     => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.5-4.3A8.5 8.5 0 1 1 20.5 11.6z"/><path d="M9 8.5c.2 2.6 2.3 5 5 5.6l1-1.2 2 .9-.4 1.6c-3.6.4-8.2-3.8-8-7.6l1.6-.4.9 2z"/>',
		'phone'  => '<path d="M5 4h3l1.5 4-2 1.5a11 11 0 0 0 7 7l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'    => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'shield' => '<path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
		'star'   => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
		'truck'  => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'heart'  => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
		'camera' => '<path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13.5" r="3.5"/>',
		'spark'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M6 18l2.5-2.5M15.5 8.5L18 6"/>',
		'list'   => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
		'cal'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'lock'   => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'mirror' => '<ellipse cx="12" cy="10" rx="6" ry="7"/><path d="M12 17v4M8 21h8"/>',
	);
	return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[ $name ] . '</svg>';
};

$na_book    = $na_page( 'book-appointment', $na_wa );
$na_contact = $na_page( 'contact-us', $na_page( 'contact', '' ) );
$na_circle  = $na_page( 'nura-circle' );
$na_install = $na_page( 'installation' );
$na_img     = get_template_directory_uri() . '/assets/images/model-editorial.webp';
$na_map_q   = rawurlencode( 'Imenti House, Moi Avenue, Nairobi' );

$na_why = array(
	array( 'shield', __( 'Honest hair', 'nura-beauty' ), __( 'Every unit is clearly labelled as 100% human hair, human-hair blend or heat-resistant fibre. We never sell a blend or fibre unit as human hair.', 'nura-beauty' ) ),
	array( 'star', __( 'Concierge experience', 'nura-beauty' ), __( 'A premium ritual from consultation to unboxing, online and in our Nairobi studio.', 'nura-beauty' ) ),
	array( 'truck', __( 'Made for how Kenya shops', 'nura-beauty' ), __( 'M-Pesa checkout, same-day delivery in Nairobi and fast countrywide shipping.', 'nura-beauty' ) ),
	array( 'heart', __( 'Care for life', 'nura-beauty' ), __( 'The NURA Circle keeps your confidence going with care reminders, revamps and rewards.', 'nura-beauty' ) ),
);

$na_services = array(
	array( __( 'Premium wigs', 'nura-beauty' ), __( 'Human hair, blend and fibre: lace front, closure, headband, bob, curly and straight units, curated for Kenyan women.', 'nura-beauty' ) ),
	array( __( 'Wig installation', 'nura-beauty' ), __( 'Professional fitting at our studio or, for VIP clients, at home.', 'nura-beauty' ) ),
	array( __( 'Styling and restyle', 'nura-beauty' ), __( 'Shaped to your face, occasion and lifestyle.', 'nura-beauty' ) ),
	array( __( 'Wash and revamp', 'nura-beauty' ), __( 'Cleaning and refreshing to bring your unit back to life.', 'nura-beauty' ) ),
	array( __( 'Colouring', 'nura-beauty' ), __( 'Safe, expert colour changes and enhancements.', 'nura-beauty' ) ),
	array( __( 'Re-lacing and repair', 'nura-beauty' ), __( 'Restoration for units that deserve a second life.', 'nura-beauty' ) ),
	array( __( 'Consultation and selection', 'nura-beauty' ), __( 'Honest guidance to find the right wig for you.', 'nura-beauty' ) ),
);

$na_values = array( __( 'Radiance', 'nura-beauty' ), __( 'Integrity of origin', 'nura-beauty' ), __( 'Craft', 'nura-beauty' ), __( 'Guidance', 'nura-beauty' ), __( 'Belonging', 'nura-beauty' ), __( 'Discretion', 'nura-beauty' ) );

$na_shop_links = array(
	$na_cat( 'human-hair-wigs', '/product-category/wigs/human-hair-wigs/' ) => __( 'Human hair wigs', 'nura-beauty' ),
	$na_cat( 'lace-front-wigs', '/product-category/wigs/lace-front-wigs/' ) => __( 'Lace front wigs', 'nura-beauty' ),
	$na_cat( 'closure-wigs', '/product-category/wigs/closure-wigs/' )       => __( 'Closure wigs', 'nura-beauty' ),
	$na_cat( 'headband-wigs', '/product-category/wigs/headband-wigs/' )     => __( 'Headband wigs', 'nura-beauty' ),
	$na_cat( 'wigs', '/product-category/wigs/' )                            => __( 'All wigs in Kenya', 'nura-beauty' ),
);
?>
<main id="primary" class="site-main nura-ab-page">

	<section class="nura-bk-hero nura-ab-hero">
		<div class="nura-container">
			<?php nura_breadcrumbs(); ?>
			<p class="nura-eyebrow"><?php esc_html_e( 'The house of radiant confidence', 'nura-beauty' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="nura-bk-hero__lede"><?php esc_html_e( 'NURA Beauty is Nairobi\'s house of premium wigs in Kenya - honestly sourced, hand-finished, and backed by real concierge care. We help women across Kenya wear their crown with confidence.', 'nura-beauty' ); ?></p>
			<ul class="nura-bk-hero__facts">
				<li><?php esc_html_e( 'Registered Nairobi business', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Every unit clearly labelled', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Studio in Nairobi CBD', 'nura-beauty' ); ?></li>
			</ul>
			<div class="nura-in-hero__cta">
				<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $na_shop ); ?>"><?php esc_html_e( 'Shop wigs', 'nura-beauty' ); ?></a>
				<a class="nura-in-btn nura-in-btn--line" href="<?php echo esc_url( $na_book ); ?>"><?php esc_html_e( 'Book a consultation', 'nura-beauty' ); ?></a>
			</div>
		</div>
	</section>

	<section class="nura-in-section">
		<div class="nura-container nura-ab-story">
			<figure class="nura-ab-story__media">
				<img src="<?php echo esc_url( $na_img ); ?>" alt="<?php esc_attr_e( 'A NURA client wearing a hand-finished wig', 'nura-beauty' ); ?>" loading="lazy" decoding="async" width="720" height="900">
				<figcaption><span><?php esc_html_e( 'Hand-finished in Nairobi', 'nura-beauty' ); ?></span></figcaption>
			</figure>
			<div class="nura-ab-story__body">
				<p class="nura-eyebrow"><?php esc_html_e( 'Our story', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Kenya\'s home for premium wigs', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'NURA Beauty is the online home of NURA CROWN & BEAUTY, a registered Nairobi business built on a simple belief: for a woman, hair is never just hair - it is identity, status and confidence.', 'nura-beauty' ); ?></p>
				<p><?php esc_html_e( 'We set out to raise the standard for buying wigs in Kenya, a market long crowded at the bottom and thin on trust. We sell 100% human hair, human-hair blend and heat-resistant fibre wigs - every unit clearly labelled with its hair type, quality-checked and finished in Nairobi.', 'nura-beauty' ); ?></p>
				<ul class="nura-ab-stats">
					<li><strong>100%</strong><span><?php esc_html_e( 'Clearly labelled hair types', 'nura-beauty' ); ?></span></li>
					<li><strong><?php esc_html_e( 'Same day', 'nura-beauty' ); ?></strong><span><?php esc_html_e( 'Delivery in Nairobi', 'nura-beauty' ); ?></span></li>
					<li><strong>M-Pesa</strong><span><?php esc_html_e( 'And card checkout', 'nura-beauty' ); ?></span></li>
				</ul>
			</div>
		</div>
	</section>

	<section class="nura-in-section nura-in-section--soft">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'Why NURA', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Why women across Kenya choose us', 'nura-beauty' ); ?></h2>
			</div>
			<div class="nura-ab-why">
				<?php foreach ( $na_why as $w ) : ?>
					<article class="nura-ab-why__card">
						<span class="nura-ct-card__ic"><?php echo $na_icon( $w[0] ); // phpcs:ignore ?></span>
						<h3><?php echo esc_html( $w[1] ); ?></h3>
						<p><?php echo esc_html( $w[2] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="nura-in-section">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'What we do', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'More than a wig shop', 'nura-beauty' ); ?></h2>
				<p class="nura-ab-sub"><?php esc_html_e( 'From your first consultation to installation and long-term care, our Nairobi team guides you end to end.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-in-services">
				<?php foreach ( $na_services as $svc ) : ?>
					<article class="nura-in-svc">
						<h3><?php echo esc_html( $svc[0] ); ?></h3>
						<p class="nura-in-svc__d"><?php echo esc_html( $svc[1] ); ?></p>
					</article>
				<?php endforeach; ?>
				<a class="nura-in-svc nura-ab-svc-cta" href="<?php echo esc_url( $na_book ); ?>">
					<h3><?php esc_html_e( 'Not sure what you need?', 'nura-beauty' ); ?></h3>
					<p class="nura-in-svc__d"><?php esc_html_e( 'Book a free consultation and a stylist will guide you.', 'nura-beauty' ); ?></p>
					<span class="nura-in-svc__go"><?php esc_html_e( 'Book a consultation', 'nura-beauty' ); ?> <span aria-hidden="true">&rarr;</span></span>
				</a>
			</div>
			<div class="nura-ab-links">
				<?php if ( $na_install ) : ?>
					<a class="nura-in-btn nura-in-btn--line" href="<?php echo esc_url( $na_install ); ?>"><?php esc_html_e( 'See installation prices', 'nura-beauty' ); ?></a>
				<?php endif; ?>
				<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $na_book ); ?>"><?php esc_html_e( 'Book an appointment', 'nura-beauty' ); ?></a>
			</div>
		</div>
	</section>

	<section class="nura-in-section nura-in-section--tight">
		<div class="nura-container">
			<div class="nura-in-care nura-ab-values">
				<div>
					<p class="nura-eyebrow"><?php esc_html_e( 'Our values', 'nura-beauty' ); ?></p>
					<h2><?php esc_html_e( 'We advise, we don\'t just sell', 'nura-beauty' ); ?></h2>
					<p><?php esc_html_e( 'We exist to make confidence visible, and we treat every client - including our medical and hair-loss clients - with calm, private respect.', 'nura-beauty' ); ?></p>
					<ul class="nura-ab-values__list">
						<?php foreach ( $na_values as $v ) : ?>
							<li><?php echo esc_html( $v ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php if ( $na_circle ) : ?>
					<div class="nura-in-care__cta">
						<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $na_circle ); ?>"><?php esc_html_e( 'Join The NURA Circle', 'nura-beauty' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="nura-in-section nura-in-section--soft">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'Visit us', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Our Nairobi studio', 'nura-beauty' ); ?></h2>
			</div>
			<div class="nura-ab-studio">
				<div class="nura-ab-studio__map">
					<iframe title="<?php esc_attr_e( 'Map to the NURA studio', 'nura-beauty' ); ?>" src="https://www.google.com/maps?q=<?php echo esc_attr( $na_map_q ); ?>&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
				</div>
				<div class="nura-ab-studio__body">
					<p class="nura-bk__studio-k"><?php esc_html_e( 'The NURA studio', 'nura-beauty' ); ?></p>
					<ul class="nura-ab-studio__lines">
						<li><?php echo $na_icon( 'pin' ); // phpcs:ignore ?><span><?php echo esc_html( $na_addr ); ?></span></li>
						<li><?php echo $na_icon( 'clock' ); // phpcs:ignore ?><span><?php echo esc_html( $na_hours ); ?></span></li>
						<li><?php echo $na_icon( 'phone' ); // phpcs:ignore ?><a href="tel:<?php echo esc_attr( $na_tel ); ?>"><?php echo esc_html( $na_phone ); ?></a></li>
						<li><?php echo $na_icon( 'mail' ); // phpcs:ignore ?><a href="mailto:<?php echo esc_attr( $na_mail ); ?>"><?php echo esc_html( $na_mail ); ?></a></li>
					</ul>
					<p class="nura-ct__note"><?php esc_html_e( 'Come in for a consultation, fitting, installation or styling. Visits are by appointment so a stylist is ready for you.', 'nura-beauty' ); ?></p>
					<div class="nura-ab-studio__cta">
						<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $na_book ); ?>"><?php esc_html_e( 'Book a visit', 'nura-beauty' ); ?></a>
						<a class="nura-in-btn nura-in-btn--line" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo esc_attr( $na_map_q ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'nura-beauty' ); ?></a>
					</div>
				</div>
			</div>
			<div class="nura-ab-shop">
				<p class="nura-bk__studio-k"><?php esc_html_e( 'Shop wigs in Kenya', 'nura-beauty' ); ?></p>
				<ul>
					<?php foreach ( $na_shop_links as $u => $label ) : ?>
						<li><a href="<?php echo esc_url( $u ); ?>"><?php echo esc_html( $label ); ?><?php echo $na_icon( 'arrow' ); // phpcs:ignore ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</section>

	<section class="nura-in-final">
		<div class="nura-container nura-in-final__inner">
			<div>
				<h2><?php esc_html_e( 'Wear your crown.', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Prefer to talk first? Message a NURA stylist and we will help you choose.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-in-care__cta">
				<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $na_wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Chat on WhatsApp', 'nura-beauty' ); ?></a>
				<?php if ( $na_contact ) : ?>
					<a class="nura-in-btn nura-in-btn--line" href="<?php echo esc_url( $na_contact ); ?>"><?php esc_html_e( 'Contact us', 'nura-beauty' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
