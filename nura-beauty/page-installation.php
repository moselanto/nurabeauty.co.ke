<?php
/**
 * Template Name: NURA Installation
 *
 * Installation page - v1.47.0. Used automatically for the page with the slug
 * "installation", or pick "NURA Installation" under Page > Template.
 *
 * Every word and price on this page is editable without code in
 * Appearance > Customize > NURA Options > Installation page.
 * List fields use one item per line, with parts separated by a | character.
 *
 * @package NURA_Beauty
 */
get_header();

$ni_phone = nura_opt( 'nura_phone' ) ? nura_opt( 'nura_phone' ) : '+254 714 994 898';
$ni_tel   = preg_replace( '/[^0-9+]/', '', $ni_phone );
$ni_wa    = nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/' . ltrim( $ni_tel, '+' );

$ni_book_page = get_page_by_path( 'book-appointment' );
$ni_book      = ( $ni_book_page && 'publish' === $ni_book_page->post_status ) ? get_permalink( $ni_book_page ) : $ni_wa;
$ni_book_inst = $ni_book_page ? add_query_arg( 'service', 'installation', $ni_book ) : $ni_wa;

$ni_services = nura_lines( 'nura_inst_services', 3 );
$ni_steps    = nura_lines( 'nura_inst_steps', 2 );
$ni_faqs     = nura_lines( 'nura_inst_faq', 2 );
$ni_facts    = array_filter( array_map( 'trim', explode( ',', (string) nura_opt( 'nura_inst_facts' ) ) ) );
$ni_circle   = trim( (string) nura_opt( 'nura_inst_circle_url' ) );
?>
<main id="primary" class="site-main nura-in-page">

	<section class="nura-bk-hero nura-in-hero">
		<div class="nura-container">
			<?php nura_breadcrumbs(); ?>
			<p class="nura-eyebrow"><?php echo esc_html( nura_opt( 'nura_inst_eyebrow' ) ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="nura-bk-hero__lede"><?php echo esc_html( nura_opt( 'nura_inst_lede' ) ); ?></p>
			<?php if ( $ni_facts ) : ?>
				<ul class="nura-bk-hero__facts">
					<?php foreach ( $ni_facts as $fact ) : ?>
						<li><?php echo esc_html( $fact ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="nura-in-hero__cta">
				<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $ni_book_inst ); ?>"><?php echo esc_html( nura_opt( 'nura_inst_cta' ) ); ?></a>
				<a class="nura-in-btn nura-in-btn--line" href="<?php echo esc_url( $ni_wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ask on WhatsApp', 'nura-beauty' ); ?></a>
			</div>
		</div>
	</section>

	<?php if ( $ni_services ) : ?>
	<section class="nura-in-section">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'Services', 'nura-beauty' ); ?></p>
				<h2><?php echo esc_html( nura_opt( 'nura_inst_services_title' ) ); ?></h2>
			</div>
			<div class="nura-in-services">
				<?php foreach ( $ni_services as $svc ) : ?>
					<article class="nura-in-svc">
						<h3><?php echo esc_html( $svc[0] ); ?></h3>
						<?php if ( '' !== $svc[1] ) : ?>
							<p class="nura-in-svc__price"><?php echo esc_html( $svc[1] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $svc[2] ) : ?>
							<p class="nura-in-svc__d"><?php echo esc_html( $svc[2] ); ?></p>
						<?php endif; ?>
						<a class="nura-in-svc__go" href="<?php echo esc_url( $ni_book_inst ); ?>"><?php esc_html_e( 'Book this', 'nura-beauty' ); ?> <span aria-hidden="true">&rarr;</span></a>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( nura_opt( 'nura_inst_price_note' ) ) : ?>
				<p class="nura-in-note"><?php echo esc_html( nura_opt( 'nura_inst_price_note' ) ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $ni_steps ) : ?>
	<section class="nura-in-section nura-in-section--soft">
		<div class="nura-container">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'The process', 'nura-beauty' ); ?></p>
				<h2><?php echo esc_html( nura_opt( 'nura_inst_steps_title' ) ); ?></h2>
			</div>
			<ol class="nura-in-steps">
				<?php foreach ( $ni_steps as $i => $step ) : ?>
					<li>
						<span class="nura-in-steps__n"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<strong><?php echo esc_html( $step[0] ); ?></strong>
						<?php if ( '' !== $step[1] ) : ?>
							<span><?php echo esc_html( $step[1] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
	<?php endif; ?>

	<section class="nura-in-section">
		<div class="nura-container">
			<div class="nura-in-care">
				<div>
					<p class="nura-eyebrow"><?php esc_html_e( 'Aftercare', 'nura-beauty' ); ?></p>
					<h2><?php echo esc_html( nura_opt( 'nura_inst_care_title' ) ); ?></h2>
					<p><?php echo esc_html( nura_opt( 'nura_inst_care_text' ) ); ?></p>
				</div>
				<div class="nura-in-care__cta">
					<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $ni_book_inst ); ?>"><?php echo esc_html( nura_opt( 'nura_inst_cta' ) ); ?></a>
					<?php if ( $ni_circle ) : ?>
						<a class="nura-in-btn nura-in-btn--line" href="<?php echo esc_url( $ni_circle ); ?>"><?php esc_html_e( 'Join The NURA Circle', 'nura-beauty' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<?php if ( $ni_faqs ) : ?>
	<section class="nura-in-section nura-in-section--tight">
		<div class="nura-container nura-in-faq">
			<div class="nura-in-head">
				<p class="nura-eyebrow"><?php esc_html_e( 'Good to know', 'nura-beauty' ); ?></p>
				<h2><?php esc_html_e( 'Questions about installation', 'nura-beauty' ); ?></h2>
			</div>
			<div class="nura-in-faq__list">
				<?php foreach ( $ni_faqs as $qa ) : ?>
					<details>
						<summary><?php echo esc_html( $qa[0] ); ?></summary>
						<p><?php echo esc_html( $qa[1] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="nura-in-final">
		<div class="nura-container nura-in-final__inner">
			<div>
				<h2><?php esc_html_e( 'Ready for your new look?', 'nura-beauty' ); ?></h2>
				<p><?php esc_html_e( 'Pick a time that suits you and we confirm on WhatsApp.', 'nura-beauty' ); ?></p>
			</div>
			<div class="nura-in-care__cta">
				<a class="nura-in-btn nura-in-btn--gold" href="<?php echo esc_url( $ni_book_inst ); ?>"><?php echo esc_html( nura_opt( 'nura_inst_cta' ) ); ?></a>
				<a class="nura-in-btn nura-in-btn--line" href="tel:<?php echo esc_attr( $ni_tel ); ?>"><?php echo esc_html( $ni_phone ); ?></a>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
