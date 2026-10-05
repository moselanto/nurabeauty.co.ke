<?php
/**
 * Search results (v1.12.0).
 *
 * Product-aware: a WooCommerce product search (post_type=product, used by the header
 * search trigger + overlay + search form) renders in the NURA product grid, so results
 * look like the shop; any other search keeps the editorial card list.
 *
 * @package NURA_Beauty
 */
get_header();
$nura_is_product_search = ( 'product' === get_query_var( 'post_type' ) ) || ( isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ); // phpcs:ignore WordPress.Security.NonceVerification
?>
<main id="primary" class="site-main">
	<div class="nura-page-hero">
		<div class="nura-container">
			<p class="nura-eyebrow"><?php echo esc_html( nura_opt( 'nura_tagline' ) ); ?></p>
			<h1><?php echo esc_html( sprintf( __( 'Search results for “%s”', 'nura-beauty' ), get_search_query( false ) ) ); ?></h1>
		</div>
	</div>
	<div class="nura-container nura-page">
		<?php nura_breadcrumbs(); ?>
		<?php if ( have_posts() ) : ?>
			<?php if ( $nura_is_product_search && function_exists( 'woocommerce_product_loop_start' ) ) : ?>
				<?php woocommerce_product_loop_start(); ?>
					<?php
					while ( have_posts() ) :
						the_post();
						global $product;
						$product = wc_get_product( get_the_ID() );
						if ( $product ) {
							wc_get_template_part( 'content', 'product' );
						}
					endwhile;
					?>
				<?php woocommerce_product_loop_end(); ?>
			<?php else : ?>
				<div class="nura-grid nura-grid--3">
					<?php while ( have_posts() ) : the_post(); ?>
						<article <?php post_class( 'nura-card' ); ?>>
							<a href="<?php the_permalink(); ?>" class="nura-card__media">
								<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'nura-portrait' ); } ?>
							</a>
							<div class="nura-card__body">
								<h3 class="nura-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
							</div>
						</article>
					<?php endwhile; ?>
				</div>
			<?php endif; ?>
			<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<div class="nura-search-empty">
				<p><?php esc_html_e( 'No results found. Explore our collections instead.', 'nura-beauty' ); ?></p>
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<a class="nura-btn nura-btn--gold" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Shop all wigs', 'nura-beauty' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php get_footer();
