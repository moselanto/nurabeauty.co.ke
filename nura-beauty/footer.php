<?php
/**
 * Footer.
 * @package NURA_Beauty
 */
$payments = array_filter( array_map( 'trim', explode( ',', (string) nura_opt( 'nura_payments' ) ) ) );
if ( empty( $payments ) && function_exists( 'nura_payment_methods' ) ) {
	// Fall back to the merchant's actually-enabled WooCommerce gateways so the
	// footer badges never advertise a payment method that is switched off.
	$payments = nura_payment_methods();
}

/**
 * Footer column fallbacks (v1.17.0). Shown only until real footer menus are
 * assigned under Appearance -> Menus, so every column has balanced content and
 * points only at pages/archives that exist.
 */
if ( ! function_exists( 'nura_footer_menu_list' ) ) {
	function nura_footer_menu_list( $items ) {
		echo '<ul>';
		foreach ( $items as $it ) {
			echo '<li><a href="' . esc_url( $it[1] ) . '">' . esc_html( $it[0] ) . '</a></li>';
		}
		echo '</ul>';
	}
}
if ( ! function_exists( 'nura_footer_shop_menu' ) ) {
	function nura_footer_shop_menu() {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		nura_footer_menu_list( array(
			array( __( 'Shop All Wigs', 'nura-beauty' ),      $shop ),
			array( __( 'Human Hair Wigs', 'nura-beauty' ),    home_url( '/product-category/human-hair-wigs/' ) ),
			array( __( 'Lace Front Wigs', 'nura-beauty' ),    home_url( '/product-category/lace-front-wigs/' ) ),
			array( __( 'Hair & Extensions', 'nura-beauty' ),  home_url( '/product-category/hair-extensions/' ) ),
			array( __( 'Wig Care', 'nura-beauty' ),           home_url( '/product-category/wig-care/' ) ),
		) );
	}
}
if ( ! function_exists( 'nura_footer_help_menu' ) ) {
	function nura_footer_help_menu() {
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
		$cart    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		nura_footer_menu_list( array(
			array( __( 'My Account', 'nura-beauty' ),        $account ),
			array( __( 'Track / View Cart', 'nura-beauty' ), $cart ),
			array( __( 'Find Your Wig', 'nura-beauty' ),     home_url( '/ai-wig-finder/' ) ),
			array( __( 'Book a Fitting', 'nura-beauty' ),    home_url( '/book-appointment/' ) ),
			array( __( 'Chat on WhatsApp', 'nura-beauty' ),  nura_opt( 'nura_whatsapp' ) ),
		) );
	}
}
if ( ! function_exists( 'nura_footer_company_menu' ) ) {
	function nura_footer_company_menu() {
		nura_footer_menu_list( array(
			array( __( 'About NURA', 'nura-beauty' ),        home_url( '/about-us/' ) ),
			array( __( 'NURA Services', 'nura-beauty' ),     home_url( '/services/' ) ),
			array( __( 'Journal', 'nura-beauty' ),           home_url( '/journal/' ) ),
			array( __( 'The NURA Experience', 'nura-beauty' ), home_url( '/services/' ) ),
		) );
	}
}
?>
<footer class="site-footer" id="site-footer">
	<div class="nura-container">
		<div class="nura-foot-grid">
			<div class="nura-foot-col nura-foot-brand">
				<p><?php echo esc_html( nura_opt( 'nura_bio' ) ); ?></p>
				<ul class="nura-foot-contact">
					<li><a href="<?php echo esc_url( nura_opt( 'nura_whatsapp' ) ); ?>"><?php echo esc_html( nura_opt( 'nura_phone' ) ); ?></a></li>
					<li><a href="mailto:<?php echo esc_attr( nura_opt( 'nura_email' ) ); ?>"><?php echo esc_html( nura_opt( 'nura_email' ) ); ?></a></li>
					<li><?php echo esc_html( nura_opt( 'nura_address' ) ); ?></li>
				</ul>
				<div class="nura-newsletter">
					<h4><?php esc_html_e( 'Join The House', 'nura-beauty' ); ?></h4>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="nura-subscribe-form">
						<input type="hidden" name="action" value="nura_subscribe">
						<?php wp_nonce_field( 'nura_subscribe', 'nura_sub_nonce' ); ?>
						<input type="hidden" name="nura_sub_ts" value="<?php echo esc_attr( time() ); ?>">
						<p class="nura-hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden"><label><?php esc_html_e( 'Leave this field empty', 'nura-beauty' ); ?><input type="text" name="nura_hp" tabindex="-1" autocomplete="off" value=""></label></p>
						<input type="email" name="nura_email" placeholder="<?php esc_attr_e( 'Your email', 'nura-beauty' ); ?>" required>
						<button type="submit" class="nura-btn nura-btn--gold nura-subscribe-btn"><?php esc_html_e( 'Subscribe', 'nura-beauty' ); ?></button>
					</form>
				</div>
			</div>

			<div class="nura-foot-col">
				<h4><?php esc_html_e( 'Shop', 'nura-beauty' ); ?></h4>
				<?php wp_nav_menu( array( 'theme_location' => 'footer_shop', 'container' => false, 'fallback_cb' => 'nura_footer_shop_menu', 'depth' => 1 ) ); ?>
			</div>
			<div class="nura-foot-col">
				<h4><?php esc_html_e( 'Help & Support', 'nura-beauty' ); ?></h4>
				<?php wp_nav_menu( array( 'theme_location' => 'footer_help', 'container' => false, 'fallback_cb' => 'nura_footer_help_menu', 'depth' => 1 ) ); ?>
			</div>
			<div class="nura-foot-col">
				<h4><?php esc_html_e( 'The House', 'nura-beauty' ); ?></h4>
				<?php wp_nav_menu( array( 'theme_location' => 'footer_company', 'container' => false, 'fallback_cb' => 'nura_footer_company_menu', 'depth' => 1 ) ); ?>
			</div>
		</div>

		<div class="nura-foot-bottom">
			<span class="nura-signoff"><?php echo esc_html( nura_opt( 'nura_signoff' ) ); ?></span>
			<?php if ( $payments ) : ?>
			<div class="nura-pay">
				<?php foreach ( $payments as $p ) : ?><span><?php echo esc_html( $p ); ?></span><?php endforeach; ?>
			</div>
			<?php endif; ?>
			<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php echo esc_html( nura_opt( 'nura_brand_name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'nura-beauty' ); ?></span>
		</div>
	</div>
</footer>

<?php
$wa = nura_opt( 'nura_whatsapp' );
if ( $wa ) {
	printf( '<a class="nura-wa" href="%s" aria-label="%s" target="_blank" rel="noopener">WA</a>', esc_url( $wa ), esc_attr__( 'Chat on WhatsApp', 'nura-beauty' ) );
}
?>
<?php wp_footer(); ?>
</body>
</html>
