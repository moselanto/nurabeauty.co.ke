<?php
/**
 * WhatsApp inbound bot for the NURA AI Stylist.
 *
 * Wires the Meta WhatsApp Cloud API INBOUND side to the same NURA_AI brain used
 * on the website. A customer messages the business number -> Meta calls this
 * webhook -> we run the text through NURAX_AI -> we reply on WhatsApp through the
 * NURAX_WhatsApp sender.
 *
 * Compliance: this only ever REPLIES to a customer who messaged the business
 * first, inside WhatsApp's 24-hour session window - no cold outreach. Business-
 * initiated template messages are a later phase.
 *
 * Security:
 *   - GET handshake checked against a verify token (env/constant or setting).
 *   - POST payloads verified with X-Hub-Signature-256 against the Meta app
 *     secret, read ONLY from an env var / wp-config constant.
 *   - Message ids are de-duplicated so Meta retries never double-reply.
 *   - Per-sender rate limiting protects the free AI tiers.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NURAX_WhatsApp_Bot {

	const OPTION     = 'nurax_wa_bot';
	const REST_NS    = 'nurax/v1';
	const REST_ROUTE = '/whatsapp';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 45 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/* --------------------------------------------------------------- secrets */

	/** Resolve a secret from env first, then a wp-config constant. Never the DB. */
	private static function secret( string $name ): string {
		$env = getenv( $name );
		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}
		if ( defined( $name ) ) {
			$val = constant( $name );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				return trim( $val );
			}
		}
		return '';
	}

	/** Meta app secret for signature verification (server-side only). */
	private function app_secret(): string {
		$s = self::secret( 'WHATSAPP_APP_SECRET' );
		if ( '' === $s ) {
			$s = self::secret( 'NURAX_WA_APP_SECRET' );
		}
		return $s;
	}

	/** Webhook verify token: env/constant override, then the setting. */
	public function verify_token(): string {
		$s = self::secret( 'NURAX_WA_VERIFY_TOKEN' );
		if ( '' !== $s ) {
			return $s;
		}
		$opt = $this->settings();
		return (string) $opt['verify_token'];
	}

	/* -------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'enabled'      => 0,
			'verify_token' => '',
			'rate_max'     => 15,
			'rate_window'  => 600,
			'history_min'  => 30,
			'phone_id'     => '',
			'api_version'  => 'v21.0',
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
		register_setting( 'nurax_wa_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();

		$out['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
		$out['verify_token'] = isset( $input['verify_token'] ) ? trim( sanitize_text_field( (string) $input['verify_token'] ) ) : '';
		$out['rate_max']     = max( 0, min( 1000, isset( $input['rate_max'] ) ? (int) $input['rate_max'] : $d['rate_max'] ) );
		$out['rate_window']  = max( 30, min( 86400, isset( $input['rate_window'] ) ? (int) $input['rate_window'] : $d['rate_window'] ) );
		$out['history_min']  = max( 1, min( 240, isset( $input['history_min'] ) ? (int) $input['history_min'] : $d['history_min'] ) );
		$out['phone_id']     = isset( $input['phone_id'] ) ? trim( sanitize_text_field( (string) $input['phone_id'] ) ) : '';
		$out['api_version']  = isset( $input['api_version'] ) && '' !== trim( (string) $input['api_version'] ) ? trim( sanitize_text_field( (string) $input['api_version'] ) ) : 'v21.0';

		return $out;
	}

	/* ------------------------------------------------------------ REST routes */

	public function register_routes(): void {
		register_rest_route(
			self::REST_NS,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'verify' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'receive' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/** GET handshake: echo hub.challenge when the verify token matches. */
	public function verify( WP_REST_Request $request ) {
		$mode      = (string) $request->get_param( 'hub.mode' );
		$token     = (string) $request->get_param( 'hub.verify_token' );
		$challenge = $request->get_param( 'hub.challenge' );

		$expected = $this->verify_token();
		if ( 'subscribe' === $mode && '' !== $expected && hash_equals( $expected, $token ) ) {
			return new WP_REST_Response( is_numeric( $challenge ) ? (int) $challenge : (string) $challenge, 200 );
		}
		return new WP_REST_Response( 'Forbidden', 403 );
	}

	/** POST: verify signature, dedupe, run the assistant, reply on WhatsApp. */
	public function receive( WP_REST_Request $request ) {
		$s = $this->settings();

		$raw = (string) $request->get_body();

		// Signature verification (only when the app secret is configured).
		$secret = $this->app_secret();
		if ( '' !== $secret ) {
			$sig      = (string) $request->get_header( 'x-hub-signature-256' );
			$expected = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
			if ( '' === $sig || ! hash_equals( $expected, $sig ) ) {
				return new WP_REST_Response( array( 'ok' => false ), 403 );
			}
		}

		// Always acknowledge, even when disabled, so Meta does not retry.
		if ( empty( $s['enabled'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['entry'] ) || ! is_array( $data['entry'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		foreach ( $data['entry'] as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['changes'] ) || ! is_array( $entry['changes'] ) ) {
				continue;
			}
			foreach ( $entry['changes'] as $change ) {
				$value = ( is_array( $change ) && isset( $change['value'] ) && is_array( $change['value'] ) ) ? $change['value'] : array();
				if ( empty( $value['messages'] ) || ! is_array( $value['messages'] ) ) {
					continue; // Ignore status/read receipts and other events.
				}
				$names = $this->name_map( isset( $value['contacts'] ) ? (array) $value['contacts'] : array() );
				foreach ( $value['messages'] as $message ) {
					if ( is_array( $message ) ) {
						$this->handle_one( $message, $names, $s );
					}
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/** Map wa_id => profile name from the webhook contacts array. */
	private function name_map( array $contacts ): array {
		$out = array();
		foreach ( $contacts as $c ) {
			if ( is_array( $c ) && isset( $c['wa_id'] ) ) {
				$out[ (string) $c['wa_id'] ] = isset( $c['profile']['name'] ) ? (string) $c['profile']['name'] : '';
			}
		}
		return $out;
	}

	private function handle_one( array $message, array $names, array $s ): void {
		$id   = isset( $message['id'] ) ? (string) $message['id'] : '';
		$from = isset( $message['from'] ) ? preg_replace( '/[^0-9]/', '', (string) $message['from'] ) : '';
		$type = isset( $message['type'] ) ? (string) $message['type'] : '';

		if ( '' === $id || '' === $from ) {
			return;
		}

		// De-dupe FIRST so Meta retries never double-process.
		$seen_key = 'nurax_wa_seen_' . md5( $id );
		if ( false !== get_transient( $seen_key ) ) {
			return;
		}
		set_transient( $seen_key, 1, 6 * HOUR_IN_SECONDS );

		// Only text is supported; nudge once (deduped by this message id) otherwise.
		if ( 'text' !== $type || empty( $message['text']['body'] ) ) {
			NURAX_WhatsApp::send_text( $from, __( 'Hello from NURA! I can help over text right now. Please type your question about our wigs, prices, delivery or your order.', 'nura-experience' ) );
			return;
		}

		$text = trim( (string) $message['text']['body'] );
		$name = isset( $names[ $from ] ) ? (string) $names[ $from ] : '';

		// Per-sender rate limit (protects the free AI tiers).
		if ( ! $this->rate_ok( $from, $s ) ) {
			NURAX_WhatsApp::send_text( $from, __( 'Thanks for your messages! Please give me a moment and send your next question shortly.', 'nura-experience' ) );
			return;
		}

		if ( ! class_exists( 'NURAX_AI' ) ) {
			return;
		}

		$hist_key = 'nurax_wa_hist_' . md5( $from );
		$history  = get_transient( $hist_key );
		$history  = is_array( $history ) ? $history : array();

		$out = NURAX_AI::instance()->handle_message(
			$text,
			$history,
			array(
				'name'  => $name,
				'phone' => $from,
				'email' => '',
			),
			'whatsapp',
			false
		);

		$reply = isset( $out['reply'] ) ? (string) $out['reply'] : '';
		if ( '' === trim( $reply ) ) {
			$reply = __( 'Thank you for your message. A NURA stylist will follow up with you shortly.', 'nura-experience' );
		}

		NURAX_WhatsApp::send_text( $from, $reply );

		// Persist a short rolling context for continuity (transient, expires).
		$history[] = array(
			'role'    => 'user',
			'content' => $text,
		);
		$history[] = array(
			'role'    => 'assistant',
			'content' => $reply,
		);
		if ( count( $history ) > 8 ) {
			$history = array_slice( $history, -8 );
		}
		set_transient( $hist_key, $history, max( 1, (int) $s['history_min'] ) * MINUTE_IN_SECONDS );
	}

	private function rate_ok( string $from, array $s ): bool {
		$max = (int) $s['rate_max'];
		$win = (int) $s['rate_window'];
		if ( $max <= 0 ) {
			return true;
		}
		$key = 'nurax_wa_rl_' . md5( $from );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $key, $n + 1, $win );
		return true;
	}

	/* ------------------------------------------------------------- admin page */

	public function menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'NURA WhatsApp Assistant', 'nura-experience' ),
			__( 'NURA WhatsApp', 'nura-experience' ),
			'manage_options',
			'nurax-whatsapp',
			array( $this, 'render' )
		);
	}

	private function status_dot( bool $ok, string $yes, string $no ): string {
		return $ok
			? '<span style="color:#1f7a3d;font-weight:600">' . esc_html( $yes ) . '</span>'
			: '<span style="color:#b00020;font-weight:600">' . esc_html( $no ) . '</span>';
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = $this->settings();
		$webhook   = rest_url( self::REST_NS . self::REST_ROUTE );
		$send_ok   = class_exists( 'NURAX_WhatsApp' ) && NURAX_WhatsApp::is_configured();
		$ai_ok     = class_exists( 'NURAX_AI' ) && NURAX_AI::instance()->is_enabled();
		$secret_ok = '' !== $this->app_secret();
		$token_ok  = '' !== $this->verify_token();
		$last_err  = class_exists( 'NURAX_WhatsApp' ) ? NURAX_WhatsApp::last_error() : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA WhatsApp Assistant', 'nura-experience' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Let customers chat with the NURA stylist on WhatsApp. Incoming messages are answered by the same AI used on the website and replies are sent from your WhatsApp Business number. Replies are session messages (within WhatsApp\'s 24-hour window) - no cold outreach.', 'nura-experience' ); ?></p>

			<h2><?php esc_html_e( 'Connect your webhook', 'nura-experience' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Callback URL', 'nura-experience' ); ?></th>
					<td><input type="text" readonly class="large-text" value="<?php echo esc_attr( $webhook ); ?>" onfocus="this.select()" />
						<p class="description"><?php esc_html_e( 'Paste this into Meta - WhatsApp - Configuration - Webhook - Callback URL, then subscribe to the "messages" field.', 'nura-experience' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Status', 'nura-experience' ); ?></h2>
			<table class="widefat striped" style="max-width:760px">
				<tbody>
					<tr><td><strong><?php esc_html_e( 'Assistant (AI)', 'nura-experience' ); ?></strong></td><td><?php echo wp_kses_post( $this->status_dot( $ai_ok, __( 'enabled', 'nura-experience' ), __( 'disabled - enable it on the NURA AI Assistant page and add an API key', 'nura-experience' ) ) ); ?></td></tr>
					<tr><td><strong><?php esc_html_e( 'Sending (WhatsApp Cloud API)', 'nura-experience' ); ?></strong></td><td><?php echo wp_kses_post( $this->status_dot( $send_ok, __( 'configured', 'nura-experience' ), __( 'not configured - set the access token (wp-config) and phone number ID below', 'nura-experience' ) ) ); ?></td></tr>
					<tr><td><strong><?php esc_html_e( 'Verify token', 'nura-experience' ); ?></strong></td><td><?php echo wp_kses_post( $this->status_dot( $token_ok, __( 'set', 'nura-experience' ), __( 'not set', 'nura-experience' ) ) ); ?></td></tr>
					<tr><td><strong><?php esc_html_e( 'Signature verification (app secret)', 'nura-experience' ); ?></strong></td><td><?php echo wp_kses_post( $this->status_dot( $secret_ok, __( 'detected', 'nura-experience' ), __( 'not set - recommended (define WHATSAPP_APP_SECRET in wp-config.php)', 'nura-experience' ) ) ); ?></td></tr>
				</tbody>
			</table>
			<?php if ( '' !== $last_err ) : ?>
				<p class="description" style="color:#b00020"><?php echo esc_html( sprintf( __( 'Last send error: %s', 'nura-experience' ), $last_err ) ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Settings', 'nura-experience' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'nurax_wa_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'WhatsApp assistant', 'nura-experience' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Reply to incoming WhatsApp messages', 'nura-experience' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Business phone number ID', 'nura-experience' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[phone_id]" value="<?php echo esc_attr( (string) $s['phone_id'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'From Meta - WhatsApp - API Setup. (Not secret. The access token is NOT entered here - define WHATSAPP_TOKEN in wp-config.php.)', 'nura-experience' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API version', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[api_version]" value="<?php echo esc_attr( (string) $s['api_version'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( 'Leave as v21.0 unless Meta tells you otherwise.', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verify token', 'nura-experience' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[verify_token]" value="<?php echo esc_attr( (string) $s['verify_token'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Any hard-to-guess string you choose. Enter the exact same value in Meta when you set up the webhook. (A NURAX_WA_VERIFY_TOKEN constant, if defined, overrides this.)', 'nura-experience' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate limit', 'nura-experience' ); ?></th>
						<td>
							<input type="number" min="0" max="1000" name="<?php echo esc_attr( self::OPTION ); ?>[rate_max]" value="<?php echo esc_attr( (string) $s['rate_max'] ); ?>" class="small-text" />
							<?php esc_html_e( 'messages per', 'nura-experience' ); ?>
							<input type="number" min="30" max="86400" name="<?php echo esc_attr( self::OPTION ); ?>[rate_window]" value="<?php echo esc_attr( (string) $s['rate_window'] ); ?>" class="small-text" />
							<?php esc_html_e( 'seconds, per sender. 0 = no limit.', 'nura-experience' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Conversation memory', 'nura-experience' ); ?></th>
						<td>
							<input type="number" min="1" max="240" name="<?php echo esc_attr( self::OPTION ); ?>[history_min]" value="<?php echo esc_attr( (string) $s['history_min'] ); ?>" class="small-text" />
							<?php esc_html_e( 'minutes of recent context kept per sender (for continuity; expires automatically).', 'nura-experience' ); ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Setup steps', 'nura-experience' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In wp-config.php add: define( \'WHATSAPP_TOKEN\', \'your-permanent-token\' ); and (recommended) define( \'WHATSAPP_APP_SECRET\', \'your-app-secret\' );', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Enter the Business phone number ID above and make sure the NURA AI Assistant is enabled with a working API key.', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Choose a verify token above, tick "Reply to incoming WhatsApp messages", save, and copy the token.', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'In Meta (developers.facebook.com) open your app - WhatsApp - Configuration. Set the Callback URL above and the same verify token, then Verify and save.', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Under Webhook fields, Subscribe to "messages".', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Send a WhatsApp message to your business number and confirm you get an AI reply.', 'nura-experience' ); ?></li>
			</ol>
		</div>
		<?php
	}
}
