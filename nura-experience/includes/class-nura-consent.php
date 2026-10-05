<?php
/**
 * Marketing consent / opt-in for NURA.
 *
 * A small, purpose-built consent ledger so the automated follow-up and cart
 * recovery features only ever message shoppers who agreed to hear from NURA.
 *
 * Consent is captured at three points:
 *   - the classic WooCommerce checkout (an opt-in checkbox),
 *   - the My Account details form,
 *   - anywhere in code via NURAX_Consent::record().
 *
 * It is stored per email in a dedicated table (and mirrored to user meta for
 * logged-in customers), every marketing send is gated through has_consent(),
 * and every marketing message carries a one-click unsubscribe that writes an
 * explicit opt-out here.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NURAX_Consent {

	const DB_VERSION = '1';
	const OPTION     = 'nurax_consent';
	const USER_META  = '_nurax_marketing_consent';
	const USER_META_AT = '_nurax_marketing_consent_at';

	private static $instance = null;

	public static function instance(): NURAX_Consent {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'nurax_consent';
	}

	public function boot(): void {
		add_action( 'admin_init', array( $this, 'maybe_install' ) );

		// Capture at the classic checkout.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'checkout_checkbox' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'capture_checkout' ), 20, 3 );

		// Capture from the My Account details form.
		add_action( 'woocommerce_edit_account_form', array( $this, 'account_field' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_account' ), 20, 1 );

		// One-click unsubscribe link handler.
		add_action( 'template_redirect', array( $this, 'handle_unsub' ) );

		// Admin.
		add_action( 'admin_menu', array( $this, 'menu' ), 46 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/* --------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'checkout_optin'  => 1,
			'default_checked' => 0,
			'gate_email'      => 1,
			'gate_whatsapp'   => 1,
			'label'           => __( 'Keep me updated with NURA news, new arrivals and offers by email and WhatsApp.', 'nura-experience' ),
		);
	}

	public function settings(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $this->defaults(), $saved );
	}

	public function register_settings(): void {
		register_setting( 'nurax_consent_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();
		$out['checkout_optin']  = empty( $input['checkout_optin'] ) ? 0 : 1;
		$out['default_checked'] = empty( $input['default_checked'] ) ? 0 : 1;
		$out['gate_email']      = empty( $input['gate_email'] ) ? 0 : 1;
		$out['gate_whatsapp']   = empty( $input['gate_whatsapp'] ) ? 0 : 1;
		$out['label']           = isset( $input['label'] ) && '' !== trim( (string) $input['label'] )
			? sanitize_text_field( (string) $input['label'] )
			: $d['label'];
		return $out;
	}

	/** True when marketing on this channel must be gated by explicit consent. */
	public static function gate_active( string $channel ): bool {
		$s = self::instance()->settings();
		if ( 'whatsapp' === $channel ) {
			return ! empty( $s['gate_whatsapp'] );
		}
		return ! empty( $s['gate_email'] );
	}

	/* ---------------------------------------------------------------- storage */

	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			consent TINYINT NOT NULL DEFAULT 0,
			source VARCHAR(40) NOT NULL DEFAULT '',
			token VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY token (token)
		) {$collate};";
		dbDelta( $sql );
		update_option( 'nurax_consent_db_version', self::DB_VERSION );
	}

	public function maybe_install(): void {
		if ( get_option( 'nurax_consent_db_version' ) !== self::DB_VERSION ) {
			$this->install();
		}
	}

	private static function new_token(): string {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 24, false, false );
		}
		return md5( uniqid( (string) wp_rand(), true ) );
	}

	/**
	 * Record (or update) a consent decision for an email address.
	 *
	 * @param string $email   Recipient email.
	 * @param bool   $consent True = opted in, false = opted out.
	 * @param string $source  Where the decision came from (checkout, account, footer...).
	 * @param int    $user_id Optional WP user id to mirror the decision onto.
	 */
	public static function record( string $email, bool $consent, string $source = '', int $user_id = 0 ): void {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		$self  = self::instance();
		global $wpdb;
		$table = $self->table();
		$now   = current_time( 'mysql' );
		$flag  = $consent ? 1 : 0;

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, token FROM {$table} WHERE email = %s LIMIT 1", $email ) );
		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'consent'    => $flag,
					'source'     => substr( sanitize_text_field( $source ), 0, 40 ),
					'user_id'    => $user_id,
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing->id )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'email'      => $email,
					'user_id'    => $user_id,
					'consent'    => $flag,
					'source'     => substr( sanitize_text_field( $source ), 0, 40 ),
					'token'      => self::new_token(),
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
		}

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::USER_META, $flag );
			update_user_meta( $user_id, self::USER_META_AT, $now );
		} elseif ( ( $u = get_user_by( 'email', $email ) ) ) {
			update_user_meta( (int) $u->ID, self::USER_META, $flag );
			update_user_meta( (int) $u->ID, self::USER_META_AT, $now );
		}
	}

	/**
	 * Whether this recipient may receive marketing. Checks user meta first
	 * (explicit wins), then the consent ledger by email.
	 */
	public static function has_consent( string $email, int $user_id = 0 ): bool {
		$email = sanitize_email( $email );
		if ( $user_id <= 0 && is_email( $email ) ) {
			$u = get_user_by( 'email', $email );
			if ( $u ) {
				$user_id = (int) $u->ID;
			}
		}
		if ( $user_id > 0 ) {
			$meta = get_user_meta( $user_id, self::USER_META, true );
			if ( '1' === (string) $meta ) {
				return true;
			}
			if ( '0' === (string) $meta ) {
				return false;
			}
		}
		if ( ! is_email( $email ) ) {
			return false;
		}
		global $wpdb;
		$table = self::instance()->table();
		$val   = $wpdb->get_var( $wpdb->prepare( "SELECT consent FROM {$table} WHERE email = %s LIMIT 1", $email ) );
		return ( null !== $val && 1 === (int) $val );
	}

	/** One-click unsubscribe URL for an email (creates a token if needed). */
	public static function unsub_url( string $email ): string {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return home_url( '/' );
		}
		$self  = self::instance();
		global $wpdb;
		$table = $self->table();
		$token = $wpdb->get_var( $wpdb->prepare( "SELECT token FROM {$table} WHERE email = %s LIMIT 1", $email ) );
		if ( ! $token ) {
			// Create a pending row so the link always resolves.
			self::record( $email, true, 'link', 0 );
			$token = $wpdb->get_var( $wpdb->prepare( "SELECT token FROM {$table} WHERE email = %s LIMIT 1", $email ) );
		}
		return esc_url_raw( add_query_arg( 'nurax_unsub', (string) $token, home_url( '/' ) ) );
	}

	/* --------------------------------------------------------------- capture */

	public function checkout_checkbox(): void {
		$s = $this->settings();
		if ( empty( $s['checkout_optin'] ) || ! function_exists( 'woocommerce_form_field' ) ) {
			return;
		}
		woocommerce_form_field(
			'nurax_marketing_optin',
			array(
				'type'    => 'checkbox',
				'class'   => array( 'form-row-wide', 'nurax-optin' ),
				'label'   => $s['label'],
				'default' => empty( $s['default_checked'] ) ? 0 : 1,
			),
			! empty( $s['default_checked'] ) ? 1 : 0
		);
	}

	/**
	 * @param int      $order_id
	 * @param array    $posted
	 * @param WC_Order $order
	 */
	public function capture_checkout( $order_id, $posted, $order ): void {
		if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) {
			return;
		}
		$email = (string) $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			return;
		}
		// Checkout is nonce-verified by WooCommerce before this hook fires.
		$optin = ! empty( $_POST['nurax_marketing_optin'] );
		$uid   = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		// Only record a positive opt-in here; never imply consent from an unticked box.
		if ( $optin ) {
			self::record( $email, true, 'checkout', $uid );
		}
	}

	public function account_field(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$s       = $this->settings();
		$checked = ( '1' === (string) get_user_meta( $user_id, self::USER_META, true ) );
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label>
				<input type="checkbox" name="nurax_marketing_optin" value="1" <?php checked( $checked ); ?> />
				<?php echo esc_html( (string) $s['label'] ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * @param int $user_id
	 */
	public function save_account( $user_id ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}
		$optin = ! empty( $_POST['nurax_marketing_optin'] );
		self::record( (string) $user->user_email, $optin, 'account', $user_id );
	}

	public function handle_unsub(): void {
		if ( empty( $_GET['nurax_unsub'] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( (string) $_GET['nurax_unsub'] ) );
		if ( '' === $token ) {
			return;
		}
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, email, user_id FROM {$table} WHERE token = %s LIMIT 1", $token ) );
		if ( $row ) {
			$wpdb->update( $table, array( 'consent' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $row->id ) );
			if ( (int) $row->user_id > 0 ) {
				update_user_meta( (int) $row->user_id, self::USER_META, 0 );
			} elseif ( ( $u = get_user_by( 'email', (string) $row->email ) ) ) {
				update_user_meta( (int) $u->ID, self::USER_META, 0 );
			}
		}
		wp_safe_redirect( add_query_arg( 'nurax_unsub_done', '1', home_url( '/' ) ) );
		exit;
	}

	/* ------------------------------------------------------------- admin page */

	public function menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'NURA Marketing Consent', 'nura-experience' ),
			__( 'NURA Consent', 'nura-experience' ),
			'manage_options',
			'nurax-consent',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = $this->settings();
		global $wpdb;
		$table   = $this->table();
		$opted   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE consent = 1" );
		$opted_out = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE consent = 0" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA Marketing Consent', 'nura-experience' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Controls the opt-in shoppers see and the consent ledger that gates automated follow-ups and cart reminders. Only people who opt in are ever messaged, and every marketing message includes an unsubscribe link.', 'nura-experience' ); ?></p>
			<p class="description"><?php echo esc_html( sprintf( __( 'Currently opted in: %1$d. Opted out: %2$d.', 'nura-experience' ), $opted, $opted_out ) ); ?></p>
			<p class="description"><?php esc_html_e( 'Note: capture works on the classic WooCommerce checkout and the My Account page. If you use the newer block-based checkout, add the opt-in there or rely on the account page.', 'nura-experience' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'nurax_consent_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Checkout opt-in', 'nura-experience' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[checkout_optin]" value="1" <?php checked( ! empty( $s['checkout_optin'] ) ); ?> /> <?php esc_html_e( 'Show a marketing opt-in checkbox on the classic checkout', 'nura-experience' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default state', 'nura-experience' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_checked]" value="1" <?php checked( ! empty( $s['default_checked'] ) ); ?> /> <?php esc_html_e( 'Pre-tick the box (leave off for stricter, explicit opt-in)', 'nura-experience' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Opt-in wording', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[label]" value="<?php echo esc_attr( (string) $s['label'] ); ?>" class="large-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require consent for', 'nura-experience' ); ?></th>
						<td>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[gate_email]" value="1" <?php checked( ! empty( $s['gate_email'] ) ); ?> /> <?php esc_html_e( 'Email follow-ups and cart reminders', 'nura-experience' ); ?></label>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[gate_whatsapp]" value="1" <?php checked( ! empty( $s['gate_whatsapp'] ) ); ?> /> <?php esc_html_e( 'WhatsApp follow-ups and cart reminders (strongly recommended)', 'nura-experience' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
