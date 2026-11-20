<?php
/**
 * NURA Experience 1.119.0 - branded PDF receipts for WooCommerce orders.
 * 1.120.0 - the receipt is attached automatically to WooCommerce's "Completed order"
 * customer email (filters: 'nurax_receipt_auto_attach' true/false,
 * 'nurax_receipt_attach_emails' list of WC email IDs).
 *
 * On the order edit screen (classic and HPOS) a "PDF receipt" box offers:
 *   - Open / print: streams the receipt inline so the browser PDF viewer can print it.
 *   - Download: the same PDF as a file.
 *   - Save to order: stores the PDF in uploads/nura-receipts (web access denied),
 *     records it on the order and adds a private order note. Re-saving replaces it.
 *   - Email to customer: sends the PDF to the billing email and adds a private note.
 * Customers can also download their receipt from My Account > Orders > View order
 * once the order is paid.
 *
 * PDF engine: FPDF 1.9 (includes/lib/fpdf, permissive licence), core Helvetica,
 * Windows-1252 text. Logo: assets/img/nura-receipt-logo.png (filter
 * 'nurax_receipt_logo' to change). Business details come from nura_facts().
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Receipt {

	const ACTION = 'nurax_receipt';
	const META   = '_nurax_receipt_file';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_admin' ) );
		add_action( 'admin_post_nurax_receipt_customer', array( $this, 'handle_customer' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'customer_button' ) );
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach_to_email' ), 10, 4 );
	}

	/* ---------------------------------------------------------------- admin box */

	public function add_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $s ) {
			add_meta_box( 'nurax-receipt', 'PDF receipt', array( $this, 'box_html' ), $s, 'side', 'high' );
		}
	}

	private static function url( $order_id, $act ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => self::ACTION, 'order' => (int) $order_id, 'act' => $act ), admin_url( 'admin-post.php' ) ),
			self::ACTION . '_' . (int) $order_id . '_' . $act
		);
	}

	public function box_html( $obj ) {
		$order = $obj instanceof WP_Post ? wc_get_order( $obj->ID ) : ( is_object( $obj ) && method_exists( $obj, 'get_id' ) ? $obj : null );
		if ( ! $order ) {
			echo '<p>Save the order first.</p>';
			return;
		}
		$id    = $order->get_id();
		$msg   = isset( $_GET['nurax_receipt'] ) ? sanitize_key( wp_unslash( $_GET['nurax_receipt'] ) ) : '';
		$notes = array(
			'saved'   => 'Receipt saved to this order.',
			'emailed' => 'Receipt emailed to the customer.',
			'noemail' => 'This order has no customer email address.',
			'failed'  => 'Could not complete that. Please try again.',
		);
		if ( isset( $notes[ $msg ] ) ) {
			echo '<p style="padding:6px 8px;background:#f6efe0;border-left:3px solid #C9972C;margin:0 0 10px">' . esc_html( $notes[ $msg ] ) . '</p>';
		}
		$btn = 'display:block;text-align:center;margin:0 0 6px;';
		echo '<a class="button button-primary" style="' . esc_attr( $btn ) . '" target="_blank" rel="noopener" href="' . esc_url( self::url( $id, 'view' ) ) . '">Open and print receipt</a>';
		echo '<a class="button" style="' . esc_attr( $btn ) . '" href="' . esc_url( self::url( $id, 'download' ) ) . '">Download PDF</a>';
		echo '<a class="button" style="' . esc_attr( $btn ) . '" href="' . esc_url( self::url( $id, 'save' ) ) . '">Save receipt to order</a>';
		$email = $order->get_billing_email();
		if ( $email ) {
			echo '<a class="button" style="' . esc_attr( $btn ) . '" href="' . esc_url( self::url( $id, 'email' ) ) . '" onclick="return confirm(\'Email the PDF receipt to ' . esc_js( $email ) . '?\');">Email receipt to customer</a>';
		}
		$file = $order->get_meta( self::META );
		if ( $file && file_exists( self::dir() . basename( $file ) ) ) {
			$when = $order->get_meta( self::META . '_at' );
			echo '<p style="margin:8px 0 0">Saved receipt: <a href="' . esc_url( self::url( $id, 'file' ) ) . '" target="_blank" rel="noopener">' . esc_html( self::filename( $order ) ) . '</a>';
			if ( $when ) {
				echo '<br><span style="color:#777">' . esc_html( wp_date( 'j M Y, H:i', (int) $when ) ) . '</span>';
			}
			echo '</p>';
		}
	}

	public function handle_admin() {
		$id  = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$act = isset( $_GET['act'] ) ? sanitize_key( wp_unslash( $_GET['act'] ) ) : '';
		if ( ! $id || ! in_array( $act, array( 'view', 'download', 'save', 'email', 'file' ), true ) ) {
			wp_die( 'Invalid request.' );
		}
		check_admin_referer( self::ACTION . '_' . $id . '_' . $act );
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'You do not have permission to do that.' );
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			wp_die( 'Order not found.' );
		}
		$back = method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $id . '&action=edit' );

		if ( 'file' === $act ) {
			$file = self::dir() . basename( (string) $order->get_meta( self::META ) );
			if ( ! $order->get_meta( self::META ) || ! file_exists( $file ) ) {
				wp_die( 'No saved receipt for this order.' );
			}
			self::send( file_get_contents( $file ), self::filename( $order ), true ); // phpcs:ignore
		}

		$pdf = self::pdf( $order );
		if ( 'view' === $act || 'download' === $act ) {
			self::send( $pdf, self::filename( $order ), 'view' === $act );
		}
		if ( 'save' === $act ) {
			$ok = self::save( $order, $pdf );
			wp_safe_redirect( add_query_arg( 'nurax_receipt', $ok ? 'saved' : 'failed', $back ) );
			exit;
		}
		// email.
		$to = $order->get_billing_email();
		if ( ! $to ) {
			wp_safe_redirect( add_query_arg( 'nurax_receipt', 'noemail', $back ) );
			exit;
		}
		$ok = self::email( $order, $pdf );
		wp_safe_redirect( add_query_arg( 'nurax_receipt', $ok ? 'emailed' : 'failed', $back ) );
		exit;
	}

	/* ------------------------------------------------- auto-attach to WC emails */

	/** Temp files created for this request's emails; removed at shutdown. */
	private static $tmp_dirs = array();

	public function attach_to_email( $attachments, $email_id = '', $order = null, $email = null ) {
		if ( ! apply_filters( 'nurax_receipt_auto_attach', true ) ) {
			return $attachments;
		}
		$ids = (array) apply_filters( 'nurax_receipt_attach_emails', array( 'customer_completed_order' ) );
		if ( ! in_array( $email_id, $ids, true ) || ! $order instanceof WC_Order ) {
			return $attachments;
		}
		try {
			$pdf = self::pdf( $order );
		} catch ( \Throwable $e ) {
			$order->add_order_note( 'PDF receipt could not be attached to the "' . $email_id . '" email: ' . $e->getMessage() );
			return $attachments;
		}
		$dir = self::dir() . 'tmp-' . wp_generate_password( 16, false, false ) . '/';
		if ( ! wp_mkdir_p( $dir ) ) {
			return $attachments;
		}
		$file = $dir . self::filename( $order );
		if ( false === @file_put_contents( $file, $pdf ) ) { // phpcs:ignore
			return $attachments;
		}
		if ( empty( self::$tmp_dirs ) ) {
			register_shutdown_function( array( __CLASS__, 'cleanup_tmp' ) );
		}
		self::$tmp_dirs[] = $dir;
		$attachments   = is_array( $attachments ) ? $attachments : array();
		$attachments[] = $file;
		$order->add_order_note( sprintf( 'PDF receipt %s attached to the Completed order email to %s.', self::receipt_no( $order ), $order->get_billing_email() ) );
		return $attachments;
	}

	public static function cleanup_tmp() {
		foreach ( self::$tmp_dirs as $dir ) {
			foreach ( (array) glob( $dir . '*' ) as $f ) {
				@unlink( $f ); // phpcs:ignore
			}
			@rmdir( $dir ); // phpcs:ignore
		}
		self::$tmp_dirs = array();
	}

	/* ------------------------------------------------------------- customer side */

	public static function paid( $order ) {
		return $order->is_paid() || $order->get_date_paid();
	}

	public function customer_button( $order ) {
		if ( ! is_object( $order ) || ! is_account_page() || ! self::paid( $order ) ) {
			return;
		}
		$url = add_query_arg(
			array( 'action' => 'nurax_receipt_customer', 'order' => $order->get_id(), 'key' => $order->get_order_key() ),
			admin_url( 'admin-post.php' )
		);
		echo '<p class="nurax-receipt-dl"><a class="button" href="' . esc_url( $url ) . '">Download receipt (PDF)</a></p>';
	}

	public function handle_customer() {
		$id    = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$order = $id ? wc_get_order( $id ) : null;
		if ( ! $order || ! $key || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			wp_die( 'Receipt not found.' );
		}
		$uid = get_current_user_id();
		if ( ! $uid || ( (int) $order->get_customer_id() !== $uid && ! current_user_can( 'edit_shop_orders' ) ) ) {
			wp_die( 'Please log in to download this receipt.' );
		}
		if ( ! self::paid( $order ) ) {
			wp_die( 'A receipt is available once the order is paid.' );
		}
		self::send( self::pdf( $order ), self::filename( $order ), false );
	}

	/* ----------------------------------------------------------- storage / mail */

	public static function dir() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'nura-receipts/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			@file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			@file_put_contents( $dir . 'index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore
		}
		return $dir;
	}

	public static function filename( $order ) {
		return 'NURA-Beauty-Receipt-' . preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $order->get_order_number() ) . '.pdf';
	}

	public static function save( $order, $pdf ) {
		$dir  = self::dir();
		$old  = (string) $order->get_meta( self::META );
		$name = 'receipt-' . $order->get_id() . '-' . wp_generate_password( 12, false, false ) . '.pdf';
		if ( false === @file_put_contents( $dir . $name, $pdf ) ) { // phpcs:ignore
			return false;
		}
		if ( $old && basename( $old ) !== $name && file_exists( $dir . basename( $old ) ) ) {
			@unlink( $dir . basename( $old ) ); // phpcs:ignore
		}
		$order->update_meta_data( self::META, $name );
		$order->update_meta_data( self::META . '_at', time() );
		$order->save();
		$user = wp_get_current_user();
		$order->add_order_note( sprintf( 'PDF receipt %s saved to the order%s.', self::receipt_no( $order ), $user && $user->exists() ? ' by ' . $user->display_name : '' ) );
		return true;
	}

	public static function email( $order, $pdf ) {
		$tmp = trailingslashit( get_temp_dir() ) . self::filename( $order );
		if ( false === @file_put_contents( $tmp, $pdf ) ) { // phpcs:ignore
			return false;
		}
		$f       = function_exists( 'nura_facts' ) ? nura_facts() : array();
		$name    = trim( $order->get_billing_first_name() );
		$subject = sprintf( 'Your NURA Beauty receipt for order #%s', $order->get_order_number() );
		$body    = '<p>' . ( $name ? 'Hello ' . esc_html( $name ) . ',' : 'Hello,' ) . '</p>'
			. '<p>Thank you for shopping with NURA Beauty. Your receipt for order #' . esc_html( $order->get_order_number() ) . ' is attached as a PDF.</p>'
			. '<p>Questions about your order? Reply to this email or WhatsApp us on ' . esc_html( self::phone() ) . '.</p>'
			. '<p>NURA Beauty<br>' . esc_html( isset( $f['address_short'] ) ? $f['address_short'] : 'Nairobi, Kenya' ) . '</p>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$ok      = wp_mail( $order->get_billing_email(), $subject, $body, $headers, array( $tmp ) );
		@unlink( $tmp ); // phpcs:ignore
		if ( $ok ) {
			$order->add_order_note( sprintf( 'PDF receipt %s emailed to %s.', self::receipt_no( $order ), $order->get_billing_email() ) );
		}
		return (bool) $ok;
	}

	private static function send( $pdf, $filename, $inline ) {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Robots-Tag: noindex' );
		echo $pdf; // phpcs:ignore
		exit;
	}

	/* ------------------------------------------------------------- data mapping */

	public static function phone() {
		return apply_filters( 'nurax_receipt_phone', '+254 714 994 898' );
	}

	public static function receipt_no( $order ) {
		return 'NB-' . str_pad( (string) $order->get_order_number(), 6, '0', STR_PAD_LEFT );
	}

	private static function clean( $s ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	private static function address_lines( $order, $type ) {
		$g     = 'get_' . $type . '_';
		$lines = array();
		$name  = trim( $order->{$g . 'first_name'}() . ' ' . $order->{$g . 'last_name'}() );
		if ( $name ) {
			$lines[] = $name;
		}
		if ( $order->{$g . 'company'}() ) {
			$lines[] = $order->{$g . 'company'}();
		}
		foreach ( array( 'address_1', 'address_2' ) as $k ) {
			if ( $order->{$g . $k}() ) {
				$lines[] = $order->{$g . $k}();
			}
		}
		$city = trim( $order->{$g . 'city'}() . ' ' . $order->{$g . 'postcode'}() );
		if ( $city ) {
			$lines[] = $city;
		}
		$state   = $order->{$g . 'state'}();
		$country = $order->{$g . 'country'}();
		if ( $state && function_exists( 'WC' ) && $country ) {
			$states = WC()->countries->get_states( $country );
			$state  = isset( $states[ $state ] ) ? $states[ $state ] : $state;
		}
		$cname = $country && function_exists( 'WC' ) ? ( isset( WC()->countries->countries[ $country ] ) ? WC()->countries->countries[ $country ] : $country ) : '';
		$tail  = trim( implode( ', ', array_filter( array( $state, $cname ) ) ) );
		if ( $tail ) {
			$lines[] = $tail;
		}
		$phone = method_exists( $order, $g . 'phone' ) ? $order->{$g . 'phone'}() : '';
		if ( $phone ) {
			$lines[] = 'Tel: ' . $phone;
		}
		if ( 'billing' === $type && $order->get_billing_email() ) {
			$lines[] = $order->get_billing_email();
		}
		return array_map( array( __CLASS__, 'clean' ), $lines );
	}

	/** Plain data array for the renderer (kept separate so it can be tested without WordPress). */
	public static function data( $order ) {
		$f     = function_exists( 'nura_facts' ) ? nura_facts() : array();
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$qty  = max( 1, (int) $item->get_quantity() );
			$sub  = (float) $item->get_subtotal();
			$meta = array();
			foreach ( $item->get_formatted_meta_data( '_', true ) as $m ) {
				$meta[] = self::clean( $m->display_key ) . ': ' . self::clean( $m->display_value );
			}
			$product = $item->get_product();
			$items[] = array(
				'name' => self::clean( $item->get_name() ),
				'meta' => implode( '  |  ', $meta ),
				'sku'  => $product && $product->get_sku() ? $product->get_sku() : '',
				'qty'  => $qty,
				'unit' => $sub / $qty,
				'line' => $sub,
			);
		}
		$totals   = array();
		$totals[] = array( 'Subtotal', (float) $order->get_subtotal() );
		if ( (float) $order->get_total_discount() > 0 ) {
			$totals[] = array( 'Discount', - (float) $order->get_total_discount() );
		}
		foreach ( $order->get_fees() as $fee ) {
			$totals[] = array( self::clean( $fee->get_name() ), (float) $fee->get_total() );
		}
		if ( $order->get_shipping_method() || (float) $order->get_shipping_total() > 0 ) {
			$totals[] = array( 'Delivery' . ( $order->get_shipping_method() ? ': ' . self::clean( $order->get_shipping_method() ) : '' ), (float) $order->get_shipping_total() );
		}
		if ( (float) $order->get_total_tax() > 0 ) {
			$totals[] = array( 'Tax', (float) $order->get_total_tax() );
		}
		$total    = (float) $order->get_total();
		$refunded = (float) $order->get_total_refunded();
		$paid     = self::paid( $order ) ? $total : 0.0;
		if ( $refunded >= $total && $total > 0 ) {
			$status = 'REFUNDED';
		} elseif ( $paid > 0 ) {
			$status = 'PAID';
		} else {
			$status = 'UNPAID';
		}
		$dp   = $order->get_date_paid();
		$dc   = $order->get_date_created();
		$ref  = $order->get_transaction_id();
		return array(
			'receipt_no'  => self::receipt_no( $order ),
			'order_no'    => (string) $order->get_order_number(),
			'issued'      => wp_date( 'j F Y' ),
			'order_date'  => $dc ? wp_date( 'j F Y, H:i', $dc->getTimestamp() ) : '',
			'paid_date'   => $dp ? wp_date( 'j F Y, H:i', $dp->getTimestamp() ) : '',
			'method'      => self::clean( $order->get_payment_method_title() ),
			'ref'         => $ref ? $ref : '',
			'status'      => $status,
			'bill'        => self::address_lines( $order, 'billing' ),
			'ship'        => $order->has_shipping_address() ? self::address_lines( $order, 'shipping' ) : array(),
			'items'       => $items,
			'totals'      => $totals,
			'total'       => $total,
			'paid'        => $paid,
			'refunded'    => $refunded,
			'balance'     => max( 0, $total - $paid ),
			'note'        => self::clean( $order->get_customer_note() ),
			'currency'    => 'KSh',
			'biz'         => array(
				'name'    => 'NURA Beauty',
				'tagline' => 'The House of Radiant Confidence',
				'address' => isset( $f['address_full'] ) ? $f['address_full'] : 'Nairobi, Kenya',
				'phone'   => self::phone(),
				'email'   => apply_filters( 'nurax_receipt_email', get_option( 'woocommerce_email_from_address', 'care@nurabeauty.co.ke' ) ),
				'web'     => 'nurabeauty.co.ke',
				'till'    => isset( $f['till'] ) ? $f['till'] : '',
				'hours'   => isset( $f['hours_text'] ) ? $f['hours_text'] : '',
				// 1.123.1: owner - returns only for a wrong or damaged item.
				'returns' => apply_filters( 'nurax_receipt_returns', 'We accept returns ONLY if you received the wrong item or the item arrived damaged. Report it within 48 hours of delivery with photos, and keep the wig unworn, uncut and in its original packaging with this receipt. We do not accept returns for change of mind, size, colour or style, or for any wig that has been worn, cut, washed or installed.' ),
			),
			'logo'        => apply_filters( 'nurax_receipt_logo', NURAX_DIR . 'assets/img/nura-receipt-logo.png' ),
		);
	}

	public static function pdf( $order ) {
		return self::render( self::data( $order ) );
	}

	/* ------------------------------------------------------------------ render */

	private static function t( $s ) {
		$s = str_replace( array( "\u{2013}", "\u{2014}" ), '-', (string) $s );
		$o = @iconv( 'UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s ); // phpcs:ignore
		return false === $o ? $s : $o;
	}

	private static function money( $cur, $n ) {
		return ( $n < 0 ? '- ' : '' ) . $cur . ' ' . number_format( abs( (float) $n ), 2 );
	}

	/** Render the receipt from a data array; returns the PDF bytes. */
	public static function render( array $d ) {
		if ( ! class_exists( 'FPDF' ) ) {
			if ( ! defined( 'FPDF_FONTPATH' ) ) {
				define( 'FPDF_FONTPATH', __DIR__ . '/lib/fpdf/font/' );
			}
			require_once __DIR__ . '/lib/fpdf/fpdf.php';
		}
		$brown = array( 58, 34, 8 );
		$gold  = array( 201, 151, 44 );
		$cream = array( 251, 246, 236 );
		$grey  = array( 110, 104, 98 );
		$line  = array( 228, 218, 200 );
		$cur   = $d['currency'];
		$L     = 16;
		$W     = 210 - 2 * $L;

		$p = new FPDF( 'P', 'mm', 'A4' );
		$p->SetTitle( self::t( 'NURA Beauty Receipt ' . $d['receipt_no'] ) );
		$p->SetAuthor( 'NURA Beauty' );
		$p->SetCreator( 'NURA Experience' );
		$p->SetMargins( $L, 16, $L );
		$p->SetAutoPageBreak( true, 34 );
		$p->AliasNbPages();
		$p->AddPage();

		// Top gold band.
		$p->SetFillColor( $gold[0], $gold[1], $gold[2] );
		$p->Rect( 0, 0, 210, 3.2, 'F' );
		$p->SetFillColor( $brown[0], $brown[1], $brown[2] );
		$p->Rect( 0, 3.2, 210, 0.8, 'F' );

		// Logo.
		if ( ! empty( $d['logo'] ) && file_exists( $d['logo'] ) ) {
			$p->Image( $d['logo'], $L, 12, 52 );
		} else {
			$p->SetXY( $L, 14 );
			$p->SetFont( 'Helvetica', 'B', 22 );
			$p->SetTextColor( $brown[0], $brown[1], $brown[2] );
			$p->Cell( 60, 10, 'NURA Beauty' );
		}

		// Title block (right).
		$p->SetTextColor( $brown[0], $brown[1], $brown[2] );
		$p->SetFont( 'Helvetica', 'B', 26 );
		$p->SetXY( 110, 12 );
		$p->Cell( 210 - $L - 110, 11, 'RECEIPT', 0, 2, 'R' );
		$p->SetFont( 'Helvetica', '', 9 );
		$p->SetTextColor( $grey[0], $grey[1], $grey[2] );
		$p->Cell( 210 - $L - 110, 5, self::t( 'Receipt no.  ' ) . self::t( $d['receipt_no'] ), 0, 2, 'R' );
		$p->Cell( 210 - $L - 110, 5, self::t( 'Date issued  ' . $d['issued'] ), 0, 2, 'R' );

		// Status pill.
		$st   = $d['status'];
		$fill = 'PAID' === $st ? $gold : ( 'REFUNDED' === $st ? $grey : array( 176, 58, 46 ) );
		$p->SetFont( 'Helvetica', 'B', 9 );
		$pw = $p->GetStringWidth( $st ) + 12;
		$px = 210 - $L - $pw;
		$p->SetFillColor( $fill[0], $fill[1], $fill[2] );
		$p->Rect( $px, 36, $pw, 7, 'F' );
		$p->SetTextColor( 255, 255, 255 );
		$p->SetXY( $px, 36 );
		$p->Cell( $pw, 7, $st, 0, 0, 'C' );

		// Business details under logo.
		$p->SetXY( $L, 36 );
		$p->SetFont( 'Helvetica', '', 8 );
		$p->SetTextColor( $grey[0], $grey[1], $grey[2] );
		$b = $d['biz'];
		$p->MultiCell( 112, 3.9, self::t( $b['address'] ), 0, 'L' );
		$p->SetX( $L );
		$p->Cell( 112, 3.9, self::t( 'Tel / WhatsApp ' . $b['phone'] . '   |   ' . $b['email'] . '   |   ' . $b['web'] ), 0, 1 );

		// Divider.
		$y = max( $p->GetY(), 44 ) + 4;
		$p->SetDrawColor( $gold[0], $gold[1], $gold[2] );
		$p->SetLineWidth( 0.5 );
		$p->Line( $L, $y, 210 - $L, $y );
		$p->SetLineWidth( 0.2 );

		// Bill to / deliver to.
		$y += 6;
		$colw = ( $W - 8 ) / 2;
		$cols = array( array( 'BILLED TO', $d['bill'] ) );
		if ( ! empty( $d['ship'] ) ) {
			$cols[] = array( 'DELIVER TO', $d['ship'] );
		}
		$maxy = $y;
		foreach ( $cols as $i => $c ) {
			$x = $L + $i * ( $colw + 8 );
			$p->SetXY( $x, $y );
			$p->SetFont( 'Helvetica', 'B', 8 );
			$p->SetTextColor( $gold[0], $gold[1], $gold[2] );
			$p->Cell( $colw, 5, $c[0], 0, 2 );
			foreach ( $c[1] as $k => $ln ) {
				$p->SetFont( 'Helvetica', 0 === $k ? 'B' : '', 0 === $k ? 10 : 9 );
				$p->SetTextColor( 0 === $k ? $brown[0] : 60, 0 === $k ? $brown[1] : 55, 0 === $k ? $brown[2] : 50 );
				$p->SetX( $x );
				$p->MultiCell( $colw, 0 === $k ? 5.4 : 4.6, self::t( $ln ), 0, 'L' );
			}
			$maxy = max( $maxy, $p->GetY() );
		}

		// Order facts strip.
		$y = $maxy + 6;
		$facts = array(
			array( 'ORDER NO.', '#' . $d['order_no'] ),
			array( 'ORDER DATE', $d['order_date'] ),
			array( 'PAYMENT METHOD', $d['method'] ? $d['method'] : '-' ),
			array( 'PAID ON', $d['paid_date'] ? $d['paid_date'] : 'Not yet paid' ),
		);
		$fw = $W / 4;
		$sh = 19;
		$p->SetFillColor( $cream[0], $cream[1], $cream[2] );
		$p->Rect( $L, $y, $W, $sh, 'F' );
		foreach ( $facts as $i => $fct ) {
			$x = $L + $i * $fw;
			$p->SetXY( $x + 3, $y + 2.8 );
			$p->SetFont( 'Helvetica', 'B', 7 );
			$p->SetTextColor( $gold[0], $gold[1], $gold[2] );
			$p->Cell( $fw - 6, 4, $fct[0], 0, 2 );
			$p->SetFont( 'Helvetica', 'B', 8.5 );
			$p->SetTextColor( $brown[0], $brown[1], $brown[2] );
			$p->MultiCell( $fw - 6, 4.2, self::t( $fct[1] ), 0, 'L' );
		}
		$y += $sh;
		if ( $d['ref'] ) {
			$p->SetXY( $L, $y + 1.5 );
			$p->SetFont( 'Helvetica', '', 7.5 );
			$p->SetTextColor( $grey[0], $grey[1], $grey[2] );
			$p->Cell( $W, 4, self::t( 'Payment reference: ' . $d['ref'] ), 0, 1, 'R' );
			$y += 5;
		}

		// Items table.
		$y += 5;
		$cw = array( $W - 20 - 32 - 34, 20, 32, 34 );
		$p->SetXY( $L, $y );
		$p->SetFillColor( $brown[0], $brown[1], $brown[2] );
		$p->SetTextColor( 255, 255, 255 );
		$p->SetFont( 'Helvetica', 'B', 8.5 );
		$p->Cell( $cw[0], 8, '   ITEM', 0, 0, 'L', true );
		$p->Cell( $cw[1], 8, 'QTY', 0, 0, 'C', true );
		$p->Cell( $cw[2], 8, 'UNIT PRICE', 0, 0, 'R', true );
		$p->Cell( $cw[3], 8, 'AMOUNT   ', 0, 1, 'R', true );
		$p->SetDrawColor( $line[0], $line[1], $line[2] );
		foreach ( $d['items'] as $n => $it ) {
			$top = $p->GetY();
			if ( $top > 245 ) {
				$p->AddPage();
				$top = $p->GetY();
			}
			$p->SetXY( $L + 3, $top + 2.5 );
			$p->SetFont( 'Helvetica', 'B', 9.5 );
			$p->SetTextColor( $brown[0], $brown[1], $brown[2] );
			$p->MultiCell( $cw[0] - 6, 4.8, self::t( $it['name'] ), 0, 'L' );
			$sub = array_filter( array( $it['meta'], $it['sku'] ? 'SKU ' . $it['sku'] : '' ) );
			if ( $sub ) {
				$p->SetX( $L + 3 );
				$p->SetFont( 'Helvetica', '', 7.8 );
				$p->SetTextColor( $grey[0], $grey[1], $grey[2] );
				$p->MultiCell( $cw[0] - 6, 4, self::t( implode( '   |   ', $sub ) ), 0, 'L' );
			}
			$bottom = $p->GetY() + 2.5;
			$p->SetFont( 'Helvetica', '', 9.5 );
			$p->SetTextColor( 40, 35, 30 );
			$p->SetXY( $L + $cw[0], $top + 2.5 );
			$p->Cell( $cw[1], 4.8, (string) $it['qty'], 0, 0, 'C' );
			$p->Cell( $cw[2], 4.8, self::t( self::money( $cur, $it['unit'] ) ), 0, 0, 'R' );
			$p->SetFont( 'Helvetica', 'B', 9.5 );
			$p->Cell( $cw[3] - 3, 4.8, self::t( self::money( $cur, $it['line'] ) ), 0, 0, 'R' );
			$p->Line( $L, $bottom, 210 - $L, $bottom );
			$p->SetY( $bottom );
		}

		// Totals.
		$tx = $L + $W - 92;
		$p->SetY( $p->GetY() + 4 );
		foreach ( $d['totals'] as $row ) {
			$ry = $p->GetY();
			$p->SetXY( $tx, $ry + 1 );
			$p->SetFont( 'Helvetica', '', 9 );
			$p->SetTextColor( 70, 64, 58 );
			$p->MultiCell( 56, 4.4, self::t( $row[0] ), 0, 'L' );
			$ny = $p->GetY() + 1;
			$p->SetXY( $tx + 58, $ry + 1 );
			$p->Cell( 34, 4.4, self::t( self::money( $cur, $row[1] ) ), 0, 0, 'R' );
			$p->SetY( $ny );
		}
		$p->SetY( $p->GetY() + 1.5 );
		$p->SetFillColor( $brown[0], $brown[1], $brown[2] );
		$p->SetTextColor( 255, 255, 255 );
		$p->SetFont( 'Helvetica', 'B', 11 );
		$p->SetX( $tx );
		$p->Cell( 52, 10, '  TOTAL', 0, 0, 'L', true );
		$p->Cell( 40, 10, self::t( self::money( $cur, $d['total'] ) ) . '  ', 0, 1, 'R', true );
		$p->SetFont( 'Helvetica', 'B', 9.5 );
		$p->SetTextColor( $gold[0], $gold[1], $gold[2] );
		$p->SetX( $tx );
		$p->Cell( 58, 7, 'Amount paid', 0, 0, 'L' );
		$p->Cell( 34, 7, self::t( self::money( $cur, $d['paid'] ) ), 0, 1, 'R' );
		if ( $d['refunded'] > 0 ) {
			$p->SetX( $tx );
			$p->SetTextColor( 70, 64, 58 );
			$p->Cell( 58, 6, 'Refunded', 0, 0, 'L' );
			$p->Cell( 34, 6, self::t( self::money( $cur, - $d['refunded'] ) ), 0, 1, 'R' );
		}
		$p->SetX( $tx );
		$p->SetTextColor( $brown[0], $brown[1], $brown[2] );
		$p->Cell( 58, 6, 'Balance due', 0, 0, 'L' );
		$p->Cell( 34, 6, self::t( self::money( $cur, $d['balance'] ) ), 0, 1, 'R' );

		// Customer note.
		if ( $d['note'] ) {
			$p->SetY( $p->GetY() + 4 );
			$p->SetFont( 'Helvetica', 'B', 8 );
			$p->SetTextColor( $gold[0], $gold[1], $gold[2] );
			$p->Cell( $W, 5, 'CUSTOMER NOTE', 0, 1 );
			$p->SetFont( 'Helvetica', 'I', 8.5 );
			$p->SetTextColor( 70, 64, 58 );
			$p->MultiCell( $W, 4.4, self::t( $d['note'] ), 0, 'L' );
		}

		// Thank-you + policy block.
		$p->SetY( max( $p->GetY() + 8, 214 ) );
		if ( $p->GetY() > 228 ) {
			$p->AddPage();
		}
		$by = $p->GetY();
		$p->SetFillColor( $cream[0], $cream[1], $cream[2] );
		$p->Rect( $L, $by, $W, 38, 'F' );
		$p->SetFillColor( $gold[0], $gold[1], $gold[2] );
		$p->Rect( $L, $by, 1.2, 38, 'F' );
		$p->SetXY( $L + 6, $by + 4 );
		$p->SetFont( 'Helvetica', 'B', 12 );
		$p->SetTextColor( $brown[0], $brown[1], $brown[2] );
		$p->Cell( $W - 12, 6, 'Thank you for choosing NURA Beauty.', 0, 2 );
		$p->SetFont( 'Helvetica', '', 8 );
		$p->SetTextColor( 70, 64, 58 );
		$p->SetFont( 'Helvetica', 'B', 8 );
		$p->Cell( 17, 4, 'Returns policy:', 0, 0 );
		$p->SetFont( 'Helvetica', '', 8 );
		$p->SetX( $L + 6 );
		$p->MultiCell( $W - 12, 4, self::t( '                            ' . $b['returns'] ), 0, 'L' );
		$p->SetX( $L + 6 );
		$p->MultiCell( $W - 12, 4, self::t( 'Visit us: ' . $b['hours'] . ( $b['till'] ? '.   M-Pesa Till ' . $b['till'] . '.' : '.' ) ), 0, 'L' );

		// Footer.
		$p->SetAutoPageBreak( false );
		$p->SetY( -24 );
		$p->SetDrawColor( $line[0], $line[1], $line[2] );
		$p->Line( $L, $p->GetY(), 210 - $L, $p->GetY() );
		$p->SetY( $p->GetY() + 2 );
		$p->SetFont( 'Helvetica', '', 7.5 );
		$p->SetTextColor( $grey[0], $grey[1], $grey[2] );
		$p->Cell( $W, 4, self::t( $b['name'] . '  |  ' . $b['tagline'] . '  |  ' . $b['web'] . '  |  ' . $b['phone'] ), 0, 1, 'C' );
		$p->Cell( $W, 4, self::t( 'This receipt was generated electronically and is valid without a signature.  Page ' . $p->PageNo() . ' of {nb}' ), 0, 1, 'C' );
		$p->SetFillColor( $gold[0], $gold[1], $gold[2] );
		$p->Rect( 0, 294, 210, 3, 'F' );

		return $p->Output( 'S' );
	}
}
