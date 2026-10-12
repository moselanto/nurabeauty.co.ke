<?php
/**
 * Template Name: NURA AI Wig Finder
 *
 * AI Wig Finder page - v1.49.0. Used automatically for pages with the slug
 * ai-wig-finder or wig-finder (see functions.php), or pick "NURA AI Wig Finder"
 * under Page > Template.
 *
 * Wraps the [nura_ai_wig_finder] tool in the same design language as the
 * Contact, Book Appointment and Installation pages: dark hero with facts and
 * buttons, numbered steps, the finder in a framed card, "prefer a human touch"
 * cards, a privacy note and a closing call to action.
 *
 * @package NURA_Beauty
 */
get_header();

$nf_phone = nura_opt( 'nura_phone' ) ? nura_opt( 'nura_phone' ) : '+254 714 994 898';
$nf_tel   = preg_replace( '/[^0-9+]/', '', $nf_phone );
$nf_wa    = nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/' . ltrim( $nf_tel, '+' );
$nf_mail  = nura_opt( 'nura_email' ) ? nura_opt( 'nura_email' ) : 'care@nurabeauty.co.ke';
$nf_addr  = nura_opt( 'nura_address' ) ? nura_opt( 'nura_address' ) : 'Imenti House, Moi Avenue, Nairobi CBD';
$nf_hours = nura_opt( 'nura_hours' ) ? nura_opt( 'nura_hours' ) : 'Mon-Sat 9:00-18:00';

$nf_page = function ( $slug, $fallback = '' ) {
	$p = get_page_by_path( $slug );
	return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : $fallback;
};
$nf_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$nf_cat  = function ( $slug, $fallback_path ) {
	$t = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $t && ! is_wp_error( $t ) ) {
		$l = get_term_link( $t );
		if ( ! is_wp_error( $l ) ) {
			return $l;
		}
	}
	return home_url( $fallback_path );
};

