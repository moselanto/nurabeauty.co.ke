<?php
/**
 * NURA Vault - customer data protection (NURA Experience 1.68.0).
 *
 * - AES-256-GCM encryption at rest for customer phone numbers and e-mails in
 *   the NURA audience (WhatsApp contacts, newsletter, customers). Values are
 *   stored as "nx1:<base64(iv|tag|ciphertext)>".
 * - Keyed HMAC-SHA256 "blind index" columns so we can still find a contact by
 *   exact phone / e-mail without storing them in clear.
 * - Key lives outside the database: NURA_DATA_KEY in wp-config.php when set,
 *   otherwise derived from the wp-config.php salts. A database dump or backup
 *   alone cannot be read.
 * - Audit log of exports and security actions, admin-only exports, masking for
 *   non-admin staff, automatic clean-up of old abandoned-cart data.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Vault {

	const PFX   = 'nx1:';
	const ON    = 'nurax_vault_on';
	const FP    = 'nurax_vault_fp';
	const AUDIT = 'nurax_audit_log';

	public static function hooks() {
		add_action( 'admin_post_nurax_vault', array( __CLASS__, 'h_vault' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Keys                                                                */
	/* ------------------------------------------------------------------ */

	private static function material_salts() {
		$m = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT' ) as $c ) {
			$m .= defined( $c ) ? (string) constant( $c ) : '';
		}
		return '' !== $m ? $m : (string) wp_salt( 'auth' );
	}

	public static function key_source() {
		return ( defined( 'NURA_DATA_KEY' ) && strlen( (string) NURA_DATA_KEY ) >= 32 ) ? 'constant' : 'salts';
	}

	/** Encryption keys: primary first, then the salt-derived key (so adding NURA_DATA_KEY later never locks old data). */
	private static function keys() {
		$k = array();
		if ( 'constant' === self::key_source() ) {
			$k[] = hash( 'sha256', 'nura-vault|' . NURA_DATA_KEY, true );
		}
		$k[] = hash( 'sha256', 'nura-vault|' . self::material_salts(), true );
		return $k;
	}

	private static function idx_key() {
		return hash( 'sha256', 'nura-index|' . ( 'constant' === self::key_source() ? NURA_DATA_KEY : self::material_salts() ), true );
	}

	public static function fingerprint() {
		$k = self::keys();
		return substr( hash( 'sha256', $k[0] ), 0, 16 );
	}

	public static function can() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'random_bytes' ) && in_array( 'aes-256-gcm', array_map( 'strtolower', (array) openssl_get_cipher_methods() ), true );
	}

	public static function on() {
		return self::can() && (bool) get_option( self::ON );
	}

	/* ------------------------------------------------------------------ */
	/* Crypto                                                              */
	/* ------------------------------------------------------------------ */

	public static function is_enc( $v ) {
		return is_string( $v ) && 0 === strpos( $v, self::PFX );
	}

	public static function enc( $v ) {
		$v = (string) $v;
		if ( '' === $v || self::is_enc( $v ) || ! self::can() ) {
			return $v;
		}
		$k   = self::keys();
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $v, 'aes-256-gcm', $k[0], OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $ct || 16 !== strlen( $tag ) ) {
			return $v;
		}
		return self::PFX . base64_encode( $iv . $tag . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function dec( $v ) {
		if ( ! self::is_enc( $v ) ) {
			return (string) $v;
		}
		if ( ! self::can() ) {
			return '';
		}
		$raw = base64_decode( substr( $v, strlen( self::PFX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$iv  = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$ct  = substr( $raw, 28 );
		foreach ( self::keys() as $k ) {
			$pt = openssl_decrypt( $ct, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $pt ) {
				return $pt;
			}
		}
		return '';
	}

	/** Value to write to the database (encrypted when protection is on). */
	public static function store( $v ) {
		$v = (string) $v;
		return self::on() ? self::enc( $v ) : $v;
	}

	public static function norm( $v, $kind ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		if ( 'email' === $kind ) {
			return strtolower( $v );
		}
		return class_exists( 'NURAX_Desk' ) ? (string) NURAX_Desk::phone( $v ) : preg_replace( '/\D/', '', $v );
	}

	/** Blind index (keyed hash) for exact lookups. */
	public static function idx( $v, $kind ) {
		$n = self::norm( self::dec( $v ), $kind );
		return '' === $n ? '' : substr( hash_hmac( 'sha256', $kind . '|' . $n, self::idx_key() ), 0, 40 );
	}

	/** Decrypt the phone / email fields of a row object. */
	public static function row( $r ) {
		if ( is_object( $r ) ) {
			foreach ( array( 'email', 'phone' ) as $f ) {
				if ( isset( $r->$f ) ) {
					$r->$f = self::dec( $r->$f );
				}
			}
		}
		return $r;
	}

	public static function rows( $rows ) {
		return array_map( array( __CLASS__, 'row' ), (array) $rows );
	}

	/** Columns to write for a phone / e-mail pair. */
	public static function cols( $email = null, $phone = null ) {
		$d = array();
		if ( null !== $email ) {
			$d['email']   = self::store( $email );
			$d['email_h'] = self::idx( $email, 'email' );
		}
		if ( null !== $phone ) {
			$d['phone']   = self::store( $phone );
			$d['phone_h'] = self::idx( $phone, 'phone' );
		}
		return $d;
	}

	/* ------------------------------------------------------------------ */
	/* Masking + permissions                                               */
	/* ------------------------------------------------------------------ */

	public static function full_access() {
		return current_user_can( 'manage_options' );
	}

	public static function mask_phone( $p ) {
		$p = (string) $p;
		$l = strlen( $p );
		return $l > 6 ? substr( $p, 0, 4 ) . str_repeat( '*', $l - 7 ) . substr( $p, -3 ) : ( $l ? str_repeat( '*', $l ) : '' );
	}

	public static function mask_email( $e ) {
		$e = (string) $e;
		if ( false === strpos( $e, '@' ) ) {
			return $e;
		}
		list( $u, $d ) = explode( '@', $e, 2 );
		return mb_substr( $u, 0, 1 ) . str_repeat( '*', max( 2, mb_strlen( $u ) - 1 ) ) . '@' . $d;
	}

	/** Shown phone / e-mail for the current user (masked for non-admin staff). */
	public static function show( $v, $kind ) {
		if ( self::full_access() || '' === (string) $v ) {
			return (string) $v;
		}
		return 'email' === $kind ? self::mask_email( $v ) : self::mask_phone( $v );
	}

	public static function can_export() {
		return self::full_access() || ! empty( NURAX_Desk::settings()['staff_export'] );
	}

	/* ------------------------------------------------------------------ */
	/* Audit log                                                           */
	/* ------------------------------------------------------------------ */

	public static function log( $what, $detail = '' ) {
		$u   = wp_get_current_user();
		$log = get_option( self::AUDIT, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array(
			't'  => current_time( 'mysql' ),
			'u'  => $u && $u->ID ? $u->user_login : 'system',
			'ip' => isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F\.:]/', '', (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'w'  => mb_substr( (string) $what, 0, 60 ),
			'd'  => mb_substr( (string) $detail, 0, 200 ),
		) );
		update_option( self::AUDIT, array_slice( $log, 0, 500 ), false );
		if ( class_exists( 'NURAX_Staff' ) ) {
			NURAX_Staff::log( 0 === strpos( (string) $what, 'Report' ) ? 'export' : 'security', $what . ( $detail ? ': ' . $detail : '' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Migration                                                           */
	/* ------------------------------------------------------------------ */

	public static function stats() {
		global $wpdb;
		$t = NURAX_Desk_Mkt::t_c();
		return array(
			'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE email <> '' OR phone <> ''" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'plain' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE (email <> '' AND email NOT LIKE 'nx1:%') OR (phone <> '' AND phone NOT LIKE 'nx1:%')" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'noidx' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE (email <> '' AND email_h = '') OR (phone <> '' AND phone_h = '')" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Process one batch. $mode = encrypt | decrypt | index. Returns rows changed.
	 */
	public static function batch( $mode, $n = 300 ) {
		global $wpdb;
		$t = NURAX_Desk_Mkt::t_c();
		if ( 'encrypt' === $mode ) {
			$w = "(email <> '' AND email NOT LIKE 'nx1:%') OR (phone <> '' AND phone NOT LIKE 'nx1:%')";
		} elseif ( 'decrypt' === $mode ) {
			$w = "email LIKE 'nx1:%' OR phone LIKE 'nx1:%'";
		} else {
			$w = "(email <> '' AND email_h = '') OR (phone <> '' AND phone_h = '')";
		}
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, email, phone FROM $t WHERE $w LIMIT %d", $n ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$done = 0;
		foreach ( $rows as $r ) {
			$e = self::dec( $r->email );
			$p = self::dec( $r->phone );
			if ( ( self::is_enc( $r->email ) && '' === $e ) || ( self::is_enc( $r->phone ) && '' === $p ) ) {
				continue; // Cannot decrypt (wrong key) - never overwrite.
			}
			$d = array(
				'email'   => 'encrypt' === $mode ? self::enc( $e ) : ( 'decrypt' === $mode ? $e : $r->email ),
				'phone'   => 'encrypt' === $mode ? self::enc( $p ) : ( 'decrypt' === $mode ? $p : $r->phone ),
				'email_h' => self::idx( $e, 'email' ),
				'phone_h' => self::idx( $p, 'phone' ),
			);
			if ( 'encrypt' === $mode && ( ( '' !== $e && ! self::is_enc( $d['email'] ) ) || ( '' !== $p && ! self::is_enc( $d['phone'] ) ) ) ) {
				continue; // Encryption failed - leave as is.
			}
			$wpdb->update( $t, $d, array( 'id' => (int) $r->id ) );
			$done++;
		}
		return $done;
	}

	private static function run( $mode ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$start = time();
		$total = 0;
		do {
			$n      = self::batch( $mode, 300 );
			$total += $n;
		} while ( $n > 0 && time() - $start < 45 );
		return $total;
	}

	/** Admin actions: encrypt / decrypt / save options. */
	public static function h_vault() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only site administrators can change data protection.', 'nura-experience' ) );
		}
		check_admin_referer( 'nurax_vault' );
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$back = NURAX_Desk::url( NURAX_Desk::P_SET ) . '#nd-security';
		if ( 'encrypt' === $op ) {
			if ( empty( $_POST['backup'] ) ) {
				wp_safe_redirect( add_query_arg( 'nd_msg', 'vault_backup', $back ) );
				exit;
			}
			if ( ! self::can() ) {
				wp_safe_redirect( add_query_arg( 'nd_msg', 'vault_nocrypto', $back ) );
				exit;
			}
			update_option( self::ON, 1, false );
			update_option( self::FP, self::fingerprint(), false );
			$n = self::run( 'encrypt' );
			self::log( 'Encryption turned on', $n . ' contacts encrypted' );
		} elseif ( 'continue' === $op ) {
			$n = self::run( self::on() ? 'encrypt' : 'decrypt' );
			self::log( 'Encryption batch', $n . ' contacts processed' );
		} elseif ( 'decrypt' === $op ) {
			if ( empty( $_POST['confirm'] ) ) {
				wp_safe_redirect( add_query_arg( 'nd_msg', 'vault_confirm', $back ) );
				exit;
			}
			update_option( self::ON, 0, false );
			$n = self::run( 'decrypt' );
			self::log( 'Encryption turned off', $n . ' contacts decrypted' );
		}
		self::run( 'index' );
		wp_safe_redirect( add_query_arg( 'nd_msg', 'vault_done', $back ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Retention                                                           */
	/* ------------------------------------------------------------------ */

	public static function retention() {
		global $wpdb;
		$s    = NURAX_Desk::settings();
		$days = max( 7, (int) ( isset( $s['cart_days'] ) ? $s['cart_days'] : 30 ) );
		$t    = $wpdb->prefix . 'nurax_abandoned_carts';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			$n = $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE COALESCE(updated_at, created_at) < %s", gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $n ) {
				self::log( 'Old abandoned carts deleted', (int) $n . ' older than ' . $days . ' days' );
			}
		}
		/* Keep the index complete (new rows written by older code paths). */
		if ( class_exists( 'NURAX_Desk_Mkt' ) ) {
			self::batch( 'index', 500 );
			if ( self::on() ) {
				self::batch( 'encrypt', 500 );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings card                                                       */
	/* ------------------------------------------------------------------ */

	public static function card() {
		$st  = self::stats();
		$on  = self::on();
		$enc = $st['total'] - $st['plain'];
		$pct = $st['total'] ? round( $enc / $st['total'] * 100 ) : 100;
		$fp  = get_option( self::FP );
		$adm = self::full_access();

		echo '<section class="nd-card nd-sec" id="nd-security"><header class="nd-card__h"><h2>Security &amp; customer data</h2>' . ( $on ? '<span class="nd-badge nd-badge--confirmed">Encrypted</span>' : '<span class="nd-badge nd-badge--muted">Not encrypted</span>' ) . '</header>';
		echo '<div class="nd-sec__grid">';
		echo '<div class="nd-sec__stat"><strong>' . (int) $pct . '%</strong><span>of ' . (int) $st['total'] . ' contacts encrypted (AES-256)</span></div>';
		echo '<div class="nd-sec__stat"><strong>' . esc_html( 'constant' === self::key_source() ? 'wp-config key' : 'Site salts' ) . '</strong><span>where the encryption key lives (outside the database)</span></div>';
		echo '<div class="nd-sec__stat"><strong>' . ( self::can() ? 'Ready' : 'Missing' ) . '</strong><span>server encryption (OpenSSL AES-256-GCM)</span></div>';
		echo '</div>';

		if ( $fp && $fp !== self::fingerprint() && 'salts' === self::key_source() ) {
			echo '<p class="nd-alert nd-alert--error">The site security keys in wp-config.php have changed since encryption was turned on. Encrypted contacts cannot be read until the old keys are restored.</p>';
		}
		if ( ! $adm ) {
			echo '<p class="nd-mut">Only site administrators can change data protection.</p></section>';
			return;
		}
		$form = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-sec__form"><input type="hidden" name="action" value="nurax_vault">' . wp_nonce_field( 'nurax_vault', '_wpnonce', true, false );
		if ( ! $on ) {
			echo $form . '<input type="hidden" name="op" value="encrypt"><p><strong>Encrypt customer phone numbers and e-mails.</strong> Your audience (WhatsApp contacts, newsletter and customers) is scrambled in the database. Anyone who copies the database or a backup sees only unreadable text. NURA Desk, campaigns and the shop keep working as normal.</p><label class="nd-field nd-field--check"><input type="checkbox" name="backup" value="1"><span><strong>I have a full, recent backup of the site</strong><small>Take one from your hosting panel or a backup plugin first.</small></span></label><button class="nd-btn nd-btn--primary">Encrypt now</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			if ( $st['plain'] ) {
				echo $form . '<input type="hidden" name="op" value="continue"><p>' . (int) $st['plain'] . ' contacts are still waiting to be encrypted.</p><button class="nd-btn nd-btn--primary">Finish encrypting</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<details class="nd-sec__more"><summary>Turn encryption off (undo)</summary>' . $form . '<input type="hidden" name="op" value="decrypt"><p>This writes every phone number and e-mail back in plain text.</p><label class="nd-field nd-field--check"><input type="checkbox" name="confirm" value="1"><span>Yes, decrypt all contacts</span></label><button class="nd-btn nd-btn--ghost">Decrypt everything</button></form></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'salts' === self::key_source() ) {
			$sug = wp_generate_password( 48, false, false );
			echo '<details class="nd-sec__more"><summary>Recommended: lock the key in wp-config.php</summary><p>The key is currently made from your site security keys. If someone regenerates those keys, encrypted contacts become unreadable. Ask your developer or host to add this line to <code>wp-config.php</code> (above "That\'s all, stop editing"), and keep a copy somewhere safe and offline:</p><pre class="nd-code">define( \'NURA_DATA_KEY\', \'' . esc_html( $sug ) . '\' );</pre><p class="nd-mut nd-small">Existing contacts stay readable after you add it. New and updated contacts use the new key. Never share this line or put it in the database.</p></details>';
		}

		/* Options. */
		$s = NURAX_Desk::settings();
		echo '<div class="nd-fields nd-fields--2" style="margin-top:14px">';
		echo '<label class="nd-field nd-field--check"><input type="checkbox" name="staff_export" value="1" form="nd-settings-form"' . checked( ! empty( $s['staff_export'] ), true, false ) . '><span><strong>Let shop managers download reports</strong><small>Off = only administrators can export customer data. Staff always see phone numbers and e-mails partly hidden.</small></span></label>';
		echo '<label class="nd-field"><span class="nd-label">Delete abandoned-cart data after (days)</span><input type="number" min="7" max="365" name="cart_days" form="nd-settings-form" value="' . (int) $s['cart_days'] . '"><small>Unfinished checkouts hold names, phones and e-mails. Older ones are deleted automatically every day.</small></label>';
		echo '</div>';

		/* Audit log. */
		$log = get_option( self::AUDIT, array() );
		echo '<details class="nd-sec__more" open><summary>Activity log (last 30 of ' . count( (array) $log ) . ')</summary>';
		if ( ! $log ) {
			echo '<p class="nd-mut">No activity yet. Every report download and data-protection change is recorded here with who, when and from which IP.</p>';
		} else {
			echo '<div class="nd-table-wrap"><table class="nd-table"><thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th><th>IP</th></tr></thead><tbody>';
			foreach ( array_slice( (array) $log, 0, 30 ) as $e ) {
				echo '<tr><td><small>' . esc_html( NURAX_Desk::date_label( $e['t'], 'j M Y, H:i' ) ) . '</small></td><td>' . esc_html( $e['u'] ) . '</td><td>' . esc_html( $e['w'] ) . '</td><td><small>' . esc_html( $e['d'] ) . '</small></td><td><small>' . esc_html( $e['ip'] ) . '</small></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</details></section>';
	}
}
