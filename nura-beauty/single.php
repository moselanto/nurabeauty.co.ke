<?php
/**
 * Single blog post.
 * @package NURA_Beauty
 */
get_header();
while ( have_posts() ) : the_post(); ?>
	<main id="primary" class="site-main">
		<div class="nura-page-hero">
			<div class="nura-container">
				<p class="nura-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
				<h1><?php the_title(); ?></h1>
			</div>
		</div>
		<div class="nura-container nura-page">
			<?php nura_breadcrumbs(); ?>
			<article <?php post_class(); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="entry-content"><?php the_post_thumbnail( 'nura-hero' ); ?></div>
				<?php endif; ?>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
			<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
		</div>
	</main>
<?php endwhile;
get_footer();