$nf_icon = function ( $name ) {
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

$nf_book = $nf_page( 'book-appointment', $nf_wa );
$nf_book_consult = $nf_page( 'book-appointment' ) ? add_query_arg( 'service', 'consultation', $nf_book ) : $nf_wa;
$nf_tryon   = $nf_page( 'virtual-try-on' );
$nf_privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

$nf_steps = array(
	array( 'camera', __( 'Share your look', 'nura-beauty' ), __( 'Answer a few quick questions about texture, length, colour and budget.', 'nura-beauty' ) ),
	array( 'spark', __( 'We match you', 'nura-beauty' ), __( 'NURA matches your face shape, skin tone and style to our collection.', 'nura-beauty' ) ),
	array( 'list', __( 'Get your shortlist', 'nura-beauty' ), __( 'See curated units with lengths, textures and prices.', 'nura-beauty' ) ),
	array( 'cal', __( 'Try or confirm', 'nura-beauty' ), __( 'Save your matches, try them on virtually or book a free consultation.', 'nura-beauty' ) ),
);
?>
<main id="primary" class="site-main nura-wf-page">

	<section class="nura-bk-hero nura-wf-hero">
		<div class="nura-container">
			<?php nura_breadcrumbs(); ?>
			<p class="nura-eyebrow"><?php esc_html_e( 'Find your perfect wig', 'nura-beauty' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="nura-bk-hero__lede"><?php esc_html_e( 'Meet your perfect wig in seconds. NURA\'s AI Wig Finder recommends the best units for your face shape, skin tone, lifestyle and budget.', 'nura-beauty' ); ?></p>
			<ul class="nura-bk-hero__facts">
				<li><?php esc_html_e( 'Free to use', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Takes about a minute', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Private by design', 'nura-beauty' ); ?></li>
			</ul>
			<div class="nura-in-hero__cta">
				<a class="nura-in-btn nura-in-btn--gold" href="#nura-finder"><?php esc_html_e( 'Start the finder', 'nura-beauty' ); ?></a>
				<a class="nura-in-btn nura-in-btn--line" href="<?php echo esc_url( $nf_book_consult ); ?>"><?php esc_html_e( 'Talk to a stylist', 'nura-beauty' ); ?></a>
			</div>
		</div>
	</section>

	<section class="nura-in-section nura-wf-steps-wrap">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'How it works', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Four steps to your crown', 'nura-beauty' ); ?></h2>
			</div>
			<ol class="nura-in-steps nura-wf-steps">
				<?php foreach ( $nf_steps as $i => $s ) : ?>
					<li>
						<span class="nura-wf-steps__top"><span class="nura-in-steps__n"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><span class="nura-ct-card__ic"><?php echo $nf_icon( $s[0] ); // phpcs:ignore ?></span></span>
						<strong><?php echo esc_html( $s[1] ); ?></strong>
						<span><?php echo esc_html( $s[2] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>

	<section class="nura-in-section nura-in-section--soft" id="nura-finder">
		<div class="nura-container">
			<div class="nura-wf-tool">
				<div class="nura-wf-tool__body">
					<?php echo do_shortcode( '[nura_ai_wig_finder]' ); // phpcs:ignore ?>
				</div>
				<p class="nura-wf-privacy"><?php echo $nf_icon( 'lock' ); // phpcs:ignore ?><span><?php esc_html_e( 'Your answers and photo are used only to generate your recommendation and are never shared.', 'nura-beauty' ); ?><?php if ( $nf_privacy ) : ?> <a href="<?php echo esc_url( $nf_privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'nura-beauty' ); ?></a><?php endif; ?></span></p>
			</div>
		</div>
	</section>

	<section class="nura-in-section">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'Prefer a human touch?', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Our stylists are always here', 'nura-beauty' ); ?></h2>
			</div>
			<div class="nura-ct-cards nura-wf-cards">
				<a class="nura-ct-card" href="<?php echo esc_url( $nf_book_consult ); ?>">
					<span class="nura-ct-card__ic"><?php echo $nf_icon( 'cal' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'Free consultation', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php esc_html_e( 'Book a stylist', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'In studio or online, we guide you personally', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Book now', 'nura-beauty' ); ?> <?php echo $nf_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
				<a class="nura-ct-card nura-ct-card--wa" href="<?php echo esc_url( $nf_wa ); ?>" target="_blank" rel="noopener">
					<span class="nura-ct-card__ic"><?php echo $nf_icon( 'wa' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'WhatsApp', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php esc_html_e( 'Send us a selfie', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'A real stylist replies with picks for you', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Start chat', 'nura-beauty' ); ?> <?php echo $nf_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
				<?php if ( $nf_tryon ) : ?>
				<a class="nura-ct-card" href="<?php echo esc_url( $nf_tryon ); ?>">
					<span class="nura-ct-card__ic"><?php echo $nf_icon( 'mirror' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'Virtual try-on', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php esc_html_e( 'See it on you', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'Preview your matches before you buy', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Try on', 'nura-beauty' ); ?> <?php echo $nf_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
				<?php endif; ?>
				<a class="nura-ct-card" href="<?php echo esc_url( $nf_shop ); ?>">
					<span class="nura-ct-card__ic"><?php echo $nf_icon( 'star' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'Browse', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php esc_html_e( 'Shop all wigs', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'Filter by texture, length and price', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Shop now', 'nura-beauty' ); ?> <?php echo $nf_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
			</div>
		</div>
	</section>

	<section class="nura-in-final">
		<div class="nura-container nura-in-final__inner">
			<div>
				<h2><?php esc_html_e( 'Still not sure which unit is you?', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Book a free consultation and we will personally guide you to your crown.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-in-care__cta">
				<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $nf_book_consult ); ?>"><?php esc_html_e( 'Book a free consultation', 'nura-beauty' ); ?></a>
				<a class="nura-in-btn nura-in-btn--line" href="tel:<?php echo esc_attr( $nf_tel ); ?>"><?php echo esc_html( $nf_phone ); ?></a>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
