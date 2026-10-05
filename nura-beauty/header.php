<?php
/**
 * Header.
 * @package NURA_Beauty
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'nura-beauty' ); ?></a>

<?php nura_announcement_bar(); ?>

<header class="site-header" id="site-header">
	<div class="nura-container nura-header-inner">
		<button class="nura-icon-btn nura-burger" aria-label="<?php esc_attr_e( 'Open menu', 'nura-beauty' ); ?>" data-nura-drawer><span aria-hidden="true">&#9776;</span></button>

		<div class="nura-logo-wrap">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				printf(
					'<a class="nura-logo" href="%s">%s</a>',
					esc_url( home_url( '/' ) ),
					esc_html( nura_opt( 'nura_brand_name' ) )
				);
			}
			?>
		</div>

		<nav class="nura-nav" aria-label="<?php esc_attr_e( 'Primary', 'nura-beauty' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'fallback_cb'    => 'nura_default_menu',
				'depth'          => 2,
			) );
			?>
		</nav>

		<div class="nura-header-actions">
			<?php
			$nura_phone   = function_exists( 'nura_opt' ) ? nura_opt( 'nura_phone' ) : '';
			$nura_wa_link = function_exists( 'nura_opt' ) ? nura_opt( 'nura_whatsapp' ) : '';
			// Never render an empty contact affordance: fall back to the published number.
			if ( ! $nura_phone && ! $nura_wa_link ) {
				$nura_phone = '+254 714 994 898';
			}
			if ( $nura_phone || $nura_wa_link ) :
				$nura_contact_href = $nura_phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $nura_phone ) : $nura_wa_link;
				$nura_contact_ext  = $nura_phone ? '' : ' target="_blank" rel="noopener"';
				$nura_contact_txt  = $nura_phone ? $nura_phone : __( 'Contact', 'nura-beauty' );
			?>
			<a class="nura-icon-btn nura-contact-link" href="<?php echo esc_url( $nura_contact_href ); ?>"<?php echo $nura_contact_ext; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'Contact NURA', 'nura-beauty' ); ?>">
				<svg class="nura-contact-link__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.9.36 1.79.7 2.63a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.45-1.27a2 2 0 0 1 2.11-.45c.84.34 1.73.57 2.63.7A2 2 0 0 1 22 16.92z"/></svg>
				<span class="nura-contact-link__txt"><?php echo esc_html( $nura_contact_txt ); ?></span>
			</a>
			<?php endif; ?>
			<button class="nura-icon-btn nura-search-ic" type="button" data-nura-search-open aria-label="<?php esc_attr_e( 'Search', 'nura-beauty' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg></button>
			<a class="nura-icon-btn nura-search-txt" href="<?php echo esc_url( add_query_arg( 'post_type', 'product', home_url( '/?s=' ) ) ); ?>" data-nura-search-open aria-label="<?php esc_attr_e( 'Search', 'nura-beauty' ); ?>"><?php esc_html_e( 'Search', 'nura-beauty' ); ?></a>
			<?php if ( function_exists( 'nura_cart_button' ) ) { nura_cart_button(); } ?>
		</div>
	</div>
</header>

<!-- Mobile drawer -->
<div class="nura-overlay" data-nura-overlay></div>
<aside class="nura-drawer" data-nura-drawer-panel aria-hidden="true">
	<div class="nura-drawer__head">
		<a class="nura-logo nura-drawer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( nura_opt( 'nura_brand_name' ) ); ?></a>
		<button class="nura-drawer__close" type="button" data-nura-drawer aria-label="<?php esc_attr_e( 'Close menu', 'nura-beauty' ); ?>"><span aria-hidden="true">&times;</span></button>
	</div>

	<form role="search" method="get" class="nura-drawer__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search wigs, styles…', 'nura-beauty' ); ?>" aria-label="<?php esc_attr_e( 'Search products', 'nura-beauty' ); ?>">
		<input type="hidden" name="post_type" value="product">
		<button type="submit" class="nura-drawer__search-btn" aria-label="<?php esc_attr_e( 'Search', 'nura-beauty' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg></button>
	</form>

	<nav class="nura-drawer__nav" aria-label="<?php esc_attr_e( 'Mobile menu', 'nura-beauty' ); ?>">
		<?php
		wp_nav_menu( array(
			'theme_location' => 'mobile',
			'container'      => false,
			'fallback_cb'    => 'nura_default_menu',
		) );
		?>
	</nav>

	<div class="nura-drawer__foot">
		<a class="nura-btn nura-btn--gold nura-drawer__wa" href="<?php echo esc_url( nura_opt( 'nura_whatsapp' ) ); ?>"><?php esc_html_e( 'Chat on WhatsApp', 'nura-beauty' ); ?></a>
		<a class="nura-drawer__account" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?>"><?php esc_html_e( 'My Account', 'nura-beauty' ); ?></a>
	</div>
</aside>
<?php
/**
 * Ecommerce-focused fallback menu, shown only until a menu is assigned under
 * Appearance -> Menus. Mirrors the intended primary structure:
 * Shop Wigs | Hair & Extensions | Wig Care | Beauty | Services.
 */
function nura_default_menu() {
	$items = array(
		array( __( 'Shop Wigs', 'nura-beauty' ),         home_url( '/product-category/wigs/' ) ),
		array( __( 'Hair & Extensions', 'nura-beauty' ), home_url( '/product-category/hair-extensions/' ) ),
		array( __( 'Wig Care', 'nura-beauty' ),          home_url( '/product-category/wig-care/' ) ),
		array( __( 'Beauty', 'nura-beauty' ),            home_url( '/product-category/beauty/' ) ),
		array( __( 'Services', 'nura-beauty' ),          home_url( '/services/' ) ),
	);
	echo '<ul>';
	foreach ( $items as $it ) {
		echo '<li><a href="' . esc_url( $it[1] ) . '">' . esc_html( $it[0] ) . '</a></li>';
	}
	echo '</ul>';
}
