<?php
/**
 * Shop sidebar.
 * @package NURA_Beauty
 */
if ( ! is_active_sidebar( 'shop-sidebar' ) ) { return; }
?>
<aside class="nura-sidebar widget-area">
	<?php dynamic_sidebar( 'shop-sidebar' ); ?>
</aside>
