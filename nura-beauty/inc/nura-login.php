<?php
/**
 * NURA Beauty - branded login screen (wp-login.php).
 *
 * Split layout: an obsidian-and-gold brand panel on the left and a clean
 * login card on the right. Covers login, lost password, reset password and
 * registration screens. Collapses to a single column on phones.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Logo links to the store, not wordpress.org. */
add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );
add_filter( 'login_headertext', function () {
	return get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'NURA Beauty';
} );

/** Fonts + styles. */
add_action( 'login_enqueue_scripts', function () {
	wp_enqueue_style( 'nura-login-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Jost:wght@400;500&family=Montserrat:wght@400;500&display=swap', array(), null );

	$logo_id  = get_theme_mod( 'custom_logo' );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	$css = '
	:root{--ink:#0E0E0E;--gold:#C9A24B;--gold-soft:#E4C983;--ivory:#F3E9DC;--line:#E7E1D8;--text:#1b1b1b;--muted:#6f6a64}
	html,body.login{height:100%}
	body.login{margin:0;display:flex;min-height:100vh;background:#FBF8F4;font-family:Montserrat,"Helvetica Neue",Arial,sans-serif;color:var(--text)}
	.nura-login-brand{position:relative;flex:0 0 46%;max-width:640px;display:flex;flex-direction:column;justify-content:space-between;padding:56px 64px;color:var(--ivory);
		background:radial-gradient(120% 80% at 0% 0%,rgba(201,162,75,.22) 0%,rgba(201,162,75,0) 55%),radial-gradient(90% 70% at 100% 100%,rgba(58,30,46,.85) 0%,rgba(58,30,46,0) 60%),var(--ink);overflow:hidden}
	.nura-login-brand:after{content:"";position:absolute;inset:24px;border:1px solid rgba(201,162,75,.28);pointer-events:none}
	.nura-login-brand *{position:relative;z-index:1}
	.nura-lb-top{font-family:Jost,Arial,sans-serif;text-transform:uppercase;letter-spacing:.32em;font-size:12px;color:var(--gold)}
	.nura-lb-main h2{font-family:"Playfair Display",Georgia,serif;font-weight:500;font-size:44px;line-height:1.12;margin:18px 0 18px;color:#fff;letter-spacing:.005em}
	.nura-lb-main h2 em{font-style:italic;color:var(--gold-soft)}
	.nura-lb-main p{font-size:15px;line-height:1.7;color:rgba(243,233,220,.78);max-width:440px;margin:0 0 28px}
	.nura-lb-list{list-style:none;margin:0;padding:0}
	.nura-lb-list li{display:flex;gap:12px;align-items:flex-start;font-size:14px;line-height:1.55;margin:0 0 14px;color:rgba(243,233,220,.9)}
	.nura-lb-list li:before{content:"";flex:0 0 18px;height:18px;margin-top:1px;border-radius:50%;border:1px solid var(--gold);background:radial-gradient(circle,var(--gold) 0 3px,transparent 4px)}
	.nura-lb-foot{display:flex;flex-wrap:wrap;gap:8px 20px;font-family:Jost,Arial,sans-serif;font-size:12px;letter-spacing:.08em;color:rgba(243,233,220,.6)}
	.nura-lb-foot a{color:var(--gold-soft);text-decoration:none}
	body.login #login{flex:1;width:auto;max-width:420px;margin:auto;padding:48px 28px}
	.login h1{margin-bottom:28px}
	.login h1 a{' . ( $logo_url ? 'background-image:url(' . esc_url( $logo_url ) . ');background-size:contain;background-position:center;width:240px;height:72px;' : 'background:none;text-indent:0;width:auto;height:auto;font-family:"Playfair Display",serif;font-size:30px;color:var(--ink);letter-spacing:.2em;' ) . 'margin:0 auto;outline:0;box-shadow:none}
	.nura-login-title{text-align:center;margin:-8px 0 26px}
	.nura-login-title strong{display:block;font-family:"Playfair Display",Georgia,serif;font-weight:500;font-size:26px;color:var(--ink)}
	.nura-login-title span{display:block;margin-top:6px;font-size:13px;color:var(--muted)}
	.login form{margin:0;padding:30px 30px 26px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 18px 50px -24px rgba(14,14,14,.18)}
	.login label{font-family:Jost,Arial,sans-serif;font-size:13px;letter-spacing:.04em;color:#3a3632;margin-bottom:6px}
	.login form .input,.login input[type=text],.login input[type=password],.login input[type=email]{font-size:15px;padding:10px 14px;min-height:48px;border:1px solid #DCD5CB;border-radius:10px;background:#FDFBF8;box-shadow:none;margin:4px 0 18px}
	.login form .input:focus,.login input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,162,75,.18);outline:0}
	.login .button.wp-hide-pw{height:48px;min-height:48px;top:4px;color:var(--muted)}
	.login .button.wp-hide-pw:focus{border-color:var(--gold);box-shadow:none}
	.login .forgetmenot{margin-top:4px}
	.login input[type=checkbox]{border-color:#cfc6ba;border-radius:4px}
	.login input[type=checkbox]:checked:before{filter:sepia(1) saturate(4) hue-rotate(5deg)}
	.wp-core-ui .button-primary,.login .button-primary{float:none;display:block;width:100%;margin-top:22px;height:50px;min-height:50px;border:0;border-radius:999px;background:var(--ink);color:#fff;font-family:Jost,Arial,sans-serif;font-size:13px;letter-spacing:.24em;text-transform:uppercase;text-shadow:none;box-shadow:none;transition:background .2s,color .2s}
	.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus,.login .button-primary:hover{background:var(--gold);color:var(--ink);box-shadow:none}
	.login .submit{clear:both}
	.login #nav,.login #backtoblog{text-align:center;padding:0;margin:18px 0 0;font-size:13px}
	.login #nav a,.login #backtoblog a,.login .privacy-policy-link{color:#5c5650;text-decoration:none}
	.login #nav a:hover,.login #backtoblog a:hover{color:var(--gold)}
	.login .privacy-policy-page-link{margin:22px 0 0}
	.login #login_error,.login .message,.login .notice,.login .success{border:1px solid var(--line);border-left:3px solid var(--gold);border-radius:10px;box-shadow:none;background:#fff;font-size:13px}
	.login #login_error{border-left-color:#B3261E}
	.login .language-switcher{display:none}
	.interim-login .nura-login-brand,.interim-login .nura-login-title{display:none}
	@media (max-width:960px){
		body.login{flex-direction:column}
		.nura-login-brand{flex:none;max-width:none;padding:34px 26px 30px}
		.nura-login-brand:after{inset:12px}
		.nura-lb-main h2{font-size:30px;margin:12px 0 10px}
		.nura-lb-main p{font-size:14px;margin:0}
		.nura-lb-list,.nura-lb-foot{display:none}
		body.login #login{padding:32px 20px 40px;margin:0 auto;width:100%;box-sizing:border-box}
		.login form{padding:24px 20px}
	}';
	wp_register_style( 'nura-login', false, array(), defined( 'NURA_VERSION' ) ? NURA_VERSION : null );
	wp_enqueue_style( 'nura-login' );
	wp_add_inline_style( 'nura-login', $css );
} );

/** Brand panel (left column). */
add_action( 'login_header', function () {
	if ( ! empty( $GLOBALS['interim_login'] ) ) {
		return;
	}
	$phone = function_exists( 'nura_opt' ) && nura_opt( 'nura_phone' ) ? nura_opt( 'nura_phone' ) : '+254 714 994 898';
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	?>
	<aside class="nura-login-brand" aria-hidden="false">
		<div class="nura-lb-top"><?php esc_html_e( 'NURA Beauty &middot; Nairobi', 'nura-beauty' ); ?></div>
		<div class="nura-lb-main">
			<h2><?php echo wp_kses( __( 'Premium wigs,<br><em>beautifully managed.</em>', 'nura-beauty' ), array( 'br' => array(), 'em' => array() ) ); ?></h2>
			<p><?php esc_html_e( 'Sign in to manage orders, wigs, appointments and journal posts for NURA Beauty.', 'nura-beauty' ); ?></p>
			<ul class="nura-lb-list">
				<li><?php esc_html_e( 'Orders, M-Pesa payments and WhatsApp enquiries in one place', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Update wigs, colours, photos, prices and stock', 'nura-beauty' ); ?></li>
				<li><?php esc_html_e( 'Publish buying guides with SEO built in', 'nura-beauty' ); ?></li>
			</ul>
		</div>
		<div class="nura-lb-foot">
			<span><?php echo esc_html( $phone ); ?></span>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $host ); ?></a>
		</div>
	</aside>
	<?php
} );

/** Heading above the form, per screen. */
add_filter( 'login_message', function ( $message ) {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
	$titles = array(
		'login'        => array( __( 'Welcome back', 'nura-beauty' ), __( 'Sign in to your NURA Beauty account', 'nura-beauty' ) ),
		'lostpassword' => array( __( 'Reset your password', 'nura-beauty' ), __( 'Enter your email and we will send you a reset link', 'nura-beauty' ) ),
		'rp'           => array( __( 'Choose a new password', 'nura-beauty' ), __( 'Use a strong password you do not use elsewhere', 'nura-beauty' ) ),
		'resetpass'    => array( __( 'Choose a new password', 'nura-beauty' ), __( 'Use a strong password you do not use elsewhere', 'nura-beauty' ) ),
		'register'     => array( __( 'Create your account', 'nura-beauty' ), __( 'Join NURA Beauty to track orders and earn rewards', 'nura-beauty' ) ),
	);
	if ( ! isset( $titles[ $action ] ) ) {
		return $message;
	}
	$t = $titles[ $action ];
	return '<div class="nura-login-title"><strong>' . esc_html( $t[0] ) . '</strong><span>' . esc_html( $t[1] ) . '</span></div>' . $message;
} );
