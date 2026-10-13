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
			<form role="search" method="get" class="nura-404-search" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="max-width:520px;margin:1.5rem auto;display:flex;gap:.5rem">
				<label class="screen-reader-text" for="nura-404-s"><?php esc_html_e( 'Search wigs', 'nura-beauty' ); ?></label>
				<input id="nura-404-s" type="search" name="s" placeholder="<?php esc_attr_e( 'Search wigs, e.g. bob, water curl', 'nura-beauty' ); ?>" style="flex:1;padding:.8rem 1rem;border-radius:999px;border:1px solid #d8cfc4">
				<input type="hidden" name="post_type" value="product">
				<button type="submit" class="nura-btn nura-btn--gold"><?php esc_html_e( 'Search', 'nura-beauty' ); ?></button>
			</form>
			<p style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
				<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Shop all wigs', 'nura-beauty' ); ?></a>
				<a class="nura-btn nura-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return Home', 'nura-beauty' ); ?></a>
			</p>
		</div>
	</section>
</main>
<?php get_footer();
