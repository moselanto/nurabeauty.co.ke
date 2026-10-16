<?php
/**
 * NURA Desk - Reminders, actions, delete + bulk (NURA Experience 1.59.0).
 *
 *  - Reminder centre: everyone who should hear from NURA today (late or due
 *    instalments, plan deposits not paid, unpaid confirmed orders, orders
 *    nobody answered, tomorrow's appointments), with escalating, editable
 *    WhatsApp templates, one-tap sending, "remind everyone" runner and a
 *    record of who was reminded when.
 *  - Automatic WhatsApp reminders through the WhatsApp Business API when it
 *    is connected (Settings > NURA Experience > WhatsApp).
 *  - AJAX actions used by the interactive Desk: status changes, delete,
 *    bulk actions, order paid / unpaid.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Remind {

	public static function hooks() {
		add_action( 'wp_ajax_nurax_desk', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_nurax_desk_delete', array( __CLASS__, 'h_delete' ) );
		add_action( 'admin_post_nurax_desk_order_pay', array( __CLASS__, 'h_order_pay' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Templates                                                           */
	/* ------------------------------------------------------------------ */

	public static function template_defaults() {
		return array(
			'tpl_plan_due'     => "Hi {first}, this is NURA Beauty.\n\nA friendly reminder that your Lipa Mdogo Mdogo payment of *{amount}* for the *{product}* is due on *{due}*.\n\nPaid so far: {paid} of {price} ({progress}%)\nBalance: {balance}\n{pay_line}\nPlan ref: {ref}\nYour statement: {link}\n\nThank you for choosing NURA.",
			'tpl_plan_late'    => "Hi {first}, this is NURA Beauty.\n\nWe noticed your Lipa Mdogo Mdogo payment for the *{product}* was due on {due} and *{amount}* is still outstanding.\n\nWhen you are ready, please send it and share the M-Pesa code here.\n{pay_line}\nBalance: {balance}\nPlan ref: {ref}\nYour statement: {link}\n\nIf anything has changed, just reply and we will work it out together.",
			'tpl_plan_firm'    => "Hi {first}, NURA Beauty here.\n\nYour Lipa Mdogo Mdogo plan for the *{product}* is now *{days} days behind*, with *{amount}* outstanding.\n\nPlease make a payment today, or reply to agree a new date with us.\n{pay_line}\nBalance: {balance}\nPlan ref: {ref}\nYour statement: {link}",
			'tpl_plan_final'   => "Hi {first}, NURA Beauty here.\n\nWe have not received a payment on your plan for the *{product}* for {days} days. *{amount}* is outstanding.\n\nPlease contact us today so we can keep your wig reserved for you.\n{pay_line}\nBalance: {balance}\nPlan ref: {ref}\nYour statement: {link}",
			'tpl_plan_deposit' => "Hi {first}, thank you for choosing Lipa Mdogo Mdogo with NURA Beauty.\n\nTo reserve your *{product}*, the deposit is *{amount}*. After that you pay {weekly} a week for {weeks} weeks, no interest.\n{pay_line}\nPlan ref: {ref}\n\nSend the M-Pesa code here once paid and we will put your wig aside for you.",
			'tpl_order_pay'    => "Hi {first}, this is NURA Beauty.\n\nYour order *{ref}* ({items}) is confirmed and waiting for you. Total: *{amount}*.\n{pay_line}\nOnce paid, please share the M-Pesa code here and we will {next_step}.\n\nThank you!",
			'tpl_order_follow' => "Hi {first}, this is NURA Beauty.\n\nWe received your order *{ref}* for {items} ({amount}). Are you still interested? Reply here and we will confirm stock, delivery and payment for you right away.",
			'tpl_appt'         => "Hi {first}, a friendly reminder from NURA Beauty.\n\nYour *{service}* is {when} at *{time}*{where}.\nRef: {ref}\n\nPlease reply YES to confirm, or let us know if you need another time.",
		);
	}

	public static function tpl( $key ) {
		$s = NURAX_Desk::settings();
		$d = self::template_defaults();
		$t = isset( $s[ $key ] ) && '' !== trim( (string) $s[ $key ] ) ? (string) $s[ $key ] : ( isset( $d[ $key ] ) ? $d[ $key ] : '' );
		return $t;
	}

	public static function render( $tpl, $vars ) {
		$s           = NURAX_Desk::settings();
		$pay         = trim( (string) $s['mpesa_details'] );
		$vars['pay_line'] = '' !== $pay ? 'Pay via M-Pesa: ' . $pay : '';
		$out = $tpl;
		foreach ( $vars as $k => $v ) {
			$out = str_replace( '{' . $k . '}', (string) $v, $out );
		}
		$out = preg_replace( '/\{[a-z_]+\}/', '', $out );
		$out = preg_replace( "/[ \t]+\n/", "\n", $out );
		$out = preg_replace( "/\n{3,}/", "\n\n", $out );
		return trim( $out );
	}

	private static function first( $name ) {
		$f = trim( (string) strtok( (string) $name, ' ' ) );
		return '' !== $f ? $f : 'there';
	}

	/** Escalation stage for a plan from days late. */
	public static function plan_stage( $m, $p ) {
		if ( 'request' === $m['health'] ) {
			return 'deposit';
		}
		if ( 'overdue' !== $m['health'] ) {
			return 'due';
		}
		if ( $m['late'] >= 14 ) {
			return 'final';
		}
		if ( $m['late'] >= 7 ) {
			return 'firm';
		}
		return 'late';
	}

	public static function stage_label( $st ) {
		$l = array( 'due' => 'Friendly reminder', 'late' => 'Gentle - late', 'firm' => 'Firm - 7+ days', 'final' => 'Final - 14+ days', 'deposit' => 'Deposit', 'pay' => 'Payment', 'follow' => 'Follow-up', 'appt' => 'Appointment' );
		return isset( $l[ $st ] ) ? $l[ $st ] : $st;
	}

	public static function plan_text( $p, $m ) {
		$stage = self::plan_stage( $m, $p );
		$map   = array( 'due' => 'tpl_plan_due', 'late' => 'tpl_plan_late', 'firm' => 'tpl_plan_firm', 'final' => 'tpl_plan_final', 'deposit' => 'tpl_plan_deposit' );
		$amt   = 'deposit' === $stage ? (float) $p->deposit : ( 'overdue' === $m['health'] ? $m['arrears'] : ( $m['next'] ? $m['next']['amount'] : $m['balance'] ) );
		return self::render( self::tpl( $map[ $stage ] ), array(
			'first'    => self::first( $p->customer_name ),
			'name'     => $p->customer_name,
			'product'  => $p->product_name . ( $p->options ? ' (' . $p->options . ')' : '' ),
			'amount'   => NURAX_Desk::money( $amt ),
			'due'      => $m['next'] ? NURAX_Desk::date_label( $m['next']['due'], 'D j M' ) : '',
			'days'     => (int) $m['late'],
			'paid'     => NURAX_Desk::money( $m['paid'] ),
			'price'    => NURAX_Desk::money( $p->price ),
			'balance'  => NURAX_Desk::money( $m['balance'] ),
			'progress' => (int) $m['progress'],
			'weekly'   => NURAX_Desk::money( $m['weekly'] ),
			'weeks'    => (int) $p->weeks,
			'ref'      => $p->ref,
			'link'     => NURAX_Desk_Plans::status_url( $p ),
		) );
	}

	public static function appt_text( $a ) {
		$today = NURAX_Desk::today();
		$when  = $a->appt_date === $today ? 'today' : ( gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) ) === $a->appt_date ? 'tomorrow' : 'on ' . NURAX_Desk::date_label( $a->appt_date, 'l j F' ) );
		return self::render( self::tpl( 'tpl_appt' ), array(
			'first'   => self::first( $a->customer_name ),
			'name'    => $a->customer_name,
			'service' => NURAX_Desk_Appts::service_label( $a->service ),
			'when'    => $when,
			'date'    => NURAX_Desk::date_label( $a->appt_date, 'l j F' ),
			'time'    => NURAX_Desk::time_label( $a->appt_time ),
			'where'   => 'studio' === $a->type ? ' at ' . NURAX_Desk::shop_address() : ( 'video' === $a->type ? ' by video call' : ' here on WhatsApp' ),
			'ref'     => $a->ref,
		) );
	}

	public static function order_text( $id, $d, $kind ) {
		$items = array();
		foreach ( (array) $d['items'] as $it ) {
			$items[] = ( isset( $it['name'] ) ? $it['name'] : '' ) . ( ! empty( $it['option'] ) ? ' (' . $it['option'] . ')' : '' );
		}
		$total = (float) $d['total'] + ( '' !== (string) $d['ship'] ? (float) $d['ship'] : 0 );
		return self::render( self::tpl( 'pay' === $kind ? 'tpl_order_pay' : 'tpl_order_follow' ), array(
			'first'     => self::first( $d['name'] ),
			'name'      => $d['name'],
			'ref'       => $d['ref'] ? $d['ref'] : '#' . $id,
			'items'     => implode( ', ', $items ),
			'amount'    => NURAX_Desk::money( $total ),
			'next_step' => 'pickup' === $d['fulfil'] ? 'have it ready for you at the studio' : 'send it out to you',
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Tracking                                                            */
	/* ------------------------------------------------------------------ */

	public static function mark( $type, $id, $how = 'WhatsApp' ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		if ( 'plan' === $type || 'appt' === $type ) {
			$t = 'plan' === $type ? NURAX_Desk::t_plans() : NURAX_Desk::t_appts();
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET last_reminded = %s, reminders = reminders + 1 WHERE id = %d", $now, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			NURAX_Desk::log( $t, $id, 'Reminder sent (' . $how . ').' );
		} elseif ( 'order' === $type ) {
			update_post_meta( $id, '_nwo_reminded', $now );
			update_post_meta( $id, '_nwo_reminders', (int) get_post_meta( $id, '_nwo_reminders', true ) + 1 );
			NURAX_Desk_Orders::add_log( $id, 'Reminder sent (' . $how . ').' );
		}
	}

	public static function last_label( $dt, $count ) {
		if ( empty( $dt ) ) {
			return 'Not reminded yet';
		}
		$days = (int) floor( ( strtotime( NURAX_Desk::today() ) - strtotime( substr( $dt, 0, 10 ) ) ) / DAY_IN_SECONDS );
		$when = 0 === $days ? 'today' : ( 1 === $days ? 'yesterday' : $days . ' days ago' );
		return 'Reminded ' . $when . ( $count > 1 ? ' (' . (int) $count . ' times)' : '' );
	}

	public static function api_ready() {
		return class_exists( 'NURAX_WhatsApp' ) && NURAX_WhatsApp::is_configured();
	}

	/* ------------------------------------------------------------------ */
	/* Queue                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Everyone to contact. Each item: type, id, name, phone, title, detail,
	 * amount, stage, last, count, text, url, sort.
	 */
	public static function queue() {
		$out   = array( 'plans' => array(), 'deposits' => array(), 'orders' => array(), 'follow' => array(), 'appts' => array() );
		$today = NURAX_Desk::today();
		$soon  = gmdate( 'Y-m-d', strtotime( $today . ' +2 days' ) );
		$paid  = NURAX_Desk_Plans::paid_map();
		foreach ( NURAX_Desk_Plans::all( array( 'request', 'active' ) ) as $p ) {
			$m = NURAX_Desk_Plans::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
			if ( 'request' === $m['health'] ) {
				if ( substr( (string) $p->created_at, 0, 10 ) >= $today ) {
					continue; // Give new requests a day.
				}
				$out['deposits'][] = self::item( 'plan', $p->id, $p->customer_name, $p->phone, $p->product_name, 'Deposit not paid - requested ' . NURAX_Desk::date_label( $p->created_at, 'j M' ), $p->deposit, 'deposit', $p->last_reminded, $p->reminders, self::plan_text( $p, $m ), NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) ), $p->created_at );
				continue;
			}
			if ( 'overdue' === $m['health'] || ( $m['next'] && $m['next']['due'] <= $soon ) ) {
				$stage  = self::plan_stage( $m, $p );
				$detail = 'overdue' === $m['health'] ? $m['late'] . ' days late - balance ' . NURAX_Desk::money( $m['balance'] ) : 'Due ' . NURAX_Desk::date_label( $m['next']['due'], 'D j M' ) . ' - balance ' . NURAX_Desk::money( $m['balance'] );
				$amt    = 'overdue' === $m['health'] ? $m['arrears'] : $m['next']['amount'];
				$out['plans'][] = self::item( 'plan', $p->id, $p->customer_name, $p->phone, $p->product_name, $detail, $amt, $stage, $p->last_reminded, $p->reminders, self::plan_text( $p, $m ), NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) ), sprintf( '%05d', 99999 - (int) $m['late'] ) );
			}
		}
		// Orders.
		$q = new WP_Query( array(
			'post_type'      => NURAX_Desk_Orders::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 200,
			'date_query'     => array( array( 'after' => gmdate( 'Y-m-d', strtotime( $today . ' -45 days' ) ) ) ),
			'meta_query'     => array( array( 'key' => NURAX_Desk_Orders::STATUS, 'value' => array( 'new', 'confirmed' ), 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( $q->posts as $o ) {
			$d   = NURAX_Desk_Orders::data( $o->ID );
			$st  = NURAX_Desk_Orders::status( $o->ID );
			$pay = NURAX_Desk_Orders::is_paid( $o->ID );
			$age = ( current_time( 'timestamp' ) - strtotime( $o->post_date ) ) / HOUR_IN_SECONDS;
			$last  = get_post_meta( $o->ID, '_nwo_reminded', true );
			$count = (int) get_post_meta( $o->ID, '_nwo_reminders', true );
			$total = (float) $d['total'] + ( '' !== (string) $d['ship'] ? (float) $d['ship'] : 0 );
			$url   = NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'order' => $o->ID ) );
			if ( 'confirmed' === $st && ! $pay ) {
				$out['orders'][] = self::item( 'order', $o->ID, $d['name'], $d['phone'], $d['ref'], 'Confirmed, not paid - ' . ( $d['payment'] ? $d['payment'] : 'payment method not set' ), $total, 'pay', $last, $count, self::order_text( $o->ID, $d, 'pay' ), $url, $o->post_date );
			} elseif ( 'new' === $st && $age >= 6 ) {
				$out['follow'][] = self::item( 'order', $o->ID, $d['name'], $d['phone'], $d['ref'], 'No reply yet - received ' . NURAX_Desk::date_label( $o->post_date, 'D j M, H:i' ), $total, 'follow', $last, $count, self::order_text( $o->ID, $d, 'follow' ), $url, $o->post_date );
			}
		}
		// Appointments today + tomorrow.
		$tm = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		foreach ( NURAX_Desk_Appts::between( $today, $tm, array( 'request', 'confirmed' ) ) as $a ) {
			if ( $a->appt_date === $today && $a->appt_time <= current_time( 'H:i' ) ) {
				continue;
			}
			$out['appts'][] = self::item( 'appt', $a->id, $a->customer_name, $a->phone, NURAX_Desk_Appts::service_label( $a->service ), ( $a->appt_date === $today ? 'Today' : 'Tomorrow' ) . ' at ' . NURAX_Desk::time_label( $a->appt_time ) . ' - ' . NURAX_Desk_Appts::status_label( $a->status ), 0, 'appt', $a->last_reminded, $a->reminders, self::appt_text( $a ), NURAX_Desk::url( NURAX_Desk::P_APPTS, array( 'appt' => $a->id ) ), $a->appt_date . $a->appt_time );
		}
		foreach ( $out as $k => $list ) {
			usort( $list, function ( $x, $y ) {
				if ( $x['done'] !== $y['done'] ) {
					return $x['done'] ? 1 : -1;
				}
				return strcmp( $x['sort'], $y['sort'] );
			} );
			$out[ $k ] = $list;
		}
		return $out;
	}

	private static function item( $type, $id, $name, $phone, $title, $detail, $amount, $stage, $last, $count, $text, $url, $sort ) {
		$done = ! empty( $last ) && substr( (string) $last, 0, 10 ) === NURAX_Desk::today();
		return array(
			'type' => $type, 'id' => (int) $id, 'name' => (string) $name, 'phone' => (string) $phone, 'title' => (string) $title,
			'detail' => $detail, 'amount' => (float) $amount, 'stage' => $stage, 'last' => (string) $last, 'count' => (int) $count,
			'text' => $text, 'wa' => NURAX_Desk::wa( $phone, $text ), 'url' => $url, 'sort' => (string) $sort, 'done' => $done,
		);
	}

	public static function pending_count() {
		$n = get_transient( 'nurax_desk_rem' );
		if ( false === $n ) {
			$n = 0;
			foreach ( self::queue() as $list ) {
				foreach ( $list as $it ) {
					$n += $it['done'] ? 0 : 1;
				}
			}
			set_transient( 'nurax_desk_rem', $n, 10 * MINUTE_IN_SECONDS );
		}
		return (int) $n;
	}

	/* ------------------------------------------------------------------ */
	/* Reminders page                                                      */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		$q     = self::queue();
		$total = 0;
		$done  = 0;
		foreach ( $q as $list ) {
			foreach ( $list as $it ) {
				$total++;
				$done += $it['done'] ? 1 : 0;
			}
		}
		$api = self::api_ready();
		$act = '<button type="button" class="nd-btn nd-btn--wa" data-nd-run' . ( $total - $done ? '' : ' disabled' ) . '>Remind everyone (' . (int) ( $total - $done ) . ')</button>';
		if ( $api ) {
			$act .= '<button type="button" class="nd-btn nd-btn--primary" data-nd-sendall>Send all automatically</button>';
		}
		$act .= NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_SET ) . '#nd-templates', 'Edit messages', 'nd-btn--ghost' );
		NURAX_Desk::header( NURAX_Desk::P_REM, 'Reminders', $act );

		echo '<div class="nd-remhero"><div class="nd-ring nd-ring--sm" style="--p:' . (int) ( $total ? round( $done / $total * 100 ) : 100 ) . '"><div><strong>' . (int) $done . '/' . (int) $total . '</strong><span>today</span></div></div>';
		echo '<div><h2>' . ( $total - $done ? esc_html( ( $total - $done ) . ' people to contact today' ) : 'Everyone has been contacted today' ) . '</h2><p class="nd-mut">Each message is already written for the customer and matches how late she is. Tap <strong>Send</strong> to open WhatsApp, then come back - the list updates by itself. ' . ( $api ? 'WhatsApp Business API is connected, so you can also send automatically.' : 'Connect the WhatsApp Business API under Settings > NURA Experience to send without tapping.' ) . '</p></div></div>';

		$sections = array(
			'plans'    => array( 'Lipa Mdogo Mdogo payments', 'Instalments that are late or due in the next 2 days. The message gets firmer at 7 and 14 days late.' ),
			'deposits' => array( 'Plan deposits not paid', 'Customers who asked for a plan but have not paid the deposit.' ),
			'orders'   => array( 'Confirmed orders not paid', 'Orders you confirmed that are still marked unpaid.' ),
			'follow'   => array( 'Orders with no reply', 'New WhatsApp orders older than 6 hours that are not confirmed yet.' ),
			'appts'    => array( 'Appointments today and tomorrow', 'Reduce no-shows with a reminder the day before.' ),
		);
		foreach ( $sections as $k => $sec ) {
			$list = $q[ $k ];
			echo '<section class="nd-card nd-remsec" data-nd-remsec><header class="nd-card__h"><div><h2>' . esc_html( $sec[0] ) . ' <span class="nd-count">' . count( $list ) . '</span></h2><p class="nd-mut nd-small">' . esc_html( $sec[1] ) . '</p></div></header>';
			if ( ! $list ) {
				echo '<p class="nd-empty-s">Nobody to remind here.</p></section>';
				continue;
			}
			echo '<ul class="nd-remlist">';
			foreach ( $list as $it ) {
				$key = $it['type'] . ':' . $it['id'];
				echo '<li class="nd-rem' . ( $it['done'] ? ' is-done' : '' ) . '" data-nd-remitem="' . esc_attr( $key ) . '">';
				echo '<span class="nd-avatar" aria-hidden="true">' . esc_html( strtoupper( mb_substr( $it['name'] ? $it['name'] : '?', 0, 1 ) ) ) . '</span>';
				echo '<a class="nd-rem__main" href="' . esc_url( $it['url'] ) . '"><strong>' . esc_html( $it['name'] ? $it['name'] : '(no name)' ) . '</strong><span>' . esc_html( $it['title'] ) . '</span><small>' . esc_html( $it['detail'] ) . '</small></a>';
				echo '<span class="nd-rem__meta">' . NURAX_Desk::badge( 'stage-' . $it['stage'], self::stage_label( $it['stage'] ) ) . ( $it['amount'] > 0 ? '<b>' . esc_html( NURAX_Desk::money( $it['amount'] ) ) . '</b>' : '' ) . '<small data-nd-last>' . esc_html( self::last_label( $it['last'], $it['count'] ) ) . '</small></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<span class="nd-rem__acts">';
				echo '<button type="button" class="nd-ico-btn" data-nd-preview="' . esc_attr( $it['text'] ) . '">Preview</button>';
				if ( $it['wa'] ) {
					echo '<a class="nd-btn nd-btn--wa nd-btn--sm" target="_blank" rel="noopener" href="' . esc_url( $it['wa'] ) . '" data-nd-remind="' . esc_attr( $key ) . '">Send</a>';
					if ( $api ) {
						echo '<button type="button" class="nd-ico-btn" data-nd-api="' . esc_attr( $key ) . '">Send automatically</button>';
					}
				} else {
					echo '<span class="nd-mut nd-small">No phone number</span>';
				}
				echo '</span></li>';
			}
			echo '</ul></section>';
		}
		echo '<div class="nd-modal" data-nd-modal hidden><div class="nd-modal__box" role="dialog" aria-modal="true" aria-label="Message preview"><header><strong>Message preview</strong><button type="button" class="nd-modal__x" data-nd-close aria-label="Close">&times;</button></header><pre class="nd-bubble" data-nd-bubble></pre><footer><button type="button" class="nd-btn nd-btn--ghost" data-nd-copytext>Copy text</button><button type="button" class="nd-btn nd-btn--primary" data-nd-close>Close</button></footer></div></div>';
		NURAX_Desk::footer();
	}

	/* ------------------------------------------------------------------ */
	/* Status / delete helpers                                             */
	/* ------------------------------------------------------------------ */

	public static function set_status( $type, $id, $to ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		if ( 'appt' === $type ) {
			if ( ! array_key_exists( $to, NURAX_Desk_Appts::statuses() ) || ! NURAX_Desk_Appts::get( $id ) ) {
				return false;
			}
			$wpdb->update( NURAX_Desk::t_appts(), array( 'status' => $to, 'updated_at' => $now ), array( 'id' => $id ) );
			NURAX_Desk::log( NURAX_Desk::t_appts(), $id, 'Status changed to ' . NURAX_Desk_Appts::status_label( $to ) . '.' );
			return true;
		}
		if ( 'order' === $type ) {
			if ( ! array_key_exists( $to, NURAX_Desk_Orders::statuses() ) || NURAX_Desk_Orders::CPT !== get_post_type( $id ) ) {
				return false;
			}
			update_post_meta( $id, NURAX_Desk_Orders::STATUS, $to );
			NURAX_Desk_Orders::add_log( $id, 'Status changed to ' . NURAX_Desk_Orders::status_label( $to ) . '.' );
			return true;
		}
		if ( 'plan' === $type && 'cancelled' === $to ) {
			$p = NURAX_Desk_Plans::get( $id );
			if ( ! $p || 'cancelled' === $p->status ) {
				return false;
			}
			NURAX_Desk_Plans::unreserve( $id );
			$wpdb->update( NURAX_Desk::t_plans(), array( 'status' => 'cancelled', 'updated_at' => $now ), array( 'id' => $id ) );
			NURAX_Desk::log( NURAX_Desk::t_plans(), $id, 'Plan cancelled.' );
			return true;
		}
		return false;
	}

	public static function delete( $type, $id ) {
		global $wpdb;
		if ( 'plan' === $type ) {
			if ( ! NURAX_Desk_Plans::get( $id ) ) {
				return false;
			}
			NURAX_Desk_Plans::unreserve( $id );
			$wpdb->delete( NURAX_Desk::t_pay(), array( 'plan_id' => $id ) );
			$wpdb->update( NURAX_Desk::t_appts(), array( 'plan_id' => 0 ), array( 'plan_id' => $id ) );
			return (bool) $wpdb->delete( NURAX_Desk::t_plans(), array( 'id' => $id ) );
		}
		if ( 'appt' === $type ) {
			return (bool) $wpdb->delete( NURAX_Desk::t_appts(), array( 'id' => $id ) );
		}
		if ( 'order' === $type && NURAX_Desk_Orders::CPT === get_post_type( $id ) ) {
			return (bool) wp_delete_post( $id, true );
		}
		return false;
	}

	private static function parse_key( $k ) {
		$parts = explode( ':', (string) $k );
		$type  = isset( $parts[0] ) ? sanitize_key( $parts[0] ) : '';
		$id    = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
		return in_array( $type, array( 'plan', 'appt', 'order' ), true ) && $id ? array( $type, $id ) : array( '', 0 );
	}

	/** Reminder text for one record (for API sending). */
	private static function text_for( $type, $id ) {
		if ( 'plan' === $type ) {
			$p = NURAX_Desk_Plans::get( $id );
			return $p ? array( $p->phone, self::plan_text( $p, NURAX_Desk_Plans::metrics( $p, NURAX_Desk_Plans::paid_sum( $id ) ) ) ) : array( '', '' );
		}
		if ( 'appt' === $type ) {
			$a = NURAX_Desk_Appts::get( $id );
			return $a ? array( $a->phone, self::appt_text( $a ) ) : array( '', '' );
		}
		if ( 'order' === $type && NURAX_Desk_Orders::CPT === get_post_type( $id ) ) {
			$d    = NURAX_Desk_Orders::data( $id );
			$kind = ( 'confirmed' === NURAX_Desk_Orders::status( $id ) ) ? 'pay' : 'follow';
			return array( $d['phone'], self::order_text( $id, $d, $kind ) );
		}
		return array( '', '' );
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	public static function ajax() {
		check_ajax_referer( 'nurax_desk', 'nonce' );
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$keys = array();
		if ( isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ) {
			$keys = array_map( 'sanitize_text_field', wp_unslash( $_POST['keys'] ) );
		} elseif ( isset( $_POST['key'] ) ) {
			$keys = array( sanitize_text_field( wp_unslash( $_POST['key'] ) ) );
		}
		$keys = array_slice( $keys, 0, 200 );
		$ok   = 0;
		$fail = 0;
		$msg  = '';
		foreach ( $keys as $k ) {
			list( $type, $id ) = self::parse_key( $k );
			if ( ! $type ) {
				$fail++;
				continue;
			}
			switch ( $op ) {
				case 'remind_log':
					self::mark( $type, $id );
					$ok++;
					break;
				case 'remind_api':
					list( $phone, $text ) = self::text_for( $type, $id );
					$to = NURAX_Desk::phone( $phone );
					if ( self::api_ready() && '' !== $to && '' !== $text && NURAX_WhatsApp::send_text( $to, $text ) ) {
						self::mark( $type, $id, 'sent automatically' );
						$ok++;
					} else {
						$fail++;
						$msg = self::api_ready() && class_exists( 'NURAX_WhatsApp' ) ? NURAX_WhatsApp::last_error() : 'WhatsApp Business API is not connected.';
					}
					break;
				case 'delete':
					self::delete( $type, $id ) ? $ok++ : $fail++;
					break;
				case 'status':
					$to = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
					self::set_status( $type, $id, $to ) ? $ok++ : $fail++;
					break;
				case 'paid':
				case 'unpaid':
					if ( 'order' === $type ) {
						NURAX_Desk_Orders::set_paid( $id, 'paid' === $op, isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '' );
						$ok++;
					} else {
						$fail++;
					}
					break;
				default:
					$fail++;
			}
		}
		NURAX_Desk::bust();
		wp_send_json_success( array( 'ok' => $ok, 'fail' => $fail, 'message' => $msg, 'last' => 'Reminded today' ) );
	}

	/** No-JS fallback for delete (from detail pages). */
	public static function h_delete() {
		NURAX_Desk::guard( 'nurax_desk_delete' );
		list( $type, $id ) = self::parse_key( isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '' );
		$page = array( 'plan' => NURAX_Desk::P_PLANS, 'appt' => NURAX_Desk::P_APPTS, 'order' => NURAX_Desk::P_ORDERS );
		$ok   = $type ? self::delete( $type, $id ) : false;
		NURAX_Desk::bust();
		wp_safe_redirect( NURAX_Desk::url( $type ? $page[ $type ] : NURAX_Desk::MENU, array( 'nd_msg' => $ok ? 'deleted' : 'notfound' ) ) );
		exit;
	}

	public static function h_order_pay() {
		NURAX_Desk::guard( 'nurax_desk_order_pay' );
		$id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$paid = ! empty( $_POST['paid'] );
		if ( $id && NURAX_Desk_Orders::CPT === get_post_type( $id ) ) {
			NURAX_Desk_Orders::set_paid( $id, $paid, isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '' );
		}
		NURAX_Desk::bust();
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_ORDERS, array( 'order' => $id, 'nd_msg' => 'saved' ) ) );
		exit;
	}

	/** Small delete button (JS-enhanced, form fallback). */
	public static function delete_form( $key, $label = 'Delete', $warn = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-nd-confirm="' . esc_attr( $warn ? $warn : 'Delete this permanently? This cannot be undone.' ) . '"><input type="hidden" name="action" value="nurax_desk_delete"><input type="hidden" name="key" value="' . esc_attr( $key ) . '">';
		wp_nonce_field( 'nurax_desk_delete' );
		echo '<button type="submit" class="nd-btn nd-btn--danger-ghost nd-btn--block">' . esc_html( $label ) . '</button></form>';
	}

	/* ------------------------------------------------------------------ */
	/* Automatic reminders (daily cron, needs WhatsApp Business API)       */
	/* ------------------------------------------------------------------ */

	public static function auto() {
		$s = NURAX_Desk::settings();
		if ( empty( $s['auto_wa'] ) || ! self::api_ready() ) {
			return;
		}
		$q    = self::queue();
		$sent = 0;
		foreach ( array( 'plans', 'deposits', 'orders', 'appts' ) as $k ) {
			foreach ( $q[ $k ] as $it ) {
				if ( $it['done'] || '' === NURAX_Desk::phone( $it['phone'] ) || $sent >= 100 ) {
					continue;
				}
				// Do not chase the same person every day: at most every 2 days (appointments: always).
				if ( 'appt' !== $it['type'] && ! empty( $it['last'] ) && strtotime( substr( $it['last'], 0, 10 ) ) > strtotime( NURAX_Desk::today() . ' -2 days' ) ) {
					continue;
				}
				if ( NURAX_WhatsApp::send_text( NURAX_Desk::phone( $it['phone'] ), $it['text'] ) ) {
					self::mark( $it['type'], $it['id'], 'sent automatically' );
					$sent++;
				}
			}
		}
		delete_transient( 'nurax_desk_rem' );
	}
}
