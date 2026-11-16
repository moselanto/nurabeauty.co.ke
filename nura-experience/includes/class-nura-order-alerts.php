<?php
/**
 * NURA Desk - order alerts to the shop's WhatsApp (NURA Experience 1.114.0; 1.114.1 set-up check).
 *
 * Every new order (website checkout, WhatsApp pop-up lead, optionally till
 * sales) sends a short alert to the shop WhatsApp number, so an order is never
 * missed when the customer does not press Send in WhatsApp.
 *
 * Channels, tried in order (NURA Desk > Order alerts):
 *   1. Meta WhatsApp Cloud API (official) - only when NURAX_WhatsApp is
 *      configured AND an approved template name is saved. Sent from the Cloud
 *      API number (a second number), to the shop number.
 *   2. CallMeBot (free, unofficial) - the shop number activates it once and
 *      the API key is saved here (or NURAX_CALLMEBOT_KEY constant / env).
 *   3. E-mail fallback to the shop e-mail when every WhatsApp channel fails.
 *
 * Sending runs in WP-Cron a few seconds after the order is saved, so checkout
 * and the WhatsApp pop-up are never slowed down. Each order alerts once
 * (meta flag), retries once after 5 minutes on failure, and the last 20
 * results are shown on the settings page.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Order_Alerts {

	const OPTION = 'nurax_order_alerts';
	const LOG    = 'nurax_order_alerts_log';
	const PAGE   = 'nurax-order-alerts';
	const HOOK   = 'nurax_order_alert_send';

	public static function hooks() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_wc_order' ), 20, 1 );
		add_action( 'nurax_wa_order_saved', array( __CLASS__, 'on_wa_order' ), 20, 1 );
		add_action( self::HOOK, array( __CLASS__, 'send' ), 10, 3 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_nurax_order_alerts_save', array( __CLASS__, 'h_save' ) );
		add_action( 'admin_post_nurax_order_alerts_test', array( __CLASS__, 'h_test' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return array_merge( array(
			'enabled'   => 1,
			'phone'     => '254714994898',
			'cmb_key'   => '',
			'template'  => '',
			'lang'      => 'en',
			'till'      => 1,
			'email'     => 1,
		), is_array( $s ) ? $s : array() );
	}

	/** CallMeBot key: env, then wp-config constant, then the password field. */
	private static function cmb_key() {
		$env = getenv( 'NURAX_CALLMEBOT_KEY' );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}
		if ( defined( 'NURAX_CALLMEBOT_KEY' ) && '' !== trim( (string) NURAX_CALLMEBOT_KEY ) ) {
			return trim( (string) NURAX_CALLMEBOT_KEY );
		}
		$s = self::settings();
		return trim( (string) $s['cmb_key'] );
	}

	private static function meta_ready() {
		$s = self::settings();
		return '' !== trim( (string) $s['template'] ) && class_exists( 'NURAX_WhatsApp' ) && NURAX_WhatsApp::is_configured();
	}

	/* ------------------------------------------------------------------ */
	/* Triggers                                                            */
	/* ------------------------------------------------------------------ */

	public static function on_wc_order( $order_id ) {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		self::queue( 'wc', (int) $order_id );
	}

	public static function on_wa_order( $post_id ) {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		self::queue( 'wa', (int) $post_id );
	}

	private static function queue( $type, $id, $attempt = 1, $delay = 15 ) {
		if ( ! $id ) {
			return;
		}
		$args = array( $type, $id, $attempt );
		if ( ! wp_next_scheduled( self::HOOK, $args ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK, $args );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Message                                                             */
	/* ------------------------------------------------------------------ */

	private static function money( $v ) {
		return 'KSh ' . number_format( (float) $v, 0 );
	}

	/** Returns array( ref, customer, items, total, payment, link ) or null to skip. */
	private static function details( $type, $id ) {
		$s = self::settings();
		if ( 'wa' === $type ) {
			if ( ! class_exists( 'NURAX_Desk_Orders' ) || NURAX_Desk_Orders::CPT !== get_post_type( $id ) ) {
				return null;
			}
			$d     = NURAX_Desk_Orders::data( $id );
			$items = array();
			foreach ( (array) $d['items'] as $it ) {
				$items[] = ( isset( $it['name'] ) ? $it['name'] : 'Item' ) . ( ! empty( $it['option'] ) ? ' (' . $it['option'] . ')' : '' ) . ' x' . ( isset( $it['qty'] ) ? (int) $it['qty'] : 1 );
			}
			$total = (float) $d['total'] + ( '' !== (string) $d['ship'] ? (float) $d['ship'] : 0 );
			return array(
				'kind'     => 'WhatsApp pop-up order',
				'ref'      => $d['ref'] ? $d['ref'] : 'WA-' . $id,
				'customer' => trim( $d['name'] . ( $d['phone'] ? ' +' . $d['phone'] : '' ) ),
				'items'    => implode( ', ', $items ),
				'total'    => self::money( $total ),
				'payment'  => trim( $d['payment'] . ( $d['mpesa_code'] ? ' - code ' . $d['mpesa_code'] : '' ) ),
				'place'    => trim( ( 'pickup' === $d['fulfil'] ? 'Pick-up' : 'Delivery' ) . ( $d['delivery'] ? ': ' . $d['delivery'] : '' ) ),
				'link'     => admin_url( 'admin.php?page=nurax-orders&order=' . $id ),
			);
		}
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$o = wc_get_order( $id );
		if ( ! $o || 'shop_order_refund' === $o->get_type() ) {
			return null;
		}
		if ( $o->get_meta( '_nura_wa_order' ) ) {
			return null; // A WhatsApp order billed at the till - already alerted as a WhatsApp order.
		}
		$till = 'nura_pos' === $o->get_created_via();
		if ( $till && empty( $s['till'] ) ) {
			return null;
		}
		if ( in_array( $o->get_status(), array( 'failed', 'cancelled', 'checkout-draft' ), true ) ) {
			return null;
		}
		$items = array();
		foreach ( $o->get_items() as $it ) {
			$items[] = $it->get_name() . ' x' . (int) $it->get_quantity();
		}
		$name = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
		$ph   = $o->get_billing_phone();
		$addr = trim( implode( ', ', array_filter( array( $o->get_shipping_city() ? $o->get_shipping_city() : $o->get_billing_city(), $o->get_shipping_state() ? $o->get_shipping_state() : $o->get_billing_state() ) ) ) );
		return array(
			'kind'     => $till ? 'Till sale' : 'Website order',
			'ref'      => '#' . $o->get_order_number(),
			'customer' => trim( ( $name ? $name : 'Customer' ) . ( $ph ? ' ' . $ph : '' ) ),
			'items'    => implode( ', ', $items ),
			'total'    => self::money( $o->get_total() ),
			'payment'  => trim( $o->get_payment_method_title() . ( $o->is_paid() ? ' (paid)' : ' (not paid yet)' ) ),
			'place'    => $addr ? 'Deliver to: ' . $addr : '',
			'link'     => $o->get_edit_order_url(),
		);
	}

	private static function text( $x ) {
		$t  = 'NEW ORDER - NURA Beauty' . "\n";
		$t .= $x['kind'] . ' ' . $x['ref'] . "\n";
		$t .= 'Customer: ' . $x['customer'] . "\n";
		$t .= 'Items: ' . $x['items'] . "\n";
		$t .= 'Total: ' . $x['total'] . "\n";
		if ( $x['payment'] ) {
			$t .= 'Payment: ' . $x['payment'] . "\n";
		}
		if ( $x['place'] ) {
			$t .= $x['place'] . "\n";
		}
		$t .= 'Open: ' . $x['link'];
		return function_exists( 'mb_substr' ) ? mb_substr( $t, 0, 1000 ) : substr( $t, 0, 1000 );
	}

	/* ------------------------------------------------------------------ */
	/* Sending                                                             */
	/* ------------------------------------------------------------------ */

	public static function send( $type, $id, $attempt = 1 ) {
		$type = 'wa' === $type ? 'wa' : 'wc';
		$id   = (int) $id;
		$flag = '_nura_alert_sent';
		$done = 'wa' === $type ? get_post_meta( $id, $flag, true ) : ( function_exists( 'wc_get_order' ) && wc_get_order( $id ) ? wc_get_order( $id )->get_meta( $flag ) : '' );
		if ( $done ) {
			return;
		}
		$x = self::details( $type, $id );
		if ( ! $x ) {
			return;
		}
		$res = self::deliver( $x );
		if ( $res['ok'] ) {
			if ( 'wa' === $type ) {
				update_post_meta( $id, $flag, $res['via'] );
			} else {
				$o = wc_get_order( $id );
				$o->update_meta_data( $flag, $res['via'] );
				$o->save_meta_data();
			}
		} elseif ( $attempt < 2 ) {
			self::queue( $type, $id, $attempt + 1, 300 );
		} else {
			self::email( $x, $res['err'] );
		}
		self::log( $x['kind'] . ' ' . $x['ref'], $res['ok'] ? 'Sent via ' . $res['via'] : 'Failed (attempt ' . (int) $attempt . '): ' . $res['err'] );
	}

	/** Try Meta, then CallMeBot. */
	private static function deliver( $x ) {
		$s    = self::settings();
		$to   = class_exists( 'NURAX_Desk' ) ? NURAX_Desk::phone( $s['phone'] ) : preg_replace( '/\D/', '', (string) $s['phone'] );
		$errs = array();
		if ( '' === $to ) {
			return array( 'ok' => false, 'via' => '', 'err' => 'No shop WhatsApp number saved.' );
		}
		if ( self::meta_ready() ) {
			$ok = NURAX_WhatsApp::send_template( $to, (string) $s['template'], (string) $s['lang'], array( $x['kind'] . ' ' . $x['ref'], $x['customer'], $x['items'], $x['total'] ) );
			if ( $ok ) {
				return array( 'ok' => true, 'via' => 'Meta Cloud API', 'err' => '' );
			}
			$errs[] = 'Meta: ' . NURAX_WhatsApp::last_error();
		}
		$key = self::cmb_key();
		if ( '' !== $key ) {
			$url = add_query_arg( array( 'phone' => '+' . $to, 'text' => rawurlencode( self::text( $x ) ), 'apikey' => rawurlencode( $key ) ), 'https://api.callmebot.com/whatsapp.php' );
			$r   = wp_remote_get( $url, array( 'timeout' => 20 ) );
			if ( is_wp_error( $r ) ) {
				$errs[] = 'CallMeBot: ' . $r->get_error_message();
			} else {
				$code = (int) wp_remote_retrieve_response_code( $r );
				$body = wp_strip_all_tags( (string) wp_remote_retrieve_body( $r ) );
				if ( 200 === $code && false === stripos( $body, 'error' ) && false === stripos( $body, 'apikey is invalid' ) ) {
					return array( 'ok' => true, 'via' => 'CallMeBot', 'err' => '' );
				}
				$errs[] = 'CallMeBot HTTP ' . $code . ': ' . substr( trim( preg_replace( '/\s+/', ' ', $body ) ), 0, 160 );
			}
		}
		if ( ! $errs ) {
			$errs[] = 'No WhatsApp channel set up (save a CallMeBot key or a Meta template).';
		}
		return array( 'ok' => false, 'via' => '', 'err' => implode( ' | ', $errs ) );
	}

	private static function email( $x, $err ) {
		$s = self::settings();
		if ( empty( $s['email'] ) ) {
			return;
		}
		wp_mail( get_option( 'admin_email' ), 'New order ' . $x['ref'] . ' (WhatsApp alert failed)', self::text( $x ) . "\n\nWhatsApp alert error: " . $err );
	}

	private static function log( $what, $msg ) {
		$l = get_option( self::LOG, array() );
		$l = is_array( $l ) ? $l : array();
		array_unshift( $l, array( current_time( 'mysql' ), $what, $msg ) );
		update_option( self::LOG, array_slice( $l, 0, 20 ), false );
	}

	/* ------------------------------------------------------------------ */
	/* Admin page                                                          */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		if ( class_exists( 'NURAX_Desk' ) ) {
			add_submenu_page( NURAX_Desk::MENU, 'Order alerts', 'Order alerts', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
		}
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::settings();
		$const = ( defined( 'NURAX_CALLMEBOT_KEY' ) && '' !== trim( (string) NURAX_CALLMEBOT_KEY ) ) || '' !== trim( (string) getenv( 'NURAX_CALLMEBOT_KEY' ) );
		$msg   = isset( $_GET['nura_msg'] ) ? sanitize_key( wp_unslash( $_GET['nura_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>Order alerts to WhatsApp</h1>';
		if ( 'saved' === $msg ) {
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		} elseif ( 'test' === $msg ) {
			echo '<div class="notice notice-info"><p>Test sent - see the result in Recent alerts below and check WhatsApp.</p></div>';
		}
		echo '<p>Every new website order, WhatsApp pop-up order' . ( ! empty( $s['till'] ) ? ' and till sale' : '' ) . ' sends a short alert to the shop WhatsApp number, even if the customer never presses Send in WhatsApp.</p>';
		echo '<p><strong>Channels in use:</strong> Meta Cloud API ' . ( self::meta_ready() ? '<span style="color:#00a32a">ready</span>' : '<span style="color:#996800">not set up</span>' ) . ' &middot; CallMeBot ' . ( '' !== self::cmb_key() ? '<span style="color:#00a32a">key saved</span>' : '<span style="color:#996800">no key</span>' ) . ' &middot; E-mail fallback ' . ( ! empty( $s['email'] ) ? 'on (' . esc_html( get_option( 'admin_email' ) ) . ')' : 'off' ) . '</p>';
		// 1.114.1: show what the site can see for Meta, never the values.
		$tok = class_exists( 'NURAX_WhatsApp' ) && '' !== NURAX_WhatsApp::token();
		$pid = class_exists( 'NURAX_WhatsApp' ) ? NURAX_WhatsApp::phone_id() : '';
		$yes = '<span style="color:#00a32a">detected</span>';
		$no  = '<span style="color:#b32d2e">not detected</span>';
		echo '<p><strong>Meta set-up check:</strong> WhatsApp token (WHATSAPP_TOKEN) ' . ( $tok ? $yes : $no ) . ' &middot; Phone number ID (WHATSAPP_PHONE_NUMBER_ID) ' . ( '' !== $pid ? $yes . ( ctype_digit( $pid ) ? '' : ' <span style="color:#b32d2e">(should be digits only)</span>' ) : $no ) . ' &middot; Template name ' . ( '' !== trim( (string) $s['template'] ) ? $yes : $no ) . '</p>';
		$merr = class_exists( 'NURAX_WhatsApp' ) ? NURAX_WhatsApp::last_error() : '';
		if ( '' !== $merr ) {
			echo '<p class="description">Last Meta API error: ' . esc_html( substr( $merr, 0, 300 ) ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nurax_order_alerts_save">';
		wp_nonce_field( 'nurax_order_alerts_save' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th>Alerts</th><td><label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $s['enabled'] ), true, false ) . '> Send order alerts</label></td></tr>';
		echo '<tr><th><label for="noa-phone">Shop WhatsApp number</label></th><td><input id="noa-phone" type="text" name="phone" class="regular-text" value="' . esc_attr( $s['phone'] ) . '"><p class="description">The number that receives alerts, e.g. 254714994898.</p></td></tr>';
		echo '<tr><th><label for="noa-key">CallMeBot API key</label></th><td>' . ( $const ? '<em>Set in wp-config.php / server environment.</em>' : '<input id="noa-key" type="password" name="cmb_key" class="regular-text" autocomplete="off" placeholder="' . ( '' !== $s['cmb_key'] ? 'Saved - leave blank to keep' : '' ) . '"><label style="margin-left:8px"><input type="checkbox" name="cmb_clear" value="1"> Remove saved key</label>' ) . '<p class="description">One-time set-up on the shop phone: save +34 694 29 84 96 in contacts as CallMeBot, send it the WhatsApp message <code>I allow callmebot to send me messages</code>, and paste the API key it replies with here.</p></td></tr>';
		echo '<tr><th><label for="noa-tpl">Meta template name</label></th><td><input id="noa-tpl" type="text" name="template" class="regular-text" value="' . esc_attr( $s['template'] ) . '"> Language <input type="text" name="lang" value="' . esc_attr( $s['lang'] ) . '" style="width:70px"><p class="description">Optional, for the official Meta WhatsApp Cloud API (needs a second number registered with Meta and the token in wp-config). Utility template with 4 body variables: {{1}} order, {{2}} customer, {{3}} items, {{4}} total. When ready, Meta is tried first and CallMeBot becomes the back-up.</p></td></tr>';
		echo '<tr><th>Till sales</th><td><label><input type="checkbox" name="till" value="1"' . checked( ! empty( $s['till'] ), true, false ) . '> Also alert for sales made at the NURA Desk till</label></td></tr>';
		echo '<tr><th>E-mail fallback</th><td><label><input type="checkbox" name="email" value="1"' . checked( ! empty( $s['email'] ), true, false ) . '> E-mail the order to the shop e-mail if WhatsApp fails twice</label></td></tr>';
		echo '</table>';
		submit_button( 'Save alerts' );
		echo '</form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nurax_order_alerts_test">';
		wp_nonce_field( 'nurax_order_alerts_test' );
		submit_button( 'Send a test alert now', 'secondary' );
		echo '</form>';
		$l = get_option( self::LOG, array() );
		echo '<h2>Recent alerts</h2><table class="widefat striped"><thead><tr><th>When</th><th>Order</th><th>Result</th></tr></thead><tbody>';
		if ( ! $l ) {
			echo '<tr><td colspan="3">No alerts yet.</td></tr>';
		}
		foreach ( (array) $l as $r ) {
			echo '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . esc_html( $r[1] ) . '</td><td>' . esc_html( $r[2] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function h_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_order_alerts_save' );
		$s              = self::settings();
		$s['enabled']   = empty( $_POST['enabled'] ) ? 0 : 1;
		$s['till']      = empty( $_POST['till'] ) ? 0 : 1;
		$s['email']     = empty( $_POST['email'] ) ? 0 : 1;
		$ph             = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$s['phone']     = class_exists( 'NURAX_Desk' ) && NURAX_Desk::phone( $ph ) ? NURAX_Desk::phone( $ph ) : $s['phone'];
		$s['template']  = isset( $_POST['template'] ) ? substr( sanitize_key( wp_unslash( $_POST['template'] ) ), 0, 100 ) : '';
		$s['lang']      = isset( $_POST['lang'] ) ? substr( preg_replace( '/[^A-Za-z_]/', '', sanitize_text_field( wp_unslash( $_POST['lang'] ) ) ), 0, 10 ) : 'en';
		$s['lang']      = '' === $s['lang'] ? 'en' : $s['lang'];
		$key            = isset( $_POST['cmb_key'] ) ? substr( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['cmb_key'] ) ) ), 0, 40 ) : '';
		if ( ! empty( $_POST['cmb_clear'] ) ) {
			$s['cmb_key'] = '';
		} elseif ( '' !== $key ) {
			$s['cmb_key'] = $key;
		}
		update_option( self::OPTION, $s, false );
		if ( class_exists( 'NURAX_Staff' ) && method_exists( 'NURAX_Staff', 'log' ) ) {
			NURAX_Staff::log( 'settings', 'Updated order alert settings' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&nura_msg=saved' ) );
		exit;
	}

	public static function h_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_order_alerts_test' );
		$x   = array(
			'kind'     => 'TEST alert',
			'ref'      => 'TEST-' . gmdate( 'His' ),
			'customer' => 'Test customer',
			'items'    => 'Test wig x1',
			'total'    => self::money( 1 ),
			'payment'  => 'Not a real order',
			'place'    => '',
			'link'     => admin_url( 'admin.php?page=nurax-orders' ),
		);
		$res = self::deliver( $x );
		self::log( 'Test alert', $res['ok'] ? 'Sent via ' . $res['via'] : 'Failed: ' . $res['err'] );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&nura_msg=test' ) );
		exit;
	}
}

NURAX_Order_Alerts::hooks();
