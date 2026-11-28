<?php
/**
 * NURA Desk - M-Pesa statement matching, M-Pesa code checks and staff float (NURA Experience 1.129.0; Paystack fee button, paid date, REST check 1.130.0; charges recognised across hand-entered vouchers 1.138.0).
 *
 * Accounts > M-Pesa statement: paste the text of the Safaricom till statement (open the PDF, select
 * all, copy, paste) or upload the CSV export. Nothing is written on upload: the page shows every
 * payment in and out with what it matches in the books (orders by M-Pesa code, Paystack payouts
 * to their online orders, vouchers by reference), suggestions for the rest, the M-Pesa charges and
 * a one-click button for each unmatched line. A reference already in the books is never recorded
 * twice.
 *
 * Orders: hand-entered and till orders show an M-Pesa code check (format, code date versus order
 * date, code already used elsewhere).
 *
 * Accounts > Staff float: money sent to staff to spend for the shop (category Staff float given),
 * what they spent (payment method Staff float, payee = the staff member) and money returned.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Mpesa_Recon {

	const RUN = 'nurax_mpesa_run_';

	public static function hooks() {
		add_action( 'admin_post_nurax_mpesa_read', array( __CLASS__, 'h_read' ) );
		add_action( 'admin_post_nurax_mpesa_act', array( __CLASS__, 'h_act' ) );
		add_action( 'admin_post_nurax_mpesa_key', array( __CLASS__, 'h_key' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'order_check_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_paid_date' ), 99 );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
	}

	private static function cap() {
		return NURAX_Desk_Accounts::cap();
	}

	private static function url( $args = array() ) {
		return NURAX_Desk::url( NURAX_Desk_Accounts::PAGE, array_merge( array( 'tab' => 'mpesa' ), $args ) );
	}

	/* ------------------------------------------------------------ M-Pesa code helpers */

	/** Date a Safaricom code was issued: 1st letter year (U = 2026), 2nd month (A = Jan), 3rd day (1-9, A = 10 ... V = 31). */
	public static function code_date( $code ) {
		$c = strtoupper( (string) $code );
		if ( ! preg_match( '/^[A-Z]{2}[0-9A-Z][A-Z0-9]{7}$/', $c ) ) {
			return '';
		}
		$y = 2026 + ord( $c[0] ) - ord( 'U' );
		$m = ord( $c[1] ) - ord( 'A' ) + 1;
		$d = ctype_digit( $c[2] ) ? (int) $c[2] : 10 + ord( $c[2] ) - ord( 'A' );
		if ( $y < 2018 || $y > 2040 || $m < 1 || $m > 12 || $d < 1 || $d > 31 || ! checkdate( $m, $d, $y ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $y, $m, $d );
	}

	/** Problems with an order's M-Pesa code, as plain sentences. */
	public static function code_issues( $o ) {
		$out = array();
		$pm  = strtolower( (string) $o->get_payment_method() . ' ' . $o->get_payment_method_title() );
		if ( false !== strpos( $pm, 'paystack' ) && false === strpos( $pm, 'nura_pos' ) ) {
			return $out; // Paystack keeps its own reference.
		}
		$code = NURAX_Desk_Accounts::norm_ref( $o->get_transaction_id() ? $o->get_transaction_id() : $o->get_meta( '_nura_paid_ref', true ) );
		$hand = in_array( $o->get_created_via(), array( 'admin', 'nura_pos', '' ), true );
		if ( '' === $code ) {
			if ( $hand && ( false !== strpos( $pm, 'mpesa' ) || false !== strpos( $pm, 'm-pesa' ) ) && $o->is_paid() ) {
				$out[] = 'No M-Pesa code is recorded on this order. Add it in Transaction ID so the payment can be matched to the M-Pesa statement.';
			}
			return $out;
		}
		if ( ! preg_match( '/^[A-Z0-9]{10}$/', $code ) ) {
			$out[] = 'The code ' . $code . ' is not a normal M-Pesa code (10 letters and numbers). Check it against the M-Pesa message.';
			return $out;
		}
		$cd = self::code_date( $code );
		$od = $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d' ) : '';
		if ( $cd && $od && $cd !== $od ) {
			$out[] = 'The code ' . $code . ' was issued on ' . date_i18n( 'j M Y', strtotime( $cd ) ) . ', but the order is dated ' . date_i18n( 'j M Y', strtotime( $od ) ) . '. Check the order date.';
		}
		if ( method_exists( 'NURAX_Desk', 'ref_used' ) ) {
			$used = NURAX_Desk::ref_used( $code, $o->get_id() );
			if ( '' !== $used ) {
				$out[] = 'The code ' . $code . ' is also used on ' . $used . '. One payment should be on one order only.';
			}
		}
		return $out;
	}

	public static function order_check_box( $o ) {
		if ( ! $o || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$issues = self::code_issues( $o );
		$code   = $o->get_transaction_id();
		echo '<div class="form-field form-field-wide nurax-code-check" style="margin-top:12px;padding:8px 10px;border-left:4px solid ' . ( $issues ? '#d63638' : '#00a32a' ) . ';background:#fff">';
		echo '<strong>M-Pesa code check</strong><br>';
		if ( $issues ) {
			foreach ( $issues as $i ) {
				echo '<span style="display:block">' . esc_html( $i ) . '</span>';
			}
		} elseif ( $code ) {
			$cd = self::code_date( $code );
			echo esc_html( 'Code ' . strtoupper( $code ) . ' looks right' . ( $cd ? ' (issued ' . date_i18n( 'j M Y', strtotime( $cd ) ) . ')' : '' ) . ' and is not used on any other order.' );
		} else {
			echo esc_html( 'No M-Pesa code on this order.' );
		}
		if ( $o->get_date_paid() && current_user_can( 'manage_woocommerce' ) ) {
			echo '<label style="display:block;margin-top:6px">Paid on (change only to match the M-Pesa or bank date) <input type="date" name="nurax_paid_on" value="' . esc_attr( $o->get_date_paid()->date( 'Y-m-d' ) ) . '" max="' . esc_attr( current_time( 'Y-m-d' ) ) . '"></label>';
		}
		echo '</div>';
	}

	/** 1.130.0: owner can correct an order's paid date from the order screen. */
	public static function save_paid_date( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the order nonce before this hook.
		if ( empty( $_POST['nurax_paid_on'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$d = NURAX_Desk::valid_date( sanitize_text_field( wp_unslash( $_POST['nurax_paid_on'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$o = wc_get_order( $order_id );
		if ( ! $d || ! $o || ! $o->get_date_paid() || $d > current_time( 'Y-m-d' ) ) {
			return;
		}
		$old = $o->get_date_paid()->date( 'Y-m-d' );
		if ( $old === $d ) {
			return;
		}
		$o->set_date_paid( $d . ' ' . $o->get_date_paid()->date( 'H:i:s' ) ); // 1.130.1: site-local string, so the time of day is kept.
		if ( $o->get_meta( '_nura_paid_on', true ) ) {
			$o->update_meta_data( '_nura_paid_on', $d );
		}
		$o->save();
		$u = wp_get_current_user();
		$o->add_order_note( 'Paid date changed from ' . $old . ' to ' . $d . ' by ' . ( $u && $u->ID ? $u->display_name : 'owner' ) . '.' );
		if ( class_exists( 'NURAX_Desk_Accounts' ) ) {
			NURAX_Desk_Accounts::bust();
		}
	}

	/**
	 * 1.130.0: read-only REST check for the monthly reconciliation routine.
	 * POST /wp-json/nurax/v1/mpesa-recon {statement: "<statement text or CSV>"} as the owner
	 * (application password). Returns every row with its match; writes nothing.
	 */
	public static function rest() {
		register_rest_route( 'nurax/v1', '/mpesa-recon', array(
			'methods'             => 'POST',
			'permission_callback' => function ( $req ) {
				return current_user_can( NURAX_Desk_Accounts::cap() ) || self::key_ok( (string) $req->get_header( 'x_api_key' ) );
			},
			'callback'            => function ( $req ) {
				$rows = self::match( self::parse( (string) $req->get_param( 'statement' ) ) );
				if ( ! $rows ) {
					return new WP_Error( 'nurax_empty', 'No M-Pesa transactions found in the statement text.', array( 'status' => 400 ) );
				}
				$from = $rows[0]['date'];
				$to   = end( $rows )['date'];
				$chg  = 0;
				foreach ( $rows as &$r ) {
					$chg += $r['charge'];
					unset( $r['details'] );
				}
				unset( $r );
				return array(
					'from'              => $from,
					'to'                => $to,
					'charges'           => round( $chg, 2 ),
					'charges_recorded'  => self::charges_recorded( $from, $to, round( $chg, 2 ) ),
					'charges_booked'    => self::charges_booked( $from, $to )['total'], // 1.138.0
					'rows'              => $rows,
				);
			},
		) );
	}

	/** 1.130.1: access key for the read-only check (X-API-Key header). Only its hash is stored. */
	const KEY_OPT = 'nurax_recon_key_hash';

	public static function key_ok( $key ) {
		$h = (string) get_option( self::KEY_OPT, '' );
		return '' !== $h && strlen( $key ) >= 32 && hash_equals( $h, hash( 'sha256', $key ) );
	}

	public static function h_key() {
		self::guard( 'nurax_mpesa_key' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'revoke' === $do ) {
			delete_option( self::KEY_OPT );
			wp_safe_redirect( self::url( array( 'nd_err' => 'The access key was removed. The monthly check can no longer read the statement matches.' ) ) );
			exit;
		}
		$key = wp_generate_password( 40, false, false );
		update_option( self::KEY_OPT, hash( 'sha256', $key ), false );
		set_transient( 'nurax_recon_key_show_' . get_current_user_id(), $key, 10 * MINUTE_IN_SECONDS );
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'accounts', 'Created a new access key for the monthly M-Pesa check' );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	private static function render_key_box() {
		$show = get_transient( 'nurax_recon_key_show_' . get_current_user_id() );
		$has  = '' !== (string) get_option( self::KEY_OPT, '' );
		echo '<details class="na-card" style="margin-top:12px"' . ( $show ? ' open' : '' ) . '><summary><strong>Monthly check by Ray: access key</strong> ' . ( $has ? '(key set)' : '(no key yet)' ) . '</summary>';
		echo '<p>Ray\'s monthly M-Pesa check reads the matches below with this key. It can only read; it cannot change anything. Save the key only in Town\'s private key form, never in a chat.</p>';
		if ( $show ) {
			delete_transient( 'nurax_recon_key_show_' . get_current_user_id() );
			echo '<p><input type="text" readonly value="' . esc_attr( $show ) . '" style="width:100%;font-family:monospace" onclick="this.select()"><br><small>Copy it now. It is shown only once.</small></p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( 'nurax_mpesa_key' );
		echo '<input type="hidden" name="action" value="nurax_mpesa_key"><input type="hidden" name="do" value="new"><button class="nd-btn nd-btn--ghost nd-btn--sm">' . ( $has ? 'Replace key' : 'Create key' ) . '</button></form> ';
		if ( $has ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'nurax_mpesa_key' );
			echo '<input type="hidden" name="action" value="nurax_mpesa_key"><input type="hidden" name="do" value="revoke"><button class="nd-btn nd-btn--ghost nd-btn--sm">Remove key</button></form>';
		}
		echo '</details>';
	}

	/* ------------------------------------------------------------ statement reading */

	private static function noise() {
		return array( 'merchant', 'customer', 'payment', 'pay', 'to', 'from', 'via', 'api', 'online', 'completed', 'nura', 'beauty', 'crown', 'charge', 'goods', 'business', 'buy', 'by', 'till', 'details', 'transaction', 'status', 'type', 'other', 'party', 'receipt', 'no', 'time', 'completion', 'paid', 'in', 'withdrawn', 'balance', 'ref', 'limited', 'kenya', 'payments', 'paystack', 'co-op', 'and', 'with', 'od', 'disclaimer', 'any', 'account', 'a', 'the' );
	}

	/** Turn statement text (copied from the PDF) or a CSV export into rows. */
	public static function parse( $text ) {
		$text = str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), (string) $text );
		$rows = self::parse_csv( $text );
		if ( ! $rows ) {
			$rows = self::parse_text( $text );
		}
		// Merge charge lines into their payment (same receipt number).
		$by = array();
		foreach ( $rows as $r ) {
			$k = $r['receipt'];
			if ( ! isset( $by[ $k ] ) ) {
				$by[ $k ] = array( 'receipt' => $k, 'date' => $r['date'], 'time' => $r['time'], 'amount' => 0.0, 'charge' => 0.0, 'name' => '', 'phone' => '', 'details' => '', 'kind' => '' );
			}
			if ( $r['charge'] ) {
				$by[ $k ]['charge'] += abs( $r['amount'] );
			} else {
				$by[ $k ]['amount']  = $r['amount'];
				$by[ $k ]['name']    = $r['name'];
				$by[ $k ]['phone']   = $r['phone'];
				$by[ $k ]['details'] = $r['details'];
				$by[ $k ]['date']    = $r['date'];
				$by[ $k ]['time']    = $r['time'];
				$up = strtoupper( $r['details'] );
				$by[ $k ]['kind'] = false !== strpos( $up, 'PAYSTACK' ) ? 'paystack' : ( $r['amount'] < 0 ? 'out' : 'in' );
			}
		}
		$out = array_values( $by );
		usort( $out, function ( $a, $b ) {
			return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
		} );
		return $out;
	}

	private static function money_val( $s ) {
		$s = trim( str_replace( array( ',', 'KSh', 'KES', ' ' ), '', (string) $s ) );
		return '' === $s || ! is_numeric( $s ) ? 0.0 : (float) $s;
	}

	private static function parse_csv( $text ) {
		$lines = array_values( array_filter( explode( "\n", $text ), 'strlen' ) );
		$head  = -1;
		foreach ( $lines as $i => $l ) {
			if ( false !== stripos( $l, 'Receipt' ) && false !== stripos( $l, 'Paid In' ) && ( substr_count( $l, ',' ) >= 4 || substr_count( $l, "\t" ) >= 4 ) ) {
				$head = $i;
				break;
			}
		}
		if ( $head < 0 ) {
			return array();
		}
		$sep  = substr_count( $lines[ $head ], "\t" ) >= 4 ? "\t" : ',';
		$cols = array_map( function ( $c ) {
			return strtolower( trim( $c ) );
		}, str_getcsv( $lines[ $head ], $sep ) );
		$find = function ( $needle ) use ( $cols ) {
			foreach ( $cols as $i => $c ) {
				if ( false !== strpos( $c, $needle ) ) {
					return $i;
				}
			}
			return -1;
		};
		$ci = array( 'r' => $find( 'receipt' ), 't' => $find( 'completion' ), 'd' => $find( 'details' ), 'in' => $find( 'paid in' ), 'out' => $find( 'withdrawn' ), 'p' => $find( 'other party' ) );
		$rows = array();
		for ( $i = $head + 1; $i < count( $lines ); $i++ ) {
			$c = str_getcsv( $lines[ $i ], $sep );
			$r = isset( $c[ $ci['r'] ] ) ? strtoupper( trim( $c[ $ci['r'] ] ) ) : '';
			if ( ! preg_match( '/^[A-Z0-9]{10}$/', $r ) ) {
				continue;
			}
			$t    = $ci['t'] >= 0 && isset( $c[ $ci['t'] ] ) ? strtotime( str_replace( '/', '-', $c[ $ci['t'] ] ) ) : false;
			$in   = $ci['in'] >= 0 && isset( $c[ $ci['in'] ] ) ? self::money_val( $c[ $ci['in'] ] ) : 0;
			$wd   = $ci['out'] >= 0 && isset( $c[ $ci['out'] ] ) ? self::money_val( $c[ $ci['out'] ] ) : 0;
			$det  = $ci['d'] >= 0 && isset( $c[ $ci['d'] ] ) ? trim( $c[ $ci['d'] ] ) : '';
			$par  = $ci['p'] >= 0 && isset( $c[ $ci['p'] ] ) ? trim( $c[ $ci['p'] ] ) : '';
			list( $phone, $name ) = self::party( $det . ' ' . $par );
			$rows[] = array(
				'receipt' => $r,
				'date'    => $t ? gmdate( 'Y-m-d', $t ) : self::code_date( $r ),
				'time'    => $t ? gmdate( 'H:i:s', $t ) : '',
				'amount'  => $in > 0 ? $in : -abs( $wd ),
				'charge'  => (bool) preg_match( '/\bCharge\b/i', $det ),
				'details' => $det . ' ' . $par,
				'phone'   => $phone,
				'name'    => $name,
			);
		}
		return $rows;
	}

	private static function parse_text( $text ) {
		$rows = array();
		if ( ! preg_match_all( '/\b([A-Z]{2}[A-Z0-9]{8})\s+(\d{4}-\d{2}-\d{2})/', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			return $rows;
		}
		$n = count( $m[0] );
		for ( $i = 0; $i < $n; $i++ ) {
			$start = $m[0][ $i ][1];
			$end   = $i + 1 < $n ? $m[0][ $i + 1 ][1] : min( strlen( $text ), $start + 1500 );
			$seg   = substr( $text, $start, $end - $start );
			$seg   = preg_replace( '/(Page \d+ of \d+|Account Type|Disclaimer:|Statement Verification).*$/s', '', $seg );
			$flat  = trim( preg_replace( '/\s+/', ' ', $seg ) );
			$body  = substr( $flat, strlen( $m[1][ $i ][0] ) + 11 );
			preg_match( '/\b(\d{2}:\d{2}:\d{2})\b/', $body, $tm );
			if ( ! preg_match_all( '/(?<![\d*])(-?\d{1,3}(?:,\d{3})*\.\d{2})(?!\d)(\s+[A-Za-z])?/', $body, $am ) ) {
				continue;
			}
			// The running balance is printed right before the transaction type (e.g. "24,518.60 Merchant to");
			// the amount paid in or withdrawn is the figure that is not followed by words.
			$amt = null;
			foreach ( $am[1] as $k => $v ) {
				if ( '' === $am[2][ $k ] ) {
					$amt = $v;
					break;
				}
			}
			$amt = null === $amt ? $am[1][0] : $amt;
			list( $phone, $name ) = self::party( $body );
			$rows[] = array(
				'receipt' => $m[1][ $i ][0],
				'date'    => $m[2][ $i ][0],
				'time'    => isset( $tm[1] ) ? $tm[1] : '',
				'amount'  => self::money_val( $amt ),
				'charge'  => (bool) preg_match( '/\bCharge\b/i', $body ),
				'details' => $body,
				'phone'   => $phone,
				'name'    => $name,
			);
		}
		return $rows;
	}

	/** Masked phone and the name that follows it, e.g. 254740***564 LEONORA MAKENA. */
	private static function party( $s ) {
		$phone = '';
		$name  = array();
		if ( preg_match( '/(\d{3,12}\*{3}\d{3})/', $s, $pm, PREG_OFFSET_CAPTURE ) ) {
			$phone = $pm[1][0];
			$rest  = substr( $s, $pm[1][1] + strlen( $phone ) );
		} elseif ( preg_match( '/REF\s+(.+)$/i', $s, $rm ) ) {
			$rest = $rm[1];
		} else {
			return array( '', '' );
		}
		$noise = self::noise();
		foreach ( preg_split( '/[\s:]+/', $rest ) as $w ) {
			$w = trim( $w, " -.,|" );
			if ( '' === $w || ! preg_match( "/^[A-Za-z][A-Za-z'\-]*$/", $w ) || in_array( strtolower( $w ), $noise, true ) ) {
				continue;
			}
			$k = strtoupper( $w );
			if ( ! in_array( $k, $name, true ) ) {
				$name[] = $k;
			}
			if ( count( $name ) >= 4 ) {
				break;
			}
		}
		return array( $phone, ucwords( strtolower( implode( ' ', $name ) ) ) );
	}

	/* ------------------------------------------------------------ matching */

	private static function ledger_by_ref( $ref ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT txn, SUM(amount) AS total, MIN(entry_date) AS d, MAX(payee) AS payee, GROUP_CONCAT(category) AS cats FROM ' . NURAX_Desk_Accounts::table() . " WHERE deleted = 0 AND REPLACE(UPPER(ref),' ','') = %s GROUP BY txn", NURAX_Desk_Accounts::norm_ref( $ref ) ) ); // phpcs:ignore WordPress.DB
	}

	private static function order_by_code( $ref ) {
		global $wpdb;
		$ref  = NURAX_Desk_Accounts::norm_ref( $ref );
		$ids  = array();
		$hpos = $wpdb->prefix . 'wc_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
			$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $hpos WHERE status NOT IN ('trash','wc-cancelled','wc-failed','wc-checkout-draft','auto-draft') AND REPLACE(UPPER(transaction_id),' ','') = %s", $ref ) ); // phpcs:ignore WordPress.DB
			$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key IN ('_nura_paid_ref','_nura_pay_ref','_nura_part_ref') AND REPLACE(UPPER(meta_value),' ','') = %s", $ref ) ) ); // phpcs:ignore WordPress.DB
		}
		$ids = array_merge( $ids, (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_transaction_id','_nura_paid_ref','_nura_pay_ref','_nura_part_ref') AND REPLACE(UPPER(meta_value),' ','') = %s", $ref ) ) ); // phpcs:ignore WordPress.DB
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$o = $id ? wc_get_order( $id ) : null;
			if ( $o && ! in_array( $o->get_status(), array( 'trash', 'cancelled', 'failed', 'checkout-draft' ), true ) ) {
				return $o;
			}
		}
		return null;
	}

	private static function orders_between( $from, $to ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return (array) wc_get_orders( array(
			'limit'        => 200,
			'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
			'date_created' => strtotime( $from . ' 00:00:00' ) . '...' . strtotime( $to . ' 23:59:59' ),
			'type'         => 'shop_order',
		) );
	}

	/** Attach to each row what it matches in the books. */
	public static function match( $rows ) {
		if ( ! $rows ) {
			return $rows;
		}
		$first = $rows[0]['date'];
		$last  = end( $rows )['date'];
		$cands = self::orders_between( gmdate( 'Y-m-d', strtotime( $first . ' -7 days' ) ), gmdate( 'Y-m-d', strtotime( $last . ' +3 days' ) ) );
		$taken = array();
		foreach ( $rows as &$r ) {
			$r['status'] = 'unmatched';
			$r['match']  = '';
			$r['link']   = '';
			$r['suggest'] = array();
			if ( 0.0 === (float) $r['amount'] ) {
				$r['status'] = 'charge_only';
				continue;
			}
			$led = self::ledger_by_ref( $r['receipt'] );
			if ( 'out' === $r['kind'] ) {
				if ( $led ) {
					$t = 0;
					$v = array();
					foreach ( $led as $l ) {
						$t  += (float) $l->total;
						$v[] = $l->txn;
					}
					$r['status'] = abs( $t - abs( $r['amount'] ) ) < 0.01 ? 'matched' : 'differs';
					$r['match']  = implode( ', ', $v ) . ( 'differs' === $r['status'] ? ' (books ' . NURAX_Desk::money( $t ) . ')' : '' );
					$r['link']   = self::voucher_url( $v[0] );
					continue;
				}
				$r['suggest'] = self::voucher_suggestions( $r );
				continue;
			}
			// Money in.
			$o = self::order_by_code( $r['receipt'] );
			if ( $o ) {
				$taken[ $o->get_id() ] = true;
				$tot         = (float) $o->get_total();
				$r['status'] = abs( $tot - $r['amount'] ) < 0.01 ? 'matched' : 'differs';
				$r['match']  = 'Order #' . $o->get_order_number() . ( 'differs' === $r['status'] ? ' (order total ' . NURAX_Desk::money( $tot ) . ')' : '' );
				$r['link']   = $o->get_edit_order_url();
				continue;
			}
			if ( $led && 'paystack' !== $r['kind'] ) {
				$r['status'] = 'matched';
				$r['match']  = $led[0]->txn;
				$r['link']   = self::voucher_url( $led[0]->txn );
				continue;
			}
			if ( 'paystack' === $r['kind'] ) {
				$r['fee_txn'] = $led ? $led[0]->txn : '';
				foreach ( $cands as $c ) {
					$pm = strtolower( $c->get_payment_method() . ' ' . $c->get_payment_method_title() );
					$pd = $c->get_date_paid() ? $c->get_date_paid()->date( 'Y-m-d' ) : $c->get_date_created()->date( 'Y-m-d' );
					$tt = (float) $c->get_total();
					if ( isset( $taken[ $c->get_id() ] ) || false === strpos( $pm, 'paystack' ) || $pd > $r['date'] || $pd < gmdate( 'Y-m-d', strtotime( $r['date'] . ' -6 days' ) ) ) {
						continue;
					}
					if ( $r['amount'] <= $tt + 0.01 && $r['amount'] >= $tt * 0.94 ) {
						$taken[ $c->get_id() ] = true;
						$r['status'] = 'likely';
						$r['match']  = 'Paystack payout for order #' . $c->get_order_number() . ' (' . NURAX_Desk::money( $tt ) . ' less ' . NURAX_Desk::money( $tt - $r['amount'] ) . ' Paystack fee)';
						$r['link']   = $c->get_edit_order_url();
						$r['order_id'] = $c->get_id();
						$r['fee']      = round( $tt - $r['amount'], 2 );
						break;
					}
				}
				continue;
			}
			$tail = substr( preg_replace( '/\D/', '', $r['phone'] ), -3 );
			foreach ( $cands as $c ) {
				if ( isset( $taken[ $c->get_id() ] ) || $c->get_transaction_id() ) {
					continue;
				}
				$od = $c->get_date_created()->date( 'Y-m-d' );
				if ( abs( strtotime( $od ) - strtotime( $r['date'] ) ) > 3 * DAY_IN_SECONDS ) {
					continue;
				}
				$ph   = preg_replace( '/\D/', '', (string) $c->get_billing_phone() );
				$same = abs( (float) $c->get_total() - $r['amount'] ) < 0.01;
				if ( $same || ( $tail && $ph && substr( $ph, -3 ) === $tail ) ) {
					$r['suggest'][] = array( 'type' => 'order', 'id' => $c->get_id(), 'label' => 'Order #' . $c->get_order_number() . ', ' . date_i18n( 'j M', strtotime( $od ) ) . ', ' . trim( $c->get_billing_first_name() . ' ' . $c->get_billing_last_name() ) . ', ' . NURAX_Desk::money( $c->get_total() ) );
				}
			}
		}
		unset( $r );
		return $rows;
	}

	private static function voucher_url( $txn ) {
		return NURAX_Desk::url( NURAX_Desk_Accounts::PAGE, array( 'tab' => 'entries', 'edit' => strtolower( $txn ), 'p' => 'all' ) ) . '#na-form';
	}

	private static function voucher_suggestions( $r ) {
		global $wpdb;
		$amt = abs( $r['amount'] );
		$res = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT txn, MIN(entry_date) AS d, SUM(amount) AS total, MAX(payee) AS payee FROM ' . NURAX_Desk_Accounts::table() . " WHERE deleted = 0 AND ref = '' AND txn LIKE 'PV-%%' AND entry_date BETWEEN %s AND %s GROUP BY txn", gmdate( 'Y-m-d', strtotime( $r['date'] . ' -3 days' ) ), gmdate( 'Y-m-d', strtotime( $r['date'] . ' +3 days' ) ) ) ); // phpcs:ignore WordPress.DB
		$out = array();
		foreach ( $res as $v ) {
			if ( abs( (float) $v->total - $amt ) < 0.01 ) {
				$out[] = array( 'type' => 'voucher', 'id' => $v->txn, 'label' => $v->txn . ', ' . date_i18n( 'j M', strtotime( $v->d ) ) . ( $v->payee ? ', ' . $v->payee : '' ) . ', ' . NURAX_Desk::money( $v->total ) );
			}
		}
		return $out;
	}

	/**
	 * Charges already in the books for the statement period.
	 * Returns the voucher number(s) that cover them, or '' when they are not (fully) in the books.
	 * 1.138.0: besides one voucher for the exact total (or the CHG reference made by the
	 * "Record charges" button), the charges also count as recorded when all M-Pesa charges
	 * vouchers dated inside the statement period add up to the statement total, so charges
	 * entered by hand in several vouchers are recognised.
	 */
	private static function charges_recorded( $from, $to, $total ) {
		global $wpdb;
		$res = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT txn, SUM(amount) AS total FROM ' . NURAX_Desk_Accounts::table() . " WHERE deleted = 0 AND category = 'fees' AND entry_date BETWEEN %s AND %s GROUP BY txn", $from, gmdate( 'Y-m-d', strtotime( $to . ' +3 days' ) ) ) ); // phpcs:ignore WordPress.DB
		foreach ( $res as $v ) {
			if ( abs( (float) $v->total - $total ) < 0.01 ) {
				return $v->txn;
			}
		}
		$ref = self::charge_ref( $from, $to );
		$led = self::ledger_by_ref( $ref );
		if ( $led ) {
			return $led[0]->txn;
		}
		$b = self::charges_booked( $from, $to );
		if ( $b['txns'] && abs( $b['total'] - $total ) < 0.01 ) {
			return implode( ', ', $b['txns'] );
		}
		return '';
	}

	/**
	 * 1.138.0: every M-Pesa charges voucher dated inside the statement period, hand-entered ones included.
	 * Category "M-Pesa, bank & card charges" only; card and bank fees and anything that mentions
	 * Paystack (payee, description or note) are left out, because those are not on the till statement.
	 *
	 * @return array{total: float, txns: string[]}
	 */
	private static function charges_booked( $from, $to ) {
		global $wpdb;
		$ps  = '%' . $wpdb->esc_like( 'paystack' ) . '%';
		$res = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT txn, SUM(amount) AS total, MIN(entry_date) AS d FROM ' . NURAX_Desk_Accounts::table() . " WHERE deleted = 0 AND category = 'fees' AND method NOT IN ('card','bank') AND payee NOT LIKE %s AND description NOT LIKE %s AND note NOT LIKE %s AND entry_date BETWEEN %s AND %s GROUP BY txn ORDER BY d ASC, txn ASC", $ps, $ps, $ps, $from, $to ) ); // phpcs:ignore WordPress.DB
		$sum  = 0;
		$txns = array();
		foreach ( $res as $v ) {
			$sum += (float) $v->total;
			if ( '' !== (string) $v->txn ) {
				$txns[] = (string) $v->txn;
			}
		}
		return array( 'total' => round( $sum, 2 ), 'txns' => $txns );
	}

	private static function charge_ref( $from, $to ) {
		return 'CHG' . gmdate( 'ymd', strtotime( $from ) ) . '-' . gmdate( 'ymd', strtotime( $to ) );
	}

	/* ------------------------------------------------------------ handlers */

	private static function guard( $nonce ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Only the owner can manage the accounts.', 'nura-experience' ), 403 );
		}
		check_admin_referer( $nonce );
	}

	public static function h_read() {
		self::guard( 'nurax_mpesa_read' );
		$text = isset( $_POST['statement'] ) ? (string) wp_unslash( $_POST['statement'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- parsed, never stored as HTML.
		if ( ! empty( $_FILES['statement_file']['tmp_name'] ) && is_uploaded_file( $_FILES['statement_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$name = strtolower( (string) $_FILES['statement_file']['name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( preg_match( '/\.(csv|txt|tsv)$/', $name ) && filesize( $_FILES['statement_file']['tmp_name'] ) < 3 * MB_IN_BYTES ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$text .= "\n" . file_get_contents( $_FILES['statement_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.Security.ValidatedSanitizedInput
			} else {
				wp_safe_redirect( self::url( array( 'nd_msg' => 'refused', 'nd_err' => 'Upload the CSV export, or open the PDF, select all, copy and paste the text into the box.' ) ) );
				exit;
			}
		}
		$rows = self::parse( $text );
		if ( ! $rows ) {
			wp_safe_redirect( self::url( array( 'nd_msg' => 'refused', 'nd_err' => 'No M-Pesa transactions were found in what was pasted. Copy the whole statement, including the receipt numbers and dates.' ) ) );
			exit;
		}
		set_transient( self::RUN . get_current_user_id(), array( 'rows' => $rows, 'at' => current_time( 'mysql' ) ), DAY_IN_SECONDS );
		wp_safe_redirect( self::url() );
		exit;
	}

	private static function insert_voucher( $date, $lines, $method, $ref, $payee, $note ) {
		global $wpdb;
		$in  = NURAX_Desk_Accounts::is_in( $lines[0]['category'] );
		$txn = NURAX_Desk_Accounts::next_txn( $in );
		$now = current_time( 'mysql' );
		foreach ( $lines as $ln ) {
			$wpdb->insert( NURAX_Desk_Accounts::table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'entry_date'  => $date,
				'category'    => $ln['category'],
				'description' => mb_substr( $ln['description'], 0, 190 ),
				'amount'      => round( (float) $ln['amount'], 2 ),
				'method'      => $method,
				'ref'         => mb_substr( $ref, 0, 60 ),
				'payee'       => mb_substr( $payee, 0, 120 ),
				'note'        => mb_substr( $note, 0, 255 ),
				'created'     => $now,
				'created_by'  => get_current_user_id(),
				'txn'         => $txn,
			) );
			NURAX_Desk_Accounts::audit( $wpdb->insert_id, $txn, 'added', array( 'amount' => array( '', (string) $ln['amount'] ), 'category' => array( '', $ln['category'] ), 'description' => array( '', $ln['description'] ) ), 'From M-Pesa statement' );
		}
		NURAX_Desk_Accounts::bust();
		return $txn;
	}

	public static function h_act() {
		self::guard( 'nurax_mpesa_act' );
		global $wpdb;
		$run  = get_transient( self::RUN . get_current_user_id() );
		$rows = $run && ! empty( $run['rows'] ) ? $run['rows'] : array();
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$rc   = isset( $_POST['receipt'] ) ? NURAX_Desk_Accounts::norm_ref( sanitize_text_field( wp_unslash( $_POST['receipt'] ) ) ) : '';
		$row  = null;
		foreach ( $rows as $r ) {
			if ( $r['receipt'] === $rc ) {
				$row = $r;
			}
		}
		$msg = 'saved';
		$err = '';
		if ( 'clear' === $do ) {
			delete_transient( self::RUN . get_current_user_id() );
			wp_safe_redirect( self::url() );
			exit;
		}
		if ( 'charges' === $do && $rows ) {
			$from  = $rows[0]['date'];
			$to    = end( $rows )['date'];
			$total = 0;
			foreach ( $rows as $r ) {
				$total += $r['charge'];
			}
			$total = round( $total, 2 );
			$booked = self::charges_booked( $from, $to );
			if ( $total <= 0 || self::charges_recorded( $from, $to, $total ) ) {
				$msg = 'refused';
				$err = 'The M-Pesa charges for this statement are already in the books.';
			} elseif ( $booked['total'] > 0 ) {
				// 1.138.0: part of the charges is already in hand-entered vouchers; recording the full total would count them twice.
				$msg = 'refused';
				$err = 'Some M-Pesa charges for this period are already in the books (' . NURAX_Desk::money( $booked['total'] ) . ' in ' . implode( ', ', $booked['txns'] ) . '). Add only the difference, ' . NURAX_Desk::money( round( $total - $booked['total'], 2 ) ) . ', as a voucher.';
			} else {
				self::insert_voucher( $to, array( array( 'category' => 'fees', 'description' => 'M-Pesa charges ' . date_i18n( 'j M', strtotime( $from ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $to ) ) . ' (per statement)', 'amount' => $total ) ), 'mpesa', self::charge_ref( $from, $to ), 'Safaricom', 'Recorded from the M-Pesa statement.' );
			}
		} elseif ( $row && in_array( $do, array( 'expense', 'income', 'float_back' ), true ) ) {
			if ( self::ledger_by_ref( $row['receipt'] ) || ( 'expense' !== $do && self::order_by_code( $row['receipt'] ) ) ) {
				$msg = 'refused';
				$err = $row['receipt'] . ' is already in the books.';
			} else {
				$cats = NURAX_Desk_Accounts::categories();
				$cat  = 'expense' === $do ? ( isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : 'other' ) : $do;
				$cat  = 'income' === $do ? 'income' : $cat;
				$desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
				if ( ! isset( $cats[ $cat ] ) || ( 'expense' === $do && NURAX_Desk_Accounts::is_in( $cat ) ) ) {
					$msg = 'refused';
					$err = 'Choose what the payment was for.';
				} else {
					$desc = '' !== $desc ? $desc : $cats[ $cat ][0];
					$txn  = self::insert_voucher( $row['date'], array( array( 'category' => $cat, 'description' => $desc, 'amount' => abs( $row['amount'] ) ) ), 'mpesa', $row['receipt'], $row['name'], 'Recorded from the M-Pesa statement.' );
					$err  = $row['receipt'] . ' recorded as ' . $txn . '.';
				}
			}
		} elseif ( $row && 'paystack_fee' === $do ) {
			// 1.130.0: book the Paystack fee (order total minus payout) under M-Pesa, bank & card charges.
			$o   = isset( $_POST['target'] ) ? wc_get_order( absint( $_POST['target'] ) ) : null;
			$fee = $o ? round( (float) $o->get_total() - abs( $row['amount'] ), 2 ) : 0;
			if ( ! $o || 'paystack' !== $row['kind'] || $fee <= 0 || $fee > (float) $o->get_total() * 0.06 + 1 ) {
				$msg = 'refused';
				$err = 'The Paystack fee could not be worked out for this payout.';
			} elseif ( self::ledger_by_ref( $row['receipt'] ) ) {
				$msg = 'refused';
				$err = 'The fee for payout ' . $row['receipt'] . ' is already in the books.';
			} else {
				$txn = self::insert_voucher( $row['date'], array( array( 'category' => 'fees', 'description' => 'Paystack fee on order #' . $o->get_order_number() . ' (payout ' . $row['receipt'] . ')', 'amount' => $fee ) ), 'card', $row['receipt'], 'Paystack', 'Order total ' . $o->get_total() . ' less payout ' . abs( $row['amount'] ) . ', from the M-Pesa statement.' );
				$err = 'Paystack fee ' . NURAX_Desk::money( $fee ) . ' for order #' . $o->get_order_number() . ' recorded as ' . $txn . '.';
			}
		} elseif ( $row && 'link_voucher' === $do ) {
			$txn = isset( $_POST['target'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['target'] ) ) ) : '';
			if ( self::ledger_by_ref( $row['receipt'] ) || ! preg_match( '/^PV-\d{4}$/', $txn ) ) {
				$msg = 'refused';
				$err = 'That voucher could not be linked.';
			} else {
				$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . NURAX_Desk_Accounts::table() . " WHERE txn = %s AND deleted = 0 AND ref = ''", $txn ) ); // phpcs:ignore WordPress.DB
				foreach ( $ids as $id ) {
					$wpdb->update( NURAX_Desk_Accounts::table(), array( 'ref' => $row['receipt'], 'method' => 'mpesa', 'payee' => $row['name'], 'updated' => current_time( 'mysql' ), 'updated_by' => get_current_user_id() ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					NURAX_Desk_Accounts::audit( $id, $txn, 'edited', array( 'ref' => array( '', $row['receipt'] ) ), 'Matched to M-Pesa statement' );
				}
				NURAX_Desk_Accounts::bust();
				$err = $row['receipt'] . ' linked to ' . $txn . '.';
			}
		} elseif ( $row && 'link_order' === $do ) {
			$o = isset( $_POST['target'] ) ? wc_get_order( absint( $_POST['target'] ) ) : null;
			if ( ! $o || $o->get_transaction_id() || self::order_by_code( $row['receipt'] ) ) {
				$msg = 'refused';
				$err = 'That order could not be linked (it already has a code, or the code is used elsewhere).';
			} else {
				$o->set_transaction_id( $row['receipt'] );
				$o->save();
				$o->add_order_note( 'M-Pesa ' . $row['receipt'] . ' (' . NURAX_Desk::money( $row['amount'] ) . ', ' . $row['date'] . ', ' . $row['name'] . ') linked from the M-Pesa statement.' );
				$err = $row['receipt'] . ' linked to order #' . $o->get_order_number() . '.';
			}
		}
		wp_safe_redirect( self::url( array( 'nd_msg' => $msg, 'nd_err' => $err ) ) );
		exit;
	}

	/* ------------------------------------------------------------ screens */

	public static function render( $keep ) {
		$run   = get_transient( self::RUN . get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$note  = isset( $_GET['nd_err'] ) ? sanitize_text_field( wp_unslash( $_GET['nd_err'] ) ) : '';
		$bad   = isset( $_GET['nd_msg'] ) && 'refused' === $_GET['nd_msg']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="na-card" style="margin-top:12px">';
		if ( '' !== $note ) {
			echo '<p class="na-alert' . ( $bad ? ' na-alert--err' : '' ) . '">' . esc_html( $note ) . '</p>';
		}
		echo '<h3>Check the M-Pesa statement against the books</h3>';
		echo '<p>Open the till statement PDF from Safaricom, select all (Ctrl+A), copy and paste it below, or upload the CSV export. Nothing is saved until you press a button on a line.</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nurax_mpesa_read' );
		echo '<input type="hidden" name="action" value="nurax_mpesa_read">';
		echo '<textarea id="nurax-mp-text" name="statement" rows="5" style="width:100%" placeholder="Paste the statement text here"></textarea>';
		echo '<p><label>Or choose the statement file (PDF or CSV) <input id="nurax-mp-file" type="file" name="statement_file" accept=".pdf,.csv,.txt,.tsv"></label> <button class="nd-btn nd-btn--primary">Check statement</button> <span id="nurax-mp-st"></span></p></form></div>';
		// PDF statements are read in the browser (pdf.js) into lines of text, then checked like pasted text. The file itself is not uploaded.
		echo '<script>(function(){var f=document.getElementById("nurax-mp-file"),t=document.getElementById("nurax-mp-text"),st=document.getElementById("nurax-mp-st");if(!f)return;'
			. 'var B="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/";function lib(cb){if(window.pdfjsLib)return cb();var s=document.createElement("script");s.src=B+"pdf.min.js";s.onload=function(){pdfjsLib.GlobalWorkerOptions.workerSrc=B+"pdf.worker.min.js";cb();};s.onerror=function(){st.textContent="Could not load the PDF reader. Open the PDF, select all, copy and paste instead.";};document.head.appendChild(s);}'
			. 'f.addEventListener("change",function(){var file=f.files[0];if(!file||!/\\.pdf$/i.test(file.name))return;st.textContent="Reading the PDF...";var rd=new FileReader();rd.onload=function(){lib(function(){pdfjsLib.getDocument({data:new Uint8Array(rd.result)}).promise.then(function(pdf){var out=[],n=pdf.numPages,i=1;function page(){if(i>n){t.value=out.join("\\n");f.value="";st.textContent="PDF read: "+n+" page(s). Press Check statement.";return;}pdf.getPage(i).then(function(pg){return pg.getTextContent();}).then(function(tc){var L={};tc.items.forEach(function(it){if(!it.str||!it.str.trim())return;var k=Math.round(it.transform[5]/2);(L[k]=L[k]||[]).push(it);});Object.keys(L).map(Number).sort(function(a,b){return b-a;}).forEach(function(k){var r=L[k].sort(function(a,b){return a.transform[4]-b.transform[4];}),s="",last=null;r.forEach(function(it){var x=it.transform[4];if(last!==null)s+=(x-last>8?"   ":" ");s+=it.str.trim();last=x+(it.width||0);});out.push(s);});i++;page();});}page();}).catch(function(){st.textContent="This PDF could not be read. If it is password protected, use the unlocked copy.";});});};rd.readAsArrayBuffer(file);});})();</script>';
		self::render_key_box();
		if ( ! $run || empty( $run['rows'] ) ) {
			return;
		}
		$rows  = self::match( $run['rows'] );
		$from  = $rows[0]['date'];
		$to    = end( $rows )['date'];
		$in    = array_filter( $rows, function ( $r ) {
			return $r['amount'] > 0;
		} );
		$out   = array_filter( $rows, function ( $r ) {
			return $r['amount'] < 0;
		} );
		$chg   = 0;
		foreach ( $rows as $r ) {
			$chg += $r['charge'];
		}
		$chg   = round( $chg, 2 );
		$sum   = function ( $list, $st = null ) {
			$t = 0;
			$n = 0;
			foreach ( $list as $r ) {
				if ( null === $st || in_array( $r['status'], (array) $st, true ) ) {
					$t += abs( $r['amount'] );
					$n++;
				}
			}
			return array( $n, $t );
		};
		$ok = array( 'matched', 'likely' );
		list( $ni, $ti ) = $sum( $in );
		list( $mi, $tmi ) = $sum( $in, $ok );
		list( $no, $to_ ) = $sum( $out );
		list( $mo, $tmo ) = $sum( $out, $ok );
		$crec = self::charges_recorded( $from, $to, $chg );
		$cbk  = $crec ? null : self::charges_booked( $from, $to ); // 1.138.0
		if ( $crec ) {
			$ctxt = 'In the books (' . $crec . ')';
		} elseif ( $cbk && $cbk['total'] > 0 ) {
			$cdif = round( $chg - $cbk['total'], 2 );
			$ctxt = NURAX_Desk::money( $cbk['total'] ) . ' in the books (' . implode( ', ', $cbk['txns'] ) . ') | ' . ( $cdif > 0 ? NURAX_Desk::money( $cdif ) . ' not in the books yet' : NURAX_Desk::money( -$cdif ) . ' more in the books than on the statement' );
		} else {
			$ctxt = 'Not in the books yet';
		}
		echo '<div class="nd-kpis na-kpis" style="margin-top:12px">';
		NURAX_Desk::kpi( 'Money in', NURAX_Desk::money( $ti ), $mi . ' of ' . $ni . ' matched | ' . NURAX_Desk::money( $ti - $tmi ) . ' not in the books', $mi === $ni ? 'green' : 'red' );
		NURAX_Desk::kpi( 'Money out', NURAX_Desk::money( $to_ ), $mo . ' of ' . $no . ' matched | ' . NURAX_Desk::money( $to_ - $tmo ) . ' not in the books', $mo === $no ? 'green' : 'red' );
		NURAX_Desk::kpi( 'M-Pesa charges', NURAX_Desk::money( $chg ), $ctxt, $crec ? 'green' : 'red' );
		echo '</div>';
		echo '<p>Statement ' . esc_html( date_i18n( 'j M', strtotime( $from ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $to ) ) ) . ', read ' . esc_html( $run['at'] ) . '. ';
		echo self::act_btn( 'clear', '', 'Clear this statement', 'nd-btn--ghost nd-btn--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $crec && $chg > 0 && ! ( $cbk && $cbk['total'] > 0 ) ) { // 1.138.0: no full-total button once part is booked.
			echo ' ' . self::act_btn( 'charges', '', 'Record ' . NURAX_Desk::money( $chg ) . ' M-Pesa charges', 'nd-btn--primary nd-btn--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</p>';
		self::table( 'Money in', $in, 'in' );
		self::table( 'Money out', $out, 'out' );
	}

	private static function act_btn( $do, $receipt, $label, $cls, $extra = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . wp_nonce_field( 'nurax_mpesa_act', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="nurax_mpesa_act"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="receipt" value="' . esc_attr( $receipt ) . '">' . $extra . '<button class="nd-btn ' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function table( $title, $rows, $dir ) {
		$lab  = array( 'matched' => 'Matched', 'likely' => 'Likely match', 'differs' => 'Amount differs', 'unmatched' => 'Not in the books', 'charge_only' => 'Charge only' );
		$col  = array( 'matched' => '#00a32a', 'likely' => '#2271b1', 'differs' => '#dba617', 'unmatched' => '#d63638', 'charge_only' => '#787c82' );
		$cats = NURAX_Desk_Accounts::categories();
		echo '<h3 style="margin-top:18px">' . esc_html( $title ) . '</h3><table class="widefat striped"><thead><tr><th>Date</th><th>M-Pesa code</th><th>From / to</th><th style="text-align:right">Amount</th><th>In the books</th><th>Action</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( date_i18n( 'j M', strtotime( $r['date'] ) ) . ' ' . substr( $r['time'], 0, 5 ) ) . '</td><td><code>' . esc_html( $r['receipt'] ) . '</code></td><td>' . esc_html( 'paystack' === $r['kind'] ? 'Paystack payout' : $r['name'] ) . '</td><td style="text-align:right">' . esc_html( NURAX_Desk::money( abs( $r['amount'] ) ) ) . '</td>';
			echo '<td><span style="color:' . esc_attr( $col[ $r['status'] ] ) . ';font-weight:600">' . esc_html( $lab[ $r['status'] ] ) . '</span>' . ( $r['match'] ? '<br>' . ( $r['link'] ? '<a href="' . esc_url( $r['link'] ) . '">' . esc_html( $r['match'] ) . '</a>' : esc_html( $r['match'] ) ) : '' ) . '</td><td>';
			if ( 'paystack' === $r['kind'] && ! empty( $r['order_id'] ) ) {
				if ( ! empty( $r['fee_txn'] ) ) {
					echo '<span style="color:#00a32a">Paystack fee recorded (' . esc_html( $r['fee_txn'] ) . ')</span>';
				} elseif ( $r['fee'] > 0 ) {
					echo self::act_btn( 'paystack_fee', $r['receipt'], 'Record Paystack fee ' . NURAX_Desk::money( $r['fee'] ), 'nd-btn--primary nd-btn--sm', '<input type="hidden" name="target" value="' . esc_attr( $r['order_id'] ) . '">' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}
			if ( 'unmatched' === $r['status'] ) {
				foreach ( $r['suggest'] as $s ) {
					echo '<div>' . self::act_btn( 'order' === $s['type'] ? 'link_order' : 'link_voucher', $r['receipt'], 'Link to ' . $s['label'], 'nd-btn--ghost nd-btn--sm', '<input type="hidden" name="target" value="' . esc_attr( $s['id'] ) . '">' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( 'out' === $dir ) {
					$opt = '';
					foreach ( $cats as $k => $c ) {
						if ( ! NURAX_Desk_Accounts::is_in( $k ) && 'prepaid' !== $k ) {
							$opt .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $c[0] ) . '</option>';
						}
					}
					echo self::act_btn( 'expense', $r['receipt'], 'Record expense', 'nd-btn--primary nd-btn--sm', '<select name="category">' . $opt . '</select> <input type="text" name="description" placeholder="What was it for?" maxlength="190"> ' ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '<div>' . self::act_btn( 'income', $r['receipt'], 'Record as other income', 'nd-btn--ghost nd-btn--sm', '<input type="text" name="description" placeholder="What was it for?" maxlength="190"> ' ) . ' ' . self::act_btn( 'float_back', $r['receipt'], 'Staff float returned', 'nd-btn--ghost nd-btn--sm' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
					echo '<small>A sale? <a href="' . esc_url( admin_url( 'admin.php?page=wc-orders&action=new' ) ) . '">Add the order</a> with this code in Transaction ID, then check the statement again.</small>';
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Staff float: given, spent, returned and what each person still holds. */
	public static function float_summary() {
		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT entry_date, category, description, amount, method, ref, payee, txn FROM ' . NURAX_Desk_Accounts::table() . " WHERE deleted = 0 AND ( category IN ('float','float_back') OR method = 'float' ) ORDER BY entry_date ASC, id ASC" ); // phpcs:ignore WordPress.DB
		$p = array();
		foreach ( $rows as $e ) {
			$k = strtolower( trim( $e->payee ) );
			$k = '' === $k ? '(no name)' : $k;
			if ( ! isset( $p[ $k ] ) ) {
				$p[ $k ] = array( 'name' => '' !== trim( $e->payee ) ? trim( $e->payee ) : '(no name)', 'given' => 0, 'spent' => 0, 'back' => 0, 'lines' => array() );
			}
			$a = (float) $e->amount;
			if ( 'float' === $e->category ) {
				$p[ $k ]['given'] += $a;
			} elseif ( 'float_back' === $e->category ) {
				$p[ $k ]['back'] += $a;
			} else {
				$p[ $k ]['spent'] += $a;
			}
			$p[ $k ]['lines'][] = $e;
		}
		return $p;
	}

	public static function render_float( $keep ) {
		$p    = self::float_summary();
		$cats = NURAX_Desk_Accounts::categories();
		echo '<div class="na-card" style="margin-top:12px"><h3>Staff float</h3>';
		echo '<p>When you send money to staff to spend for the shop, record it as a payment with category <strong>Staff float given</strong> and the staff member as payee. When they report what they bought, record each item with payment method <strong>Staff float</strong> and the same payee. Unspent money sent back is <strong>Staff float returned</strong>. Float is not a cost; only what is spent is.</p>';
		echo NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk_Accounts::PAGE, array( 'tab' => 'entries', 'edit' => 'new' ) ), '+ Record float or spending', 'nd-btn--primary' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $p ) {
			echo '<p>No staff float recorded yet.</p>';
			return;
		}
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>Staff</th><th style="text-align:right">Given</th><th style="text-align:right">Spent</th><th style="text-align:right">Returned</th><th style="text-align:right">Still holding</th></tr></thead><tbody>';
		foreach ( $p as $x ) {
			$bal = $x['given'] - $x['spent'] - $x['back'];
			echo '<tr><td>' . esc_html( $x['name'] ) . '</td><td style="text-align:right">' . esc_html( NURAX_Desk::money( $x['given'] ) ) . '</td><td style="text-align:right">' . esc_html( NURAX_Desk::money( $x['spent'] ) ) . '</td><td style="text-align:right">' . esc_html( NURAX_Desk::money( $x['back'] ) ) . '</td><td style="text-align:right;font-weight:600;color:' . ( $bal < -0.005 ? '#d63638' : 'inherit' ) . '">' . esc_html( NURAX_Desk::money( $bal ) ) . ( $bal < -0.005 ? ' (spent more than given: the shop owes them)' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		foreach ( $p as $x ) {
			echo '<h4 style="margin-top:16px">' . esc_html( $x['name'] ) . '</h4><table class="widefat"><tbody>';
			foreach ( $x['lines'] as $e ) {
				$t = 'float' === $e->category ? 'Given' : ( 'float_back' === $e->category ? 'Returned' : 'Spent: ' . ( isset( $cats[ $e->category ] ) ? $cats[ $e->category ][0] : $e->category ) );
				echo '<tr><td>' . esc_html( date_i18n( 'j M Y', strtotime( $e->entry_date ) ) ) . '</td><td>' . esc_html( $e->txn ) . '</td><td>' . esc_html( $t ) . '</td><td>' . esc_html( $e->description ) . '</td><td>' . esc_html( $e->ref ) . '</td><td style="text-align:right">' . esc_html( NURAX_Desk::money( $e->amount ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}
}

NURAX_Mpesa_Recon::hooks();
