<?php
/**
 * Fallback template (blog / archive listing).
 * @package NURA_Beauty
 */
get_header();
?>
<main id="primary" class="site-main">
	<div class="nura-page-hero">
		<div class="nura-container">
			<p class="nura-eyebrow"><?php echo esc_html( nura_opt( 'nura_tagline' ) ); ?></p>
			<h1><?php echo is_home() ? esc_html__( 'Journal', 'nura-beauty' ) : esc_html( get_the_archive_title() ); ?></h1>
		</div>
	</div>
	<div class="nura-container nura-page">
		<?php nura_breadcrumbs(); ?>
		<?php if ( have_posts() ) : ?>
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
			<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing here yet. Please check back soon.', 'nura-beauty' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer();
