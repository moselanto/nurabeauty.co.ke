<?php
/**
 * Search form.
 * @package NURA_Beauty
 */
?>
<form role="search" method="get" class="nura-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="nura-s"><?php esc_html_e( 'Search products &amp; articles', 'nura-beauty' ); ?></label>
	<input type="search" id="nura-s" name="s" placeholder="<?php esc_attr_e( 'Search wigs, styles, articles...', 'nura-beauty' ); ?>" value="<?php echo get_search_query(); ?>">
	<input type="hidden" name="post_type" value="product">
	<button type="submit" class="nura-btn"><?php esc_html_e( 'Search', 'nura-beauty' ); ?></button>
</form>
