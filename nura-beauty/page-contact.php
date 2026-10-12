<?php
/**
 * Contact page (slug: contact) - v1.43.0.
 *
 * Replaces the plain text + unstyled Contact Form 7 layout with:
 * dark hero, four quick-contact cards (WhatsApp, call, email, studio),
 * a styled message form (theme handler: nonce, honeypot, time-trap, rate limit),
 * a studio card with a lazy-loaded map, hours and booking link, quick-help links
 * and social profiles. Contact details come from NURA Options > Brand.
 *
 * @package NURA_Beauty
 */
get_header();

$nc_phone = nura_opt( 'nura_phone' ) ? nura_opt( 'nura_phone' ) : '+254 714 994 898';
$nc_tel   = preg_replace( '/[^0-9+]/', '', $nc_phone );
$nc_wa    = nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/' . ltrim( $nc_tel, '+' );
$nc_mail  = nura_opt( 'nura_email' ) ? nura_opt( 'nura_email' ) : 'care@nurabeauty.co.ke';
$nc_addr  = nura_opt( 'nura_address' ) ? nura_opt( 'nura_address' ) : 'Imenti House, Moi Avenue, Nairobi CBD';
$nc_hours = nura_opt( 'nura_hours' ) ? nura_opt( 'nura_hours' ) : 'Mon-Sat 9:00-18:00';
$nc_map_q = rawurlencode( 'Imenti House, Moi Avenue, Nairobi' );
$nc_sent  = isset( $_GET['nura_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification

$nc_page = function ( $slug ) {
	$p = get_page_by_path( $slug );
	return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
};
$nc_book = $nc_page( 'book-appointment' );

$nc_icon = function ( $name ) {
	$p = array(
		'wa'    => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.5-4.3A8.5 8.5 0 1 1 20.5 11.6z"/><path d="M9 8.5c.2 2.6 2.3 5 5 5.6l1-1.2 2 .9-.4 1.6c-3.6.4-8.2-3.8-8-7.6l1.6-.4.9 2z"/>',
		'phone' => '<path d="M5 4h3l1.5 4-2 1.5a11 11 0 0 0 7 7l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'   => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);
	return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[ $name ] . '</svg>';
};
?>
<main id="primary" class="site-main nura-ct-page">

	<section class="nura-bk-hero nura-ct-hero">
		<div class="nura-container">
			<?php nura_breadcrumbs(); ?>
			<p class="nura-eyebrow"><?php esc_html_e( 'We are here for you', 'nura-beauty' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="nura-bk-hero__lede"><?php esc_html_e( 'Questions about an order, choosing the right wig or booking a fitting? Talk to a real NURA stylist - WhatsApp is the fastest way to reach us.', 'nura-beauty' ); ?></p>
		</div>
	</section>

	<section class="nura-ct-cards-wrap">
		<div class="nura-container">
			<div class="nura-ct-cards">
				<a class="nura-ct-card nura-ct-card--wa" href="<?php echo esc_url( $nc_wa ); ?>" target="_blank" rel="noopener">
					<span class="nura-ct-card__ic"><?php echo $nc_icon( 'wa' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'WhatsApp', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php esc_html_e( 'Chat with a stylist', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'Fastest for orders and advice', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Start chat', 'nura-beauty' ); ?> <?php echo $nc_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
				<a class="nura-ct-card" href="tel:<?php echo esc_attr( $nc_tel ); ?>">
					<span class="nura-ct-card__ic"><?php echo $nc_icon( 'phone' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'Call us', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php echo esc_html( $nc_phone ); ?></span>
					<span class="nura-ct-card__n"><?php echo esc_html( $nc_hours ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Call now', 'nura-beauty' ); ?> <?php echo $nc_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
				<a class="nura-ct-card" href="mailto:<?php echo esc_attr( $nc_mail ); ?>">
					<span class="nura-ct-card__ic"><?php echo $nc_icon( 'mail' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'Email', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php echo esc_html( $nc_mail ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'We reply within one business day', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Send email', 'nura-beauty' ); ?> <?php echo $nc_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
				<a class="nura-ct-card" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo esc_attr( $nc_map_q ); ?>" target="_blank" rel="noopener">
					<span class="nura-ct-card__ic"><?php echo $nc_icon( 'pin' ); // phpcs:ignore ?></span>
					<span class="nura-ct-card__k"><?php esc_html_e( 'Visit the studio', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__v"><?php echo esc_html( $nc_addr ); ?></span>
					<span class="nura-ct-card__n"><?php esc_html_e( 'By appointment', 'nura-beauty' ); ?></span>
					<span class="nura-ct-card__go"><?php esc_html_e( 'Get directions', 'nura-beauty' ); ?> <?php echo $nc_icon( 'arrow' ); // phpcs:ignore ?></span>
				</a>
			</div>
		</div>
	</section>

	<section class="nura-ct">
		<div class="nura-container nura-ct__grid">

			<div class="nura-ct__formcol">
				<h2 class="nura-ct__h"><?php esc_html_e( 'Send us a message', 'nura-beauty' ); ?></h2>
				<p class="nura-ct__sub"><?php esc_html_e( 'Tell us what you need and we will get back to you within one business day.', 'nura-beauty' ); ?></p>

				<?php if ( $nc_sent ) : ?>
					<div class="nura-ct__ok" role="status">
						<strong><?php esc_html_e( 'Thank you - your message is on its way.', 'nura-beauty' ); ?></strong>
						<span><?php esc_html_e( 'We will reply shortly. Need an answer now?', 'nura-beauty' ); ?> <a href="<?php echo esc_url( $nc_wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Message us on WhatsApp', 'nura-beauty' ); ?></a></span>
					</div>
				<?php endif; ?>

				<form class="nura-ct__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="nura_contact">
					<input type="hidden" name="nura_kind" value="enquiry">
					<?php wp_nonce_field( 'nura_contact' ); ?>
					<div class="nura-bk__hp" aria-hidden="true"><label><?php esc_html_e( 'Leave empty', 'nura-beauty' ); ?><input type="text" name="nura_hp" tabindex="-1" autocomplete="off" value=""></label></div>
					<input type="hidden" name="nura_ct_ts" value="<?php echo esc_attr( time() ); ?>">

					<fieldset class="nura-ct__topics">
						<legend><?php esc_html_e( 'What is it about?', 'nura-beauty' ); ?></legend>
						<?php
						$nc_topics = array(
							__( 'An order', 'nura-beauty' ),
							__( 'Choosing a wig', 'nura-beauty' ),
							__( 'Installation or fitting', 'nura-beauty' ),
							__( 'Wholesale or corporate', 'nura-beauty' ),
							__( 'Something else', 'nura-beauty' ),
						);
						foreach ( $nc_topics as $i => $t ) :
							?>
							<label><input type="radio" name="nura_service" value="<?php echo esc_attr( $t ); ?>" <?php checked( 0, $i ); ?>><span><?php echo esc_html( $t ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>

					<div class="nura-ct__row">
						<div class="nura-bk__f">
							<label for="nc-name"><?php esc_html_e( 'Your name', 'nura-beauty' ); ?></label>
							<input id="nc-name" name="nura_name" type="text" autocomplete="name" placeholder="<?php esc_attr_e( 'e.g. Jane Wanjiku', 'nura-beauty' ); ?>" required>
						</div>
						<div class="nura-bk__f">
							<label for="nc-contact"><?php esc_html_e( 'Phone / WhatsApp or email', 'nura-beauty' ); ?></label>
							<input id="nc-contact" name="nura_contact_val" type="text" autocomplete="email" placeholder="<?php esc_attr_e( '0712 345 678 or you@email.com', 'nura-beauty' ); ?>" required>
						</div>
					</div>
					<div class="nura-bk__f">
						<label for="nc-msg"><?php esc_html_e( 'Your message', 'nura-beauty' ); ?></label>
						<textarea id="nc-msg" name="nura_message" rows="5" maxlength="2000" placeholder="<?php esc_attr_e( 'e.g. Order number, the wig you are looking at, or the look you want', 'nura-beauty' ); ?>" required></textarea>
					</div>
					<button type="submit" class="nura-bk__submit"><?php esc_html_e( 'Send message', 'nura-beauty' ); ?></button>
					<p class="nura-bk__small"><?php esc_html_e( 'We only use your details to reply to you.', 'nura-beauty' ); ?></p>
				</form>
			</div>

			<aside class="nura-ct__aside">
				<div class="nura-ct__studio">
					<div class="nura-ct__map">
						<iframe title="<?php esc_attr_e( 'Map to the NURA studio', 'nura-beauty' ); ?>" src="https://www.google.com/maps?q=<?php echo esc_attr( $nc_map_q ); ?>&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
					</div>
					<div class="nura-ct__studio-body">
						<p class="nura-bk__studio-k"><?php esc_html_e( 'The NURA studio', 'nura-beauty' ); ?></p>
						<p class="nura-ct__line"><?php echo $nc_icon( 'pin' ); // phpcs:ignore ?><span><?php echo esc_html( $nc_addr ); ?></span></p>
						<p class="nura-ct__line"><?php echo $nc_icon( 'clock' ); // phpcs:ignore ?><span><?php echo esc_html( $nc_hours ); ?></span></p>
						<p class="nura-ct__note"><?php esc_html_e( 'Come in for a consultation, fitting, installation or styling. Visits are by appointment so a stylist is ready for you.', 'nura-beauty' ); ?></p>
						<?php if ( $nc_book ) : ?>
							<a class="nura-ct__book" href="<?php echo esc_url( $nc_book ); ?>"><?php esc_html_e( 'Book an appointment', 'nura-beauty' ); ?></a>
						<?php endif; ?>
					</div>
				</div>

				<?php
				$nc_help = array_filter( array(
					'track-order' => __( 'Track your order', 'nura-beauty' ),
					'delivery'    => __( 'Delivery times and costs', 'nura-beauty' ),
					'returns'     => __( 'Returns and exchanges', 'nura-beauty' ),
					'faq'         => __( 'Frequently asked questions', 'nura-beauty' ),
				) );
				$nc_links = array();
				foreach ( $nc_help as $slug => $label ) {
					$u = $nc_page( $slug );
					if ( $u ) {
						$nc_links[ $u ] = $label;
					}
				}
				if ( $nc_links ) :
					?>
					<div class="nura-ct__help">
						<p class="nura-bk__studio-k"><?php esc_html_e( 'Quick answers', 'nura-beauty' ); ?></p>
						<ul>
							<?php foreach ( $nc_links as $u => $label ) : ?>
								<li><a href="<?php echo esc_url( $u ); ?>"><?php echo esc_html( $label ); ?><?php echo $nc_icon( 'arrow' ); // phpcs:ignore ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php
				$nc_social = function_exists( 'nura_social_links' ) ? nura_social_links() : array();
				if ( $nc_social ) :
					?>
					<div class="nura-ct__social">
						<p class="nura-bk__studio-k"><?php esc_html_e( 'Follow the house', 'nura-beauty' ); ?></p>
						<p class="nura-ct__note"><?php esc_html_e( 'Transformations, tutorials and new drops.', 'nura-beauty' ); ?></p>
						<div class="nura-ct__social-row">
							<?php foreach ( $nc_social as $s ) : $key = isset( $s['key'] ) ? $s['key'] : ''; ?>
								<a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( isset( $s['label'] ) ? $s['label'] : ucfirst( $key ) ); ?>"><?php echo function_exists( 'nura_social_icon' ) ? nura_social_icon( $key ) : esc_html( ucfirst( $key ) ); // phpcs:ignore ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</aside>

		</div>
	</section>
</main>
<?php
get_footer();
