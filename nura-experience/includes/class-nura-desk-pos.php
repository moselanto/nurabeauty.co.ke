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
		return array( 'walk-in' => 'Walk-in', 'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'instagram' => 'Instagram / TikTok', 'online' => 'Online shop' );
	}

	public static function channel( $order ) {
		$c = (string) $order->get_meta( '_nura_channel' );
		if ( '' !== $c ) {
			return $c;
		}
		return 'nura_pos' === $order->get_created_via() ? 'walk-in' : 'online';
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

	public static function wa_text( $order ) {
		$b    = self::biz();
		$paid = self::is_paid( $order );
		$l    = array( '*' . $b['name'] . ' - ' . ( $paid ? 'Receipt' : 'Invoice' ) . ' #' . $order->get_order_number() . '*', '' );
		foreach ( $order->get_items() as $it ) {
			$l[] = $it->get_name() . ' x ' . $it->get_quantity() . ' - ' . NURAX_Desk::money( $it->get_total() );
		}
		foreach ( $order->get_fees() as $f ) {
			$l[] = $f->get_name() . ' - ' . NURAX_Desk::money( $f->get_total() );
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$l[] = 'Delivery - ' . NURAX_Desk::money( $order->get_shipping_total() );
		}
		$l[] = '*Total: ' . NURAX_Desk::money( $order->get_total() ) . '*';
		$l[] = $paid ? 'Paid by ' . $order->get_payment_method_title() . ( $order->get_meta( '_nura_pay_ref' ) ? ' (' . $order->get_meta( '_nura_pay_ref' ) . ')' : '' ) . '. Thank you!' : 'Amount due: ' . NURAX_Desk::money( $order->get_total() ) . ( trim( NURAX_Desk::settings()['mpesa_details'] ) ? "\nPay via M-Pesa: " . NURAX_Desk::settings()['mpesa_details'] : '' );
		$l[] = '';
		$l[] = 'View / download: ' . self::public_url( $order, $paid ? 'receipt' : 'invoice' );
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
			$html = self::render( $order, 'invoice', false );
			$ok   = wp_mail( $to, self::biz()['name'] . ' - ' . ucfirst( $type ) . ' #' . $order->get_order_number(), $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
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
		wp_send_json_error( array( 'message' => 'Unknown action.' ), 400 );
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

				<div class="pos__adj">
					<label>Discount <span class="pos__disc"><input type="number" min="0" step="1" value="0" data-pos-disc><select data-pos-disctype><option value="amt">KSh</option><option value="pct">%</option></select></span></label>
					<label>Delivery fee <input type="number" min="0" step="1" value="0" data-pos-del></label>
					<input type="text" placeholder="Deliver to (optional)" data-pos-delto class="pos__wide">
					<textarea rows="2" placeholder="Note on invoice (optional)" data-pos-note class="pos__wide"></textarea>
				</div>

				<dl class="pos__totals">
					<div><dt>Subtotal</dt><dd data-pos-sub>KSh 0</dd></div>
					<div><dt>Discount</dt><dd data-pos-dsc>- KSh 0</dd></div>
					<div><dt>Delivery</dt><dd data-pos-dl>KSh 0</dd></div>
					<div class="is-total"><dt>Total</dt><dd data-pos-total>KSh 0</dd></div>
				</dl>

				<div class="pos__pay">
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
					<button type="button" class="pos__invoice" data-pos-invoice disabled>Save as unpaid invoice</button>
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
			$okp = 'all' === $pay || ( 'paid' === $pay ? $p : ! $p );
			if ( $okc && $okp ) {
				$rows[] = $o;
			}
		}
		$per   = 30;
		$total = count( $rows );
		$rows  = array_slice( $rows, ( $paged - 1 ) * $per, $per );

		$exp = '<a class="nd-btn nd-btn--ghost" href="' . esc_url( NURAX_Desk::export_url( 'sales' ) . '&from=' . $from . '&to=' . $to ) . '">Export sales CSV</a><a class="nd-btn nd-btn--ghost" href="' . esc_url( NURAX_Desk::export_url( 'sale_items' ) . '&from=' . $from . '&to=' . $to ) . '">Export items CSV</a>';
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
		NURAX_Desk::kpi( 'Period', NURAX_Desk::date_label( $from, 'j M' ) . ' - ' . NURAX_Desk::date_label( $to, 'j M' ), NURAX_Desk::date_label( $to, 'Y' ) );
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
		echo '</select><select name="pay"><option value="all">Paid and unpaid</option><option value="paid"' . selected( $pay, 'paid', false ) . '>Paid</option><option value="unpaid"' . selected( $pay, 'unpaid', false ) . '>Unpaid</option></select><input type="search" placeholder="Filter this page" data-nd-local><button class="nd-btn nd-btn--ghost" type="submit">Apply</button></form></div>';

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
			if ( is_email( $o->get_billing_email() ) ) {
				echo '<button type="button" class="nd-ico-btn" data-pos-email="' . (int) $o->get_id() . '">E-mail</button>';
			}
			if ( ! $p ) {
				echo '<button type="button" class="nd-ico-btn nd-ico-btn--ok" data-pos-paid="' . (int) $o->get_id() . '">Mark paid</button>';
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

	/** Full HTML page for an invoice or receipt. */
	public static function render( $order, $type, $print, $public = false ) {
		$b     = self::biz();
		$paid  = self::is_paid( $order );
		$num   = $order->get_order_number();
		$date  = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'j F Y, H:i' ) : '';
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$phone = NURAX_Desk::phone_label( NURAX_Desk::phone( $order->get_billing_phone() ) );
		$addr  = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_city() );
		$ref   = $order->get_meta( '_nura_pay_ref' ) ? $order->get_meta( '_nura_pay_ref' ) : $order->get_transaction_id();
		$cash  = $order->get_meta( '_nura_cashier' ) ? get_userdata( (int) $order->get_meta( '_nura_cashier' ) ) : null;
		$title = 'receipt' === $type ? 'Receipt' : ( $paid ? 'Tax invoice / receipt' : 'Invoice' );
		$rows  = '';
		foreach ( $order->get_items() as $it ) {
			$meta = array();
			foreach ( $it->get_formatted_meta_data( '_', true ) as $m ) {
				$meta[] = wp_strip_all_tags( $m->display_key . ': ' . $m->display_value );
			}
			$unit  = $it->get_quantity() ? (float) $it->get_total() / $it->get_quantity() : 0;
			$rows .= '<tr><td><strong>' . esc_html( $it->get_name() ) . '</strong>' . ( $meta ? '<small>' . esc_html( implode( ', ', $meta ) ) . '</small>' : '' ) . '</td><td class="c">' . (int) $it->get_quantity() . '</td><td class="r">' . esc_html( NURAX_Desk::money( $unit ) ) . '</td><td class="r">' . esc_html( NURAX_Desk::money( $it->get_total() ) ) . '</td></tr>';
		}
		$extra = '';
		$disc  = 0;
		foreach ( $order->get_fees() as $f ) {
			if ( (float) $f->get_total() < 0 ) {
				$disc += (float) $f->get_total();
			} else {
				$rows .= '<tr><td><strong>' . esc_html( $f->get_name() ) . '</strong></td><td class="c">1</td><td class="r">' . esc_html( NURAX_Desk::money( $f->get_total() ) ) . '</td><td class="r">' . esc_html( NURAX_Desk::money( $f->get_total() ) ) . '</td></tr>';
			}
		}
		$sub = (float) $order->get_subtotal();
		foreach ( $order->get_fees() as $f ) {
			if ( (float) $f->get_total() > 0 ) {
				$sub += (float) $f->get_total();
			}
		}
		$extra .= '<div><span>Subtotal</span><span>' . esc_html( NURAX_Desk::money( $sub ) ) . '</span></div>';
		if ( $disc < 0 ) {
			$extra .= '<div><span>Discount</span><span>- ' . esc_html( NURAX_Desk::money( abs( $disc ) ) ) . '</span></div>';
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$extra .= '<div><span>Delivery</span><span>' . esc_html( NURAX_Desk::money( $order->get_shipping_total() ) ) . '</span></div>';
		}
		if ( (float) $order->get_total_tax() > 0 ) {
			$extra .= '<div><span>Tax</span><span>' . esc_html( NURAX_Desk::money( $order->get_total_tax() ) ) . '</span></div>';
		}
		$extra .= '<div class="t"><span>Total</span><span>' . esc_html( NURAX_Desk::money( $order->get_total() ) ) . '</span></div>';
		if ( $paid ) {
			$extra .= '<div><span>Paid (' . esc_html( $order->get_payment_method_title() ) . ( $ref ? ' ' . esc_html( $ref ) : '' ) . ')</span><span>' . esc_html( NURAX_Desk::money( $order->get_total() ) ) . '</span></div>';
			if ( (float) $order->get_meta( '_nura_tendered' ) > 0 ) {
				$extra .= '<div><span>Cash received</span><span>' . esc_html( NURAX_Desk::money( $order->get_meta( '_nura_tendered' ) ) ) . '</span></div><div><span>Change</span><span>' . esc_html( NURAX_Desk::money( $order->get_meta( '_nura_change' ) ) ) . '</span></div>';
			}
			$extra .= '<div class="bal"><span>Balance due</span><span>KSh 0</span></div>';
		} else {
			$extra .= '<div class="bal due"><span>Amount due</span><span>' . esc_html( NURAX_Desk::money( $order->get_total() ) ) . '</span></div>';
		}
		$pay_note = ! $paid && trim( NURAX_Desk::settings()['mpesa_details'] ) ? '<p class="pay"><strong>How to pay:</strong> M-Pesa ' . esc_html( NURAX_Desk::settings()['mpesa_details'] ) . '. Use invoice #' . esc_html( $num ) . ' as the reference.</p>' : '';
		$note     = $order->get_customer_note() ? '<p class="note"><strong>Note:</strong> ' . esc_html( $order->get_customer_note() ) . '</p>' : '';
		$footer   = '' !== trim( $b['note'] ) ? esc_html( $b['note'] ) : 'Thank you for choosing ' . esc_html( $b['name'] ) . '. Unworn wigs can be returned within 7 days with this receipt.';
		$qr_text  = rawurlencode( self::public_url( $order, $type ) );

		ob_start();
		?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title . ' #' . $num . ' - ' . $b['name'] ); ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2eee7;font:13px/1.5 -apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1b1b1b}
