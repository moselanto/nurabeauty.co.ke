<?php
/**
 * NURA Care Reminders.
 *
 * A post-purchase care email journey. When an order completes, NURA schedules a
 * short, branded sequence of reminder emails (install & first-week care, first
 * wash & revamp, then a revamp + reorder nudge) using WP-Cron single events.
 * Emails are wrapped in WooCommerce's own header/footer so they match the store
 * brand. The whole journey is filterable and can be switched off per site,
 * per order, or per customer.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Care_Reminders {

	const CRON_HOOK = 'nurax_send_care_email';
	const META_DONE = '_nurax_care_scheduled';

	public function __construct() {
		// Schedule the journey once the order is paid / completed.
		add_action( 'woocommerce_order_status_completed', array( $this, 'schedule' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'schedule' ), 20, 1 );

		// Stop the journey if the order is cancelled or refunded.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'unschedule' ), 20, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'unschedule' ), 20, 1 );

		// Cron handler.
		add_action( self::CRON_HOOK, array( $this, 'deliver' ), 10, 2 );
	}

	/**
	 * The reminder schedule: step key => [ offset in seconds, subject ].
	 * Filterable so intervals can be tuned without code edits.
	 */
	private function steps() {
		$day  = DAY_IN_SECONDS;
		$steps = array(
			'welcome' => array( 2 * $day, __( 'Your NURA unit: how to fit it & first-week care', 'nura-experience' ) ),
			'firstwash' => array( 42 * $day, __( "It's time for your first wash & revamp", 'nura-experience' ) ),
			'revamp' => array( 84 * $day, __( 'Keep your NURA radiant: revamp & reorder', 'nura-experience' ) ),
		);
		return apply_filters( 'nurax_care_schedule', $steps );
	}

	/** Schedule the sequence for an order (once). */
	public function schedule( $order_id ) {
		if ( ! apply_filters( 'nurax_care_reminders_enabled', true, $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::META_DONE ) ) {
			return;
		}
		if ( ! $order->get_billing_email() ) {
			return;
		}
		// Respect a per-customer opt-out.
		$user_id = $order->get_user_id();
		if ( $user_id && get_user_meta( $user_id, 'nurax_care_optout', true ) ) {
			return;
		}

		$now = time();
		foreach ( $this->steps() as $step => $data ) {
			$when = $now + (int) $data[0];
			if ( ! wp_next_scheduled( self::CRON_HOOK, array( $order_id, $step ) ) ) {
				wp_schedule_single_event( $when, self::CRON_HOOK, array( $order_id, $step ) );
			}
		}
		$order->update_meta_data( self::META_DONE, current_time( 'mysql' ) );
		$order->save();
	}

	/** Cancel any pending reminders for an order. */
	public function unschedule( $order_id ) {
		foreach ( array_keys( $this->steps() ) as $step ) {
			$ts = wp_next_scheduled( self::CRON_HOOK, array( $order_id, $step ) );
			while ( $ts ) {
				wp_unschedule_event( $ts, self::CRON_HOOK, array( $order_id, $step ) );
				$ts = wp_next_scheduled( self::CRON_HOOK, array( $order_id, $step ) );
			}
		}
	}

	/** Cron callback: build and send one reminder email. */
	public function deliver( $order_id, $step ) {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$status = $order->get_status();
		if ( in_array( $status, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			return;
		}
		$steps = $this->steps();
		if ( ! isset( $steps[ $step ] ) ) {
			return;
		}
		$to = $order->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$subject = $steps[ $step ][1];
		$body    = $this->body( $order, $step );

		$mailer  = WC()->mailer();
		$wrapped = $mailer->wrap_message( $subject, $body );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$mailer->send( $to, $subject, $wrapped, $headers, array() );
		$order->add_order_note( sprintf( /* translators: %s: reminder step key */ __( 'NURA care reminder sent (%s).', 'nura-experience' ), $step ) );
	}

	/** Branded HTML body for a given step. */
	private function body( $order, $step ) {
		$name    = $order->get_billing_first_name();
		$hello   = $name ? sprintf( __( 'Hi %s,', 'nura-experience' ), esc_html( $name ) ) : __( 'Hello,', 'nura-experience' );
		$account = wc_get_page_permalink( 'myaccount' );
		$shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

		$blocks = array();
		$blocks[] = '<p>' . $hello . '</p>';

		switch ( $step ) {
			case 'welcome':
				$blocks[] = '<p>' . esc_html__( 'Your NURA unit is on its way to becoming part of your everyday radiance. Here is how to start it off right:', 'nura-experience' ) . '</p>';
				$blocks[] = '<ul>'
					. '<li>' . esc_html__( 'Glueless and wear-and-go units fit straight from the box using the adjustable straps and combs.', 'nura-experience' ) . '</li>'
					. '<li>' . esc_html__( 'For lace units, book a NURA installation and our stylists will fit and style it for you.', 'nura-experience' ) . '</li>'
					. '<li>' . esc_html__( 'Store it on a wig stand in the satin bag provided to keep the hair and lace fresh.', 'nura-experience' ) . '</li>'
					. '</ul>';
				break;
			case 'firstwash':
				$blocks[] = '<p>' . esc_html__( 'It has been about six weeks. Now is the perfect time for your first wash and revamp so your unit stays soft, full and radiant:', 'nura-experience' ) . '</p>';
				$blocks[] = '<ul>'
					. '<li>' . esc_html__( 'Wash with a sulfate-free shampoo and a moisturising conditioner.', 'nura-experience' ) . '</li>'
					. '<li>' . esc_html__( 'Air-dry on a wig stand and always use a heat protectant before styling.', 'nura-experience' ) . '</li>'
					. '<li>' . esc_html__( 'Prefer we handle it? Book a NURA revamp and we will restore it for you.', 'nura-experience' ) . '</li>'
					. '</ul>';
				break;
			case 'revamp':
			default:
				$blocks[] = '<p>' . esc_html__( 'Your NURA unit has been with you for a few months now. Keep it radiant with a professional revamp, or refresh your rotation with a new look:', 'nura-experience' ) . '</p>';
				$blocks[] = '<ul>'
					. '<li>' . esc_html__( 'Book a revamp to deep-clean, restore and restyle your current unit.', 'nura-experience' ) . '</li>'
					. '<li>' . esc_html__( 'Ready for something new? Explore the latest NURA arrivals.', 'nura-experience' ) . '</li>'
					. '</ul>';
				break;
		}

		$blocks[] = '<p style="margin:24px 0;">'
			. '<a href="' . esc_url( $shop ) . '" style="background:#c79a54;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;display:inline-block;">' . esc_html__( 'Shop NURA', 'nura-experience' ) . '</a>'
			. '</p>';
		$blocks[] = '<p style="font-size:13px;color:#777;">'
			. sprintf(
				/* translators: %s: My Account URL */
				esc_html__( 'Your full care schedule, warranty and reminders live in The NURA Circle: %s', 'nura-experience' ),
				'<a href="' . esc_url( $account ) . '">' . esc_html__( 'view your account', 'nura-experience' ) . '</a>'
			)
			. '</p>';

		return implode( "\n", $blocks );
	}
}
