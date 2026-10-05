<?php
/**
 * Post-purchase follow-up automation for NURA.
 *
 * Built entirely on WooCommerce orders - no new data store. A scheduled scan
 * runs a gentle three-step cadence, timed from when each order is paid:
 *
 *   1. Thank-you + care tips  (default 1 day)  - transactional aftercare.
 *   2. Review request         (default 10 days) - marketing (consent gated).
 *   3. Reorder / replenish    (default 75 days) - marketing (consent gated).
 *
 * Each step can go by email and/or WhatsApp. A one-time "since" stamp means
 * enabling the feature never messages the historical back-catalogue. Marketing
 * steps are gated through NURAX_Consent, and the whole feature is OFF by
 * default, configured under WooCommerce > NURA Follow-ups.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NURAX_Followup {

	const CRON_HOOK = 'nurax_followup_scan';
	const OPTION    = 'nurax_followup';
	const SINCE     = 'nurax_fu_since';
	const LAST_RUN  = 'nurax_fu_last_run';
	const COUNTS    = 'nurax_fu_counts';

	/* Order meta this module owns. */
	const M_FU1 = '_nurax_fu_1_sent';
	const M_FU2 = '_nurax_fu_2_sent';
	const M_FU3 = '_nurax_fu_3_sent';

	private static $instance = null;

	public static function instance(): NURAX_Followup {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_filter( 'cron_schedules', array( $this, 'cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'ensure_scheduled' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 31 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/* ------------------------------------------------------------- scheduling */

	public function cron_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules['nurax_15min'] ) ) {
			$schedules['nurax_15min'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes (NURA)', 'nura-experience' ),
			);
		}
		return $schedules;
	}

	private function stamp_since(): void {
		if ( false === get_option( self::SINCE, false ) ) {
			update_option( self::SINCE, time(), false );
		}
	}

	public function ensure_scheduled(): void {
		$this->stamp_since();
		$enabled   = ! empty( $this->settings()['enabled'] );
		$scheduled = wp_next_scheduled( self::CRON_HOOK );
		if ( $enabled && ! $scheduled ) {
			wp_schedule_event( time() + 300, 'nurax_15min', self::CRON_HOOK );
		} elseif ( ! $enabled && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/* --------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'enabled'     => 0,
			'channel'     => 'email',
			'thanks_on'   => 1,
			'review_on'   => 1,
			'reorder_on'  => 1,
			'fu1_days'    => 1,
			'fu2_days'    => 10,
			'fu3_days'    => 75,
			'fu1_msg'     => __( 'Hello %1$s, thank you for your NURA order (%2$s). To keep your wig looking flawless: store it on a stand, detangle gently from the ends, wash with cool water and a sulphate-free shampoo, and air dry. Any questions? Just reply and our stylists will help.', 'nura-experience' ),
			'fu2_msg'     => __( 'Hello %1$s, we hope you are loving your NURA pieces from order %2$s. Would you share a quick review? It helps other clients choose with confidence: %3$s', 'nura-experience' ),
			'fu3_msg'     => __( 'Hello %1$s, it has been a little while since your last NURA order (%2$s). If you are ready to refresh your look or restock your care essentials, we are here to help: %3$s', 'nura-experience' ),
		);
	}

	public function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $this->defaults(), $saved );
	}

	public function register_settings(): void {
		register_setting( 'nurax_followup_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();

		$out['enabled']    = empty( $input['enabled'] ) ? 0 : 1;
		$out['thanks_on']  = empty( $input['thanks_on'] ) ? 0 : 1;
		$out['review_on']  = empty( $input['review_on'] ) ? 0 : 1;
		$out['reorder_on'] = empty( $input['reorder_on'] ) ? 0 : 1;

		$channel        = isset( $input['channel'] ) ? sanitize_key( (string) $input['channel'] ) : 'email';
		$out['channel'] = in_array( $channel, array( 'both', 'whatsapp', 'email' ), true ) ? $channel : 'email';

		$fu1 = isset( $input['fu1_days'] ) ? absint( $input['fu1_days'] ) : $d['fu1_days'];
		$fu2 = isset( $input['fu2_days'] ) ? absint( $input['fu2_days'] ) : $d['fu2_days'];
		$fu3 = isset( $input['fu3_days'] ) ? absint( $input['fu3_days'] ) : $d['fu3_days'];
		$out['fu1_days'] = max( 0, min( 30, $fu1 ) );
		$out['fu2_days'] = max( $out['fu1_days'] + 1, min( 120, $fu2 ) );
		$out['fu3_days'] = max( $out['fu2_days'] + 1, min( 365, $fu3 ) );

		foreach ( array( 'fu1_msg', 'fu2_msg', 'fu3_msg' ) as $k ) {
			$val       = isset( $input[ $k ] ) ? sanitize_textarea_field( (string) $input[ $k ] ) : '';
			$out[ $k ] = '' !== $val ? $val : $d[ $k ];
		}

		return $out;
	}

	/* -------------------------------------------------------------- cron work */

	public function run(): void {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$since  = (int) get_option( self::SINCE, 0 );
		$counts = get_option( self::COUNTS, array() );
		$counts = is_array( $counts ) ? $counts : array();
		$c1     = isset( $counts['fu1'] ) ? (int) $counts['fu1'] : 0;
		$c2     = isset( $counts['fu2'] ) ? (int) $counts['fu2'] : 0;
		$c3     = isset( $counts['fu3'] ) ? (int) $counts['fu3'] : 0;

		$orders = wc_get_orders(
			array(
				'limit'   => 40,
				'status'  => array( 'processing', 'completed' ),
				'orderby' => 'date',
				'order'   => 'ASC',
				'return'  => 'objects',
			)
		);
		if ( empty( $orders ) ) {
			return;
		}

		$now = time();
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
				continue;
			}
			$anchor = $this->anchor( $order );
			if ( $anchor <= 0 ) {
				continue;
			}
			if ( $anchor < $since ) {
				// Predates the feature: close out so it is never messaged.
				$this->mark( $order, self::M_FU1, 'skipped' );
				$this->mark( $order, self::M_FU2, 'skipped' );
				$this->mark( $order, self::M_FU3, 'skipped' );
				continue;
			}

			// Stage 1 - thank-you + care (transactional; not consent gated).
			if ( ! empty( $s['thanks_on'] ) && '' === (string) $order->get_meta( self::M_FU1 ) && $now >= $anchor + (int) $s['fu1_days'] * DAY_IN_SECONDS ) {
				$msg = $this->message( (string) $s['fu1_msg'], $order, $this->shop_url() );
				if ( '' !== $msg && $this->deliver( $order, $msg, (string) $s['channel'], __( 'Thank you from NURA - caring for your new pieces', 'nura-experience' ), false ) ) {
					$this->mark( $order, self::M_FU1, (string) $now );
					$c1++;
				} else {
					$this->mark( $order, self::M_FU1, 'skipped' );
				}
			}

			// Stage 2 - review request (marketing; consent gated).
			if ( ! empty( $s['review_on'] ) && '' === (string) $order->get_meta( self::M_FU2 ) && $now >= $anchor + (int) $s['fu2_days'] * DAY_IN_SECONDS ) {
				$msg = $this->message( (string) $s['fu2_msg'], $order, $this->review_url( $order ) );
				if ( '' !== $msg && $this->deliver( $order, $msg, (string) $s['channel'], __( 'How are you loving your NURA pieces?', 'nura-experience' ), true ) ) {
					$this->mark( $order, self::M_FU2, (string) $now );
					$c2++;
				} else {
					$this->mark( $order, self::M_FU2, 'skipped' );
				}
			}

			// Stage 3 - reorder / replenishment (marketing; consent gated).
			if ( ! empty( $s['reorder_on'] ) && '' === (string) $order->get_meta( self::M_FU3 ) && $now >= $anchor + (int) $s['fu3_days'] * DAY_IN_SECONDS ) {
				$msg = $this->message( (string) $s['fu3_msg'], $order, $this->shop_url() );
				if ( '' !== $msg && $this->deliver( $order, $msg, (string) $s['channel'], __( 'Ready to refresh your look? NURA is here', 'nura-experience' ), true ) ) {
					$this->mark( $order, self::M_FU3, (string) $now );
					$c3++;
				} else {
					$this->mark( $order, self::M_FU3, 'skipped' );
				}
			}
		}

		update_option( self::COUNTS, array( 'fu1' => $c1, 'fu2' => $c2, 'fu3' => $c3 ), false );
		update_option( self::LAST_RUN, time(), false );
	}

	private function anchor( $order ): int {
		$paid = $order->get_date_paid();
		if ( $paid && is_object( $paid ) && method_exists( $paid, 'getTimestamp' ) ) {
			return (int) $paid->getTimestamp();
		}
		$created = $order->get_date_created();
		if ( $created && is_object( $created ) && method_exists( $created, 'getTimestamp' ) ) {
			return (int) $created->getTimestamp();
		}
		return 0;
	}

	private function mark( $order, string $key, string $value ): void {
		$order->update_meta_data( $key, $value );
		$order->save();
	}

	private function shop_url(): string {
		return function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
	}

	private function review_url( $order ): string {
		foreach ( $order->get_items() as $item ) {
			if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
				$pid = (int) $item->get_product_id();
				if ( $pid > 0 ) {
					return get_permalink( $pid ) . '#reviews';
				}
			}
		}
		return $this->shop_url();
	}

	/* ---------------------------------------------------------- messaging out */

	private function message( string $template, $order, string $link ): string {
		$name = trim( (string) $order->get_billing_first_name() );
		$name = '' !== $name ? $name : __( 'there', 'nura-experience' );
		$num  = '#' . $order->get_order_number();
		return trim( sprintf( $template, $name, $num, $link ) );
	}

	/**
	 * Deliver on the configured channel(s). $marketing gates the send through
	 * consent. Returns true when the message was delivered or intentionally
	 * skipped (opted out), so the stage is not retried forever.
	 */
	private function deliver( $order, string $message, string $channel, string $email_subject, bool $marketing ): bool {
		$email = (string) $order->get_billing_email();
		$phone = preg_replace( '/[^0-9]/', '', (string) $order->get_billing_phone() );
		$uid   = (int) $order->get_customer_id();

		$use_wa = ( 'both' === $channel || 'whatsapp' === $channel );
		$use_em = ( 'both' === $channel || 'email' === $channel );

		$handled = false;

		if ( $use_em && is_email( $email ) ) {
			$ok = true;
			if ( $marketing && class_exists( 'NURAX_Consent' ) && NURAX_Consent::gate_active( 'email' ) ) {
				$ok = NURAX_Consent::has_consent( $email, $uid );
			}
			if ( ! $ok ) {
				$handled = true; // Opted out.
			} else {
				$html    = $this->email_html( $order, $message );
				$headers = array(
					'Content-Type: text/html; charset=UTF-8',
					'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
				);
				if ( wp_mail( $email, $email_subject, $html, $headers ) ) {
					$handled = true;
				}
			}
		}

		if ( $use_wa && '' !== $phone && class_exists( 'NURAX_WhatsApp' ) && NURAX_WhatsApp::is_configured() ) {
			$ok = true;
			if ( $marketing && class_exists( 'NURAX_Consent' ) && NURAX_Consent::gate_active( 'whatsapp' ) ) {
				$ok = NURAX_Consent::has_consent( $email, $uid );
			}
			if ( ! $ok ) {
				$handled = true;
			} elseif ( NURAX_WhatsApp::send_text( $phone, $message ) ) {
				$handled = true;
			}
		}

		return $handled;
	}

	private function email_html( $order, string $message ): string {
		$brand  = esc_html( get_bloginfo( 'name' ) );
		$accent = '#b76e79';
		$ink    = '#2b2b2b';
		$muted  = '#8a8a8a';
		$email  = (string) $order->get_billing_email();
		$unsub  = class_exists( 'NURAX_Consent' ) ? NURAX_Consent::unsub_url( $email ) : esc_url_raw( home_url( '/' ) );
		$body   = nl2br( esc_html( $message ) );

		return '<div style="background:#f7f4f2;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #ece5e2;">'
			. '<tr><td style="background:' . $accent . ';padding:18px 24px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.02em;">' . $brand . '</td></tr>'
			. '<tr><td style="padding:24px;color:' . $ink . ';font-size:15px;line-height:1.6;">' . $body . '</td></tr>'
			. '<tr><td style="padding:16px 24px;background:#f5efec;color:' . $muted . ';font-size:12px;line-height:1.5;">'
			. '<p style="margin:0;">You are receiving this because you ordered from ' . $brand . '. To stop marketing messages, you can <a href="' . $unsub . '" style="color:' . $muted . ';">unsubscribe</a>.</p>'
			. '</td></tr>'
			. '</table></td></tr></table></div>';
	}

	/* ------------------------------------------------------------- admin page */

	public function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'NURA Follow-ups', 'nura-experience' ),
			__( 'NURA Follow-ups', 'nura-experience' ),
			'manage_woocommerce',
			'nurax-followups',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s        = $this->settings();
		$last_run = (int) get_option( self::LAST_RUN, 0 );
		$next     = wp_next_scheduled( self::CRON_HOOK );
		$counts   = get_option( self::COUNTS, array() );
		$counts   = is_array( $counts ) ? $counts : array();
		$c1       = isset( $counts['fu1'] ) ? (int) $counts['fu1'] : 0;
		$c2       = isset( $counts['fu2'] ) ? (int) $counts['fu2'] : 0;
		$c3       = isset( $counts['fu3'] ) ? (int) $counts['fu3'] : 0;
		$fmt      = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA post-purchase follow-ups', 'nura-experience' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Automatic messages timed from when each order is paid: a thank-you with wig care tips, a review request, and a gentle reorder reminder. The thank-you is aftercare; the review and reorder messages are marketing and are only sent to shoppers who opted in (see NURA Consent).', 'nura-experience' ); ?>
			</p>

			<div class="notice notice-info inline" style="padding:10px 12px;">
				<strong><?php esc_html_e( 'Status', 'nura-experience' ); ?>:</strong>
				<?php
				printf(
					/* translators: 1: last run, 2: next run */
					esc_html__( 'Last scan: %1$s. Next scan: %2$s.', 'nura-experience' ),
					$last_run ? esc_html( wp_date( $fmt, $last_run ) ) : esc_html__( 'not yet run', 'nura-experience' ),
					$next ? esc_html( wp_date( $fmt, (int) $next ) ) : esc_html__( 'not scheduled', 'nura-experience' )
				);
				?>
				&nbsp;|&nbsp;
				<?php
				printf(
					/* translators: 1: thank-you count, 2: review count, 3: reorder count */
					esc_html__( 'Sent so far - thank-you: %1$d, review: %2$d, reorder: %3$d.', 'nura-experience' ),
					$c1,
					$c2,
					$c3
				);
				?>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'nurax_followup_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Follow-up automation', 'nura-experience' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable the scheduled follow-up scan', 'nura-experience' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Channel', 'nura-experience' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[channel]">
								<option value="email" <?php selected( $s['channel'], 'email' ); ?>><?php esc_html_e( 'Email only', 'nura-experience' ); ?></option>
								<option value="whatsapp" <?php selected( $s['channel'], 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp only', 'nura-experience' ); ?></option>
								<option value="both" <?php selected( $s['channel'], 'both' ); ?>><?php esc_html_e( 'Email and WhatsApp', 'nura-experience' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'WhatsApp uses the billing phone on the order and your Meta Cloud API settings, and must comply with WhatsApp\'s messaging window / template rules.', 'nura-experience' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thank-you + care', 'nura-experience' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[thanks_on]" value="1" <?php checked( ! empty( $s['thanks_on'] ) ); ?> /> <?php esc_html_e( 'Send', 'nura-experience' ); ?></label>
							&nbsp; <input type="number" min="0" max="30" name="<?php echo esc_attr( self::OPTION ); ?>[fu1_days]" value="<?php echo esc_attr( (string) $s['fu1_days'] ); ?>" class="small-text" /> <?php esc_html_e( 'days after payment', 'nura-experience' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Review request', 'nura-experience' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[review_on]" value="1" <?php checked( ! empty( $s['review_on'] ) ); ?> /> <?php esc_html_e( 'Send', 'nura-experience' ); ?></label>
							&nbsp; <input type="number" min="2" max="120" name="<?php echo esc_attr( self::OPTION ); ?>[fu2_days]" value="<?php echo esc_attr( (string) $s['fu2_days'] ); ?>" class="small-text" /> <?php esc_html_e( 'days after payment (marketing - consent gated)', 'nura-experience' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reorder reminder', 'nura-experience' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[reorder_on]" value="1" <?php checked( ! empty( $s['reorder_on'] ) ); ?> /> <?php esc_html_e( 'Send', 'nura-experience' ); ?></label>
							&nbsp; <input type="number" min="3" max="365" name="<?php echo esc_attr( self::OPTION ); ?>[fu3_days]" value="<?php echo esc_attr( (string) $s['fu3_days'] ); ?>" class="small-text" /> <?php esc_html_e( 'days after payment (marketing - consent gated)', 'nura-experience' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thank-you message', 'nura-experience' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[fu1_msg]" rows="3" class="large-text"><?php echo esc_textarea( $s['fu1_msg'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Review request message', 'nura-experience' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[fu2_msg]" rows="3" class="large-text"><?php echo esc_textarea( $s['fu2_msg'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reorder message', 'nura-experience' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[fu3_msg]" rows="3" class="large-text"><?php echo esc_textarea( $s['fu3_msg'] ); ?></textarea></td>
					</tr>
				</table>
				<p class="description"><?php esc_html_e( 'Placeholders: %1$s = customer first name, %2$s = order number, %3$s = link (review link or shop link).', 'nura-experience' ); ?></p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