.bar{position:sticky;top:0;display:flex;gap:8px;justify-content:center;padding:10px;background:#0E0E0E}
.bar button,.bar a{padding:9px 16px;border:0;border-radius:8px;background:#E4C983;color:#0E0E0E;font-weight:700;text-decoration:none;cursor:pointer;font-size:13px}
.bar a.alt,.bar button.alt{background:#2a2622;color:#fff}
.doc{margin:24px auto;background:#fff;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.a4{width:210mm;min-height:270mm;padding:16mm 16mm 14mm}
.head{display:flex;justify-content:space-between;gap:20px;padding-bottom:16px;border-bottom:3px solid #0E0E0E}
.brand{font-family:Georgia,"Times New Roman",serif;font-size:30px;letter-spacing:.22em;font-weight:700;line-height:1}
.brand small{display:block;margin-top:6px;font:600 9px/1.4 -apple-system,Arial,sans-serif;letter-spacing:.24em;color:#a07a24;text-transform:uppercase}
.biz{text-align:right;font-size:12px;color:#444}
.title{display:flex;justify-content:space-between;align-items:flex-end;margin:22px 0 18px}
.title h1{margin:0;font:700 22px/1.1 Georgia,serif;letter-spacing:.02em}
.stamp{display:inline-block;padding:5px 12px;border:2px solid;border-radius:6px;font-weight:800;letter-spacing:.16em;font-size:12px;transform:rotate(-4deg)}
.stamp.paid{color:#1f8a4c;border-color:#1f8a4c}.stamp.due{color:#b3261e;border-color:#b3261e}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
.box{padding:12px 14px;border-radius:8px;background:#faf7f2}
.box h3{margin:0 0 4px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:#8a6a1f}
table{width:100%;border-collapse:collapse}
th{padding:9px 8px;background:#0E0E0E;color:#E4C983;text-align:left;font-size:10px;letter-spacing:.14em;text-transform:uppercase}
td{padding:10px 8px;border-bottom:1px solid #eee;vertical-align:top}
td small{display:block;color:#777;font-size:11px}
.c{text-align:center}.r{text-align:right;white-space:nowrap}
.sum{display:flex;justify-content:flex-end;margin-top:12px}
.sum>div{width:300px}
.sum div div{display:flex;justify-content:space-between;padding:5px 0}
.sum .t{margin-top:4px;padding-top:9px;border-top:2px solid #0E0E0E;font-size:16px;font-weight:800}
.sum .bal{margin-top:6px;padding:8px 10px;border-radius:6px;background:#eef8f1;font-weight:700;color:#1f8a4c}
.sum .bal.due{background:#fdecea;color:#b3261e}
.pay,.note{margin:16px 0 0;padding:10px 12px;border-left:3px solid #C9A24B;background:#fffaf0}
.foot{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-top:28px;padding-top:14px;border-top:1px solid #eee;color:#555;font-size:11px}
.foot img{width:84px;height:84px}
.rc{width:80mm;padding:6mm 5mm;font-size:12px}
.rc .brand{text-align:center;font-size:24px}.rc .brand small{text-align:center}
.rc .ctr{text-align:center}.rc hr{border:0;border-top:1px dashed #999;margin:8px 0}
.rc table td,.rc table th{padding:4px 0;border:0;font-size:12px}.rc th{background:none;color:#000;border-bottom:1px dashed #999!important}
.rc .sum>div{width:100%}.rc .sum .t{font-size:15px}
.rc .qr{display:block;margin:8px auto 0;width:96px;height:96px}
@media print{body{background:#fff}.bar{display:none}.doc{margin:0;box-shadow:none}.a4{width:auto;min-height:0;padding:0}@page{margin:12mm}}
@media print{.rc{width:72mm;padding:0}@page{margin:4mm}}
@media (max-width:820px){.a4{width:auto;margin:0;padding:18px}.meta{grid-template-columns:1fr}.head{flex-direction:column}.biz{text-align:left}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Print / Save as PDF</button><?php if ( ! $public ) : ?><a class="alt" href="<?php echo esc_url( self::doc_url( $order, 'receipt' === $type ? 'invoice' : 'receipt', false ) ); ?>"><?php echo 'receipt' === $type ? 'Show A4 invoice' : 'Show 80 mm receipt'; ?></a><?php endif; ?></div>
<?php if ( 'receipt' === $type ) : ?>
<div class="doc rc">
	<div class="brand"><?php echo esc_html( strtoupper( $b['name'] ) ); ?><small>Wear your crown</small></div>
	<p class="ctr"><?php echo esc_html( $b['addr'] ); ?><br><?php echo esc_html( $b['phone'] ); ?><?php echo $b['pin'] ? '<br>PIN: ' . esc_html( $b['pin'] ) : ''; ?></p>
	<hr><p class="ctr"><strong><?php echo esc_html( strtoupper( $paid ? 'Receipt' : 'Invoice' ) . ' #' . $num ); ?></strong><br><?php echo esc_html( $date ); ?><?php echo $cash ? '<br>Served by ' . esc_html( $cash->display_name ) : ''; ?><?php echo $name ? '<br>Customer: ' . esc_html( $name ) : ''; ?></p><hr>
	<table><thead><tr><th>Item</th><th class="c">Qty</th><th class="r">Amount</th></tr></thead><tbody>
	<?php foreach ( $order->get_items() as $it ) : ?><tr><td><?php echo esc_html( $it->get_name() ); ?></td><td class="c"><?php echo (int) $it->get_quantity(); ?></td><td class="r"><?php echo esc_html( NURAX_Desk::money( $it->get_total() ) ); ?></td></tr><?php endforeach; ?>
	<?php foreach ( $order->get_fees() as $f ) : if ( (float) $f->get_total() < 0 ) { continue; } ?><tr><td><?php echo esc_html( $f->get_name() ); ?></td><td class="c">1</td><td class="r"><?php echo esc_html( NURAX_Desk::money( $f->get_total() ) ); ?></td></tr><?php endforeach; ?>
	</tbody></table><hr>
	<div class="sum"><div><?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div><hr>
	<?php echo $pay_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p class="ctr"><?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br><?php echo esc_html( $b['web'] ); ?></p>
	<img class="qr" alt="" src="https://api.qrserver.com/v1/create-qr-code/?size=192x192&amp;margin=0&amp;data=<?php echo esc_attr( $qr_text ); ?>">
</div>
<?php else : ?>
<div class="doc a4">
	<div class="head">
		<div class="brand"><?php echo esc_html( strtoupper( $b['name'] ) ); ?><small>East Africa's house of radiant confidence</small></div>
		<div class="biz"><strong><?php echo esc_html( $b['name'] ); ?></strong><br><?php echo esc_html( $b['addr'] ); ?><br><?php echo esc_html( $b['phone'] . ' - ' . $b['email'] ); ?><br><?php echo esc_html( $b['web'] ); ?><?php echo $b['pin'] ? '<br>KRA PIN: ' . esc_html( $b['pin'] ) : ''; ?></div>
	</div>
	<div class="title"><div><h1><?php echo esc_html( $title ); ?></h1><span>#<?php echo esc_html( $num ); ?> - <?php echo esc_html( $date ); ?></span></div><span class="stamp <?php echo $paid ? 'paid' : 'due'; ?>"><?php echo $paid ? 'PAID' : 'UNPAID'; ?></span></div>
	<div class="meta">
		<div class="box"><h3>Billed to</h3><strong><?php echo esc_html( $name ? $name : 'Walk-in customer' ); ?></strong><?php echo $phone ? '<br>' . esc_html( $phone ) : ''; ?><?php echo $order->get_billing_email() ? '<br>' . esc_html( $order->get_billing_email() ) : ''; ?><?php echo $addr ? '<br>' . esc_html( $addr ) : ''; ?></div>
		<div class="box"><h3>Details</h3>Invoice no: <?php echo esc_html( $num ); ?><br>Date: <?php echo esc_html( $date ); ?><br>Channel: <?php echo esc_html( self::channels()[ self::channel( $order ) ] ?? 'Online shop' ); ?><?php echo $cash ? '<br>Served by: ' . esc_html( $cash->display_name ) : ''; ?></div>
	</div>
	<table><thead><tr><th>Description</th><th class="c">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead><tbody><?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></tbody></table>
	<div class="sum"><div><?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
	<?php echo $pay_note . $note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="foot"><div><?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>Prices in Kenya Shillings (KES).</div><img alt="" src="https://api.qrserver.com/v1/create-qr-code/?size=168x168&amp;margin=0&amp;data=<?php echo esc_attr( $qr_text ); ?>"></div>
</div>
<?php endif; ?>
<?php if ( $print ) : ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print();},350);});</script><?php endif; ?>
</body></html>
		<?php
		return (string) ob_get_clean();
	}
}
