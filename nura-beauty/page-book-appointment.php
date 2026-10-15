<?php
/**
 * Book Appointment page (slug: book-appointment) - v1.38.0.
 *
 * Replaces the plain text + unstyled form with a guided booking page:
 * service cards, a clear form (name, phone, service, how to meet, date, time,
 * notes), the NURA booking endpoint (validates, logs, returns a pre-filled
 * WhatsApp message), studio details and a short FAQ. Works without the NURA
 * Experience plugin too: the form then opens WhatsApp with the details.
 *
 * @package NURA_Beauty
 */
get_header();

$nb_phone = nura_opt( 'nura_phone' );
if ( ! $nb_phone ) {
	$nb_phone = '+254 714 994 898';
}
$nb_tel = preg_replace( '/[^0-9+]/', '', $nb_phone );
$nb_wa  = nura_opt( 'nura_whatsapp' );
if ( ! $nb_wa ) {
	$nb_wa = 'https://wa.me/' . ltrim( $nb_tel, '+' );
}
$nb_services = array(
	'consultation' => array( __( 'Free consultation', 'nura-beauty' ), __( '15 minutes. Talk through your style, face shape and budget and get honest advice on the right unit.', 'nura-beauty' ), __( 'Free', 'nura-beauty' ) ),
	'fitting'      => array( __( 'Wig fitting', 'nura-beauty' ), __( 'Try units on in our Nairobi studio and find the cap size, length and density that suit you.', 'nura-beauty' ), __( 'Studio', 'nura-beauty' ) ),
	'installation' => array( __( 'Installation & styling', 'nura-beauty' ), __( 'Professional install, restyle, colour or revamp of your NURA or own unit.', 'nura-beauty' ), __( 'Studio', 'nura-beauty' ) ),
	'bridal'       => array( __( 'Bridal & occasion', 'nura-beauty' ), __( 'Unit, trial, install and touch-up planned as one experience for your big day.', 'nura-beauty' ), __( 'By arrangement', 'nura-beauty' ) ),
);
$nb_pre = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : 'consultation'; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! isset( $nb_services[ $nb_pre ] ) ) {
	$nb_pre = 'consultation';
}
$nb_endpoint = function_exists( 'rest_url' ) ? rest_url( 'nurax/v1/stylist-booking' ) : '';
?>
<main id="primary" class="site-main nura-book-page">

	<section class="nura-bk-hero">
		<div class="nura-container">
			<?php nura_breadcrumbs(); ?>
			<p class="nura-eyebrow"><?php esc_html_e( 'NURA Studio', 'nura-beauty' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="nura-bk-hero__lede"><?php esc_html_e( 'Book a free consultation, a fitting or an installation. Pick a time that suits you and we confirm on WhatsApp.', 'nura-beauty' ); ?></p>
			<ul class="nura-bk-hero__facts">
				<li><?php esc_html_e( 'Free 15-minute consultation', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Studio, video call or WhatsApp', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Mon - Sat, 8am - 8pm', 'nura-beauty' ); ?></li>
			</ul>
		</div>
	</section>

	<section class="nura-bk">
		<div class="nura-container nura-bk__grid">

			<div class="nura-bk__main">
				<h2 class="nura-bk__h"><span>1</span><?php esc_html_e( 'Choose your appointment', 'nura-beauty' ); ?></h2>
				<div class="nura-bk__services" role="radiogroup" aria-label="<?php esc_attr_e( 'Appointment type', 'nura-beauty' ); ?>">
					<?php foreach ( $nb_services as $key => $s ) : ?>
						<label class="nura-bk__svc">
							<input type="radio" name="nb_service_pick" value="<?php echo esc_attr( $key ); ?>" <?php checked( $nb_pre, $key ); ?>>
							<span class="nura-bk__svc-body">
								<span class="nura-bk__svc-top"><strong><?php echo esc_html( $s[0] ); ?></strong><em><?php echo esc_html( $s[2] ); ?></em></span>
								<span class="nura-bk__svc-d"><?php echo esc_html( $s[1] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<h2 class="nura-bk__h"><span>2</span><?php esc_html_e( 'How it works', 'nura-beauty' ); ?></h2>
				<ol class="nura-bk__steps">
					<li><strong><?php esc_html_e( 'Send your request', 'nura-beauty' ); ?></strong><?php esc_html_e( 'Pick a service, date and time. It takes under a minute.', 'nura-beauty' ); ?></li>
					<li><strong><?php esc_html_e( 'We confirm on WhatsApp', 'nura-beauty' ); ?></strong><?php esc_html_e( 'Our team confirms your slot or suggests the nearest free one.', 'nura-beauty' ); ?></li>
					<li><strong><?php esc_html_e( 'Meet your stylist', 'nura-beauty' ); ?></strong><?php esc_html_e( 'In the studio, on a video call or on WhatsApp. Bring a photo of the look you love.', 'nura-beauty' ); ?></li>
				</ol>

				<div class="nura-bk__studio">
					<div>
						<p class="nura-bk__studio-k"><?php esc_html_e( 'Studio', 'nura-beauty' ); ?></p>
						<p class="nura-bk__studio-v"><?php esc_html_e( 'Imenti House, Moi Avenue, Nairobi CBD', 'nura-beauty' ); ?></p>
						<p class="nura-bk__studio-n"><?php esc_html_e( 'Visits by appointment so a stylist is ready for you.', 'nura-beauty' ); ?></p>
					</div>
					<div>
						<p class="nura-bk__studio-k"><?php esc_html_e( 'Hours', 'nura-beauty' ); ?></p>
						<p class="nura-bk__studio-v"><?php esc_html_e( 'Monday - Saturday, 8am - 8pm', 'nura-beauty' ); ?></p>
						<p class="nura-bk__studio-n"><a href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo rawurlencode( 'Imenti House, Moi Avenue, Nairobi' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'nura-beauty' ); ?> &#8594;</a></p>
					</div>
				</div>

				<div class="nura-bk__faq">
					<h2 class="nura-bk__h nura-bk__h--plain"><?php esc_html_e( 'Good to know', 'nura-beauty' ); ?></h2>
					<details><summary><?php esc_html_e( 'Is the consultation really free?', 'nura-beauty' ); ?></summary><p><?php esc_html_e( 'Yes. The 15-minute consultation is free, in the studio, on a video call or on WhatsApp, and there is no obligation to buy.', 'nura-beauty' ); ?></p></details>
					<details><summary><?php esc_html_e( 'Can you install a wig I bought elsewhere?', 'nura-beauty' ); ?></summary><p><?php esc_html_e( 'Yes. Tell us about the unit in the notes and we will advise what it needs before your appointment.', 'nura-beauty' ); ?></p></details>
					<details><summary><?php esc_html_e( 'How do I change or cancel?', 'nura-beauty' ); ?></summary><p><?php esc_html_e( 'Message us on WhatsApp as early as you can and we will move your slot.', 'nura-beauty' ); ?></p></details>
					<details><summary><?php esc_html_e( 'What should I bring?', 'nura-beauty' ); ?></summary><p><?php esc_html_e( 'A photo of the look you want, and your own unit if you are booking an installation or revamp.', 'nura-beauty' ); ?></p></details>
				</div>
			</div>

			<aside class="nura-bk__aside">
				<form class="nura-bk__form" data-nura-book data-endpoint="<?php echo esc_url( $nb_endpoint ); ?>" data-wa="<?php echo esc_url( $nb_wa ); ?>" novalidate>
					<p class="nura-bk__form-h"><?php esc_html_e( 'Request your appointment', 'nura-beauty' ); ?></p>
					<input type="hidden" name="service" value="<?php echo esc_attr( $nb_pre ); ?>">
					<div class="nura-bk__hp" aria-hidden="true"><label><?php esc_html_e( 'Leave empty', 'nura-beauty' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

					<div class="nura-bk__f">
						<label for="nb-name"><?php esc_html_e( 'Full name', 'nura-beauty' ); ?></label>
						<input id="nb-name" name="name" type="text" autocomplete="name" placeholder="<?php esc_attr_e( 'e.g. Jane Wanjiku', 'nura-beauty' ); ?>" required>
					</div>
					<div class="nura-bk__f">
						<label for="nb-phone"><?php esc_html_e( 'Phone / WhatsApp', 'nura-beauty' ); ?></label>
						<div class="nura-bk__phone"><span>+254</span><input id="nb-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="712 345 678" required></div>
					</div>
					<div class="nura-bk__f">
						<span class="nura-bk__lbl"><?php esc_html_e( 'How would you like to meet?', 'nura-beauty' ); ?></span>
						<div class="nura-bk__seg" role="radiogroup">
							<label><input type="radio" name="type" value="studio" checked><span><?php esc_html_e( 'Studio', 'nura-beauty' ); ?></span></label>
							<label><input type="radio" name="type" value="video"><span><?php esc_html_e( 'Video call', 'nura-beauty' ); ?></span></label>
							<label><input type="radio" name="type" value="whatsapp"><span><?php esc_html_e( 'WhatsApp', 'nura-beauty' ); ?></span></label>
						</div>
					</div>
					<div class="nura-bk__row">
						<div class="nura-bk__f">
							<label for="nb-date"><?php esc_html_e( 'Date', 'nura-beauty' ); ?></label>
							<input id="nb-date" name="date" type="date" required>
						</div>
						<div class="nura-bk__f">
							<label for="nb-time"><?php esc_html_e( 'Time', 'nura-beauty' ); ?></label>
							<select id="nb-time" name="time" required>
								<option value=""><?php esc_html_e( 'Choose', 'nura-beauty' ); ?></option>
								<?php for ( $h = 9; $h <= 17; $h++ ) : foreach ( array( '00', '30' ) as $m ) : $v = sprintf( '%02d:%s', $h, $m ); ?>
									<option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( date_i18n( 'g:i a', strtotime( '2000-01-01 ' . $v ) ) ); ?></option>
								<?php endforeach; endfor; ?>
							</select>
						</div>
					</div>
					<div class="nura-bk__f">
						<label for="nb-notes"><?php esc_html_e( 'The look you want (optional)', 'nura-beauty' ); ?></label>
						<textarea id="nb-notes" name="notes" rows="3" maxlength="300" placeholder="<?php esc_attr_e( 'e.g. 18 inch body wave, natural hairline, for a wedding on 5 Dec', 'nura-beauty' ); ?>"></textarea>
					</div>
					<p class="nura-bk__err" role="alert" hidden></p>
					<button type="submit" class="nura-bk__submit"><?php esc_html_e( 'Request appointment', 'nura-beauty' ); ?></button>
					<p class="nura-bk__small"><?php esc_html_e( 'No payment now. We confirm your slot on WhatsApp.', 'nura-beauty' ); ?></p>

					<div class="nura-bk__done" hidden>
						<p class="nura-bk__done-h"><?php esc_html_e( 'Almost done', 'nura-beauty' ); ?></p>
						<p class="nura-bk__done-t"></p>
						<a class="nura-bk__wa" href="<?php echo esc_url( $nb_wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Send on WhatsApp', 'nura-beauty' ); ?></a>
					</div>
				</form>
				<div class="nura-bk__alt">
					<a href="tel:<?php echo esc_attr( $nb_tel ); ?>"><?php esc_html_e( 'Prefer to call?', 'nura-beauty' ); ?> <strong><?php echo esc_html( $nb_phone ); ?></strong></a>
				</div>
			</aside>

		</div>
	</section>
</main>
<?php
get_footer();
