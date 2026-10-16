<?php
/**
 * NURA Desk - "Join The House" subscribe pop-up + footer form (NURA Experience 1.62.0).
 *
 * - Footer "Join The House" form and a calm, one-time pop-up for new visitors.
 * - Step 1: e-mail (+ first name). Step 2 (optional): WhatsApp number + what
 *   she loves (bob, curly, human hair...), saved as tags.
 * - Every subscriber lands in NURA Desk > Marketing > Audience (source
 *   "newsletter", consent recorded), ready for e-mail and WhatsApp campaigns.
 * - Optional welcome code (single-use WooCommerce coupon locked to her e-mail).
 * - Shown once per visitor/browser: never again after subscribing, and not for
 *   N days after closing. Never on cart, checkout or account pages, never over
 *   another pop-up, and not for logged-in shop staff.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Sub {

	public static function hooks() {
		add_action( 'wp_ajax_nurax_subscribe', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_nurax_subscribe', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'popup' ), 40 );
	}

	public static function interests() {
		return array( 'human-hair' => 'Human hair', 'bob' => 'Bob wigs', 'curly' => 'Curly & kinky', 'straight' => 'Straight & body wave', 'colour' => 'Colour & blonde', 'bridal' => 'Bridal', 'budget' => 'Under KSh 7,000', 'care' => 'Wig care' );
	}

	public static function preview() {
		return isset( $_GET['nura_house'] ) && 'preview' === $_GET['nura_house']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private static function excluded() {
		if ( is_admin() || wp_doing_ajax() ) {
			return true;
		}
		if ( self::preview() ) {
			return false; // Preview link: always show, even to staff.
		}
		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) {
			return true;
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return true; // Staff never see it.
		}
		return (bool) apply_filters( 'nurax_sub_excluded', false );
	}

	public static function assets() {
		if ( is_admin() ) {
			return;
		}
		$s = NURAX_Desk::settings();
		wp_enqueue_style( 'nurax-sub', NURAX_URL . 'assets/css/nura-subscribe.css', array(), NURAX_VERSION );
		wp_enqueue_script( 'nurax-sub', NURAX_URL . 'assets/js/nura-subscribe.js', array(), NURAX_VERSION, true );
		wp_localize_script( 'nurax-sub', 'NURAX_SUB', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'popup'  => ( ( ! empty( $s['sub_popup'] ) || self::preview() ) && ! self::excluded() ) ? 1 : 0,
			'delay'  => max( 4, (int) $s['sub_delay'] ),
			'scroll' => min( 90, max( 10, (int) $s['sub_scroll'] ) ),
			'days'   => max( 1, (int) $s['sub_days'] ),
			'preview' => self::preview() ? 1 : 0,
		) );
	}

	/** Pop-up markup (hidden until the script decides to show it). */
	public static function popup() {
		$s = NURAX_Desk::settings();
		if ( ( empty( $s['sub_popup'] ) && ! self::preview() ) || self::excluded() ) {
			return;
		}
		$coupon = (float) $s['sub_coupon'];
		$img    = trim( (string) $s['sub_image'] );
		$perks  = array_filter( array_map( 'trim', explode( "\n", (string) $s['sub_perks'] ) ) );
		?>
		<div class="nhs" data-nhs hidden>
			<div class="nhs__veil" data-nhs-close></div>
			<div class="nhs__card" role="dialog" aria-modal="true" aria-labelledby="nhs-title" tabindex="-1">
				<button type="button" class="nhs__x" data-nhs-close aria-label="<?php esc_attr_e( 'Close', 'nura-experience' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
				<div class="nhs__art"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : ''; ?>>
					<div class="nhs__brand"><span>NURA</span><small>Wear your crown</small></div>
					<?php if ( $coupon > 0 ) : ?><div class="nhs__badge"><small><?php esc_html_e( 'Welcome gift', 'nura-experience' ); ?></small><strong><?php echo esc_html( NURAX_Desk::money( $coupon ) ); ?></strong><small><?php esc_html_e( 'off your first wig', 'nura-experience' ); ?></small></div><?php endif; ?>
				</div>
				<div class="nhs__body">
					<div class="nhs__step is-on" data-nhs-step="1">
						<p class="nhs__eyebrow"><?php esc_html_e( 'The NURA house list', 'nura-experience' ); ?></p>
						<h2 class="nhs__title" id="nhs-title"><?php echo esc_html( $s['sub_title'] ); ?></h2>
						<p class="nhs__text"><?php echo esc_html( $s['sub_text'] ); ?></p>
						<?php if ( $perks ) : ?><ul class="nhs__perks"><?php foreach ( array_slice( $perks, 0, 4 ) as $pk ) : ?><li><?php echo esc_html( $pk ); ?></li><?php endforeach; ?></ul><?php endif; ?>
						<form class="nhs__form" data-nhs-form novalidate>
							<input type="text" name="name" autocomplete="given-name" placeholder="<?php esc_attr_e( 'First name', 'nura-experience' ); ?>" aria-label="<?php esc_attr_e( 'First name', 'nura-experience' ); ?>">
							<input type="email" name="email" autocomplete="email" inputmode="email" placeholder="<?php esc_attr_e( 'Your e-mail address', 'nura-experience' ); ?>" aria-label="<?php esc_attr_e( 'E-mail address', 'nura-experience' ); ?>" required>
							<input type="text" name="website" class="nhs__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
							<button type="submit" class="nhs__btn"><?php echo esc_html( $coupon > 0 ? __( 'Unlock my gift', 'nura-experience' ) : __( 'Join The House', 'nura-experience' ) ); ?></button>
							<p class="nhs__err" data-nhs-err role="alert" hidden></p>
						</form>
						<p class="nhs__legal"><?php echo wp_kses_post( sprintf( __( 'By joining you agree to receive offers from NURA. Unsubscribe anytime. <a href="%s">Privacy</a>', 'nura-experience' ), esc_url( home_url( '/privacy-policy/' ) ) ) ); ?></p>
						<button type="button" class="nhs__later" data-nhs-close><?php esc_html_e( 'No thanks, maybe later', 'nura-experience' ); ?></button>
					</div>

					<div class="nhs__step" data-nhs-step="2">
						<p class="nhs__eyebrow"><?php esc_html_e( 'Welcome to the house', 'nura-experience' ); ?></p>
						<h2 class="nhs__title"><?php esc_html_e( 'One last touch', 'nura-experience' ); ?></h2>
						<p class="nhs__text"><?php esc_html_e( 'Get new drops and restock alerts on WhatsApp too, and tell us what you love so we only send what you want.', 'nura-experience' ); ?></p>
						<form class="nhs__form" data-nhs-more novalidate>
							<div class="nhs__phone"><span>+254</span><input type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="<?php esc_attr_e( 'WhatsApp number (optional)', 'nura-experience' ); ?>"></div>
							<div class="nhs__chips" role="group" aria-label="<?php esc_attr_e( 'What do you love?', 'nura-experience' ); ?>">
								<?php foreach ( self::interests() as $k => $l ) : ?><label><input type="checkbox" name="love[]" value="<?php echo esc_attr( $k ); ?>"><span><?php echo esc_html( $l ); ?></span></label><?php endforeach; ?>
							</div>
							<button type="submit" class="nhs__btn"><?php esc_html_e( 'Save my preferences', 'nura-experience' ); ?></button>
						</form>
						<button type="button" class="nhs__later" data-nhs-skip><?php esc_html_e( 'Skip', 'nura-experience' ); ?></button>
					</div>

					<div class="nhs__step nhs__done" data-nhs-step="3">
						<div class="nhs__tick" aria-hidden="true"></div>
						<h2 class="nhs__title"><?php esc_html_e( 'You are in.', 'nura-experience' ); ?></h2>
						<p class="nhs__text" data-nhs-donetext><?php esc_html_e( 'Check your inbox for a welcome note from us.', 'nura-experience' ); ?></p>
						<div class="nhs__code" data-nhs-code hidden><span data-nhs-codeval></span><button type="button" data-nhs-copy><?php esc_html_e( 'Copy', 'nura-experience' ); ?></button></div>
						<a class="nhs__btn" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Start shopping', 'nura-experience' ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<button type="button" class="nhs-tab" data-nhs-tab hidden><span class="nhs-tab__dot" aria-hidden="true"></span><?php echo esc_html( $coupon > 0 ? sprintf( __( 'Get %s off', 'nura-experience' ), NURAX_Desk::money( $coupon ) ) : __( 'Join The House', 'nura-experience' ) ); ?></button>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Saving                                                              */
	/* ------------------------------------------------------------------ */

	/** Save a subscriber. Returns array( ok, code, msg ). */
	public static function save( $a ) {
		global $wpdb;
		$email = isset( $a['email'] ) && is_email( $a['email'] ) ? strtolower( sanitize_email( $a['email'] ) ) : '';
		$phone = isset( $a['phone'] ) ? NURAX_Desk::phone( $a['phone'] ) : '';
		if ( '' === $email && '' === $phone ) {
			return array( 'ok' => false, 'msg' => __( 'Please enter a valid e-mail address.', 'nura-experience' ) );
		}
		$t      = NURAX_Desk_Mkt::t_c();
		$exists = $email ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s LIMIT 1", $email ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id     = NURAX_Desk_Mkt::upsert( array( 'name' => isset( $a['name'] ) ? $a['name'] : '', 'email' => $email, 'phone' => $phone, 'source' => 'newsletter', 'consent' => 1 ) );
		if ( ! $id ) {
			return array( 'ok' => false, 'msg' => __( 'Something went wrong. Please try again.', 'nura-experience' ) );
		}
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array( 'email_ok' => 1, 'consent' => 1, 'updated_at' => current_time( 'mysql' ) );
		if ( $phone ) {
			$data['wa_ok'] = 1;
			if ( $row && ! $row->phone ) {
				$data['phone'] = $phone;
			}
		}
		$tags = array_filter( array_map( 'trim', explode( ',', $row ? (string) $row->tags : '' ) ) );
		$tags[] = 'newsletter';
		foreach ( isset( $a['love'] ) ? (array) $a['love'] : array() as $l ) {
			$l = sanitize_key( $l );
			if ( isset( self::interests()[ $l ] ) ) {
				$tags[] = $l;
			}
		}
		$data['tags'] = mb_substr( implode( ',', array_unique( $tags ) ), 0, 255 );
		$wpdb->update( $t, $data, array( 'id' => $id ) );

		$code = '';
		if ( $email && empty( $a['more'] ) ) {
			$code = self::coupon( $email, $exists );
			if ( ! $exists ) {
				self::welcome( $row ? $row : (object) array( 'name' => '', 'token' => '' ), $email, $code );
			}
		}
		return array( 'ok' => true, 'code' => $code, 'returning' => (bool) $exists );
	}

	/** One single-use welcome coupon per e-mail (only for people who have never bought). */
	private static function coupon( $email, $existing ) {
		$s   = NURAX_Desk::settings();
		$amt = (float) $s['sub_coupon'];
		if ( $amt <= 0 || ! class_exists( 'WC_Coupon' ) || ( $existing && (int) $existing->orders > 0 ) ) {
			return '';
		}
		$key  = 'nurax_wc_' . md5( $email );
		$have = get_option( $key );
		if ( $have ) {
			return (string) $have;
		}
		$code = 'WELCOME-' . strtoupper( wp_generate_password( 5, false, false ) );
		try {
			$c = new WC_Coupon();
			$c->set_code( $code );
			$c->set_discount_type( 'fixed_cart' );
			$c->set_amount( $amt );
			$c->set_individual_use( true );
			$c->set_usage_limit( 1 );
			$c->set_usage_limit_per_user( 1 );
			$c->set_email_restrictions( array( $email ) );
			if ( (float) $s['sub_min'] > 0 ) {
				$c->set_minimum_amount( (float) $s['sub_min'] );
			}
			$c->set_date_expires( strtotime( '+30 days' ) );
			$c->set_description( 'Join The House welcome code' );
			$c->save();
		} catch ( \Throwable $e ) {
			return '';
		}
		update_option( $key, $code, false );
		return $code;
	}

	private static function welcome( $c, $email, $code ) {
		$s    = NURAX_Desk::settings();
		$body = "Hi {first},\n\nWelcome to the house. You will be the first to hear about new wigs, restocks and members-only offers - and we promise to keep it worth opening.\n\n";
		if ( $code ) {
			$body .= "Your welcome gift: *" . NURAX_Desk::money( $s['sub_coupon'] ) . " off your first wig* with the code *" . $code . "*" . ( (float) $s['sub_min'] > 0 ? ' on orders from ' . NURAX_Desk::money( $s['sub_min'] ) : '' ) . ". It is valid for 30 days.\n\n";
		}
		$body .= "Not sure where to start? Take our 60-second Wig Finder, or message us on WhatsApp and a stylist will help you choose.";
		$k = (object) array(
			'name'      => 'welcome',
			'subject'   => 'Welcome to the house',
			'preheader' => $code ? 'Your welcome gift is inside' : 'New wigs, offers and care tips - first',
			'heading'   => 'Welcome to the house',
			'body'      => $body,
			'btn_text'  => 'Shop the collection',
			'btn_url'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
			'image'     => trim( (string) $s['sub_image'] ),
			'products'  => '',
		);
		$from = is_email( $s['mkt_from_email'] ) ? $s['mkt_from_email'] : get_option( 'admin_email' );
		wp_mail( $email, 'Welcome to NURA' . ( $code ? ' - your gift is inside' : '' ), NURAX_Desk_Mkt::email_html( $k, $c ), array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . ( $s['mkt_from_name'] ? $s['mkt_from_name'] : 'NURA Beauty' ) . ' <' . $from . '>' ) );
	}

	public static function ajax() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form on cached pages; honeypot + rate limit.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'code' => '' ) );
		}
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'nurax_sub_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 8 ) {
			wp_send_json_error( array( 'message' => __( 'Too many attempts. Please try again in a few minutes.', 'nura-experience' ) ), 429 );
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );
		$res = self::save( array(
			'name'  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'love'  => isset( $_POST['love'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['love'] ) ) : array(),
			'more'  => ! empty( $_POST['more'] ),
		) );
		// phpcs:enable
		$res['ok'] ? wp_send_json_success( $res ) : wp_send_json_error( array( 'message' => $res['msg'] ), 400 );
	}

	/** Numbers for the Marketing page. */
	public static function stats() {
		global $wpdb;
		$t = NURAX_Desk_Mkt::t_c();
		return array(
			'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE sources LIKE '%newsletter%'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'week'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE sources LIKE '%%newsletter%%' AND created_at >= %s", gmdate( 'Y-m-d', strtotime( NURAX_Desk::today() . ' -6 days' ) ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'wa'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE sources LIKE '%newsletter%' AND phone <> ''" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
