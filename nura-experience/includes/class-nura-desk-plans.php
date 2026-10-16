<?php
/**
 * NURA Desk - Lipa Mdogo Mdogo payment plans (NURA Experience 1.57.0).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Plans {

	public static function hooks() {
		add_action( 'wp_ajax_nurax_lmm_request', array( __CLASS__, 'ajax_request' ) );
		add_action( 'wp_ajax_nopriv_nurax_lmm_request', array( __CLASS__, 'ajax_request' ) );
		add_action( 'wp_ajax_nurax_lmm_price', array( __CLASS__, 'ajax_price' ) );
		add_action( 'admin_post_nurax_lmm_save', array( __CLASS__, 'h_save' ) );
		add_action( 'admin_post_nurax_lmm_pay', array( __CLASS__, 'h_pay' ) );
		add_action( 'admin_post_nurax_lmm_void', array( __CLASS__, 'h_void' ) );
		add_action( 'admin_post_nurax_lmm_status', array( __CLASS__, 'h_status' ) );
		add_action( 'template_redirect', array( __CLASS__, 'statement_page' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Labels                                                              */
	/* ------------------------------------------------------------------ */

	public static function methods() {
		return array(
			'mpesa' => 'M-Pesa',
			'cash'  => 'Cash',
			'bank'  => 'Bank transfer',
			'card'  => 'Card',
			'other' => 'Other',
		);
	}

	public static function method_label( $k ) {
		$m = self::methods();
		return isset( $m[ $k ] ) ? $m[ $k ] : (string) $k;
	}

	public static function health_labels() {
		return array(
			'request'   => 'New request',
			'on_track'  => 'On track',
			'due_soon'  => 'Due soon',
			'overdue'   => 'Overdue',
			'completed' => 'Fully paid',
			'released'  => 'Released',
			'cancelled' => 'Cancelled',
		);
	}

	public static function health_label( $k ) {
		$l = self::health_labels();
		return isset( $l[ $k ] ) ? $l[ $k ] : (string) $k;
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_plans() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function all( $statuses = array() ) {
		global $wpdb;
		$t = NURAX_Desk::t_plans();
		if ( $statuses ) {
			$in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status IN ($in) ORDER BY id DESC LIMIT 5000", $statuses ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return (array) $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function paid_map() {
		global $wpdb;
		$out = array();
		$res = $wpdb->get_results( 'SELECT plan_id, SUM(amount) AS s FROM ' . NURAX_Desk::t_pay() . ' WHERE voided = 0 GROUP BY plan_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $res as $r ) {
			$out[ (int) $r->plan_id ] = (float) $r->s;
		}
		return $out;
	}

	public static function paid_sum( $id ) {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . NURAX_Desk::t_pay() . ' WHERE voided = 0 AND plan_id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function payments( $id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_pay() . ' WHERE plan_id = %d ORDER BY paid_at DESC, id DESC', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function status_url( $p ) {
		return add_query_arg( 'nura_plan', $p->token, home_url( '/' ) );
	}

	public static function for_customer( $user_id, $email, $phone ) {
		global $wpdb;
		$t = NURAX_Desk::t_plans();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status <> 'cancelled' AND ( user_id = %d OR ( email <> '' AND email = %s ) OR ( phone <> '' AND phone = %s ) ) ORDER BY id DESC LIMIT 20", (int) $user_id, (string) $email, (string) $phone ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Schedule, allocation of payments to instalments, and plan health.
	 */
	public static function metrics( $p, $paid ) {
		$s     = NURAX_Desk::settings();
		$today = NURAX_Desk::today();
		$grace = max( 0, (int) $s['lmm_grace'] );
		$price = max( 0, (float) $p->price );
		$dep   = min( $price, max( 0, (float) $p->deposit ) );
		$weeks = max( 1, (int) $p->weeks );
		$start = NURAX_Desk::valid_date( $p->start_date ) ? $p->start_date : substr( (string) $p->created_at, 0, 10 );
		if ( ! NURAX_Desk::valid_date( $start ) ) {
			$start = $today;
		}
		$bal0 = max( 0, $price - $dep );
		$wk   = ceil( $bal0 / $weeks );
		$rows = array(
			array( 'n' => 0, 'label' => 'Deposit', 'due' => $start, 'amount' => $dep ),
		);
		for ( $i = 1; $i <= $weeks; $i++ ) {
			$amt    = $i < $weeks ? $wk : max( 0, $bal0 - $wk * ( $weeks - 1 ) );
			$rows[] = array( 'n' => $i, 'label' => 'Week ' . $i, 'due' => gmdate( 'Y-m-d', strtotime( $start . ' +' . ( 7 * $i ) . ' days' ) ), 'amount' => $amt );
		}
		$left    = (float) $paid;
		$arrears = 0;
		$next    = null;
		$overdue = false;
		$late    = 0;
		$t_today = strtotime( $today );
		foreach ( $rows as $k => $r ) {
			$pd   = min( $r['amount'], max( 0, $left ) );
			$left = $left - $pd;
			$open = $r['amount'] - $pd;
			$rows[ $k ]['paid'] = $pd;
			$rows[ $k ]['open'] = max( 0, $open );
			if ( $open <= 0.009 ) {
				$st = 'paid';
			} else {
				$late_by = (int) floor( ( $t_today - strtotime( $r['due'] ) ) / DAY_IN_SECONDS );
				if ( $late_by > $grace ) {
					$st      = 'overdue';
					$overdue = true;
					$late    = max( $late, $late_by );
				} elseif ( $late_by >= 0 ) {
					$st = 'due';
				} elseif ( $pd > 0 ) {
					$st = 'partial';
				} else {
					$st = 'upcoming';
				}
				if ( $late_by >= 0 ) {
					$arrears += $open;
				}
				if ( null === $next ) {
					$next = array( 'due' => $r['due'], 'amount' => $open, 'label' => $r['label'] );
				}
			}
			$rows[ $k ]['status'] = $st;
		}
		$balance  = max( 0, $price - (float) $paid );
		$progress = $price > 0 ? (int) min( 100, floor( ( (float) $paid / $price ) * 100 ) ) : 0;
		if ( 'cancelled' === $p->status ) {
			$h = 'cancelled';
		} elseif ( 'released' === $p->status ) {
			$h = 'released';
		} elseif ( $balance <= 0.009 && $paid > 0 ) {
			$h = 'completed';
		} elseif ( 'request' === $p->status ) {
			$h = 'request';
		} elseif ( $overdue ) {
			$h = 'overdue';
		} elseif ( $next && strtotime( $next['due'] ) <= strtotime( $today . ' +3 days' ) ) {
			$h = 'due_soon';
		} else {
			$h = 'on_track';
		}
		if ( in_array( $h, array( 'completed', 'released', 'cancelled' ), true ) ) {
			$next    = null;
			$arrears = 0;
			$late    = 0;
		}
		return array(
			'rows'     => $rows,
			'paid'     => (float) $paid,
			'balance'  => $balance,
			'progress' => $progress,
			'arrears'  => $arrears,
			'next'     => $next,
			'late'     => $late,
			'health'   => $h,
			'weekly'   => $wk,
			'end'      => $rows[ count( $rows ) - 1 ]['due'],
		);
	}

	/** Headline numbers for the Overview and the menu badge. */
	public static function kpis() {
		global $wpdb;
		$k     = array( 'active' => 0, 'outstanding' => 0, 'overdue' => 0, 'arrears' => 0, 'due7' => 0, 'due7_count' => 0, 'requests' => 0, 'ready' => 0, 'month' => 0, 'month_count' => 0 );
		$today = NURAX_Desk::today();
		$in7   = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
		$paid  = self::paid_map();
		foreach ( self::all( array( 'request', 'active', 'completed' ) ) as $p ) {
			$m = self::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
			if ( 'request' === $m['health'] ) {
				$k['requests']++;
				continue;
			}
			if ( 'completed' === $m['health'] ) {
				$k['ready']++;
				continue;
			}
			$k['active']++;
			$k['outstanding'] += $m['balance'];
			if ( 'overdue' === $m['health'] ) {
				$k['overdue']++;
				$k['arrears'] += $m['arrears'];
			}
			foreach ( $m['rows'] as $r ) {
				if ( $r['open'] > 0 && $r['due'] <= $in7 && $r['due'] >= $today ) {
					$k['due7'] += $r['open'];
					$k['due7_count']++;
				}
			}
		}
		$from = current_time( 'Y-m-01 00:00:00' );
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) AS s, COUNT(*) AS c FROM ' . NURAX_Desk::t_pay() . ' WHERE voided = 0 AND paid_at >= %s', $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $row ) {
			$k['month']       = (float) $row->s;
			$k['month_count'] = (int) $row->c;
		}
		return $k;
	}

	/* ------------------------------------------------------------------ */
	/* Status sync + stock                                                 */
	/* ------------------------------------------------------------------ */

	public static function sync( $id ) {
		global $wpdb;
		$p = self::get( $id );
		if ( ! $p || in_array( $p->status, array( 'cancelled', 'released' ), true ) ) {
			return;
		}
		$paid = self::paid_sum( $id );
		$bal  = (float) $p->price - $paid;
		$now  = current_time( 'mysql' );
		if ( $paid > 0 && $bal <= 0.009 ) {
			if ( 'completed' !== $p->status ) {
				$wpdb->update( NURAX_Desk::t_plans(), array( 'status' => 'completed', 'completed_at' => $now, 'updated_at' => $now ), array( 'id' => $id ) );
				NURAX_Desk::log( NURAX_Desk::t_plans(), $id, 'Plan fully paid - ready to release the wig.' );
			}
		} elseif ( $paid > 0 && in_array( $p->status, array( 'request', 'completed' ), true ) ) {
			$wpdb->update( NURAX_Desk::t_plans(), array( 'status' => 'active', 'completed_at' => null, 'updated_at' => $now ), array( 'id' => $id ) );
			NURAX_Desk::log( NURAX_Desk::t_plans(), $id, 'request' === $p->status ? 'Deposit received - plan is now active.' : 'Plan re-opened - balance outstanding.' );
		}
		if ( $paid > 0 ) {
			self::reserve( $id );
		}
		NURAX_Desk::bust();
	}

	private static function stock_product( $p ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$prod = wc_get_product( $p->variation_id ? $p->variation_id : $p->product_id );
		return ( $prod && $prod->managing_stock() ) ? $prod : null;
	}

	public static function reserve( $id ) {
		global $wpdb;
		$p = self::get( $id );
		$s = NURAX_Desk::settings();
		if ( ! $p || $p->stock_reserved || empty( $s['lmm_reserve'] ) ) {
			return;
		}
		$prod = self::stock_product( $p );
		if ( ! $prod ) {
			return;
		}
		$new = wc_update_product_stock( $prod, 1, 'decrease' );
		$wpdb->update( NURAX_Desk::t_plans(), array( 'stock_reserved' => 1 ), array( 'id' => $id ) );
		NURAX_Desk::log( NURAX_Desk::t_plans(), $id, 'One unit reserved from stock' . ( false !== $new && null !== $new ? ' (stock now ' . (int) $new . ')' : '' ) . '.' );
	}

	public static function unreserve( $id ) {
		global $wpdb;
		$p = self::get( $id );
		if ( ! $p || ! $p->stock_reserved ) {
			return;
		}
		$prod = self::stock_product( $p );
		if ( $prod ) {
			wc_update_product_stock( $prod, 1, 'increase' );
		}
		$wpdb->update( NURAX_Desk::t_plans(), array( 'stock_reserved' => 0 ), array( 'id' => $id ) );
		NURAX_Desk::log( NURAX_Desk::t_plans(), $id, 'Reserved unit returned to stock.' );
	}

	/* ------------------------------------------------------------------ */
	/* Messages                                                            */
	/* ------------------------------------------------------------------ */

	public static function reminder_text( $p, $m ) {
		$first = trim( strtok( (string) $p->customer_name, ' ' ) );
		$l     = array();
		$l[]   = 'Hi ' . ( $first ? $first : 'there' ) . ', this is NURA Beauty.';
		$l[]   = '';
		if ( 'overdue' === $m['health'] ) {
			$l[] = 'A gentle reminder that your Lipa Mdogo Mdogo plan for the *' . $p->product_name . '* has *' . NURAX_Desk::money( $m['arrears'] ) . '* outstanding.';
		} elseif ( $m['next'] ) {
			$l[] = 'A friendly reminder that your next Lipa Mdogo Mdogo payment of *' . NURAX_Desk::money( $m['next']['amount'] ) . '* for the *' . $p->product_name . '* is due on *' . NURAX_Desk::date_label( $m['next']['due'] ) . '*.';
		}
		$l[] = '';
		$l[] = 'Paid so far: ' . NURAX_Desk::money( $m['paid'] ) . ' of ' . NURAX_Desk::money( $p->price ) . ' (' . $m['progress'] . '%)';
		$l[] = 'Balance: ' . NURAX_Desk::money( $m['balance'] );
		$l[] = 'Plan ref: ' . $p->ref;
		$l[] = '';
		$l[] = 'Your statement: ' . self::status_url( $p );
		$l[] = 'Thank you for choosing NURA.';
		return implode( "\n", $l );
	}

	public static function receipt_text( $p, $m, $pay ) {
		$first = trim( strtok( (string) $p->customer_name, ' ' ) );
		$l     = array();
		$l[]   = '*NURA Beauty - Payment received*';
		$l[]   = '';
		$l[]   = 'Thank you ' . ( $first ? $first : '' ) . '. We have received *' . NURAX_Desk::money( $pay->amount ) . '* (' . self::method_label( $pay->method ) . ( $pay->reference ? ' ' . $pay->reference : '' ) . ') on ' . NURAX_Desk::date_label( $pay->paid_at ) . '.';
		$l[]   = '';
		$l[]   = 'Wig: ' . $p->product_name . ( $p->options ? ' (' . $p->options . ')' : '' );
		$l[]   = 'Paid so far: ' . NURAX_Desk::money( $m['paid'] ) . ' of ' . NURAX_Desk::money( $p->price ) . ' (' . $m['progress'] . '%)';
		$l[]   = 'Balance: ' . NURAX_Desk::money( $m['balance'] );
		if ( $m['balance'] <= 0.009 ) {
			$l[] = '';
			$l[] = 'Your plan is *fully paid*. We will contact you to arrange collection or delivery.';
		} elseif ( $m['next'] ) {
			$l[] = 'Next payment: ' . NURAX_Desk::money( $m['next']['amount'] ) . ' by ' . NURAX_Desk::date_label( $m['next']['due'] );
		}
		$l[] = 'Plan ref: ' . $p->ref;
		$l[] = '';
		$l[] = 'Your statement: ' . self::status_url( $p );
		return implode( "\n", $l );
	}

	/* ------------------------------------------------------------------ */
	/* Website requests (from the theme pop-up)                            */
	/* ------------------------------------------------------------------ */

	public static function ajax_request() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form on cached pages; honeypot + rate limit.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'ok' => 1 ) );
		}
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'nurax_lmmr_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 8 ) {
			wp_send_json_error( array( 'message' => 'rate' ), 429 );
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		if ( ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => 'wc' ), 400 );
		}
		$pid  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$vid  = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$prod = $pid ? wc_get_product( $pid ) : null;
		if ( ! $prod ) {
			wp_send_json_error( array( 'message' => 'product' ), 400 );
		}
		$price = $prod->is_type( 'variable' ) ? (float) $prod->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $prod );
		if ( $vid ) {
			$v = wc_get_product( $vid );
			if ( $v && (int) $v->get_parent_id() === $pid ) {
				$price = (float) wc_get_price_to_display( $v );
			} else {
				$vid = 0;
			}
		}
		$s = NURAX_Desk::settings();
		if ( $price <= 0 || $price < (float) $s['lmm_min'] ) {
			wp_send_json_error( array( 'message' => 'price' ), 400 );
		}
		$weeks = isset( $_POST['weeks'] ) ? absint( $_POST['weeks'] ) : 0;
		$opts  = NURAX_Desk::week_options();
		if ( ! in_array( $weeks, $opts, true ) ) {
			$weeks = max( $opts );
		}
		$t   = NURAX_Desk::t_plans();
		$ref = isset( $_POST['ref'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ) ) ) : '';
		if ( ! preg_match( '/^LMM-\d{6}-[A-Z0-9]{4}$/', $ref ) ) {
			$ref = NURAX_Desk::new_ref( 'LMM', $t );
		} elseif ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ref = %s", $ref ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			wp_send_json_success( array( 'ref' => $ref ) ); // Same request sent twice.
		}
		$name   = isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 120 ) : '';
		$phone  = isset( $_POST['phone'] ) ? NURAX_Desk::phone( wp_unslash( $_POST['phone'] ) ) : '';
		$opt    = isset( $_POST['options'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['options'] ) ), 0, 255 ) : '';
		$fulfil = isset( $_POST['fulfil'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['fulfil'] ) ), 0, 120 ) : '';
		// phpcs:enable
		$now = current_time( 'mysql' );
		$u   = wp_get_current_user();
		$wpdb->insert( $t, array(
			'ref'           => $ref,
			'token'         => wp_generate_password( 32, false, false ),
			'status'        => 'request',
			'customer_name' => $name,
			'phone'         => $phone,
			'email'         => ( $u && $u->ID ) ? $u->user_email : '',
			'user_id'       => ( $u && $u->ID ) ? (int) $u->ID : 0,
			'product_id'    => $pid,
			'variation_id'  => $vid,
			'product_name'  => $prod->get_name(),
			'options'       => $opt,
			'price'         => $price,
			'deposit'       => ceil( $price * (int) $s['lmm_deposit'] / 100 ),
			'weeks'         => $weeks,
			'start_date'    => NURAX_Desk::today(),
			'fulfil'        => $fulfil,
			'notes'         => '',
			'source'        => 'website',
			'log'           => wp_json_encode( array( array( 't' => $now, 'u' => 'Website', 'm' => 'Plan requested from the product page.' ) ) ),
			'created_at'    => $now,
			'updated_at'    => $now,
		) );
		NURAX_Desk::bust();
		wp_send_json_success( array( 'ref' => $ref ) );
	}

	public static function ajax_price() {
		check_ajax_referer( 'nurax_lmm_price', 'nonce' );
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			wp_send_json_error( array(), 403 );
		}
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$prod = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $prod ) {
			wp_send_json_error( array(), 404 );
		}
		$price = $prod->is_type( 'variable' ) ? (float) $prod->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $prod );
		$opts  = '';
		if ( $prod->is_type( 'variation' ) ) {
			$opts = wc_get_formatted_variation( $prod, true, false, false );
		}
		$name = $prod->is_type( 'variation' ) ? get_the_title( $prod->get_parent_id() ) : $prod->get_name();
		wp_send_json_success( array( 'price' => $price, 'name' => $name, 'options' => wp_strip_all_tags( (string) $opts ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Statement page for the customer: /?nura_plan=TOKEN                  */
	/* ------------------------------------------------------------------ */

	public static function statement_page() {
		if ( empty( $_GET['nura_plan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		global $wpdb;
		$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['nura_plan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( strlen( $tok ) < 20 ) {
			return;
		}
		$p = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_plans() . ' WHERE token = %s', $tok ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $p ) {
			return;
		}
		$m    = self::metrics( $p, self::paid_sum( $p->id ) );
		$pays = array_filter( self::payments( $p->id ), function ( $y ) {
			return ! $y->voided;
		} );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'pre_get_document_title', function () {
			return 'Your Lipa Mdogo Mdogo statement - NURA Beauty';
		} );
		get_header();
		$img = $p->product_id ? get_the_post_thumbnail_url( $p->product_id, 'woocommerce_thumbnail' ) : '';
		?>
		<main id="primary" class="site-main nlmm-pub">
			<style>
				.nlmm-pub{padding:48px 16px 72px;background:#faf7f2}
				.nlmm-pub__card{max-width:720px;margin:0 auto;background:#fff;border:1px solid #ece6dc;border-radius:20px;overflow:hidden;box-shadow:0 20px 50px rgba(14,14,14,.06)}
				.nlmm-pub__head{padding:28px;background:linear-gradient(135deg,#0E0E0E,#2a1f14);color:#fff}
				.nlmm-pub__eyebrow{font-family:var(--font-ui,inherit);font-size:.7rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#E4C983}
				.nlmm-pub__head h1{margin:.4rem 0 .2rem;color:#fff;font-size:1.7rem}
				.nlmm-pub__head p{margin:0;color:rgba(255,255,255,.75);font-size:.9rem}
				.nlmm-pub__body{padding:24px 28px 28px}
				.nlmm-pub__prod{display:flex;gap:14px;align-items:center;margin-bottom:20px}
				.nlmm-pub__prod img{width:72px;height:72px;border-radius:12px;object-fit:cover;border:1px solid #ece6dc}
				.nlmm-pub__bar{height:12px;border-radius:99px;background:#efe9df;overflow:hidden;margin:6px 0 8px}
				.nlmm-pub__bar span{display:block;height:100%;background:linear-gradient(90deg,#C9A24B,#E4C983)}
				.nlmm-pub__nums{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0 22px}
				.nlmm-pub__nums div{padding:12px;border-radius:12px;background:#faf7f2;text-align:center}
				.nlmm-pub__nums small{display:block;color:#6f675d;font-size:.72rem;text-transform:uppercase;letter-spacing:.1em}
				.nlmm-pub__nums strong{font-size:1.1rem}
				.nlmm-pub table{width:100%;border-collapse:collapse;font-size:.9rem;margin-bottom:20px}
				.nlmm-pub th,.nlmm-pub td{padding:10px 8px;border-bottom:1px solid #f0ebe3;text-align:left}
				.nlmm-pub td:last-child,.nlmm-pub th:last-child{text-align:right}
				.nlmm-pub .is-paid{color:#1f8a4c;font-weight:600}.nlmm-pub .is-overdue{color:#b3261e;font-weight:600}
				.nlmm-pub h2{font-size:1rem;font-family:var(--font-ui,inherit);letter-spacing:.12em;text-transform:uppercase;margin:6px 0 10px}
				@media(max-width:560px){.nlmm-pub__nums{grid-template-columns:1fr}.nlmm-pub__body{padding:20px}}
			</style>
			<div class="nlmm-pub__card">
				<div class="nlmm-pub__head">
					<span class="nlmm-pub__eyebrow">Lipa Mdogo Mdogo statement</span>
					<h1><?php echo esc_html( $p->customer_name ? $p->customer_name : 'Your plan' ); ?></h1>
					<p><?php echo esc_html( 'Plan ' . $p->ref . ' - ' . self::health_label( $m['health'] ) . ' - updated ' . NURAX_Desk::date_label( NURAX_Desk::today() ) ); ?></p>
				</div>
				<div class="nlmm-pub__body">
					<div class="nlmm-pub__prod">
						<?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?>
						<div><strong><?php echo esc_html( $p->product_name ); ?></strong><?php echo $p->options ? '<br><small>' . esc_html( $p->options ) . '</small>' : ''; ?><br><small><?php echo esc_html( NURAX_Desk::money( $p->price ) . ' over ' . (int) $p->weeks . ' weeks, no interest' ); ?></small></div>
					</div>
					<div class="nlmm-pub__bar"><span style="width:<?php echo (int) $m['progress']; ?>%"></span></div>
					<small><?php echo esc_html( $m['progress'] . '% paid' ); ?></small>
					<div class="nlmm-pub__nums">
						<div><small>Paid</small><strong><?php echo esc_html( NURAX_Desk::money( $m['paid'] ) ); ?></strong></div>
						<div><small>Balance</small><strong><?php echo esc_html( NURAX_Desk::money( $m['balance'] ) ); ?></strong></div>
						<div><small>Next payment</small><strong><?php echo esc_html( $m['next'] ? NURAX_Desk::money( $m['next']['amount'] ) : '-' ); ?></strong><?php echo $m['next'] ? '<small>' . esc_html( NURAX_Desk::date_label( $m['next']['due'] ) ) . '</small>' : ''; ?></div>
					</div>
					<h2>Schedule</h2>
					<table><thead><tr><th>Payment</th><th>Due</th><th>Status</th><th>Amount</th></tr></thead><tbody>
					<?php foreach ( $m['rows'] as $r ) : ?>
						<tr><td><?php echo esc_html( $r['label'] ); ?></td><td><?php echo esc_html( NURAX_Desk::date_label( $r['due'] ) ); ?></td><td class="is-<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( ucfirst( $r['status'] ) ); ?></td><td><?php echo esc_html( NURAX_Desk::money( $r['amount'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
					<?php if ( $pays ) : ?>
						<h2>Payments received</h2>
						<table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead><tbody>
						<?php foreach ( $pays as $y ) : ?>
							<tr><td><?php echo esc_html( NURAX_Desk::date_label( $y->paid_at ) ); ?></td><td><?php echo esc_html( self::method_label( $y->method ) ); ?></td><td><?php echo esc_html( $y->reference ); ?></td><td><?php echo esc_html( NURAX_Desk::money( $y->amount ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
					<p style="font-size:.85rem;color:#6f675d;margin:0">Questions about your plan? Message us on WhatsApp and quote <strong><?php echo esc_html( $p->ref ); ?></strong>.</p>
				</div>
			</div>
		</main>
		<?php
		get_footer();
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Admin page router                                                   */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['plan'] ) ? absint( $_GET['plan'] ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable
		if ( 'new' === $action || ( 'edit' === $action && $id ) ) {
			self::form( $id );
		} elseif ( $id ) {
			self::view( $id );
		} else {
			self::listing();
		}
	}

	/* ------------------------------------------------------------------ */
	/* List                                                                */
	/* ------------------------------------------------------------------ */

	private static function listing() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'open';
		$q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$paid  = self::paid_map();
		$today = NURAX_Desk::today();
		$in7   = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
		$rows  = array();
		$count = array( 'open' => 0, 'request' => 0, 'overdue' => 0, 'due_week' => 0, 'completed' => 0, 'released' => 0, 'cancelled' => 0, 'all' => 0 );
		foreach ( self::all() as $p ) {
			if ( '' !== $q ) {
				$hay = strtolower( $p->ref . ' ' . $p->customer_name . ' ' . $p->phone . ' ' . $p->product_name . ' ' . $p->email );
				if ( false === strpos( $hay, strtolower( $q ) ) && false === strpos( $hay, NURAX_Desk::phone( $q ) ? NURAX_Desk::phone( $q ) : '~~' ) ) {
					continue;
				}
			}
			$m    = self::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
			$tabs = array( 'all' );
			if ( in_array( $m['health'], array( 'on_track', 'due_soon', 'overdue' ), true ) ) {
				$tabs[] = 'open';
			}
			if ( 'request' === $m['health'] ) {
				$tabs[] = 'request';
			}
			if ( 'overdue' === $m['health'] ) {
				$tabs[] = 'overdue';
			}
			if ( $m['next'] && $m['next']['due'] <= $in7 ) {
				$tabs[] = 'due_week';
			}
			if ( in_array( $m['health'], array( 'completed', 'released', 'cancelled' ), true ) ) {
				$tabs[] = $m['health'];
			}
			foreach ( $tabs as $tb ) {
				$count[ $tb ]++;
			}
			if ( in_array( $tab, $tabs, true ) ) {
				$rows[] = array( $p, $m );
			}
		}
		if ( in_array( $tab, array( 'open', 'overdue', 'due_week' ), true ) ) {
			usort( $rows, function ( $a, $b ) {
				$da = $a[1]['next'] ? $a[1]['next']['due'] : '9999';
				$db = $b[1]['next'] ? $b[1]['next']['due'] : '9999';
				return strcmp( $da, $db );
			} );
		}
		$total = count( $rows );
		$per   = 25;
		$rows  = array_slice( $rows, ( $paged - 1 ) * $per, $per );

		$act = NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'action' => 'new' ) ), '+ New payment plan', 'nd-btn--primary' ) .
			NURAX_Desk::btn( NURAX_Desk::export_url( 'plans' ), 'Export CSV', 'nd-btn--ghost' );
		NURAX_Desk::header( NURAX_Desk::P_PLANS, 'Lipa Mdogo Mdogo plans', $act );

		$k = self::kpis();
		echo '<div class="nd-kpis nd-kpis--4">';
		NURAX_Desk::kpi( 'Outstanding', NURAX_Desk::money( $k['outstanding'] ), $k['active'] . ' active plans' );
		NURAX_Desk::kpi( 'Overdue', NURAX_Desk::money( $k['arrears'] ), $k['overdue'] . ' plans behind', $k['overdue'] ? 'red' : '' );
		NURAX_Desk::kpi( 'Due next 7 days', NURAX_Desk::money( $k['due7'] ), $k['due7_count'] . ' instalments' );
		NURAX_Desk::kpi( 'Collected this month', NURAX_Desk::money( $k['month'] ), $k['month_count'] . ' payments', 'gold' );
		echo '</div>';

		$labels = array( 'open' => 'In progress', 'request' => 'New requests', 'overdue' => 'Overdue', 'due_week' => 'Due this week', 'completed' => 'Fully paid', 'released' => 'Released', 'cancelled' => 'Cancelled', 'all' => 'All' );
		echo '<div class="nd-toolbar"><nav class="nd-pills">';
		foreach ( $labels as $kk => $lbl ) {
			echo '<a class="nd-pill' . ( $kk === $tab ? ' is-on' : '' ) . ( 'overdue' === $kk && $count[ $kk ] ? ' nd-pill--alert' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'tab' => $kk, 's' => $q ) ) ) . '">' . esc_html( $lbl ) . ' <span>' . (int) $count[ $kk ] . '</span></a>';
		}
		echo '</nav><form method="get" class="nd-search"><input type="hidden" name="page" value="' . esc_attr( NURAX_Desk::P_PLANS ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="search" name="s" value="' . esc_attr( $q ) . '" placeholder="Search name, phone, ref or wig"><button class="nd-btn nd-btn--ghost" type="submit">Search</button></form></div>';

		if ( ! $rows ) {
			echo '<div class="nd-empty"><strong>Nothing here yet.</strong><p>Plans requested on the website appear under "New requests". You can also add a plan yourself.</p>' . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'action' => 'new' ) ), '+ New payment plan', 'nd-btn--primary' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			NURAX_Desk::footer();
			return;
		}

		echo '<div class="nd-table-wrap"><table class="nd-table"><thead><tr><th>Plan</th><th>Customer</th><th>Wig</th><th class="nd-col-prog">Progress</th><th>Balance</th><th>Next payment</th><th>Status</th><th class="nd-col-act"></th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			list( $p, $m ) = $row;
			$view = NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) );
			$wa   = NURAX_Desk::wa( $p->phone, self::reminder_text( $p, $m ) );
			echo '<tr class="is-' . esc_attr( $m['health'] ) . '">';
			echo '<td><a class="nd-ref" href="' . esc_url( $view ) . '">' . esc_html( $p->ref ) . '</a><small>' . esc_html( NURAX_Desk::date_label( $p->created_at, 'j M Y' ) . ( 'website' === $p->source ? ' - website' : '' ) ) . '</small></td>';
			echo '<td><strong>' . esc_html( $p->customer_name ? $p->customer_name : '(no name yet)' ) . '</strong><small>' . esc_html( NURAX_Desk::phone_label( $p->phone ) ) . '</small></td>';
			echo '<td><span class="nd-clip">' . esc_html( $p->product_name ) . '</span><small>' . esc_html( $p->options ) . '</small></td>';
			echo '<td class="nd-col-prog"><div class="nd-prog"><span style="width:' . (int) $m['progress'] . '%"></span></div><small>' . esc_html( NURAX_Desk::money( $m['paid'] ) . ' of ' . NURAX_Desk::money( $p->price ) . ' - ' . $m['progress'] . '%' ) . '</small></td>';
			echo '<td><strong>' . esc_html( NURAX_Desk::money( $m['balance'] ) ) . '</strong>' . ( $m['arrears'] > 0 ? '<small class="nd-red">' . esc_html( NURAX_Desk::money( $m['arrears'] ) . ' behind' ) . '</small>' : '' ) . '</td>';
			echo '<td>' . ( $m['next'] ? '<strong>' . esc_html( NURAX_Desk::money( $m['next']['amount'] ) ) . '</strong><small' . ( $m['late'] ? ' class="nd-red"' : '' ) . '>' . esc_html( NURAX_Desk::date_label( $m['next']['due'], 'D j M' ) . ( $m['late'] ? ' - ' . $m['late'] . ' days late' : '' ) ) . '</small>' : '<span class="nd-mut">-</span>' ) . '</td>';
			echo '<td>' . NURAX_Desk::badge( $m['health'], self::health_label( $m['health'] ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="nd-col-act"><a class="nd-ico-btn" href="' . esc_url( $view . '#nd-pay' ) . '" title="Record payment">+ Pay</a>' . ( $wa && $m['next'] ? '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '" title="Send WhatsApp reminder">Remind</a>' : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		self::pager( $total, $per, $paged, array( 'tab' => $tab, 's' => $q ) );
		NURAX_Desk::footer();
	}

	public static function pager( $total, $per, $paged, $args, $page = '' ) {
		$pages = (int) ceil( $total / $per );
		if ( $pages < 2 ) {
			return;
		}
		$page = $page ? $page : NURAX_Desk::P_PLANS;
		echo '<nav class="nd-pager">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			echo '<a class="' . ( $i === $paged ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( $page, array_merge( $args, array( 'paged' => $i ) ) ) ) . '">' . (int) $i . '</a>';
		}
		echo '</nav>';
	}

	/* ------------------------------------------------------------------ */
	/* Single plan                                                         */
	/* ------------------------------------------------------------------ */

	private static function view( $id ) {
		$p = self::get( $id );
		if ( ! $p ) {
			NURAX_Desk::header( NURAX_Desk::P_PLANS, 'Plan not found' );
			echo '<p>' . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_PLANS ), 'Back to plans', 'nd-btn--ghost' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			NURAX_Desk::footer();
			return;
		}
		$m     = self::metrics( $p, self::paid_sum( $id ) );
		$pays  = self::payments( $id );
		$wa    = NURAX_Desk::wa( $p->phone, self::reminder_text( $p, $m ) );
		$back  = NURAX_Desk::url( NURAX_Desk::P_PLANS );
		$open  = ! in_array( $p->status, array( 'cancelled', 'released' ), true );
		$act   = NURAX_Desk::btn( $back, 'All plans', 'nd-btn--ghost' );
		if ( $wa && $open && $m['next'] ) {
			$act .= NURAX_Desk::btn( $wa, 'WhatsApp reminder', 'nd-btn--wa', 'target="_blank" rel="noopener"' );
		}
		$act .= '<button type="button" class="nd-btn nd-btn--ghost" data-nd-copy="' . esc_attr( self::status_url( $p ) ) . '">Copy statement link</button>';
		$act .= '<button type="button" class="nd-btn nd-btn--ghost" data-nd-print>Print</button>';
		$act .= NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $id, 'action' => 'edit' ) ), 'Edit', 'nd-btn--ghost' );
		NURAX_Desk::header( NURAX_Desk::P_PLANS, $p->ref, $act );

		// Receipt prompt right after a payment.
		$pid = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $pid ) {
			foreach ( $pays as $y ) {
				if ( (int) $y->id === $pid && ! $y->voided ) {
					$rw = NURAX_Desk::wa( $p->phone, self::receipt_text( $p, $m, $y ) );
					echo '<div class="nd-receipt"><div><strong>' . esc_html( NURAX_Desk::money( $y->amount ) ) . ' recorded.</strong> Send the customer a receipt with her new balance.</div>' . ( $rw ? NURAX_Desk::btn( $rw, 'Send WhatsApp receipt', 'nd-btn--wa', 'target="_blank" rel="noopener"' ) : '<span class="nd-mut">Add a phone number to send receipts.</span>' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}

		echo '<div class="nd-print-head"><strong>NURA Beauty</strong> - Lipa Mdogo Mdogo statement - ' . esc_html( $p->ref ) . ' - ' . esc_html( NURAX_Desk::date_label( NURAX_Desk::today() ) ) . '</div>';
		echo '<div class="nd-plan">';

		// Left column.
		echo '<div class="nd-plan__main">';
		echo '<section class="nd-card nd-hero">';
		echo '<div class="nd-ring" style="--p:' . (int) $m['progress'] . '"><div><strong>' . (int) $m['progress'] . '%</strong><span>paid</span></div></div>';
		echo '<div class="nd-hero__txt"><div class="nd-hero__badges">' . NURAX_Desk::badge( $m['health'], self::health_label( $m['health'] ) ) . ( $p->stock_reserved ? NURAX_Desk::badge( 'muted', 'Stock reserved' ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<h2>' . esc_html( $p->product_name ) . '</h2><p class="nd-mut">' . esc_html( trim( $p->options . ( $p->options ? ' - ' : '' ) . NURAX_Desk::money( $p->price ) . ' over ' . (int) $p->weeks . ' weeks, started ' . NURAX_Desk::date_label( $p->start_date ) ) ) . '</p>';
		echo '<dl class="nd-stats">';
		echo '<div><dt>Paid</dt><dd>' . esc_html( NURAX_Desk::money( $m['paid'] ) ) . '</dd></div>';
		echo '<div><dt>Balance</dt><dd>' . esc_html( NURAX_Desk::money( $m['balance'] ) ) . '</dd></div>';
		echo '<div><dt>Behind</dt><dd class="' . ( $m['arrears'] > 0 ? 'nd-red' : '' ) . '">' . esc_html( NURAX_Desk::money( $m['arrears'] ) ) . '</dd></div>';
		echo '<div><dt>Next payment</dt><dd>' . esc_html( $m['next'] ? NURAX_Desk::money( $m['next']['amount'] ) . ' - ' . NURAX_Desk::date_label( $m['next']['due'], 'D j M' ) : '-' ) . '</dd></div>';
		echo '</dl></div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Payment schedule</h2><span class="nd-mut">Ends ' . esc_html( NURAX_Desk::date_label( $m['end'] ) ) . '</span></header>';
		echo '<ol class="nd-sched">';
		foreach ( $m['rows'] as $r ) {
			echo '<li class="is-' . esc_attr( $r['status'] ) . '"><span class="nd-sched__dot"></span><span class="nd-sched__l"><strong>' . esc_html( $r['label'] ) . '</strong><small>' . esc_html( NURAX_Desk::date_label( $r['due'] ) ) . '</small></span>';
			echo '<span class="nd-sched__s">' . esc_html( ucfirst( $r['status'] ) ) . ( $r['paid'] > 0 && $r['open'] > 0 ? '<small>' . esc_html( NURAX_Desk::money( $r['paid'] ) . ' paid' ) . '</small>' : '' ) . '</span>';
			echo '<span class="nd-sched__a">' . esc_html( NURAX_Desk::money( $r['amount'] ) ) . '</span></li>';
		}
		echo '</ol></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Payments ledger</h2><span class="nd-mut">' . count( array_filter( $pays, function ( $y ) {
			return ! $y->voided;
		} ) ) . ' payments</span></header>';
		if ( ! $pays ) {
			echo '<p class="nd-mut">No payments yet. Record the deposit once the customer pays.</p>';
		} else {
			echo '<table class="nd-table nd-table--flat"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Note</th><th>Amount</th><th class="nd-noprint"></th></tr></thead><tbody>';
			foreach ( $pays as $y ) {
				$rec = $y->recorded_by ? get_userdata( $y->recorded_by ) : null;
				echo '<tr class="' . ( $y->voided ? 'is-void' : '' ) . '"><td>' . esc_html( NURAX_Desk::date_label( $y->paid_at, 'D j M Y' ) ) . '<small>' . esc_html( $rec ? 'by ' . $rec->display_name : '' ) . '</small></td><td>' . esc_html( self::method_label( $y->method ) ) . '</td><td><code>' . esc_html( $y->reference ) . '</code></td><td>' . esc_html( $y->voided ? 'VOID: ' . $y->void_reason : $y->note ) . '</td><td><strong>' . esc_html( NURAX_Desk::money( $y->amount ) ) . '</strong></td><td class="nd-noprint">';
				if ( ! $y->voided && $open ) {
					$rw = NURAX_Desk::wa( $p->phone, self::receipt_text( $p, $m, $y ) );
					if ( $rw ) {
						echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( $rw ) . '">Receipt</a>';
					}
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inline" data-nd-void><input type="hidden" name="action" value="nurax_lmm_void"><input type="hidden" name="payment_id" value="' . (int) $y->id . '"><input type="hidden" name="reason" value="">';
					wp_nonce_field( 'nurax_lmm_void' );
					echo '<button type="submit" class="nd-ico-btn nd-ico-btn--danger">Void</button></form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
		echo '</div>';

		// Right column.
		echo '<aside class="nd-plan__side">';
		if ( $open && $m['balance'] > 0 ) {
			$sug = $m['next'] ? $m['next']['amount'] : $m['balance'];
			echo '<section class="nd-card nd-card--accent" id="nd-pay"><header class="nd-card__h"><h2>Record a payment</h2></header>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form nd-form--tight"><input type="hidden" name="action" value="nurax_lmm_pay"><input type="hidden" name="plan_id" value="' . (int) $id . '">';
			wp_nonce_field( 'nurax_lmm_pay' );
			echo '<label class="nd-field"><span class="nd-label">Amount (KSh)</span><input type="number" name="amount" min="1" step="1" max="' . esc_attr( ceil( $m['balance'] ) ) . '" value="' . esc_attr( ceil( $sug ) ) . '" required class="nd-big"></label>';
			echo '<div class="nd-quick">';
			if ( $m['next'] ) {
				echo '<button type="button" data-nd-amt="' . esc_attr( ceil( $m['next']['amount'] ) ) . '">' . esc_html( $m['next']['label'] ) . ' ' . esc_html( NURAX_Desk::money( $m['next']['amount'] ) ) . '</button>';
			}
			if ( $m['arrears'] > 0 && abs( $m['arrears'] - ( $m['next'] ? $m['next']['amount'] : 0 ) ) > 1 ) {
				echo '<button type="button" data-nd-amt="' . esc_attr( ceil( $m['arrears'] ) ) . '">All behind ' . esc_html( NURAX_Desk::money( $m['arrears'] ) ) . '</button>';
			}
			echo '<button type="button" data-nd-amt="' . esc_attr( ceil( $m['balance'] ) ) . '">Clear balance ' . esc_html( NURAX_Desk::money( $m['balance'] ) ) . '</button></div>';
			echo '<label class="nd-field"><span class="nd-label">Method</span><select name="method">';
			foreach ( self::methods() as $mk => $ml ) {
				echo '<option value="' . esc_attr( $mk ) . '">' . esc_html( $ml ) . '</option>';
			}
			echo '</select></label>';
			echo '<label class="nd-field"><span class="nd-label">M-Pesa code / reference</span><input type="text" name="reference" maxlength="60" placeholder="e.g. SJK4X2PQ7Z" style="text-transform:uppercase"></label>';
			echo '<label class="nd-field"><span class="nd-label">Date received</span><input type="date" name="paid_on" value="' . esc_attr( NURAX_Desk::today() ) . '" max="' . esc_attr( NURAX_Desk::today() ) . '"></label>';
			echo '<label class="nd-field"><span class="nd-label">Note (optional)</span><input type="text" name="note" maxlength="200"></label>';
			if ( is_email( $p->email ) ) {
				echo '<label class="nd-field nd-field--check"><input type="checkbox" name="email_receipt" value="1" checked><span>E-mail a receipt to ' . esc_html( $p->email ) . '</span></label>';
			}
			echo '<button type="submit" class="nd-btn nd-btn--primary nd-btn--block">Record payment</button></form></section>';
		}

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Customer</h2></header><dl class="nd-dl">';
		echo '<div><dt>Name</dt><dd>' . esc_html( $p->customer_name ? $p->customer_name : '-' ) . '</dd></div>';
		echo '<div><dt>Phone</dt><dd>' . ( $p->phone ? '<a href="' . esc_url( 'https://wa.me/' . $p->phone ) . '" target="_blank" rel="noopener">' . esc_html( NURAX_Desk::phone_label( $p->phone ) ) . '</a>' : '-' ) . '</dd></div>';
		echo '<div><dt>E-mail</dt><dd>' . esc_html( $p->email ? $p->email : '-' ) . '</dd></div>';
		echo '<div><dt>Collection</dt><dd>' . esc_html( $p->fulfil ? $p->fulfil : '-' ) . '</dd></div>';
		if ( $p->wc_order_id ) {
			echo '<div><dt>Order</dt><dd><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $p->wc_order_id . '&action=edit' ) ) . '">#' . (int) $p->wc_order_id . '</a></dd></div>';
		}
		if ( $p->product_id ) {
			echo '<div><dt>Product</dt><dd><a href="' . esc_url( get_edit_post_link( $p->product_id ) ) . '">Edit product</a> &middot; <a href="' . esc_url( get_permalink( $p->product_id ) ) . '" target="_blank" rel="noopener">View</a></dd></div>';
		}
		echo '</dl>';
		if ( $p->notes ) {
			echo '<p class="nd-note">' . nl2br( esc_html( $p->notes ) ) . '</p>';
		}
		echo '</section>';

		echo '<section class="nd-card nd-noprint"><header class="nd-card__h"><h2>Plan status</h2></header><div class="nd-stack">';
		$st = function ( $to, $label, $cls, $confirm = '' ) use ( $id ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' data-nd-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '><input type="hidden" name="action" value="nurax_lmm_status"><input type="hidden" name="plan_id" value="' . (int) $id . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
			wp_nonce_field( 'nurax_lmm_status' );
			echo '<button type="submit" class="nd-btn ' . esc_attr( $cls ) . ' nd-btn--block">' . esc_html( $label ) . '</button></form>';
		};
		if ( 'completed' === $p->status ) {
			$st( 'released', 'Mark wig released to customer', 'nd-btn--primary' );
		}
		if ( 'request' === $p->status ) {
			echo '<p class="nd-mut">Recording the deposit activates the plan automatically.</p>';
		}
		if ( $open ) {
			$st( 'cancelled', 'Cancel plan', 'nd-btn--danger-ghost', 'Cancel this plan? Any reserved stock will be returned. Payments stay in the ledger for your records.' );
		} else {
			$st( 'active', 'Re-open plan', 'nd-btn--ghost' );
		}
		echo '</div></section>';

		$log = NURAX_Desk::log_rows( $p->log );
		if ( $log ) {
			echo '<section class="nd-card nd-noprint"><header class="nd-card__h"><h2>Activity</h2></header><ol class="nd-log">';
			foreach ( array_slice( $log, 0, 25 ) as $e ) {
				echo '<li><span>' . esc_html( isset( $e['m'] ) ? $e['m'] : '' ) . '</span><small>' . esc_html( ( isset( $e['t'] ) ? NURAX_Desk::date_label( $e['t'], 'j M, H:i' ) : '' ) . ' - ' . ( isset( $e['u'] ) ? $e['u'] : '' ) ) . '</small></li>';
			}
			echo '</ol></section>';
		}
		echo '</aside></div>';
		NURAX_Desk::footer();
	}

	/* ------------------------------------------------------------------ */
	/* New / edit                                                          */
	/* ------------------------------------------------------------------ */

	private static function form( $id ) {
		$p = $id ? self::get( $id ) : null;
		$s = NURAX_Desk::settings();
		$v = array(
			'customer_name' => $p ? $p->customer_name : '',
			'phone'         => $p ? $p->phone : '',
			'email'         => $p ? $p->email : '',
			'product_id'    => $p ? ( $p->variation_id ? $p->variation_id : $p->product_id ) : 0,
			'product_name'  => $p ? $p->product_name : '',
			'options'       => $p ? $p->options : '',
			'price'         => $p ? (float) $p->price : '',
			'deposit'       => $p ? (float) $p->deposit : '',
			'weeks'         => $p ? (int) $p->weeks : max( NURAX_Desk::week_options() ),
			'start_date'    => $p ? $p->start_date : NURAX_Desk::today(),
			'fulfil'        => $p ? $p->fulfil : '',
			'notes'         => $p ? $p->notes : '',
			'wc_order_id'   => $p ? (int) $p->wc_order_id : '',
		);
		$back = $p ? NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $id ) ) : NURAX_Desk::url( NURAX_Desk::P_PLANS );
		NURAX_Desk::header( NURAX_Desk::P_PLANS, $p ? 'Edit ' . $p->ref : 'New payment plan', NURAX_Desk::btn( $back, 'Cancel', 'nd-btn--ghost' ) );
		$prod_label = '';
		if ( $v['product_id'] && function_exists( 'wc_get_product' ) ) {
			$pp = wc_get_product( $v['product_id'] );
			if ( $pp ) {
				$prod_label = wp_strip_all_tags( $pp->get_formatted_name() );
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form" data-nd-planform><input type="hidden" name="action" value="nurax_lmm_save"><input type="hidden" name="plan_id" value="' . (int) $id . '">';
		wp_nonce_field( 'nurax_lmm_save' );
		echo '<div class="nd-plan"><div class="nd-plan__main">';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Customer</h2></header><div class="nd-fields nd-fields--2">';
		echo '<label class="nd-field"><span class="nd-label">Full name *</span><input type="text" name="customer_name" required maxlength="120" value="' . esc_attr( $v['customer_name'] ) . '"></label>';
		echo '<label class="nd-field"><span class="nd-label">Phone / WhatsApp *</span><input type="tel" name="phone" required value="' . esc_attr( $v['phone'] ? '+' . $v['phone'] : '' ) . '" placeholder="0712 345 678 or +44..."></label>';
		echo '<label class="nd-field"><span class="nd-label">E-mail</span><input type="email" name="email" value="' . esc_attr( $v['email'] ) . '"></label>';
		echo '<label class="nd-field"><span class="nd-label">Collection / delivery</span><input type="text" name="fulfil" maxlength="120" value="' . esc_attr( $v['fulfil'] ) . '" placeholder="e.g. Pick up at the studio"></label>';
		echo '</div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Wig</h2></header><div class="nd-fields nd-fields--2">';
		echo '<label class="nd-field nd-field--wide"><span class="nd-label">Product</span><select class="wc-product-search" name="product_id" data-placeholder="Search for a wig..." data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" style="width:100%" data-nd-product>';
		if ( $v['product_id'] ) {
			echo '<option value="' . (int) $v['product_id'] . '" selected>' . esc_html( $prod_label ? $prod_label : $v['product_name'] ) . '</option>';
		}
		echo '</select><small>Pick the exact length/colour (variation) when you can, so stock is reserved correctly.</small></label>';
		echo '<label class="nd-field"><span class="nd-label">Name on statement</span><input type="text" name="product_name" maxlength="255" value="' . esc_attr( $v['product_name'] ) . '" data-nd-pname></label>';
		echo '<label class="nd-field"><span class="nd-label">Options</span><input type="text" name="options" maxlength="255" value="' . esc_attr( $v['options'] ) . '" placeholder="e.g. 18 inch, Natural black" data-nd-popts></label>';
		echo '</div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>Plan</h2></header><div class="nd-fields nd-fields--4">';
		echo '<label class="nd-field"><span class="nd-label">Price (KSh) *</span><input type="number" name="price" min="1" step="1" required value="' . esc_attr( $v['price'] ) . '" data-nd-price></label>';
		echo '<label class="nd-field"><span class="nd-label">Deposit (KSh)</span><input type="number" name="deposit" min="0" step="1" value="' . esc_attr( $v['deposit'] ) . '" data-nd-deposit data-pct="' . (int) $s['lmm_deposit'] . '"><small>Default ' . (int) $s['lmm_deposit'] . '%</small></label>';
		echo '<label class="nd-field"><span class="nd-label">Weeks</span><input type="number" name="weeks" min="1" max="52" value="' . esc_attr( $v['weeks'] ) . '" data-nd-weeks></label>';
		echo '<label class="nd-field"><span class="nd-label">Start date</span><input type="date" name="start_date" value="' . esc_attr( $v['start_date'] ) . '" data-nd-start></label>';
		echo '</div><div class="nd-preview" data-nd-preview></div></section>';

		if ( ! $p ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Deposit already paid?</h2><span class="nd-mut">Optional</span></header><div class="nd-fields nd-fields--3">';
			echo '<label class="nd-field"><span class="nd-label">Amount received (KSh)</span><input type="number" name="init_amount" min="0" step="1" data-nd-init></label>';
			echo '<label class="nd-field"><span class="nd-label">Method</span><select name="init_method">';
			foreach ( self::methods() as $mk => $ml ) {
				echo '<option value="' . esc_attr( $mk ) . '">' . esc_html( $ml ) . '</option>';
			}
			echo '</select></label>';
			echo '<label class="nd-field"><span class="nd-label">M-Pesa code / reference</span><input type="text" name="init_reference" maxlength="60"></label>';
			echo '</div></section>';
		}
		echo '</div><aside class="nd-plan__side"><section class="nd-card"><header class="nd-card__h"><h2>Notes</h2></header>';
		echo '<label class="nd-field"><textarea name="notes" rows="6" placeholder="Anything the team should know">' . esc_textarea( $v['notes'] ) . '</textarea></label>';
		echo '<label class="nd-field"><span class="nd-label">WooCommerce order # (optional)</span><input type="number" name="wc_order_id" min="0" value="' . esc_attr( $v['wc_order_id'] ) . '"></label>';
		echo '<button type="submit" class="nd-btn nd-btn--primary nd-btn--block">' . ( $p ? 'Save changes' : 'Create plan' ) . '</button></section></aside></div></form>';
		NURAX_Desk::footer();
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	public static function h_save() {
		NURAX_Desk::guard( 'nurax_lmm_save' );
		global $wpdb;
		$in    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id    = isset( $in['plan_id'] ) ? absint( $in['plan_id'] ) : 0;
		$name  = isset( $in['customer_name'] ) ? mb_substr( sanitize_text_field( $in['customer_name'] ), 0, 120 ) : '';
		$phone = isset( $in['phone'] ) ? NURAX_Desk::phone( $in['phone'] ) : '';
		$price = isset( $in['price'] ) ? round( (float) $in['price'], 2 ) : 0;
		$start = isset( $in['start_date'] ) ? NURAX_Desk::valid_date( $in['start_date'] ) : '';
		$back  = $id ? NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $id, 'action' => 'edit' ) ) : NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'action' => 'new' ) );
		if ( strlen( $name ) < 2 || '' === $phone || $price <= 0 || '' === $start ) {
			wp_safe_redirect( add_query_arg( 'nd_msg', 'invalid', $back ) );
			exit;
		}
		$pid = isset( $in['product_id'] ) ? absint( $in['product_id'] ) : 0;
		$vid = 0;
		if ( $pid && function_exists( 'wc_get_product' ) ) {
			$pp = wc_get_product( $pid );
			if ( $pp && $pp->is_type( 'variation' ) ) {
				$vid = $pid;
				$pid = (int) $pp->get_parent_id();
			} elseif ( ! $pp ) {
				$pid = 0;
			}
		}
		$s       = NURAX_Desk::settings();
		$deposit = isset( $in['deposit'] ) && '' !== (string) $in['deposit'] ? (float) $in['deposit'] : ceil( $price * (int) $s['lmm_deposit'] / 100 );
		$deposit = min( $price, max( 0, round( $deposit, 2 ) ) );
		$pname   = isset( $in['product_name'] ) ? mb_substr( sanitize_text_field( $in['product_name'] ), 0, 255 ) : '';
		if ( '' === $pname && $pid ) {
			$pname = get_the_title( $pid );
		}
		$email = isset( $in['email'] ) && is_email( $in['email'] ) ? sanitize_email( $in['email'] ) : '';
		$data  = array(
			'customer_name' => $name,
			'phone'         => $phone,
			'email'         => $email,
			'product_id'    => $pid,
			'variation_id'  => $vid,
			'product_name'  => $pname,
			'options'       => isset( $in['options'] ) ? mb_substr( sanitize_text_field( $in['options'] ), 0, 255 ) : '',
			'price'         => $price,
			'deposit'       => $deposit,
			'weeks'         => isset( $in['weeks'] ) ? min( 52, max( 1, absint( $in['weeks'] ) ) ) : 4,
			'start_date'    => $start,
			'fulfil'        => isset( $in['fulfil'] ) ? mb_substr( sanitize_text_field( $in['fulfil'] ), 0, 120 ) : '',
			'notes'         => isset( $in['notes'] ) ? sanitize_textarea_field( $in['notes'] ) : '',
			'wc_order_id'   => isset( $in['wc_order_id'] ) ? absint( $in['wc_order_id'] ) : 0,
			'updated_at'    => current_time( 'mysql' ),
		);
		if ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$data['user_id'] = (int) $user->ID;
			}
		}
		$t = NURAX_Desk::t_plans();
		if ( $id ) {
			$old = self::get( $id );
			if ( ! $old ) {
				wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'nd_msg' => 'notfound' ) ) );
				exit;
			}
			if ( $old->stock_reserved && ( (int) $old->product_id !== $pid || (int) $old->variation_id !== $vid ) ) {
				self::unreserve( $id );
			}
			$wpdb->update( $t, $data, array( 'id' => $id ) );
			NURAX_Desk::log( $t, $id, 'Plan details edited.' );
		} else {
			$data['ref']        = NURAX_Desk::new_ref( 'LMM', $t );
			$data['token']      = wp_generate_password( 32, false, false );
			$data['status']     = 'request';
			$data['source']     = 'admin';
			$data['created_at'] = current_time( 'mysql' );
			$data['log']        = '[]';
			$wpdb->insert( $t, $data );
			$id = (int) $wpdb->insert_id;
			NURAX_Desk::log( $t, $id, 'Plan created.' );
			$amt = isset( $in['init_amount'] ) ? round( (float) $in['init_amount'], 2 ) : 0;
			if ( $amt > 0 ) {
				$amt = min( $amt, $price );
				$m   = isset( $in['init_method'] ) && array_key_exists( $in['init_method'], self::methods() ) ? $in['init_method'] : 'mpesa';
				self::insert_payment( $id, $amt, $m, isset( $in['init_reference'] ) ? $in['init_reference'] : '', current_time( 'mysql' ), 'Deposit' );
			}
		}
		self::sync( $id );
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $id, 'nd_msg' => 'saved' ) ) );
		exit;
	}

	private static function clean_ref( $r ) {
		return mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\-\/ ]/', '', (string) $r ) ), 0, 60 );
	}

	private static function insert_payment( $plan_id, $amount, $method, $reference, $paid_at, $note ) {
		global $wpdb;
		$wpdb->insert( NURAX_Desk::t_pay(), array(
			'plan_id'     => (int) $plan_id,
			'amount'      => $amount,
			'method'      => $method,
			'reference'   => self::clean_ref( $reference ),
			'paid_at'     => $paid_at,
			'note'        => mb_substr( sanitize_text_field( (string) $note ), 0, 200 ),
			'recorded_by' => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		) );
		$pid = (int) $wpdb->insert_id;
		NURAX_Desk::log( NURAX_Desk::t_plans(), $plan_id, 'Payment of ' . NURAX_Desk::money( $amount ) . ' recorded (' . self::method_label( $method ) . ( $reference ? ' ' . self::clean_ref( $reference ) : '' ) . ').' );
		return $pid;
	}

	public static function h_pay() {
		NURAX_Desk::guard( 'nurax_lmm_pay' );
		global $wpdb;
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id   = isset( $in['plan_id'] ) ? absint( $in['plan_id'] ) : 0;
		$p    = self::get( $id );
		$view = NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $id ) );
		if ( ! $p ) {
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		$amt = isset( $in['amount'] ) ? round( (float) $in['amount'], 2 ) : 0;
		if ( $amt <= 0 ) {
			wp_safe_redirect( add_query_arg( 'nd_msg', 'amount', $view ) );
			exit;
		}
		$bal = (float) $p->price - self::paid_sum( $id );
		if ( $amt > $bal + 0.5 ) {
			wp_safe_redirect( add_query_arg( 'nd_msg', 'over', $view ) );
			exit;
		}
		$ref = self::clean_ref( isset( $in['reference'] ) ? $in['reference'] : '' );
		if ( '' !== $ref && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . NURAX_Desk::t_pay() . ' WHERE voided = 0 AND reference = %s', $ref ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_safe_redirect( add_query_arg( 'nd_msg', 'dup', $view ) );
			exit;
		}
		$method = isset( $in['method'] ) && array_key_exists( $in['method'], self::methods() ) ? $in['method'] : 'mpesa';
		$day    = isset( $in['paid_on'] ) ? NURAX_Desk::valid_date( $in['paid_on'] ) : '';
		if ( '' === $day || $day > NURAX_Desk::today() ) {
			$day = NURAX_Desk::today();
		}
		$when = $day . ' ' . current_time( 'H:i:s' );
		$pid  = self::insert_payment( $id, $amt, $method, $ref, $when, isset( $in['note'] ) ? $in['note'] : '' );
		self::sync( $id );

		if ( ! empty( $in['email_receipt'] ) && is_email( $p->email ) ) {
			$p2 = self::get( $id );
			$m  = self::metrics( $p2, self::paid_sum( $id ) );
			$y  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_pay() . ' WHERE id = %d', $pid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $y ) {
				wp_mail( $p->email, 'NURA Beauty - payment received (' . $p->ref . ')', self::receipt_text( $p2, $m, $y ) );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'nd_msg' => 'paid', 'pid' => $pid ), $view ) );
		exit;
	}

	public static function h_void() {
		NURAX_Desk::guard( 'nurax_lmm_void' );
		global $wpdb;
		$pid = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		$why = isset( $_POST['reason'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['reason'] ) ), 0, 200 ) : '';
		$y   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NURAX_Desk::t_pay() . ' WHERE id = %d', $pid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $y ) {
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		$wpdb->update( NURAX_Desk::t_pay(), array( 'voided' => 1, 'void_reason' => '' !== $why ? $why : 'No reason given' ), array( 'id' => $pid ) );
		NURAX_Desk::log( NURAX_Desk::t_plans(), $y->plan_id, 'Payment of ' . NURAX_Desk::money( $y->amount ) . ' voided' . ( $why ? ': ' . $why : '' ) . '.' );
		$p = self::get( $y->plan_id );
		if ( $p && 'completed' === $p->status ) {
			$wpdb->update( NURAX_Desk::t_plans(), array( 'status' => 'active', 'completed_at' => null ), array( 'id' => $p->id ) );
		}
		if ( $p && self::paid_sum( $p->id ) <= 0 && 'active' === $p->status ) {
			$wpdb->update( NURAX_Desk::t_plans(), array( 'status' => 'request' ), array( 'id' => $p->id ) );
		}
		self::sync( $y->plan_id );
		NURAX_Desk::bust();
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $y->plan_id, 'nd_msg' => 'voided' ) ) );
		exit;
	}

	public static function h_status() {
		NURAX_Desk::guard( 'nurax_lmm_status' );
		global $wpdb;
		$id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$to = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
		$p  = self::get( $id );
		if ( ! $p || ! in_array( $to, array( 'released', 'cancelled', 'active' ), true ) ) {
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		$now = current_time( 'mysql' );
		$t   = NURAX_Desk::t_plans();
		if ( 'released' === $to ) {
			self::reserve( $id ); // Make sure the unit is out of stock.
			$wpdb->update( $t, array( 'status' => 'released', 'released_at' => $now, 'updated_at' => $now ), array( 'id' => $id ) );
			NURAX_Desk::log( $t, $id, 'Wig released to the customer.' );
		} elseif ( 'cancelled' === $to ) {
			self::unreserve( $id );
			$wpdb->update( $t, array( 'status' => 'cancelled', 'updated_at' => $now ), array( 'id' => $id ) );
			NURAX_Desk::log( $t, $id, 'Plan cancelled. ' . NURAX_Desk::money( self::paid_sum( $id ) ) . ' was paid - settle any refund or credit with the customer.' );
		} else {
			$paid = self::paid_sum( $id );
			$wpdb->update( $t, array( 'status' => $paid > 0 ? 'active' : 'request', 'released_at' => null, 'updated_at' => $now ), array( 'id' => $id ) );
			NURAX_Desk::log( $t, $id, 'Plan re-opened.' );
			self::sync( $id );
		}
		NURAX_Desk::bust();
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $id, 'nd_msg' => 'status' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Overview widgets                                                    */
	/* ------------------------------------------------------------------ */

	public static function attention_list( $limit ) {
		$paid  = self::paid_map();
		$items = array();
		$today = NURAX_Desk::today();
		foreach ( self::all( array( 'active' ) ) as $p ) {
			$m = self::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
			if ( 'overdue' === $m['health'] || ( $m['next'] && $m['next']['due'] <= gmdate( 'Y-m-d', strtotime( $today . ' +2 days' ) ) ) ) {
				$items[] = array( $p, $m );
			}
		}
		usort( $items, function ( $a, $b ) {
			return $b[1]['late'] - $a[1]['late'];
		} );
		if ( ! $items ) {
			echo '<p class="nd-empty-s">All plans are up to date.</p>';
			return;
		}
		echo '<ul class="nd-list">';
		foreach ( array_slice( $items, 0, $limit ) as $it ) {
			list( $p, $m ) = $it;
			$wa = NURAX_Desk::wa( $p->phone, self::reminder_text( $p, $m ) );
			echo '<li><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) ) ) . '"><strong>' . esc_html( $p->customer_name ) . '</strong><small>' . esc_html( $p->product_name ) . '</small></a>';
			echo '<span class="nd-list__meta">' . ( 'overdue' === $m['health'] ? '<b class="nd-red">' . esc_html( NURAX_Desk::money( $m['arrears'] ) ) . '</b><small>' . (int) $m['late'] . ' days late</small>' : '<b>' . esc_html( NURAX_Desk::money( $m['next']['amount'] ) ) . '</b><small>due ' . esc_html( NURAX_Desk::date_label( $m['next']['due'], 'D j M' ) ) . '</small>' ) . '</span>';
			echo $wa ? '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">Remind</a>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</li>';
		}
		echo '</ul>';
	}

	public static function request_list( $limit ) {
		$rows = array_slice( self::all( array( 'request' ) ), 0, $limit );
		if ( ! $rows ) {
			return;
		}
		echo '<h3 class="nd-sub">Payment plan requests</h3><ul class="nd-list">';
		foreach ( $rows as $p ) {
			echo '<li><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $p->id ) ) ) . '"><strong>' . esc_html( $p->customer_name ? $p->customer_name : $p->ref ) . '</strong><small>' . esc_html( $p->product_name . ' - ' . NURAX_Desk::money( $p->price ) . ' / ' . (int) $p->weeks . ' weeks' ) . '</small></a><span class="nd-list__meta"><small>' . esc_html( human_time_diff( strtotime( $p->created_at ), current_time( 'timestamp' ) ) . ' ago' ) . '</small></span></li>';
		}
		echo '</ul>';
	}

	public static function latest_payments( $limit ) {
		global $wpdb;
		$res = $wpdb->get_results( $wpdb->prepare( 'SELECT y.*, p.customer_name, p.ref, p.id AS pid FROM ' . NURAX_Desk::t_pay() . ' y JOIN ' . NURAX_Desk::t_plans() . ' p ON p.id = y.plan_id WHERE y.voided = 0 ORDER BY y.paid_at DESC, y.id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $res ) {
			echo '<p class="nd-empty-s">No payments recorded yet.</p>';
			return;
		}
		echo '<ul class="nd-list">';
		foreach ( $res as $y ) {
			echo '<li><a class="nd-list__main" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_PLANS, array( 'plan' => $y->pid ) ) ) . '"><strong>' . esc_html( $y->customer_name ) . '</strong><small>' . esc_html( self::method_label( $y->method ) . ( $y->reference ? ' ' . $y->reference : '' ) . ' - ' . $y->ref ) . '</small></a><span class="nd-list__meta"><b class="nd-green">' . esc_html( NURAX_Desk::money( $y->amount ) ) . '</b><small>' . esc_html( NURAX_Desk::date_label( $y->paid_at, 'D j M' ) ) . '</small></span></li>';
		}
		echo '</ul>';
	}

	/** Daily customer e-mail reminders (opt-in). */
	public static function customer_reminders() {
		$s = NURAX_Desk::settings();
		if ( empty( $s['cust_email'] ) ) {
			return;
		}
		$today    = NURAX_Desk::today();
		$tomorrow = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		$paid     = self::paid_map();
		foreach ( self::all( array( 'active' ) ) as $p ) {
			if ( ! is_email( $p->email ) ) {
				continue;
			}
			$m    = self::metrics( $p, isset( $paid[ $p->id ] ) ? $paid[ $p->id ] : 0 );
			$send = ( $m['next'] && $m['next']['due'] === $tomorrow && 'overdue' !== $m['health'] ) || ( 'overdue' === $m['health'] && in_array( (int) $m['late'], array( (int) $s['lmm_grace'] + 1, 7, 14 ), true ) );
			if ( $send ) {
				wp_mail( $p->email, 'NURA Beauty - your Lipa Mdogo Mdogo plan (' . $p->ref . ')', str_replace( '*', '', self::reminder_text( $p, $m ) ) );
				NURAX_Desk::log( NURAX_Desk::t_plans(), $p->id, 'Reminder e-mail sent.' );
			}
		}
	}
}
