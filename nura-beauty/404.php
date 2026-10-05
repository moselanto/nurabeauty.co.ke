<?php
/**
 * 404.
 * @package NURA_Beauty
 */
get_header(); ?>
<main id="primary" class="site-main">
	<section class="section section--ink text-center">
		<div class="nura-container">
			<p class="nura-eyebrow"><?php esc_html_e( 'Lost your way?', 'nura-beauty' ); ?></p>
			<h1><?php esc_html_e( 'This page could not be found', 'nura-beauty' ); ?></h1>
			<p class="nura-lede"><?php esc_html_e( 'The page you were looking for has moved or no longer exists. Let us guide you back.', 'nura-beauty' ); ?></p>
			<p><a class="nura-btn nura-btn--gold" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return Home', 'nura-beauty' ); ?></a></p>
		</div>
	</section>
</main>
<?php get_footer();
