<?php
/**
 * NURA Desk - Point of sale, sales, invoices and receipts (NURA Experience 1.60.0).
 *
 * Every sale is a real WooCommerce order, so stock, reports and the customer's
 * account stay in sync:
 *   - Online checkout orders (created_via "checkout").
 *   - Walk-in / phone sales rung up here (created_via "nura_pos").
 *   - WhatsApp pop-up orders turned into a sale with one click.
 * Each sale can be printed as an A4 invoice or an 80 mm receipt (or saved as
 * PDF from the print dialog), shared by WhatsApp as a link, or e-mailed.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_POS {

	public static function hooks() {
		add_action( 'wp_ajax_nurax_pos_search', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_nurax_pos_customers', array( __CLASS__, 'ajax_customers' ) );
		add_action( 'wp_ajax_nurax_pos_sale', array( __CLASS__, 'ajax_sale' ) );
		add_action( 'wp_ajax_nurax_pos_order', array( __CLASS__, 'ajax_order' ) );
		add_action( 'admin_post_nurax_desk_doc', array( __CLASS__, 'h_doc' ) );
		add_action( 'template_redirect', array( __CLASS__, 'public_doc' ) );
	}

	public static function methods() {
		return array(
			'mpesa' => 'M-Pesa',
			'cash'  => 'Cash',
			'card'  => 'Card',
			'bank'  => 'Bank transfer',
			'other' => 'Other',
		);
	}

	public static function channels() {
		return array( 'walk-in' => 'Walk-in', 'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'instagram' => 'Instagram / TikTok', 'online' => 'Online shop', 'admin' => 'Added in WooCommerce' );
	}

	public static function channel( $order ) {
		$c = (string) $order->get_meta( '_nura_channel' );
		if ( '' !== $c ) {
			return $c;
		}
		$v = $order->get_created_via();
		return 'nura_pos' === $v ? 'walk-in' : ( 'admin' === $v ? 'admin' : 'online' );
	}

	public static function is_paid( $order ) {
		return $order->is_paid() || 'completed' === $order->get_status();
	}

	public static function doc_url( $order, $type, $print = true ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=nurax_desk_doc&type=' . $type . '&order=' . $order->get_id() . ( $print ? '&print=1' : '' ) ), 'nurax_doc_' . $order->get_id() );
	}

	public static function public_url( $order, $type = 'invoice' ) {
		return add_query_arg( array( 'nura_doc' => $type, 'o' => $order->get_id(), 'k' => $order->get_order_key() ), home_url( '/' ) );
	}

	public static function biz() {
		$s = NURAX_Desk::settings();
		$p = function_exists( 'nura_opt' ) ? (string) nura_opt( 'nura_phone' ) : '';
		return array(
			'name'  => '' !== trim( $s['biz_name'] ) ? $s['biz_name'] : 'NURA Beauty',
			'pin'   => (string) $s['biz_pin'],
			'phone' => '' !== trim( $s['biz_phone'] ) ? $s['biz_phone'] : ( $p ? $p : '+254 714 994 898' ),
			'email' => '' !== trim( $s['biz_email'] ) ? $s['biz_email'] : 'care@nurabeauty.co.ke',
			'addr'  => NURAX_Desk::shop_address(),
			'web'   => preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ),
			'note'  => (string) $s['invoice_note'],
		);
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	private static function guard_ajax() {
		check_ajax_referer( 'nurax_pos', 'nonce' );
		if ( ! current_user_can( NURAX_Desk::CAP ) || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
	}

	private static function tile( $p, $parent = null ) {
		$img = wp_get_attachment_image_url( $p->get_image_id() ? $p->get_image_id() : ( $parent ? $parent->get_image_id() : 0 ), 'thumbnail' );
		$opt = $p->is_type( 'variation' ) ? wp_strip_all_tags( wc_get_formatted_variation( $p, true, false, false ) ) : '';
		return array(
			'id'    => $p->get_id(),
			'name'  => $parent ? $parent->get_name() : $p->get_name(),
			'opt'   => $opt,
			'price' => (float) wc_get_price_to_display( $p ),
			'sku'   => (string) $p->get_sku(),
			'stock' => $p->managing_stock() ? (int) $p->get_stock_quantity() : null,
			'in'    => $p->is_in_stock(),
			'img'   => $img ? $img : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'thumbnail' ) : '' ),
		);
	}

	public static function ajax_search() {
		self::guard_ajax();
		$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$cat = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0;
		$ids = array();
		if ( '' !== $q ) {
			$ds  = WC_Data_Store::load( 'product' );
			$ids = $ds->search_products( $q, '', true, false, 40 );
		} else {
			$args = array( 'status' => 'publish', 'limit' => 40, 'orderby' => 'popularity', 'return' => 'ids' );
			if ( $cat ) {
				$term = get_term( $cat, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$args['category'] = array( $term->slug );
				}
			}
			$ids = wc_get_products( $args );
		}
		$out = array();
		foreach ( array_unique( array_map( 'absint', (array) $ids ) ) as $id ) {
			$p = $id ? wc_get_product( $id ) : null;
			if ( ! $p || 'publish' !== get_post_status( $p->is_type( 'variation' ) ? $p->get_parent_id() : $id ) ) {
				continue;
			}
			if ( $p->is_type( 'variable' ) ) {
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v && $v->is_purchasable() && $v->get_price() !== '' ) {
						$out[] = self::tile( $v, $p );
					}
				}
			} elseif ( $p->is_type( 'variation' ) ) {
				$out[] = self::tile( $p, wc_get_product( $p->get_parent_id() ) );
			} elseif ( $p->is_purchasable() && '' !== $p->get_price() ) {
				$out[] = self::tile( $p );
			}
			if ( count( $out ) >= 60 ) {
				break;
			}
		}
		wp_send_json_success( $out );
	}

	public static function ajax_customers() {
		self::guard_ajax();
		$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$out = array();
		if ( strlen( $q ) < 2 ) {
			wp_send_json_success( $out );
		}
		$seen = array();
		$ph   = NURAX_Desk::phone( $q );
		if ( class_exists( 'NURAX_Desk_Mkt' ) ) {
			foreach ( NURAX_Desk_Mkt::find( $q, 10 ) as $c ) {
				$k = $c->phone ? $c->phone : $c->email;
				if ( isset( $seen[ $k ] ) ) {
					continue;
				}
				$seen[ $k ] = 1;
				$out[]      = array( 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email, 'user_id' => (int) $c->user_id, 'orders' => (int) $c->orders, 'spent' => (float) $c->spent );
			}
		}
		$users = get_users( array(
			'search'         => '*' . $q . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'number'         => 8,
		) );
		if ( $ph ) {
			$users = array_merge( $users, get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => substr( $ph, -9 ), 'meta_compare' => 'LIKE', 'number' => 5 ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		foreach ( $users as $u ) {
			$p = NURAX_Desk::phone( get_user_meta( $u->ID, 'billing_phone', true ) );
			$k = $p ? $p : $u->user_email;
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = 1;
			$name       = trim( get_user_meta( $u->ID, 'billing_first_name', true ) . ' ' . get_user_meta( $u->ID, 'billing_last_name', true ) );
			$out[]      = array( 'name' => $name ? $name : $u->display_name, 'phone' => $p, 'email' => $u->user_email, 'user_id' => (int) $u->ID, 'orders' => 0, 'spent' => 0 );
		}
		wp_send_json_success( array_slice( $out, 0, 12 ) );
	}

	/** Complete a sale (or save an unpaid invoice). */
	public static function ajax_sale() {
		self::guard_ajax();
		$raw = isset( $_POST['sale'] ) ? json_decode( wp_unslash( $_POST['sale'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $raw ) || empty( $raw['items'] ) || ! is_array( $raw['items'] ) ) {
			wp_send_json_error( array( 'message' => 'The cart is empty.' ), 400 );
		}
		$cust  = isset( $raw['customer'] ) && is_array( $raw['customer'] ) ? $raw['customer'] : array();
		$name  = mb_substr( sanitize_text_field( isset( $cust['name'] ) ? $cust['name'] : '' ), 0, 120 );
		$phone = NURAX_Desk::phone( isset( $cust['phone'] ) ? $cust['phone'] : '' );
		$email = isset( $cust['email'] ) && is_email( $cust['email'] ) ? sanitize_email( $cust['email'] ) : '';
		$uid   = isset( $cust['user_id'] ) ? absint( $cust['user_id'] ) : 0;
		if ( ! $uid && $email ) {
			$u   = get_user_by( 'email', $email );
			$uid = $u ? (int) $u->ID : 0;
		}
		$pay     = isset( $raw['payment'] ) && is_array( $raw['payment'] ) ? $raw['payment'] : array();
		$method  = isset( $pay['method'] ) && array_key_exists( $pay['method'], self::methods() ) ? $pay['method'] : 'cash';
		$paid    = ! empty( $pay['paid'] );
		$ref     = mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\- ]/', '', isset( $pay['ref'] ) ? (string) $pay['ref'] : '' ) ), 0, 40 );
		$channel = isset( $raw['channel'] ) && array_key_exists( $raw['channel'], self::channels() ) ? $raw['channel'] : 'walk-in';

		try {
			$order = wc_create_order( array( 'customer_id' => $uid, 'created_via' => 'nura_pos' ) );
			if ( is_wp_error( $order ) ) {
				throw new Exception( $order->get_error_message() );
			}
			$lines = 0;
			foreach ( $raw['items'] as $it ) {
				$p   = isset( $it['id'] ) ? wc_get_product( absint( $it['id'] ) ) : null;
				$qty = isset( $it['qty'] ) ? max( 1, min( 999, absint( $it['qty'] ) ) ) : 1;
				$pr  = isset( $it['price'] ) ? max( 0, round( (float) $it['price'], 2 ) ) : 0;
				if ( $p && ! NURAX_Staff::can( 'edit_price' ) && abs( $pr - (float) wc_get_price_to_display( $p ) ) > 0.5 ) {
					NURAX_Staff::log( 'blocked', 'Blocked: tried to sell ' . $p->get_name() . ' at ' . NURAX_Desk::money( $pr ) . ' (shop price ' . NURAX_Desk::money( wc_get_price_to_display( $p ) ) . ')', array( 'level' => 'warn' ) );
					throw new Exception( 'Only the owner can change prices at the till. ' . $p->get_name() . ' must be sold at ' . NURAX_Desk::money( wc_get_price_to_display( $p ) ) . '.' );
				}
				if ( $p ) {
					$order->add_product( $p, $qty, array( 'subtotal' => $pr * $qty, 'total' => $pr * $qty ) );
					$lines++;
				} elseif ( ! empty( $it['custom'] ) && ! empty( $it['name'] ) ) {
					$item = new WC_Order_Item_Fee();
					$item->set_name( mb_substr( sanitize_text_field( $it['name'] ), 0, 120 ) . ( $qty > 1 ? ' x ' . $qty : '' ) );
					$item->set_amount( $pr * $qty );
					$item->set_total( $pr * $qty );
					$item->set_tax_status( 'none' );
					$order->add_item( $item );
					$lines++;
				}
			}
			if ( ! $lines ) {
				throw new Exception( 'None of the items could be added.' );
			}
			$sub  = 0;
			foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $li ) {
				$sub += (float) $li->get_total();
			}
			$disc = isset( $raw['discount'] ) ? (float) $raw['discount'] : 0;
			if ( ! empty( $raw['discount_pct'] ) ) {
				$disc = round( $sub * min( 100, max( 0, $disc ) ) / 100 );
			}
			$disc = min( $sub, max( 0, round( $disc, 2 ) ) );
			if ( $disc > 0 && $sub > 0 && $disc / $sub * 100 > NURAX_Staff::max_disc() + 0.01 ) {
				NURAX_Staff::log( 'blocked', 'Blocked: tried to give a ' . round( $disc / $sub * 100 ) . '% discount (' . NURAX_Desk::money( $disc ) . ')', array( 'level' => 'warn' ) );
				throw new Exception( 'The highest discount you can give is ' . NURAX_Staff::max_disc() . '%. Ask the owner for a bigger discount.' );
			}
			if ( $disc > 0 ) {
				$f = new WC_Order_Item_Fee();
				$f->set_name( 'Discount' );
				$f->set_amount( -$disc );
				$f->set_total( -$disc );
				$f->set_tax_status( 'none' );
				$order->add_item( $f );
			}
			$del = isset( $raw['delivery'] ) ? max( 0, round( (float) $raw['delivery'], 2 ) ) : 0;
			if ( $del > 0 ) {
				$sh = new WC_Order_Item_Shipping();
				$sh->set_method_title( 'Delivery' . ( ! empty( $raw['delivery_to'] ) ? ' - ' . mb_substr( sanitize_text_field( $raw['delivery_to'] ), 0, 80 ) : '' ) );
				$sh->set_method_id( 'flat_rate' );
				$sh->set_total( $del );
				$order->add_item( $sh );
			}
			$parts = explode( ' ', $name, 2 );
			$order->set_billing_first_name( $parts[0] ? $parts[0] : 'Walk-in' );
			$order->set_billing_last_name( isset( $parts[1] ) ? $parts[1] : ( $name ? '' : 'customer' ) );
			$order->set_billing_phone( $phone ? '+' . $phone : '' );
			$order->set_billing_email( $email );
			$order->set_billing_country( 'KE' );
			if ( ! empty( $raw['delivery_to'] ) ) {
				$order->set_billing_address_1( mb_substr( sanitize_text_field( $raw['delivery_to'] ), 0, 120 ) );
			}
			$order->set_payment_method( 'nura_pos_' . $method );
			$order->set_payment_method_title( self::methods()[ $method ] );
			if ( ! empty( $raw['note'] ) ) {
				$order->set_customer_note( mb_substr( sanitize_textarea_field( $raw['note'] ), 0, 500 ) );
			}
			$order->update_meta_data( '_nura_channel', $channel );
			$order->update_meta_data( '_nura_cashier', get_current_user_id() );
			if ( $ref ) {
				$order->update_meta_data( '_nura_pay_ref', $ref );
				$order->set_transaction_id( $ref );
			}
			$order->calculate_totals( false );
			$total    = (float) $order->get_total();
			$tendered = isset( $pay['tendered'] ) ? max( 0, (float) $pay['tendered'] ) : 0;
			if ( $paid && 'cash' === $method && $tendered > 0 ) {
				$order->update_meta_data( '_nura_tendered', $tendered );
				$order->update_meta_data( '_nura_change', max( 0, $tendered - $total ) );
			}
			$wa = isset( $raw['wa_order'] ) ? absint( $raw['wa_order'] ) : 0;
			if ( $wa ) {
				$order->update_meta_data( '_nura_wa_order', $wa );
			}
			if ( $paid ) {
				$order->set_date_paid( time() );
				$order->set_status( 'completed', 'Sale completed at NURA Desk POS (' . self::methods()[ $method ] . ( $ref ? ' ' . $ref : '' ) . ').' );
			} else {
				$order->set_status( 'pending', 'Invoice created at NURA Desk POS - awaiting payment.' );
			}
			$order->save();
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => 'Could not save the sale: ' . $e->getMessage() ), 500 );
		}

		if ( $wa && class_exists( 'NURAX_Desk_Orders' ) && NURAX_Desk_Orders::CPT === get_post_type( $wa ) ) {
			update_post_meta( $wa, NURAX_Desk_Orders::STATUS, $paid ? 'delivered' : 'confirmed' );
			if ( $paid ) {
				NURAX_Desk_Orders::set_paid( $wa, true, $ref );
			}
			update_post_meta( $wa, '_nwo_wc_order', $order->get_id() );
			NURAX_Desk_Orders::add_log( $wa, 'Sale #' . $order->get_order_number() . ' created at the POS.' );
		}
		if ( class_exists( 'NURAX_Desk_Mkt' ) && ( $phone || $email ) ) {
			NURAX_Desk_Mkt::upsert( array( 'name' => $name, 'phone' => $phone, 'email' => $email, 'user_id' => $uid, 'source' => 'pos', 'consent' => ! empty( $cust['consent'] ), 'spent' => $paid ? $total : 0 ) );
		}
		NURAX_Desk::bust();
		NURAX_Staff::log( 'sale', ( $paid ? 'Sale #' : 'Unpaid invoice #' ) . $order->get_order_number() . ' - ' . ( $name ? $name : 'walk-in' ) . ' - ' . self::methods()[ $method ] . ( $disc > 0 ? ' - discount ' . NURAX_Desk::money( $disc ) : '' ), array( 'object' => 'order', 'object_id' => $order->get_id(), 'amount' => (float) $order->get_total() ) );
		wp_send_json_success( self::summary( $order ) );
	}

	/** Data the register needs after a sale / for re-printing. */
	public static function summary( $order ) {
		$phone = NURAX_Desk::phone( $order->get_billing_phone() );
		return array(
			'id'       => $order->get_id(),
			'number'   => $order->get_order_number(),
			'total'    => (float) $order->get_total(),
			'paid'     => self::is_paid( $order ),
			'change'   => (float) $order->get_meta( '_nura_change' ),
			'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email'    => $order->get_billing_email(),
			'receipt'  => self::doc_url( $order, 'receipt' ),
			'invoice'  => self::doc_url( $order, 'invoice' ),
			'wa'       => $phone ? NURAX_Desk::wa( $phone, self::wa_text( $order ) ) : '',
			'edit'     => $order->get_edit_order_url(),
		);
	}

	/** E-mail-safe (table + inline styles) invoice / receipt for Gmail, Outlook and phones. */
	public static function email_html( $order ) {
		$b     = self::biz();
		$logo  = self::logo_url();
		$paid  = self::is_paid( $order );
		$type  = $paid ? 'receipt' : 'invoice';
		$num   = $order->get_order_number();
		$date  = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'j F Y' ) : '';
		$first = trim( $order->get_billing_first_name() );
		$link  = self::public_url( $order, $type );
		$meth  = $order->get_payment_method_title();
		$ref   = $order->get_meta( '_nura_pay_ref' ) ? $order->get_meta( '_nura_pay_ref' ) : $order->get_transaction_id();
		$mpesa = trim( (string) NURAX_Desk::settings()['mpesa_details'] );
		$f     = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
		$m     = function ( $n ) { return esc_html( NURAX_Desk::money( $n ) ); };

		$rows = '';
		foreach ( $order->get_items() as $it ) {
			$meta = array();
			foreach ( $it->get_formatted_meta_data( '_', true ) as $mm ) {
				$meta[] = wp_strip_all_tags( $mm->display_key . ': ' . $mm->display_value );
			}
			$rows .= '<tr><td style="' . $f . 'padding:14px 0;border-bottom:1px solid #eee6d8;font-size:14px;color:#1b1b1b;"><strong style="font-weight:600;">' . esc_html( $it->get_name() ) . '</strong>' . ( $meta ? '<br><span style="font-size:12px;color:#8a8174;">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '' ) . '<br><span style="font-size:12px;color:#8a8174;">Qty ' . (int) $it->get_quantity() . '</span></td><td align="right" style="' . $f . 'padding:14px 0;border-bottom:1px solid #eee6d8;font-size:14px;font-weight:600;color:#0E0E0E;white-space:nowrap;">' . $m( $it->get_total() ) . '</td></tr>';
		}
		$disc = 0;
		foreach ( $order->get_fees() as $fe ) {
			if ( (float) $fe->get_total() < 0 ) {
				$disc += (float) $fe->get_total();
				continue;
			}
			$rows .= '<tr><td style="' . $f . 'padding:14px 0;border-bottom:1px solid #eee6d8;font-size:14px;"><strong style="font-weight:600;">' . esc_html( $fe->get_name() ) . '</strong></td><td align="right" style="' . $f . 'padding:14px 0;border-bottom:1px solid #eee6d8;font-size:14px;font-weight:600;white-space:nowrap;">' . $m( $fe->get_total() ) . '</td></tr>';
		}
		$ln = function ( $l, $v, $strong = false ) use ( $f ) {
			return '<tr><td style="' . $f . 'padding:4px 0;font-size:' . ( $strong ? '16px;font-weight:700;color:#0E0E0E' : '13px;color:#6f675d' ) . ';">' . $l . '</td><td align="right" style="' . $f . 'padding:4px 0;font-size:' . ( $strong ? '16px;font-weight:700' : '13px;font-weight:600' ) . ';color:#0E0E0E;white-space:nowrap;">' . $v . '</td></tr>';
		};
		$tot = $ln( 'Subtotal', $m( $order->get_subtotal() ) );
		if ( $disc < 0 ) {
			$tot .= $ln( 'Discount', '- ' . $m( abs( $disc ) ) );
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$tot .= $ln( 'Delivery', $m( $order->get_shipping_total() ) );
		}
		if ( (float) $order->get_total_tax() > 0 ) {
			$tot .= $ln( 'VAT', $m( $order->get_total_tax() ) );
		}
		$tot .= $ln( 'Total', $m( $order->get_total() ), true );

		$band = $paid
			? '<td style="' . $f . 'padding:16px 20px;background:#e7f4ec;border-radius:10px;"><span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;color:#1f7a45;">Paid in full</span><br><span style="font-size:13px;color:#1f7a45;">' . esc_html( trim( $meth . ( $ref ? ' · ' . $ref : '' ) ) ) . '</span></td><td align="right" style="' . $f . 'padding:16px 20px;background:#e7f4ec;border-radius:10px;font-size:20px;font-weight:700;color:#1f7a45;white-space:nowrap;">' . $m( $order->get_total() ) . '</td>'
			: '<td style="' . $f . 'padding:16px 20px;background:#0E0E0E;border-radius:10px 0 0 10px;"><span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;color:#E4C983;">Amount due</span>' . ( $mpesa ? '<br><span style="font-size:13px;color:#e9e2d6;">M-Pesa: ' . esc_html( $mpesa ) . ' · Ref ' . esc_html( $num ) . '</span>' : '' ) . '</td><td align="right" style="' . $f . 'padding:16px 20px;background:#0E0E0E;border-radius:0 10px 10px 0;font-size:20px;font-weight:700;color:#ffffff;white-space:nowrap;">' . $m( $order->get_total() ) . '</td>';

		$brand = $logo
			? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $b['name'] ) . '" width="220" style="display:block;margin:0 auto;width:220px;max-width:70%;height:auto;border:0;">'
			: '<div style="font-family:Georgia,serif;font-size:28px;letter-spacing:8px;font-weight:700;color:#C9A24B;">' . esc_html( strtoupper( $b['name'] ) ) . '</div>';
		$title = $paid ? 'Your receipt' : 'Your invoice';
		$intro = $paid
			? 'Thank you for your purchase' . ( $first ? ', ' . esc_html( $first ) : '' ) . '. Here is your receipt for order #' . esc_html( $num ) . '.'
			: ( $first ? 'Hi ' . esc_html( $first ) . ', here' : 'Here' ) . ' is your invoice #' . esc_html( $num ) . '. You can pay any time using the details below.';

		$html  = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $b['name'] . ' ' . $title ) . '</title></head>';
		$html .= '<body style="margin:0;padding:0;background:#f3eee6;">';
		$html .= '<div style="display:none;max-height:0;overflow:hidden;">' . esc_html( ( $paid ? 'Receipt' : 'Invoice' ) . ' #' . $num . ' - ' . NURAX_Desk::money( $order->get_total() ) . ( $paid ? ' paid. Thank you!' : ' due.' ) ) . '</div>';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3eee6;"><tr><td align="center" style="padding:24px 12px;">';
		$html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">';
		$html .= '<tr><td style="height:5px;background:#C9A24B;line-height:5px;font-size:0;">&nbsp;</td></tr>';
		$html .= '<tr><td align="center" style="padding:30px 24px 22px;background:#0E0E0E;">' . $brand . '</td></tr>';
		$html .= '<tr><td style="padding:30px 32px 6px;' . $f . '">';
		$html .= '<p style="margin:0 0 6px;font-size:11px;letter-spacing:3px;text-transform:uppercase;font-weight:700;color:#a07a24;">' . ( $paid ? 'Receipt' : 'Invoice' ) . ' #' . esc_html( $num ) . ' · ' . esc_html( $date ) . '</p>';
		$html .= '<h1 style="margin:0 0 12px;font-family:Georgia,\'Times New Roman\',serif;font-size:28px;line-height:1.2;font-weight:600;color:#0E0E0E;">' . esc_html( $title ) . '</h1>';
		$html .= '<p style="margin:0;font-size:15px;line-height:1.6;color:#4a443c;">' . $intro . '</p></td></tr>';
		$html .= '<tr><td style="padding:18px 32px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table></td></tr>';
		$html .= '<tr><td style="padding:12px 32px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $tot . '</table></td></tr>';
		$html .= '<tr><td style="padding:18px 32px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' . $band . '</tr></table></td></tr>';
		$html .= '<tr><td align="center" style="padding:26px 32px 8px;"><a href="' . esc_url( $link ) . '" style="' . $f . 'display:inline-block;padding:15px 30px;background:#C9A24B;border-radius:999px;color:#0E0E0E;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;text-decoration:none;">View &amp; download ' . ( $paid ? 'receipt' : 'invoice' ) . '</a></td></tr>';
		$html .= '<tr><td align="center" style="padding:6px 32px 26px;' . $f . 'font-size:12px;color:#8a8174;">Open the link to print or save it as a PDF.</td></tr>';
		$html .= '<tr><td style="padding:20px 32px;background:#faf6ef;border-top:1px solid #eee6d8;' . $f . 'font-size:12px;line-height:1.6;color:#6f675d;">' . ( '' !== trim( $b['note'] ) ? esc_html( $b['note'] ) : 'Unworn wigs in their original condition can be returned within 7 days of delivery.' ) . '<br>Questions? WhatsApp or call <strong style="color:#0E0E0E;">' . esc_html( $b['phone'] ) . '</strong> or reply to this e-mail.</td></tr>';
		$html .= '<tr><td align="center" style="padding:22px 32px;background:#0E0E0E;' . $f . 'font-size:12px;line-height:1.7;color:#cfc6b8;"><span style="font-family:Georgia,serif;font-style:italic;font-size:16px;color:#E4C983;">Thank you for wearing NURA.</span><br>' . esc_html( $b['name'] ) . ' · ' . esc_html( $b['addr'] ) . '<br><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#E4C983;text-decoration:none;">' . esc_html( $b['web'] ) . '</a>' . ( $b['pin'] ? ' · KRA PIN ' . esc_html( $b['pin'] ) : '' ) . '</td></tr>';
		$html .= '</table></td></tr></table></body></html>';
		return $html;
	}

	public static function wa_text( $order ) {
		$b     = self::biz();
		$paid  = self::is_paid( $order );
		$num   = $order->get_order_number();
		$first = trim( $order->get_billing_first_name() );
		$mpesa = trim( (string) NURAX_Desk::settings()['mpesa_details'] );
		$ref   = $order->get_meta( '_nura_pay_ref' ) ? $order->get_meta( '_nura_pay_ref' ) : $order->get_transaction_id();
		$date  = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'j M Y' ) : '';
		$l     = array();
		$l[]   = 'Hi' . ( $first ? ' ' . $first : '' ) . ',';
		$l[]   = $paid ? 'Thank you for shopping with *' . $b['name'] . '*. Here is your receipt.' : 'Here is your invoice from *' . $b['name'] . '*.';
		$l[]   = '';
		$l[]   = '*' . ( $paid ? 'RECEIPT' : 'INVOICE' ) . ' #' . $num . '*' . ( $date ? '  ·  ' . $date : '' );
		$l[]   = '━━━━━━━━━━━━━━';
		foreach ( $order->get_items() as $it ) {
			$l[] = '• ' . $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
			$l[] = '      ' . NURAX_Desk::money( $it->get_total() );
		}
		$disc = 0;
		foreach ( $order->get_fees() as $f ) {
			if ( (float) $f->get_total() < 0 ) {
				$disc += (float) $f->get_total();
				continue;
			}
			$l[] = '• ' . $f->get_name();
			$l[] = '      ' . NURAX_Desk::money( $f->get_total() );
		}
		$l[] = '━━━━━━━━━━━━━━';
		if ( $disc < 0 ) {
			$l[] = 'Discount: - ' . NURAX_Desk::money( abs( $disc ) );
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$l[] = 'Delivery: ' . NURAX_Desk::money( $order->get_shipping_total() );
		}
		$l[] = '*TOTAL: ' . NURAX_Desk::money( $order->get_total() ) . '*';
		$l[] = '';
		if ( $paid ) {
			$l[] = '*PAID*' . ( $order->get_payment_method_title() ? ' via ' . $order->get_payment_method_title() : '' ) . ( $ref ? ' (' . $ref . ')' : '' );
		} else {
			$l[] = '*AMOUNT DUE: ' . NURAX_Desk::money( $order->get_total() ) . '*';
			if ( $mpesa ) {
				$l[] = 'Pay via M-Pesa: ' . $mpesa;
				$l[] = 'Reference: ' . $num;
			}
		}
		$l[] = '';
		$l[] = 'View or download your ' . ( $paid ? 'receipt' : 'invoice' ) . ':';
		$l[] = self::public_url( $order, $paid ? 'receipt' : 'invoice' );
		$l[] = '';
		$l[] = 'Thank you for wearing NURA.';
		$l[] = '_' . $b['name'] . ' · ' . $b['phone'] . '_';
		return implode( "\n", $l );
	}

	/** Order actions from the sales list / register: email, mark paid. */
	public static function ajax_order() {
		self::guard_ajax();
		$order = wc_get_order( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		$op    = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ), 404 );
		}
		if ( 'email' === $op ) {
			$to = isset( $_POST['to'] ) && is_email( wp_unslash( $_POST['to'] ) ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : $order->get_billing_email();
			if ( ! is_email( $to ) ) {
				wp_send_json_error( array( 'message' => 'No e-mail address for this customer.' ), 400 );
			}
			$type = self::is_paid( $order ) ? 'receipt' : 'invoice';
			$html = self::email_html( $order );
			$subj = 'Your ' . self::biz()['name'] . ' ' . $type . ' #' . $order->get_order_number() . ' - ' . NURAX_Desk::money( $order->get_total() ) . ( 'receipt' === $type ? ' paid' : ' due' );
			$hdrs = array( 'Content-Type: text/html; charset=UTF-8' );
			if ( is_email( self::biz()['email'] ) ) {
				$hdrs[] = 'Reply-To: ' . self::biz()['name'] . ' <' . self::biz()['email'] . '>';
			}
			$ok   = wp_mail( $to, $subj, $html, $hdrs );
			if ( $ok ) {
				$order->add_order_note( ucfirst( $type ) . ' e-mailed to ' . $to . ' from NURA Desk.' );
			}
			$ok ? wp_send_json_success( array( 'message' => 'E-mailed to ' . $to ) ) : wp_send_json_error( array( 'message' => 'The e-mail could not be sent. Check your site e-mail (SMTP) settings.' ), 500 );
		}
		if ( 'paid' === $op ) {
			$method = isset( $_POST['method'] ) && array_key_exists( wp_unslash( $_POST['method'] ), self::methods() ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'mpesa';
			$ref    = isset( $_POST['ref'] ) ? mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\- ]/', '', wp_unslash( $_POST['ref'] ) ) ), 0, 40 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$order->set_payment_method( 'nura_pos_' . $method );
			$order->set_payment_method_title( self::methods()[ $method ] );
			if ( $ref ) {
				$order->update_meta_data( '_nura_pay_ref', $ref );
				$order->set_transaction_id( $ref );
			}
			$order->set_date_paid( time() );
			$order->set_status( 'completed', 'Marked paid at NURA Desk (' . self::methods()[ $method ] . ( $ref ? ' ' . $ref : '' ) . ').' );
			$order->save();
			NURAX_Desk::bust();
			wp_send_json_success( self::summary( $order ) );
		}
		if ( 'complete' === $op ) {
			$order->set_status( 'completed', 'Marked fulfilled (packed / delivered) at NURA Desk.' );
			$order->save();
			NURAX_Desk::bust();
			wp_send_json_success( array( 'message' => 'Order #' . $order->get_order_number() . ' fulfilled' ) );
		}
		wp_send_json_error( array( 'message' => 'Unknown action.' ), 400 );
	}

	/** Online orders that are paid / on hold and still need packing or delivery. */
	public static function to_pack( $limit = 20 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return (array) wc_get_orders( array( 'limit' => $limit, 'type' => 'shop_order', 'status' => array( 'wc-processing', 'wc-on-hold' ), 'orderby' => 'date', 'order' => 'ASC' ) );
	}

	/** Overview panel: online orders to pack. */
	public static function pack_list( $limit = 8 ) {
		$rows = self::to_pack( $limit );
		if ( ! $rows ) {
			echo '<p class="nd-empty-s">No online orders waiting to be packed.</p>';
			return;
		}
		echo '<ul class="nd-list">';
		foreach ( $rows as $o ) {
			$items = array();
			foreach ( $o->get_items() as $it ) {
				$items[] = ( $it->get_quantity() > 1 ? $it->get_quantity() . ' x ' : '' ) . $it->get_name();
			}
			$name  = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
			$phone = NURAX_Desk::phone( $o->get_billing_phone() );
			echo '<li><a class="nd-list__main" href="' . esc_url( $o->get_edit_order_url() ) . '"><strong>#' . esc_html( $o->get_order_number() . ' ' . $name ) . '</strong><small>' . esc_html( implode( ', ', $items ) ) . '</small></a>';
			echo '<span class="nd-list__meta"><b>' . esc_html( NURAX_Desk::money( $o->get_total() ) ) . '</b><small>' . esc_html( ( self::is_paid( $o ) ? 'Paid' : 'On hold' ) . ' - ' . ( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'D j M, H:i' ) : '' ) ) . '</small></span>';
			echo '<span class="nd-list__acts"><a class="nd-ico-btn" target="_blank" rel="noopener" href="' . esc_url( self::doc_url( $o, 'invoice' ) ) . '">Invoice</a>';
			if ( $phone ) {
				echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $phone, self::wa_text( $o ) ) ) . '">WhatsApp</a>';
			}
			echo '<button type="button" class="nd-ico-btn nd-ico-btn--ok" data-pos-complete="' . (int) $o->get_id() . '">Fulfilled</button></span></li>';
		}
		echo '</ul>';
	}

	/** Money in today across every channel (paid WooCommerce orders + Lipa Mdogo payments). */
	public static function today_total() {
		global $wpdb;
		$t   = NURAX_Desk::today();
		$sum = 0;
		$n   = 0;
		if ( function_exists( 'wc_get_orders' ) ) {
			foreach ( (array) self::orders( $t, $t, -1 ) as $o ) {
				if ( self::is_paid( $o ) ) {
					$sum += (float) $o->get_total();
					$n++;
				}
			}
		}
		$plan = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . NURAX_Desk::t_pay() . ' WHERE voided = 0 AND paid_at >= %s', $t . ' 00:00:00' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'sum' => $sum + $plan, 'orders' => $n, 'plan' => $plan );
	}

	/* ------------------------------------------------------------------ */
	/* Admin pages                                                         */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		if ( ! function_exists( 'wc_get_orders' ) ) {
			NURAX_Desk::header( NURAX_Desk::P_POS, 'Point of sale' );
			echo '<p>WooCommerce is required.</p>';
			NURAX_Desk::footer();
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'register'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'sales' === $view ? self::sales() : self::register();
	}

	private static function subnav( $on ) {
		return '<nav class="nd-seg">' .
			'<a class="' . ( 'register' === $on ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_POS ) ) . '">New sale</a>' .
			'<a class="' . ( 'sales' === $on ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_POS, array( 'view' => 'sales' ) ) ) . '">Sales &amp; invoices</a></nav>';
	}

	private static function register() {
		// Pre-load a WhatsApp order into the cart.
		$pre = array();
		$wa  = isset( $_GET['from_wa'] ) ? absint( $_GET['from_wa'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $wa && class_exists( 'NURAX_Desk_Orders' ) && NURAX_Desk_Orders::CPT === get_post_type( $wa ) ) {
			$d     = NURAX_Desk_Orders::data( $wa );
			$items = array();
			foreach ( (array) $d['items'] as $it ) {
				$p = ! empty( $it['id'] ) ? wc_get_product( absint( $it['id'] ) ) : null;
				if ( $p ) {
					$t        = self::tile( $p, $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : null );
					$t['qty'] = isset( $it['qty'] ) ? max( 1, (int) $it['qty'] ) : 1;
					$items[]  = $t;
				}
			}
			$pre = array(
				'wa_order' => $wa,
				'items'    => $items,
				'customer' => array( 'name' => $d['name'], 'phone' => $d['phone'], 'email' => '' ),
				'delivery' => 'pickup' === $d['fulfil'] ? 0 : ( '' !== (string) $d['ship'] ? (float) $d['ship'] : 0 ),
				'delivery_to' => 'pickup' === $d['fulfil'] ? '' : $d['delivery'],
				'channel'  => 'whatsapp',
				'ref'      => $d['ref'],
			);
		}
		wp_localize_script( 'nurax-desk', 'NURAX_POS', array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'nurax_pos' ),
			'methods'  => self::methods(),
			'channels' => self::channels(),
			'pre'      => $pre,
			'mpesa'    => (string) NURAX_Desk::settings()['mpesa_details'],
		) );
		NURAX_Desk::header( NURAX_Desk::P_POS, 'Point of sale', self::subnav( 'register' ) );
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 12 ) );
		?>
		<div class="pos" data-pos>
			<section class="pos__left">
				<div class="pos__search">
					<input type="search" placeholder="Search wigs by name, SKU, colour or length  ( / )" data-pos-q autocomplete="off">
				</div>
				<div class="pos__cats" data-pos-cats>
					<button type="button" class="is-on" data-cat="0">Popular</button>
					<?php foreach ( is_array( $cats ) ? $cats : array() as $c ) : ?>
						<?php if ( 'uncategorized' === $c->slug ) { continue; } ?>
						<button type="button" data-cat="<?php echo (int) $c->term_id; ?>"><?php echo esc_html( $c->name ); ?></button>
					<?php endforeach; ?>
				</div>
				<div class="pos__grid" data-pos-grid><p class="pos__hint">Loading products...</p></div>
				<button type="button" class="pos__custom" data-pos-custom>+ Add a custom item or service (e.g. installation, revamp)</button>
			</section>

			<aside class="pos__cart">
				<header class="pos__cart-h">
					<strong>Current sale</strong>
					<select data-pos-channel aria-label="Sale channel">
						<?php foreach ( self::channels() as $k => $l ) : if ( 'online' === $k ) { continue; } ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="pos__clear" data-pos-clear>Clear</button>
				</header>

				<div class="pos__body">
				<div class="pos__cust" data-pos-cust>
					<div class="pos__cust-search">
						<input type="search" placeholder="Customer: search name or phone, or type a new one" data-pos-cq autocomplete="off">
						<ul class="pos__cust-list" data-pos-clist hidden></ul>
					</div>
					<div class="pos__cust-fields">
						<input type="text" placeholder="Full name" data-pos-c="name">
						<input type="tel" placeholder="Phone (0712...)" data-pos-c="phone">
						<input type="email" placeholder="E-mail (for invoice)" data-pos-c="email">
					</div>
					<label class="pos__consent"><input type="checkbox" data-pos-c="consent"> Customer agrees to receive offers by WhatsApp / e-mail</label>
				</div>

				<ul class="pos__lines" data-pos-lines><li class="pos__empty">Tap a product to add it.</li></ul>

				<details class="pos__more" data-pos-more>
				<summary>Discount, delivery &amp; note <span data-pos-more-sum></span></summary>
				<div class="pos__adj">
					<label>Discount <span class="pos__disc"><input type="number" min="0" step="1" value="0" data-pos-disc><select data-pos-disctype><option value="amt">KSh</option><option value="pct">%</option></select></span></label>
					<label>Delivery fee <input type="number" min="0" step="1" value="0" data-pos-del></label>
					<input type="text" placeholder="Deliver to (optional)" data-pos-delto class="pos__wide">
					<textarea rows="2" placeholder="Note on invoice (optional)" data-pos-note class="pos__wide"></textarea>
				</div>
				</details>
				</div>

				<div class="pos__foot">

				<dl class="pos__totals">
					<div><dt>Subtotal</dt><dd data-pos-sub>KSh 0</dd></div>
					<div><dt>Discount</dt><dd data-pos-dsc>- KSh 0</dd></div>
					<div><dt>Delivery</dt><dd data-pos-dl>KSh 0</dd></div>
					<div class="is-total"><dt>Total</dt><dd data-pos-total>KSh 0</dd></div>
				</dl>

				<div class="pos__pay">
					<p class="pos__paylabel">How is the customer paying?</p>
					<div class="pos__methods" data-pos-methods>
						<?php $i = 0; foreach ( self::methods() as $k => $l ) : ?>
							<button type="button" data-m="<?php echo esc_attr( $k ); ?>" class="<?php echo 0 === $i++ ? 'is-on' : ''; ?>"><?php echo esc_html( $l ); ?></button>
						<?php endforeach; ?>
					</div>
					<div class="pos__payrow">
						<input type="text" placeholder="M-Pesa code / reference" data-pos-ref>
						<input type="number" min="0" step="1" placeholder="Cash received" data-pos-tender hidden>
					</div>
					<p class="pos__change" data-pos-change hidden></p>
					<button type="button" class="pos__charge" data-pos-charge disabled>Charge KSh 0</button>
					<button type="button" class="pos__invoice" data-pos-invoice disabled>Save as unpaid invoice (pay later)</button>
				</div>
				</div>
			</aside>
		</div>

		<div class="nd-modal pos-done" data-pos-done hidden>
			<div class="nd-modal__box pos-done__box" role="dialog" aria-modal="true" aria-label="Sale complete">
				<div class="pos-done__tick" aria-hidden="true"></div>
				<h2 data-pos-done-title>Sale complete</h2>
				<p class="pos-done__amt" data-pos-done-amt></p>
				<p class="pos-done__chg" data-pos-done-chg hidden></p>
				<div class="pos-done__acts">
					<a class="nd-btn nd-btn--primary" target="_blank" rel="noopener" data-pos-done-receipt>Print receipt</a>
					<a class="nd-btn nd-btn--ghost" target="_blank" rel="noopener" data-pos-done-invoice>Print A4 invoice</a>
					<a class="nd-btn nd-btn--wa" target="_blank" rel="noopener" data-pos-done-wa>Send on WhatsApp</a>
					<button type="button" class="nd-btn nd-btn--ghost" data-pos-done-email>E-mail</button>
				</div>
				<button type="button" class="nd-btn nd-btn--primary nd-btn--block pos-done__new" data-pos-new>New sale</button>
			</div>
		</div>

		<div class="nd-modal" data-pos-custom-modal hidden>
			<div class="nd-modal__box" role="dialog" aria-modal="true" aria-label="Custom item">
				<header><strong>Custom item or service</strong><button type="button" class="nd-modal__x" data-pos-custom-close aria-label="Close">&times;</button></header>
				<div class="pos-custom">
					<input type="text" placeholder="Description, e.g. Wig installation" data-pos-cname>
					<input type="number" min="0" step="1" placeholder="Price (KSh)" data-pos-cprice>
					<div class="pos-custom__quick">
						<button type="button" data-q="Wig installation">Installation</button>
						<button type="button" data-q="Wig revamp / wash">Revamp</button>
						<button type="button" data-q="Styling">Styling</button>
						<button type="button" data-q="Wig cap">Wig cap</button>
					</div>
				</div>
				<footer><button type="button" class="nd-btn nd-btn--primary" data-pos-cadd>Add to sale</button></footer>
			</div>
		</div>
		<?php
		NURAX_Desk::footer();
	}

	/* ------------------------------------------------------------------ */
	/* Sales & invoices                                                    */
	/* ------------------------------------------------------------------ */

	private static function range( $r ) {
		$today = NURAX_Desk::today();
		switch ( $r ) {
			case 'today':
				return array( $today, $today );
			case 'week':
				return array( gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) ), $today );
			case 'last30':
				return array( gmdate( 'Y-m-d', strtotime( $today . ' -29 days' ) ), $today );
			case 'lastmonth':
				$f = gmdate( 'Y-m-01', strtotime( substr( $today, 0, 7 ) . '-01 -1 month' ) );
				return array( $f, gmdate( 'Y-m-t', strtotime( $f ) ) );
			case 'year':
				return array( substr( $today, 0, 4 ) . '-01-01', $today );
			default:
				return array( substr( $today, 0, 7 ) . '-01', $today );
		}
	}

	/** Orders in a date range (WooCommerce, HPOS-safe). */
	public static function orders( $from, $to, $limit = -1, $paged = 1 ) {
		return wc_get_orders( array(
			'limit'        => $limit,
			'paged'        => $paged,
			'paginate'     => $limit > 0,
			'type'         => 'shop_order',
			'status'       => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
			'date_created' => strtotime( $from . ' 00:00:00' ) . '...' . strtotime( $to . ' 23:59:59' ),
			'orderby'      => 'date',
			'order'        => 'DESC',
		) );
	}

	private static function sales() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$r     = isset( $_GET['r'] ) ? sanitize_key( wp_unslash( $_GET['r'] ) ) : 'month';
		$ch    = isset( $_GET['ch'] ) ? sanitize_key( wp_unslash( $_GET['ch'] ) ) : 'all';
		$pay   = isset( $_GET['pay'] ) ? sanitize_key( wp_unslash( $_GET['pay'] ) ) : 'all';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		list( $from, $to ) = self::range( $r );
		$all   = self::orders( $from, $to, 3000 );
		$all   = is_object( $all ) ? $all->orders : (array) $all;
		if ( ! NURAX_Staff::can( 'view_all_sales' ) ) {
			$me  = get_current_user_id();
			$all = array_values( array_filter( $all, function ( $o ) use ( $me ) {
				return (int) $o->get_meta( '_nura_cashier' ) === $me;
			} ) );
		}
		$k     = array( 'total' => 0, 'count' => 0, 'paid' => 0, 'unpaid' => 0, 'unpaid_n' => 0, 'walk' => 0, 'online' => 0, 'wa' => 0 );
		$rows  = array();
		foreach ( $all as $o ) {
			$c = self::channel( $o );
			$p = self::is_paid( $o );
			$t = (float) $o->get_total();
			$k['count']++;
			$k['total'] += $t;
			if ( $p ) {
				$k['paid'] += $t;
			} else {
				$k['unpaid'] += $t;
				$k['unpaid_n']++;
			}
			if ( 'online' === $c ) {
				$k['online'] += $t;
			} elseif ( 'whatsapp' === $c ) {
				$k['wa'] += $t;
			} else {
				$k['walk'] += $t;
			}
			$okc = 'all' === $ch || ( 'shop' === $ch ? 'online' === $c : ( 'store' === $ch ? 'online' !== $c : $ch === $c ) );
			$okp = 'all' === $pay || ( 'pack' === $pay ? in_array( $o->get_status(), array( 'processing', 'on-hold' ), true ) : ( 'paid' === $pay ? $p : ! $p ) );
			if ( $okc && $okp ) {
				$rows[] = $o;
			}
		}
		$per   = 30;
		$total = count( $rows );
		$rows  = array_slice( $rows, ( $paged - 1 ) * $per, $per );

		$exp = NURAX_Desk::export_btns( 'sales', 'Sales report', '&from=' . $from . '&to=' . $to ) . NURAX_Desk::export_btns( 'sale_items', 'Items report', '&from=' . $from . '&to=' . $to );
		NURAX_Desk::header( NURAX_Desk::P_POS, 'Sales & invoices', self::subnav( 'sales' ) . $exp );
		wp_localize_script( 'nurax-desk', 'NURAX_POS', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nurax_pos' ), 'methods' => self::methods() ) );

		echo '<div class="nd-kpis">';
		NURAX_Desk::kpi( 'Sales', NURAX_Desk::money( $k['total'] ), $k['count'] . ' orders', 'ink' );
		NURAX_Desk::kpi( 'Paid', NURAX_Desk::money( $k['paid'] ), 'money in', 'gold' );
		NURAX_Desk::kpi( 'Unpaid invoices', NURAX_Desk::money( $k['unpaid'] ), $k['unpaid_n'] . ' invoices', $k['unpaid_n'] ? 'red' : '', NURAX_Desk::url( NURAX_Desk::P_POS, array( 'view' => 'sales', 'r' => $r, 'pay' => 'unpaid' ) ) );
		NURAX_Desk::kpi( 'Average sale', NURAX_Desk::money( $k['count'] ? $k['total'] / $k['count'] : 0 ), 'per order' );
		NURAX_Desk::kpi( 'Walk-in & phone', NURAX_Desk::money( $k['walk'] ), 'in the studio' );
		NURAX_Desk::kpi( 'Online shop', NURAX_Desk::money( $k['online'] ), 'website checkout' );
		NURAX_Desk::kpi( 'WhatsApp', NURAX_Desk::money( $k['wa'] ), 'from WhatsApp orders' );
		global $wpdb;
		$lmm = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) s, COUNT(*) c FROM ' . NURAX_Desk::t_pay() . ' WHERE voided = 0 AND paid_at >= %s AND paid_at <= %s', $from . ' 00:00:00', $to . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		NURAX_Desk::kpi( 'Lipa Mdogo collected', NURAX_Desk::money( $lmm ? $lmm->s : 0 ), ( $lmm ? (int) $lmm->c : 0 ) . ' instalments (not in sales above)', '', NURAX_Desk::url( NURAX_Desk::P_PLANS ) );
		echo '</div>';

		$ranges = array( 'today' => 'Today', 'week' => '7 days', 'month' => 'This month', 'lastmonth' => 'Last month', 'last30' => '30 days', 'year' => 'This year' );
		$chs    = array( 'all' => 'All channels', 'store' => 'Studio (walk-in, phone, WhatsApp)', 'shop' => 'Online shop', 'walk-in' => 'Walk-in', 'whatsapp' => 'WhatsApp' );
		echo '<div class="nd-toolbar"><nav class="nd-pills">';
		foreach ( $ranges as $kk => $l ) {
			echo '<a class="nd-pill' . ( $kk === $r ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_POS, array( 'view' => 'sales', 'r' => $kk, 'ch' => $ch, 'pay' => $pay ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav><form method="get" class="nd-search"><input type="hidden" name="page" value="' . esc_attr( NURAX_Desk::P_POS ) . '"><input type="hidden" name="view" value="sales"><input type="hidden" name="r" value="' . esc_attr( $r ) . '"><select name="ch">';
		foreach ( $chs as $kk => $l ) {
			echo '<option value="' . esc_attr( $kk ) . '"' . selected( $ch, $kk, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select><select name="pay"><option value="all">Paid and unpaid</option><option value="paid"' . selected( $pay, 'paid', false ) . '>Paid</option><option value="unpaid"' . selected( $pay, 'unpaid', false ) . '>Unpaid</option><option value="pack"' . selected( $pay, 'pack', false ) . '>To pack / deliver</option></select><input type="search" placeholder="Filter this page" data-nd-local><button class="nd-btn nd-btn--ghost" type="submit">Apply</button></form></div>';

		if ( ! $rows ) {
			echo '<div class="nd-empty"><strong>No sales in this period.</strong><p>Ring up a walk-in sale under "New sale". Online orders appear here automatically.</p>' . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_POS ), 'New sale', 'nd-btn--primary' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			NURAX_Desk::footer();
			return;
		}
		echo '<div class="nd-table-wrap"><table class="nd-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Items</th><th>Channel</th><th>Total</th><th>Payment</th><th class="nd-col-act"></th></tr></thead><tbody>';
		foreach ( $rows as $o ) {
			$c     = self::channel( $o );
			$p     = self::is_paid( $o );
			$name  = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
			$items = array();
			foreach ( $o->get_items() as $it ) {
				$items[] = ( $it->get_quantity() > 1 ? $it->get_quantity() . ' x ' : '' ) . $it->get_name();
			}
			$phone = NURAX_Desk::phone( $o->get_billing_phone() );
			echo '<tr data-nd-search="' . esc_attr( strtolower( $o->get_order_number() . ' ' . $name . ' ' . $phone . ' ' . implode( ' ', $items ) ) ) . '">';
			echo '<td><a class="nd-ref" target="_blank" rel="noopener" href="' . esc_url( self::doc_url( $o, 'invoice', false ) ) . '">#' . esc_html( $o->get_order_number() ) . '</a><small>' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j M Y, H:i' ) : '' ) . '</small></td>';
			echo '<td><strong>' . esc_html( $name ? $name : 'Walk-in customer' ) . '</strong><small>' . esc_html( NURAX_Desk::phone_label( $phone ) ) . '</small></td>';
			echo '<td><span class="nd-clip">' . esc_html( implode( ', ', $items ) ) . '</span></td>';
			echo '<td>' . NURAX_Desk::badge( 'online' === $c ? 'released' : ( 'whatsapp' === $c ? 'confirmed' : 'muted' ), self::channels()[ $c ] ?? ucfirst( $c ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td><strong>' . esc_html( NURAX_Desk::money( $o->get_total() ) ) . '</strong></td>';
			echo '<td>' . ( $p ? NURAX_Desk::badge( 'confirmed', 'Paid' ) : NURAX_Desk::badge( 'overdue', 'Unpaid' ) ) . '<small>' . esc_html( $o->get_payment_method_title() ) . '</small></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="nd-col-act">';
			echo '<a class="nd-ico-btn" target="_blank" rel="noopener" href="' . esc_url( self::doc_url( $o, 'receipt' ) ) . '">Receipt</a>';
			echo '<a class="nd-ico-btn" target="_blank" rel="noopener" href="' . esc_url( self::doc_url( $o, 'invoice' ) ) . '">Invoice</a>';
			if ( $phone ) {
				echo '<a class="nd-ico-btn nd-ico-btn--wa" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $phone, self::wa_text( $o ) ) ) . '">WhatsApp</a>';
			}
			echo '<button type="button" class="nd-ico-btn" data-pos-email="' . (int) $o->get_id() . '" data-email="' . esc_attr( is_email( $o->get_billing_email() ) ? $o->get_billing_email() : '' ) . '">E-mail</button>';
			if ( ! $p ) {
				echo '<button type="button" class="nd-ico-btn nd-ico-btn--ok" data-pos-paid="' . (int) $o->get_id() . '">Mark paid</button>';
			} elseif ( in_array( $o->get_status(), array( 'processing', 'on-hold' ), true ) ) {
				echo '<button type="button" class="nd-ico-btn nd-ico-btn--ok" data-pos-complete="' . (int) $o->get_id() . '">Fulfilled</button>';
			}
			echo '<a class="nd-ico-btn" href="' . esc_url( $o->get_edit_order_url() ) . '">Open</a>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		NURAX_Desk_Plans::pager( $total, $per, $paged, array( 'view' => 'sales', 'r' => $r, 'ch' => $ch, 'pay' => $pay ), NURAX_Desk::P_POS );
		NURAX_Desk::footer();
	}

	public static function export_rows( $type ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$from = isset( $_GET['from'] ) ? NURAX_Desk::valid_date( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$to   = isset( $_GET['to'] ) ? NURAX_Desk::valid_date( wp_unslash( $_GET['to'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable
		if ( ! $from || ! $to ) {
			list( $from, $to ) = self::range( 'year' );
		}
		$orders = self::orders( $from, $to, 5000 );
		$orders = is_object( $orders ) ? $orders->orders : (array) $orders;
		if ( 'sale_items' === $type ) {
			$rows = array( array( 'Invoice', 'Date', 'Channel', 'Customer', 'Phone', 'Product', 'SKU', 'Qty', 'Line total', 'Paid' ) );
			foreach ( $orders as $o ) {
				foreach ( $o->get_items() as $it ) {
					$p      = $it->get_product();
					$rows[] = array( $o->get_order_number(), $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '', self::channel( $o ), trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), $o->get_billing_phone(), $it->get_name(), $p ? $p->get_sku() : '', $it->get_quantity(), $it->get_total(), self::is_paid( $o ) ? 'Yes' : 'No' );
				}
			}
			return $rows;
		}
		$rows = array( array( 'Invoice', 'Date', 'Status', 'Channel', 'Customer', 'Phone', 'E-mail', 'Items', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Paid', 'Method', 'Reference', 'Cashier' ) );
		foreach ( $orders as $o ) {
			$disc = 0;
			foreach ( $o->get_fees() as $f ) {
				if ( (float) $f->get_total() < 0 ) {
					$disc += (float) $f->get_total();
				}
			}
			$items = array();
			foreach ( $o->get_items() as $it ) {
				$items[] = $it->get_quantity() . ' x ' . $it->get_name();
			}
			$cash   = $o->get_meta( '_nura_cashier' ) ? get_userdata( (int) $o->get_meta( '_nura_cashier' ) ) : null;
			$rows[] = array( $o->get_order_number(), $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '', $o->get_status(), self::channel( $o ), trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), $o->get_billing_phone(), $o->get_billing_email(), implode( '; ', $items ), $o->get_subtotal(), $disc, $o->get_shipping_total(), $o->get_total(), self::is_paid( $o ) ? 'Yes' : 'No', $o->get_payment_method_title(), $o->get_meta( '_nura_pay_ref' ) ? $o->get_meta( '_nura_pay_ref' ) : $o->get_transaction_id(), $cash ? $cash->display_name : '' );
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ */
	/* Documents                                                           */
	/* ------------------------------------------------------------------ */

	public static function h_doc() {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_doc_' . $id );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			wp_die( 'Order not found.' );
		}
		$type = isset( $_GET['type'] ) && 'receipt' === $_GET['type'] ? 'receipt' : 'invoice';
		nocache_headers();
		echo self::render( $order, $type, ! empty( $_GET['print'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		exit;
	}

	/** Customer-facing link: /?nura_doc=invoice&o=ID&k=ORDER_KEY */
	public static function public_doc() {
		if ( empty( $_GET['nura_doc'] ) || empty( $_GET['o'] ) || empty( $_GET['k'] ) || ! function_exists( 'wc_get_order' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$order = wc_get_order( absint( $_GET['o'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key   = sanitize_text_field( wp_unslash( $_GET['k'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			return;
		}
		$type = 'receipt' === $_GET['nura_doc'] ? 'receipt' : 'invoice'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo self::render( $order, $type, false, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Logo for invoices: Desk setting, then the theme's custom logo, then the site icon. */
	public static function logo_url() {
		$s = NURAX_Desk::settings();
		if ( ! empty( $s['invoice_logo'] ) ) {
			return (string) $s['invoice_logo'];
		}
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$u = wp_get_attachment_image_url( $id, 'full' );
			if ( $u ) {
				return $u;
			}
		}
		$icon = get_site_icon_url( 256 );
		return $icon ? $icon : '';
	}

	/** Full HTML page for an invoice or receipt. */
	public static function render( $order, $type, $print, $public = false ) {
		$b     = self::biz();
		$logo  = self::logo_url();
		$paid  = self::is_paid( $order );
		$num   = $order->get_order_number();
		$dobj  = $order->get_date_created();
		$date  = $dobj ? $dobj->date_i18n( 'j F Y' ) : '';
		$time  = $dobj ? $dobj->date_i18n( 'H:i' ) : '';
		$pdate = $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'j F Y' ) : $date;
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$phone = NURAX_Desk::phone_label( NURAX_Desk::phone( $order->get_billing_phone() ) );
		$addr  = trim( implode( ', ', array_filter( array( $order->get_billing_address_1(), $order->get_billing_city() ) ) ) );
		$ref   = $order->get_meta( '_nura_pay_ref' ) ? $order->get_meta( '_nura_pay_ref' ) : $order->get_transaction_id();
		$cash  = $order->get_meta( '_nura_cashier' ) ? get_userdata( (int) $order->get_meta( '_nura_cashier' ) ) : null;
		$title = 'receipt' === $type ? 'Receipt' : ( $paid ? 'Tax Invoice' : 'Invoice' );
		$chan  = self::channels()[ self::channel( $order ) ] ?? 'Online shop';
		$meth  = $order->get_payment_method_title();
		$mpesa = trim( (string) NURAX_Desk::settings()['mpesa_details'] );

		/* Line items. */
		$rows = '';
		$i    = 0;
		foreach ( $order->get_items() as $it ) {
			$meta = array();
			foreach ( $it->get_formatted_meta_data( '_', true ) as $m ) {
				$meta[] = wp_strip_all_tags( $m->display_key . ': ' . $m->display_value );
			}
			$sku = '';
			if ( is_callable( array( $it, 'get_product' ) ) && $it->get_product() && $it->get_product()->get_sku() ) {
				$sku = 'SKU ' . $it->get_product()->get_sku();
			}
			$sub   = array_filter( array( implode( ' · ', $meta ), $sku ) );
			$unit  = $it->get_quantity() ? (float) $it->get_total() / $it->get_quantity() : 0;
			$rows .= '<tr><td class="n">' . sprintf( '%02d', ++$i ) . '</td><td><strong>' . esc_html( $it->get_name() ) . '</strong>' . ( $sub ? '<small>' . esc_html( implode( ' · ', $sub ) ) . '</small>' : '' ) . '</td><td class="c">' . (int) $it->get_quantity() . '</td><td class="r">' . esc_html( NURAX_Desk::money( $unit ) ) . '</td><td class="r b">' . esc_html( NURAX_Desk::money( $it->get_total() ) ) . '</td></tr>';
		}
		$disc = 0;
		foreach ( $order->get_fees() as $f ) {
			if ( (float) $f->get_total() < 0 ) {
				$disc += (float) $f->get_total();
			} else {
				$rows .= '<tr><td class="n">' . sprintf( '%02d', ++$i ) . '</td><td><strong>' . esc_html( $f->get_name() ) . '</strong></td><td class="c">1</td><td class="r">' . esc_html( NURAX_Desk::money( $f->get_total() ) ) . '</td><td class="r b">' . esc_html( NURAX_Desk::money( $f->get_total() ) ) . '</td></tr>';
			}
		}
		$sub = (float) $order->get_subtotal();
		foreach ( $order->get_fees() as $f ) {
			if ( (float) $f->get_total() > 0 ) {
				$sub += (float) $f->get_total();
			}
		}

		/* Totals. */
		$extra = '<div class="ln"><span>Subtotal</span><span>' . esc_html( NURAX_Desk::money( $sub ) ) . '</span></div>';
		if ( $disc < 0 ) {
			$extra .= '<div class="ln"><span>Discount</span><span>- ' . esc_html( NURAX_Desk::money( abs( $disc ) ) ) . '</span></div>';
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$extra .= '<div class="ln"><span>Delivery' . ( $order->get_shipping_method() ? ' <em>' . esc_html( $order->get_shipping_method() ) . '</em>' : '' ) . '</span><span>' . esc_html( NURAX_Desk::money( $order->get_shipping_total() ) ) . '</span></div>';
		}
		if ( (float) $order->get_total_tax() > 0 ) {
			$extra .= '<div class="ln"><span>VAT</span><span>' . esc_html( NURAX_Desk::money( $order->get_total_tax() ) ) . '</span></div>';
		}
		$extra .= '<div class="ln tot"><span>Total</span><span>' . esc_html( NURAX_Desk::money( $order->get_total() ) ) . '</span></div>';
		if ( $paid ) {
			$extra .= '<div class="ln"><span>Paid' . ( $meth ? ' - ' . esc_html( $meth ) : '' ) . '</span><span>' . esc_html( NURAX_Desk::money( $order->get_total() ) ) . '</span></div>';
			if ( (float) $order->get_meta( '_nura_tendered' ) > 0 ) {
				$extra .= '<div class="ln"><span>Cash received</span><span>' . esc_html( NURAX_Desk::money( $order->get_meta( '_nura_tendered' ) ) ) . '</span></div><div class="ln"><span>Change</span><span>' . esc_html( NURAX_Desk::money( $order->get_meta( '_nura_change' ) ) ) . '</span></div>';
			}
		}
		$due_box = $paid
			? '<div class="due ok"><span>Balance due</span><strong>KSh 0</strong></div>'
			: '<div class="due"><span>Amount due</span><strong>' . esc_html( NURAX_Desk::money( $order->get_total() ) ) . '</strong></div>';

		$note    = $order->get_customer_note() ? esc_html( $order->get_customer_note() ) : '';
		$footer  = '' !== trim( $b['note'] ) ? esc_html( $b['note'] ) : 'Thank you for choosing ' . esc_html( $b['name'] ) . '. Unworn wigs in their original condition can be returned within 7 days of delivery with this document.';
		$qr      = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&color=14-14-14&data=' . rawurlencode( self::public_url( $order, $type ) );
		$brand   = $logo
			? '<img class="logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $b['name'] ) . '">'
			: '<div class="word">' . esc_html( strtoupper( $b['name'] ) ) . '<small>Wear your crown</small></div>';

		ob_start();
		?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title . ' #' . $num . ' - ' . $b['name'] ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ink:#0E0E0E;--gold:#C9A24B;--gold2:#E4C983;--cream:#FAF6EF;--line:#ECE4D6;--mut:#6f675d}
*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;background:#ebe5db;font:12.5px/1.55 "Montserrat",-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1b1b1b}
.bar{position:sticky;top:0;z-index:5;display:flex;gap:8px;justify-content:center;padding:10px;background:var(--ink)}
.bar button,.bar a{padding:9px 18px;border:0;border-radius:999px;background:var(--gold2);color:var(--ink);font:700 12px/1 "Montserrat",Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;cursor:pointer}
.bar a.alt{background:#2a2622;color:#fff}
.doc{position:relative;margin:28px auto;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.12)}
/* ---------- A4 ---------- */
.a4{width:210mm;min-height:297mm;display:flex;flex-direction:column;overflow:hidden}
.a4 .top{height:6px;background:linear-gradient(90deg,var(--ink) 0 70%,var(--gold) 70% 100%)}
.a4 .pad{padding:14mm 15mm 0;flex:1;display:flex;flex-direction:column}
.hd{display:flex;justify-content:space-between;align-items:flex-start;gap:24px}
.logo{display:block;max-height:78px;max-width:230px;width:auto;height:auto;object-fit:contain}
.word{font:700 30px/1 "Playfair Display",Georgia,serif;letter-spacing:.28em;color:var(--ink)}
.word small{display:block;margin-top:8px;font:600 8.5px/1 "Montserrat",Arial,sans-serif;letter-spacing:.34em;color:var(--gold)}
.doc-t{text-align:right}
.doc-t h1{margin:0;font:600 34px/1 "Playfair Display",Georgia,serif;letter-spacing:.04em;color:var(--ink)}
.doc-t .no{margin-top:8px;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut)}
.doc-t .no b{color:var(--ink)}
.pill{display:inline-block;margin-top:10px;padding:5px 12px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase}
.pill.paid{background:#e7f4ec;color:#1f7a45}.pill.due{background:#fbeae7;color:#a8321f}
.rule{display:flex;align-items:center;gap:12px;margin:22px 0 20px}
.rule::before,.rule::after{content:"";flex:1;height:1px;background:var(--line)}
.rule span{width:7px;height:7px;transform:rotate(45deg);background:var(--gold)}
.parties{display:grid;grid-template-columns:1.1fr 1.1fr 1fr;gap:14px}
.cell{padding:14px 16px;border:1px solid var(--line);border-radius:10px;background:#fff}
.cell.dark{background:var(--ink);border-color:var(--ink);color:#e9e2d6}
.cell h3{margin:0 0 8px;font-size:9px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:var(--gold)}
.cell strong{display:block;font-size:13px;color:var(--ink);margin-bottom:2px}
.cell.dark strong{color:#fff}
.cell p{margin:0;color:var(--mut);font-size:11.5px;line-height:1.6}.cell.dark p{color:#cfc6b8}
.kv{display:grid;grid-template-columns:auto 1fr;gap:3px 12px;font-size:11.5px}
.kv dt{color:#a99d8a}.kv dd{margin:0;text-align:right;color:#fff;font-weight:600}
table{width:100%;border-collapse:separate;border-spacing:0;margin-top:22px}
thead th{padding:11px 12px;background:var(--ink);color:var(--gold2);text-align:left;font-size:9px;font-weight:700;letter-spacing:.2em;text-transform:uppercase}
thead th:first-child{border-radius:8px 0 0 8px}thead th:last-child{border-radius:0 8px 8px 0}
tbody td{padding:13px 12px;border-bottom:1px solid var(--line);vertical-align:top}
tbody tr:nth-child(even) td{background:#fdfbf7}
td strong{font-weight:600;color:var(--ink)}
td small{display:block;margin-top:3px;color:#8a8174;font-size:10.5px}
td.n{width:34px;color:#b4a58a;font-weight:600}
.c{text-align:center}.r{text-align:right;white-space:nowrap}.b{font-weight:700;color:var(--ink)}
.lower{display:grid;grid-template-columns:1fr 290px;gap:26px;margin-top:20px;align-items:start}
.info{display:grid;gap:12px}
.box{padding:14px 16px;border-radius:10px;background:var(--cream);border:1px solid var(--line)}
.box h4{margin:0 0 6px;font-size:9px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#8a6a1f}
.box p{margin:0;color:#4a443c;font-size:11.5px}
.box.pay{border-left:3px solid var(--gold)}
.totals .ln{display:flex;justify-content:space-between;gap:10px;padding:6px 2px;color:#4a443c}
.totals .ln em{font-style:normal;color:#9a9083;font-size:10.5px}
.totals .ln span:last-child{color:var(--ink);font-weight:600;white-space:nowrap}
.totals .tot{margin-top:6px;padding-top:11px;border-top:1.5px solid var(--ink);font-size:14px}
.totals .tot span{font-weight:700;color:var(--ink)}
.due{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding:14px 16px;border-radius:10px;background:var(--ink);color:var(--gold2)}
.due span{font-size:9.5px;font-weight:700;letter-spacing:.22em;text-transform:uppercase}
.due strong{font:700 20px/1 "Playfair Display",Georgia,serif;color:#fff}
.due.ok{background:#e7f4ec;color:#1f7a45}.due.ok strong{color:#1f7a45}
.stamp{position:absolute;right:22mm;top:118mm;padding:8px 18px;border:3px double #1f7a45;border-radius:8px;color:#1f7a45;font:700 22px/1 "Playfair Display",Georgia,serif;letter-spacing:.2em;transform:rotate(-12deg);opacity:.16;pointer-events:none}
.sign{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-top:auto;padding:24px 0 16px}
.qr{display:flex;align-items:center;gap:12px;color:var(--mut);font-size:10.5px;line-height:1.5}
.qr img{width:74px;height:74px;padding:5px;border:1px solid var(--line);border-radius:8px;background:#fff}
.thanks{text-align:right}
.thanks b{display:block;font:italic 600 20px/1.2 "Playfair Display",Georgia,serif;color:var(--ink)}
.thanks span{font-size:10.5px;color:var(--mut)}
.ft{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 15mm;background:var(--ink);color:#cfc6b8;font-size:10px;letter-spacing:.04em}
.ft b{color:var(--gold2);font-weight:600}
.terms{margin:0 0 10px;color:#8a8174;font-size:10px;line-height:1.5}
/* ---------- 80 mm receipt ---------- */
.rc{width:80mm;padding:7mm 5mm;font-size:11.5px;color:#111}
.rc .logo{margin:0 auto;max-height:58px;max-width:52mm}
.rc .word{text-align:center;font-size:22px}.rc .word small{text-align:center}
.rc .ctr{text-align:center;margin:6px 0}
.rc .tt{margin:8px 0 2px;text-align:center;font:700 13px/1.2 "Montserrat",Arial,sans-serif;letter-spacing:.24em;text-transform:uppercase}
.rc hr{border:0;border-top:1px dashed #9a9a9a;margin:9px 0}
.rc table{margin:0}.rc thead th{background:none;color:#111;padding:4px 0;border-bottom:1px solid #111;font-size:9px;border-radius:0!important}
.rc tbody td{padding:5px 0;border:0;background:none!important;font-size:11.5px}
.rc .totals .ln{padding:3px 0;color:#111}.rc .totals .ln span:last-child{color:#111}
.rc .totals .tot{font-size:14px;border-top:1px solid #111}
.rc .due{margin-top:8px;padding:8px 10px;border-radius:6px}
.rc .due strong{font-size:16px}
.rc .qrc{display:block;margin:10px auto 2px;width:92px;height:92px}
.rc .small{font-size:10px;color:#444}
@media print{
  body{background:#fff}.bar{display:none}.doc{margin:0;box-shadow:none}
  .a4{width:auto;min-height:calc(297mm - 0.1mm)}
  @page{size:A4;margin:0}
}
<?php if ( 'receipt' === $type ) : ?>@media print{.rc{width:72mm;padding:0 1mm}@page{size:80mm auto;margin:3mm}.rc .logo{filter:grayscale(1) contrast(1.4)}}<?php endif; ?>
@media (max-width:820px){.a4{width:auto;min-height:0;margin:0}.a4 .pad{padding:20px}.hd{flex-direction:column}.doc-t{text-align:left}.parties,.lower{grid-template-columns:1fr}.ft{padding:12px 20px}.stamp{display:none}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Print / Save as PDF</button><?php if ( ! $public ) : ?><a class="alt" href="<?php echo esc_url( self::doc_url( $order, 'receipt' === $type ? 'invoice' : 'receipt', false ) ); ?>"><?php echo 'receipt' === $type ? 'A4 invoice' : '80 mm receipt'; ?></a><?php endif; ?></div>
<?php if ( 'receipt' === $type ) : ?>
<div class="doc rc">
	<?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p class="ctr small"><?php echo esc_html( $b['addr'] ); ?><br><?php echo esc_html( $b['phone'] ); ?> · <?php echo esc_html( $b['web'] ); ?><?php echo $b['pin'] ? '<br>KRA PIN: ' . esc_html( $b['pin'] ) : ''; ?></p>
	<p class="tt"><?php echo esc_html( $paid ? 'Receipt' : 'Invoice' ); ?></p>
	<p class="ctr small">#<?php echo esc_html( $num ); ?> · <?php echo esc_html( $date . ' ' . $time ); ?><?php echo $cash ? '<br>Served by ' . esc_html( $cash->display_name ) : ''; ?><?php echo $name ? '<br>Customer: ' . esc_html( $name ) : ''; ?></p>
	<hr>
	<table><thead><tr><th>Item</th><th class="c">Qty</th><th class="r">Amount</th></tr></thead><tbody>
	<?php foreach ( $order->get_items() as $it ) : ?><tr><td><?php echo esc_html( $it->get_name() ); ?></td><td class="c"><?php echo (int) $it->get_quantity(); ?></td><td class="r"><?php echo esc_html( NURAX_Desk::money( $it->get_total() ) ); ?></td></tr><?php endforeach; ?>
	<?php foreach ( $order->get_fees() as $f ) : if ( (float) $f->get_total() < 0 ) { continue; } ?><tr><td><?php echo esc_html( $f->get_name() ); ?></td><td class="c">1</td><td class="r"><?php echo esc_html( NURAX_Desk::money( $f->get_total() ) ); ?></td></tr><?php endforeach; ?>
	</tbody></table><hr>
	<div class="totals"><?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php echo $due_box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( ! $paid && $mpesa ) : ?><p class="ctr small"><strong>Pay via M-Pesa:</strong> <?php echo esc_html( $mpesa ); ?><br>Reference: <?php echo esc_html( $num ); ?></p><?php endif; ?>
	<hr>
	<p class="ctr small"><?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<img class="qrc" alt="" src="<?php echo esc_url( $qr ); ?>">
	<p class="ctr small">Scan to view online</p>
</div>
<?php else : ?>
<div class="doc a4">
	<div class="top"></div>
	<?php if ( $paid ) : ?><div class="stamp">PAID</div><?php endif; ?>
	<div class="pad">
		<div class="hd">
			<div><?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="doc-t"><h1><?php echo esc_html( $title ); ?></h1><div class="no">No. <b><?php echo esc_html( $num ); ?></b></div><span class="pill <?php echo $paid ? 'paid' : 'due'; ?>"><?php echo $paid ? 'Paid' : 'Payment due'; ?></span></div>
		</div>
		<div class="rule"><span></span></div>
		<div class="parties">
			<div class="cell"><h3>From</h3><strong><?php echo esc_html( $b['name'] ); ?></strong><p><?php echo esc_html( $b['addr'] ); ?><br><?php echo esc_html( $b['phone'] ); ?><br><?php echo esc_html( $b['email'] ); ?><?php echo $b['pin'] ? '<br>KRA PIN: ' . esc_html( $b['pin'] ) : ''; ?></p></div>
			<div class="cell"><h3>Billed to</h3><strong><?php echo esc_html( $name ? $name : 'Walk-in customer' ); ?></strong><p><?php echo $phone ? esc_html( $phone ) . '<br>' : ''; ?><?php echo $order->get_billing_email() ? esc_html( $order->get_billing_email() ) . '<br>' : ''; ?><?php echo $addr ? esc_html( $addr ) : ''; ?></p></div>
			<div class="cell dark"><h3>Details</h3><dl class="kv">
				<dt>Issued</dt><dd><?php echo esc_html( $date ); ?></dd>
				<dt><?php echo $paid ? 'Paid on' : 'Due'; ?></dt><dd><?php echo esc_html( $paid ? $pdate : 'On receipt' ); ?></dd>
				<dt>Channel</dt><dd><?php echo esc_html( $chan ); ?></dd>
				<?php if ( $meth ) : ?><dt>Payment</dt><dd><?php echo esc_html( $meth ); ?></dd><?php endif; ?>
				<?php if ( $paid && $ref ) : ?><dt>Ref.</dt><dd><?php echo esc_html( $ref ); ?></dd><?php endif; ?>
				<?php if ( $cash ) : ?><dt>Served by</dt><dd><?php echo esc_html( $cash->display_name ); ?></dd><?php endif; ?>
			</dl></div>
		</div>
		<table><thead><tr><th>#</th><th>Description</th><th class="c">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead><tbody><?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></tbody></table>
		<div class="lower">
			<div class="info">
				<?php if ( ! $paid ) : ?><div class="box pay"><h4>How to pay</h4><p><?php echo $mpesa ? 'M-Pesa: <strong>' . esc_html( $mpesa ) . '</strong><br>' : ''; ?>Use invoice number <strong><?php echo esc_html( $num ); ?></strong> as your reference. Card and bank transfer also accepted - ask us on WhatsApp <?php echo esc_html( $b['phone'] ); ?>.</p></div><?php endif; ?>
				<?php if ( $note ) : ?><div class="box"><h4>Note</h4><p><?php echo $note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p></div><?php endif; ?>
				<div class="box"><h4>Terms</h4><p><?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> All prices in Kenya Shillings (KES).</p></div>
			</div>
			<div class="totals"><?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $due_box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<div class="sign">
			<div class="qr"><img alt="" src="<?php echo esc_url( $qr ); ?>"><div><strong>Scan to view or download</strong><br>this <?php echo esc_html( strtolower( $title ) ); ?> online.</div></div>
			<div class="thanks"><b>Thank you for wearing NURA.</b><span>East Africa's house of radiant confidence</span></div>
		</div>
	</div>
	<div class="ft"><span><b><?php echo esc_html( $b['name'] ); ?></b> · <?php echo esc_html( $b['addr'] ); ?></span><span><?php echo esc_html( $b['phone'] ); ?> · <?php echo esc_html( $b['email'] ); ?> · <?php echo esc_html( $b['web'] ); ?></span></div>
</div>
<?php endif; ?>
<?php if ( $print ) : ?><script>window.addEventListener('load',function(){var go=function(){setTimeout(function(){window.print();},300);};if(document.fonts&&document.fonts.ready){document.fonts.ready.then(go);}else{go();}});</script><?php endif; ?>
</body></html>
		<?php
		return (string) ob_get_clean();
	}
}
