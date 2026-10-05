<?php
/**
 * Abandoned cart recovery for NURA.
 *
 * Captures a shopper's cart together with the email (and phone, when known)
 * they entered, then runs a timed three-step reminder sequence on WP-Cron.
 * Each reminder can go by email and/or WhatsApp, restores the cart from a
 * one-click link, stops the moment an order is placed, and carries a one-click
 * unsubscribe wired into the NURA consent ledger.
 *
 * Marketing consent gating (NURAX_Consent) is respected: when the consent gate
 * for a channel is active, only shoppers who opted in are messaged on it.
 *
 * The whole feature is OFF by default and is configured under
 * WooCommerce > NURA Cart Recovery.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NURAX_Abandoned_Cart {

	const DB_VERSION = '1';
	const CRON_HOOK  = 'nurax_cart_recovery_event';
	const OPTION     = 'nurax_cart_recovery';

	private static $instance = null;

	public static function instance(): NURAX_Abandoned_Cart {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'nurax_abandoned_carts';
	}

	public function defaults(): array {
		return array(
			'enabled'         => 0,
			'channel'         => 'email',
			'delay1'          => 60,
			'delay2'          => 1440,
			'delay3'          => 4320,
			'discount_code'   => '',
			'from_name'       => '',
			'reply_to'        => '',
			'whatsapp_handoff' => '',
			'wa_template'     => '',
			'wa_lang'         => 'en_US',
		);
	}

	public function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $this->defaults(), $saved );
	}

	public function boot(): void {
		add_filter( 'cron_schedules', array( $this, 'cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'process' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );

		add_action( 'admin_init', array( $this, 'maybe_install' ) );

		add_action( 'woocommerce_add_to_cart', array( $this, 'capture_logged_in' ), 20 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'capture_logged_in' ), 20 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'capture_logged_in' ), 20 );
		add_action( 'wp_ajax_nurax_cart_capture', array( $this, 'ajax_capture' ) );
		add_action( 'wp_ajax_nopriv_nurax_cart_capture', array( $this, 'ajax_capture' ) );
		add_action( 'wp_footer', array( $this, 'capture_script' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'capture_from_store_api' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'handle_links' ) );

		add_action( 'woocommerce_thankyou', array( $this, 'complete_by_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'complete_by_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'complete_by_order' ), 10, 1 );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_nurax_cart_test', array( $this, 'handle_test_send' ) );
	}

	public function cron_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules['nurax_15min'] ) ) {
			$schedules['nurax_15min'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes (NURA)', 'nura-experience' ),
			);
		}
		return $schedules;
	}

	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'nurax_15min', self::CRON_HOOK );
		}
	}

	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(32) NOT NULL DEFAULT '',
			name VARCHAR(190) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cart LONGTEXT NULL,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT '',
			token VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			mailed_stage TINYINT NOT NULL DEFAULT 0,
			created_at DATETIME NULL,
			updated_at DATETIME NULL,
			recovered_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY status (status),
			KEY token (token)
		) {$collate};";
		dbDelta( $sql );
		update_option( 'nurax_cart_db_version', self::DB_VERSION );
	}

	public function maybe_install(): void {
		if ( get_option( 'nurax_cart_db_version' ) !== self::DB_VERSION ) {
			$this->install();
		}
	}

	public function capture_logged_in(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( empty( $user->user_email ) ) {
			return;
		}
		$phone = (string) get_user_meta( (int) $user->ID, 'billing_phone', true );
		$this->capture( (string) $user->user_email, (string) $user->display_name, (int) $user->ID, $phone );
	}

	/**
	 * @param WC_Order        $order
	 * @param WP_REST_Request $request
	 */
	public function capture_from_store_api( $order, $request ): void {
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}
		$email = method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '';
		if ( ! is_email( $email ) ) {
			return;
		}
		$name = '';
		if ( method_exists( $order, 'get_billing_first_name' ) ) {
			$name = trim( (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name() );
		}
		$phone = method_exists( $order, 'get_billing_phone' ) ? (string) $order->get_billing_phone() : '';
		$uid   = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		$this->capture( $email, $name, $uid, $phone );
	}

	public function ajax_capture(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'nurax_cart' ) ) {
			wp_send_json_error( 'bad_nonce', 403 );
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) && function_exists( 'WC' ) && WC()->customer ) {
			$maybe = WC()->customer->get_billing_email();
			if ( is_email( $maybe ) ) {
				$email = $maybe;
			}
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( 'bad_email', 200 );
		}
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$this->capture( $email, '', (int) get_current_user_id(), $phone );
		wp_send_json_success( 'ok' );
	}

	private function new_token(): string {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 24, false, false );
		}
		return md5( uniqid( (string) wp_rand(), true ) );
	}

	private function capture( string $email, string $name, int $user_id, string $phone = '' ): void {
		if ( ! is_email( $email ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$items = array();
		foreach ( WC()->cart->get_cart() as $ci ) {
			$product = isset( $ci['data'] ) ? $ci['data'] : null;
			$pid     = isset( $ci['product_id'] ) ? (int) $ci['product_id'] : 0;
			if ( ! $product ) {
				continue;
			}
			$items[] = array(
				'id'    => $pid,
				'qty'   => isset( $ci['quantity'] ) ? (int) $ci['quantity'] : 1,
				'name'  => $product->get_name(),
				'price' => (float) $product->get_price(),
			);
		}
		if ( empty( $items ) ) {
			return;
		}

		global $wpdb;
		$table     = $this->table();
		$now       = current_time( 'mysql' );
		$subtotal  = (float) WC()->cart->get_subtotal();
		$currency  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$cart_json = wp_json_encode( $items );
		$phone     = preg_replace( '/[^0-9]/', '', $phone );

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, status FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT 1", $email )
		);

		if ( $existing ) {
			$data = array(
				'name'         => $name,
				'user_id'      => $user_id,
				'cart'         => $cart_json,
				'subtotal'     => $subtotal,
				'currency'     => $currency,
				'status'       => 'pending',
				'mailed_stage' => 0,
				'updated_at'   => $now,
			);
			if ( '' !== $phone ) {
				$data['phone'] = $phone;
			}
			$wpdb->update( $table, $data, array( 'id' => (int) $existing->id ) );
		} else {
			$wpdb->insert(
				$table,
				array(
					'email'        => $email,
					'phone'        => $phone,
					'name'         => $name,
					'user_id'      => $user_id,
					'cart'         => $cart_json,
					'subtotal'     => $subtotal,
					'currency'     => $currency,
					'token'        => $this->new_token(),
					'status'       => 'pending',
					'mailed_stage' => 0,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
		}
	}

	public function capture_script(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
		$nonce = esc_js( wp_create_nonce( 'nurax_cart' ) );
		?>
		<script>
		(function(){
			var CR = { ajax: '<?php echo $ajax; ?>', nonce: '<?php echo $nonce; ?>' };
			var sent = '';
			var timer = null;
			function valid(v){ return v.indexOf( '@' ) > 0 && v.lastIndexOf( '.' ) > v.indexOf( '@' ); }
			function send(v){
				if ( v === sent || ! valid( v ) ) { return; }
				sent = v;
				var d = new URLSearchParams();
				d.append( 'action', 'nurax_cart_capture' );
				d.append( 'nonce', CR.nonce );
				d.append( 'email', v );
				var ph = document.querySelector( 'input[name="billing_phone"], #billing_phone' );
				if ( ph && ph.value ) { d.append( 'phone', ph.value ); }
				if ( window.fetch ) { fetch( CR.ajax, { method: 'POST', credentials: 'same-origin', body: d } ); }
			}
			function isEmailField(t){
				if ( ! t || t.tagName !== 'INPUT' ) { return false; }
				if ( t.type === 'email' ) { return true; }
				var id = ( t.id || '' ).toLowerCase();
				var nm = ( t.name || '' ).toLowerCase();
				return id === 'billing_email' || id === 'email' || id.indexOf( 'email' ) > -1 || nm.indexOf( 'email' ) > -1;
			}
			function fromEvent(e){
				var t = e.target;
				if ( ! isEmailField( t ) ) { return; }
				var v = ( t.value || '' ).trim();
				if ( valid( v ) ) { send( v ); }
			}
			document.addEventListener( 'change', fromEvent, true );
			document.addEventListener( 'focusout', fromEvent, true );
			document.addEventListener( 'input', function( e ){
				if ( ! isEmailField( e.target ) ) { return; }
				if ( timer ) { clearTimeout( timer ); }
				timer = setTimeout( function(){ fromEvent( e ); }, 900 );
			}, true );
		})();
		</script>
		<?php
	}

	public function handle_links(): void {
		global $wpdb;
		$table = $this->table();

		if ( isset( $_GET['nura_recover'] ) && '' !== $_GET['nura_recover'] ) {
			$token = sanitize_text_field( wp_unslash( $_GET['nura_recover'] ) );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token ) );
			if ( $row && function_exists( 'WC' ) && WC()->cart ) {
				$items = json_decode( (string) $row->cart, true );
				if ( is_array( $items ) ) {
					WC()->cart->empty_cart();
					foreach ( $items as $it ) {
						$pid = isset( $it['id'] ) ? (int) $it['id'] : 0;
						$qty = isset( $it['qty'] ) ? (int) $it['qty'] : 1;
						if ( $pid > 0 ) {
							WC()->cart->add_to_cart( $pid, max( 1, $qty ) );
						}
					}
				}
				if ( 'completed' !== $row->status ) {
					$wpdb->update( $table, array( 'status' => 'recovered', 'mailed_stage' => 3 ), array( 'id' => (int) $row->id ) );
				}
				wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' ) );
				exit;
			}
		}
	}

	public function complete_by_order( $order_id ): void {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$email = $order->get_billing_email();
		if ( empty( $email ) ) {
			return;
		}
		global $wpdb;
		$table = $this->table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'completed', recovered_at = %s WHERE email = %s AND status IN ('pending','recovered')",
				current_time( 'mysql' ),
				$email
			)
		);
	}

	public function process(): void {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		global $wpdb;
		$table  = $this->table();
		$delays = array( 1 => (int) $s['delay1'], 2 => (int) $s['delay2'], 3 => (int) $s['delay3'] );
		$rows   = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'pending' AND mailed_stage < 3 AND email <> '' ORDER BY updated_at ASC LIMIT 25" );
		if ( empty( $rows ) ) {
			return;
		}
		$now = (int) current_time( 'timestamp' );
		foreach ( $rows as $row ) {
			$next = ( (int) $row->mailed_stage ) + 1;
			if ( $next < 1 || $next > 3 ) {
				continue;
			}
			$due_after = isset( $delays[ $next ] ) ? $delays[ $next ] : 0;
			$updated   = strtotime( (string) $row->updated_at );
			$age_min   = ( $now - $updated ) / 60;
			if ( $age_min < $due_after ) {
				continue;
			}
			// Handled = delivered on at least one channel OR intentionally skipped
			// for missing consent, so we never rescan the same stage forever.
			if ( $this->send_stage( $row, $next, $s ) ) {
				$wpdb->update( $table, array( 'mailed_stage' => $next ), array( 'id' => (int) $row->id ) );
			}
		}
	}

	private function send_stage( $row, int $stage, array $s ): bool {
		$email = sanitize_email( (string) $row->email );
		if ( ! is_email( $email ) ) {
			return true; // Unusable row; advance so it is not retried.
		}
		$user_id = (int) $row->user_id;
		$channel = (string) $s['channel'];
		$use_em  = ( 'email' === $channel || 'both' === $channel );
		$use_wa  = ( 'whatsapp' === $channel || 'both' === $channel );

		$items   = json_decode( (string) $row->cart, true );
		$items   = is_array( $items ) ? $items : array();
		$recover = esc_url_raw( add_query_arg( 'nura_recover', (string) $row->token, home_url( '/' ) ) );
		$unsub   = class_exists( 'NURAX_Consent' ) ? NURAX_Consent::unsub_url( $email ) : esc_url_raw( home_url( '/' ) );

		$handled = false;

		// Email channel.
		if ( $use_em ) {
			$consent_ok = ! ( class_exists( 'NURAX_Consent' ) && NURAX_Consent::gate_active( 'email' ) ) || ( class_exists( 'NURAX_Consent' ) && NURAX_Consent::has_consent( $email, $user_id ) );
			if ( ! $consent_ok ) {
				$handled = true; // Opted out: do not email, do not retry.
			} else {
				$subject   = $this->subject_for( $stage, $s );
				$html      = $this->email_html( $row, $items, $stage, $s, $recover, $unsub );
				$from_name = $s['from_name'] ? $s['from_name'] : get_bloginfo( 'name' );
				$reply     = $s['reply_to'] ? $s['reply_to'] : get_option( 'admin_email' );
				$headers   = array(
					'Content-Type: text/html; charset=UTF-8',
					'From: ' . $from_name . ' <' . get_option( 'admin_email' ) . '>',
					'Reply-To: ' . $reply,
				);
				if ( wp_mail( $email, $subject, $html, $headers ) ) {
					$handled = true;
				}
			}
		}

		// WhatsApp channel (only when a phone is known and the sender is configured).
		if ( $use_wa && class_exists( 'NURAX_WhatsApp' ) && NURAX_WhatsApp::is_configured() ) {
			$phone = preg_replace( '/[^0-9]/', '', (string) $row->phone );
			if ( '' !== $phone ) {
				$consent_ok = ! ( class_exists( 'NURAX_Consent' ) && NURAX_Consent::gate_active( 'whatsapp' ) ) || ( class_exists( 'NURAX_Consent' ) && NURAX_Consent::has_consent( $email, $user_id ) );
				if ( ! $consent_ok ) {
					$handled = true;
				} else {
					$sent_wa = false;
					if ( '' !== (string) $s['wa_template'] ) {
						// Business-initiated: an approved template delivers outside the 24h window.
						$first   = $row->name ? strtok( (string) $row->name, ' ' ) : '';
						$sent_wa = NURAX_WhatsApp::send_template( $phone, (string) $s['wa_template'], (string) $s['wa_lang'], array( $first, $recover ) );
					} else {
						// In-window free-form text (delivered only if the shopper messaged in the last 24h).
						$sent_wa = NURAX_WhatsApp::send_text( $phone, $this->whatsapp_text( $row, $items, $stage, $s, $recover ) );
					}
					if ( $sent_wa ) {
						$handled = true;
					}
				}
			}
		}

		return $handled;
	}

	private function whatsapp_text( $row, array $items, int $stage, array $s, string $recover ): string {
		$brand = get_bloginfo( 'name' );
		$name  = $row->name ? (string) $row->name : '';
		$first = $name ? ' ' . strtok( $name, ' ' ) : '';
		$lead  = ( $stage >= 3 )
			? sprintf( 'a last reminder about the items in your %s cart.', $brand )
			: sprintf( 'the pieces you left in your %s cart are still waiting for you.', $brand );
		$code = ( $stage >= 2 && $s['discount_code'] ) ? ' Use code ' . $s['discount_code'] . ' at checkout.' : '';
		return trim( 'Hello' . $first . ', ' . $lead . $code . ' Pick up where you left off: ' . $recover );
	}

	private function subject_for( int $stage, array $s ): string {
		$brand = get_bloginfo( 'name' );
		if ( 2 === $stage ) {
			return $s['discount_code'] ? 'A little something to complete your order' : 'Still thinking it over?';
		}
		if ( 3 === $stage ) {
			return 'Last reminder about your ' . $brand . ' cart';
		}
		return 'You left something beautiful in your cart';
	}

	private function recommendations( array $exclude_ids ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 8,
				'orderby' => 'popularity',
				'order'   => 'DESC',
				'exclude' => array_map( 'intval', $exclude_ids ),
			)
		);
		$out = array();
		foreach ( $products as $p ) {
			$out[] = $p;
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	private function email_html( $row, array $items, int $stage, array $s, string $recover, string $unsub ): string {
		$brand  = esc_html( get_bloginfo( 'name' ) );
		$accent = '#b76e79';
		$ink    = '#2b2b2b';
		$muted  = '#8a8a8a';
		$name   = $row->name ? esc_html( (string) $row->name ) : 'there';

		$ids       = array();
		$rows_html = '';
		foreach ( $items as $it ) {
			$pid    = isset( $it['id'] ) ? (int) $it['id'] : 0;
			$ids[]  = $pid;
			$pname  = isset( $it['name'] ) ? esc_html( (string) $it['name'] ) : '';
			$qty    = isset( $it['qty'] ) ? (int) $it['qty'] : 1;
			$thumb  = '';
			$price  = '';
			if ( function_exists( 'wc_get_product' ) ) {
				$p = wc_get_product( $pid );
				if ( $p ) {
					$thumb = $p->get_image( array( 64, 64 ) );
					$price = function_exists( 'wc_price' ) ? wc_price( $p->get_price() ) : '';
				}
			}
			$rows_html .= '<tr>'
				. '<td style="padding:8px 10px;vertical-align:middle;width:72px;">' . $thumb . '</td>'
				. '<td style="padding:8px 10px;vertical-align:middle;color:' . $ink . ';font-size:15px;">' . $pname . ' <span style="color:' . $muted . ';">x ' . $qty . '</span></td>'
				. '<td style="padding:8px 10px;vertical-align:middle;text-align:right;color:' . $ink . ';font-size:15px;">' . $price . '</td>'
				. '</tr>';
		}

		if ( 2 === $stage ) {
			$lead = 'We saved your cart. If something held you back, just reply to this email and our stylists will help. Here is a little nudge to make it easier.';
		} elseif ( 3 === $stage ) {
			$lead = 'This is the last reminder for the pieces you picked. Our most-loved styles sell quickly, so we wanted to give you one more chance to complete your order.';
		} else {
			$lead = 'You left a few beautiful pieces in your cart at ' . get_bloginfo( 'name' ) . '. Whenever you are ready, you can pick up right where you left off.';
		}

		$discount_html = '';
		if ( $stage >= 2 && $s['discount_code'] ) {
			$code          = esc_html( $s['discount_code'] );
			$discount_html = '<div style="margin:18px 0;padding:14px 16px;border:1px dashed ' . $accent . ';border-radius:10px;background:#faf3f4;text-align:center;">'
				. '<div style="color:' . $muted . ';font-size:13px;text-transform:uppercase;letter-spacing:.08em;">Use this code at checkout</div>'
				. '<div style="color:' . $accent . ';font-size:22px;font-weight:700;margin-top:4px;">' . $code . '</div>'
				. '</div>';
		}

		$recs     = $this->recommendations( $ids );
		$rec_html = '';
		if ( count( $recs ) > 0 ) {
			$cards = '';
			foreach ( $recs as $p ) {
				$link   = esc_url( get_permalink( $p->get_id() ) );
				$img    = $p->get_image( array( 120, 120 ) );
				$t      = esc_html( $p->get_name() );
				$pr     = function_exists( 'wc_price' ) ? wc_price( $p->get_price() ) : '';
				$cards .= '<td style="width:33%;padding:8px;vertical-align:top;text-align:center;">'
					. '<a href="' . $link . '" style="text-decoration:none;color:' . $ink . ';">'
					. '<div>' . $img . '</div>'
					. '<div style="font-size:13px;margin-top:6px;">' . $t . '</div>'
					. '<div style="font-size:13px;color:' . $accent . ';margin-top:2px;">' . $pr . '</div>'
					. '</a></td>';
			}
			$rec_html = '<div style="margin-top:26px;">'
				. '<div style="font-size:15px;font-weight:700;color:' . $ink . ';margin-bottom:6px;">You may also love</div>'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' . $cards . '</tr></table>'
				. '</div>';
		}

		$wa      = preg_replace( '/[^0-9]/', '', (string) $s['whatsapp_handoff'] );
		$wa_html = $wa ? '<a href="https://wa.me/' . esc_attr( $wa ) . '" style="color:' . $accent . ';">WhatsApp us</a>' : '';

		$btn = '<a href="' . $recover . '" style="display:inline-block;background:' . $accent . ';color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:10px;font-size:16px;font-weight:700;">Complete your order</a>';

		$help = ( $stage >= 2 )
			? '<p style="color:' . $muted . ';font-size:14px;">Not sure which style is right for you? Reply to this email' . ( $wa_html ? ' or ' . $wa_html : '' ) . ' and our stylists will guide you.</p>'
			: '';

		$html = '<div style="background:#f7f4f2;padding:24px 0;font-family:Arial,Helvetica,sans-serif;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #ece5e2;">'
			. '<tr><td style="background:' . $accent . ';padding:18px 24px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.02em;">' . $brand . '</td></tr>'
			. '<tr><td style="padding:24px;">'
			. '<p style="font-size:17px;color:' . $ink . ';margin:0 0 6px;">Hello ' . $name . ',</p>'
			. '<p style="font-size:15px;color:' . $muted . ';margin:0 0 16px;line-height:1.55;">' . esc_html( $lead ) . '</p>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #f0eae7;border-radius:10px;">' . $rows_html . '</table>'
			. $discount_html
			. '<div style="text-align:center;margin:22px 0;">' . $btn . '</div>'
			. $help
			. $rec_html
			. '</td></tr>'
			. '<tr><td style="padding:16px 24px;background:#f5efec;color:' . $muted . ';font-size:12px;line-height:1.5;">'
			. '<p style="margin:0;">You are receiving this because you started an order at ' . $brand . '. If you would prefer not to receive cart reminders, you can <a href="' . $unsub . '" style="color:' . $muted . ';">unsubscribe</a>.</p>'
			. '</td></tr>'
			. '</table></td></tr></table></div>';

		return $html;
	}

	public function handle_test_send(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_cart_test' );

		$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		$stage = isset( $_POST['test_stage'] ) ? (int) $_POST['test_stage'] : 1;
		if ( $stage < 1 || $stage > 3 ) {
			$stage = 1;
		}

		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 1" );
		if ( ! $row ) {
			$row = (object) array(
				'email'        => $to,
				'phone'        => '',
				'name'         => '',
				'cart'         => wp_json_encode( $this->sample_items() ),
				'subtotal'     => 0,
				'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'token'        => 'test-' . wp_generate_password( 12, false, false ),
				'status'       => 'pending',
				'mailed_stage' => 0,
				'user_id'      => 0,
			);
		}
		$row->email = $to;

		// Test send bypasses timing and consent gating so you can preview design.
		$items     = json_decode( (string) $row->cart, true );
		$items     = is_array( $items ) ? $items : $this->sample_items();
		$recover   = esc_url_raw( add_query_arg( 'nura_recover', (string) $row->token, home_url( '/' ) ) );
		$unsub     = class_exists( 'NURAX_Consent' ) ? NURAX_Consent::unsub_url( $to ) : esc_url_raw( home_url( '/' ) );
		$s         = $this->settings();
		$subject   = $this->subject_for( $stage, $s );
		$html      = $this->email_html( $row, $items, $stage, $s, $recover, $unsub );
		$from_name = $s['from_name'] ? $s['from_name'] : get_bloginfo( 'name' );
		$headers   = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . get_option( 'admin_email' ) . '>',
		);
		$ok = wp_mail( $to, $subject, $html, $headers );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'nurax-cart-recovery', 'nurax_test' => $ok ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function sample_items(): array {
		$items = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products( array( 'status' => 'publish', 'limit' => 2 ) );
			foreach ( $products as $p ) {
				$items[] = array(
					'id'    => $p->get_id(),
					'qty'   => 1,
					'name'  => $p->get_name(),
					'price' => (float) $p->get_price(),
				);
			}
		}
		if ( empty( $items ) ) {
			$items[] = array( 'id' => 0, 'qty' => 1, 'name' => 'Sample luxury wig', 'price' => 0 );
		}
		return $items;
	}

	public function admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'NURA Cart Recovery', 'nura-experience' ),
			__( 'NURA Cart Recovery', 'nura-experience' ),
			'manage_woocommerce',
			'nurax-cart-recovery',
			array( $this, 'render_admin' )
		);
	}

	public function register_settings(): void {
		register_setting( 'nurax_cart_recovery_group', self::OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out                    = $this->defaults();
		$out['enabled']         = empty( $input['enabled'] ) ? 0 : 1;
		$channel                = isset( $input['channel'] ) ? sanitize_key( (string) $input['channel'] ) : 'email';
		$out['channel']         = in_array( $channel, array( 'email', 'whatsapp', 'both' ), true ) ? $channel : 'email';
		$out['delay1']          = max( 5, (int) ( $input['delay1'] ?? 60 ) );
		$out['delay2']          = max( 10, (int) ( $input['delay2'] ?? 1440 ) );
		$out['delay3']          = max( 15, (int) ( $input['delay3'] ?? 4320 ) );
		$out['discount_code']   = sanitize_text_field( (string) ( $input['discount_code'] ?? '' ) );
		$out['from_name']       = sanitize_text_field( (string) ( $input['from_name'] ?? '' ) );
		$out['reply_to']        = sanitize_email( (string) ( $input['reply_to'] ?? '' ) );
		$out['whatsapp_handoff'] = preg_replace( '/[^0-9]/', '', (string) ( $input['whatsapp_handoff'] ?? '' ) );
		$out['wa_template']      = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $input['wa_template'] ?? '' ) ) );
		$out['wa_lang']          = preg_replace( '/[^A-Za-z_]/', '', (string) ( $input['wa_lang'] ?? 'en_US' ) );
		if ( '' === $out['wa_lang'] ) {
			$out['wa_lang'] = 'en_US';
		}
		return $out;
	}

	public function render_admin(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		global $wpdb;
		$table     = $this->table();
		$s         = $this->settings();
		$opt       = self::OPTION;
		$rows      = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 50" );
		$pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
		$next_cron = wp_next_scheduled( self::CRON_HOOK );
		$next_txt  = $next_cron ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $next_cron ), 'Y-m-d H:i' ) : 'not scheduled';
		$test_flag = isset( $_GET['nurax_test'] ) ? sanitize_text_field( wp_unslash( $_GET['nurax_test'] ) ) : '';
		$wa_ready  = class_exists( 'NURAX_WhatsApp' ) && NURAX_WhatsApp::is_configured();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA Abandoned Cart Recovery', 'nura-experience' ); ?></h1>
			<p><?php echo esc_html( sprintf( 'Pending carts: %d. Next scheduled run: %s.', $pending, $next_txt ) ); ?></p>
			<p class="description"><?php esc_html_e( 'Reminders respect the NURA marketing-consent settings. WhatsApp reminders also require the WhatsApp Cloud API to be configured and a captured phone number, and must comply with WhatsApp\'s messaging window / template rules.', 'nura-experience' ); ?>
			<?php echo $wa_ready ? '' : '<br><strong>' . esc_html__( 'WhatsApp sending is not configured yet.', 'nura-experience' ) . '</strong>'; ?>
			</p>
			<?php if ( '' !== $test_flag ) : ?>
				<div class="notice notice-<?php echo ( '1' === $test_flag ) ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( ( '1' === $test_flag ) ? 'Test email handed to the mailer. Check the inbox.' : 'Test email could not be sent. Check your email/SMTP setup.' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'nurax_cart_recovery_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable reminders', 'nura-experience' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( 1, (int) $s['enabled'] ); ?> /> <?php esc_html_e( 'Send the abandoned-cart reminder sequence', 'nura-experience' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Channel', 'nura-experience' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[channel]">
								<option value="email" <?php selected( $s['channel'], 'email' ); ?>><?php esc_html_e( 'Email only', 'nura-experience' ); ?></option>
								<option value="whatsapp" <?php selected( $s['channel'], 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp only', 'nura-experience' ); ?></option>
								<option value="both" <?php selected( $s['channel'], 'both' ); ?>><?php esc_html_e( 'Email and WhatsApp', 'nura-experience' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email 1 delay (minutes)', 'nura-experience' ); ?></th>
						<td><input type="number" min="5" name="<?php echo esc_attr( $opt ); ?>[delay1]" value="<?php echo esc_attr( (int) $s['delay1'] ); ?>" /> <span class="description"><?php esc_html_e( 'e.g. 60 = 1 hour after the cart is abandoned', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email 2 delay (minutes)', 'nura-experience' ); ?></th>
						<td><input type="number" min="10" name="<?php echo esc_attr( $opt ); ?>[delay2]" value="<?php echo esc_attr( (int) $s['delay2'] ); ?>" /> <span class="description"><?php esc_html_e( 'e.g. 1440 = 24 hours', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email 3 delay (minutes)', 'nura-experience' ); ?></th>
						<td><input type="number" min="15" name="<?php echo esc_attr( $opt ); ?>[delay3]" value="<?php echo esc_attr( (int) $s['delay3'] ); ?>" /> <span class="description"><?php esc_html_e( 'e.g. 4320 = 72 hours', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Discount code (optional)', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[discount_code]" value="<?php echo esc_attr( (string) $s['discount_code'] ); ?>" /> <span class="description"><?php esc_html_e( 'Shown from reminder 2 onward. Create the matching coupon in WooCommerce.', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'From name', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[from_name]" value="<?php echo esc_attr( (string) $s['from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reply-to email', 'nura-experience' ); ?></th>
						<td><input type="email" name="<?php echo esc_attr( $opt ); ?>[reply_to]" value="<?php echo esc_attr( (string) $s['reply_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WhatsApp handoff number', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[whatsapp_handoff]" value="<?php echo esc_attr( (string) $s['whatsapp_handoff'] ); ?>" /> <span class="description"><?php esc_html_e( 'Digits only, incl. country code, e.g. 254714994898. Shown as a "WhatsApp us" link in emails.', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WhatsApp template name', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[wa_template]" value="<?php echo esc_attr( (string) $s['wa_template'] ); ?>" /> <span class="description"><?php esc_html_e( 'Approved Meta template name. Required to send WhatsApp reminders outside WhatsApp\'s 24-hour window. Build the template with two body variables: {{1}} = first name, {{2}} = the recover-cart link. Leave blank to send free-form text (delivered only if the shopper messaged you in the last 24h).', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WhatsApp template language', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[wa_lang]" value="<?php echo esc_attr( (string) $s['wa_lang'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( 'Template language code, e.g. en_US.', 'nura-experience' ); ?></span></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Send a test email', 'nura-experience' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Sends a real, immediate copy of the chosen stage (using the latest captured cart, or a sample). Bypasses timing and consent gating so you can confirm delivery and design.', 'nura-experience' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nurax_cart_test" />
				<?php wp_nonce_field( 'nurax_cart_test' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Send to', 'nura-experience' ); ?></th>
						<td><input type="email" name="test_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Which email', 'nura-experience' ); ?></th>
						<td>
							<select name="test_stage">
								<option value="1"><?php esc_html_e( 'Reminder 1', 'nura-experience' ); ?></option>
								<option value="2"><?php esc_html_e( 'Reminder 2 - nudge + discount', 'nura-experience' ); ?></option>
								<option value="3"><?php esc_html_e( 'Reminder 3 - last chance', 'nura-experience' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Send test email', 'nura-experience' ), 'secondary' ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent captured carts', 'nura-experience' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'nura-experience' ); ?></th>
						<th><?php esc_html_e( 'Items', 'nura-experience' ); ?></th>
						<th><?php esc_html_e( 'Subtotal', 'nura-experience' ); ?></th>
						<th><?php esc_html_e( 'Reminders sent', 'nura-experience' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nura-experience' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'nura-experience' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $rows ) : ?>
					<?php foreach ( $rows as $r ) : ?>
						<?php
						$decoded = json_decode( (string) $r->cart, true );
						$count   = is_array( $decoded ) ? count( $decoded ) : 0;
						?>
						<tr>
							<td><?php echo esc_html( (string) $r->email ); ?></td>
							<td><?php echo esc_html( (string) $count ); ?></td>
							<td><?php echo esc_html( trim( (string) $r->currency . ' ' . number_format( (float) $r->subtotal, 2 ) ) ); ?></td>
							<td><?php echo esc_html( (string) $r->mailed_stage ); ?></td>
							<td><?php echo esc_html( (string) $r->status ); ?></td>
							<td><?php echo esc_html( (string) $r->updated_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No carts captured yet.', 'nura-experience' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
