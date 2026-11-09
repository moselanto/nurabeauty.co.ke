<?php
/**
 * NURA Desk - Accounts: expenses, sales and profit (NURA Experience 1.83.0; buying prices 1.84.0).
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
 * 1.84.0: a private "Buying price" on every product and colour/variation. The cost of each wig
 *  is saved on the order line when it is sold, so Cost of sales = what the wigs you actually sold
 *  cost you (not what you bought this month), and a "Profit by product" table shows each wig.
 *  Stock purchases are now recorded as stock (inventory) and kept out of profit, so nothing is
 *  counted twice. Sourced (out-of-stock) sales use the supplier cost from Sourced sales.
 *
 * Storage: {prefix}nurax_ledger. Owner-only (manage_options); filter nurax_accounts_cap.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Accounts {

	const DBV     = '1.2.0'; // 1.1.0 (plugin 1.86.0): cover_from / cover_months. 1.2.0 (plugin 1.105.0): vouchers (txn) + change history table.
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
		add_action( 'admin_post_nurax_acc_sale', array( __CLASS__, 'h_sale' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'bust' ) );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'bust' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'bust' ) );
		// 1.84.0: buying price on products and variations.
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'cost_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'cost_save' ) );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'cost_field_var' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'cost_save_var' ), 10, 2 );
		add_action( 'woocommerce_new_order_item', array( __CLASS__, 'snapshot_cost' ), 10, 3 );
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
			cover_from date NULL DEFAULT NULL,
			cover_months smallint(5) unsigned NOT NULL DEFAULT 0,
			txn varchar(20) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY entry_date (entry_date),
			KEY category (category),
			KEY deleted (deleted),
			KEY txn (txn),
			KEY ref (ref)
		) {$c};" );
		$l = self::log_table();
		dbDelta( "CREATE TABLE {$l} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
			txn varchar(20) NOT NULL DEFAULT '',
			action varchar(12) NOT NULL DEFAULT '',
			changes text NULL,
			reason varchar(255) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY txn (txn),
			KEY entry_id (entry_id)
		) {$c};" );
		update_option( self::DBV_OPT, self::DBV, false );
		self::seed();
		self::migrate_advance();
		self::migrate_txn();
	}

	/* ------------------------------------------------------------ vouchers (1.105.0)
	 * One payment = one voucher (PV-0001 money paid out, RV-0001 money received), the way
	 * accounting systems record a split transaction: the voucher holds the date, payment method,
	 * M-Pesa / bank reference, payee and receipt; each item on the receipt is a line with its own
	 * category, description and amount. Lines of one voucher always share the reference, so one
	 * M-Pesa code is never typed twice and never double counted. Every add / edit / removal is kept
	 * in nurax_ledger_log (who, when, old -> new, reason) - nothing is ever hard-deleted.
	 */
	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_ledger_log';
	}

	/** M-Pesa / bank references are compared without spaces and in capitals (sk l4x7 = SKL4X7). */
	public static function norm_ref( $r ) {
		return strtoupper( preg_replace( '/\s+/', '', (string) $r ) );
	}

	public static function is_in( $cat ) {
		return 'income' === self::group_of( $cat ) || 'capital' === $cat;
	}

	private static function next_txn( $in ) {
		global $wpdb;
		$key = $in ? 'nurax_ledger_seq_rv' : 'nurax_ledger_seq_pv';
		$n   = (int) get_option( $key, 0 );
		do {
			$n++;
			$no  = ( $in ? 'RV-' : 'PV-' ) . str_pad( (string) $n, 4, '0', STR_PAD_LEFT );
			$hit = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE txn = %s', $no ) ); // phpcs:ignore WordPress.DB
		} while ( $hit );
		update_option( $key, $n, false );
		return $no;
	}

	/**
	 * Give every existing record a voucher number, in date order. Records already sharing the same
	 * reference, date, payment method and payee (items bought on one M-Pesa payment) are put on one
	 * voucher. Runs once.
	 */
	private static function migrate_txn() {
		if ( get_option( 'nurax_ledger_m_txn' ) ) {
			return;
		}
		global $wpdb;
		$rows   = (array) $wpdb->get_results( 'SELECT id, entry_date, category, method, ref, payee FROM ' . self::table() . " WHERE txn = '' ORDER BY entry_date ASC, id ASC" ); // phpcs:ignore WordPress.DB
		$groups = array();
		foreach ( $rows as $e ) {
			$ref = self::norm_ref( $e->ref );
			$k   = '' !== $ref ? $e->entry_date . '|' . $e->method . '|' . $ref . '|' . strtolower( trim( $e->payee ) ) : 'id' . $e->id;
			if ( ! isset( $groups[ $k ] ) ) {
				$groups[ $k ] = array( 'in' => self::is_in( $e->category ), 'ids' => array() );
			}
			$groups[ $k ]['ids'][] = (int) $e->id;
		}
		foreach ( $groups as $g ) {
			$no = self::next_txn( $g['in'] );
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET txn = %s WHERE id IN (' . implode( ',', array_map( 'intval', $g['ids'] ) ) . ')', $no ) ); // phpcs:ignore WordPress.DB
		}
		update_option( 'nurax_ledger_m_txn', time(), false );
	}

	private static function audit( $entry_id, $txn, $action, $changes = array(), $reason = '' ) {
		global $wpdb;
		$wpdb->insert( self::log_table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'entry_id' => (int) $entry_id,
			'txn'      => (string) $txn,
			'action'   => $action,
			'changes'  => $changes ? wp_json_encode( $changes ) : '',
			'reason'   => mb_substr( (string) $reason, 0, 255 ),
			'user_id'  => get_current_user_id(),
			'at'       => current_time( 'mysql' ),
		) );
	}

	/** Lines of a voucher (or of a single legacy record), oldest first. */
	public static function voucher( $txn ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE txn = %s AND deleted = 0 ORDER BY id ASC', $txn ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * 1.86.0: the owner confirmed the 2-month "deposit" is NOT refundable - it is two months of
	 * rent paid in advance that the shop then "sits on" (uses as rent). Move the seeded entry from
	 * 'Deposits paid (refundable)' to 'Rent paid in advance'. Months stay unset until the owner
	 * picks them, so it is not counted as rent in the wrong month.
	 */
	private static function migrate_advance() {
		if ( get_option( 'nurax_ledger_m_advance' ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET category = 'prepaid', description = %s, cover_months = 2, note = %s WHERE category = 'deposit' AND created_by = 0 AND description = %s", 'Rent paid in advance - 2 months (non-refundable, used as rent)', 'Two months of rent paid ahead. Set the months it covers (e.g. the last two months of the lease) so it is counted as rent in those months.', 'Rent deposit - 2 months (refundable)' ) ); // phpcs:ignore WordPress.DB
		update_option( 'nurax_ledger_m_advance', time(), false );
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
			array( 'deposit', 'Rent deposit - 2 months (refundable)', 44000 ), // converted to 'prepaid' by migrate_advance().
			array( 'legal', 'Lease agreement fee', 3000 ),
		) as $r ) {
			$wpdb->insert( self::table(), array( 'entry_date' => '2026-11-01', 'category' => $r[0], 'description' => $r[1], 'amount' => $r[2], 'method' => 'mpesa', 'note' => $note, 'created' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		update_option( 'nurax_ledger_seeded', time(), false );
	}

	/**
	 * key => [ label, group, hint ]. Groups:
	 *  stock - stock bought; inventory until sold (cost then comes from the buying price)
	 *  cogs  - extra cost of sales not in the buying price (freight, free packaging)
	 *  opex  - operating expenses (the running costs of the shop)
	 *  setup - one-off start-up costs (renovation, lease fees, licences)
	 *  income - other income (not through the website or the till)
	 *  asset - money you still own: refundable deposits, furniture & equipment
	 *  owner - owner's capital in / drawings out
	 */
	public static function categories() {
		return apply_filters( 'nurax_ledger_categories', array(
			'stock'      => array( 'Stock bought (wigs, hair, products)', 'stock', 'Goes into stock. It becomes a cost only when the wig is sold, using its buying price.' ),
			'freight'    => array( 'Import, freight & customs', 'cogs', 'Shipping in, clearing, duty - only if NOT already in the buying price.' ),
			'packaging'  => array( 'Packaging & free accessories', 'cogs', 'Boxes, bags, wig caps given free with sales.' ),
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
			'prepaid'    => array( 'Rent paid in advance (non-refundable)', 'prepaid', 'Months of rent paid ahead, e.g. a 2-month advance you later sit on. Becomes rent in the months it covers.' ),
			'deposit'    => array( 'Deposits paid (refundable)', 'asset', 'Only money you will get back (e.g. KPLC/water deposit) - not a cost.' ),
			'equipment'  => array( 'Furniture & equipment', 'asset', 'Chairs, mirrors, wig heads, POS phone, steamers - kept for years.' ),
			'capital'    => array( 'Owner capital in', 'owner', 'Money the owner puts into the business.' ),
			'drawings'   => array( 'Owner drawings out', 'owner', 'Money the owner takes out for personal use.' ),
		) );
	}

	public static function groups() {
		return array(
			'stock'  => 'Stock bought (inventory - not in profit)',
			'cogs'   => 'Cost of sales (extras)',
			'opex'   => 'Operating expenses',
			'setup'  => 'One-off setup costs',
			'income' => 'Other income',
			'prepaid' => 'Paid in advance (becomes a cost month by month)',
			'asset'  => 'Assets & refundable deposits (not in profit)',
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

	/**
	 * 1.100.0: comparative period. A whole calendar month compares with the previous
	 * month, a quarter with the previous quarter, a year with the previous year;
	 * any other range with the same number of days immediately before it.
	 */
	public static function prior_period( $from, $to ) {
		$f = strtotime( $from );
		$t = strtotime( $to );
		if ( '2000-01-01' === $from ) {
			return array( '1999-01-01', '1999-12-31', 'Prior period' );
		}
		$whole_months = ( '01' === gmdate( 'd', $f ) && gmdate( 'Y-m-t', $t ) === $to );
		if ( $whole_months ) {
			$months = ( (int) gmdate( 'Y', $t ) - (int) gmdate( 'Y', $f ) ) * 12 + (int) gmdate( 'n', $t ) - (int) gmdate( 'n', $f ) + 1;
			$pf     = gmdate( 'Y-m-01', gmmktime( 0, 0, 0, (int) gmdate( 'n', $f ) - $months, 1, (int) gmdate( 'Y', $f ) ) );
			$pt     = gmdate( 'Y-m-t', gmmktime( 0, 0, 0, (int) gmdate( 'n', $f ) - 1, 1, (int) gmdate( 'Y', $f ) ) );
			$label  = 1 === $months ? gmdate( 'F Y', strtotime( $pf ) ) : ( 12 === $months ? 'Year ' . gmdate( 'Y', strtotime( $pf ) ) : gmdate( 'M Y', strtotime( $pf ) ) . ' - ' . gmdate( 'M Y', strtotime( $pt ) ) );
			return array( $pf, $pt, $label );
		}
		$days = (int) round( ( $t - $f ) / DAY_IN_SECONDS ) + 1;
		$pt   = gmdate( 'Y-m-d', $f - DAY_IN_SECONDS );
		$pf   = gmdate( 'Y-m-d', $f - $days * DAY_IN_SECONDS );
		return array( $pf, $pt, gmdate( 'j M', strtotime( $pf ) ) . ' - ' . gmdate( 'j M Y', strtotime( $pt ) ) );
	}

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
			$sql .= ' AND ( description LIKE %s OR payee LIKE %s OR ref LIKE %s OR note LIKE %s OR txn LIKE %s )';
			array_push( $a, $like, $like, $like, $like, $like );
		}
		$sql .= ' ORDER BY entry_date DESC, id DESC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $a ) ); // phpcs:ignore WordPress.DB
	}

	public static function bust() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_nurax\\_acc\\_%' OR option_name LIKE '\\_transient\\_timeout\\_nurax\\_acc\\_%'" ); // phpcs:ignore WordPress.DB
	}

	/* ------------------------------------------------------------ buying price (1.84.0) */

	public static function can_see_cost() {
		return current_user_can( self::cap() ) || ( class_exists( 'NURAX_Staff' ) && current_user_can( NURAX_Desk::CAP ) && NURAX_Staff::can( 'see_totals' ) );
	}

	public static function cost_field() {
		if ( ! self::can_see_cost() ) {
			return;
		}
		woocommerce_wp_text_input( array(
			'id'          => '_nura_cost',
			'label'       => 'Buying price (KSh)',
			'data_type'   => 'price',
			'desc_tip'    => true,
			'description' => 'What one unit cost you, including transport to the shop. Private - customers never see it. Used for profit in NURA Desk > Accounts. For colours/variations, set it on each variation.',
		) );
	}

	public static function cost_save( $product ) {
		if ( self::can_see_cost() && isset( $_POST['_nura_cost'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$v = wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_nura_cost'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			'' === $v ? $product->delete_meta_data( '_nura_cost' ) : $product->update_meta_data( '_nura_cost', $v );
		}
	}

	public static function cost_field_var( $loop, $data, $variation ) {
		if ( ! self::can_see_cost() ) {
			return;
		}
		woocommerce_wp_text_input( array(
			'id'            => '_nura_cost' . $loop,
			'name'          => '_nura_cost[' . $loop . ']',
			'value'         => get_post_meta( $variation->ID, '_nura_cost', true ),
			'label'         => 'Buying price (KSh)',
			'data_type'     => 'price',
			'wrapper_class' => 'form-row form-row-full',
			'desc_tip'      => true,
			'description'   => 'Private. Leave empty to use the main product\'s buying price.',
		) );
	}

	public static function cost_save_var( $variation_id, $i ) {
		if ( self::can_see_cost() && isset( $_POST['_nura_cost'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$v = wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_nura_cost'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			'' === $v ? delete_post_meta( $variation_id, '_nura_cost' ) : update_post_meta( $variation_id, '_nura_cost', $v );
		}
	}

	/** Current buying price for a product/variation (variation first, then the main product). */
	public static function current_cost( $product_id, $variation_id = 0 ) {
		foreach ( array( (int) $variation_id, (int) $product_id ) as $id ) {
			if ( $id ) {
				$v = get_post_meta( $id, '_nura_cost', true );
				if ( '' !== $v && is_numeric( $v ) && (float) $v > 0 ) {
					return (float) $v;
				}
			}
		}
		return null;
	}

	/** Save the buying price on the order line at the moment of sale, so later price edits never change past profit. */
	public static function snapshot_cost( $item_id, $item, $order_id ) {
		if ( ! $item instanceof WC_Order_Item_Product || '' !== (string) wc_get_order_item_meta( $item_id, '_nura_cost', true ) ) {
			return;
		}
		$c = self::current_cost( $item->get_product_id(), $item->get_variation_id() );
		if ( null !== $c ) {
			wc_update_order_item_meta( $item_id, '_nura_cost', $c );
		}
	}

	/** WooCommerce sales (website + Point of sale) paid in the period, plus cost of each item sold. */
	/**
	 * 1.106.0: is a WooCommerce order a sale? Only money actually received counts (IFRS 15: revenue
	 * when the goods are handed over and paid). The owner can mark any order on the Profit & Loss tab:
	 *  paid    - sold and paid (counted)
	 *  pending - ordered but not paid yet, e.g. WhatsApp / cash-on-delivery customer who has not paid
	 *  test    - a test order by the owner (never counted)
	 * Without a mark: Completed = paid; Processing paid online = paid; Processing cash on delivery,
	 * Pending payment and On hold = pending.
	 */
	public static function sale_state( $o ) {
		$set = (string) $o->get_meta( '_nura_sale', true );
		if ( in_array( $set, array( 'paid', 'pending', 'test' ), true ) ) {
			return $set;
		}
		$st = $o->get_status();
		if ( 'completed' === $st ) {
			return 'paid';
		}
		if ( 'processing' === $st && 'cod' !== $o->get_payment_method() && $o->get_date_paid() ) {
			return 'paid';
		}
		return 'pending';
	}

	public static function h_sale() {
		self::guard( 'nurax_acc_sale' );
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$to = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
		$o  = $id && function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
		$lb = array( 'paid' => 'Sold - paid', 'pending' => 'Not paid yet (pending)', 'test' => 'Test order (not a sale)', 'auto' => 'Automatic (from order status)' );
		if ( $o && isset( $lb[ $to ] ) ) {
			if ( 'auto' === $to ) {
				$o->delete_meta_data( '_nura_sale' );
			} else {
				$o->update_meta_data( '_nura_sale', $to );
			}
			$o->save();
			$u = wp_get_current_user();
			$o->add_order_note( 'Accounts: marked as ' . $lb[ $to ] . ' by ' . ( $u && $u->ID ? $u->display_name : 'owner' ) . '.' );
			self::bust();
			if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
				NURAX_Staff::log( 'accounts', 'Order #' . $o->get_order_number() . ' marked as ' . $lb[ $to ] );
			}
		}
		self::back( array( 'nd_msg' => 'saved', 'tab' => 'overview' ) );
	}

	/** Orders of the period with their sale state and one-click marks (Profit & Loss tab). */
	private static function render_orders( $r, $keep ) {
		$s    = $r['sales'];
		$hid  = '';
		foreach ( array_merge( $keep, array( 'tab' => 'overview' ) ) as $k => $v ) {
			$hid .= '<input type="hidden" name="_' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$wa_n = 0;
		$wa_v = 0.0;
		if ( class_exists( 'NURAX_Desk_Orders' ) ) {
			$q = get_posts( array( 'post_type' => NURAX_Desk_Orders::CPT, 'post_status' => 'private', 'numberposts' => 500, 'fields' => 'ids' ) );
			foreach ( $q as $pid ) {
				if ( 'cancelled' !== NURAX_Desk_Orders::status( $pid ) && ! NURAX_Desk_Orders::is_paid( $pid ) ) {
					$d = NURAX_Desk_Orders::data( $pid );
					$wa_n++;
					$wa_v += (float) $d['total'];
				}
			}
		}
		echo '<section class="nd-card na-orders"><header class="nd-card__h"><h2>Orders: sold or not paid yet</h2></header>';
		echo '<p class="na-muted">Only paid orders count as sales. Mark each order: <strong>Sold</strong> when the money is received, <strong>Not paid</strong> while you wait (e.g. a WhatsApp or cash-on-delivery customer), <strong>Test</strong> for your own test orders.</p>';
		if ( ! $s['list'] ) {
			echo '<p class="nd-empty">No website or till orders in this period.</p>';
		} else {
			$tag = array( 'paid' => array( 'completed', 'Sold - counted' ), 'pending' => array( 'request', 'Not paid - not counted' ), 'test' => array( 'muted', 'Test - not counted' ) );
			echo '<div class="nd-table-wrap"><table class="nd-table na-tbl"><thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th class="na-r">Total</th><th>In the accounts</th><th></th></tr></thead><tbody>';
			foreach ( $s['list'] as $o ) {
				echo '<tr><td class="na-nowrap"><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $o['id'] . '&action=edit' ) ) . '">#' . esc_html( $o['no'] ) . '</a><small>' . esc_html( $o['date'] ? date_i18n( 'j M Y', strtotime( $o['date'] ) ) : '' ) . ' - ' . esc_html( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $o['status'] ) : $o['status'] ) . '</small></td>';
				echo '<td>' . esc_html( $o['name'] ) . '</td><td>' . esc_html( $o['method'] ) . '</td><td class="na-r">' . esc_html( NURAX_Desk::money( $o['total'] ) ) . '</td>';
				echo '<td>' . NURAX_Desk::badge( $tag[ $o['state'] ][0], $tag[ $o['state'] ][1] ) . ( $o['set'] ? '' : '<small class="na-muted">automatic</small>' ) . '</td><td class="na-acts">'; // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( array( 'paid' => 'Sold', 'pending' => 'Not paid', 'test' => 'Test' ) as $to => $lab ) {
					if ( $to === $o['state'] ) {
						continue;
					}
					echo '<form method="post" class="nd-inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
					wp_nonce_field( 'nurax_acc_sale' );
					echo '<input type="hidden" name="action" value="nurax_acc_sale"><input type="hidden" name="order_id" value="' . (int) $o['id'] . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">' . $hid . '<button class="nd-btn nd-btn--ghost nd-btn--sm">' . esc_html( $lab ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
			echo '<p class="na-muted">' . esc_html( 'Not paid yet: ' . (int) $s['awaiting_n'] . ' order(s), ' . NURAX_Desk::money( $s['awaiting'] ) . ' (not in sales).' . ( $s['tests'] ? ' Test orders: ' . (int) $s['tests'] . '.' : '' ) ) . '</p>';
		}
		if ( $wa_n ) {
			echo '<p class="na-muted">WhatsApp orders not paid yet: ' . (int) $wa_n . ' (' . esc_html( NURAX_Desk::money( $wa_v ) ) . ') - never counted as sales. <a href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_ORDERS, array() ) ) . '">Open WhatsApp orders</a> to mark them paid or cancelled.</p>';
		}
		echo '</section>';
	}

	public static function sales( $from, $to ) {
		$key = 'nurax_acc_v3_' . md5( $from . $to );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$out = array( 'online' => 0.0, 'pos' => 0.0, 'shipping' => 0.0, 'refunds' => 0.0, 'orders' => 0, 'cost' => 0.0, 'units' => 0, 'missing' => 0, 'missing_rev' => 0.0, 'products' => array(), 'list' => array(), 'awaiting' => 0.0, 'awaiting_n' => 0, 'tests' => 0 );
		if ( function_exists( 'wc_get_orders' ) ) {
			$ids = wc_get_orders( array(
				'type'         => 'shop_order',
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold' ),
				'date_created' => $from . '...' . $to . ' 23:59:59',
				'limit'        => -1,
				'return'       => 'ids',
			) );
			foreach ( (array) $ids as $id ) {
				$o = wc_get_order( $id );
				if ( ! $o ) {
					continue;
				}
				$state = self::sale_state( $o );
				$out['list'][] = array( 'id' => $o->get_id(), 'no' => $o->get_order_number(), 'date' => $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d' ) : '', 'name' => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), 'total' => (float) $o->get_total(), 'method' => $o->get_payment_method_title(), 'status' => $o->get_status(), 'state' => $state, 'set' => (string) $o->get_meta( '_nura_sale', true ) );
				if ( 'paid' !== $state ) {
					if ( 'test' === $state ) {
						$out['tests']++;
					} else {
						$out['awaiting']   += (float) $o->get_total();
						$out['awaiting_n']++;
					}
					continue; // 1.106.0: only paid orders are sales.
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
				foreach ( $o->get_items( 'line_item' ) as $iid => $it ) {
					$qty = (int) $it->get_quantity() + (int) $o->get_qty_refunded_for_item( $iid ); // refunded qty is negative
					if ( $qty <= 0 ) {
						continue;
					}
					$rev = (float) $it->get_total() - (float) $o->get_total_refunded_for_item( $iid );
					$src = $it->get_meta( '_nura_sourced', true );
					$how = 'sold';
					if ( is_array( $src ) && ! empty( $src['cost'] ) ) {
						$unit = (float) $src['cost'];
						$how  = 'sourced';
					} elseif ( '' !== (string) $it->get_meta( '_nura_cost', true ) ) {
						$unit = (float) $it->get_meta( '_nura_cost', true );
					} else {
						$unit = self::current_cost( $it->get_product_id(), $it->get_variation_id() );
					}
					$pid = (int) $it->get_product_id();
					$k   = $pid ? $pid : 'x' . md5( $it->get_name() );
					if ( ! isset( $out['products'][ $k ] ) ) {
						$p = $pid ? wc_get_product( $pid ) : null;
						$out['products'][ $k ] = array( 'name' => $p ? $p->get_name() : $it->get_name(), 'id' => $pid, 'qty' => 0, 'rev' => 0.0, 'cost' => 0.0, 'missing' => 0, 'sourced' => 0 );
					}
					$row = &$out['products'][ $k ];
					$row['qty'] += $qty;
					$row['rev'] += $rev;
					$out['units'] += $qty;
					if ( null === $unit ) {
						$row['missing']      += $qty;
						$out['missing']      += $qty;
						$out['missing_rev']  += $rev;
					} else {
						$row['cost'] += $unit * $qty;
						$out['cost'] += $unit * $qty;
						if ( 'sourced' === $how ) {
							$row['sourced'] += $qty;
						}
					}
					unset( $row );
				}
			}
		}
		uasort( $out['products'], function ( $a, $b ) {
			return $b['rev'] <=> $a['rev'];
		} );
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Rent paid in advance: how much is used up as rent inside [from, to], and the balance still
	 * to use at the end of the period. Months not yet set = nothing used (balance only).
	 */
	public static function cover_text( $e ) {
		$n = max( 1, (int) $e->cover_months );
		if ( empty( $e->cover_from ) || '0000-00-00' === $e->cover_from ) {
			return $n . ' month(s) of rent paid ahead - months not set yet (set them to count it as rent)';
		}
		$a = strtotime( $e->cover_from );
		$b = strtotime( '+' . ( $n - 1 ) . ' months', $a );
		return 'Counts as rent: ' . date_i18n( 'M Y', $a ) . ( $n > 1 ? ' - ' . date_i18n( 'M Y', $b ) : '' ) . ' (' . NURAX_Desk::money( (float) $e->amount / $n ) . ' a month)';
	}

	public static function advance( $from, $to ) {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT amount, entry_date, cover_from, cover_months FROM ' . self::table() . " WHERE deleted = 0 AND category = 'prepaid' AND entry_date <= %s", $to ) ); // phpcs:ignore WordPress.DB
		$used = 0.0;
		$bal  = 0.0;
		$unset = 0;
		$pf   = strtotime( $from );
		$pt   = strtotime( $to . ' 23:59:59' );
		foreach ( $rows as $e ) {
			$amt = (float) $e->amount;
			$n   = max( 1, (int) $e->cover_months );
			if ( empty( $e->cover_from ) || '0000-00-00' === $e->cover_from ) {
				$bal += $amt;
				$unset++;
				continue;
			}
			$per    = $amt / $n;
			$before = 0.0;
			for ( $k = 0; $k < $n; $k++ ) {
				$ms   = strtotime( '+' . $k . ' months', strtotime( gmdate( 'Y-m-01', strtotime( $e->cover_from ) ) ) );
				$me   = strtotime( gmdate( 'Y-m-t 23:59:59', $ms ) );
				$days = ( $me - $ms + 1 ) / 86400;
				$a    = max( $ms, $pf );
				$b    = min( $me, $pt );
				if ( $b > $a ) {
					$used += $per * ( ( $b - $a + 1 ) / 86400 ) / $days;
				}
				if ( $me <= $pt ) {
					$before += $per;
				} elseif ( $ms <= $pt ) {
					$before += $per * ( ( $pt - $ms + 1 ) / 86400 ) / $days;
				}
			}
			$bal += max( 0, $amt - $before );
		}
		return array( 'used' => round( $used, 2 ), 'balance' => round( $bal, 2 ), 'unset' => $unset );
	}

	/** Profit & Loss for a period. */
	public static function pnl( $from, $to ) {
		$s    = self::sales( $from, $to );
		$cats = self::categories();
		$by   = array();
		foreach ( self::entries( $from, $to ) as $e ) {
			$by[ $e->category ] = ( isset( $by[ $e->category ] ) ? $by[ $e->category ] : 0 ) + (float) $e->amount;
		}
		$sum = function ( $group ) use ( &$by, &$cats ) {
			$t = 0;
			foreach ( $by as $k => $v ) {
				if ( isset( $cats[ $k ] ) && $cats[ $k ][1] === $group ) {
					$t += $v;
				}
			}
			return $t;
		};
		// 1.86.0: rent paid in advance becomes rent in the months it covers (straight-line by day).
		$adv = self::advance( $from, $to );
		if ( $adv['used'] > 0 ) {
			$by['rent_advance'] = $adv['used'];
			$cats['rent_advance'] = array( 'Rent (from rent paid in advance)', 'opex', '' );
		}
		$r = array( 'sales' => $s, 'by' => $by, 'advance' => $adv );
		$r['other_income'] = $sum( 'income' );
		$r['revenue']      = $s['online'] + $s['pos'] + $s['shipping'] + $r['other_income'] - $s['refunds'];
		$r['cogs_items']   = $s['cost'];
		$r['cogs_extra']   = $sum( 'cogs' );
		$r['cogs']         = $r['cogs_items'] + $r['cogs_extra'];
		$r['stock']        = $sum( 'stock' );
		$r['gross']        = $r['revenue'] - $r['cogs'];
		$r['opex']         = $sum( 'opex' );
		$r['setup']        = $sum( 'setup' );
		$r['operating']    = $r['gross'] - $r['opex'];
		$r['net']          = $r['operating'] - $r['setup'];
		$r['asset']        = $sum( 'asset' );
		$r['capital']      = isset( $by['capital'] ) ? $by['capital'] : 0;
		$r['drawings']     = isset( $by['drawings'] ) ? $by['drawings'] : 0;
		$r['prepaid_paid'] = $sum( 'prepaid' );
		$r['spent']        = $r['stock'] + $r['cogs_extra'] + ( $r['opex'] - ( isset( $by['rent_advance'] ) ? $by['rent_advance'] : 0 ) ) + $r['setup'] + $r['asset'] + $r['prepaid_paid'];
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

	/**
	 * 1.105.0: save a voucher - one payment (date, method, reference, payee, receipt) with one or
	 * more item lines. Editing re-opens the whole voucher: header changes (e.g. a corrected M-Pesa
	 * code) apply to every line; lines can be added, changed or removed. Controls:
	 *  - lines must add up to the receipt total when one is entered;
	 *  - a reference already used on another voucher is stopped (double-posting guard) unless the
	 *    owner confirms it really is a different payment;
	 *  - changing an amount, category or date, or removing an item, needs a reason;
	 *  - every change is written to the change history (old -> new, who, when, reason).
	 * When a save is stopped the typed form is kept (transient) so nothing has to be retyped.
	 */
	public static function h_save() {
		self::guard( 'nurax_acc_save' );
		global $wpdb;
		$t   = self::table();
		$cats = self::categories();
		$P   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification
		$txn = isset( $P['txn'] ) ? strtoupper( sanitize_text_field( $P['txn'] ) ) : '';
		$txn = preg_match( '/^(PV|RV)-\d{4,}$/', $txn ) ? $txn : '';
		$date   = NURAX_Desk::valid_date( isset( $P['entry_date'] ) ? sanitize_text_field( $P['entry_date'] ) : '' );
		$method = isset( $P['method'] ) ? sanitize_key( $P['method'] ) : 'mpesa';
		$method = isset( self::methods()[ $method ] ) ? $method : 'other';
		$ref    = mb_substr( self::norm_ref( isset( $P['ref'] ) ? sanitize_text_field( $P['ref'] ) : '' ), 0, 60 );
		$payee  = mb_substr( isset( $P['payee'] ) ? sanitize_text_field( $P['payee'] ) : '', 0, 120 );
		$note   = mb_substr( isset( $P['note'] ) ? sanitize_text_field( $P['note'] ) : '', 0, 255 );
		$note_o = isset( $P['note_orig'] ) ? sanitize_text_field( $P['note_orig'] ) : '';
		$reason = mb_substr( isset( $P['reason'] ) ? sanitize_text_field( $P['reason'] ) : '', 0, 255 );
		$dup_ok = ! empty( $P['dup_ok'] );
		$rtotal = isset( $P['receipt_total'] ) ? trim( (string) preg_replace( '/[^0-9.]/', '', (string) $P['receipt_total'] ) ) : '';
		$cover_m = isset( $P['cover_months'] ) ? max( 1, min( 60, absint( $P['cover_months'] ) ) ) : 1;
		$cover_f = isset( $P['cover_from'] ) ? sanitize_text_field( $P['cover_from'] ) : '';
		$cover_f = preg_match( '/^\d{4}-\d{2}$/', $cover_f ) ? $cover_f . '-01' : null;

		$raw = isset( $P['lines'] ) && is_array( $P['lines'] ) ? $P['lines'] : array();
		if ( ! $raw && isset( $P['category'] ) ) { // older single-entry form.
			$raw = array( array( 'id' => isset( $P['id'] ) ? $P['id'] : 0, 'category' => $P['category'], 'description' => isset( $P['description'] ) ? $P['description'] : '', 'amount' => isset( $P['amount'] ) ? $P['amount'] : '' ) );
		}
		$lines = array();
		$bad   = false;
		foreach ( $raw as $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$ln = array(
				'id'          => absint( isset( $l['id'] ) ? $l['id'] : 0 ),
				'category'    => sanitize_key( isset( $l['category'] ) ? $l['category'] : '' ),
				'description' => mb_substr( sanitize_text_field( isset( $l['description'] ) ? (string) $l['description'] : '' ), 0, 190 ),
				'amount'      => round( (float) preg_replace( '/[^0-9.]/', '', isset( $l['amount'] ) ? (string) $l['amount'] : '' ), 2 ),
			);
			if ( ! $ln['id'] && '' === $ln['description'] && $ln['amount'] <= 0 ) {
				continue; // empty spare row.
			}
			if ( ! isset( $cats[ $ln['category'] ] ) || '' === $ln['description'] || $ln['amount'] <= 0 ) {
				$bad = true;
			}
			$lines[] = $ln;
		}
		$draft = array( 'txn' => $txn, 'entry_date' => $date ? $date : ( isset( $P['entry_date'] ) ? sanitize_text_field( $P['entry_date'] ) : '' ), 'method' => $method, 'ref' => $ref, 'payee' => $payee, 'note' => $note, 'note_orig' => $note_o, 'reason' => $reason, 'receipt_total' => $rtotal, 'cover_from' => $cover_f ? substr( $cover_f, 0, 7 ) : '', 'cover_months' => $cover_m, 'lines' => $lines );
		$fail  = function ( $msg, $extra = array() ) use ( $draft, $txn ) {
			set_transient( 'nurax_acc_draft_' . get_current_user_id(), $draft, HOUR_IN_SECONDS );
			self::back( array_merge( array( 'nd_msg' => $msg, 'tab' => 'entries', 'edit' => $txn ? strtolower( $txn ) : 'new', 'draft' => 1 ), $extra ) );
		};
		if ( ! $date || ! $lines || $bad ) {
			$fail( 'acc_invalid' );
		}
		$total = 0;
		foreach ( $lines as $ln ) {
			$total += $ln['amount'];
		}
		if ( '' !== $rtotal && abs( $total - (float) $rtotal ) > 0.005 ) {
			$fail( 'acc_total' );
		}
		$old = array();
		if ( $txn ) {
			foreach ( self::voucher( $txn ) as $o ) {
				$old[ (int) $o->id ] = $o;
			}
		}
		if ( '' !== $ref && ! $dup_ok ) {
			$other = $wpdb->get_var( $wpdb->prepare( "SELECT txn FROM {$t} WHERE deleted = 0 AND UPPER(REPLACE(ref, ' ', '')) = %s AND txn <> %s ORDER BY entry_date DESC, id DESC LIMIT 1", $ref, $txn ) ); // phpcs:ignore WordPress.DB
			if ( $other ) {
				$fail( 'acc_dupref', array( 'dup' => strtolower( $other ) ) );
			}
		}
		// A reason is needed when a posted figure changes or an item is removed.
		if ( $old && '' === $reason ) {
			$first    = reset( $old );
			$material = $first->entry_date !== $date;
			$kept     = array();
			foreach ( $lines as $ln ) {
				if ( isset( $old[ $ln['id'] ] ) ) {
					$kept[ $ln['id'] ] = true;
					$o = $old[ $ln['id'] ];
					if ( $o->category !== $ln['category'] || abs( (float) $o->amount - $ln['amount'] ) > 0.005 ) {
						$material = true;
					}
				}
			}
			if ( count( $kept ) < count( $old ) ) {
				$material = true;
			}
			if ( $material ) {
				$fail( 'acc_reason' );
			}
		}
		$receipt_id = 0;
		if ( ! empty( $_FILES['receipt']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$type = wp_check_filetype( sanitize_file_name( wp_unslash( $_FILES['receipt']['name'] ) ) );
			if ( in_array( $type['ext'], array( 'jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic' ), true ) ) {
				$att = media_handle_upload( 'receipt', 0, array( 'post_title' => 'Receipt - ' . ( $txn ? $txn . ' ' : '' ) . $lines[0]['description'] ) );
				if ( ! is_wp_error( $att ) ) {
					$receipt_id = (int) $att;
				}
			}
		}
		if ( ! $receipt_id && $old ) {
			foreach ( $old as $o ) {
				if ( $o->receipt_id ) {
					$receipt_id = (int) $o->receipt_id;
					break;
				}
			}
		}
		$new_txn = ! $txn;
		if ( $new_txn ) {
			$txn = self::next_txn( self::is_in( $lines[0]['category'] ) );
		}
		$uid  = get_current_user_id();
		$now  = current_time( 'mysql' );
		$keep = array();
		$fields = array( 'entry_date', 'category', 'description', 'amount', 'method', 'ref', 'payee', 'note', 'receipt_id', 'cover_from', 'cover_months' );
		foreach ( $lines as $ln ) {
			$o   = isset( $old[ $ln['id'] ] ) ? $old[ $ln['id'] ] : null;
			$pre = 'prepaid' === $ln['category'];
			$row = array(
				'txn'          => $txn,
				'entry_date'   => $date,
				'category'     => $ln['category'],
				'description'  => $ln['description'],
				'amount'       => $ln['amount'],
				'method'       => $method,
				'ref'          => $ref,
				'payee'        => $payee,
				'note'         => ( $o && $note === $note_o ) ? (string) $o->note : $note,
				'receipt_id'   => $receipt_id ? $receipt_id : ( $o ? (int) $o->receipt_id : 0 ),
				'cover_from'   => $pre ? $cover_f : null,
				'cover_months' => $pre ? $cover_m : 0,
			);
			if ( $o ) {
				$keep[ (int) $o->id ] = true;
				$chg = array();
				foreach ( $fields as $f ) {
					$a = null === $o->$f ? '' : (string) $o->$f;
					$b = null === $row[ $f ] ? '' : (string) $row[ $f ];
					if ( 'amount' === $f ? abs( (float) $a - (float) $b ) > 0.005 : ( 'cover_months' === $f || 'receipt_id' === $f ? (int) $a !== (int) $b : $a !== $b ) ) {
						$chg[ $f ] = array( $a, $b );
					}
				}
				if ( $chg ) {
					$row['updated']    = $now;
					$row['updated_by'] = $uid;
					$wpdb->update( $t, $row, array( 'id' => $o->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					self::audit( $o->id, $txn, 'edited', $chg, $reason );
				}
			} else {
				$row['created']    = $now;
				$row['created_by'] = $uid;
				$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::audit( $wpdb->insert_id, $txn, 'added', array( 'amount' => array( '', (string) $ln['amount'] ), 'category' => array( '', $ln['category'] ), 'description' => array( '', $ln['description'] ) ), $new_txn ? '' : $reason );
			}
		}
		foreach ( $old as $id => $o ) {
			if ( empty( $keep[ $id ] ) ) {
				$wpdb->update( $t, array( 'deleted' => 1, 'deleted_by' => $uid, 'updated' => $now ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::audit( $id, $txn, 'removed', array( 'amount' => array( (string) $o->amount, '' ), 'description' => array( $o->description, '' ) ), $reason );
			}
		}
		delete_transient( 'nurax_acc_draft_' . $uid );
		self::bust();
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'accounts', ( $new_txn ? 'Added ' : 'Edited ' ) . $txn . ' (' . count( $lines ) . ' item' . ( 1 === count( $lines ) ? '' : 's' ) . ', ' . NURAX_Desk::money( $total ) . ')' . ( $reason ? ' - ' . $reason : '' ) );
		}
		self::back( array( 'nd_msg' => 'saved', 'tab' => 'entries' ) );
	}

	public static function h_delete() {
		self::guard( 'nurax_acc_delete' );
		global $wpdb;
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$reason = isset( $_POST['reason'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['reason'] ) ), 0, 255 ) : '';
		$o      = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d AND deleted = 0', $id ) ) : null; // phpcs:ignore WordPress.DB
		if ( $o ) {
			$wpdb->update( self::table(), array( 'deleted' => 1, 'deleted_by' => get_current_user_id(), 'updated' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::audit( $id, $o->txn, 'removed', array( 'amount' => array( (string) $o->amount, '' ), 'description' => array( $o->description, '' ) ), $reason );
			self::bust();
			if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
				NURAX_Staff::log( 'accounts', 'Removed ' . ( $o->txn ? $o->txn . ' ' : '' ) . 'item: ' . $o->description . ' (' . NURAX_Desk::money( $o->amount ) . ')' . ( $reason ? ' - ' . $reason : '' ) );
			}
		}
		self::back( array( 'nd_msg' => 'deleted', 'tab' => 'entries' ) );
	}

	/**
	 * 1.85.0: downloads. Excel (.xlsx) is the presentation format - NURA black & gold header band
	 * with the logo, title, period, frozen headers, KSh number formats and print-ready A4 - built by
	 * NURAX_Xlsx. A CSV file is plain text by definition (it cannot hold colours, fonts or a logo),
	 * so CSV is kept only as a clean data file for accountants and other software: one header row,
	 * ISO dates (YYYY-MM-DD), plain numbers, UTF-8.
	 * ?sheet=pnl|entries|products  &fmt=xlsx|csv
	 */
	public static function export_rows( $sheet, $from, $to ) {
		$cats = self::categories();
		$grp  = self::groups();
		if ( 'entries' === $sheet ) {
			$rows = array( array( 'Voucher', 'Date', 'Type', 'Category', 'Description', 'Amount (KES)', 'Paid by', 'Reference', 'Paid to / received from', 'Note', 'Receipt link' ) );
			foreach ( array_reverse( self::entries( $from, $to ) ) as $e ) {
				$g      = self::group_of( $e->category );
				$rows[] = array( $e->txn, $e->entry_date, $grp[ $g ], isset( $cats[ $e->category ] ) ? $cats[ $e->category ][0] : $e->category, $e->description, round( (float) $e->amount, 2 ), self::methods()[ $e->method ] ?? $e->method, $e->ref, $e->payee, trim( $e->note . ( 'prepaid' === $e->category ? ' ' . self::cover_text( $e ) : '' ) ), $e->receipt_id ? (string) wp_get_attachment_url( $e->receipt_id ) : '' );
			}
			return $rows;
		}
		$r = self::pnl( $from, $to );
		if ( 'products' === $sheet ) {
			$rows = array( array( 'Product', 'Qty sold', 'Sales (KES)', 'Cost (KES)', 'Profit (KES)', 'Margin', 'Buying price set' ) );
			foreach ( $r['sales']['products'] as $p ) {
				$profit = $p['rev'] - $p['cost'];
				$rows[] = array( $p['name'], (int) $p['qty'], round( $p['rev'], 2 ), round( $p['cost'], 2 ), round( $profit, 2 ), $p['rev'] > 0 && $p['missing'] < $p['qty'] ? round( $profit / $p['rev'] * 100, 1 ) . '%' : '', $p['missing'] ? 'No' : 'Yes' );
			}
			return $rows;
		}
		$s    = $r['sales'];
		$rows = array( array( 'Section', 'Line item', 'Amount (KES)' ) );
		$add  = function ( $sec, $line, $v ) use ( &$rows ) {
			$rows[] = array( $sec, $line, round( (float) $v, 2 ) );
		};
		$add( 'Revenue', 'Website sales', $s['online'] );
		$add( 'Revenue', 'In-shop sales (Point of sale)', $s['pos'] );
		$add( 'Revenue', 'Delivery charged to customers', $s['shipping'] );
		$add( 'Revenue', 'Other income', $r['other_income'] );
		$add( 'Revenue', 'Less: refunds', -$s['refunds'] );
		$add( 'TOTAL REVENUE', '', $r['revenue'] );
		$add( 'Cost of sales', 'Cost of wigs & products sold (buying price)', -$r['cogs_items'] );
		foreach ( $cats as $k => $c ) {
			if ( 'cogs' === $c[1] && ! empty( $r['by'][ $k ] ) ) {
				$add( 'Cost of sales', $c[0], -$r['by'][ $k ] );
			}
		}
		$add( 'GROSS PROFIT', $r['revenue'] > 0 ? 'Gross margin ' . self::pct( $r['gross'], $r['revenue'] ) : '', $r['gross'] );
		foreach ( $cats as $k => $c ) {
			if ( 'opex' === $c[1] && ! empty( $r['by'][ $k ] ) ) {
				$add( 'Operating expenses', $c[0], -$r['by'][ $k ] );
			}
		}
		if ( ! empty( $r['by']['rent_advance'] ) ) {
			$add( 'Operating expenses', 'Rent (from rent paid in advance)', -$r['by']['rent_advance'] );
		}
		$add( 'OPERATING PROFIT', '', $r['operating'] );
		foreach ( $cats as $k => $c ) {
			if ( 'setup' === $c[1] && ! empty( $r['by'][ $k ] ) ) {
				$add( 'One-off setup costs', $c[0], -$r['by'][ $k ] );
			}
		}
		$add( $r['net'] >= 0 ? 'NET PROFIT' : 'NET LOSS', $r['revenue'] > 0 ? 'Net margin ' . self::pct( $r['net'], $r['revenue'] ) : 'No sales in this period', $r['net'] );
		$add( 'Not part of profit', 'Rent paid in advance - not yet used (balance at period end)', $r['advance']['balance'] );
		foreach ( $cats as $k => $c ) {
			if ( in_array( $c[1], array( 'prepaid', 'stock', 'asset', 'owner' ), true ) ) {
				$add( 'Not part of profit', 'prepaid' === $c[1] ? 'Rent paid in advance during the period' : $c[0], isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 );
			}
		}
		return $rows;
	}

	public static function h_csv() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'No permission', 403 );
		}
		check_admin_referer( 'nurax_acc_csv' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		list( $from, $to, $label ) = self::period( isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : '', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		$sheet = isset( $_GET['sheet'] ) ? sanitize_key( wp_unslash( $_GET['sheet'] ) ) : 'pnl';
		$fmt   = isset( $_GET['fmt'] ) ? sanitize_key( wp_unslash( $_GET['fmt'] ) ) : 'xlsx';
		// phpcs:enable
		$sheet = in_array( $sheet, array( 'pnl', 'entries', 'products' ), true ) ? $sheet : 'pnl';
		$rows  = self::export_rows( $sheet, $from, $to );
		if ( class_exists( 'NURAX_Vault' ) && method_exists( 'NURAX_Vault', 'log' ) ) {
			NURAX_Vault::log( 'Report downloaded', 'Accounts - ' . $sheet . ' (' . strtoupper( $fmt ) . ', ' . $label . ')' );
		}
		// 1.100.0: professional Statement of Profit or Loss workbook (IAS 1 layout, comparative period, live formulas, ratios, notes, by product).
		if ( 'pnl' === $sheet && 'csv' !== $fmt && class_exists( 'NURAX_Xlsx_Statement' ) && class_exists( 'ZipArchive' ) ) {
			list( $pf, $pt, $plabel ) = self::prior_period( $from, $to );
			if ( NURAX_Xlsx_Statement::send_pnl( self::pnl( $from, $to ), self::pnl( $pf, $pt ), array(
				'from'   => $from,
				'to'     => $to,
				'label'  => $label,
				'pfrom'  => $pf,
				'pto'    => $pt,
				'plabel' => $plabel,
				'cats'   => self::categories(),
			) ) ) {
				exit;
			}
		}
		// 1.104.0: Expenses & income report at the same standard as the P&L (summary vs comparative period, register, suppliers & methods, notes and records to check).
		if ( 'entries' === $sheet && 'csv' !== $fmt && class_exists( 'NURAX_Xlsx_Statement' ) && method_exists( 'NURAX_Xlsx_Statement', 'send_entries' ) && class_exists( 'ZipArchive' ) ) {
			list( $pf, $pt, $plabel ) = self::prior_period( $from, $to );
			if ( NURAX_Xlsx_Statement::send_entries( self::entries( $from, $to ), self::entries( $pf, $pt ), array(
				'from'    => $from,
				'to'      => $to,
				'label'   => $label,
				'pfrom'   => $pf,
				'pto'     => $pt,
				'plabel'  => $plabel,
				'cats'    => self::categories(),
				'groups'  => self::groups(),
				'methods' => self::methods(),
			) ) ) {
				exit;
			}
		}
		if ( 'csv' !== $fmt && class_exists( 'NURAX_Xlsx' ) && NURAX_Xlsx::available() ) {
			$meta = array( 'period' => 'Period: ' . $label . ' (' . date_i18n( 'j M Y', strtotime( $from ) ) . ' - ' . date_i18n( 'j M Y', strtotime( $to ) ) . ')' );
			if ( 'products' !== $sheet ) {
				$meta['no_totals'] = true;
			}
			if ( NURAX_Xlsx::send( $rows, 'acc_' . $sheet, $meta ) ) {
				exit;
			}
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nura-beauty-' . ( 'pnl' === $sheet ? 'profit-and-loss' : ( 'entries' === $sheet ? 'expenses-and-income' : 'profit-by-product' ) ) . '_' . $from . '_to_' . $to . '.csv"' );
		$f = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $f, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( $rows as $row ) {
			fputcsv( $f, array_map( function ( $v ) {
				$v = (string) $v;
				return preg_match( '/^[=+\-@]/', $v ) && ! is_numeric( $v ) ? "'" . $v : $v; // spreadsheet formula-injection guard
			}, $row ) );
		}
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------ page */

	/** Accounting format: negatives (losses) in brackets, e.g. (KSh 25,000). */
	private static function m( $n ) {
		$n = round( (float) $n );
		return $n < 0 ? '(' . NURAX_Desk::money( abs( $n ) ) . ')' : NURAX_Desk::money( $n );
	}

	private static function pct( $a, $b ) {
		return $b > 0 ? self::pctv( $a / $b * 100 ) : 'n/a';
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
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // line id, 'new' or a voucher no. like pv-0007.
		// phpcs:enable
		list( $from, $to, $label ) = self::period( $p, $fr, $tt );
		$keep = array( 'p' => $p, 'from' => $fr, 'to' => $tt );
		$r    = self::pnl( $from, $to );
		list( $pf, $pt, $plabel ) = self::prior_period( $from, $to ); // 1.101.0: comparative period on screen.
		$rp   = self::pnl( $pf, $pt );
		$dl   = function ( $sheet, $fmt ) use ( $keep ) {
			return wp_nonce_url( add_query_arg( array_merge( $keep, array( 'action' => 'nurax_acc_csv', 'sheet' => $sheet, 'fmt' => $fmt ) ), admin_url( 'admin-post.php' ) ), 'nurax_acc_csv' );
		};
		$sheet_for = 'entries' === $tab ? 'entries' : ( 'products' === $tab ? 'products' : 'pnl' );
		$act  = NURAX_Desk::btn( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries', 'edit' => 'new' ) ) ), '+ Record payment or income', 'nd-btn--primary' )
			. '<a class="nd-btn nd-btn--xl" href="' . esc_url( $dl( $sheet_for, 'xlsx' ) ) . '" title="Branded Excel report: NURA black and gold header, logo, KSh formats, print-ready"><span class="nd-xl-ic" aria-hidden="true">X</span>Excel report</a>'
			. '<a class="nd-btn nd-btn--ghost nd-btn--csv" href="' . esc_url( $dl( $sheet_for, 'csv' ) ) . '" title="Plain data file for accountants and other software (CSV cannot hold colours or a logo)">CSV data</a>';
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
		$costs = $r['cogs'] + $r['opex'] + $r['setup'];
		echo '<div class="nd-kpis na-kpis">';
		NURAX_Desk::kpi( 'Revenue', self::m( $r['revenue'] ), ( $r['sales']['orders'] ? $r['sales']['orders'] . ' paid order(s), ' . $r['sales']['units'] . ' item(s)' : 'No paid orders yet' ) . ' | ' . self::delta( $r['revenue'], $rp['revenue'] ) . ' vs ' . $plabel, 'ink' );
		NURAX_Desk::kpi( 'Gross profit', self::m( $r['gross'] ), $r['revenue'] > 0 ? 'Margin ' . self::pct( $r['gross'], $r['revenue'] ) : 'Starts with your first sale', $r['gross'] >= 0 ? 'gold' : 'red' );
		NURAX_Desk::kpi( 'Costs', self::m( $costs ), 'Cost of sales ' . self::m( $r['cogs'] ) . ' | Running ' . self::m( $r['opex'] ) . ' | Setup ' . self::m( $r['setup'] ), '' );
		NURAX_Desk::kpi( $r['net'] >= 0 ? 'Net profit' : 'Net loss', self::m( $r['net'] ), ( $r['revenue'] > 0 ? 'Net margin ' . self::pct( $r['net'], $r['revenue'] ) : 'Setup period - no sales yet' ) . ' | ' . $plabel . ' ' . self::m( $rp['net'] ), $r['net'] >= 0 ? 'green' : 'red' );
		NURAX_Desk::kpi( 'Paid ahead, stock & equipment', self::m( $r['stock'] + $r['asset'] + $r['advance']['balance'] ), 'Rent paid in advance not yet used ' . self::m( $r['advance']['balance'] ) . ' | not a cost yet', '' );
		echo '</div>';

		// Profit at a glance (transparent equation).
		echo '<div class="na-eq" aria-label="How net profit is calculated">';
		foreach ( array(
			array( 'Revenue', $r['revenue'], '' ),
			array( 'Cost of sales', $r['cogs'], '-' ),
			array( 'Running costs', $r['opex'], '-' ),
			array( 'Setup costs', $r['setup'], '-' ),
		) as $i => $x ) {
			echo ( $i ? '<span class="na-eq__op" aria-hidden="true">' . esc_html( $x[2] ) . '</span>' : '' ) . '<span class="na-eq__t"><small>' . esc_html( $x[0] ) . '</small><b>' . esc_html( NURAX_Desk::money( $x[1] ) ) . '</b></span>';
		}
		echo '<span class="na-eq__op" aria-hidden="true">=</span><span class="na-eq__t na-eq__t--res ' . ( $r['net'] >= 0 ? 'is-pos' : 'is-neg' ) . '"><small>' . esc_html( $r['net'] >= 0 ? 'Net profit' : 'Net loss' ) . '</small><b>' . esc_html( self::m( $r['net'] ) ) . '</b></span></div>';

		if ( $r['sales']['missing'] > 0 ) {
			echo '<div class="na-alert"><strong>' . (int) $r['sales']['missing'] . ' item(s) sold have no buying price</strong>, so their cost is missing and profit looks higher than it is (' . esc_html( NURAX_Desk::money( $r['sales']['missing_rev'] ) ) . ' of sales). Add a buying price to these products - see <a href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'products' ) ) ) ) . '">Profit by product</a>.</div>';
		}

		$tabs = array( 'overview' => 'Profit & Loss', 'products' => 'Profit by product', 'entries' => 'Expenses & income', 'breakdown' => 'Where the money goes' );
		echo '<nav class="nt-tabs na-tabs">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="nt-tab' . ( $k === $tab ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => $k ) ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';

		if ( 'entries' === $tab ) {
			self::render_entries( $from, $to, $cat, $q, $edit, $keep );
		} elseif ( 'products' === $tab ) {
			self::render_products( $r );
		} elseif ( 'breakdown' === $tab ) {
			self::render_breakdown( $r );
		} else {
			self::render_pnl( $r, $label, $rp, $plabel, $from, $to, $pf, $pt );
			self::render_orders( $r, $keep );
		}
		echo '</div></div>';
	}

	private static function line( $label, $v, $cls = '', $sub = '' ) {
		echo '<tr class="' . esc_attr( $cls ) . '"><th scope="row">' . esc_html( $label ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</th><td>' . esc_html( self::m( $v ) ) . '</td></tr>';
	}

	private static function sec( $label ) {
		echo '<tr class="na-st__sec"><th colspan="2">' . esc_html( $label ) . '</th></tr>';
	}

	private static function lines_for( $r, $group ) {
		$n = 0;
		foreach ( self::categories() as $k => $c ) {
			if ( $group === $c[1] && ! empty( $r['by'][ $k ] ) ) {
				self::line( $c[0], $r['by'][ $k ] );
				$n++;
			}
		}
		return $n;
	}

	/** 1.102.0: percentage with one decimal; values that round to zero show as 0% (never -0%). */
	private static function pctv( $v ) {
		$v = round( (float) $v, 1 );
		return ( abs( $v ) < 0.05 ? '0' : rtrim( rtrim( number_format( $v, 1, '.', '' ), '0' ), '.' ) ) . '%';
	}

	/** "+12.5%" / "-3.0%" / "new" / "-" change label. */
	private static function delta( $a, $b ) {
		$a = (float) $a;
		$b = (float) $b;
		if ( 0.0 === round( $b, 2 ) ) {
			return 0.0 === round( $a, 2 ) ? '-' : 'new';
		}
		$d = round( ( $a - $b ) / abs( $b ) * 100, 1 );
		if ( abs( $d ) < 0.05 ) {
			return '0.0%'; // 1.102.0: never show '-0%'.
		}
		return ( $d > 0 ? '+' : '' ) . number_format( $d, 1 ) . '%';
	}

	/** One comparative statement row: label | note | this | prior | change KES | change % | % of revenue. */
	private static function crow( $label, $note, $c, $p, $rev, $cls = '', $sub = '' ) {
		$chg = $c - $p;
		$up  = $chg > 0.5 ? 'is-up' : ( $chg < -0.5 ? 'is-down' : '' );
		echo '<tr class="' . esc_attr( $cls ) . '"><th scope="row">' . esc_html( $label ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</th>'
			. '<td class="na-cs__n">' . esc_html( $note ) . '</td>'
			. '<td>' . esc_html( self::m( $c ) ) . '</td>'
			. '<td class="na-cs__p">' . esc_html( self::m( $p ) ) . '</td>'
			. '<td class="na-cs__d ' . esc_attr( $up ) . '">' . esc_html( self::m( $chg ) ) . '</td>'
			. '<td class="na-cs__d ' . esc_attr( $up ) . '">' . esc_html( self::delta( $c, $p ) ) . '</td>'
			. '<td class="na-cs__r">' . esc_html( $rev > 0 ? self::pctv( $c / $rev * 100 ) : '-' ) . '</td></tr>';
	}

	private static function csec( $label ) {
		echo '<tr class="na-cs__sec"><th colspan="7">' . esc_html( $label ) . '</th></tr>';
	}

	/** Comparative lines for one category group (shown when either period has an amount). Costs are negative. */
	private static function clines( $r, $rp, $group, $note, $rev ) {
		$n = 0;
		foreach ( self::categories() as $k => $c ) {
			if ( $group !== $c[1] ) {
				continue;
			}
			$a = isset( $r['by'][ $k ] ) ? (float) $r['by'][ $k ] : 0;
			$b = isset( $rp['by'][ $k ] ) ? (float) $rp['by'][ $k ] : 0;
			if ( 0.0 === $a && 0.0 === $b ) {
				continue;
			}
			self::crow( $c[0], $note, -$a, -$b, $rev );
			$n++;
		}
		return $n;
	}

	/**
	 * 1.101.0: Statement of Profit or Loss on screen, matching the Excel workbook
	 * (IAS 1, expenses by nature): this period beside the comparative period,
	 * change in KES and %, % of revenue, key ratios, notes and a cash movements
	 * summary.
	 */
	private static function render_pnl( $r, $label, $rp = null, $plabel = '', $from = '', $to = '', $pf = '', $pt = '' ) {
		$rp  = is_array( $rp ) ? $rp : self::pnl( '1999-01-01', '1999-01-01' );
		$s   = $r['sales'];
		$sp  = $rp['sales'];
		$rev = (float) $r['revenue'];
		$d   = function ( $x ) {
			return $x ? date_i18n( 'j M Y', strtotime( $x ) ) : '';
		};
		echo '<div class="na-grid na-grid--stmt"><section class="nd-card na-pnl na-cs-wrap"><header class="na-pnl__h"><span>NURA Beauty</span><h2>Statement of Profit or Loss</h2><p>' . esc_html( $label . ( $from ? ' (' . $d( $from ) . ' - ' . $d( $to ) . ')' : '' ) . ' compared with ' . $plabel . ( $pf ? ' (' . $d( $pf ) . ' - ' . $d( $pt ) . ')' : '' ) ) . '</p><p>Kenya Shillings (KES) &middot; expenses by nature (IAS 1) &middot; costs and losses in brackets &middot; unaudited management accounts</p></header>';
		echo '<div class="na-cs-scroll"><table class="na-cs"><thead><tr><th scope="col">Line item</th><th scope="col" class="na-cs__n">Note</th><th scope="col">' . esc_html( $label ) . '</th><th scope="col" class="na-cs__p">' . esc_html( $plabel ) . '</th><th scope="col">Change KES</th><th scope="col">Change %</th><th scope="col">% of revenue</th></tr></thead><tbody>';

		self::csec( 'Revenue' );
		self::crow( 'Website sales', '2', $s['online'], $sp['online'], $rev, '', 'Paid website orders, after discounts' );
		self::crow( 'In-shop sales (point of sale)', '2', $s['pos'], $sp['pos'], $rev );
		self::crow( 'Delivery charged to customers', '2', $s['shipping'], $sp['shipping'], $rev );
		self::crow( 'Other operating income', '2', $r['other_income'], $rp['other_income'], $rev );
		self::crow( 'Less: refunds and returns', '2', -$s['refunds'], -$sp['refunds'], $rev );
		self::crow( 'Total revenue', '', $r['revenue'], $rp['revenue'], $rev, 'na-cs__tot' );

		self::csec( 'Cost of sales' );
		self::crow( 'Cost of goods sold', '3', -$r['cogs_items'], -$rp['cogs_items'], $rev, '', $s['units'] ? $s['units'] . ' item(s) at their buying price' . ( $s['missing'] ? ' - ' . $s['missing'] . ' without a buying price' : '' ) : '' );
		self::clines( $r, $rp, 'cogs', '3', $rev );
		self::crow( 'Total cost of sales', '', -$r['cogs'], -$rp['cogs'], $rev, 'na-cs__sub' );
		self::crow( 'Gross profit', '', $r['gross'], $rp['gross'], $rev, 'na-cs__tot' );

		self::csec( 'Operating expenses' );
		$ra = isset( $r['by']['rent_advance'] ) ? (float) $r['by']['rent_advance'] : 0;
		$pa = isset( $rp['by']['rent_advance'] ) ? (float) $rp['by']['rent_advance'] : 0;
		if ( $ra || $pa ) {
			self::crow( 'Rent (released from rent paid in advance)', '6', -$ra, -$pa, $rev );
		}
		if ( ! self::clines( $r, $rp, 'opex', '4', $rev ) && ! $ra && ! $pa ) {
			echo '<tr class="na-st__empty"><td colspan="7">None recorded in either period.</td></tr>';
		}
		self::crow( 'Total operating expenses', '', -$r['opex'], -$rp['opex'], $rev, 'na-cs__sub' );
		self::crow( 'Operating profit / (loss)', '', $r['operating'], $rp['operating'], $rev, 'na-cs__tot' );

		if ( $r['setup'] > 0 || $rp['setup'] > 0 ) {
			self::csec( 'Non-recurring items (set-up costs)' );
			self::clines( $r, $rp, 'setup', '5', $rev );
			self::crow( 'Total non-recurring items', '', -$r['setup'], -$rp['setup'], $rev, 'na-cs__sub' );
		}
		self::crow( 'Profit / (loss) before tax', '', $r['net'], $rp['net'], $rev, 'na-cs__tot' );
		self::crow( 'Income tax expense', '7', 0, 0, $rev );
		self::crow( 'Profit / (loss) for the period', '', $r['net'], $rp['net'], $rev, 'na-cs__net ' . ( $r['net'] >= 0 ? 'is-pos' : 'is-neg' ) );
		echo '</tbody></table></div></section>';

		/* Side: ratios, cash movements, memorandum. */
		$m = function ( $a, $b ) {
			return $b > 0 ? self::pctv( $a / $b * 100 ) : '-';
		};
		echo '<aside class="na-side">';
		echo '<section class="nd-card na-ratios"><h3>Key ratios</h3><table class="na-kv"><thead><tr><th></th><th>' . esc_html( $label ) . '</th><th>' . esc_html( $plabel ) . '</th></tr></thead><tbody>';
		$rows = array(
			array( 'Gross margin', $m( $r['gross'], $r['revenue'] ), $m( $rp['gross'], $rp['revenue'] ) ),
			array( 'Operating margin', $m( $r['operating'], $r['revenue'] ), $m( $rp['operating'], $rp['revenue'] ) ),
			array( 'Net margin', $m( $r['net'], $r['revenue'] ), $m( $rp['net'], $rp['revenue'] ) ),
			array( 'Cost of sales ratio', $m( $r['cogs'], $r['revenue'] ), $m( $rp['cogs'], $rp['revenue'] ) ),
			array( 'Operating expense ratio', $m( $r['opex'], $r['revenue'] ), $m( $rp['opex'], $rp['revenue'] ) ),
			array( 'Revenue growth', self::delta( $r['revenue'], $rp['revenue'] ), '' ),
			array( 'Break-even revenue', ( $r['revenue'] > 0 && $r['gross'] > 0 ) ? self::m( $r['opex'] / ( $r['gross'] / $r['revenue'] ) ) : '-', ( $rp['revenue'] > 0 && $rp['gross'] > 0 ) ? self::m( $rp['opex'] / ( $rp['gross'] / $rp['revenue'] ) ) : '-' ),
			array( 'Items sold', (string) (int) $s['units'], (string) (int) $sp['units'] ),
		);
		foreach ( $rows as $x ) {
			echo '<tr><th scope="row">' . esc_html( $x[0] ) . '</th><td>' . esc_html( $x[1] ) . '</td><td>' . esc_html( $x[2] ) . '</td></tr>';
		}
		echo '</tbody></table></section>';

		$cash = function ( $x ) {
			$in  = (float) $x['revenue'] + (float) $x['capital'];
			$out = (float) $x['spent'] + (float) $x['drawings'];
			return array( (float) $x['revenue'], (float) $x['capital'], $in, $out, $in - $out );
		};
		$cc = $cash( $r );
		$cp = $cash( $rp );
		echo '<section class="nd-card na-cashmv"><h3>Cash movements summary</h3><table class="na-kv"><thead><tr><th></th><th>' . esc_html( $label ) . '</th><th>' . esc_html( $plabel ) . '</th></tr></thead><tbody>';
		$lines = array(
			array( 'Received from customers (revenue)', $cc[0], $cp[0], '' ),
			array( 'Owner capital introduced', $cc[1], $cp[1], '' ),
			array( 'Total cash in', $cc[2], $cp[2], 'is-sub' ),
			array( 'Paid out: stock, costs, set-up, deposits, equipment, prepaid rent, drawings', -$cc[3], -$cp[3], '' ),
			array( 'Net cash movement', $cc[4], $cp[4], 'is-tot' ),
		);
		foreach ( $lines as $x ) {
			echo '<tr class="' . esc_attr( $x[3] ) . '"><th scope="row">' . esc_html( $x[0] ) . '</th><td>' . esc_html( self::m( $x[1] ) ) . '</td><td>' . esc_html( self::m( $x[2] ) ) . '</td></tr>';
		}
		echo '</tbody></table><p class="na-muted">Cash basis estimate from NURA Desk records: paid sales count as received; every expense, stock, deposit, equipment, rent advance and drawing counts as paid when recorded.</p></section>';

		echo '<section class="nd-card na-bs"><h3>Not part of profit (memorandum)</h3><table class="na-kv"><thead><tr><th></th><th>' . esc_html( $label ) . '</th><th>' . esc_html( $plabel ) . '</th></tr></thead><tbody>';
		echo '<tr><th scope="row">Prepaid rent - unused balance at period end' . ( $r['advance']['unset'] ? '<small>months not set yet</small>' : '' ) . '</th><td>' . esc_html( self::m( $r['advance']['balance'] ) ) . '</td><td>' . esc_html( self::m( $rp['advance']['balance'] ) ) . '</td></tr>';
		foreach ( self::categories() as $k => $c ) {
			if ( in_array( $c[1], array( 'prepaid', 'stock', 'asset', 'owner' ), true ) ) {
				echo '<tr><th scope="row">' . esc_html( 'prepaid' === $c[1] ? 'Rent paid in advance this period' : $c[0] ) . '</th><td>' . esc_html( self::m( isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 ) ) . '</td><td>' . esc_html( self::m( isset( $rp['by'][ $k ] ) ? $rp['by'][ $k ] : 0 ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table></section></aside></div>';

		echo '<section class="nd-card na-notes"><h3>Notes to the statement</h3><ol>'
			. '<li><b>Basis of preparation.</b> Unaudited management accounts from NURA Desk records. Sales are recognised when the order is paid or completed; expenses on the date entered. Presentation follows IAS 1 with expenses by nature, in Kenya Shillings.</li>'
			. '<li><b>Revenue.</b> Website and shop sales after discounts, delivery charged to customers and other operating income. Refunds and returns are deducted when made.</li>'
			. '<li><b>Cost of sales.</b> The buying price of each item sold, captured at the time of sale, plus freight, duty and packaging not already in the buying price. Stock bought is inventory and becomes a cost only when sold (IAS 2).</li>'
			. '<li><b>Operating expenses.</b> Running costs by nature: rent, staff, utilities, marketing, delivery, payment charges, software, repairs, supplies, insurance, taxes and levies.</li>'
			. '<li><b>Non-recurring items.</b> One-off set-up costs (renovation, lease and registration fees, permits) shown separately so running performance compares fairly.</li>'
			. '<li><b>Prepaid rent.</b> Rent paid in advance is an asset and is released to rent evenly over the months it covers.</li>'
			. '<li><b>Income tax.</b> Not computed here; turnover or income tax is determined in the KRA return. Levies paid are in operating expenses.</li>'
			. '</ol><p class="na-muted">Comparative period: ' . esc_html( $plabel ) . '. Download the Excel report for the same statement with live formulas, ratios, notes and profit by product.</p></section>';
	}

	private static function render_products( $r ) {
		$rows = $r['sales']['products'];
		echo '<section class="nd-card na-prod"><div class="na-prod__h"><h2>Profit by product</h2><p class="na-muted">Each wig or product sold in this period: what customers paid, what it cost you (its buying price at the time of sale) and what you made.</p></div>';
		if ( ! $rows ) {
			echo '<p class="nd-empty">No paid sales in this period yet.</p></section>';
			return;
		}
		$t = array( 'qty' => 0, 'rev' => 0, 'cost' => 0 );
		echo '<div class="nd-table-wrap"><table class="nd-table na-tbl na-ptbl"><thead><tr><th>Product</th><th class="na-r">Sold</th><th class="na-r">Sales</th><th class="na-r">Cost</th><th class="na-r">Profit</th><th class="na-r">Margin</th></tr></thead><tbody>';
		foreach ( $rows as $p ) {
			$known  = $p['qty'] - $p['missing'];
			$profit = $p['rev'] - $p['cost'];
			$t['qty'] += $p['qty'];
			$t['rev'] += $p['rev'];
			$t['cost'] += $p['cost'];
			$edit = $p['id'] ? get_edit_post_link( $p['id'] ) : '';
			echo '<tr' . ( $p['missing'] ? ' class="is-missing"' : '' ) . '><td><strong>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $p['name'] ) . '</a>' : esc_html( $p['name'] ) ) . '</strong>';
			if ( $p['missing'] ) {
				echo '<small class="na-warn">No buying price on ' . (int) $p['missing'] . ' sold - <a href="' . esc_url( $edit ) . '">add it</a></small>';
			}
			if ( $p['sourced'] ) {
				echo '<small>' . (int) $p['sourced'] . ' sourced from a supplier (cost from Sourced sales)</small>';
			}
			echo '</td><td class="na-r">' . (int) $p['qty'] . '</td><td class="na-r">' . esc_html( NURAX_Desk::money( $p['rev'] ) ) . '</td>';
			echo '<td class="na-r">' . ( $known ? esc_html( NURAX_Desk::money( $p['cost'] ) ) : '<span class="na-muted">not set</span>' ) . '</td>';
			echo '<td class="na-r na-amt ' . ( $profit >= 0 ? 'na-amt--in' : 'na-amt--neg' ) . '">' . ( $p['missing'] === $p['qty'] ? '<span class="na-muted">-</span>' : esc_html( self::m( $profit ) ) ) . '</td>';
			echo '<td class="na-r">' . ( $p['missing'] === $p['qty'] ? '<span class="na-muted">-</span>' : esc_html( self::pct( $profit, $p['rev'] ) ) ) . '</td></tr>';
		}
		$tp = $t['rev'] - $t['cost'];
		echo '</tbody><tfoot><tr><th>Total</th><th class="na-r">' . (int) $t['qty'] . '</th><th class="na-r">' . esc_html( NURAX_Desk::money( $t['rev'] ) ) . '</th><th class="na-r">' . esc_html( NURAX_Desk::money( $t['cost'] ) ) . '</th><th class="na-r">' . esc_html( self::m( $tp ) ) . '</th><th class="na-r">' . esc_html( self::pct( $tp, $t['rev'] ) ) . '</th></tr></tfoot></table></div>';
		echo '<p class="na-muted na-tip">Sales here are product sales after discounts and refunds; delivery charges are not included. Set buying prices under Products &gt; Edit &gt; General (or on each colour under Variations).</p></section>';
	}

	private static function render_breakdown( $r ) {
		$cats  = self::categories();
		$items = array();
		foreach ( $r['by'] as $k => $v ) {
			if ( isset( $cats[ $k ] ) && in_array( $cats[ $k ][1], array( 'prepaid', 'stock', 'cogs', 'opex', 'setup', 'asset' ), true ) && $v > 0 ) {
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$msg  = isset( $_GET['nd_msg'] ) ? sanitize_key( wp_unslash( $_GET['nd_msg'] ) ) : '';
		$dup  = isset( $_GET['dup'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['dup'] ) ) ) : '';
		$use_draft = ! empty( $_GET['draft'] );
		// phpcs:enable
		// Load the voucher being edited (by voucher no. or by a line id).
		$txn   = '';
		$lines = array();
		if ( $edit && 'new' !== $edit ) {
			if ( ctype_digit( (string) $edit ) ) {
				$one = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d AND deleted = 0', absint( $edit ) ) ); // phpcs:ignore WordPress.DB
				if ( $one ) {
					$txn   = (string) $one->txn;
					$lines = $txn ? self::voucher( $txn ) : array( $one );
				}
			} elseif ( preg_match( '/^(pv|rv)-\d{4,}$/', (string) $edit ) ) {
				$txn   = strtoupper( $edit );
				$lines = self::voucher( $txn );
				if ( ! $lines ) {
					$txn = '';
				}
			}
		}
		$h0 = $lines ? $lines[0] : null;
		$H  = array(
			'entry_date'    => $h0 ? $h0->entry_date : NURAX_Desk::today(),
			'method'        => $h0 ? $h0->method : 'mpesa',
			'ref'           => $h0 ? $h0->ref : '',
			'payee'         => $h0 ? $h0->payee : '',
			'note'          => $h0 ? $h0->note : '',
			'reason'        => '',
			'receipt_total' => '',
			'cover_from'    => $h0 && ! empty( $h0->cover_from ) ? substr( $h0->cover_from, 0, 7 ) : '',
			'cover_months'  => $h0 && $h0->cover_months ? (int) $h0->cover_months : 2,
		);
		$receipt_id = 0;
		$L = array();
		foreach ( $lines as $e ) {
			$L[] = array( 'id' => (int) $e->id, 'category' => $e->category, 'description' => $e->description, 'amount' => (float) $e->amount );
			if ( ! $receipt_id && $e->receipt_id ) {
				$receipt_id = (int) $e->receipt_id;
			}
			if ( 'prepaid' === $e->category ) {
				$H['cover_from']   = ! empty( $e->cover_from ) ? substr( $e->cover_from, 0, 7 ) : '';
				$H['cover_months'] = $e->cover_months ? (int) $e->cover_months : 2;
			}
		}
		$note_orig = $H['note'];
		$draft     = $use_draft ? get_transient( 'nurax_acc_draft_' . get_current_user_id() ) : false;
		if ( is_array( $draft ) ) {
			foreach ( $H as $k => $v ) {
				if ( isset( $draft[ $k ] ) ) {
					$H[ $k ] = $draft[ $k ];
				}
			}
			$note_orig = isset( $draft['note_orig'] ) ? $draft['note_orig'] : $note_orig;
			if ( ! empty( $draft['lines'] ) ) {
				$L = $draft['lines'];
			}
		}
		if ( ! $L ) {
			$L = array( array( 'id' => 0, 'category' => 'rent', 'description' => '', 'amount' => '' ) );
		}
		$hid = '';
		foreach ( array_merge( $keep, array( 'cat' => $cat, 'tab' => 'entries' ) ) as $k => $v ) {
			$hid .= '<input type="hidden" name="_' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		$catopts = function ( $sel ) use ( $cats, $grp ) {
			$o = '';
			foreach ( $grp as $g => $gl ) {
				$o .= '<optgroup label="' . esc_attr( $gl ) . '">';
				foreach ( $cats as $k => $c ) {
					if ( $c[1] === $g ) {
						$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $sel, $k, false ) . ' title="' . esc_attr( $c[2] ) . '">' . esc_html( $c[0] ) . '</option>';
					}
				}
				$o .= '</optgroup>';
			}
			return $o;
		};
		$line_html = function ( $i, $ln ) use ( $catopts ) {
			return '<tr class="na-ln"><td><input type="hidden" name="lines[' . $i . '][id]" value="' . (int) $ln['id'] . '"><select name="lines[' . $i . '][category]" class="na-ln__cat">' . $catopts( $ln['category'] ) . '</select></td>'
				. '<td><input type="text" name="lines[' . $i . '][description]" maxlength="190" placeholder="e.g. LED shelf lights" value="' . esc_attr( $ln['description'] ) . '"></td>'
				. '<td><input type="text" inputmode="decimal" class="na-ln__amt" name="lines[' . $i . '][amount]" placeholder="0" value="' . esc_attr( '' === $ln['amount'] ? '' : (float) $ln['amount'] ) . '"></td>'
				. '<td><button type="button" class="nd-btn nd-btn--danger-ghost nd-btn--sm na-ln__rm" title="Remove this item">Remove</button></td></tr>';
		};

		echo '<div class="na-grid na-grid--entries">';
		echo '<section class="nd-card na-form' . ( $edit ? ' is-open' : '' ) . '" id="na-form"><h2>' . ( $txn ? 'Edit ' . esc_html( $txn ) : 'Record a payment or money received' ) . '</h2>';
		// Stopped-save notices.
		if ( 'acc_total' === $msg ) {
			echo '<p class="na-alert na-alert--err">The items do not add up to the receipt total you entered. Check each amount, or clear the receipt total. Nothing was saved.</p>';
		} elseif ( 'acc_reason' === $msg ) {
			echo '<p class="na-alert na-alert--err">You changed an amount, category or date, or removed an item. Please give a short reason for the change - it is kept in the change history. Nothing was saved yet.</p>';
		} elseif ( 'acc_dupref' === $msg && $dup ) {
			$dl = self::voucher( $dup );
			$ds = 0;
			foreach ( $dl as $x ) {
				$ds += (float) $x->amount;
			}
			echo '<p class="na-alert na-alert--err">This reference is already recorded on <strong>' . esc_html( $dup ) . '</strong>' . ( $dl ? ' (' . esc_html( date_i18n( 'j M Y', strtotime( $dl[0]->entry_date ) ) ) . ', ' . count( $dl ) . ' item' . ( 1 === count( $dl ) ? '' : 's' ) . ', ' . esc_html( NURAX_Desk::money( $ds ) ) . ')' : '' ) . '. If these items were paid with that same payment, <a href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries', 'edit' => strtolower( $dup ) ) ) ) ) . '#na-form">open ' . esc_html( $dup ) . ' and add them there</a> so the payment is recorded once. If it really is a different payment, tick the box below and save again. Nothing was saved yet.</p>';
		}
		echo '<p class="na-muted">One payment = one record. If one M-Pesa payment or receipt covered several items, add a line for each item: they share the date, reference, supplier and receipt, and the payment is counted once.</p>';
		echo '<form method="post" enctype="multipart/form-data" class="na-vform" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'nurax_acc_save' );
		echo '<input type="hidden" name="action" value="nurax_acc_save"><input type="hidden" name="txn" value="' . esc_attr( $txn ) . '"><input type="hidden" name="note_orig" value="' . esc_attr( $note_orig ) . '">' . $hid; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="na-f">';
		echo '<label><span>Date *</span><input type="date" name="entry_date" required value="' . esc_attr( $H['entry_date'] ) . '"></label>';
		echo '<label><span>Paid by</span><select name="method">';
		foreach ( self::methods() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $H['method'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>M-Pesa code / bank reference</span><input type="text" name="ref" maxlength="60" placeholder="e.g. SKL4X7Y2ZQ" value="' . esc_attr( $H['ref'] ) . '" style="text-transform:uppercase"></label>';
		echo '<label><span>Paid to / received from</span><input type="text" name="payee" maxlength="120" placeholder="Landlord, supplier, staff name..." value="' . esc_attr( $H['payee'] ) . '"></label>';
		echo '</div>';
		// Item lines.
		echo '<div class="nd-table-wrap"><table class="na-lines"><thead><tr><th>What is it for? *</th><th>Item / description *</th><th class="na-r">Amount (KSh) *</th><th></th></tr></thead><tbody data-na-lines>';
		$i = 0;
		foreach ( $L as $ln ) {
			echo $line_html( $i++, $ln ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody><tfoot><tr><td colspan="2"><button type="button" class="nd-btn nd-btn--ghost nd-btn--sm" data-na-add>+ Add another item on this receipt</button></td><td class="na-r"><strong data-na-sum>0</strong></td><td></td></tr></tfoot></table></div>';
		echo '<template id="na-ln-tpl">' . $line_html( '__I__', array( 'id' => 0, 'category' => 'supplies', 'description' => '', 'amount' => '' ) ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="na-f">';
		echo '<label><span>Receipt / M-Pesa total (optional check)</span><input type="text" inputmode="decimal" name="receipt_total" placeholder="Total on the receipt" value="' . esc_attr( $H['receipt_total'] ) . '"><small class="na-warn" data-na-diff hidden></small></label>';
		echo '<label class="na-f__w"><span>Note</span><input type="text" name="note" maxlength="255" value="' . esc_attr( $H['note'] ) . '"></label>';
		echo '<div class="na-f__w na-adv" data-na-adv><p class="na-adv__t">Rent paid in advance</p><div class="na-adv__g">';
		echo '<label><span>First month it covers</span><input type="month" name="cover_from" value="' . esc_attr( $H['cover_from'] ) . '"></label>';
		echo '<label><span>Number of months</span><input type="number" min="1" max="60" name="cover_months" value="' . esc_attr( (int) $H['cover_months'] ) . '"></label></div>';
		echo '<p class="na-muted">It is counted as rent month by month in the months it covers. Not sure yet? Leave the month empty - it stays as "paid in advance, not yet used" and never inflates any month.</p></div>';
		echo '<label class="na-f__w"><span>Receipt (photo or PDF)' . ( $receipt_id ? ' - <a href="' . esc_url( wp_get_attachment_url( $receipt_id ) ) . '" target="_blank" rel="noopener">view current</a>' : '' ) . '</span><input type="file" name="receipt" accept="image/*,application/pdf"></label>';
		if ( $txn ) {
			echo '<label class="na-f__w"><span>Reason for change (needed if you change an amount, category or date, or remove an item)</span><input type="text" name="reason" maxlength="255" placeholder="e.g. wrong category - glue is shop fit-out, not rent" value="' . esc_attr( $H['reason'] ) . '"></label>';
		}
		if ( 'acc_dupref' === $msg ) {
			echo '<label class="na-f__w na-check"><input type="checkbox" name="dup_ok" value="1"> <span>This is a different payment that has the same reference</span></label>';
		}
		echo '</div>';
		echo '<p class="na-f__acts"><button class="nd-btn nd-btn--primary">' . ( $txn ? 'Save changes' : 'Save' ) . '</button> ';
		if ( $txn || $edit ) {
			echo '<a class="nd-btn nd-btn--ghost" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries' ) ) ) ) . '">Cancel</a>';
		}
		echo '</p></form>';
		echo '<script>(function(){var f=document.querySelector(".na-vform");if(!f)return;var tb=f.querySelector("[data-na-lines]"),tpl=document.getElementById("na-ln-tpl"),n=' . (int) $i . ',adv=f.querySelector("[data-na-adv]"),sum=f.querySelector("[data-na-sum]"),rt=f.querySelector("[name=receipt_total]"),diff=f.querySelector("[data-na-diff]");'
			. 'function num(v){v=parseFloat(String(v).replace(/[^0-9.]/g,""));return isNaN(v)?0:v;}function fmt(v){return "KSh "+v.toLocaleString("en-KE",{minimumFractionDigits:0,maximumFractionDigits:2});}'
			. 'function calc(){var t=0,pre=false;tb.querySelectorAll(".na-ln").forEach(function(r){t+=num(r.querySelector(".na-ln__amt").value);if(r.querySelector(".na-ln__cat").value==="prepaid")pre=true;});sum.textContent=fmt(t);if(adv)adv.hidden=!pre;var ok=true;if(rt&&rt.value.trim()!==""){var d=Math.round((num(rt.value)-t)*100)/100;ok=Math.abs(d)<0.005;diff.hidden=ok;diff.textContent=ok?"":(d>0?fmt(d)+" of the receipt is not on any item yet":"Items are "+fmt(-d)+" more than the receipt");}else{diff.hidden=true;}return ok;}'
			. 'f.querySelector("[data-na-add]").addEventListener("click",function(){var h=tpl.innerHTML.split("__I__").join(String(n++));tb.insertAdjacentHTML("beforeend",h);calc();var r=tb.lastElementChild;if(r)r.querySelector("input[type=text]").focus();});'
			. 'tb.addEventListener("click",function(e){var b=e.target.closest(".na-ln__rm");if(!b)return;if(tb.querySelectorAll(".na-ln").length<2){alert("A record needs at least one item.");return;}b.closest(".na-ln").remove();calc();});'
			. 'f.addEventListener("input",calc);f.addEventListener("change",calc);f.addEventListener("submit",function(e){if(!calc()){e.preventDefault();alert("The items do not add up to the receipt total.");}});calc();})();</script>';
		// Change history.
		if ( $txn ) {
			$hist = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::log_table() . ' WHERE txn = %s ORDER BY id DESC LIMIT 40', $txn ) ); // phpcs:ignore WordPress.DB
			if ( $hist ) {
				$names = array( 'entry_date' => 'date', 'category' => 'category', 'description' => 'item', 'amount' => 'amount', 'method' => 'paid by', 'ref' => 'reference', 'payee' => 'paid to', 'note' => 'note', 'receipt_id' => 'receipt', 'cover_from' => 'first month', 'cover_months' => 'months' );
				echo '<details class="na-hist"><summary>Change history (' . count( $hist ) . ')</summary><ul>';
				foreach ( $hist as $hrow ) {
					$u   = $hrow->user_id ? get_userdata( $hrow->user_id ) : null;
					$chg = $hrow->changes ? json_decode( $hrow->changes, true ) : array();
					$bits = array();
					if ( 'edited' === $hrow->action && is_array( $chg ) ) {
						foreach ( $chg as $f => $ab ) {
							$fa = $ab[0];
							$fb = $ab[1];
							if ( 'category' === $f ) {
								$fa = isset( $cats[ $fa ] ) ? $cats[ $fa ][0] : $fa;
								$fb = isset( $cats[ $fb ] ) ? $cats[ $fb ][0] : $fb;
							} elseif ( 'amount' === $f ) {
								$fa = NURAX_Desk::money( (float) $fa );
								$fb = NURAX_Desk::money( (float) $fb );
							} elseif ( 'receipt_id' === $f ) {
								$fa = $fa ? 'attached' : 'none';
								$fb = $fb ? 'new file' : 'none';
							}
							$bits[] = ( isset( $names[ $f ] ) ? $names[ $f ] : $f ) . ': ' . ( '' === (string) $fa ? '(blank)' : $fa ) . ' -> ' . ( '' === (string) $fb ? '(blank)' : $fb );
						}
					} elseif ( is_array( $chg ) ) {
						$d    = isset( $chg['description'] ) ? ( 'added' === $hrow->action ? $chg['description'][1] : $chg['description'][0] ) : '';
						$am   = isset( $chg['amount'] ) ? ( 'added' === $hrow->action ? $chg['amount'][1] : $chg['amount'][0] ) : 0;
						$bits[] = $d . ' ' . NURAX_Desk::money( (float) $am );
					}
					echo '<li><span class="na-muted">' . esc_html( date_i18n( 'j M Y H:i', strtotime( $hrow->at ) ) ) . ' - ' . esc_html( $u ? $u->display_name : 'System' ) . '</span> <strong>' . esc_html( ucfirst( $hrow->action ) ) . '</strong> ' . esc_html( implode( '; ', $bits ) ) . ( $hrow->reason ? ' <em>Reason: ' . esc_html( $hrow->reason ) . '</em>' : '' ) . '</li>';
				}
				echo '</ul></details>';
			}
		}
		echo '<p class="na-muted na-tip">Tip: stock you buy, refundable deposits and furniture/equipment are recorded but kept out of profit - you still own them. Stock becomes a cost when it sells, at its buying price.</p></section>';

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
		echo '</select><input type="search" name="q" placeholder="Search item, payee, M-Pesa code, PV number" value="' . esc_attr( $q ) . '"><button class="nd-btn nd-btn--ghost nd-btn--sm">Search</button></form>';
		if ( ! $list ) {
			echo '<p class="nd-empty">Nothing recorded for this period yet. Use the form to add your first entry.</p></section></div>';
			return;
		}
		// Voucher sizes / totals (whole voucher, not only the filtered lines).
		$vt = array();
		$ids = array_unique( array_filter( wp_list_pluck( $list, 'txn' ) ) );
		if ( $ids ) {
			$ph = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT txn, COUNT(*) n, SUM(amount) s FROM ' . self::table() . " WHERE deleted = 0 AND txn IN ({$ph}) GROUP BY txn", array_values( $ids ) ) ) as $v ) { // phpcs:ignore WordPress.DB
				$vt[ $v->txn ] = $v;
			}
		}
		$tot  = 0;
		$seen = array();
		echo '<div class="nd-table-wrap"><table class="nd-table na-tbl"><thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Paid by</th><th class="na-r">Amount</th><th></th></tr></thead><tbody>';
		foreach ( $list as $e ) {
			$g    = self::group_of( $e->category );
			$tot += (float) $e->amount;
			$v    = $e->txn && isset( $vt[ $e->txn ] ) ? $vt[ $e->txn ] : null;
			$multi = $v && (int) $v->n > 1;
			$seen[ $e->txn ] = isset( $seen[ $e->txn ] ) ? $seen[ $e->txn ] + 1 : 1;
			echo '<tr' . ( $multi ? ' class="na-multi"' : '' ) . '><td class="na-nowrap">' . esc_html( date_i18n( 'j M Y', strtotime( $e->entry_date ) ) ) . ( $e->txn ? '<small class="na-vch">' . esc_html( $e->txn ) . '</small>' : '' ) . '</td>';
			echo '<td><strong>' . esc_html( $e->description ) . '</strong>' . ( $e->payee ? '<small>' . esc_html( $e->payee ) . '</small>' : '' ) . ( $multi ? '<small class="na-vch">One of ' . (int) $v->n . ' items on this payment - total ' . esc_html( NURAX_Desk::money( $v->s ) ) . '</small>' : '' ) . ( $e->note ? '<small class="na-note">' . esc_html( $e->note ) . '</small>' : '' ) . ( 'prepaid' === $e->category ? '<small class="na-cover">' . esc_html( self::cover_text( $e ) ) . '</small>' : '' ) . '</td>';
			echo '<td><span class="na-chip na-g--' . esc_attr( $g ) . '">' . esc_html( isset( $cats[ $e->category ] ) ? $cats[ $e->category ][0] : $e->category ) . '</span></td>';
			echo '<td class="na-nowrap">' . esc_html( self::methods()[ $e->method ] ?? $e->method ) . ( $e->ref ? '<small>' . esc_html( $e->ref ) . '</small>' : '' ) . '</td>';
			echo '<td class="na-r na-amt na-amt--' . esc_attr( self::is_in( $e->category ) ? 'in' : 'out' ) . '">' . esc_html( ( self::is_in( $e->category ) ? '+ ' : '' ) . NURAX_Desk::money( $e->amount ) ) . '</td>';
			echo '<td class="na-acts">' . ( $e->receipt_id ? '<a class="nd-btn nd-btn--ghost nd-btn--sm" href="' . esc_url( wp_get_attachment_url( $e->receipt_id ) ) . '" target="_blank" rel="noopener">Receipt</a>' : '' );
			echo '<a class="nd-btn nd-btn--ghost nd-btn--sm" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries', 'edit' => $e->txn ? strtolower( $e->txn ) : $e->id, 'cat' => $cat ) ) ) ) . '#na-form">Edit</a>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="var r=prompt(\'Remove this item? It will no longer count in the accounts. Reason (kept in the change history):\');if(r===null)return false;this.reason.value=r;return true;">';
			wp_nonce_field( 'nurax_acc_delete' );
			echo '<input type="hidden" name="action" value="nurax_acc_delete"><input type="hidden" name="id" value="' . (int) $e->id . '"><input type="hidden" name="reason" value="">' . $hid . '<button class="nd-btn nd-btn--danger-ghost nd-btn--sm">Remove</button></form></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$nv = count( array_unique( wp_list_pluck( $list, 'txn' ) ) );
		echo '</tbody><tfoot><tr><th colspan="4">' . count( $list ) . ' item' . ( 1 === count( $list ) ? '' : 's' ) . ' on ' . (int) $nv . ' payment' . ( 1 === $nv ? '' : 's' ) . '</th><th class="na-r">' . esc_html( NURAX_Desk::money( $tot ) ) . '</th><th></th></tr></tfoot></table></div></section></div>';
	}
}
