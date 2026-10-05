<?php
/**
 * NURA AI orchestrator - the single "brain" behind the stylist.
 *
 * Selects an available provider (Google Gemini / Groq / OpenAI, in that order
 * unless overridden), grounds every reply in NURA's live WooCommerce catalogue
 * and brand facts, keeps replies on-brand and honest (never invents prices or
 * stock), and offers a WhatsApp / human hand-off when unsure. Both the website
 * chat and the WhatsApp bot call handle_message().
 *
 * Requires class-nura-ai-providers.php. API keys live only in env / wp-config
 * (GEMINI_API_KEY, GROQ_API_KEY, OPENAI_API_KEY) - never in the database.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NURAX_AI {

	const OPTION       = 'nurax_ai';
	const STATS_OPTION = 'nurax_ai_stats';
	const CTX_TRANSIENT = 'nurax_ai_catalog_ctx';

	private static $instance = null;
	private static $booted   = false;

	public function __construct() {
		if ( null === self::$instance ) {
			self::$instance = $this;
		}
		if ( ! self::$booted ) {
			self::$booted = true;
			add_action( 'admin_menu', array( $this, 'menu' ), 44 );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'save_post_product', array( $this, 'flush_catalog_ctx' ) );
		}
	}

	public static function instance(): NURAX_AI {
		return self::$instance instanceof self ? self::$instance : new self();
	}

	/* -------------------------------------------------------------- settings */

	public function defaults(): array {
		return array(
			'enabled'      => 0,
			'provider'     => 'auto',
			'gemini_model' => NURAX_AI_Provider_Gemini::DEFAULT_MODEL,
			'groq_model'   => NURAX_AI_Provider_Groq::DEFAULT_MODEL,
			'openai_model' => NURAX_AI_Provider_OpenAI::DEFAULT_MODEL,
			'temperature'  => 0.4,
			'extra_prompt' => '',
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
		register_setting( 'nurax_ai_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		$d   = $this->defaults();
		$out = array();

		$out['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
		$prov                = isset( $input['provider'] ) ? sanitize_text_field( (string) $input['provider'] ) : 'auto';
		$out['provider']     = in_array( $prov, array( 'auto', 'gemini', 'groq', 'openai' ), true ) ? $prov : 'auto';
		$out['gemini_model'] = isset( $input['gemini_model'] ) && '' !== trim( (string) $input['gemini_model'] ) ? sanitize_text_field( (string) $input['gemini_model'] ) : $d['gemini_model'];
		$out['groq_model']   = isset( $input['groq_model'] ) && '' !== trim( (string) $input['groq_model'] ) ? sanitize_text_field( (string) $input['groq_model'] ) : $d['groq_model'];
		$out['openai_model'] = isset( $input['openai_model'] ) && '' !== trim( (string) $input['openai_model'] ) ? sanitize_text_field( (string) $input['openai_model'] ) : $d['openai_model'];
		$temp                = isset( $input['temperature'] ) ? (float) $input['temperature'] : $d['temperature'];
		$out['temperature']  = max( 0, min( 1, $temp ) );
		$out['extra_prompt'] = isset( $input['extra_prompt'] ) ? sanitize_textarea_field( (string) $input['extra_prompt'] ) : '';

		return $out;
	}

	public function temperature(): float {
		$s = $this->settings();
		return (float) $s['temperature'];
	}

	/* ------------------------------------------------------------- providers */

	/** All providers, model overrides applied. */
	public function providers(): array {
		$s = $this->settings();
		return array(
			'gemini' => new NURAX_AI_Provider_Gemini( (string) $s['gemini_model'] ),
			'groq'   => new NURAX_AI_Provider_Groq( (string) $s['groq_model'] ),
			'openai' => new NURAX_AI_Provider_OpenAI( (string) $s['openai_model'] ),
		);
	}

	/** Providers with a key present server-side, keyed by id. */
	public function available_providers(): array {
		$out = array();
		foreach ( $this->providers() as $id => $p ) {
			if ( $p->is_available() ) {
				$out[ $id ] = $p;
			}
		}
		return $out;
	}

	/** Chosen provider (honours the preference; auto = gemini, groq, openai). */
	public function pick_provider() {
		$s    = $this->settings();
		$all  = $this->providers();
		$pref = (string) $s['provider'];

		if ( 'auto' !== $pref && isset( $all[ $pref ] ) && $all[ $pref ]->is_available() ) {
			return $all[ $pref ];
		}
		foreach ( array( 'gemini', 'groq', 'openai' ) as $id ) {
			if ( isset( $all[ $id ] ) && $all[ $id ]->is_available() ) {
				return $all[ $id ];
			}
		}
		return null;
	}

	/** Enabled AND at least one provider has a key. */
	public function is_enabled(): bool {
		$s = $this->settings();
		return ! empty( $s['enabled'] ) && null !== $this->pick_provider();
	}

	/* -------------------------------------------------------------- grounding */

	private function brand( string $key, string $default = '' ): string {
		if ( function_exists( 'nura_opt' ) ) {
			$v = (string) nura_opt( $key );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return $default;
	}

	public function flush_catalog_ctx(): void {
		delete_transient( self::CTX_TRANSIENT );
	}

	/** A compact, cached snapshot of live categories + in-stock products. */
	private function catalog_context(): string {
		$cached = get_transient( self::CTX_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$lines = array();

		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 20,
			)
		);
		if ( is_array( $cats ) && $cats ) {
			$names = array();
			foreach ( $cats as $c ) {
				if ( is_object( $c ) && isset( $c->name ) ) {
					$names[] = $c->name;
				}
			}
			if ( $names ) {
				$lines[] = 'Categories: ' . implode( ', ', $names );
			}
		}

		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => 20,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'stock_status' => 'instock',
				)
			);
			if ( is_array( $products ) && $products ) {
				$lines[] = 'In-stock products (name - price - link):';
				foreach ( $products as $p ) {
					if ( ! is_object( $p ) ) {
						continue;
					}
					$price = html_entity_decode( wp_strip_all_tags( (string) $p->get_price_html() ), ENT_QUOTES );
					$price = trim( preg_replace( '/\s+/', ' ', $price ) );
					$lines[] = '- ' . $p->get_name() . ' - ' . ( '' !== $price ? $price : 'see site' ) . ' - ' . get_permalink( $p->get_id() );
				}
			}
		}

		$ctx = implode( "\n", $lines );
		if ( '' === $ctx ) {
			$ctx = 'Catalogue is being set up; direct the shopper to the shop page.';
		}
		set_transient( self::CTX_TRANSIENT, $ctx, HOUR_IN_SECONDS );
		return $ctx;
	}

	private function build_system_prompt( string $channel, array $contact ): string {
		$name     = $this->brand( 'nura_brand_name', 'NURA' );
		$phone    = $this->brand( 'nura_phone', '' );
		$whatsapp = $this->brand( 'nura_whatsapp', '' );
		$email    = $this->brand( 'nura_email', '' );
		$announce = $this->brand( 'nura_announcement', '' );
		$shop     = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );

		$extra    = class_exists( 'NURAX_Settings' ) ? (string) NURAX_Settings::get( 'stylist_prompt', '' ) : '';
		$opt_extra = (string) $this->settings()['extra_prompt'];

		$who  = $contact['name'] ?? '';
		$len  = 'whatsapp' === $channel ? 'Keep replies short and friendly (2-5 sentences) - this is WhatsApp.' : 'Keep replies concise and helpful.';

		$p  = "You are the NURA stylist concierge for {$name}, a luxury human-hair wig and beauty store based in Nairobi, Kenya. ";
		$p .= "You help shoppers choose wigs and answer questions about products, prices, availability, delivery and payment. ";
		$p .= "Currency is Kenyan Shillings (KES). Accepted payments: M-Pesa, Cash on Delivery, and Bank Transfer. ";
		$p .= "{$len} Be warm, elegant and practical - never pushy. ";
		$p .= "Only state prices, product names and stock from the catalogue facts below; if something is not listed, say you'll check and offer the shop link or a human follow-up rather than guessing. ";
		$p .= "Never invent discounts, delivery times or policies. Do not give medical or scalp-condition advice; suggest a professional if asked. ";
		if ( '' !== $whatsapp || '' !== $phone ) {
			$p .= 'For anything you cannot resolve, offer to connect them with the NURA team' . ( '' !== $phone ? " on {$phone}" : '' ) . '. ';
		}
		$p .= "Shop link: {$shop}. ";
		if ( '' !== $announce ) {
			$p .= "Current store note: {$announce}. ";
		}
		if ( '' !== $who ) {
			$p .= "The shopper's name is {$who}. ";
		}
		if ( '' !== trim( $extra ) ) {
			$p .= "\n\nBrand notes: " . trim( $extra );
		}
		if ( '' !== trim( $opt_extra ) ) {
			$p .= "\n\nAdditional instructions: " . trim( $opt_extra );
		}
		$p .= "\n\nCatalogue facts:\n" . $this->catalog_context();

		return $p;
	}

	private function fallback_reply(): string {
		$whatsapp = $this->brand( 'nura_whatsapp', '' );
		$shop     = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
		$msg      = __( 'Thanks for reaching out to NURA. Our team will help you shortly.', 'nura-experience' );
		if ( '' !== $shop ) {
			$msg .= ' ' . sprintf( __( 'You can also browse our collection here: %s', 'nura-experience' ), $shop );
		}
		return $msg;
	}

	/* --------------------------------------------------------------- run */

	/**
	 * Handle one turn. Returns array{ok:bool,reply:string,provider:string}.
	 *
	 * @param string                                       $text    The customer's message.
	 * @param array<int,array{role:string,content:string}> $history Prior turns (oldest first).
	 * @param array<string,string>                         $contact name / phone / email if known.
	 * @param string                                       $channel 'web' or 'whatsapp'.
	 * @param bool                                         $web     Reserved for channel-specific behaviour.
	 */
	public function handle_message( string $text, array $history = array(), array $contact = array(), string $channel = 'web', bool $web = false ): array {
		$text = trim( $text );
		if ( '' === $text ) {
			return array(
				'ok'       => false,
				'reply'    => $this->fallback_reply(),
				'provider' => '',
			);
		}

		$provider = $this->pick_provider();
		if ( null === $provider ) {
			return array(
				'ok'       => false,
				'reply'    => $this->fallback_reply(),
				'provider' => '',
			);
		}

		$system   = $this->build_system_prompt( $channel, $contact );
		$messages = array();
		foreach ( $history as $h ) {
			if ( isset( $h['role'], $h['content'] ) && in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
				$messages[] = array(
					'role'    => (string) $h['role'],
					'content' => (string) $h['content'],
				);
			}
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $text,
		);

		$opts = array(
			'temperature' => $this->temperature(),
			'max_tokens'  => 'whatsapp' === $channel ? 400 : 600,
		);

		$res = $provider->chat( $system, $messages, $opts );

		if ( empty( $res['ok'] ) ) {
			$this->record_stat( false, $provider->id(), isset( $res['error'] ) ? (string) $res['error'] : 'unknown error' );
			return array(
				'ok'       => false,
				'reply'    => $this->fallback_reply(),
				'provider' => $provider->id(),
			);
		}

		$this->record_stat( true, $provider->id(), '' );
		return array(
			'ok'       => true,
			'reply'    => (string) $res['text'],
			'provider' => $provider->id(),
		);
	}

	private function record_stat( bool $ok, string $provider, string $error ): void {
		$s = get_option( self::STATS_OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		$s['sent']          = (int) ( $s['sent'] ?? 0 ) + ( $ok ? 1 : 0 );
		$s['errors']        = (int) ( $s['errors'] ?? 0 ) + ( $ok ? 0 : 1 );
		$s['last_provider'] = $provider;
		$s['last_ts']       = time();
		if ( ! $ok ) {
			$s['last_error'] = $error;
		}
		update_option( self::STATS_OPTION, $s, false );
	}

	/* ------------------------------------------------------------- admin page */

	public function menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'NURA AI Assistant', 'nura-experience' ),
			__( 'NURA AI', 'nura-experience' ),
			'manage_options',
			'nurax-ai',
			array( $this, 'render' )
		);
	}

	private function dot( bool $ok, string $yes, string $no ): string {
		return $ok
			? '<span style="color:#1f7a3d;font-weight:600">' . esc_html( $yes ) . '</span>'
			: '<span style="color:#8a6d00;font-weight:600">' . esc_html( $no ) . '</span>';
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = $this->settings();
		$avail = $this->available_providers();
		$stats = get_option( self::STATS_OPTION, array() );
		$stats = is_array( $stats ) ? $stats : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA AI Assistant', 'nura-experience' ); ?></h1>
			<p class="description"><?php esc_html_e( 'One AI brain for the website stylist and the WhatsApp assistant. It answers from your live catalogue and brand facts. API keys are read only from wp-config.php / server environment - never stored here.', 'nura-experience' ); ?></p>

			<h2><?php esc_html_e( 'Provider keys detected (server-side)', 'nura-experience' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
					<tr><td><strong>Google Gemini</strong> <code>GEMINI_API_KEY</code></td><td><?php echo wp_kses_post( $this->dot( isset( $avail['gemini'] ), __( 'detected', 'nura-experience' ), __( 'not set', 'nura-experience' ) ) ); ?></td></tr>
					<tr><td><strong>Groq</strong> <code>GROQ_API_KEY</code></td><td><?php echo wp_kses_post( $this->dot( isset( $avail['groq'] ), __( 'detected', 'nura-experience' ), __( 'not set', 'nura-experience' ) ) ); ?></td></tr>
					<tr><td><strong>OpenAI</strong> <code>OPENAI_API_KEY</code></td><td><?php echo wp_kses_post( $this->dot( isset( $avail['openai'] ), __( 'detected', 'nura-experience' ), __( 'not set', 'nura-experience' ) ) ); ?></td></tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Add whichever you have to wp-config.php, e.g. define( \'GEMINI_API_KEY\', \'AIza...\' );', 'nura-experience' ); ?></p>

			<?php if ( ! empty( $stats ) ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: ok count, 2: error count, 3: last provider */
							__( 'Replies sent: %1$d - errors: %2$d - last provider: %3$s', 'nura-experience' ),
							(int) ( $stats['sent'] ?? 0 ),
							(int) ( $stats['errors'] ?? 0 ),
							(string) ( $stats['last_provider'] ?? '-' )
						)
					);
					?>
					<?php if ( ! empty( $stats['last_error'] ) ) : ?>
						<br><span style="color:#b00020"><?php echo esc_html( sprintf( __( 'Last error: %s', 'nura-experience' ), (string) $stats['last_error'] ) ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Settings', 'nura-experience' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'nurax_ai_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable AI assistant', 'nura-experience' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php esc_html_e( 'Use AI to answer (website + WhatsApp). Needs at least one key above.', 'nura-experience' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Preferred provider', 'nura-experience' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[provider]">
								<option value="auto" <?php selected( $s['provider'], 'auto' ); ?>><?php esc_html_e( 'Auto (Gemini, then Groq, then OpenAI)', 'nura-experience' ); ?></option>
								<option value="gemini" <?php selected( $s['provider'], 'gemini' ); ?>>Google Gemini</option>
								<option value="groq" <?php selected( $s['provider'], 'groq' ); ?>>Groq</option>
								<option value="openai" <?php selected( $s['provider'], 'openai' ); ?>>OpenAI</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Gemini model', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[gemini_model]" value="<?php echo esc_attr( (string) $s['gemini_model'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Groq model', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[groq_model]" value="<?php echo esc_attr( (string) $s['groq_model'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OpenAI model', 'nura-experience' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[openai_model]" value="<?php echo esc_attr( (string) $s['openai_model'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Creativity (temperature)', 'nura-experience' ); ?></th>
						<td><input type="number" step="0.1" min="0" max="1" name="<?php echo esc_attr( self::OPTION ); ?>[temperature]" value="<?php echo esc_attr( (string) $s['temperature'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e( '0 = precise, 1 = creative. 0.4 is a good default.', 'nura-experience' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Extra instructions for the AI', 'nura-experience' ); ?></th>
						<td><textarea name="<?php echo esc_attr( self::OPTION ); ?>[extra_prompt]" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Delivery areas, return policy, current promotions, tone...', 'nura-experience' ); ?>"><?php echo esc_textarea( (string) $s['extra_prompt'] ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
