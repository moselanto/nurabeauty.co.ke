<?php
/**
 * NURA Experience settings.
 *
 * A single place to (optionally) plug real AI providers into the Wig Finder and
 * Virtual Try-On, and to toggle features. Everything works without keys using
 * the built-in rule-based recommender and client-side try-on; keys simply
 * upgrade those to full AI.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Settings {

	const OPTION = 'nurax_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function menu() {
		add_options_page( 'NURA Experience', 'NURA Experience', 'manage_options', 'nurax', array( $this, 'page' ) );
	}

	public function register() {
		register_setting( 'nurax', self::OPTION );
	}

	public static function get( $key, $default = '' ) {
		$opts = get_option( self::OPTION, array() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	public function page() {
		$o = get_option( self::OPTION, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA Experience', 'nura-experience' ); ?></h1>
			<p><?php esc_html_e( 'The three exclusive features work out of the box. Add API details below only if you want to upgrade the AI Wig Finder to real face-shape vision analysis, or the Virtual Try-On to auto-aligned face tracking.', 'nura-experience' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'nurax' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'AI vision API endpoint', 'nura-experience' ); ?></th>
						<td><input type="url" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[ai_endpoint]" value="<?php echo esc_attr( $o['ai_endpoint'] ?? '' ); ?>" placeholder="https://api.provider.com/face-shape"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'AI API key', 'nura-experience' ); ?></th>
						<td><input type="password" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[ai_key]" value="<?php echo esc_attr( $o['ai_key'] ?? '' ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Try-On face-tracking provider (JS)', 'nura-experience' ); ?></th>
						<td><input type="text" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[tryon_provider]" value="<?php echo esc_attr( $o['tryon_provider'] ?? '' ); ?>" placeholder="e.g. mediapipe, banuba, custom"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'VIP membership price (KES/yr)', 'nura-experience' ); ?></th>
						<td><input type="number" name="<?php echo esc_attr( self::OPTION ); ?>[member_price]" value="<?php echo esc_attr( $o['member_price'] ?? '6000' ); ?>"></td>
					</tr>
				<tr><th colspan="2" style="padding-top:1.4em"><h2 style="margin:0"><?php esc_html_e( 'AI Stylist (chat concierge)', 'nura-experience' ); ?></h2><p style="font-weight:400;max-width:640px"><?php esc_html_e( 'A luxury chat widget appears on every page. Add an API key to power it with real AI; without a key it runs a smart guided assistant with product picks and WhatsApp hand-off.', 'nura-experience' ); ?></p></th></tr>
					<tr>
						<th><?php esc_html_e( 'Enable AI Stylist', 'nura-experience' ); ?></th>
						<td><select name="<?php echo esc_attr( self::OPTION ); ?>[stylist_enable]"><option value="on" <?php selected( $o['stylist_enable'] ?? 'on', 'on' ); ?>>On</option><option value="off" <?php selected( $o['stylist_enable'] ?? 'on', 'off' ); ?>>Off</option></select></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'AI provider', 'nura-experience' ); ?></th>
						<td><select name="<?php echo esc_attr( self::OPTION ); ?>[stylist_provider]"><option value="openai" <?php selected( $o['stylist_provider'] ?? 'openai', 'openai' ); ?>>OpenAI</option><option value="gemini" <?php selected( $o['stylist_provider'] ?? 'openai', 'gemini' ); ?>>Google Gemini</option><option value="off" <?php selected( $o['stylist_provider'] ?? 'openai', 'off' ); ?>>Guided only (no key)</option></select></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'AI API key', 'nura-experience' ); ?></th>
						<td><input type="password" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[stylist_key]" value="<?php echo esc_attr( $o['stylist_key'] ?? '' ); ?>" placeholder="sk-...  or  AIza..."></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'AI model', 'nura-experience' ); ?></th>
						<td><input type="text" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[stylist_model]" value="<?php echo esc_attr( $o['stylist_model'] ?? '' ); ?>" placeholder="gpt-4o-mini   (or gemini-1.5-flash)"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WhatsApp link or number', 'nura-experience' ); ?></th>
						<td><input type="text" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp]" value="<?php echo esc_attr( $o['whatsapp'] ?? '' ); ?>" placeholder="https://wa.me/2547XXXXXXXX"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Stylist greeting', 'nura-experience' ); ?></th>
						<td><textarea style="width:420px;height:60px" name="<?php echo esc_attr( self::OPTION ); ?>[stylist_greeting]"><?php echo esc_textarea( $o['stylist_greeting'] ?? '' ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Extra brand notes for the AI', 'nura-experience' ); ?></th>
						<td><textarea style="width:420px;height:90px" name="<?php echo esc_attr( self::OPTION ); ?>[stylist_prompt]" placeholder="Delivery areas, return policy, tone, current promotions..."><?php echo esc_textarea( $o['stylist_prompt'] ?? '' ); ?></textarea></td>
					</tr>
					</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
