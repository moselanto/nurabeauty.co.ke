<?php
/**
 * NURA Desk - Marketing: contacts, segments, e-mail and WhatsApp campaigns
 * (NURA Experience 1.60.0).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Desk_Mkt {

	const CRON = 'nurax_mkt_tick';

	public static function t_c() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_contacts';
	}

	public static function t_k() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_campaigns';
	}

	public static function t_s() {
		global $wpdb;
		return $wpdb->prefix . 'nurax_camp_sends';
	}

	public static function install( $c ) {
		$a = self::t_c();
		$b = self::t_k();
		$d = self::t_s();
		dbDelta( "CREATE TABLE $a (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL DEFAULT '',
  email varchar(120) NOT NULL DEFAULT '',
  phone varchar(20) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sources varchar(120) NOT NULL DEFAULT '',
  tags varchar(255) NOT NULL DEFAULT '',
  orders int(10) unsigned NOT NULL DEFAULT 0,
  spent decimal(12,2) NOT NULL DEFAULT 0,
  last_order datetime DEFAULT NULL,
  email_ok tinyint(1) NOT NULL DEFAULT 1,
  wa_ok tinyint(1) NOT NULL DEFAULT 1,
  consent tinyint(1) NOT NULL DEFAULT 0,
  token varchar(40) NOT NULL DEFAULT '',
  created_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY email (email),
  KEY phone (phone),
  KEY token (token)
) $c;" );
		dbDelta( "CREATE TABLE $b (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(160) NOT NULL DEFAULT '',
  channel varchar(10) NOT NULL DEFAULT 'email',
  subject varchar(200) NOT NULL DEFAULT '',
  preheader varchar(200) NOT NULL DEFAULT '',
  heading varchar(200) NOT NULL DEFAULT '',
  body longtext,
  btn_text varchar(80) NOT NULL DEFAULT '',
  btn_url varchar(255) NOT NULL DEFAULT '',
  image varchar(255) NOT NULL DEFAULT '',
  products varchar(255) NOT NULL DEFAULT '',
  segment varchar(40) NOT NULL DEFAULT 'all',
  seg_value varchar(60) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'draft',
  total int(10) unsigned NOT NULL DEFAULT 0,
  sent int(10) unsigned NOT NULL DEFAULT 0,
  failed int(10) unsigned NOT NULL DEFAULT 0,
  opened int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  sent_at datetime DEFAULT NULL,
  PRIMARY KEY  (id)
) $c;" );
		dbDelta( "CREATE TABLE $d (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(10) NOT NULL DEFAULT 'queued',
  sent_at datetime DEFAULT NULL,
  opened_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY campaign_id (campaign_id),
  KEY contact_id (contact_id)
) $c;" );
	}

	public static function hooks() {
		add_filter( 'cron_schedules', function ( $s ) {
			$s['nurax_5min'] = array( 'interval' => 300, 'display' => 'Every 5 minutes (NURA)' );
			return $s;
		} );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + 300, 'nurax_5min', self::CRON );
			}
		} );
		add_action( self::CRON, array( __CLASS__, 'cron' ) );
		add_action( 'admin_post_nurax_mkt_save', array( __CLASS__, 'h_save' ) );
		add_action( 'admin_post_nurax_mkt_sync', array( __CLASS__, 'h_sync' ) );
		add_action( 'admin_post_nurax_mkt_contact', array( __CLASS__, 'h_contact' ) );
		add_action( 'wp_ajax_nurax_mkt', array( __CLASS__, 'ajax' ) );
		add_action( 'template_redirect', array( __CLASS__, 'public_routes' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'from_order' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'from_order' ) );
		add_action( 'user_register', array( __CLASS__, 'from_user' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Contacts                                                            */
	/* ------------------------------------------------------------------ */

	public static function find( $q, $limit = 10 ) {
		global $wpdb;
		$t    = self::t_c();
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$ph   = NURAX_Desk::phone( $q );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s ORDER BY spent DESC LIMIT %d", $like, $like, $ph ? '%' . substr( $ph, -9 ) . '%' : $like, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Insert or merge a contact by e-mail, then phone. */
	public static function upsert( $a ) {
		global $wpdb;
		$t     = self::t_c();
		$email = isset( $a['email'] ) && is_email( $a['email'] ) ? strtolower( sanitize_email( $a['email'] ) ) : '';
		$phone = isset( $a['phone'] ) ? NURAX_Desk::phone( $a['phone'] ) : '';
		if ( '' === $email && '' === $phone ) {
			return 0;
		}
		$row = null;
		if ( $email ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( ! $row && $phone ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE phone = %s LIMIT 1", $phone ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$now = current_time( 'mysql' );
		$src = isset( $a['source'] ) ? sanitize_key( $a['source'] ) : '';
		if ( $row ) {
			$d = array( 'updated_at' => $now );
			if ( $email && ! $row->email ) {
				$d['email'] = $email;
			}
			if ( $phone && ! $row->phone ) {
				$d['phone'] = $phone;
			}
			if ( ! empty( $a['name'] ) && ! $row->name ) {
				$d['name'] = mb_substr( sanitize_text_field( $a['name'] ), 0, 120 );
			}
			if ( ! empty( $a['user_id'] ) && ! $row->user_id ) {
				$d['user_id'] = (int) $a['user_id'];
			}
			if ( $src && false === strpos( $row->sources, $src ) ) {
				$d['sources'] = trim( $row->sources . ',' . $src, ',' );
			}
			if ( ! empty( $a['consent'] ) ) {
				$d['consent'] = 1;
			}
			if ( ! empty( $a['spent'] ) ) {
				$d['spent']      = (float) $row->spent + (float) $a['spent'];
				$d['orders']     = (int) $row->orders + 1;
				$d['last_order'] = $now;
			}
			$wpdb->update( $t, $d, array( 'id' => $row->id ) );
			return (int) $row->id;
		}
		$wpdb->insert( $t, array(
			'name'       => isset( $a['name'] ) ? mb_substr( sanitize_text_field( $a['name'] ), 0, 120 ) : '',
			'email'      => $email,
			'phone'      => $phone,
			'user_id'    => isset( $a['user_id'] ) ? (int) $a['user_id'] : 0,
			'sources'    => $src,
			'consent'    => empty( $a['consent'] ) ? 0 : 1,
			'orders'     => empty( $a['spent'] ) ? 0 : 1,
			'spent'      => isset( $a['spent'] ) ? (float) $a['spent'] : 0,
			'last_order' => empty( $a['spent'] ) ? null : $now,
			'token'      => wp_generate_password( 24, false, false ),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return (int) $wpdb->insert_id;
	}

	public static function from_order( $order_id ) {
		$o = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $o || $o->get_meta( '_nura_mkt_counted' ) || 'nura_pos' === $o->get_created_via() ) {
			return;
		}
		self::upsert( array( 'name' => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), 'email' => $o->get_billing_email(), 'phone' => $o->get_billing_phone(), 'user_id' => $o->get_customer_id(), 'source' => 'shop', 'spent' => (float) $o->get_total() ) );
		$o->update_meta_data( '_nura_mkt_counted', 1 );
		$o->save_meta_data();
	}

	public static function from_user( $uid ) {
		$u = get_userdata( $uid );
		if ( $u ) {
			self::upsert( array( 'name' => $u->display_name, 'email' => $u->user_email, 'user_id' => $uid, 'source' => 'account', 'consent' => 1 ) );
		}
	}

	/** Rebuild the audience from every source (safe to run any time). */
	public static function sync() {
		global $wpdb;
		$n = 0;
		foreach ( get_users( array( 'number' => 5000, 'role__not_in' => array( 'administrator', 'editor', 'shop_manager', 'author' ) ) ) as $u ) {
			$name = trim( get_user_meta( $u->ID, 'billing_first_name', true ) . ' ' . get_user_meta( $u->ID, 'billing_last_name', true ) );
			self::upsert( array( 'name' => $name ? $name : $u->display_name, 'email' => $u->user_email, 'phone' => get_user_meta( $u->ID, 'billing_phone', true ), 'user_id' => $u->ID, 'source' => 'account' ) );
			$n++;
		}
		$agg = array();
		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders( array( 'limit' => 5000, 'type' => 'shop_order', 'status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ) ) );
			foreach ( $orders as $o ) {
				$id = self::upsert( array( 'name' => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), 'email' => $o->get_billing_email(), 'phone' => $o->get_billing_phone(), 'user_id' => $o->get_customer_id(), 'source' => 'nura_pos' === $o->get_created_via() ? 'pos' : 'shop' ) );
				if ( ! $id || ! NURAX_Desk_POS::is_paid( $o ) ) {
					continue;
				}
				if ( ! isset( $agg[ $id ] ) ) {
					$agg[ $id ] = array( 0, 0, '' );
				}
				$agg[ $id ][0]++;
				$agg[ $id ][1] += (float) $o->get_total();
				$dt = $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i:s' ) : '';
				if ( $dt > $agg[ $id ][2] ) {
					$agg[ $id ][2] = $dt;
				}
				$o->update_meta_data( '_nura_mkt_counted', 1 );
				$o->save_meta_data();
				$n++;
			}
		}
		foreach ( NURAX_Desk_Plans::all() as $p ) {
			self::upsert( array( 'name' => $p->customer_name, 'email' => $p->email, 'phone' => $p->phone, 'source' => 'plan' ) );
		}
		global $wpdb;
		foreach ( (array) $wpdb->get_results( 'SELECT customer_name, phone, email FROM ' . NURAX_Desk::t_appts() . ' LIMIT 5000' ) as $a ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::upsert( array( 'name' => $a->customer_name, 'email' => $a->email, 'phone' => $a->phone, 'source' => 'appointment' ) );
		}
		$q = new WP_Query( array( 'post_type' => 'nurax_wa_order', 'post_status' => 'private', 'posts_per_page' => 3000, 'fields' => 'ids' ) );
		foreach ( $q->posts as $pid ) {
			$d = get_post_meta( $pid, '_nwo', true );
			if ( is_array( $d ) ) {
				self::upsert( array( 'name' => isset( $d['name'] ) ? $d['name'] : '', 'phone' => isset( $d['phone'] ) ? $d['phone'] : '', 'source' => 'whatsapp' ) );
			}
		}
		$t = self::t_c();
		foreach ( $agg as $id => $v ) {
			$wpdb->update( $t, array( 'orders' => $v[0], 'spent' => $v[1], 'last_order' => $v[2] ? $v[2] : null ), array( 'id' => $id ) );
		}
		update_option( 'nurax_mkt_synced', current_time( 'mysql' ), false );
		return $n;
	}

	/* ------------------------------------------------------------------ */
	/* Segments                                                            */
	/* ------------------------------------------------------------------ */

	public static function segments() {
		$s = NURAX_Desk::settings();
		return array(
			'all'        => 'Everyone',
			'customers'  => 'Customers who have bought',
			'vip'        => 'Top customers (spent ' . NURAX_Desk::money( $s['mkt_vip'] ) . '+)',
			'winback'    => 'Not bought in ' . (int) $s['mkt_winback'] . '+ days',
			'leads'      => 'Never bought (leads)',
			'plan'       => 'Lipa Mdogo Mdogo customers',
			'appointment'=> 'Appointment clients',
			'whatsapp'   => 'WhatsApp order customers',
			'account'    => 'Registered on the website',
			'consent'    => 'Opted in to offers',
			'tag'        => 'Has tag...',
		);
	}

	/** SQL WHERE for a segment + channel (contacts table alias-free). */
	public static function where( $seg, $val, $channel ) {
		global $wpdb;
		$s = NURAX_Desk::settings();
		if ( 'any' === $channel ) {
			$w = '1=1';
		} else {
			$w = 'email' === $channel ? "email <> '' AND email_ok = 1" : "phone <> '' AND wa_ok = 1";
		}
		if ( 'any' !== $channel && empty( $s['mkt_soft_optin'] ) ) {
			$w .= ' AND consent = 1';
		}
		switch ( $seg ) {
			case 'customers':
				$w .= ' AND orders > 0';
				break;
			case 'vip':
				$w .= $wpdb->prepare( ' AND spent >= %f', (float) $s['mkt_vip'] );
				break;
			case 'winback':
				$w .= $wpdb->prepare( ' AND orders > 0 AND last_order < %s', gmdate( 'Y-m-d', strtotime( NURAX_Desk::today() . ' -' . (int) $s['mkt_winback'] . ' days' ) ) );
				break;
			case 'leads':
				$w .= ' AND orders = 0';
				break;
			case 'plan':
			case 'appointment':
			case 'whatsapp':
			case 'account':
				$w .= $wpdb->prepare( ' AND sources LIKE %s', '%' . $wpdb->esc_like( $seg ) . '%' );
				break;
			case 'consent':
				$w .= ' AND consent = 1';
				break;
			case 'tag':
				$w .= $wpdb->prepare( ' AND tags LIKE %s', '%' . $wpdb->esc_like( (string) $val ) . '%' );
				break;
		}
		return $w;
	}

	public static function count( $seg, $val, $channel ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t_c() . ' WHERE ' . self::where( $seg, $val, $channel ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/* ------------------------------------------------------------------ */
	/* Rendering                                                           */
	/* ------------------------------------------------------------------ */

	public static function personal( $text, $c ) {
		$first = trim( (string) strtok( (string) ( $c ? $c->name : '' ), ' ' ) );
		return str_replace( array( '{first}', '{name}' ), array( $first ? $first : 'there', $c && $c->name ? $c->name : 'there' ), (string) $text );
	}

	/** Light formatting: blank line = paragraph, **bold**, [text](url), bare links. */
	private static function body_html( $text ) {
		$out = '';
		foreach ( preg_split( "/\n\s*\n/", trim( (string) $text ) ) as $p ) {
			$h = esc_html( $p );
			$h = preg_replace( '/\*\*(.+?)\*\*|\*(.+?)\*/', '<strong>$1$2</strong>', $h );
			$h = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" style="color:#a07a24">$1</a>', $h );
			$out .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#2b2622">' . nl2br( $h ) . '</p>';
		}
		return $out;
	}

	public static function email_html( $k, $c = null, $sid = 0 ) {
		$b     = NURAX_Desk_POS::biz();
		$unsub = $c && $c->token ? add_query_arg( array( 'nura_unsub' => $c->token ), home_url( '/' ) ) : home_url( '/' );
		$utm   = function ( $u ) use ( $k ) {
			return add_query_arg( array( 'utm_source' => 'nura_email', 'utm_medium' => 'email', 'utm_campaign' => sanitize_title( $k->name ) ), $u );
		};
		$prods = '';
		$ids   = array_filter( array_map( 'absint', explode( ',', (string) $k->products ) ) );
		if ( $ids && function_exists( 'wc_get_product' ) ) {
			$cells = array();
			foreach ( array_slice( $ids, 0, 6 ) as $pid ) {
				$p = wc_get_product( $pid );
				if ( ! $p ) {
					continue;
				}
				$img     = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
				$cells[] = '<td width="50%" valign="top" style="padding:8px"><a href="' . esc_url( $utm( $p->get_permalink() ) ) . '" style="text-decoration:none;color:#1b1b1b"><img src="' . esc_url( $img ? $img : '' ) . '" width="250" alt="" style="width:100%;max-width:250px;height:auto;border-radius:10px;display:block;background:#f3e9dc"><p style="margin:10px 0 2px;font-size:14px;font-weight:600">' . esc_html( $p->get_name() ) . '</p><p style="margin:0 0 8px;font-size:14px;color:#a07a24;font-weight:700">' . esc_html( NURAX_Desk::money( wc_get_price_to_display( $p ) ) ) . '</p><span style="display:inline-block;padding:7px 14px;border:1px solid #0E0E0E;border-radius:6px;font-size:12px;font-weight:700;letter-spacing:.08em">SHOP NOW</span></a></td>';
			}
			foreach ( array_chunk( $cells, 2 ) as $row ) {
				$prods .= '<tr>' . implode( '', $row ) . ( 1 === count( $row ) ? '<td></td>' : '' ) . '</tr>';
			}
			$prods = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 10px">' . $prods . '</table>';
		}
		$pixel = $sid ? '<img src="' . esc_url( add_query_arg( array( 'nura_mo' => $sid, 'k' => substr( wp_hash( 'nura_mo' . $sid ), 0, 12 ) ), home_url( '/' ) ) ) . '" width="1" height="1" alt="" style="display:block;border:0">' : '';
		$btn   = $k->btn_text && $k->btn_url ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 22px"><tr><td style="border-radius:8px;background:#0E0E0E"><a href="' . esc_url( $utm( $k->btn_url ) ) . '" style="display:inline-block;padding:14px 26px;color:#E4C983;font-weight:700;font-size:14px;letter-spacing:.08em;text-decoration:none;text-transform:uppercase">' . esc_html( $k->btn_text ) . '</a></td></tr></table>' : '';
		$img   = $k->image ? '<img src="' . esc_url( $k->image ) . '" width="600" alt="" style="width:100%;max-width:600px;height:auto;display:block">' : '';
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $k->subject ) . '</title></head>' .
			'<body style="margin:0;padding:0;background:#f2eee7;font-family:Helvetica,Arial,sans-serif">' .
			'<div style="display:none;max-height:0;overflow:hidden">' . esc_html( self::personal( $k->preheader, $c ) ) . '</div>' .
			'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2eee7"><tr><td align="center" style="padding:24px 12px">' .
			'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden">' .
			'<tr><td align="center" style="background:#0E0E0E;padding:26px 20px"><a href="' . esc_url( $utm( home_url( '/' ) ) ) . '" style="text-decoration:none"><span style="font-family:Georgia,serif;font-size:30px;letter-spacing:8px;color:#E4C983;font-weight:bold">' . esc_html( strtoupper( $b['name'] === 'NURA Beauty' ? 'NURA' : $b['name'] ) ) . '</span><br><span style="font-size:10px;letter-spacing:4px;color:#bfb6a8">WEAR YOUR CROWN</span></a></td></tr>' .
			'<tr><td>' . $img . '</td></tr>' .
			'<tr><td style="padding:30px 32px 10px">' . ( $k->heading ? '<h1 style="margin:0 0 16px;font-family:Georgia,serif;font-size:26px;line-height:1.25;color:#0E0E0E">' . esc_html( self::personal( $k->heading, $c ) ) . '</h1>' : '' ) . self::body_html( self::personal( $k->body, $c ) ) . $btn . $prods . '</td></tr>' .
			'<tr><td style="padding:22px 32px;background:#faf7f2;border-top:1px solid #eee5d6;font-size:12px;line-height:1.6;color:#7a7268" align="center">' . esc_html( $b['name'] . ' - ' . $b['addr'] ) . '<br>' . esc_html( $b['phone'] ) . ' - <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#7a7268">' . esc_html( $b['web'] ) . '</a><br><br>You are receiving this because you shopped with us or joined NURA.<br><a href="' . esc_url( $unsub ) . '" style="color:#7a7268;text-decoration:underline">Unsubscribe</a></td></tr>' .
			'</table>' . $pixel . '</td></tr></table></body></html>';
	}

	public static function wa_text( $k, $c ) {
		$t = self::personal( $k->body, $c );
		if ( $k->btn_url ) {
			$t .= "\n\n" . ( $k->btn_text ? $k->btn_text . ': ' : '' ) . $k->btn_url;
		}
		return trim( $t ) . "\n\nReply STOP to opt out.";
	}

	/* ------------------------------------------------------------------ */
	/* Sending                                                             */
	/* ------------------------------------------------------------------ */

	public static function campaign( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_k() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function queue( $k ) {
		global $wpdb;
		$ids = $wpdb->get_col( 'SELECT id FROM ' . self::t_c() . ' WHERE ' . self::where( $k->segment, $k->seg_value, $k->channel ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->delete( self::t_s(), array( 'campaign_id' => $k->id, 'status' => 'queued' ) );
		foreach ( $ids as $cid ) {
			$wpdb->insert( self::t_s(), array( 'campaign_id' => $k->id, 'contact_id' => (int) $cid, 'status' => 'queued' ) );
		}
		$wpdb->update( self::t_k(), array( 'status' => 'sending', 'total' => count( $ids ), 'sent' => 0, 'failed' => 0, 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $k->id ) );
		return count( $ids );
	}

	/** Process up to $n queued sends. Returns remaining count. */
	public static function tick( $id, $n = 15 ) {
		global $wpdb;
		$k = self::campaign( $id );
		if ( ! $k || 'sending' !== $k->status ) {
			return 0;
		}
		$api = NURAX_Desk_Remind::api_ready();
		if ( 'whatsapp' === $k->channel && ! $api ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t_s() . " WHERE campaign_id = %d AND status = 'queued'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$s    = NURAX_Desk::settings();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.id AS sid, c.* FROM ' . self::t_s() . ' s JOIN ' . self::t_c() . " c ON c.id = s.contact_id WHERE s.campaign_id = %d AND s.status = 'queued' LIMIT %d", $id, $n ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $rows as $c ) {
			if ( 'email' === $k->channel ) {
				$from = is_email( $s['mkt_from_email'] ) ? $s['mkt_from_email'] : get_option( 'admin_email' );
				$hdr  = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . ( $s['mkt_from_name'] ? $s['mkt_from_name'] : 'NURA Beauty' ) . ' <' . $from . '>', 'List-Unsubscribe: <' . add_query_arg( 'nura_unsub', $c->token, home_url( '/' ) ) . '>' );
				$ok   = is_email( $c->email ) && wp_mail( $c->email, self::personal( $k->subject, $c ), self::email_html( $k, $c, (int) $c->sid ), $hdr );
			} else {
				$ok = NURAX_WhatsApp::send_text( NURAX_Desk::phone( $c->phone ), self::wa_text( $k, $c ) );
			}
			$wpdb->update( self::t_s(), array( 'status' => $ok ? 'sent' : 'failed', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $c->sid ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t_k() . ' SET ' . ( $ok ? 'sent = sent + 1' : 'failed = failed + 1' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t_s() . " WHERE campaign_id = %d AND status = 'queued'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( 0 === $left ) {
			$wpdb->update( self::t_k(), array( 'status' => 'sent' ), array( 'id' => $id ) );
		}
		return $left;
	}

	public static function cron() {
		global $wpdb;
		foreach ( (array) $wpdb->get_col( 'SELECT id FROM ' . self::t_k() . " WHERE status = 'sending' LIMIT 5" ) as $id ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::tick( (int) $id, 40 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Public routes: unsubscribe + open pixel                            */
	/* ------------------------------------------------------------------ */

	public static function public_routes() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['nura_mo'] ) && ! empty( $_GET['k'] ) ) {
			$sid = absint( $_GET['nura_mo'] );
			if ( hash_equals( substr( wp_hash( 'nura_mo' . $sid ), 0, 12 ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_s() . ' WHERE id = %d', $sid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( $row && ! $row->opened_at ) {
					$wpdb->update( self::t_s(), array( 'opened_at' => current_time( 'mysql' ) ), array( 'id' => $sid ) );
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t_k() . ' SET opened = opened + 1 WHERE id = %d', $row->campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
			nocache_headers();
			header( 'Content-Type: image/gif' );
			echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ); // phpcs:ignore
			exit;
		}
		if ( ! empty( $_GET['nura_unsub'] ) ) {
			$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['nura_unsub'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$ok  = $tok && $wpdb->update( self::t_c(), array( 'email_ok' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'token' => $tok ) );
			nocache_headers();
			wp_die( '<div style="text-align:center;font-family:Georgia,serif"><h1 style="letter-spacing:.2em">NURA</h1><p style="font-family:Arial,sans-serif;font-size:16px">' . ( false !== $ok ? 'You have been unsubscribed. You will not receive marketing e-mails from NURA Beauty any more.' : 'This link is no longer valid.' ) . '</p><p style="font-family:Arial,sans-serif"><a href="' . esc_url( home_url( '/' ) ) . '">Back to the shop</a></p></div>', 'Unsubscribed - NURA Beauty', array( 'response' => 200 ) );
		}
		// phpcs:enable
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	public static function ajax() {
		check_ajax_referer( 'nurax_mkt', 'nonce' );
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			wp_send_json_error( array(), 403 );
		}
		global $wpdb;
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		if ( 'count' === $op ) {
			$seg = isset( $_POST['segment'] ) ? sanitize_key( wp_unslash( $_POST['segment'] ) ) : 'all';
			$val = isset( $_POST['seg_value'] ) ? sanitize_text_field( wp_unslash( $_POST['seg_value'] ) ) : '';
			$ch  = isset( $_POST['channel'] ) && 'whatsapp' === $_POST['channel'] ? 'whatsapp' : 'email';
			wp_send_json_success( array( 'count' => self::count( $seg, $val, $ch ) ) );
		}
		if ( 'preview' === $op ) {
			$k = (object) array(
				'name' => 'preview', 'subject' => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
				'preheader' => '', 'heading' => isset( $_POST['heading'] ) ? sanitize_text_field( wp_unslash( $_POST['heading'] ) ) : '',
				'body' => isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '',
				'btn_text' => isset( $_POST['btn_text'] ) ? sanitize_text_field( wp_unslash( $_POST['btn_text'] ) ) : '',
				'btn_url' => isset( $_POST['btn_url'] ) ? esc_url_raw( wp_unslash( $_POST['btn_url'] ) ) : '',
				'image' => isset( $_POST['image'] ) ? esc_url_raw( wp_unslash( $_POST['image'] ) ) : '',
				'products' => isset( $_POST['products'] ) ? preg_replace( '/[^0-9,]/', '', wp_unslash( $_POST['products'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			);
			$c = (object) array( 'name' => 'Wanjiku Kamau', 'token' => '' );
			wp_send_json_success( array( 'html' => self::email_html( $k, $c ) ) );
		}
		if ( 'tick' === $op ) {
			$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
			$left = self::tick( $id, 12 );
			$k    = self::campaign( $id );
			wp_send_json_success( array( 'left' => $left, 'sent' => $k ? (int) $k->sent : 0, 'failed' => $k ? (int) $k->failed : 0, 'total' => $k ? (int) $k->total : 0, 'status' => $k ? $k->status : '' ) );
		}
		if ( 'wa_mark' === $op ) {
			$sid = isset( $_POST['sid'] ) ? absint( $_POST['sid'] ) : 0;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_s() . ' WHERE id = %d', $sid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $row && 'queued' === $row->status ) {
				$wpdb->update( self::t_s(), array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $sid ) );
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t_k() . ' SET sent = sent + 1 WHERE id = %d', $row->campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t_s() . " WHERE campaign_id = %d AND status = 'queued'", $row->campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( ! $left ) {
					$wpdb->update( self::t_k(), array( 'status' => 'sent' ), array( 'id' => $row->campaign_id ) );
				}
			}
			wp_send_json_success();
		}
		wp_send_json_error( array(), 400 );
	}

	/* ------------------------------------------------------------------ */
	/* Admin handlers                                                      */
	/* ------------------------------------------------------------------ */

	public static function h_sync() {
		NURAX_Desk::guard( 'nurax_mkt_sync' );
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		self::sync();
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'contacts', 'nd_msg' => 'synced' ) ) );
		exit;
	}

	public static function h_contact() {
		NURAX_Desk::guard( 'nurax_mkt_contact' );
		global $wpdb;
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$op = isset( $in['op'] ) ? sanitize_key( $in['op'] ) : 'add';
		if ( 'add' === $op ) {
			$id = self::upsert( array( 'name' => isset( $in['name'] ) ? $in['name'] : '', 'email' => isset( $in['email'] ) ? $in['email'] : '', 'phone' => isset( $in['phone'] ) ? $in['phone'] : '', 'source' => 'manual', 'consent' => ! empty( $in['consent'] ) ) );
			if ( $id && ! empty( $in['tags'] ) ) {
				$wpdb->update( self::t_c(), array( 'tags' => mb_substr( sanitize_text_field( $in['tags'] ), 0, 255 ) ), array( 'id' => $id ) );
			}
		} else {
			$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
			if ( 'delete' === $op ) {
				$wpdb->delete( self::t_c(), array( 'id' => $id ) );
				$wpdb->delete( self::t_s(), array( 'contact_id' => $id ) );
			} elseif ( 'toggle_email' === $op || 'toggle_wa' === $op ) {
				$col = 'toggle_email' === $op ? 'email_ok' : 'wa_ok';
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t_c() . " SET $col = 1 - $col WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			} elseif ( 'tags' === $op ) {
				$wpdb->update( self::t_c(), array( 'tags' => mb_substr( sanitize_text_field( isset( $in['tags'] ) ? $in['tags'] : '' ), 0, 255 ) ), array( 'id' => $id ) );
			}
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'nd_msg', 'saved', $back ? remove_query_arg( 'nd_msg', $back ) : NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'contacts' ) ) ) );
		exit;
	}

	public static function h_save() {
		NURAX_Desk::guard( 'nurax_mkt_save' );
		global $wpdb;
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id   = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		$segs = self::segments();
		$data = array(
			'name'      => mb_substr( sanitize_text_field( isset( $in['name'] ) && '' !== trim( $in['name'] ) ? $in['name'] : 'Campaign ' . current_time( 'j M' ) ), 0, 160 ),
			'channel'   => isset( $in['channel'] ) && 'whatsapp' === $in['channel'] ? 'whatsapp' : 'email',
			'subject'   => mb_substr( sanitize_text_field( isset( $in['subject'] ) ? $in['subject'] : '' ), 0, 200 ),
			'preheader' => mb_substr( sanitize_text_field( isset( $in['preheader'] ) ? $in['preheader'] : '' ), 0, 200 ),
			'heading'   => mb_substr( sanitize_text_field( isset( $in['heading'] ) ? $in['heading'] : '' ), 0, 200 ),
			'body'      => sanitize_textarea_field( isset( $in['body'] ) ? $in['body'] : '' ),
			'btn_text'  => mb_substr( sanitize_text_field( isset( $in['btn_text'] ) ? $in['btn_text'] : '' ), 0, 80 ),
			'btn_url'   => esc_url_raw( isset( $in['btn_url'] ) ? $in['btn_url'] : '' ),
			'image'     => esc_url_raw( isset( $in['image'] ) ? $in['image'] : '' ),
			'products'  => implode( ',', array_slice( array_filter( array_map( 'absint', isset( $in['products'] ) ? (array) $in['products'] : array() ) ), 0, 6 ) ),
			'segment'   => isset( $in['segment'] ) && isset( $segs[ $in['segment'] ] ) ? $in['segment'] : 'all',
			'seg_value' => mb_substr( sanitize_text_field( isset( $in['seg_value'] ) ? $in['seg_value'] : '' ), 0, 60 ),
		);
		if ( $id ) {
			$old = self::campaign( $id );
			if ( $old && 'draft' !== $old->status ) {
				$id = 0; // Sent campaigns are never edited: save as a copy.
			}
		}
		if ( $id ) {
			$wpdb->update( self::t_k(), $data, array( 'id' => $id ) );
		} else {
			$data['status']     = 'draft';
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( self::t_k(), $data );
			$id = (int) $wpdb->insert_id;
		}
		$do = isset( $in['do'] ) ? sanitize_key( $in['do'] ) : 'save';
		$k  = self::campaign( $id );
		if ( 'test' === $do && $k ) {
			$u  = wp_get_current_user();
			$to = isset( $in['test_to'] ) && is_email( $in['test_to'] ) ? $in['test_to'] : $u->user_email;
			$c  = (object) array( 'name' => $u->display_name, 'token' => '' );
			wp_mail( $to, '[Test] ' . self::personal( $k->subject, $c ), self::email_html( $k, $c ), array( 'Content-Type: text/html; charset=UTF-8' ) );
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'id' => $id, 'nd_msg' => 'tested' ) ) );
			exit;
		}
		if ( 'send' === $do && $k ) {
			self::queue( $k );
			wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'send', 'id' => $id ) ) );
			exit;
		}
		wp_safe_redirect( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'id' => $id, 'nd_msg' => 'saved' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Admin pages                                                         */
	/* ------------------------------------------------------------------ */

	private static function subnav( $on ) {
		$l = array( 'campaigns' => 'Campaigns', 'contacts' => 'Audience' );
		$h = '<nav class="nd-seg">';
		foreach ( $l as $k => $v ) {
			$h .= '<a class="' . ( $on === $k ? 'is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => $k ) ) ) . '">' . esc_html( $v ) . '</a>';
		}
		return $h . '</nav>';
	}

	public static function page() {
		if ( ! current_user_can( NURAX_Desk::CAP ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'campaigns'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $view ) {
			self::edit( $id );
		} elseif ( 'send' === $view && $id ) {
			self::send_view( $id );
		} elseif ( 'contacts' === $view ) {
			self::contacts();
		} else {
			self::campaigns();
		}
	}

	private static function campaigns() {
		global $wpdb;
		$act = NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'ch' => 'email' ) ), '+ E-mail campaign', 'nd-btn--primary' ) . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'ch' => 'whatsapp' ) ), '+ WhatsApp campaign', 'nd-btn--wa' );
		NURAX_Desk::header( NURAX_Desk::P_MKT, 'Marketing', self::subnav( 'campaigns' ) . $act );
		$t   = self::t_c();
		$all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$em  = self::count( 'all', '', 'email' );
		$wa  = self::count( 'all', '', 'whatsapp' );
		$cu  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE orders > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$st  = $wpdb->get_row( 'SELECT COALESCE(SUM(sent),0) s, COALESCE(SUM(opened),0) o FROM ' . self::t_k() . " WHERE channel = 'email'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<div class="nd-kpis">';
		NURAX_Desk::kpi( 'Audience', (string) $all, 'contacts in total', 'ink', NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'contacts' ) ) );
		NURAX_Desk::kpi( 'Reachable by e-mail', (string) $em, 'subscribed' );
		NURAX_Desk::kpi( 'Reachable on WhatsApp', (string) $wa, 'with a phone number' );
		NURAX_Desk::kpi( 'Average open rate', ( $st && $st->s ? round( $st->o / $st->s * 100 ) : 0 ) . '%', 'across e-mail campaigns', 'gold' );
		echo '</div>';
		if ( ! $all ) {
			echo '<div class="nd-empty"><strong>Build your audience first.</strong><p>Pull in every customer from the shop, NURA accounts, WhatsApp orders, payment plans and appointments in one click.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nurax_mkt_sync">';
			wp_nonce_field( 'nurax_mkt_sync' );
			echo '<button class="nd-btn nd-btn--primary">Import my customers</button></form></div>';
		}
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . self::t_k() . ' ORDER BY id DESC LIMIT 100' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			echo '<div class="nd-grid2"><section class="nd-card"><h2 class="nd-h">E-mail campaigns</h2><p class="nd-mut">Branded e-mails with your photos, a button and product cards with live prices. Sent in the background, with open tracking and a one-click unsubscribe.</p>' . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'ch' => 'email' ) ), 'Create an e-mail', 'nd-btn--primary' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<section class="nd-card"><h2 class="nd-h">WhatsApp campaigns</h2><p class="nd-mut">A personal message to each customer with their first name. Send one-by-one with a single tap each, or automatically when the WhatsApp Business API is connected.</p>' . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'ch' => 'whatsapp' ) ), 'Create a WhatsApp message', 'nd-btn--wa' ) . '</section></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			NURAX_Desk::footer();
			return;
		}
		$segs = self::segments();
		echo '<div class="nd-table-wrap"><table class="nd-table"><thead><tr><th>Campaign</th><th>Channel</th><th>Audience</th><th>Status</th><th>Sent</th><th>Opened</th><th class="nd-col-act"></th></tr></thead><tbody>';
		foreach ( $rows as $k ) {
			$open = 'email' === $k->channel && $k->sent ? round( $k->opened / $k->sent * 100 ) . '%' : '-';
			$tone = array( 'draft' => 'muted', 'sending' => 'request', 'sent' => 'confirmed' );
			echo '<tr><td><a class="nd-ref" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'draft' === $k->status ? 'edit' : 'send', 'id' => $k->id ) ) ) . '" style="font-family:inherit;font-size:14px">' . esc_html( $k->name ) . '</a><small>' . esc_html( 'email' === $k->channel ? $k->subject : wp_trim_words( $k->body, 10 ) ) . '</small></td>';
			echo '<td>' . NURAX_Desk::badge( 'email' === $k->channel ? 'released' : 'confirmed', 'email' === $k->channel ? 'E-mail' : 'WhatsApp' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . esc_html( isset( $segs[ $k->segment ] ) ? $segs[ $k->segment ] : $k->segment ) . '</td>';
			echo '<td>' . NURAX_Desk::badge( isset( $tone[ $k->status ] ) ? $tone[ $k->status ] : 'muted', ucfirst( $k->status ) ) . '<small>' . esc_html( $k->sent_at ? NURAX_Desk::date_label( $k->sent_at, 'j M, H:i' ) : NURAX_Desk::date_label( $k->created_at, 'j M' ) ) . '</small></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td><strong>' . (int) $k->sent . '</strong> / ' . (int) $k->total . ( $k->failed ? '<small class="nd-red">' . (int) $k->failed . ' failed</small>' : '' ) . '</td>';
			echo '<td>' . esc_html( $open ) . '</td>';
			echo '<td class="nd-col-act"><a class="nd-ico-btn" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'id' => $k->id ) ) ) . '">' . ( 'draft' === $k->status ? 'Edit' : 'Duplicate' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
		NURAX_Desk::footer();
	}

	private static function edit( $id ) {
		$k  = $id ? self::campaign( $id ) : null;
		$ch = $k ? $k->channel : ( isset( $_GET['ch'] ) && 'whatsapp' === $_GET['ch'] ? 'whatsapp' : 'email' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$v  = $k ? $k : (object) array( 'name' => '', 'subject' => '', 'preheader' => '', 'heading' => '', 'body' => "Hi {first},\n\n", 'btn_text' => 'Shop now', 'btn_url' => home_url( '/shop/' ), 'image' => '', 'products' => '', 'segment' => 'all', 'seg_value' => '' );
		$sent = $k && 'draft' !== $k->status;
		wp_enqueue_media();
		NURAX_Desk::header( NURAX_Desk::P_MKT, $k ? ( $sent ? 'Duplicate: ' : 'Edit: ' ) . $k->name : ( 'whatsapp' === $ch ? 'New WhatsApp campaign' : 'New e-mail campaign' ), self::subnav( 'campaigns' ) );
		wp_localize_script( 'nurax-desk', 'NURAX_MKT', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nurax_mkt' ), 'channel' => $ch ) );
		$segs = self::segments();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form" data-mkt-form><input type="hidden" name="action" value="nurax_mkt_save"><input type="hidden" name="id" value="' . ( $sent ? 0 : (int) $id ) . '"><input type="hidden" name="channel" value="' . esc_attr( $ch ) . '"><input type="hidden" name="do" value="save" data-mkt-do>';
		wp_nonce_field( 'nurax_mkt_save' );
		echo '<div class="nd-plan nd-plan--mkt"><div class="nd-plan__main">';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>1. Audience</h2><span class="nd-badge nd-badge--confirmed" data-mkt-count>...</span></header><div class="nd-fields nd-fields--2">';
		echo '<label class="nd-field"><span class="nd-label">Campaign name (internal)</span><input type="text" name="name" value="' . esc_attr( $v->name ) . '" placeholder="e.g. December bridal offer"></label>';
		echo '<label class="nd-field"><span class="nd-label">Send to</span><select name="segment" data-mkt-seg>';
		foreach ( $segs as $sk => $sl ) {
			echo '<option value="' . esc_attr( $sk ) . '"' . selected( $v->segment, $sk, false ) . '>' . esc_html( $sl ) . '</option>';
		}
		echo '</select></label><label class="nd-field" data-mkt-tag' . ( 'tag' === $v->segment ? '' : ' hidden' ) . '><span class="nd-label">Tag</span><input type="text" name="seg_value" value="' . esc_attr( $v->seg_value ) . '" placeholder="e.g. bridal"></label></div></section>';

		echo '<section class="nd-card"><header class="nd-card__h"><h2>2. Message</h2></header><div class="nd-fields">';
		if ( 'email' === $ch ) {
			echo '<div class="nd-fields nd-fields--2"><label class="nd-field"><span class="nd-label">Subject line *</span><input type="text" name="subject" required maxlength="200" value="' . esc_attr( $v->subject ) . '" placeholder="{first}, your new crown is waiting" data-mkt-in></label>';
			echo '<label class="nd-field"><span class="nd-label">Preview text</span><input type="text" name="preheader" maxlength="200" value="' . esc_attr( $v->preheader ) . '" placeholder="Shown after the subject in the inbox"></label></div>';
			echo '<label class="nd-field"><span class="nd-label">Headline</span><input type="text" name="heading" maxlength="200" value="' . esc_attr( $v->heading ) . '" placeholder="New in: 22 inch body wave" data-mkt-in></label>';
			echo '<label class="nd-field"><span class="nd-label">Banner image</span><span class="nd-inrow"><input type="url" name="image" value="' . esc_attr( $v->image ) . '" placeholder="https://..." data-mkt-in data-mkt-img><button type="button" class="nd-btn nd-btn--ghost" data-mkt-media>Choose image</button></span></label>';
		}
		echo '<label class="nd-field"><span class="nd-label">Message *</span><textarea name="body" rows="10" required data-mkt-in data-mkt-body>' . esc_textarea( $v->body ) . '</textarea><small>{first} = customer\'s first name. *bold*. Blank line = new paragraph. Links: [text](https://...)</small></label>';
		echo '<div class="nd-fields nd-fields--2"><label class="nd-field"><span class="nd-label">Button text</span><input type="text" name="btn_text" value="' . esc_attr( $v->btn_text ) . '" data-mkt-in></label><label class="nd-field"><span class="nd-label">Button link</span><input type="url" name="btn_url" value="' . esc_attr( $v->btn_url ) . '" data-mkt-in></label></div>';
		if ( 'email' === $ch ) {
			echo '<label class="nd-field"><span class="nd-label">Feature products (up to 6, live prices)</span><select class="wc-product-search" multiple name="products[]" data-placeholder="Search wigs..." data-action="woocommerce_json_search_products" style="width:100%" data-mkt-prods>';
			foreach ( array_filter( array_map( 'absint', explode( ',', (string) $v->products ) ) ) as $pid ) {
				$p = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
				if ( $p ) {
					echo '<option value="' . (int) $pid . '" selected>' . esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) . '</option>';
				}
			}
			echo '</select></label>';
		}
		echo '</div></section></div>';

		echo '<aside class="nd-plan__side"><section class="nd-card nd-mkt-prev"><header class="nd-card__h"><h2>Preview</h2></header>';
		if ( 'email' === $ch ) {
			echo '<iframe title="E-mail preview" data-mkt-frame class="nd-mkt-frame"></iframe>';
		} else {
			echo '<div class="nd-phone"><div class="nd-phone__top">NURA Beauty</div><div class="nd-phone__chat"><pre class="nd-bubble" data-mkt-wa></pre></div></div>';
		}
		echo '</section><section class="nd-card"><div class="nd-stack">';
		echo '<button type="submit" class="nd-btn nd-btn--ghost nd-btn--block" data-mkt-btn="save">Save draft</button>';
		if ( 'email' === $ch ) {
			echo '<label class="nd-field"><span class="nd-label">Send a test to</span><input type="email" name="test_to" value="' . esc_attr( wp_get_current_user()->user_email ) . '"></label><button type="submit" class="nd-btn nd-btn--ghost nd-btn--block" data-mkt-btn="test">Send test e-mail</button>';
		}
		echo '<button type="submit" class="nd-btn ' . ( 'email' === $ch ? 'nd-btn--primary' : 'nd-btn--wa' ) . ' nd-btn--block" data-mkt-btn="send" data-nd-confirm-send="1">' . ( 'email' === $ch ? 'Send e-mail campaign' : 'Start WhatsApp campaign' ) . '</button>';
		echo '<p class="nd-mut nd-small">' . ( 'email' === $ch ? 'E-mails go out in the background in small batches. Unsubscribed contacts are always skipped.' : ( NURAX_Desk_Remind::api_ready() ? 'Messages are sent automatically through the WhatsApp Business API.' : 'You will get a list with one-tap Send buttons for each customer.' ) ) . '</p></div></section></aside></div></form>';
		NURAX_Desk::footer();
	}

	private static function send_view( $id ) {
		global $wpdb;
		$k = self::campaign( $id );
		if ( ! $k ) {
			NURAX_Desk::header( NURAX_Desk::P_MKT, 'Campaign not found' );
			NURAX_Desk::footer();
			return;
		}
		wp_localize_script( 'nurax-desk', 'NURAX_MKT', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nurax_mkt' ), 'run' => 'sending' === $k->status && ( 'email' === $k->channel || NURAX_Desk_Remind::api_ready() ) ? (int) $id : 0 ) );
		NURAX_Desk::header( NURAX_Desk::P_MKT, $k->name, self::subnav( 'campaigns' ) . NURAX_Desk::btn( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'edit', 'id' => $id ) ), 'Duplicate', 'nd-btn--ghost' ) );
		$pct = $k->total ? round( ( $k->sent + $k->failed ) / $k->total * 100 ) : 100;
		echo '<section class="nd-card nd-remhero"><div class="nd-ring nd-ring--sm" style="--p:' . (int) $pct . '" data-mkt-ring><div><strong data-mkt-pct>' . (int) $pct . '%</strong><span>sent</span></div></div><div><h2>' . esc_html( 'email' === $k->channel ? $k->subject : 'WhatsApp campaign' ) . '</h2><p class="nd-mut"><span data-mkt-sent>' . (int) $k->sent . '</span> of ' . (int) $k->total . ' sent' . ( 'email' === $k->channel ? ' - ' . (int) $k->opened . ' opened' : '' ) . ' - <span data-mkt-failed>' . (int) $k->failed . '</span> failed - <span data-mkt-status>' . esc_html( ucfirst( $k->status ) ) . '</span></p>' . ( 'email' === $k->channel && 'sending' === $k->status ? '<p class="nd-small nd-mut">Keep this page open for the fastest sending. If you close it, the rest goes out in the background every 5 minutes.</p>' : '' ) . '</div></section>';
		if ( 'whatsapp' === $k->channel && ! NURAX_Desk_Remind::api_ready() ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.id AS sid, s.status AS sst, c.* FROM ' . self::t_s() . ' s JOIN ' . self::t_c() . ' c ON c.id = s.contact_id WHERE s.campaign_id = %d ORDER BY s.status DESC, c.name ASC LIMIT 1000', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Send to each customer</h2><button type="button" class="nd-btn nd-btn--wa" data-mkt-next>Send next</button></header><ul class="nd-remlist">';
			foreach ( (array) $rows as $c ) {
				$done = 'queued' !== $c->sst;
				echo '<li class="nd-rem' . ( $done ? ' is-done' : '' ) . '" data-mkt-item="' . (int) $c->sid . '"><span class="nd-avatar">' . esc_html( strtoupper( mb_substr( $c->name ? $c->name : '?', 0, 1 ) ) ) . '</span><span class="nd-rem__main"><strong>' . esc_html( $c->name ? $c->name : NURAX_Desk::phone_label( $c->phone ) ) . '</strong><small>' . esc_html( NURAX_Desk::phone_label( $c->phone ) . ( $c->orders ? ' - ' . (int) $c->orders . ' orders' : '' ) ) . '</small></span><span class="nd-rem__meta"><small data-mkt-st>' . ( $done ? 'Sent' : 'Waiting' ) . '</small></span><span class="nd-rem__acts"><a class="nd-btn nd-btn--wa nd-btn--sm" target="_blank" rel="noopener" href="' . esc_url( NURAX_Desk::wa( $c->phone, self::wa_text( $k, $c ) ) ) . '" data-mkt-wa-send="' . (int) $c->sid . '">Send</a></span></li>';
			}
			echo '</ul></section>';
		} elseif ( 'email' === $k->channel ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>E-mail</h2></header><iframe class="nd-mkt-frame" title="E-mail" srcdoc="' . esc_attr( self::email_html( $k, (object) array( 'name' => 'Wanjiku', 'token' => '' ) ) ) . '"></iframe></section>';
		}
		NURAX_Desk::footer();
	}

	private static function contacts() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$seg   = isset( $_GET['seg'] ) ? sanitize_key( wp_unslash( $_GET['seg'] ) ) : 'all';
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$t    = self::t_c();
		$segs = self::segments();
		unset( $segs['tag'] );
		$w = '1=1';
		if ( 'all' !== $seg && isset( $segs[ $seg ] ) ) {
			$w = '(' . self::where( $seg, '', 'any' ) . ')';
		}
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$w   .= $wpdb->prepare( ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR tags LIKE %s)', $like, $like, $like, $like );
		}
		$per   = 50;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $w" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $w ORDER BY spent DESC, id DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$sync  = get_option( 'nurax_mkt_synced' );
		$act   = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inline"><input type="hidden" name="action" value="nurax_mkt_sync">' . wp_nonce_field( 'nurax_mkt_sync', '_wpnonce', true, false ) . '<button class="nd-btn nd-btn--ghost" title="' . esc_attr( $sync ? 'Last import ' . $sync : 'Never imported' ) . '">Refresh from shop</button></form>' . NURAX_Desk::btn( NURAX_Desk::export_url( 'contacts' ), 'Export CSV', 'nd-btn--ghost' );
		NURAX_Desk::header( NURAX_Desk::P_MKT, 'Audience', self::subnav( 'contacts' ) . $act );
		echo '<details class="nd-card"><summary class="nd-sum">+ Add a contact</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-form" style="margin-top:12px"><input type="hidden" name="action" value="nurax_mkt_contact"><input type="hidden" name="op" value="add">';
		wp_nonce_field( 'nurax_mkt_contact' );
		echo '<div class="nd-fields nd-fields--4"><input type="text" name="name" placeholder="Name"><input type="tel" name="phone" placeholder="Phone"><input type="email" name="email" placeholder="E-mail"><input type="text" name="tags" placeholder="Tags, e.g. bridal, vip"></div><label class="nd-field nd-field--check" style="margin-top:10px"><input type="checkbox" name="consent" value="1" checked><span>Agreed to receive offers</span></label><button class="nd-btn nd-btn--primary">Add contact</button></form></details>';
		echo '<div class="nd-toolbar"><nav class="nd-pills">';
		foreach ( array_merge( array( 'all' => 'All' ), array_slice( $segs, 1, null, true ) ) as $sk => $sl ) {
			echo '<a class="nd-pill' . ( $sk === $seg ? ' is-on' : '' ) . '" href="' . esc_url( NURAX_Desk::url( NURAX_Desk::P_MKT, array( 'view' => 'contacts', 'seg' => $sk ) ) ) . '">' . esc_html( preg_replace( '/ \(.*\)$/', '', $sl ) ) . '</a>';
		}
		echo '</nav><form method="get" class="nd-search"><input type="hidden" name="page" value="' . esc_attr( NURAX_Desk::P_MKT ) . '"><input type="hidden" name="view" value="contacts"><input type="hidden" name="seg" value="' . esc_attr( $seg ) . '"><input type="search" name="s" value="' . esc_attr( $q ) . '" placeholder="Search name, phone, e-mail or tag"><button class="nd-btn nd-btn--ghost">Search</button></form></div>';
		if ( ! $rows ) {
			echo '<div class="nd-empty"><strong>No contacts yet.</strong><p>Click "Refresh from shop" to import your customers.</p></div>';
			NURAX_Desk::footer();
			return;
		}
		echo '<p class="nd-mut nd-small">' . (int) $total . ' contacts</p><div class="nd-table-wrap"><table class="nd-table"><thead><tr><th>Contact</th><th>From</th><th>Orders</th><th>Spent</th><th>Last order</th><th>E-mail</th><th>WhatsApp</th><th class="nd-col-act"></th></tr></thead><tbody>';
		$form = function ( $op, $id, $label, $cls, $confirm = '' ) {
			return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inline"' . ( $confirm ? ' data-nd-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '><input type="hidden" name="action" value="nurax_mkt_contact"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><input type="hidden" name="id" value="' . (int) $id . '">' . wp_nonce_field( 'nurax_mkt_contact', '_wpnonce', true, false ) . '<button class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
		};
		foreach ( $rows as $c ) {
			echo '<tr><td><strong>' . esc_html( $c->name ? $c->name : '-' ) . '</strong><small>' . esc_html( trim( NURAX_Desk::phone_label( $c->phone ) . '  ' . $c->email ) ) . '</small>' . ( $c->tags ? '<small>Tags: ' . esc_html( $c->tags ) . '</small>' : '' ) . '</td>';
			echo '<td><small>' . esc_html( str_replace( ',', ', ', $c->sources ) ) . '</small></td><td>' . (int) $c->orders . '</td><td>' . esc_html( NURAX_Desk::money( $c->spent ) ) . '</td><td><small>' . esc_html( $c->last_order ? NURAX_Desk::date_label( $c->last_order, 'j M Y' ) : '-' ) . '</small></td>';
			echo '<td>' . ( $c->email ? $form( 'toggle_email', $c->id, $c->email_ok ? 'Subscribed' : 'Unsubscribed', 'nd-ico-btn' . ( $c->email_ok ? ' nd-ico-btn--wa' : '' ) ) : '<span class="nd-mut">-</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . ( $c->phone ? $form( 'toggle_wa', $c->id, $c->wa_ok ? 'Yes' : 'Opted out', 'nd-ico-btn' . ( $c->wa_ok ? ' nd-ico-btn--wa' : '' ) ) : '<span class="nd-mut">-</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="nd-col-act">' . $form( 'delete', $c->id, 'Delete', 'nd-ico-btn nd-ico-btn--danger', 'Delete this contact?' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table></div>';
		NURAX_Desk_Plans::pager( $total, $per, $paged, array( 'view' => 'contacts', 'seg' => $seg, 's' => $q ), NURAX_Desk::P_MKT );
		NURAX_Desk::footer();
	}

	public static function export_rows() {
		global $wpdb;
		$rows = array( array( 'Name', 'Phone', 'E-mail', 'Sources', 'Tags', 'Orders', 'Spent', 'Last order', 'E-mail subscribed', 'WhatsApp OK', 'Consent' ) );
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . self::t_c() . ' ORDER BY spent DESC' ) as $c ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows[] = array( $c->name, $c->phone, $c->email, $c->sources, $c->tags, $c->orders, $c->spent, $c->last_order, $c->email_ok ? 'Yes' : 'No', $c->wa_ok ? 'Yes' : 'No', $c->consent ? 'Yes' : 'No' );
		}
		return $rows;
	}
}
