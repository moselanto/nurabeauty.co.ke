<?php
/**
 * My Account (logged out): branded black-and-gold sign-in / register cards.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_is_account_login_screen() {
	return function_exists( 'is_account_page' ) && is_account_page() && ! is_user_logged_in();
}

/** Friendlier headings and button text on the account login screen only. */
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'woocommerce' !== $domain || is_admin() || ! nura_is_account_login_screen() ) {
		return $translation;
	}
	$map = array(
		'Login'                                                      => __( 'Sign in', 'nura-beauty' ),
		'Register'                                                   => __( 'Create an account', 'nura-beauty' ),
		'Log in'                                                     => __( 'Sign in', 'nura-beauty' ),
		'Lost your password?'                                        => __( 'Forgot your password?', 'nura-beauty' ),
		'A link to set a new password will be sent to your email address.' => __( 'We will email you a secure link to set your password.', 'nura-beauty' ),
	);
	return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
}, 20, 3 );

/** Short intro above the two cards. */
add_action( 'woocommerce_before_customer_login_form', function () {
	echo '<div class="nura-acct-intro"><p class="nura-eyebrow">' . esc_html__( 'The NURA Circle', 'nura-beauty' ) . '</p>'
		. '<h2>' . esc_html__( 'Welcome back, queen', 'nura-beauty' ) . '</h2>'
		. '<p>' . esc_html__( 'Sign in to track orders, reorder your favourites and check out faster. New here? Create an account in seconds.', 'nura-beauty' ) . '</p></div>';
} );

add_action( 'wp_head', function () {
	if ( ! nura_is_account_login_screen() ) {
		return;
	}
	?>
	<style id="nura-account-login-css">
	.woocommerce-account .entry-content{max-width:1000px}
	.nura-acct-intro{text-align:center;max-width:620px;margin:0 auto 2rem}
	.nura-acct-intro h2{font-family:var(--font-display,'Playfair Display',serif);font-size:clamp(1.6rem,3vw,2.2rem);margin:.3rem 0 .5rem}
	.nura-acct-intro p{color:#555;margin:0}
	.nura-acct-intro .nura-eyebrow{color:#7a5a12;letter-spacing:.18em;text-transform:uppercase;font-size:.72rem}
	#customer_login.u-columns{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:stretch}
	#customer_login.u-columns::before,#customer_login.u-columns::after{display:none}
	#customer_login .u-column1,#customer_login .u-column2{float:none;width:auto;margin:0;border-radius:16px;padding:2rem 2rem 1.6rem}
	#customer_login .u-column1{background:#0e0e0e;color:#f3e9dc;border:1px solid rgba(201,162,75,.4)}
	#customer_login .u-column2{background:#faf6f0;border:1px solid rgba(201,162,75,.3)}
	#customer_login h2{font-family:var(--font-display,'Playfair Display',serif);font-size:1.5rem;margin:0 0 1.2rem}
	#customer_login .u-column1 h2{color:#f3e9dc}
	#customer_login label{font-family:var(--font-ui,'Jost',sans-serif);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a5a12;margin-bottom:.4rem;display:block}
	#customer_login .u-column1 label{color:#c9a24b}
	#customer_login .required{color:#c9a24b}
	#customer_login input.input-text{width:100%;box-sizing:border-box;border-radius:10px;padding:.8rem .95rem;font-size:1rem;border:1px solid #ddd2c2;background:#fff;color:#0e0e0e;transition:border-color .2s,box-shadow .2s}
	#customer_login .u-column1 input.input-text{background:#1a1a1a;border-color:rgba(243,233,220,.2);color:#f3e9dc}
	#customer_login input.input-text:focus{outline:0;border-color:#c9a24b;box-shadow:0 0 0 3px rgba(201,162,75,.22)}
	#customer_login .woocommerce-form-login__rememberme{display:flex;align-items:center;gap:.5rem;text-transform:none;letter-spacing:0;font-size:.9rem;color:rgba(243,233,220,.85);margin-bottom:1rem}
	#customer_login input[type=checkbox]{accent-color:#c9a24b;width:18px;height:18px}
	#customer_login button.button{width:100%;border:0;border-radius:999px;padding:.9rem 1.5rem;font-family:var(--font-ui,'Jost',sans-serif);font-weight:600;letter-spacing:.1em;text-transform:uppercase;font-size:.82rem;cursor:pointer;transition:transform .2s,box-shadow .2s,background .2s}
	#customer_login .u-column1 button.button{background:#c9a24b;color:#0e0e0e}
	#customer_login .u-column1 button.button:hover{background:#e0bb63;transform:translateY(-1px);box-shadow:0 10px 24px rgba(201,162,75,.3)}
	#customer_login .u-column2 button.button{background:#0e0e0e;color:#f3e9dc}
	#customer_login .u-column2 button.button:hover{background:#2a2a2a;transform:translateY(-1px)}
	#customer_login .lost_password{text-align:center;margin:1rem 0 0}
	#customer_login .lost_password a{color:#c9a24b;text-decoration:underline;text-underline-offset:3px}
	#customer_login .u-column2 p,#customer_login .woocommerce-privacy-policy-text p{font-size:.88rem;color:#555}
	#customer_login .woocommerce-privacy-policy-text a{color:#7a5a12}
	@media(max-width:768px){#customer_login.u-columns{grid-template-columns:1fr}#customer_login .u-column1,#customer_login .u-column2{padding:1.5rem 1.2rem}}
	</style>
	<?php
}, 32 );
