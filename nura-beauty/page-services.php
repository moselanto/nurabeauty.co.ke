<?php
/**
 * Services page template.
 *
 * WordPress auto-uses this for the Page whose slug is "services" (no template
 * selection needed). It presents the full NURA service menu - the depth behind
 * the homepage's single "The NURA Experience" summary and the target of the
 * homepage "Explore NURA Services" CTA and the "Services" nav item.
 *
 * SETUP: create a Page titled "Services" (Pages -> Add New -> Publish). The
 * body can be left empty - this template renders the whole page.
 *
 * @package NURA_Beauty
 */
get_header();

$wa       = nura_opt( 'nura_whatsapp' );
$book_url = home_url( '/book-appointment/' );

$services = array(
	array( 'n' => '01', 'title' => __( 'Installation', 'nura-beauty' ),      'desc' => __( 'Professional fitting and a flawless, secure melt for a natural, all-day hold.', 'nura-beauty' ) ),
	array( 'n' => '02', 'title' => __( 'Wig Revamp', 'nura-beauty' ),         'desc' => __( 'Wash, condition and restyle a loved unit back to its original lustre.', 'nura-beauty' ) ),
	array( 'n' => '03', 'title' => __( 'Wig Repair', 'nura-beauty' ),         'desc' => __( 'Lace, wefts and knots expertly restored to extend the life of your crown.', 'nura-beauty' ) ),
	array( 'n' => '04', 'title' => __( 'Colouring', 'nura-beauty' ),          'desc' => __( 'Custom colour, highlights and root work by hand, matched to your tone.', 'nura-beauty' ) ),
	array( 'n' => '05', 'title' => __( 'Restyling', 'nura-beauty' ),          'desc' => __( 'Cut, curl and reshape into a fresh silhouette that suits the moment.', 'nura-beauty' ) ),
	array( 'n' => '06', 'title' => __( 'Consultation', 'nura-beauty' ),       'desc' => __( 'One-on-one guidance, in studio or virtual, to find your perfect unit.', 'nura-beauty' ) ),
	array( 'n' => '07', 'title' => __( 'Custom Wig Making', 'nura-beauty' ),  'desc' => __( 'Bespoke units built to your measurements, density and texture.', 'nura-beauty' ) ),
	array( 'n' => '08', 'title' => __( 'Bridal Hair', 'nura-beauty' ),        'desc' => __( 'Photograph-ready bridal styling and hand-finished occasion units.', 'nura-beauty' ) ),
);
?>
<main id="primary" class="site-main">

	<div class="nura-page-hero">
		<div class="nura-container">
			<p class="nura-eyebrow"><?php esc_html_e( 'The NURA Experience', 'nura-beauty' ); ?></p>
			<h1><?php esc_html_e( 'NURA Services', 'nura-beauty' ); ?></h1>
		</div>
	</div>

	<div class="nura-container">
		<?php nura_breadcrumbs(); ?>
	</div>

	<section class="section" id="services-list" style="padding-top:0">
		<div class="nura-container">
			<div class="nura-shead nura-reveal">
				<p class="nura-eyebrow"><?php esc_html_e( 'More than a wig', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'A complete beauty experience', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'From first fitting to lifelong care, every NURA service is handled by hand, in studio, by our team in Nairobi.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-grid nura-grid--4 nura-reveal">
				<?php foreach ( $services as $s ) : ?>
					<div class="nura-feature">
						<span class="icn"><?php echo esc_html( $s['n'] ); ?></span>
						<h3><?php echo esc_html( $s['title'] ); ?></h3>
						<p><?php echo esc_html( $s['desc'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section--ink text-center">
		<div class="nura-container nura-reveal">
			<p class="nura-eyebrow"><?php esc_html_e( 'Ready when you are', 'nura-beauty' ); ?></p>
			<h2><?php esc_html_e( 'Book your NURA appointment', 'nura-beauty' ); ?></h2>
			<p class="nura-lede" style="max-width:620px;margin-inline:auto"><?php esc_html_e( 'Tell us the look you want and we will guide you to the right service and unit - virtual or in studio.', 'nura-beauty' ); ?></p>
			<p style="margin-top:1.6rem">
				<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $book_url ); ?>"><?php esc_html_e( 'Book an appointment', 'nura-beauty' ); ?></a>
				<?php if ( $wa ) : ?>
				<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( $wa ); ?>"><?php esc_html_e( 'Chat on WhatsApp', 'nura-beauty' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	</section>

</main>
<?php get_footer();
