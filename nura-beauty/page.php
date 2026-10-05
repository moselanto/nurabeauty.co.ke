<?php
/**
 * Standard page template with a luxury page hero.
 * @package NURA_Beauty
 */
get_header();
while ( have_posts() ) : the_post(); ?>
	<main id="primary" class="site-main">
		<div class="nura-page-hero">
			<div class="nura-container">
				<p class="nura-eyebrow"><?php echo esc_html( nura_opt( 'nura_tagline' ) ); ?></p>
				<h1><?php the_title(); ?></h1>
			</div>
		</div>
		<div class="nura-container nura-page">
			<?php nura_breadcrumbs(); ?>
			<article <?php post_class(); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		</div>
	</main>
<?php endwhile;
get_footer();
