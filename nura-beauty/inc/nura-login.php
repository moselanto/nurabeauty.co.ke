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
	// 1.72.0: self-hosted fonts on the login page too (no Google Fonts request).
	if ( file_exists( NURA_DIR . 'assets/fonts/montserrat.woff2' ) && apply_filters( 'nura_local_fonts', true ) ) {
		$fu = NURA_URI . 'assets/fonts/';
		$ff = '';
		foreach ( array( array( 'Playfair Display', 'playfair', '400 900' ), array( 'Jost', 'jost', '100 900' ), array( 'Montserrat', 'montserrat', '100 900' ) ) as $f ) {
			$ff .= '@font-face{font-family:"' . $f[0] . '";font-style:normal;font-weight:' . $f[2] . ';font-display:swap;src:url(' . esc_url( $fu . $f[1] . '.woff2' ) . ') format("woff2")}';
		}
		wp_register_style( 'nura-login-fonts', false, array(), NURA_VERSION );
		wp_enqueue_style( 'nura-login-fonts' );
		wp_add_inline_style( 'nura-login-fonts', $ff );
	} else {
		wp_enqueue_style( 'nura-login-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Jost:wght@400;500&family=Montserrat:wght@400;500&display=swap', array(), null );
	}

	$logo_id  = get_theme_mod( 'custom_logo' );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	$bg_url   = nura_login_bg_url();

	$css = '
	:root{--ink:#0E0E0E;--gold:#C9A24B;--gold-soft:#E4C983;--ivory:#F3E9DC;--line:#E7E1D8;--text:#1b1b1b;--muted:#6f6a64}
	html,body.login{height:100%}
	body.login{margin:0;display:flex;min-height:100vh;background:#FBF8F4;font-family:Montserrat,"Helvetica Neue",Arial,sans-serif;color:var(--text)}
	.nura-login-brand{position:relative;flex:0 0 52%;max-width:760px;display:flex;flex-direction:column;justify-content:flex-end;gap:40px;padding:56px 64px;color:var(--ivory);overflow:hidden;
		background:var(--ink) url(' . esc_url( $bg_url ) . ') center 22%/cover no-repeat}
	.nura-login-brand:before{content:"";position:absolute;inset:0;z-index:0;background:linear-gradient(180deg,rgba(14,14,14,.55) 0%,rgba(14,14,14,.25) 34%,rgba(14,14,14,.78) 72%,rgba(14,14,14,.94) 100%),linear-gradient(90deg,rgba(14,14,14,.55) 0%,rgba(14,14,14,0) 65%)}
	.nura-login-brand:after{content:"";position:absolute;inset:24px;z-index:0;border:1px solid rgba(201,162,75,.38);pointer-events:none}
	.nura-login-brand *{position:relative;z-index:1}
	.nura-lb-head{display:flex;align-items:center;justify-content:space-between;gap:16px}
	.nura-lb-logo img{display:block;height:44px;width:auto;max-width:200px}
	.nura-lb-badge{font-family:Jost,Arial,sans-serif;font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold-soft);border:1px solid rgba(201,162,75,.5);border-radius:999px;padding:7px 14px;background:rgba(14,14,14,.35);backdrop-filter:blur(6px)}
	.nura-lb-list-wrap{display:inline-block;padding:20px 22px 8px;border:1px solid rgba(243,233,220,.16);border-radius:16px;background:rgba(14,14,14,.38);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
	.nura-lb-gold{display:block;width:56px;height:1px;background:var(--gold);margin:0 0 18px}
	.nura-lb-top{font-family:Jost,Arial,sans-serif;text-transform:uppercase;letter-spacing:.32em;font-size:12px;color:var(--gold)}
	.nura-lb-main h2{font-family:"Playfair Display",Georgia,serif;font-weight:500;font-size:clamp(36px,3.6vw,52px);line-height:1.08;margin:14px 0 16px;color:#fff;letter-spacing:.005em;text-shadow:0 2px 24px rgba(0,0,0,.35)}
	.nura-lb-main h2 em{font-style:italic;color:var(--gold-soft)}
	.nura-lb-main p{font-size:15px;line-height:1.7;color:rgba(243,233,220,.86);max-width:460px;margin:0 0 26px}
	.nura-lb-list{list-style:none;margin:0;padding:0}
	.nura-lb-list li{display:flex;gap:12px;align-items:flex-start;font-size:14px;line-height:1.55;margin:0 0 14px;color:rgba(243,233,220,.9)}
	.nura-lb-list li:before{content:"";flex:0 0 18px;height:18px;margin-top:1px;border-radius:50%;border:1px solid var(--gold);background:radial-gradient(circle,var(--gold) 0 3px,transparent 4px)}
	.nura-lb-foot{display:flex;flex-wrap:wrap;gap:8px 20px;font-family:Jost,Arial,sans-serif;font-size:12px;letter-spacing:.08em;color:rgba(243,233,220,.6)}
	.nura-lb-foot a{color:var(--gold-soft);text-decoration:none}
	body.login #login{flex:1;width:auto;max-width:430px;margin:auto;padding:48px 28px;animation:nuraIn .6s ease both}
	@keyframes nuraIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
	.nura-login-secure{display:flex;justify-content:center;align-items:center;gap:8px;margin:20px 0 0;font-size:12px;color:var(--muted);letter-spacing:.02em}
	.nura-login-secure:before{content:"";width:12px;height:12px;border:1.5px solid var(--gold);border-radius:3px 3px 4px 4px;box-shadow:inset 0 -5px 0 rgba(201,162,75,.25)}
	.nura-login-help{text-align:center;margin:10px 0 0;font-size:12px;color:var(--muted)}
	.nura-login-help a{color:var(--ink);text-decoration:none;border-bottom:1px solid rgba(201,162,75,.6)}
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
		.nura-login-brand{flex:none;max-width:none;min-height:300px;padding:28px 24px 26px;background-position:center 18%}
		.nura-login-brand:after{inset:10px}
		.nura-lb-logo img{height:34px}
		.nura-lb-badge{display:none}
		.nura-lb-main h2{font-size:30px;margin:8px 0 8px}
		.nura-lb-main p{font-size:14px;margin:0}
		.nura-lb-list-wrap,.nura-lb-foot,.nura-lb-gold{display:none}
		body.login #login{padding:28px 18px 40px;margin:0 auto;width:100%;box-sizing:border-box}
		.login h1{display:none}
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
	<aside class="nura-login-brand">
		<div class="nura-lb-main">
			<span class="nura-lb-gold"></span>
			<div class="nura-lb-top"><?php esc_html_e( 'The House of Radiant Confidence', 'nura-beauty' ); ?></div>
			<h2><?php echo wp_kses( __( 'Premium wigs,<br><em>beautifully managed.</em>', 'nura-beauty' ), array( 'br' => array(), 'em' => array() ) ); ?></h2>
			<p><?php esc_html_e( 'Sign in to manage orders, wigs, appointments and journal posts for NURA Beauty.', 'nura-beauty' ); ?></p>
			<div class="nura-lb-list-wrap">
				<ul class="nura-lb-list">
					<li><?php esc_html_e( 'Orders, M-Pesa payments and WhatsApp enquiries in one place', 'nura-beauty' ); ?></li>
					<li><?php esc_html_e( 'Update wigs, colours, photos, prices and stock', 'nura-beauty' ); ?></li>
					<li><?php esc_html_e( 'Publish buying guides with SEO built in', 'nura-beauty' ); ?></li>
				</ul>
			</div>
		</div>
		<div class="nura-lb-foot">
			<span><?php echo esc_html( $phone ); ?></span>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $host ); ?></a>
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> NURA Beauty</span>
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

/**
 * Background photo for the brand panel. Change it under
 * Appearance > Customize > NURA Brand > Login background photo.
 */
function nura_login_bg_url() {
	$url = get_theme_mod( 'nura_login_bg', '' );
	if ( ! $url ) {
		$url = content_url( '/uploads/2026/10/243-03.jpg' );
	}
	return $url;
}

/** Logo shown on the dark photo panel (the gold logo reads well on black). */
function nura_login_light_logo_url() {
	$id = get_theme_mod( 'custom_logo' );
	return $id ? wp_get_attachment_image_url( $id, 'full' ) : '';
}

add_action( 'customize_register', function ( $wp_customize ) {
	$section = $wp_customize->get_section( 'nura_brand' ) ? 'nura_brand' : 'title_tagline';
	$wp_customize->add_setting( 'nura_login_bg', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'nura_login_bg', array(
		'label'       => __( 'Login background photo', 'nura-beauty' ),
		'description' => __( 'Shown behind the text on the left of the login page. A tall portrait photo works best.', 'nura-beauty' ),
		'section'     => $section,
	) ) );
} );

/** Reassurance + help line under the form. */
add_action( 'login_footer', function () {
	if ( ! empty( $GLOBALS['interim_login'] ) ) {
		return;
	}
	$wa = 'https://wa.me/254714994898';
	echo '<script>(function(){var l=document.getElementById("login");if(!l)return;var s=document.createElement("p");s.className="nura-login-secure";s.textContent="' . esc_js( __( 'Secure, encrypted sign-in', 'nura-beauty' ) ) . '";var h=document.createElement("p");h.className="nura-login-help";h.innerHTML=' . wp_json_encode( esc_html__( 'Need help signing in?', 'nura-beauty' ) . ' <a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Chat with us on WhatsApp', 'nura-beauty' ) . '</a>' ) . ';var f=l.querySelector("form");if(f){f.insertAdjacentElement("afterend",h);f.insertAdjacentElement("afterend",s);}})();</script>';
} );
