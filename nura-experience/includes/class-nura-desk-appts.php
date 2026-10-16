<?php
/**
 * NURA Desk - Studio appointments (NURA Experience 1.57.0).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Appts {

	public static function hooks() {
		add_action( 'admin_post_nurax_appt_save', array( __CLASS__, 'h_save' ) );
		add_action( 'admin_post_nurax_appt_status', array( __CLASS__, 'h_status' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Labels                                                              */
	/* ------------------------------------------------------------------ */

	public static function services() {
		return (array) apply_filters( 'nurax_desk_services', array(
			'consultation' => 'Free consultation',
			'fitting'      => 'Wig fitting',
			'installation' => 'Installation & styling',
			'revamp'       => 'Wig revamp / wash',
			'bridal'       => 'Bridal & occasion',
			'collection'   => 'Wig collection',
			'other'        => 'Other',
		) );
	}

	public static function service_label( $k ) {
		$s = self::services();
		return isset( $s[ $k ] ) ? $s[ $k ] : ucfirst( (string) $k );
	}

	public static function types() {
		return array(
			'studio'   => 'Studio visit',
			'video'    => 'Video call',
			'whatsapp' => 'WhatsApp chat',
		);
	}

	public static function type_label( $k ) {
		$t = self::types();
		return isset( $t[ $k ] ) ? $t[ $k ] : (string) $k;
	}

	public static function statuses() {
		return array(
			'request'   => 'Awaiting confirmation',
			'confirmed' => 'Confirmed',
			'completed' => 'Completed',
			'no_show'   => 'No-show',
			'cancelled' => 'Cancelled',
		);
	}

	public static function status_label( $k ) {
		$s = self::statuses();
		return isset( $s[ $k ] ) ? $s[ $k ] : (string) $k;
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_appts() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function between( $from, $to, $statuses = array() ) {
		global $wpdb;
		$t    = NURAX_Desk::t_appts();
		$args = array( $from, $to );
		$sql  = "SELECT * FROM $t WHERE appt_date >= %s AND appt_date <= %s";
		if ( $statuses ) {
			$sql .= ' AND status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$args = array_merge( $args, $statuses );
		}
		$sql .= ' ORDER BY appt_date ASC, appt_time ASC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function for_customer( $user_id, $email, $phone ) {
		global $wpdb;
		$t = NURAX_Desk::t_appts();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE appt_date >= %s AND status IN ('request','confirmed') AND ( user_id = %d OR ( email <> '' AND email = %s ) OR ( phone <> '' AND phone = %s ) ) ORDER BY appt_date ASC, appt_time ASC LIMIT 10", NURAX_Desk::today(), (int) $user_id, (string) $email, (string) $phone ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function kpis() {
		global $wpdb;
		$t     = NURAX_Desk::t_appts();
		$today = NURAX_Desk::today();
		$in7   = gmdate( 'Y-m-d', strtotime( $today . ' +6 days' ) );
		$k     = array( 'today' => 0, 'today_confirmed' => 0, 'week' => 0, 'requests' => 0 );
		$k['today']           = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE appt_date = %s AND status IN ('request','confirmed','completed')", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$k['today_confirmed'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE appt_date = %s AND status IN ('confirmed','completed')", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$k['week']            = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE appt_date >= %s AND appt_date <= %s AND status IN ('request','confirmed')", $today, $in7 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$k['requests']        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE appt_date >= %s AND status = 'request'", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $k;
	}

	/* ------------------------------------------------------------------ */
	/* Slots + capacity                                                    */
	/* ------------------------------------------------------------------ */

	/** All bookable times for a date ('' list when closed). */
	public static function slots( $date ) {
		$s    = NURAX_Desk::settings();
		$days = array_map( 'absint', explode( ',', (string) $s['appt_days'] ) );
		$dow  = (int) gmdate( 'N', strtotime( $date ) );
		if ( ! in_array( $dow, $days, true ) ) {
			return array();
		}
		$out  = array();
		$step = max( 10, (int) $s['appt_step'] ) * 60;
		$t    = strtotime( '2000-01-01 ' . $s['appt_open'] );
		$end  = strtotime( '2000-01-01 ' . $s['appt_close'] );
		$cut  = self::cutoff();
		while ( $t && $end && $t <= $end && count( $out ) < 100 ) {
			if ( gmdate( 'H:i', $t ) >= $cut ) {
				break; // Morning-only bookings.
			}
			$out[] = gmdate( 'H:i', $t );
			$t    += $step;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Booking rules: morning only, deposit, arrival policy, terms        */
	/* ------------------------------------------------------------------ */

	public static function cutoff() {
		$s = NURAX_Desk::settings();
		return isset( $s['appt_cutoff'] ) && preg_match( '/^\d{2}:\d{2}$/', (string) $s['appt_cutoff'] ) ? $s['appt_cutoff'] : '12:00';
	}

	public static function grace() {
		$s = NURAX_Desk::settings();
		return max( 0, (int) ( isset( $s['appt_grace'] ) ? $s['appt_grace'] : 15 ) );
	}

	public static function prices() {
		$s   = NURAX_Desk::settings();
		$out = array();
		foreach ( explode( ',', (string) ( isset( $s['appt_prices'] ) ? $s['appt_prices'] : '' ) ) as $pair ) {
			$kv = array_map( 'trim', explode( '=', $pair, 2 ) );
			if ( 2 === count( $kv ) && '' !== $kv[0] ) {
				$out[ sanitize_key( $kv[0] ) ] = max( 0, (float) preg_replace( '/[^0-9.]/', '', $kv[1] ) );
			}
		}
		return $out;
	}

	/** Deposit to secure a slot (0 when deposits are off: no M-Pesa details). */
	public static function deposit_for( $service, $type = 'studio' ) {
		$s = NURAX_Desk::settings();
		if ( 'studio' !== $type || '' === trim( (string) $s['mpesa_details'] ) ) {
			return 0;
		}
		$pct = min( 100, max( 0, (int) ( isset( $s['appt_dep_pct'] ) ? $s['appt_dep_pct'] : 50 ) ) );
		$min = max( 0, (int) ( isset( $s['appt_dep_min'] ) ? $s['appt_dep_min'] : 500 ) );
		$p   = self::prices();
		$p   = isset( $p[ $service ] ) ? $p[ $service ] : 0;
		return (int) max( $min, ceil( $p * $pct / 100 ) );
	}

	public static function terms_list() {
		$s = NURAX_Desk::settings();
		$t = trim( (string) ( isset( $s['appt_terms'] ) ? $s['appt_terms'] : '' ) );
		if ( '' !== $t ) {
			return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', $t ) ) ) );
		}
		$g   = self::grace();
		$pct = (int) ( isset( $s['appt_dep_pct'] ) ? $s['appt_dep_pct'] : 50 );
		$min = (int) ( isset( $s['appt_dep_min'] ) ? $s['appt_dep_min'] : 500 );
		$cut = NURAX_Desk::time_label( self::cutoff() );
		return array(
			'Studio appointments are available in the morning only, with the last booking before ' . $cut . '. Afternoons are for walk-in clients.',
			'A deposit of ' . $pct . '% of the service price' . ( $min > 0 ? ' (minimum KSh ' . number_format( $min ) . ')' : '' ) . ' secures a studio slot. Video and WhatsApp consultations need no deposit. Your booking is confirmed only once we have verified your M-Pesa payment. An unverified booking is released after ' . self::hold_hours() . ' hours.',
			'Your deposit is deducted from your final bill. For a free consultation, it is credited to any purchase or service you take on the day.',
			'Please arrive on time. If you arrive early, we will attend to you at your booked time. A client already being served will not be interrupted.',
			'We hold your slot for ' . $g . ' minutes after your booked time. After that the slot is released and you will be served as a walk-in when the next stylist is free. This may not be on the same day.',
			'Walk-in clients are served only in free time between booked appointments. A booked client who arrives on time is always served first.',
			'To reschedule, let us know at least 24 hours before your appointment. Your deposit moves to the new date once.',
			'If you cancel with less than 24 hours notice, or do not come, the deposit is not refunded.',
			'By booking, you agree to these terms.',
		);
	}

	/** Where a booked client stands right now: [key, label]. */
	public static function arrival_state( $a, $now_hm = '' ) {
		$now_hm = $now_hm ? $now_hm : current_time( 'H:i' );
		$slot   = strtotime( '2000-01-01 ' . $a->appt_time );
		$g      = self::grace() * 60;
		if ( 'completed' === $a->status ) {
			return array( 'done', 'Served' );
		}
		if ( ! empty( $a->arrived_at ) ) {
			$arr = strtotime( '2000-01-01 ' . substr( $a->arrived_at, 11, 5 ) );
			if ( $arr < $slot ) {
				return array( 'early', 'Arrived early - serve at ' . NURAX_Desk::time_label( $a->appt_time ) );
			}
			if ( $arr <= $slot + $g ) {
				return array( 'ontime', 'Arrived on time - serve now' );
			}
			return array( 'late', 'Arrived late - serve as a walk-in' );
		}
		$now = strtotime( '2000-01-01 ' . $now_hm );
		if ( $now < $slot ) {
			return array( 'due', 'Expected in ' . (int) round( ( $slot - $now ) / 60 ) . ' min' );
		}
		if ( $now <= $slot + $g ) {
			return array( 'grace', 'Late ' . (int) round( ( $now - $slot ) / 60 ) . ' min - slot held until ' . NURAX_Desk::time_label( gmdate( 'H:i', $slot + $g ) ) );
		}
		return array( 'released', 'Slot released - treat as a walk-in' );
	}

	public static function deposit_tag( $a ) {
		if ( self::hold_expired( $a ) ) {
			return '<small class="nb-dep nb-dep--exp">Deposit not verified in ' . (int) self::hold_hours() . 'h - slot released</small>';
		}
		if ( empty( $a->deposit ) || (float) $a->deposit <= 0 ) {
			return '<small class="nb-dep nb-dep--none">No deposit</small>';
		}
		if ( 'verified' === $a->deposit_status ) {
			return '<small class="nb-dep nb-dep--ok">Deposit ' . esc_html( NURAX_Desk::money( $a->deposit ) ) . ' received</small>';
		}
		return '<small class="nb-dep nb-dep--chk">Deposit ' . esc_html( NURAX_Desk::money( $a->deposit ) ) . ( $a->deposit_ref ? ' - check M-Pesa ' . esc_html( $a->deposit_ref ) : ' - not paid' ) . '</small>';
	}

	/** Today's studio board: who is due, who is late, can we take a walk-in now. */
	public static function studio_board() {
		$today = NURAX_Desk::today();
		if ( ! self::slots( $today ) ) {
			return;
		}
		$now  = current_time( 'H:i' );
		$cut  = self::cutoff();
		$dur  = (int) NURAX_Desk::settings()['appt_duration'];
		$list = array_values( array_filter( self::between( $today, $today, array( 'request', 'confirmed', 'completed' ) ), function ( $a ) {
			return 'studio' === $a->type;
		} ) );
		$next = null;
		foreach ( $list as $a ) {
			$st = self::arrival_state( $a, $now );
			if ( in_array( $st[0], array( 'due', 'grace', 'early', 'ontime' ), true ) && ( ! $next || $a->appt_time < $next->appt_time ) ) {
				$next = $a;
			}
		}
		$nowt = strtotime( '2000-01-01 ' . $now );
		if ( $now >= $cut && ! $next ) {
			$verdict = array( 'yes', 'Walk-ins welcome', 'Bookings are finished for today. Serve walk-ins freely.' );
		} elseif ( ! $next ) {
			$verdict = array( 'yes', 'Walk-ins welcome now', 'No more booked clients are expected today.' );
		} else {
			$free = (int) round( ( strtotime( '2000-01-01 ' . $next->appt_time ) - $nowt ) / 60 );
			$st   = self::arrival_state( $next, $now );
			if ( in_array( $st[0], array( 'grace', 'early', 'ontime' ), true ) || $free <= 0 ) {
				$verdict = array( 'no', 'Booked client first', $next->customer_name . ' (' . NURAX_Desk::time_label( $next->appt_time ) . ') comes first. Offer the walk-in the next free time or the afternoon.' );
			} elseif ( $free >= $dur ) {
				$verdict = array( 'yes', 'You can take a walk-in now', $free . ' min free before ' . $next->customer_name . ' at ' . NURAX_Desk::time_label( $next->appt_time ) . '. Tell the walk-in the service must finish by then.' );
			} else {
				$verdict = array( 'wait', 'Only ' . $free . ' min free', $next->customer_name . ' is booked at ' . NURAX_Desk::time_label( $next->appt_time ) . '. Take a walk-in only for a quick service, or offer a time after the booking.' );
			}
		}
		echo '<section class="nb-board"><div class="nb-board__v nb-board__v--' . esc_attr( $verdict[0] ) . '"><span class="nb-board__eyebrow">Studio now - ' . esc_html( NURAX_Desk::time_label( $now ) ) . '</span><strong>' . esc_html( $verdict[1] ) . '</strong><p>' . esc_html( $verdict[2] ) . '</p><small>Rule: early arrivals wait for their time. A client more than ' . (int) self::grace() . ' min late loses the slot and joins the walk-in queue.</small></div>';
		echo '<div class="nb-board__list">';
		if ( ! $list ) {
			echo '<p class="nb-board__empty">No studio bookings today. Walk-ins all day.</p>';
		}
		foreach ( $list as $a ) {
			$st = self::arrival_state( $a, $now );
			echo '<div class="nb-slot nb-slot--' . esc_attr( $st[0] ) . '"><b>' . esc_html( NURAX_Desk::time_label( $a->appt_time ) ) . '</b><div><strong>' . esc_html( $a->customer_name ) . '</strong><small>' . esc_html( self::service_label( $a->service ) ) . '</small>' . self::deposit_tag( $a ) . '</div><span class="nb-slot__st">' . esc_html( $st[1] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( empty( $a->arrived_at ) && 'completed' !== $a->status ) {
				self::status_btn( $a->id, 'arrived', 'Checked in', 'nd-ico-btn nd-ico-btn--ok' );
			} elseif ( 'confirmed' === $a->status ) {
				self::status_btn( $a->id, 'completed', 'Done', 'nd-ico-btn nd-ico-btn--ok' );
			}
			echo '</div>';
		}
		echo '</div></section>';
	}

	public static function booked( $date, $time, $exclude = 0 ) {
		global $wpdb;
		$t = NURAX_Desk::t_appts();
		$cut = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::hold_hours() * HOUR_IN_SECONDS );
		// A website request whose deposit has not been verified only holds the slot for a few hours.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE appt_date = %s AND appt_time = %s AND id <> %d AND ( status = 'confirmed' OR ( status = 'request' AND NOT ( deposit > 0 AND deposit_status <> 'verified' AND created_at < %s ) ) )", $date, $time, (int) $exclude, $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function hold_hours() {
		$s = NURAX_Desk::settings();
		return max( 1, (int) ( isset( $s['appt_hold'] ) ? $s['appt_hold'] : 3 ) );
	}

	/** True when a website request's deposit was never verified in time (slot released). */
	public static function hold_expired( $a ) {
		return 'request' === $a->status && ! empty( $a->deposit ) && (float) $a->deposit > 0 && 'verified' !== $a->deposit_status && strtotime( $a->created_at ) < current_time( 'timestamp' ) - self::hold_hours() * HOUR_IN_SECONDS;
	}

	/** M-Pesa code already used on another live booking? */
	public static function code_used( $code ) {
		global $wpdb;
		return '' !== $code && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . NURAX_Desk::t_appts() . " WHERE deposit_ref = %s AND status IN ('request','confirmed','completed')", $code ) ) > 0; // phpcs:ignore WordPress.DB
	}

	/** Open booking already held by this phone on this date? */
	public static function phone_has( $phone, $date ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . NURAX_Desk::t_appts() . " WHERE phone = %s AND appt_date = %s AND status IN ('request','confirmed')", $phone, $date ) ) > 0; // phpcs:ignore WordPress.DB
	}

	public static function is_full( $date, $time, $exclude = 0 ) {
		$s = NURAX_Desk::settings();
		return self::booked( $date, $time, $exclude ) >= max( 1, (int) $s['appt_capacity'] );
	}

	public static function routes() {
		register_rest_route( 'nurax/v1', '/slots', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function ( WP_REST_Request $req ) {
				$date = NURAX_Desk::valid_date( (string) $req->get_param( 'date' ) );
				if ( '' === $date ) {
					return new WP_Error( 'nurax_date', 'Invalid date', array( 'status' => 400 ) );
				}
				$slots = self::slots( $date );
				$full  = array();
				foreach ( $slots as $sl ) {
					if ( self::is_full( $date, $sl ) ) {
						$full[] = $sl;
					}
				}
				$res = rest_ensure_response( array( 'closed' => ! $slots, 'slots' => $slots, 'full' => $full ) );
				$res->header( 'Cache-Control', 'no-store' );
				return $res;
			},
		) );
	}

	/** Called by the website booking form. Returns the reference. */
	public static function create_web( $a ) {
		global $wpdb;
		$t   = NURAX_Desk::t_appts();
		$s   = NURAX_Desk::settings();
		$ref = NURAX_Desk::new_ref( 'APT', $t );
		$now = current_time( 'mysql' );
		$u   = wp_get_current_user();
		$wpdb->insert( $t, array(
			'ref'           => $ref,
			'status'        => 'request',
			'service'       => array_key_exists( $a['service'], self::services() ) ? $a['service'] : 'other',
			'type'          => array_key_exists( $a['type'], self::types() ) ? $a['type'] : 'studio',
			'appt_date'     => $a['date'],
			'appt_time'     => $a['time'],
			'duration'      => (int) $s['appt_duration'],
			'customer_name' => mb_substr( (string) $a['name'], 0, 120 ),
			'phone'         => NURAX_Desk::phone( $a['phone'] ),
			'email'         => ( $u && $u->ID ) ? $u->user_email : '',
			'user_id'       => ( $u && $u->ID ) ? (int) $u->ID : 0,
			'notes'         => (string) $a['notes'],
			'source'        => 'website',
			'deposit'       => isset( $a['deposit'] ) ? (float) $a['deposit'] : 0,
			'deposit_ref'   => isset( $a['deposit_ref'] ) ? mb_substr( (string) $a['deposit_ref'], 0, 40 ) : '',
			'deposit_status' => ! empty( $a['deposit_ref'] ) ? 'claimed' : 'none',
			'terms_at'      => ! empty( $a['terms'] ) ? $now : null,
			'log'           => wp_json_encode( array( array( 't' => $now, 'u' => 'Website', 'm' => 'Booked on the website.' . ( ! empty( $a['deposit'] ) ? ' Deposit ' . NURAX_Desk::money( $a['deposit'] ) . ( ! empty( $a['deposit_ref'] ) ? ', M-Pesa ' . $a['deposit_ref'] . ' (to verify)' : '' ) . '.' : '' ) . ( ! empty( $a['terms'] ) ? ' Booking terms accepted.' : '' ) ) ) ),
			'created_at'    => $now,
			'updated_at'    => $now,
		) );
		NURAX_Desk::bust();
		return $ref;
	}

	/* ------------------------------------------------------------------ */
	/* Messages                                                            */
	/* ------------------------------------------------------------------ */

	public static function confirm_text( $a ) {
		$first = trim( strtok( (string) $a->customer_name, ' ' ) );
		$where = 'studio' === $a->type ? 'at our studio, ' . NURAX_Desk::shop_address() : ( 'video' === $a->type ? 'by video call (we will send the link before we start)' : 'here on WhatsApp' );
		return implode( "\n", array(
			'Hi ' . ( $first ? $first : 'there' ) . ', this is NURA Beauty.',
			'',
			'Your *' . self::service_label( $a->service ) . '* is *confirmed*:',
			NURAX_Desk::date_label( $a->appt_date, 'l j F Y' ) . ' at ' . NURAX_Desk::time_label( $a->appt_time ),
			ucfirst( $where ) . '.',
			'',
			'Ref: ' . $a->ref,
			( ! empty( $a->deposit ) && (float) $a->deposit > 0 ? ( 'verified' === $a->deposit_status ? 'Deposit of ' . NURAX_Desk::money( $a->deposit ) . ' received, thank you. It comes off your final bill.' : 'Please pay the ' . NURAX_Desk::money( $a->deposit ) . ' deposit to ' . NURAX_Desk::settings()['mpesa_details'] . ' to keep this slot.' ) : '' ),
			'Please arrive on time. We hold your slot for ' . self::grace() . ' minutes. After that it goes to the next client and you are served as a walk-in.',
			'Need to change the time? Reply at least 24 hours before. We look forward to seeing you.',
		) );
	}

	public static function reminder_text( $a ) {
		return NURAX_Desk_Remind::appt_text( $a );
	}

	/* ------------------------------------------------------------------ */
	/* Admin router                                                        */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['appt'] ) ? absint( $_GET['appt'] ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable
		if ( 'new' === $action || ( 'edit' === $action && $id ) ) {
			self::form( $id );
		} elseif ( $id ) {
			self::view( $id );
		} elseif ( 'week' === $view ) {
			self::week();
		} else {
			self::listing();
		}
	}

	private static function head_actions() {
		return NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'action' => 'new' ) ), '+ New appointment', 'nd-btn--primary' ) .
			NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_APPTS ), 'List', 'nd-btn--ghost' ) .
			NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'view' => 'week' ) ), 'Week board', 'nd-btn--ghost' ) .
			NURAX_Desk::export_btns( 'appts' );
	}

	/* ------------------------------------------------------------------ */
	/* List                                                                */
	/* ------------------------------------------------------------------ */

	private static function listing() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'upcoming';
		$q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$t     = NURAX_Desk::t_appts();
		$today = NURAX_Desk::today();
		$tabs  = array(
			'upcoming'  => array( 'Upcoming', "appt_date >= %s AND status IN ('request','confirmed')", 'ASC' ),
			'request'   => array( 'To confirm', "appt_date >= %s AND status = 'request'", 'ASC' ),
			'today'     => array( 'Today', "appt_date = %s AND status <> 'cancelled'", 'ASC' ),
			'past'      => array( 'Past', "appt_date < %s AND status <> 'cancelled'", 'DESC' ),
			'cancelled' => array( 'Cancelled', "status = 'cancelled' AND appt_date >= DATE_SUB(%s, INTERVAL 365 DAY)", 'DESC' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'upcoming';
		}
		$counts = array();
		foreach ( $tabs as $k => $d ) {
			$counts[ $k ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE " . $d[1], $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		$where = $tabs[ $tab ][1];
		$args  = array( $today );
		if ( '' !== $q ) {
			$like   = '%' . $wpdb->esc_like( $q ) . '%';
			$where .= ' AND ( customer_name LIKE %s OR phone LIKE %s OR ref LIKE %s OR notes LIKE %s )';
			$ph     = NURAX_Desk::phone( $q );
			$args   = array_merge( $args, array( $like, $ph ? '%' . $ph . '%' : $like, $like, $like ) );
		}
		$dir   = $tabs[ $tab ][2];
		$per   = 30;
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY appt_date $dir, appt_time $dir LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $paged - 1 ) * $per ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		NURAX_Desk::header( NURAX_Desk::P_APPTS, 'Appointments', self::head_actions() );
		self::studio_board();
		$k = self::kpis();
		echo '<div class="nd-kpis nd-kpis--4">';
		NURAX_Desk::kpi( 'Today', (string) $k['today'], $k['today_confirmed'] . ' confirmed', 'ink' );
		NURAX_Desk::kpi( 'Next 7 days', (string) $k['week'], 'booked' );
		NURAX_Desk::kpi( 'To confirm', (string) $k['requests'], 'website requests', $k['requests'] ? 'amber' : '' );
		$no = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'no_show' AND appt_date >= %s", gmdate( 'Y-m-d', strtotime( $today . ' -30 days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		NURAX_Desk::kpi( 'No-shows (30 days)', (string) $no, 'clients who missed', $no ? 'red' : '' );
		echo '</div>';

		echo '<div class="nd-toolbar"><nav class="nd-pills">';
		foreach ( $tabs as $kk => $d ) {
			echo '<a class="nd-pill' . ( $kk === $tab ? ' is-on' : '' ) . ( 'request' === $kk && $counts[ $kk ] ? ' nd-pill--alert' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'tab' => $kk, 's' => $q ) ) ) . '">' . esc_html( $d[0] ) . ' <span>' . (int) $counts[ $kk ] . '</span></a>';
		}
		echo '</nav><form method="get" class="nd-search"><input type="hidden" name="page" value="' . esc_attr( NURAX_Desk::P_APPTS ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="search" name="s" value="' . esc_attr( $q ) . '" placeholder="Search name, phone or ref"><button class="nd-btn nd-btn--ghost" type="submit">Search</button></form></div>';

		if ( ! $rows ) {
			echo '<div class="nd-empty"><strong>No appointments here.</strong><p>Bookings made on the website appear here automatically under "To confirm".</p></div>';
			NURAX_Desk::footer();
			return;
		}
		echo '<div class="nd-table-wrap"><table class="nd-table" data-nd-type="appt"><thead><tr><th class="nd-col-chk"><input type="checkbox" data-nd-all aria-label="Select all"></th><th>When</th><th>Client</th><th>Service</th><th>Stylist</th><th>Status</th><th class="nd-col-act"></th></tr></thead><tbody>';
		$last = '';
		foreach ( $rows as $a ) {
			if ( $a->appt_date !== $last ) {
				$last = $a->appt_date;
				$lbl  = $a->appt_date === $today ? 'Today' : NURAX_Desk::date_label( $a->appt_date, 'l j F Y' );
				echo '<tr class="nd-daysep"><td colspan="7">' . esc_html( $lbl ) . '</td></tr>';
			}
			self::row( $a );
		}
		echo '</tbody></table></div>';
		NURAX_Desk_Plans::pager( $total, $per, $paged, array( 'tab' => $tab, 's' => $q ), NURAX_Desk::P_APPTS );
		NURAX_Desk::footer();
	}

	private static function row( $a ) {
		$view = NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $a->id ) );
		echo '<tr class="is-' . esc_attr( $a->status ) . '" data-nd-key="appt:' . (int) $a->id . '" data-nd-search="' . esc_attr( strtolower( $a->ref . ' ' . $a->customer_name . ' ' . $a->phone . ' ' . $a->notes ) ) . '">';
		echo '<td class="nd-col-chk"><input type="checkbox" class="nd-check" value="appt:' . (int) $a->id . '" aria-label="Select"></td>';
		echo '<td><strong class="nd-time">' . esc_html( NURAX_Desk::time_label( $a->appt_time ) ) . '</strong><small>' . esc_html( (int) $a->duration . ' min - ' . self::type_label( $a->type ) ) . '</small></td>';
		echo '<td><a href="' . esc_url( $view ) . '"><strong>' . esc_html( $a->customer_name ) . '</strong></a><small>' . esc_html( NURAX_Desk::phone_label( $a->phone ) . ' - ' . $a->ref ) . '</small></td>';
		echo '<td>' . esc_html( self::service_label( $a->service ) ) . ( $a->notes ? '<small class="nd-clip">' . esc_html( $a->notes ) . '</small>' : '' ) . self::deposit_tag( $a ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td>' . esc_html( $a->staff ? $a->staff : '-' ) . '</td>';
		echo '<td>' . NURAX_Desk::badge( $a->status, self::status_label( $a->status ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<td class="nd-col-act">';
		self::quick_actions( $a );
		echo '<button type="button" class="nd-ico-btn nd-ico-btn--danger" data-nd-del="appt:' . (int) $a->id . '">Delete</button></td></tr>';
	}

	private static function quick_actions( $a ) {
		$today = NURAX_Desk::today();
		$dep   = ! empty( $a->deposit ) && (float) $a->deposit > 0 && 'verified' !== $a->deposit_status;
		if ( $dep && in_array( $a->status, array( 'request', 'confirmed' ), true ) ) {
			self::status_btn( $a->id, 'deposit_ok', 'Deposit received', 'nd-ico-btn nd-ico-btn--ok' );
		}
		if ( 'request' === $a->status ) {
			self::status_btn( $a->id, 'confirmed', $dep ? 'Confirm without deposit' : 'Confirm', $dep ? 'nd-ico-btn' : 'nd-ico-btn nd-ico-btn--ok', $dep ? 'The deposit has not been marked as received. Confirm anyway?' : '' );
		}
		if ( 'confirmed' === $a->status && $a->appt_date === $today && empty( $a->arrived_at ) ) {
			self::status_btn( $a->id, 'arrived', 'Checked in', 'nd-ico-btn' );
		}
		if ( in_array( $a->status, array( 'request', 'confirmed' ), true ) && $a->phone ) {
			$txt = 'request' === $a->status ? self::confirm_text( $a ) : self::reminder_text( $a );
			echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $a->phone, $txt ) ) . '"' . ( 'confirmed' === $a->status ? ' data-nd-remind="appt:' . (int) $a->id . '"' : '' ) . '>' . ( 'request' === $a->status ? 'WhatsApp' : 'Remind' ) . '</a>';
		}
		if ( 'confirmed' === $a->status && $a->appt_date <= $today ) {
			self::status_btn( $a->id, 'completed', 'Done', 'nd-ico-btn nd-ico-btn--ok' );
			self::status_btn( $a->id, 'no_show', 'No-show', 'nd-ico-btn nd-ico-btn--danger' );
		}
	}

	private static function status_btn( $id, $to, $label, $cls, $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inline"' . ( $confirm ? ' data-nd-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '><input type="hidden" name="action" value="nurax_appt_status"><input type="hidden" name="appt_id" value="' . (int) $id . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
		wp_nonce_field( 'nurax_appt_status' );
		echo '<button type="submit" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/* ------------------------------------------------------------------ */
	/* Week board                                                          */
	/* ------------------------------------------------------------------ */

	private static function week() {
		$start = isset( $_GET['start'] ) ? NURAX_Desk::valid_date( wp_unslash( $_GET['start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' === $start ) {
			$start = NURAX_Desk::today();
		}
		$dow   = (int) gmdate( 'N', strtotime( $start ) );
		$mon   = gmdate( 'Y-m-d', strtotime( $start . ' -' . ( $dow - 1 ) . ' days' ) );
		$sun   = gmdate( 'Y-m-d', strtotime( $mon . ' +6 days' ) );
		$rows  = self::between( $mon, $sun );
		$by    = array();
		foreach ( $rows as $a ) {
			$by[ $a->appt_date ][] = $a;
		}
		$prev = gmdate( 'Y-m-d', strtotime( $mon . ' -7 days' ) );
		$next = gmdate( 'Y-m-d', strtotime( $mon . ' +7 days' ) );
		NURAX_Desk::header( NURAX_Desk::P_APPTS, 'Week of ' . NURAX_Desk::date_label( $mon, 'j M' ) . ' - ' . NURAX_Desk::date_label( $sun, 'j M Y' ), self::head_actions() );
		echo '<div class="nd-toolbar"><nav class="nd-pills">';
		echo '<a class="nd-pill" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'view' => 'week', 'start' => $prev ) ) ) . '">&larr; Previous week</a>';
		echo '<a class="nd-pill is-on" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'view' => 'week' ) ) ) . '">This week</a>';
		echo '<a class="nd-pill" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'view' => 'week', 'start' => $next ) ) ) . '">Next week &rarr;</a>';
		echo '</nav><div class="nd-legend">' . NURAX_Desk::badge( 'request', 'To confirm' ) . NURAX_Desk::badge( 'confirmed', 'Confirmed' ) . NURAX_Desk::badge( 'completed', 'Completed' ) . NURAX_Desk::badge( 'no_show', 'No-show' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="nd-week">';
		for ( $i = 0; $i < 7; $i++ ) {
			$d      = gmdate( 'Y-m-d', strtotime( $mon . ' +' . $i . ' days' ) );
			$closed = ! self::slots( $d );
			$list   = isset( $by[ $d ] ) ? $by[ $d ] : array();
			echo '<div class="nd-week__day' . ( NURAX_Desk::today() === $d ? ' is-today' : '' ) . ( $closed ? ' is-closed' : '' ) . '">';
			echo '<header><span>' . esc_html( NURAX_Desk::date_label( $d, 'D' ) ) . '</span><strong>' . esc_html( NURAX_Desk::date_label( $d, 'j' ) ) . '</strong><a href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'action' => 'new', 'date' => $d ) ) ) . '" title="Add appointment" class="nd-week__add">+</a></header>';
			if ( $closed && ! $list ) {
				echo '<p class="nd-week__closed">Closed</p>';
			}
			foreach ( $list as $a ) {
				if ( 'cancelled' === $a->status ) {
					continue;
				}
				echo '<a class="nd-week__item is-' . esc_attr( $a->status ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $a->id ) ) ) . '"><b>' . esc_html( NURAX_Desk::time_label( $a->appt_time ) ) . '</b><span>' . esc_html( $a->customer_name ) . '</span><small>' . esc_html( self::service_label( $a->service ) . ( $a->staff ? ' - ' . $a->staff : '' ) ) . '</small></a>';
			}
			echo '</div>';
		}
		echo '</div>';
		NURAX_Desk::footer();
	}

	/* ------------------------------------------------------------------ */
	/* Single                                                              */
	/* ------------------------------------------------------------------ */

	private static function view( $id ) {
		$a = self::get( $id );
		if ( ! $a ) {
			NURAX_Desk::header( NURAX_Desk::P_APPTS, 'Appointment not found' );
			NURAX_Desk::footer();
			return;
		}
		$act = NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_APPTS ), 'All appointments', 'nd-btn--ghost' ) .
			NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $id, 'action' => 'edit' ) ), 'Edit / reschedule', 'nd-btn--ghost' );
		NURAX_Desk::header( NURAX_Desk::P_APPTS, $a->customer_name . ' - ' . self::service_label( $a->service ), $act );
		echo '<div class="nd-plan"><div class="nd-plan__main">';
		echo '<section class="nd-card nd-hero nd-hero--appt"><div class="nd-cal"><span>' . esc_html( NURAX_Desk::date_label( $a->appt_date, 'M' ) ) . '</span><strong>' . esc_html( NURAX_Desk::date_label( $a->appt_date, 'j' ) ) . '</strong><small>' . esc_html( NURAX_Desk::date_label( $a->appt_date, 'D' ) ) . '</small></div>';
		echo '<div class="nd-hero__txt"><div class="nd-hero__badges">' . NURAX_Desk::badge( $a->status, self::status_label( $a->status ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<h2>' . esc_html( NURAX_Desk::time_label( $a->appt_time ) . ' - ' . self::service_label( $a->service ) ) . '</h2>';
		echo '<p class="nd-mut">' . esc_html( (int) $a->duration . ' minutes - ' . self::type_label( $a->type ) . ( $a->staff ? ' - with ' . $a->staff : '' ) . ' - ref ' . $a->ref ) . '</p>';
		echo '<div class="nd-hero__acts">';
		if ( $a->phone ) {
			if ( 'request' === $a->status ) {
				echo NURAX_Desk::btn( NURAX_Desk::wa( $a->phone, self::confirm_text( $a ) ), 'Send confirmation on WhatsApp', 'nd-btn--wa', 'target="_blank" rel="noopener"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( 'confirmed' === $a->status ) {
				echo NURAX_Desk::btn( NURAX_Desk::wa( $a->phone, self::reminder_text( $a ) ), 'Send reminder on WhatsApp', 'nd-btn--wa', 'target="_blank" rel="noopener" data-nd-remind="appt:' . (int) $a->id . '"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo NURAX_Desk::btn( NURAX_Desk::wa( $a->phone, self::confirm_text( $a ) ), 'Re-send confirmation', 'nd-btn--ghost', 'target="_blank" rel="noopener"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</div></div></section>';
		if ( $a->notes ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Notes</h2></header><p class="nd-note">' . nl2br( esc_html( $a->notes ) ) . '</p></section>';
		}
		if ( $a->plan_id ) {
			$p = NURAX_Desk_Plans::get( $a->plan_id );
			if ( $p ) {
				$m = NURAX_Desk_Plans::metrics( $p, NURAX_Desk_Plans::paid_sum( $p->id ) );
				echo '<section class="nd-card"><header class="nd-card__h"><h2>Linked payment plan</h2>' . NURAX_Desk::badge( $m['health'], NURAX_Desk_Plans::health_label( $m['health'] ) ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<p><a href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) ) ) . '"><strong>' . esc_html( $p->ref ) . '</strong></a> - ' . esc_html( $p->product_name ) . '</p><div class="nd-prog"><span style="width:' . (int) $m['progress'] . '%"></span></div><small>' . esc_html( NURAX_Desk::money( $m['paid'] ) . ' paid - balance ' . NURAX_Desk::money( $m['balance'] ) ) . '</small></section>';
			}
		}
		$history = self::history( $a );
		if ( $history ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Client history</h2><span class="nd-mut">' . count( $history ) . ' other visits</span></header><ul class="nd-list">';
			foreach ( $history as $h ) {
				echo '<li><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $h->id ) ) ) . '"><strong>' . esc_html( NURAX_Desk::date_label( $h->appt_date ) ) . '</strong><small>' . esc_html( self::service_label( $h->service ) ) . '</small></a><span class="nd-list__meta">' . NURAX_Desk::badge( $h->status, self::status_label( $h->status ) ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</ul></section>';
		}
		echo '</div><aside class="nd-plan__side">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Client</h2></header><dl class="nd-dl">';
		echo '<div><dt>Name</dt><dd>' . esc_html( $a->customer_name ) . '</dd></div>';
		echo '<div><dt>Phone</dt><dd>' . ( $a->phone ? '<a href="' . esc_url( 'https://wa.me/' . $a->phone ) . '" target="_blank" rel="noopener">' . esc_html( NURAX_Desk::phone_label( $a->phone ) ) . '</a>' : '-' ) . '</dd></div>';
		echo '<div><dt>E-mail</dt><dd>' . esc_html( $a->email ? $a->email : '-' ) . '</dd></div>';
		echo '<div><dt>Booked via</dt><dd>' . esc_html( ucfirst( $a->source ) . ', ' . NURAX_Desk::date_label( $a->created_at, 'j M Y' ) ) . '</dd></div>';
		if ( (float) $a->price > 0 ) {
			echo '<div><dt>Price</dt><dd>' . esc_html( NURAX_Desk::money( $a->price ) ) . '</dd></div>';
		}
		echo '</dl></section>';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Status</h2></header><div class="nd-stack">';
		$opts = array(
			'request'   => array( 'confirmed' => array( 'Confirm appointment', 'nd-btn--primary', '' ), 'cancelled' => array( 'Cancel', 'nd-btn--danger-ghost', 'Cancel this appointment?' ) ),
			'confirmed' => array( 'completed' => array( 'Mark completed', 'nd-btn--primary', '' ), 'no_show' => array( 'Client did not come', 'nd-btn--ghost', '' ), 'cancelled' => array( 'Cancel', 'nd-btn--danger-ghost', 'Cancel this appointment?' ) ),
			'completed' => array( 'confirmed' => array( 'Undo - back to confirmed', 'nd-btn--ghost', '' ) ),
			'no_show'   => array( 'confirmed' => array( 'Undo - back to confirmed', 'nd-btn--ghost', '' ) ),
			'cancelled' => array( 'request' => array( 'Restore appointment', 'nd-btn--ghost', '' ) ),
		);
		$set = isset( $opts[ $a->status ] ) ? $opts[ $a->status ] : array();
		foreach ( $set as $to => $o ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $o[2] ? ' data-nd-confirm="' . esc_attr( $o[2] ) . '"' : '' ) . '><input type="hidden" name="action" value="nurax_appt_status"><input type="hidden" name="appt_id" value="' . (int) $id . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
			wp_nonce_field( 'nurax_appt_status' );
			echo '<button type="submit" class="nd-btn nd-btn--block ' . esc_attr( $o[1] ) . '">' . esc_html( $o[0] ) . '</button></form>';
		}
		NURAX_Desk_Remind::delete_form( 'appt:' . (int) $id, 'Delete appointment' );
		if ( in_array( $a->status, array( 'completed' ), true ) && in_array( $a->service, array( 'consultation', 'fitting' ), true ) ) {
			echo '<a class="nd-btn nd-btn--ghost nd-btn--block" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'action' => 'new' ) ) ) . '">Start a payment plan</a>';
		}
		echo '</div></section>';
		$log = NURAX_Desk::log_rows( $a->log );
		if ( $log ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Activity</h2></header><ol class="nd-log">';
			foreach ( array_slice( $log, 0, 20 ) as $e ) {
				echo '<li><span>' . esc_html( isset( $e['m'] ) ? $e['m'] : '' ) . '</span><small>' . esc_html( ( isset( $e['t'] ) ? NURAX_Desk::date_label( $e['t'], 'j M, H:i' ) : '' ) . ' - ' . ( isset( $e['u'] ) ? $e['u'] : '' ) ) . '</small></li>';
			}
			echo '</ol></section>';
		}
		echo '</aside></div>';
		NURAX_Desk::footer();
	}

	private static function history( $a ) {
		global $wpdb;
		if ( '' === (string) $a->phone ) {
			return array();
		}
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_appts() . ' WHERE phone = %s AND id <> %d ORDER BY appt_date DESC LIMIT 10', $a->phone, (int) $a->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/* ------------------------------------------------------------------ */
	/* Form                                                                */
	/* ------------------------------------------------------------------ */

	private static function form( $id ) {
		$a = $id ? self::get( $id ) : null;
		$s = NURAX_Desk::settings();
		$d = isset( $_GET['date'] ) ? NURAX_Desk::valid_date( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$v = array(
			'customer_name' => $a ? $a->customer_name : '',
			'phone'         => $a ? $a->phone : '',
			'email'         => $a ? $a->email : '',
			'service'       => $a ? $a->service : 'consultation',
			'type'          => $a ? $a->type : 'studio',
			'appt_date'     => $a ? $a->appt_date : ( $d ? $d : NURAX_Desk::today() ),
			'appt_time'     => $a ? $a->appt_time : '',
			'duration'      => $a ? (int) $a->duration : (int) $s['appt_duration'],
			'staff'         => $a ? $a->staff : '',
			'price'         => $a && (float) $a->price > 0 ? (float) $a->price : '',
			'status'        => $a ? $a->status : 'confirmed',
			'plan_id'       => $a ? (int) $a->plan_id : 0,
			'notes'         => $a ? $a->notes : '',
		);
		if ( ! $a ) { // Prefill from a WhatsApp order.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$g = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! empty( $g['pf_name'] ) ) { $v['customer_name'] = sanitize_text_field( $g['pf_name'] ); }
			if ( ! empty( $g['pf_phone'] ) ) { $v['phone'] = NURAX_Desk::phone( $g['pf_phone'] ); }
			if ( ! empty( $g['pf_service'] ) && array_key_exists( $g['pf_service'], self::services() ) ) { $v['service'] = $g['pf_service']; }
			if ( ! empty( $g['pf_notes'] ) ) { $v['notes'] = sanitize_textarea_field( $g['pf_notes'] ); }
			// phpcs:enable
		}
		$back = $a ? NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $id ) ) : NURAX_Desk::url( NURAX_Desk::P_APPTS );
		NURAX_Desk::header( NURAX_Desk::P_APPTS, $a ? 'Edit appointment ' . $a->ref : 'New appointment', NURAX_Desk::btn( $back, 'Cancel', 'nd-btn--ghost' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form"><input type="hidden" name="action" value="nurax_appt_save"><input type="hidden" name="appt_id" value="' . (int) $id . '">';
		wp_nonce_field( 'nurax_appt_save' );
		echo '<div class="nd-plan"><div class="nd-plan__main">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Client</h2></header><div class="nd-fields nd-fields--3">';
		echo '<label class="nd-field"><span class="nd-label">Full name *</span><input type="text" name="customer_name" required maxlength="120" value="' . esc_attr( $v['customer_name'] ) . '"></label>';
		echo '<label class="nd-field"><span class="nd-label">Phone / WhatsApp *</span><input type="tel" name="phone" required value="' . esc_attr( $v['phone'] ? '+' . $v['phone'] : '' ) . '" placeholder="0712 345 678"></label>';
		echo '<label class="nd-field"><span class="nd-label">E-mail</span><input type="email" name="email" value="' . esc_attr( $v['email'] ) . '"></label>';
		echo '</div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Appointment</h2></header><div class="nd-fields nd-fields--3">';
		echo '<label class="nd-field"><span class="nd-label">Service</span><select name="service">';
		foreach ( self::services() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $v['service'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="nd-field"><span class="nd-label">How</span><select name="type">';
		foreach ( self::types() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $v['type'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		$staff = array_filter( array_map( 'trim', explode( ',', (string) $s['staff'] ) ) );
		if ( $staff ) {
			echo '<label class="nd-field"><span class="nd-label">Stylist</span><select name="staff"><option value="">Anyone</option>';
			foreach ( $staff as $st ) {
				echo '<option' . selected( $v['staff'], $st, false ) . '>' . esc_html( $st ) . '</option>';
			}
			echo '</select></label>';
		} else {
			echo '<label class="nd-field"><span class="nd-label">Stylist</span><input type="text" name="staff" maxlength="80" value="' . esc_attr( $v['staff'] ) . '"><small>Add your team under Settings to pick from a list.</small></label>';
		}
		echo '<label class="nd-field"><span class="nd-label">Date *</span><input type="date" name="appt_date" required value="' . esc_attr( $v['appt_date'] ) . '"></label>';
		$times = self::slots( $v['appt_date'] );
		if ( $v['appt_time'] && ! in_array( $v['appt_time'], $times, true ) ) {
			$times[] = $v['appt_time'];
			sort( $times );
		}
		echo '<label class="nd-field"><span class="nd-label">Time *</span><input type="time" name="appt_time" required step="300" value="' . esc_attr( $v['appt_time'] ) . '" list="nd-times"><datalist id="nd-times">';
		foreach ( $times as $tm ) {
			echo '<option value="' . esc_attr( $tm ) . '">';
		}
		echo '</datalist><small>Opening slots: ' . esc_html( $s['appt_open'] . ' - ' . $s['appt_close'] ) . '</small></label>';
		echo '<label class="nd-field"><span class="nd-label">Length (minutes)</span><input type="number" name="duration" min="10" step="5" value="' . esc_attr( $v['duration'] ) . '"></label>';
		echo '<label class="nd-field"><span class="nd-label">Price (KSh, optional)</span><input type="number" name="price" min="0" step="1" value="' . esc_attr( $v['price'] ) . '"></label>';
		echo '<label class="nd-field"><span class="nd-label">Status</span><select name="status">';
		foreach ( self::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $v['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		$plans = NURAX_Desk_Plans::all( array( 'request', 'active', 'completed' ) );
		echo '<label class="nd-field"><span class="nd-label">Link to payment plan</span><select name="plan_id"><option value="0">None</option>';
		foreach ( array_slice( $plans, 0, 300 ) as $p ) {
			echo '<option value="' . (int) $p->id . '"' . selected( $v['plan_id'], (int) $p->id, false ) . '>' . esc_html( $p->ref . ' - ' . $p->customer_name ) . '</option>';
		}
		echo '</select></label>';
		echo '</div></section></div>';
		echo '<aside class="nd-plan__side"><section class="nd-card"><header class="nd-card__h"><h2>Notes</h2></header><label class="nd-field"><textarea name="notes" rows="7" placeholder="Look wanted, unit to bring, special requests">' . esc_textarea( $v['notes'] ) . '</textarea></label>';
		echo '<button type="submit" class="nd-btn nd-btn--primary nd-btn--block">' . ( $a ? 'Save changes' : 'Create appointment' ) . '</button></section></aside></div></form>';
		NURAX_Desk::footer();
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	public static function h_save() {
		NURAX_Desk::guard( 'nurax_appt_save' );
		global $wpdb;
		$in    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id    = isset( $in['appt_id'] ) ? absint( $in['appt_id'] ) : 0;
		$name  = isset( $in['customer_name'] ) ? mb_substr( sanitize_text_field( $in['customer_name'] ), 0, 120 ) : '';
		$phone = isset( $in['phone'] ) ? NURAX_Desk::phone( $in['phone'] ) : '';
		$date  = isset( $in['appt_date'] ) ? NURAX_Desk::valid_date( $in['appt_date'] ) : '';
		$time  = isset( $in['appt_time'] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $in['appt_time'] ) ? (string) $in['appt_time'] : '';
		$back  = $id ? NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $id, 'action' => 'edit' ) ) : NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'action' => 'new' ) );
		if ( strlen( $name ) < 2 || '' === $phone || '' === $date || '' === $time ) {
			wp_safe_redirect( add_query_arg( 'nd_msg', 'invalid', $back ) );
			exit;
		}
		$status = isset( $in['status'] ) && array_key_exists( $in['status'], self::statuses() ) ? $in['status'] : 'confirmed';
		$email  = isset( $in['email'] ) && is_email( $in['email'] ) ? sanitize_email( $in['email'] ) : '';
		$data   = array(
			'status'        => $status,
			'service'       => isset( $in['service'] ) && array_key_exists( $in['service'], self::services() ) ? $in['service'] : 'other',
			'type'          => isset( $in['type'] ) && array_key_exists( $in['type'], self::types() ) ? $in['type'] : 'studio',
			'appt_date'     => $date,
			'appt_time'     => $time,
			'duration'      => isset( $in['duration'] ) ? min( 600, max( 10, absint( $in['duration'] ) ) ) : 60,
			'customer_name' => $name,
			'phone'         => $phone,
			'email'         => $email,
			'staff'         => isset( $in['staff'] ) ? mb_substr( sanitize_text_field( $in['staff'] ), 0, 80 ) : '',
			'price'         => isset( $in['price'] ) ? max( 0, round( (float) $in['price'], 2 ) ) : 0,
			'plan_id'       => isset( $in['plan_id'] ) ? absint( $in['plan_id'] ) : 0,
			'notes'         => isset( $in['notes'] ) ? sanitize_textarea_field( $in['notes'] ) : '',
			'updated_at'    => current_time( 'mysql' ),
		);
		$t = NURAX_Desk::t_appts();
		if ( $id ) {
			$old = self::get( $id );
			if ( ! $old ) {
				wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'nd_msg' => 'notfound' ) ) );
				exit;
			}
			$wpdb->update( $t, $data, array( 'id' => $id ) );
			$msg = ( $old->appt_date !== $date || $old->appt_time !== $time ) ? 'Rescheduled from ' . NURAX_Desk::date_label( $old->appt_date, 'D j M' ) . ' ' . $old->appt_time . ' to ' . NURAX_Desk::date_label( $date, 'D j M' ) . ' ' . $time . '.' : 'Details edited.';
			NURAX_Desk::log( $t, $id, $msg );
		} else {
			$data['ref']        = NURAX_Desk::new_ref( 'APT', $t );
			$data['source']     = 'admin';
			$data['created_at'] = current_time( 'mysql' );
			$data['log']        = '[]';
			$wpdb->insert( $t, $data );
			$id = (int) $wpdb->insert_id;
			NURAX_Desk::log( $t, $id, 'Appointment created.' );
		}
		NURAX_Desk::bust();
		$s   = NURAX_Desk::settings();
		$msg = ( in_array( $status, array( 'request', 'confirmed' ), true ) && self::booked( $date, $time ) > max( 1, (int) $s['appt_capacity'] ) ) ? 'full' : 'saved';
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $id, 'nd_msg' => $msg ) ) );
		exit;
	}

	public static function h_status() {
		NURAX_Desk::guard( 'nurax_appt_status' );
		global $wpdb;
		$id = isset( $_POST['appt_id'] ) ? absint( $_POST['appt_id'] ) : 0;
		$to = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
		$a  = self::get( $id );
		if ( ! $a || ! ( array_key_exists( $to, self::statuses() ) || in_array( $to, array( 'deposit_ok', 'arrived' ), true ) ) ) {
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		$now = current_time( 'mysql' );
		if ( 'deposit_ok' === $to ) {
			$set = array( 'deposit_status' => 'verified', 'status' => 'request' === $a->status ? 'confirmed' : $a->status, 'updated_at' => $now );
			$msg = 'Deposit ' . NURAX_Desk::money( $a->deposit ) . ' received' . ( $a->deposit_ref ? ' (M-Pesa ' . $a->deposit_ref . ')' : '' ) . ' - booking confirmed.';
		} elseif ( 'arrived' === $to ) {
			$set   = array( 'arrived_at' => $now, 'updated_at' => $now );
			$a->arrived_at = $now;
			$state = self::arrival_state( $a );
			$msg   = 'Checked in at ' . NURAX_Desk::time_label( substr( $now, 11, 5 ) ) . ': ' . $state[1] . '.';
		} else {
			$set = array( 'status' => $to, 'updated_at' => $now );
			$msg = 'Status changed to ' . self::status_label( $to ) . '.';
		}
		$wpdb->update( NURAX_Desk::t_appts(), $set, array( 'id' => $id ) );
		NURAX_Desk::log( NURAX_Desk::t_appts(), $id, $msg );
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 'appointment', $a->ref . ' ' . $a->customer_name . ': ' . $msg, array( 'object' => 'appt', 'object_id' => $id, 'amount' => 'deposit_ok' === $to ? (float) $a->deposit : 0 ) );
		}
		NURAX_Desk::bust();
		$ref  = wp_get_referer();
		$dest = ( $ref && false !== strpos( $ref, 'page=' . NURAX_Desk::P_APPTS ) && false === strpos( $ref, 'appt=' ) ) || ( $ref && false !== strpos( $ref, 'page=' . NURAX_Desk::MENU ) ) ? remove_query_arg( 'nd_msg', $ref ) : NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $id ) );
		wp_safe_redirect( add_query_arg( 'nd_msg', 'status', $dest ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Overview widgets                                                    */
	/* ------------------------------------------------------------------ */

	public static function mini_list( $from, $to, $empty, $compact = false ) {
		$rows = array_filter( self::between( $from, $to ), function ( $a ) {
			return 'cancelled' !== $a->status;
		} );
		if ( ! $rows ) {
			echo '<p class="nd-empty-s">' . esc_html( $empty ) . '</p>';
			return;
		}
		echo '<ul class="nd-list nd-list--appts">';
		foreach ( $rows as $a ) {
			echo '<li class="is-' . esc_attr( $a->status ) . '"><span class="nd-list__time">' . esc_html( NURAX_Desk::time_label( $a->appt_time ) ) . '</span><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $a->id ) ) ) . '"><strong>' . esc_html( $a->customer_name ) . '</strong><small>' . esc_html( self::service_label( $a->service ) . ' - ' . self::type_label( $a->type ) . ( $a->staff ? ' - ' . $a->staff : '' ) ) . '</small></a>';
			echo '<span class="nd-list__meta">' . NURAX_Desk::badge( $a->status, self::status_label( $a->status ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( ! $compact ) {
				echo '<span class="nd-list__acts">';
				self::quick_actions( $a );
				echo '</span>';
			} elseif ( $a->phone && in_array( $a->status, array( 'request', 'confirmed' ), true ) ) {
				echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $a->phone, 'request' === $a->status ? self::confirm_text( $a ) : self::reminder_text( $a ) ) ) . '">' . ( 'request' === $a->status ? 'Confirm' : 'Remind' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	public static function request_list( $limit ) {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_appts() . " WHERE status = 'request' AND appt_date >= %s ORDER BY appt_date ASC, appt_time ASC LIMIT %d", NURAX_Desk::today(), $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			echo '<p class="nd-empty-s">No bookings waiting for confirmation.</p>';
			return;
		}
		echo '<h3 class="nd-sub">Bookings to confirm</h3><ul class="nd-list">';
		foreach ( $rows as $a ) {
			echo '<li><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $a->id ) ) ) . '"><strong>' . esc_html( $a->customer_name ) . '</strong><small>' . esc_html( self::service_label( $a->service ) . ' - ' . NURAX_Desk::date_label( $a->appt_date, 'D j M' ) . ', ' . NURAX_Desk::time_label( $a->appt_time ) ) . '</small></a><span class="nd-list__acts">';
			self::quick_actions( $a );
			echo '</span></li>';
		}
		echo '</ul>';
	}
}
