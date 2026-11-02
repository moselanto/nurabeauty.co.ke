<?php
/**
 * NURA Desk - Accounts: expenses, sales and profit (NURA Experience 1.83.0).
 *
 * A simple, accountant-friendly book for the studio:
 *  - Record every shilling going out (rent, renovation, stock, salaries, M-Pesa charges...)
 *    and any income that does not come through the shop (e.g. a cash installation).
 *  - Sales come in automatically from WooCommerce (website orders and Point of sale), so they
 *    are never typed twice.
 *  - A Profit & Loss statement in the international layout (Revenue - Cost of sales =
 *    Gross profit - Operating expenses - One-off setup costs = Net profit), for any period.
 *  - Money that is NOT a cost is kept out of profit and shown separately: refundable rent
 *    deposits, furniture & equipment, owner capital and owner drawings.
 *  - Receipts (photo/PDF) attached to entries, CSV export for an accountant, soft delete with
 *    an audit trail (who added / edited / removed).
 *
 * Storage: {prefix}nurax_ledger. Owner-only (manage_options); filter nurax_accounts_cap.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Accounts {

	const DBV     = '1.0.0';
	const DBV_OPT = 'nurax_ledger_db';
	const PAGE    = 'nurax-accounts';

	public static function cap() {
		return (string) apply_filters( 'nurax_accounts_cap', 'manage_options' );
	}

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'admin_post_nurax_acc_save', array( __CLASS__, 'h_save' ) );
		add_action( 'admin_post_nurax_acc_delete', array( __CLASS__, 'h_delete' ) );
		add_action( 'admin_post_nurax_acc_csv', array( __CLASS__, 'h_csv' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'bust' ) );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'bust' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_ledger';
	}

	public static function maybe_install() {
		if ( self::DBV === get_option( self::DBV_OPT ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		$c = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_date date NOT NULL,
			category varchar(30) NOT NULL DEFAULT 'other',
			description varchar(190) NOT NULL DEFAULT '',
			amount decimal(14,2) NOT NULL DEFAULT 0,
			method varchar(16) NOT NULL DEFAULT 'mpesa',
			ref varchar(60) NOT NULL DEFAULT '',
			payee varchar(120) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			receipt_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated datetime NULL DEFAULT NULL,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			deleted tinyint(1) NOT NULL DEFAULT 0,
			deleted_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY entry_date (entry_date),
			KEY category (category),
			KEY deleted (deleted)
		) {$c};" );
		update_option( self::DBV_OPT, self::DBV, false );
		self::seed();
	}

	/** The owner's first entries (2 Nov 2026 brief). Added once; editable / removable like any entry. */
	private static function seed() {
		if ( get_option( 'nurax_ledger_seeded' ) ) {
			return;
		}
		global $wpdb;
		$now  = current_time( 'mysql' );
		$note = 'Added at set-up from the owner\'s brief - please check the date and payment details.';
		foreach ( array(
			array( 'rent', 'Shop rent - Stanbank House, 2nd Floor, Room 20 (first month)', 22000 ),
			array( 'deposit', 'Rent deposit - 2 months (refundable)', 44000 ),
			array( 'legal', 'Lease agreement fee', 3000 ),
		) as $r ) {
			$wpdb->insert( self::table(), array( 'entry_date' => '2026-11-01', 'category' => $r[0], 'description' => $r[1], 'amount' => $r[2], 'method' => 'mpesa', 'note' => $note, 'created' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		update_option( 'nurax_ledger_seeded', time(), false );
	}

	/**
	 * key => [ label, group, hint ]. Groups:
	 *  cogs  - cost of sales (what the wigs/products you sold cost you)
	 *  opex  - operating expenses (the running costs of the shop)
	 *  setup - one-off start-up costs (renovation, lease fees, licences)
	 *  income - other income (not through the website or the till)
	 *  asset - money you still own: refundable deposits, furniture & equipment
	 *  owner - owner's capital in / drawings out
	 */
	public static function categories() {
		return apply_filters( 'nurax_ledger_categories', array(
			'stock'      => array( 'Stock purchases (wigs, hair, products)', 'cogs', 'Wigs, hair and products bought to sell.' ),
			'freight'    => array( 'Import, freight & customs', 'cogs', 'Shipping in, clearing, duty on stock.' ),
			'packaging'  => array( 'Packaging & wig accessories', 'cogs', 'Boxes, bags, caps, stands given with sales.' ),
			'rent'       => array( 'Rent', 'opex', 'Monthly shop rent.' ),
			'salaries'   => array( 'Salaries & wages', 'opex', 'Staff pay, stylists, casuals.' ),
			'utilities'  => array( 'Electricity, water & internet', 'opex', 'KPLC tokens, water, Wi-Fi, airtime.' ),
			'marketing'  => array( 'Marketing & advertising', 'opex', 'Meta/Google ads, influencers, printing.' ),
			'delivery'   => array( 'Delivery & transport', 'opex', 'Riders, matatu/bus parcels, fuel.' ),
			'fees'       => array( 'M-Pesa, bank & card charges', 'opex', 'Transaction and gateway fees.' ),
			'software'   => array( 'Website, software & subscriptions', 'opex', 'Hosting, domain, plugins, apps.' ),
			'repairs'    => array( 'Repairs & maintenance', 'opex', 'Fixes in the shop.' ),
			'supplies'   => array( 'Shop supplies & cleaning', 'opex', 'Stationery, cleaning, tea, small items.' ),
			'insurance'  => array( 'Insurance', 'opex', 'Shop or stock insurance.' ),
			'tax'        => array( 'Taxes & government charges', 'opex', 'Turnover tax, county levies (not your own drawings).' ),
			'other'      => array( 'Other expense', 'opex', 'Anything that does not fit above.' ),
			'renovation' => array( 'Shop renovation & fit-out', 'setup', 'Painting, shelving, lighting, signage, branding.' ),
			'legal'      => array( 'Legal, lease & registration fees', 'setup', 'Lease agreement, business registration.' ),
			'licences'   => array( 'Licences & permits', 'setup', 'County single business permit, fire, health.' ),
			'income'     => array( 'Other income', 'income', 'Cash/M-Pesa income not rung up at the till (e.g. a service).' ),
			'deposit'    => array( 'Deposits paid (refundable)', 'asset', 'Rent or utility deposits you get back - not a cost.' ),
			'equipment'  => array( 'Furniture & equipment', 'asset', 'Chairs, mirrors, wig heads, POS phone, steamers - kept for years.' ),
			'capital'    => array( 'Owner capital in', 'owner', 'Money the owner puts into the business.' ),
			'drawings'   => array( 'Owner drawings out', 'owner', 'Money the owner takes out for personal use.' ),
		) );
	}

	public static function groups() {
		return array(
			'cogs'   => 'Cost of sales',
			'opex'   => 'Operating expenses',
			'setup'  => 'One-off setup costs',
			'income' => 'Other income',
			'asset'  => 'Assets & deposits (not in profit)',
			'owner'  => 'Owner capital & drawings (not in profit)',
		);
	}

	public static function methods() {
		return array( 'mpesa' => 'M-Pesa', 'cash' => 'Cash', 'bank' => 'Bank transfer', 'card' => 'Card', 'other' => 'Other' );
	}

	public static function group_of( $cat ) {
		$c = self::categories();
		return isset( $c[ $cat ] ) ? $c[ $cat ][1] : 'opex';
	}

	/* ------------------------------------------------------------ periods */

	/** [ from, to, label ] for a preset or custom range (Y-m-d, inclusive). */
	public static function period( $p, $from = '', $to = '' ) {
		$ts = current_time( 'timestamp' );
		$y  = (int) gmdate( 'Y', $ts );
		$m  = (int) gmdate( 'n', $ts );
		switch ( $p ) {
			case 'last_month':
				$f = gmdate( 'Y-m-01', gmmktime( 0, 0, 0, $m - 1, 1, $y ) );
				return array( $f, gmdate( 'Y-m-t', strtotime( $f ) ), gmdate( 'F Y', strtotime( $f ) ) );
			case 'quarter':
				$q = (int) ceil( $m / 3 );
				$f = gmdate( 'Y-m-d', gmmktime( 0, 0, 0, ( $q - 1 ) * 3 + 1, 1, $y ) );
				return array( $f, gmdate( 'Y-m-t', gmmktime( 0, 0, 0, $q * 3, 1, $y ) ), 'Q' . $q . ' ' . $y );
			case 'year':
				return array( $y . '-01-01', $y . '-12-31', (string) $y );
			case 'all':
				return array( '2000-01-01', '2099-12-31', 'All time' );
			case 'custom':
				$f = NURAX_Desk::valid_date( $from );
				$t = NURAX_Desk::valid_date( $to );
				if ( $f && $t && $f <= $t ) {
					return array( $f, $t, date_i18n( 'j M Y', strtotime( $f ) ) . ' - ' . date_i18n( 'j M Y', strtotime( $t ) ) );
				}
				// fall through to this month
			default:
				return array( gmdate( 'Y-m-01', $ts ), gmdate( 'Y-m-t', $ts ), gmdate( 'F Y', $ts ) );
		}
	}

	/* ------------------------------------------------------------ data */

	public static function entries( $from, $to, $cat = '', $q = '' ) {
		global $wpdb;
		$t   = self::table();
		$sql = "SELECT * FROM {$t} WHERE deleted = 0 AND entry_date BETWEEN %s AND %s";
		$a   = array( $from, $to );
		if ( $cat ) {
			if ( isset( self::groups()[ $cat ] ) ) {
				$keys = array_keys( array_filter( self::categories(), function ( $c ) use ( $cat ) {
					return $c[1] === $cat;
				} ) );
				$sql .= ' AND category IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')';
				$a    = array_merge( $a, $keys );
			} else {
				$sql .= ' AND category = %s';
				$a[]  = $cat;
			}
		}
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$sql .= ' AND ( description LIKE %s OR payee LIKE %s OR ref LIKE %s OR note LIKE %s )';
			array_push( $a, $like, $like, $like, $like );
		}
		$sql .= ' ORDER BY entry_date DESC, id DESC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $a ) ); // phpcs:ignore WordPress.DB
	}

	public static function bust() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_nurax\\_acc\\_%' OR option_name LIKE '\\_transient\\_timeout\\_nurax\\_acc\\_%'" ); // phpcs:ignore WordPress.DB
	}

	/** WooCommerce sales (website + Point of sale) paid in the period. */
	public static function sales( $from, $to ) {
		$key = 'nurax_acc_s_' . md5( $from . $to );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$out = array( 'online' => 0.0, 'pos' => 0.0, 'shipping' => 0.0, 'refunds' => 0.0, 'orders' => 0, 'fees' => 0.0 );
		if ( function_exists( 'wc_get_orders' ) ) {
			$ids = wc_get_orders( array(
				'type'         => 'shop_order',
				'status'       => array( 'wc-processing', 'wc-completed' ),
				'date_created' => $from . '...' . $to . ' 23:59:59',
				'limit'        => -1,
				'return'       => 'ids',
			) );
			foreach ( (array) $ids as $id ) {
				$o = wc_get_order( $id );
				if ( ! $o ) {
					continue;
				}
				$net = (float) $o->get_total() - (float) $o->get_shipping_total() - (float) $o->get_shipping_tax();
				if ( 'nura_pos' === $o->get_created_via() ) {
					$out['pos'] += $net;
				} else {
					$out['online'] += $net;
				}
				$out['shipping'] += (float) $o->get_shipping_total() + (float) $o->get_shipping_tax();
				$out['refunds']  += (float) $o->get_total_refunded();
				$out['orders']++;
			}
		}
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Profit & Loss for a period. */
	public static function pnl( $from, $to ) {
		$s    = self::sales( $from, $to );
		$cats = self::categories();
		$by   = array();
		foreach ( self::entries( $from, $to ) as $e ) {
			$by[ $e->category ] = ( isset( $by[ $e->category ] ) ? $by[ $e->category ] : 0 ) + (float) $e->amount;
		}
		$sum = function ( $group ) use ( $by, $cats ) {
			$t = 0;
			foreach ( $by as $k => $v ) {
				if ( isset( $cats[ $k ] ) && $cats[ $k ][1] === $group ) {
					$t += $v;
				}
			}
			return $t;
		};
		$r = array( 'sales' => $s, 'by' => $by );
		$r['other_income'] = $sum( 'income' );
		$r['revenue']      = $s['online'] + $s['pos'] + $s['shipping'] + $r['other_income'] - $s['refunds'];
		$r['cogs']         = $sum( 'cogs' );
		$r['gross']        = $r['revenue'] - $r['cogs'];
		$r['opex']         = $sum( 'opex' );
		$r['setup']        = $sum( 'setup' );
		$r['operating']    = $r['gross'] - $r['opex'];
		$r['net']          = $r['operating'] - $r['setup'];
		$r['asset']        = $sum( 'asset' );
		$r['capital']      = isset( $by['capital'] ) ? $by['capital'] : 0;
		$r['drawings']     = isset( $by['drawings'] ) ? $by['drawings'] : 0;
		$r['spent']        = $r['cogs'] + $r['opex'] + $r['setup'] + $r['asset'];
		return $r;
	}

	/* ------------------------------------------------------------ handlers */

	private static function guard( $nonce ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Only the owner can manage the accounts.', 'nura-experience' ), 403 );
		}
		check_admin_referer( $nonce );
	}

	private static function back( $args ) {
		$keep = array();
		foreach ( array( 'p', 'from', 'to', 'cat', 'tab' ) as $k ) {
			if ( isset( $_POST[ '_' . $k ] ) && '' !== $_POST[ '_' . $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$keep[ $k ] = sanitize_text_field( wp_unslash( $_POST[ '_' . $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
		wp_safe_redirect( NURAX_Desk::url( self::PAGE, array_merge( $keep, $args ) ) );
		exit;
	}

	public static function h_save() {
		self::guard( 'nurax_acc_save' );
		global $wpdb;
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$date = NURAX_Desk::valid_date( isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : '' );
		$cat  = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$amt  = isset( $_POST['amount'] ) ? (float) preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST['amount'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		if ( ! $date || ! isset( self::categories()[ $cat ] ) || $amt <= 0 || '' === $desc ) {
			self::back( array( 'nd_msg' => 'invalid', 'tab' => 'entries', 'edit' => $id ? $id : 'new' ) );
		}
		$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'mpesa';
		$row    = array(
			'entry_date'  => $date,
			'category'    => $cat,
			'description' => mb_substr( $desc, 0, 190 ),
			'amount'      => round( $amt, 2 ),
			'method'      => isset( self::methods()[ $method ] ) ? $method : 'other',
			'ref'         => mb_substr( isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '', 0, 60 ),
			'payee'       => mb_substr( isset( $_POST['payee'] ) ? sanitize_text_field( wp_unslash( $_POST['payee'] ) ) : '', 0, 120 ),
			'note'        => mb_substr( isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '', 0, 255 ),
		);
		if ( ! empty( $_FILES['receipt']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$type = wp_check_filetype( sanitize_file_name( wp_unslash( $_FILES['receipt']['name'] ) ) );
			if ( in_array( $type['ext'], array( 'jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic' ), true ) ) {
				$att = media_handle_upload( 'receipt', 0, array( 'post_title' => 'Receipt - ' . $row['description'] ) );
				if ( ! is_wp_error( $att ) ) {
					$row['receipt_id'] = (int) $att;
				}
			}
		}
		$uid = get_current_user_id();
		$now = current_time( 'mysql' );
		if ( $id ) {
			$row['updated']    = $now;
			$row['updated_by'] = $uid;
			$wpdb->update( self::table(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$row['created']    = $now;
			$row['created_by'] = $uid;
			$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'accounts', ( $id ? 'Edited' : 'Added' ) . ' ' . self::categories()[ $cat ][0] . ': ' . $row['description'] . ' (' . NURAX_Desk::money( $row['amount'] ) . ')' );
		}
		self::back( array( 'nd_msg' => 'saved', 'tab' => 'entries' ) );
	}

	public static function h_delete() {
		self::guard( 'nurax_acc_delete' );
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id ) {
			$wpdb->update( self::table(), array( 'deleted' => 1, 'deleted_by' => get_current_user_id(), 'updated' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
				NURAX_Staff::log( 'accounts', 'Removed ledger entry #' . $id );
			}
		}
		self::back( array( 'nd_msg' => 'deleted', 'tab' => 'entries' ) );
	}

	public static function h_csv() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'No permission', 403 );
		}
		check_admin_referer( 'nurax_acc_csv' );
		list( $from, $to, $label ) = self::period( isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : '', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		$cats = self::categories();
		$grp  = self::groups();
		$r    = self::pnl( $from, $to );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nura-accounts-' . $from . '-to-' . $to . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		fwrite( $f, "\xEF\xBB\xBF" );
		fputcsv( $f, array( 'NURA Beauty - Profit & Loss', $label ) );
		foreach ( array(
			array( 'Website sales', $r['sales']['online'] ), array( 'In-shop sales (Point of sale)', $r['sales']['pos'] ), array( 'Delivery charged', $r['sales']['shipping'] ),
			array( 'Other income', $r['other_income'] ), array( 'Less refunds', -$r['sales']['refunds'] ), array( 'Revenue', $r['revenue'] ),
			array( 'Cost of sales', -$r['cogs'] ), array( 'Gross profit', $r['gross'] ), array( 'Operating expenses', -$r['opex'] ),
			array( 'Operating profit', $r['operating'] ), array( 'One-off setup costs', -$r['setup'] ), array( 'Net profit', $r['net'] ),
			array( 'Assets & deposits (not in profit)', $r['asset'] ), array( 'Owner capital in', $r['capital'] ), array( 'Owner drawings out', $r['drawings'] ),
		) as $l ) {
			fputcsv( $f, array( $l[0], round( $l[1], 2 ) ) );
		}
		fputcsv( $f, array() );
		fputcsv( $f, array( 'Date', 'Group', 'Category', 'Description', 'Amount (KSh)', 'Paid by', 'Reference', 'Paid to / from', 'Note', 'Receipt' ) );
		foreach ( array_reverse( self::entries( $from, $to ) ) as $e ) {
			$g = self::group_of( $e->category );
			fputcsv( $f, array( $e->entry_date, $grp[ $g ], isset( $cats[ $e->category ] ) ? $cats[ $e->category ][0] : $e->category, $e->description, $e->amount, self::methods()[ $e->method ] ?? $e->method, $e->ref, $e->payee, $e->note, $e->receipt_id ? wp_get_attachment_url( $e->receipt_id ) : '' ) );
		}
		fclose( $f );
		exit;
	}

	/* ------------------------------------------------------------ page */

	private static function m( $n ) {
		$n = (float) $n;
		return ( $n < 0 ? '- ' : '' ) . NURAX_Desk::money( abs( $n ) );
	}

	private static function pct( $a, $b ) {
		return $b > 0 ? round( $a / $b * 100, 1 ) . '%' : '-';
	}

	public static function page() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Only the owner can open the accounts.', 'nura-experience' ) );
		}
		self::maybe_install();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$p    = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : 'month';
		$fr   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$tt   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$cat  = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : '';
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		// phpcs:enable
		list( $from, $to, $label ) = self::period( $p, $fr, $tt );
		$keep = array( 'p' => $p, 'from' => $fr, 'to' => $tt );
		$r    = self::pnl( $from, $to );
		$csv  = wp_nonce_url( add_query_arg( array_merge( $keep, array( 'action' => 'nurax_acc_csv' ) ), admin_url( 'admin-post.php' ) ), 'nurax_acc_csv' );
		$act  = NURAX_Desk::btn( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries', 'edit' => 'new' ) ) ), '+ Record expense or income', 'nd-btn--primary' )
			. '<a class="nd-btn nd-btn--ghost" href="' . esc_url( $csv ) . '">Download for accountant (CSV)</a>';
		NURAX_Desk::header( self::PAGE, 'Accounts', $act );

		echo '<div class="na">';
		// Period bar.
		echo '<form class="na-period" method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		echo '<div class="na-seg" role="group" aria-label="Period">';
		foreach ( array( 'month' => 'This month', 'last_month' => 'Last month', 'quarter' => 'This quarter', 'year' => 'This year', 'all' => 'All time' ) as $k => $l ) {
			echo '<a class="na-seg__b' . ( $k === $p ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array( 'p' => $k, 'tab' => $tab ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</div><div class="na-custom"><label>From <input type="date" name="from" value="' . esc_attr( 'custom' === $p ? $from : '' ) . '"></label><label>To <input type="date" name="to" value="' . esc_attr( 'custom' === $p ? $to : '' ) . '"></label><input type="hidden" name="p" value="custom"><button class="nd-btn nd-btn--ghost nd-btn--sm">Apply</button></div>';
		echo '<span class="na-period__l">' . esc_html( $label ) . '</span></form>';

		// KPIs.
		echo '<div class="nd-kpis na-kpis">';
		NURAX_Desk::kpi( 'Revenue', self::m( $r['revenue'] ), $r['sales']['orders'] . ' paid order(s) + other income', 'ink' );
		NURAX_Desk::kpi( 'Gross profit', self::m( $r['gross'] ), 'margin ' . self::pct( $r['gross'], $r['revenue'] ), $r['gross'] >= 0 ? 'gold' : 'red' );
		NURAX_Desk::kpi( 'Expenses', self::m( $r['cogs'] + $r['opex'] + $r['setup'] ), 'stock ' . self::m( $r['cogs'] ) . ' - running ' . self::m( $r['opex'] ) . ' - setup ' . self::m( $r['setup'] ), '' );
		NURAX_Desk::kpi( 'Net profit', self::m( $r['net'] ), $r['net'] >= 0 ? 'net margin ' . self::pct( $r['net'], $r['revenue'] ) : 'a loss for this period', $r['net'] >= 0 ? 'green' : 'red' );
		NURAX_Desk::kpi( 'Deposits & equipment', self::m( $r['asset'] ), 'yours to keep - not counted as a cost', '' );
		echo '</div>';

		$tabs = array( 'overview' => 'Profit & Loss', 'entries' => 'Expenses & income', 'breakdown' => 'Where the money goes' );
		echo '<nav class="nt-tabs na-tabs">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="nt-tab' . ( $k === $tab ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => $k ) ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';

		if ( 'entries' === $tab ) {
			self::render_entries( $from, $to, $cat, $q, $edit, $keep );
		} elseif ( 'breakdown' === $tab ) {
			self::render_breakdown( $r );
		} else {
			self::render_pnl( $r, $label );
		}
		echo '</div></div>';
	}

	private static function line( $label, $v, $cls = '', $sub = '' ) {
		echo '<tr class="' . esc_attr( $cls ) . '"><th scope="row">' . esc_html( $label ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</th><td>' . esc_html( self::m( $v ) ) . '</td></tr>';
	}

	private static function render_pnl( $r, $label ) {
		$cats = self::categories();
		$s    = $r['sales'];
		echo '<div class="na-grid"><section class="nd-card na-pnl"><header class="na-pnl__h"><span>NURA Beauty</span><h2>Profit &amp; Loss statement</h2><p>' . esc_html( $label ) . ' - Kenyan Shillings</p></header><table class="na-st">';
		echo '<tbody><tr class="na-st__sec"><th colspan="2">Revenue</th></tr>';
		self::line( 'Website sales', $s['online'], '', 'Paid website orders (excl. delivery)' );
		self::line( 'In-shop sales', $s['pos'], '', 'Point of sale' );
		self::line( 'Delivery charged to customers', $s['shipping'] );
		self::line( 'Other income', $r['other_income'] );
		if ( $s['refunds'] > 0 ) {
			self::line( 'Less: refunds', -$s['refunds'] );
		}
		self::line( 'Total revenue', $r['revenue'], 'na-st__tot' );
		echo '<tr class="na-st__sec"><th colspan="2">Cost of sales</th></tr>';
		foreach ( $cats as $k => $c ) {
			if ( 'cogs' === $c[1] && ! empty( $r['by'][ $k ] ) ) {
				self::line( $c[0], -$r['by'][ $k ] );
			}
		}
		self::line( 'Gross profit', $r['gross'], 'na-st__tot', 'Margin ' . self::pct( $r['gross'], $r['revenue'] ) );
		echo '<tr class="na-st__sec"><th colspan="2">Operating expenses</th></tr>';
		$any = false;
		foreach ( $cats as $k => $c ) {
			if ( 'opex' === $c[1] && ! empty( $r['by'][ $k ] ) ) {
				self::line( $c[0], -$r['by'][ $k ] );
				$any = true;
			}
		}
		if ( ! $any ) {
			echo '<tr class="na-st__empty"><td colspan="2">No running costs recorded in this period.</td></tr>';
		}
		self::line( 'Operating profit', $r['operating'], 'na-st__tot' );
		if ( $r['setup'] > 0 ) {
			echo '<tr class="na-st__sec"><th colspan="2">One-off setup costs</th></tr>';
			foreach ( $cats as $k => $c ) {
				if ( 'setup' === $c[1] && ! empty( $r['by'][ $k ] ) ) {
					self::line( $c[0], -$r['by'][ $k ] );
				}
			}
		}
		self::line( 'Net profit', $r['net'], 'na-st__net ' . ( $r['net'] >= 0 ? 'is-pos' : 'is-neg' ), 'Net margin ' . self::pct( $r['net'], $r['revenue'] ) );
		echo '</tbody></table></section>';

		echo '<aside class="na-side"><section class="nd-card na-bs"><h3>Not part of profit</h3><p class="na-muted">Money you paid out that you still own, and money moved in or out by the owner.</p><dl>';
		foreach ( $cats as $k => $c ) {
			if ( in_array( $c[1], array( 'asset', 'owner' ), true ) ) {
				echo '<div><dt>' . esc_html( $c[0] ) . '</dt><dd>' . esc_html( self::m( isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 ) ) . '</dd></div>';
			}
		}
		echo '</dl></section>';
		echo '<section class="nd-card na-cash"><h3>Cash out this period</h3><strong>' . esc_html( self::m( $r['spent'] + $r['drawings'] ) ) . '</strong><p class="na-muted">Everything paid out: stock, running costs, setup, deposits, equipment and drawings.</p></section>';
		echo '<section class="nd-card na-help"><h3>How profit is worked out</h3><ol><li><b>Revenue</b> - paid website and shop orders come in automatically, plus any other income you record.</li><li><b>Cost of sales</b> - what you paid for the stock you sell.</li><li><b>Operating expenses</b> - rent, staff, ads, delivery, charges.</li><li><b>Setup costs</b> - renovation and lease fees, shown on their own line so your running profit stays clear.</li><li>Deposits and equipment are <b>not</b> costs - you still own them.</li></ol></section></aside></div>';
	}

	private static function render_breakdown( $r ) {
		$cats  = self::categories();
		$items = array();
		foreach ( $r['by'] as $k => $v ) {
			if ( isset( $cats[ $k ] ) && in_array( $cats[ $k ][1], array( 'cogs', 'opex', 'setup', 'asset' ), true ) && $v > 0 ) {
				$items[ $k ] = $v;
			}
		}
		arsort( $items );
		$total = array_sum( $items );
		echo '<section class="nd-card na-bd"><h2>Where the money goes</h2>';
		if ( ! $items ) {
			echo '<p class="nd-empty">No expenses recorded in this period yet.</p></section>';
			return;
		}
		echo '<ul class="na-bars">';
		foreach ( $items as $k => $v ) {
			$w = $total > 0 ? max( 2, round( $v / $total * 100 ) ) : 0;
			echo '<li><span class="na-bars__l">' . esc_html( $cats[ $k ][0] ) . '<small>' . esc_html( self::groups()[ $cats[ $k ][1] ] ) . '</small></span><span class="na-bars__t"><i class="na-g--' . esc_attr( $cats[ $k ][1] ) . '" style="width:' . (int) $w . '%"></i></span><span class="na-bars__v">' . esc_html( NURAX_Desk::money( $v ) ) . ' <small>' . esc_html( self::pct( $v, $total ) ) . '</small></span></li>';
		}
		echo '</ul><p class="na-bd__tot">Total paid out <strong>' . esc_html( NURAX_Desk::money( $total ) ) . '</strong></p></section>';
	}

	private static function render_entries( $from, $to, $cat, $q, $edit, $keep ) {
		global $wpdb;
		$cats = self::categories();
		$grp  = self::groups();
		$row  = null;
		if ( $edit && 'new' !== $edit ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d AND deleted = 0', absint( $edit ) ) ); // phpcs:ignore WordPress.DB
		}
		$hid = '';
		foreach ( array_merge( $keep, array( 'cat' => $cat, 'tab' => 'entries' ) ) as $k => $v ) {
			$hid .= '<input type="hidden" name="_' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<div class="na-grid na-grid--entries">';
		// Form.
		echo '<section class="nd-card na-form' . ( $edit ? ' is-open' : '' ) . '" id="na-form"><h2>' . ( $row ? 'Edit entry' : 'Record expense or income' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nurax_acc_save' );
		echo '<input type="hidden" name="action" value="nurax_acc_save"><input type="hidden" name="id" value="' . (int) ( $row ? $row->id : 0 ) . '">' . $hid; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="na-f"><label class="na-f__w"><span>What is it for? *</span><select name="category" required>';
		foreach ( $grp as $g => $gl ) {
			echo '<optgroup label="' . esc_attr( $gl ) . '">';
			foreach ( $cats as $k => $c ) {
				if ( $c[1] === $g ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $row ? $row->category : 'rent', $k, false ) . ' title="' . esc_attr( $c[2] ) . '">' . esc_html( $c[0] ) . '</option>';
				}
			}
			echo '</optgroup>';
		}
		echo '</select></label>';
		echo '<label class="na-f__w"><span>Description *</span><input type="text" name="description" maxlength="190" required placeholder="e.g. November rent, LED shelf lights, 10 bob wigs from supplier" value="' . esc_attr( $row ? $row->description : '' ) . '"></label>';
		echo '<label><span>Amount (KSh) *</span><input type="text" inputmode="decimal" name="amount" required placeholder="0" value="' . esc_attr( $row ? (float) $row->amount : '' ) . '"></label>';
		echo '<label><span>Date *</span><input type="date" name="entry_date" required value="' . esc_attr( $row ? $row->entry_date : NURAX_Desk::today() ) . '"></label>';
		echo '<label><span>Paid by</span><select name="method">';
		foreach ( self::methods() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $row ? $row->method : 'mpesa', $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>M-Pesa / bank reference</span><input type="text" name="ref" maxlength="60" placeholder="e.g. SKL4X7Y2ZQ" value="' . esc_attr( $row ? $row->ref : '' ) . '"></label>';
		echo '<label class="na-f__w"><span>Paid to / received from</span><input type="text" name="payee" maxlength="120" placeholder="Landlord, supplier, staff name..." value="' . esc_attr( $row ? $row->payee : '' ) . '"></label>';
		echo '<label class="na-f__w"><span>Note</span><input type="text" name="note" maxlength="255" value="' . esc_attr( $row ? $row->note : '' ) . '"></label>';
		echo '<label class="na-f__w"><span>Receipt (photo or PDF)' . ( $row && $row->receipt_id ? ' - <a href="' . esc_url( wp_get_attachment_url( $row->receipt_id ) ) . '" target="_blank" rel="noopener">view current</a>' : '' ) . '</span><input type="file" name="receipt" accept="image/*,application/pdf"></label></div>';
		echo '<p class="na-f__acts"><button class="nd-btn nd-btn--primary">' . ( $row ? 'Save changes' : 'Save entry' ) . '</button> ';
		if ( $row ) {
			echo '<a class="nd-btn nd-btn--ghost" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries' ) ) ) ) . '">Cancel</a>';
		}
		echo '</p></form>';
		echo '<p class="na-muted na-tip">Tip: refundable deposits and furniture/equipment are recorded but kept out of profit - you still own them.</p></section>';

		// List.
		$list = self::entries( $from, $to, $cat, $q );
		echo '<section class="nd-card na-list"><form class="na-filter" method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="entries">';
		foreach ( $keep as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<select name="cat" onchange="this.form.submit()"><option value="">All entries</option>';
		foreach ( $grp as $g => $gl ) {
			echo '<option value="' . esc_attr( $g ) . '"' . selected( $cat, $g, false ) . '>' . esc_html( $gl ) . '</option>';
		}
		foreach ( $cats as $k => $c ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cat, $k, false ) . '>&nbsp;&nbsp;' . esc_html( $c[0] ) . '</option>';
		}
		echo '</select><input type="search" name="q" placeholder="Search description, payee, reference" value="' . esc_attr( $q ) . '"><button class="nd-btn nd-btn--ghost nd-btn--sm">Search</button></form>';
		if ( ! $list ) {
			echo '<p class="nd-empty">Nothing recorded for this period yet. Use the form to add your first entry.</p></section></div>';
			return;
		}
		$tot = 0;
		echo '<div class="nd-table-wrap"><table class="nd-table na-tbl"><thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Paid by</th><th class="na-r">Amount</th><th></th></tr></thead><tbody>';
		foreach ( $list as $e ) {
			$g    = self::group_of( $e->category );
			$tot += (float) $e->amount;
			echo '<tr><td class="na-nowrap">' . esc_html( date_i18n( 'j M Y', strtotime( $e->entry_date ) ) ) . '</td>';
			echo '<td><strong>' . esc_html( $e->description ) . '</strong>' . ( $e->payee ? '<small>' . esc_html( $e->payee ) . '</small>' : '' ) . ( $e->note ? '<small class="na-note">' . esc_html( $e->note ) . '</small>' : '' ) . '</td>';
			echo '<td><span class="na-chip na-g--' . esc_attr( $g ) . '">' . esc_html( isset( $cats[ $e->category ] ) ? $cats[ $e->category ][0] : $e->category ) . '</span></td>';
			echo '<td class="na-nowrap">' . esc_html( self::methods()[ $e->method ] ?? $e->method ) . ( $e->ref ? '<small>' . esc_html( $e->ref ) . '</small>' : '' ) . '</td>';
			echo '<td class="na-r na-amt na-amt--' . esc_attr( 'income' === $g || 'capital' === $e->category ? 'in' : 'out' ) . '">' . esc_html( ( 'income' === $g || 'capital' === $e->category ? '+ ' : '' ) . NURAX_Desk::money( $e->amount ) ) . '</td>';
			echo '<td class="na-acts">' . ( $e->receipt_id ? '<a class="nd-btn nd-btn--ghost nd-btn--sm" href="' . esc_url( wp_get_attachment_url( $e->receipt_id ) ) . '" target="_blank" rel="noopener">Receipt</a>' : '' );
			echo '<a class="nd-btn nd-btn--ghost nd-btn--sm" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries', 'edit' => $e->id, 'cat' => $cat ) ) ) ) . '#na-form">Edit</a>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Remove this entry? It will no longer count in the accounts.\')">';
			wp_nonce_field( 'nurax_acc_delete' );
			echo '<input type="hidden" name="action" value="nurax_acc_delete"><input type="hidden" name="id" value="' . (int) $e->id . '">' . $hid . '<button class="nd-btn nd-btn--danger-ghost nd-btn--sm">Remove</button></form></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody><tfoot><tr><th colspan="4">' . count( $list ) . ' entr' . ( 1 === count( $list ) ? 'y' : 'ies' ) . '</th><th class="na-r">' . esc_html( NURAX_Desk::money( $tot ) ) . '</th><th></th></tr></tfoot></table></div></section></div>';
	}
}
