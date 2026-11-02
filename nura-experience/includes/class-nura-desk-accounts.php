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

	const DBV     = '1.1.0'; // 1.1.0 (plugin 1.86.0): cover_from / cover_months for rent paid in advance.
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
			PRIMARY KEY  (id),
			KEY entry_date (entry_date),
			KEY category (category),
			KEY deleted (deleted)
		) {$c};" );
		update_option( self::DBV_OPT, self::DBV, false );
		self::seed();
		self::migrate_advance();
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
	public static function sales( $from, $to ) {
		$key = 'nurax_acc_v2_' . md5( $from . $to );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$out = array( 'online' => 0.0, 'pos' => 0.0, 'shipping' => 0.0, 'refunds' => 0.0, 'orders' => 0, 'cost' => 0.0, 'units' => 0, 'missing' => 0, 'missing_rev' => 0.0, 'products' => array() );
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

	public static function h_save() {
		self::guard( 'nurax_acc_save' );
		global $wpdb;
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$date = NURAX_Desk::valid_date( isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : '' );
		$cat  = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$amt  = isset( $_POST['amount'] ) ? (float) preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST['amount'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		if ( ! $date || ! isset( self::categories()[ $cat ] ) || $amt <= 0 || '' === $desc ) {
			self::back( array( 'nd_msg' => 'acc_invalid', 'tab' => 'entries', 'edit' => $id ? $id : 'new' ) );
		}
		$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'mpesa';
		$cover_m = isset( $_POST['cover_months'] ) ? max( 0, min( 60, absint( $_POST['cover_months'] ) ) ) : 0;
		$cover_f = isset( $_POST['cover_from'] ) ? sanitize_text_field( wp_unslash( $_POST['cover_from'] ) ) : '';
		$cover_f = preg_match( '/^\d{4}-\d{2}$/', $cover_f ) ? $cover_f . '-01' : null;
		$row    = array(
			'entry_date'  => $date,
			'category'    => $cat,
			'description' => mb_substr( $desc, 0, 190 ),
			'amount'      => round( $amt, 2 ),
			'method'      => isset( self::methods()[ $method ] ) ? $method : 'other',
			'ref'         => mb_substr( isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '', 0, 60 ),
			'payee'       => mb_substr( isset( $_POST['payee'] ) ? sanitize_text_field( wp_unslash( $_POST['payee'] ) ) : '', 0, 120 ),
			'note'        => mb_substr( isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '', 0, 255 ),
			'cover_from'  => 'prepaid' === $cat ? $cover_f : null,
			'cover_months' => 'prepaid' === $cat ? max( 1, $cover_m ) : 0,
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
			$rows = array( array( 'Date', 'Type', 'Category', 'Description', 'Amount (KES)', 'Paid by', 'Reference', 'Paid to / received from', 'Note', 'Receipt link' ) );
			foreach ( array_reverse( self::entries( $from, $to ) ) as $e ) {
				$g      = self::group_of( $e->category );
				$rows[] = array( $e->entry_date, $grp[ $g ], isset( $cats[ $e->category ] ) ? $cats[ $e->category ][0] : $e->category, $e->description, round( (float) $e->amount, 2 ), self::methods()[ $e->method ] ?? $e->method, $e->ref, $e->payee, trim( $e->note . ( 'prepaid' === $e->category ? ' ' . self::cover_text( $e ) : '' ) ), $e->receipt_id ? (string) wp_get_attachment_url( $e->receipt_id ) : '' );
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
		return $b > 0 ? round( $a / $b * 100, 1 ) . '%' : 'n/a';
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
		$dl   = function ( $sheet, $fmt ) use ( $keep ) {
			return wp_nonce_url( add_query_arg( array_merge( $keep, array( 'action' => 'nurax_acc_csv', 'sheet' => $sheet, 'fmt' => $fmt ) ), admin_url( 'admin-post.php' ) ), 'nurax_acc_csv' );
		};
		$sheet_for = 'entries' === $tab ? 'entries' : ( 'products' === $tab ? 'products' : 'pnl' );
		$act  = NURAX_Desk::btn( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries', 'edit' => 'new' ) ) ), '+ Record expense or income', 'nd-btn--primary' )
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
		NURAX_Desk::kpi( 'Revenue', self::m( $r['revenue'] ), $r['sales']['orders'] ? $r['sales']['orders'] . ' paid order(s), ' . $r['sales']['units'] . ' item(s)' : 'No paid orders yet', 'ink' );
		NURAX_Desk::kpi( 'Gross profit', self::m( $r['gross'] ), $r['revenue'] > 0 ? 'Margin ' . self::pct( $r['gross'], $r['revenue'] ) : 'Starts with your first sale', $r['gross'] >= 0 ? 'gold' : 'red' );
		NURAX_Desk::kpi( 'Costs', self::m( $costs ), 'Cost of sales ' . self::m( $r['cogs'] ) . ' | Running ' . self::m( $r['opex'] ) . ' | Setup ' . self::m( $r['setup'] ), '' );
		NURAX_Desk::kpi( $r['net'] >= 0 ? 'Net profit' : 'Net loss', self::m( $r['net'] ), $r['revenue'] > 0 ? 'Net margin ' . self::pct( $r['net'], $r['revenue'] ) : 'Setup period - no sales yet', $r['net'] >= 0 ? 'green' : 'red' );
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
			self::render_pnl( $r, $label );
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

	private static function render_pnl( $r, $label ) {
		$s = $r['sales'];
		echo '<div class="na-grid"><section class="nd-card na-pnl"><header class="na-pnl__h"><span>NURA Beauty</span><h2>Profit &amp; Loss statement</h2><p>' . esc_html( $label ) . ' &middot; Kenyan Shillings &middot; losses shown in brackets</p></header><table class="na-st"><tbody>';

		self::sec( 'Revenue' );
		self::line( 'Website sales', $s['online'], '', 'Paid website orders, after discounts' );
		self::line( 'In-shop sales', $s['pos'], '', 'Point of sale' );
		self::line( 'Delivery charged to customers', $s['shipping'] );
		if ( $r['other_income'] > 0 ) {
			self::line( 'Other income', $r['other_income'] );
		}
		if ( $s['refunds'] > 0 ) {
			self::line( 'Less: refunds', -$s['refunds'] );
		}
		self::line( 'Total revenue', $r['revenue'], 'na-st__tot' );

		self::sec( 'Cost of sales' );
		self::line( 'Cost of wigs & products sold', $r['cogs_items'], '', $s['units'] ? $s['units'] . ' item(s) at their buying price' . ( $s['missing'] ? ' - ' . $s['missing'] . ' without a buying price' : '' ) : 'No items sold' );
		self::lines_for( $r, 'cogs' );
		self::line( 'Total cost of sales', $r['cogs'], 'na-st__sub' );
		self::line( 'Gross profit', $r['gross'], 'na-st__tot', $r['revenue'] > 0 ? 'Gross margin ' . self::pct( $r['gross'], $r['revenue'] ) : '' );

		self::sec( 'Operating expenses' );
		$adv_line = ! empty( $r['by']['rent_advance'] );
		if ( $adv_line ) {
			self::line( 'Rent (from rent paid in advance)', $r['by']['rent_advance'], '', 'Share of the advance for the months in this period' );
		}
		if ( ! self::lines_for( $r, 'opex' ) && ! $adv_line ) {
			echo '<tr class="na-st__empty"><td colspan="2">None recorded in this period.</td></tr>';
		}
		self::line( 'Total operating expenses', $r['opex'], 'na-st__sub' );
		self::line( 'Operating profit', $r['operating'], 'na-st__tot' );

		if ( $r['setup'] > 0 ) {
			self::sec( 'One-off setup costs' );
			self::lines_for( $r, 'setup' );
			self::line( 'Total setup costs', $r['setup'], 'na-st__sub' );
		}
		self::line( $r['net'] >= 0 ? 'Net profit' : 'Net loss', $r['net'], 'na-st__net ' . ( $r['net'] >= 0 ? 'is-pos' : 'is-neg' ), $r['revenue'] > 0 ? 'Net margin ' . self::pct( $r['net'], $r['revenue'] ) : 'No sales in this period yet' );
		echo '</tbody></table></section>';

		echo '<aside class="na-side"><section class="nd-card na-bs"><h3>Not part of profit</h3><p class="na-muted">Money paid out that is still yours, and money the owner put in or took out.</p><dl>';
		echo '<div class="na-bs__adv"><dt>Rent paid in advance - not yet used<small>Balance at the end of this period' . ( $r['advance']['unset'] ? '; months not set yet' : '' ) . '</small></dt><dd>' . esc_html( self::m( $r['advance']['balance'] ) ) . '</dd></div>';
		foreach ( self::categories() as $k => $c ) {
			if ( in_array( $c[1], array( 'prepaid', 'stock', 'asset', 'owner' ), true ) ) {
				echo '<div><dt>' . esc_html( ( 'prepaid' === $c[1] ? 'Paid in advance this period' : $c[0] ) ) . '</dt><dd>' . esc_html( self::m( isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 ) ) . '</dd></div>';
			}
		}
		echo '</dl></section>';
		echo '<section class="nd-card na-cash"><h3>Cash paid out this period</h3><strong>' . esc_html( self::m( $r['spent'] + $r['drawings'] ) ) . '</strong><p class="na-muted">Everything you paid: stock, running and setup costs, deposits, equipment and drawings.</p></section>';
		echo '<section class="nd-card na-help"><h3>How profit is worked out</h3><ol>'
			. '<li><b>Revenue</b> comes in automatically from paid website and shop orders.</li>'
			. '<li><b>Cost of sales</b> is the buying price of each wig you actually sold. Stock you buy is not a cost until it sells, so it is never counted twice.</li>'
			. '<li><b>Operating expenses</b> are the running costs: rent, staff, ads, delivery, charges.</li>'
			. '<li><b>Setup costs</b> (renovation, lease fees, permits) have their own line so your monthly running profit stays clear.</li>'
			. '<li><b>Rent paid in advance</b> (e.g. a 2-month non-refundable advance) is not a cost when you pay it - it becomes rent in the months it covers.</li><li>Stock, refundable deposits and equipment are <b>not costs</b> - you still own them.</li></ol></section></aside></div>';
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
		$cf = $row && ! empty( $row->cover_from ) ? substr( $row->cover_from, 0, 7 ) : '';
		echo '<div class="na-f__w na-adv" data-na-adv><p class="na-adv__t">Rent paid in advance</p><div class="na-adv__g">';
		echo '<label><span>First month it covers</span><input type="month" name="cover_from" value="' . esc_attr( $cf ) . '"></label>';
		echo '<label><span>Number of months</span><input type="number" min="1" max="60" name="cover_months" value="' . esc_attr( $row && $row->cover_months ? (int) $row->cover_months : 2 ) . '"></label></div>';
		echo '<p class="na-muted">It is counted as rent month by month in the months it covers. Not sure yet? Leave the month empty - it stays as "paid in advance, not yet used" and never inflates any month.</p></div>';
		echo '<label class="na-f__w"><span>Receipt (photo or PDF)' . ( $row && $row->receipt_id ? ' - <a href="' . esc_url( wp_get_attachment_url( $row->receipt_id ) ) . '" target="_blank" rel="noopener">view current</a>' : '' ) . '</span><input type="file" name="receipt" accept="image/*,application/pdf"></label></div>';
		echo '<p class="na-f__acts"><button class="nd-btn nd-btn--primary">' . ( $row ? 'Save changes' : 'Save entry' ) . '</button> ';
		if ( $row ) {
			echo '<a class="nd-btn nd-btn--ghost" href="' . esc_url( NURAX_Desk::url( self::PAGE, array_merge( $keep, array( 'tab' => 'entries' ) ) ) ) . '">Cancel</a>';
		}
		echo '</p></form>';
		echo '<script>(function(){var s=document.querySelector(\'.na-form select[name=category]\'),b=document.querySelector(\'[data-na-adv]\');if(!s||!b)return;function t(){b.hidden=s.value!==\'prepaid\';}s.addEventListener(\'change\',t);t();})();</script>';
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
			echo '<td><strong>' . esc_html( $e->description ) . '</strong>' . ( $e->payee ? '<small>' . esc_html( $e->payee ) . '</small>' : '' ) . ( $e->note ? '<small class="na-note">' . esc_html( $e->note ) . '</small>' : '' ) . ( 'prepaid' === $e->category ? '<small class="na-cover">' . esc_html( self::cover_text( $e ) ) . '</small>' : '' ) . '</td>';
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
