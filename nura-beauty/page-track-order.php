<?php
/**
 * Track Order page (v1.55.4): WooCommerce order lookup plus simple, accurate help.
 * WordPress uses this automatically for the page with the slug "track-order".
 *
 * @package NURA_Beauty
 */
get_header();
$wa = nura_opt( 'nura_whatsapp' ) ? nura_opt( 'nura_whatsapp' ) : 'https://wa.me/254714994898';
?>
<main id="primary" class="site-main nura-track">
	<div class="nura-page-hero">
		<div class="nura-container">
			<p class="nura-eyebrow"><?php esc_html_e( 'Your order', 'nura-beauty' ); ?></p>
			<h1><?php esc_html_e( 'Track your order', 'nura-beauty' ); ?></h1>
			<p class="nura-lede"><?php esc_html_e( 'Enter your order number and the email you used at checkout to see its status.', 'nura-beauty' ); ?></p>
		</div>
	</div>
	<div class="nura-container" style="max-width:760px;margin-inline:auto;padding-block:2rem 3rem">
		<div class="nura-track__form"><?php echo do_shortcode( '[woocommerce_order_tracking]' ); ?></div>
		<p class="nura-track__hint"><?php esc_html_e( 'Your order number is in your order confirmation email (for example, 1743). Signed in? See every order under My Account > Orders.', 'nura-beauty' ); ?></p>

		<h2><?php esc_html_e( 'What each status means', 'nura-beauty' ); ?></h2>
		<table class="nura-track__table">
			<tbody>
				<tr><th><?php esc_html_e( 'Pending payment', 'nura-beauty' ); ?></th><td><?php esc_html_e( 'We are waiting for your payment to come through.', 'nura-beauty' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Processing', 'nura-beauty' ); ?></th><td><?php esc_html_e( 'Your order is confirmed and being packed. Cash on delivery orders show this too.', 'nura-beauty' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Completed', 'nura-beauty' ); ?></th><td><?php esc_html_e( 'Your order has been delivered or collected.', 'nura-beauty' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'On hold', 'nura-beauty' ); ?></th><td><?php esc_html_e( 'We need to confirm something with you. We will call or WhatsApp you.', 'nura-beauty' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Cancelled or refunded', 'nura-beauty' ); ?></th><td><?php esc_html_e( 'The order was cancelled or your refund has been sent.', 'nura-beauty' ); ?></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Delivery times', 'nura-beauty' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Nairobi: same-day delivery for orders placed before 5 PM.', 'nura-beauty' ); ?></li>
			<li><?php esc_html_e( 'Outside Nairobi: sent by courier. We share the tracking details on WhatsApp once your parcel leaves.', 'nura-beauty' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Need help?', 'nura-beauty' ); ?></h2>
		<p><?php echo esc_html( sprintf( /* translators: %s: opening hours. */ __( 'Send us your order number on WhatsApp and we will update you (%s).', 'nura-beauty' ), nura_hours_text() ) ); ?></p>
		<p><a class="nura-btn nura-btn--gold" href="<?php echo esc_url( $wa ); ?>"><?php esc_html_e( 'Chat on WhatsApp', 'nura-beauty' ); ?></a></p>
	</div>
	<style>
	.nura-track h2{font-family:var(--font-display,'Playfair Display',serif);margin:2.2rem 0 .8rem}
	.nura-track__form .track_order{background:#0e0e0e;color:#f3e9dc;border:1px solid rgba(201,162,75,.4);border-radius:16px;padding:1.6rem}
	.nura-track__form .track_order p:first-child{color:rgba(243,233,220,.85)}
	.nura-track__form label{color:#c9a24b;font-size:.75rem;letter-spacing:.12em;text-transform:uppercase}
	.nura-track__form input.input-text{width:100%;box-sizing:border-box;border-radius:10px;padding:.8rem .95rem;background:#1a1a1a;border:1px solid rgba(243,233,220,.2);color:#f3e9dc}
	.nura-track__form button.button{background:#c9a24b;color:#0e0e0e;border:0;border-radius:999px;padding:.85rem 1.8rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase}
	.nura-track__hint{font-size:.9rem;color:#666;margin-top:.8rem}
	.nura-track__table{width:100%;border-collapse:collapse}
	.nura-track__table th,.nura-track__table td{padding:.7rem .8rem;border-bottom:1px solid #eee3d3;text-align:left;vertical-align:top}
	.nura-track__table th{white-space:nowrap;color:#7a5a12}
	</style>
</main>
<?php
get_footer();
