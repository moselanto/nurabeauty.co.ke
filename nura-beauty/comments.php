<?php
/**
 * Comments.
 * @package NURA_Beauty
 */
if ( post_password_required() ) { return; }
?>
<div id="comments" class="nura-comments entry-content">
	<?php if ( have_comments() ) : ?>
		<h2><?php printf( esc_html( _n( '%s Comment', '%s Comments', get_comments_number(), 'nura-beauty' ) ), number_format_i18n( get_comments_number() ) ); ?></h2>
		<ol class="comment-list">
			<?php wp_list_comments( array( 'style' => 'ol', 'avatar_size' => 48 ) ); ?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</div>
