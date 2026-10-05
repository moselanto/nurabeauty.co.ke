<?php
/**
 * AI Wig Finder - multi-step consultation.
 *
 * A guided, Green-World-style consultation: face shape -> texture/length ->
 * occasion -> budget -> your details + consent. Returns real product matches,
 * captures the lead (emails the store), and hands off to a prefilled WhatsApp
 * booking. Ships with a transparent rule-based recommender so it works out of
 * the box; a real vision model can be attached via the nurax_face_analysis
 * filter to turn an uploaded selfie into a face-shape value.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_AI_Wig_Finder {

	public function __construct() {
		add_shortcode( 'nura_ai_wig_finder', array( $this, 'render' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route( 'nurax/v1', '/wig-finder', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'recommend' ),
			'permission_callback' => '__return_true',
		) );
	}

	private function step_opts( $field, $opts ) {
		$html = '<div class="nurax-opts" data-field="' . esc_attr( $field ) . '">';
		foreach ( $opts as $val => $label ) {
			$html .= '<button type="button" data-value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</button>';
		}
		$html .= '</div>';
		return $html;
	}

	/** The consultation wizard UI. */
	public function render() {
		ob_start(); ?>
		<div class="nurax-finder" data-nurax-finder>
			<div class="nurax-finder__progress"><span data-nurax-progress></span></div>
			<form class="nurax-quiz" data-nurax-quiz novalidate>

				<fieldset class="nurax-step is-active" data-step="1">
					<h3><?php esc_html_e( 'What is your face shape?', 'nura-experience' ); ?></h3>
					<p class="nurax-step__hint"><?php esc_html_e( 'Choose the closest match - or pick "Not sure" and our stylist will help you find the most flattering shape at your fitting.', 'nura-experience' ); ?></p>
					<?php
					echo $this->step_opts( 'face', array(
						'oval' => 'Oval', 'round' => 'Round', 'square' => 'Square',
						'heart' => 'Heart', 'long' => 'Long', 'unsure' => 'Not sure',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</fieldset>

				<fieldset class="nurax-step" data-step="2">
					<h3><?php esc_html_e( 'Preferred texture', 'nura-experience' ); ?></h3>
					<?php
					echo $this->step_opts( 'texture', array(
						'straight' => 'Straight', 'body' => 'Body wave', 'curly' => 'Curly', 'kinky' => 'Kinky / coily',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<h3><?php esc_html_e( 'Length', 'nura-experience' ); ?></h3>
					<?php
					echo $this->step_opts( 'length', array(
						'bob' => 'Bob', 'medium' => 'Medium', 'long' => 'Long', 'extra' => 'Extra long',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</fieldset>

				<fieldset class="nurax-step" data-step="3">
					<h3><?php esc_html_e( 'What is the occasion?', 'nura-experience' ); ?></h3>
					<?php
					echo $this->step_opts( 'life', array(
						'lowmaint' => 'Everyday', 'pro' => 'Professional', 'glam' => 'Glam / events', 'bridal' => 'Bridal',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</fieldset>

				<fieldset class="nurax-step" data-step="4">
					<h3><?php esc_html_e( 'Your budget (KES)', 'nura-experience' ); ?></h3>
					<?php
					echo $this->step_opts( 'budget', array(
						'5000' => 'Up to 5,000', '10000' => '5,000 - 10,000', '18000' => '10,000 - 18,000', '99999' => '18,000+',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</fieldset>

				<fieldset class="nurax-step" data-step="5">
					<h3><?php esc_html_e( 'Where should we send your recommendation?', 'nura-experience' ); ?></h3>
					<div class="nurax-fields">
						<label><?php esc_html_e( 'Full name', 'nura-experience' ); ?><input type="text" name="name" required></label>
						<label><?php esc_html_e( 'Phone / WhatsApp', 'nura-experience' ); ?><input type="tel" name="phone" required></label>
						<label class="nurax-full"><?php esc_html_e( 'Email (optional)', 'nura-experience' ); ?><input type="email" name="email"></label>
						<label class="nurax-full"><?php esc_html_e( 'Anything specific you would like? (optional)', 'nura-experience' ); ?><textarea name="concern" rows="2"></textarea></label>
						<label class="nurax-consent nurax-full"><input type="checkbox" name="consent" value="1"> <?php esc_html_e( 'I agree to be contacted about my consultation.', 'nura-experience' ); ?></label>
					</div>
				</fieldset>

				<div class="nurax-quiz__nav">
					<button type="button" class="nura-btn nura-btn--ghost" data-nurax-back hidden><?php esc_html_e( 'Back', 'nura-experience' ); ?></button>
					<button type="button" class="nura-btn nura-btn--gold" data-nurax-next><?php esc_html_e( 'Next', 'nura-experience' ); ?></button>
					<button type="submit" class="nura-btn nura-btn--gold" data-nurax-submit hidden><?php esc_html_e( 'See my matches', 'nura-experience' ); ?></button>
				</div>
			</form>
			<div class="nurax-results" data-nurax-results hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** REST recommender + lead capture. */
	public function recommend( WP_REST_Request $req ) {
		$face    = sanitize_text_field( (string) $req->get_param( 'face' ) );
		$texture = sanitize_text_field( (string) $req->get_param( 'texture' ) );
		$length  = sanitize_text_field( (string) $req->get_param( 'length' ) );
		$life    = sanitize_text_field( (string) $req->get_param( 'life' ) );
		$budget  = absint( $req->get_param( 'budget' ) );
		$name    = sanitize_text_field( (string) $req->get_param( 'name' ) );
		$phone   = sanitize_text_field( (string) $req->get_param( 'phone' ) );
		$email   = sanitize_email( (string) $req->get_param( 'email' ) );
		$concern = sanitize_textarea_field( (string) $req->get_param( 'concern' ) );
		$consent = absint( $req->get_param( 'consent' ) );

		// Hook point for a real AI vision service (returns a face-shape string).
		$face = apply_filters( 'nurax_face_analysis', $face, $req );

		$cat_map  = array( 'lowmaint' => 'ready-to-wear', 'glam' => 'lace-front-hd', 'pro' => 'lace-front-hd', 'bridal' => 'bridal-occasion' );
		$pref_cat = isset( $cat_map[ $life ] ) ? $cat_map[ $life ] : '';

		$args = array( 'status' => 'publish', 'limit' => 8, 'orderby' => 'popularity' );
		if ( $pref_cat ) {
			$args['category'] = array( $pref_cat );
		}
		if ( $budget && $budget <= 6000 ) {
			$args['orderby'] = 'price';
			$args['order']   = 'ASC';
		}

		$products = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();

		$matched = array();
		foreach ( $products as $prod ) {
			$price = (float) $prod->get_price();
			if ( $budget && $price > $budget * 1.15 ) {
				continue;
			}
			$matched[] = $prod;
		}
		if ( empty( $matched ) ) {
			$matched = $products;
		}
		if ( empty( $matched ) && function_exists( 'wc_get_products' ) ) {
			$matched = wc_get_products( array( 'status' => 'publish', 'limit' => 3, 'orderby' => 'popularity' ) );
		}

		$out = array();
		foreach ( array_slice( $matched, 0, 6 ) as $prod ) {
			$img   = wp_get_attachment_image_url( $prod->get_image_id(), 'woocommerce_thumbnail' );
			$out[] = array(
				'id'    => $prod->get_id(),
				'name'  => $prod->get_name(),
				'price' => wp_strip_all_tags( wc_price( $prod->get_price() ) ),
				'img'   => $img ? $img : wc_placeholder_img_src(),
				'url'   => get_permalink( $prod->get_id() ),
			);
		}

		if ( $consent && $name && $phone ) {
			$this->capture_lead( compact( 'name', 'phone', 'email', 'face', 'texture', 'length', 'life', 'budget', 'concern' ) );
		}

		$wa       = NURAX_Settings::get( 'whatsapp', get_theme_mod( 'nura_whatsapp', '' ) );
		$whatsapp = '';
		if ( $wa ) {
			$msg      = sprintf(
				'Hi NURA, I just completed the wig consultation. Name: %1$s. Phone: %2$s. Face: %3$s, texture: %4$s, length: %5$s, occasion: %6$s, budget KES %7$s. I would love to book a fitting.',
				$name, $phone, $face, $texture, $length, $life, $budget
			);
			$whatsapp = $wa . ( ( false === strpos( $wa, '?' ) ) ? '?' : '&' ) . 'text=' . rawurlencode( $msg );
		}

		$note = sprintf(
			/* translators: 1: name 2: texture 3: occasion */
			__( '%1$s, based on your %2$s look for a %3$s occasion, here are your top matches. Book a free fitting and we will confirm your perfect unit.', 'nura-experience' ),
			$name ? $name : __( 'Here you are', 'nura-experience' ),
			( $texture && 'unsure' !== $texture ) ? $texture : __( 'chosen', 'nura-experience' ),
			$life ? $life : __( 'special', 'nura-experience' )
		);

		return rest_ensure_response( array( 'note' => $note, 'products' => $out, 'whatsapp' => $whatsapp ) );
	}

	/** Email the store when a consenting lead completes the consultation. */
	private function capture_lead( $data ) {
		$to      = get_option( 'admin_email' );
		$subject = 'New NURA wig consultation - ' . $data['name'];
		$lines   = array(
			'A new consultation was submitted on your website:',
			'',
			'Name: ' . $data['name'],
			'Phone / WhatsApp: ' . $data['phone'],
			'Email: ' . ( $data['email'] ? $data['email'] : '-' ),
			'Face shape: ' . $data['face'],
			'Texture: ' . $data['texture'],
			'Length: ' . $data['length'],
			'Occasion: ' . $data['life'],
			'Budget (KES): ' . $data['budget'],
			'Notes: ' . ( $data['concern'] ? $data['concern'] : '-' ),
		);
		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
