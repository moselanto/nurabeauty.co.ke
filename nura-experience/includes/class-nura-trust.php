<?php
/**
 * NURA Trust layer - "Why Shop NURA?" badges + customer reviews.
 *
 * Two shortcodes, both dormant until placed on a page:
 *   [nura_why_shop] - a row of trust badges (quality, M-Pesa, Nairobi pickup,
 *                     Kenya-wide delivery, personal help). Static and safe.
 *   [nura_reviews]  - customer reviews: the curated testimonials you enter in
 *                     Settings, plus (optionally) the most recent Google reviews
 *                     pulled live from the Google Places API.
 *
 * SECURITY: the Google Places API key is read ONLY from the NURA_GOOGLE_PLACES_KEY
 * constant in wp-config.php - never the database or an admin field. The Place ID
 * is a public identifier and may be set in Settings. Google's API returns at most
 * five reviews and you cannot choose which; curated testimonials cover the rest.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Trust {

	const OPTION = 'nurax_trust';
	const CACHE  = 'nurax_google_reviews';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		add_shortcode( 'nura_why_shop', array( $this, 'render_why' ) );
		add_shortcode( 'nura_reviews', array( $this, 'render_reviews' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	private function opt( $key, $default = '' ) {
		$o = get_option( self::OPTION, array() );
		return isset( $o[ $key ] ) ? $o[ $key ] : $default;
	}

	/* ------------------------------------------------------------------ */
	/* Why Shop NURA?                                                      */
	/* ------------------------------------------------------------------ */

	private function why_items() {
		$items = array(
			array( 'title' => __( 'Quality You Can Trust', 'nura-experience' ), 'text' => __( 'Carefully selected hair and beauty products.', 'nura-experience' ) ),
			array( 'title' => __( 'Easy M-Pesa Checkout', 'nura-experience' ),  'text' => __( 'Pay securely with convenient local options.', 'nura-experience' ) ),
			array( 'title' => __( 'Nairobi Pickup', 'nura-experience' ),        'text' => __( 'Convenient pickup available in Nairobi.', 'nura-experience' ) ),
			array( 'title' => __( 'Kenya-Wide Delivery', 'nura-experience' ),   'text' => __( 'Delivered to your door across Kenya.', 'nura-experience' ) ),
			array( 'title' => __( 'Personal Assistance', 'nura-experience' ),   'text' => __( 'Need help choosing? Our team is here.', 'nura-experience' ) ),
		);
		return apply_filters( 'nurax_why_shop_items', $items );
	}

	public function render_why( $atts = array() ) {
		$atts  = shortcode_atts( array( 'title' => __( 'Why Shop NURA?', 'nura-experience' ) ), $atts, 'nura_why_shop' );
		$items = $this->why_items();
		ob_start(); ?>
		<section class="nura-why">
			<?php if ( $atts['title'] ) : ?><h2 class="nura-why__title"><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
			<div class="nura-why__grid">
				<?php foreach ( $items as $it ) : ?>
					<div class="nura-why__item">
						<h3><?php echo esc_html( $it['title'] ); ?></h3>
						<p><?php echo esc_html( $it['text'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<style>
		.nura-why{max-width:1100px;margin:2.5rem auto;padding:0 1rem;text-align:center}
		.nura-why__title{font-size:1.6rem;margin:0 0 1.5rem}
		.nura-why__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.2rem}
		.nura-why__item{padding:1.3rem;border:1px solid #eee;border-radius:14px;background:#fff}
		.nura-why__item h3{margin:0 0 .4rem;font-size:1.05rem;color:#b8975a}
		.nura-why__item p{margin:0;color:#555;font-size:.92rem}
		</style>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Reviews                                                             */
	/* ------------------------------------------------------------------ */

	/** Curated testimonials from settings: one per line "Name | 5 | Quote". */
	private function curated() {
		$raw = (string) $this->opt( 'testimonials', '' );
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$name  = isset( $parts[0] ) ? $parts[0] : '';
			$stars = isset( $parts[1] ) ? min( 5, max( 1, (int) $parts[1] ) ) : 5;
			$quote = isset( $parts[2] ) ? $parts[2] : '';
			if ( '' === $quote ) {
				continue;
			}
			$out[] = array( 'name' => $name, 'stars' => $stars, 'text' => $quote, 'source' => 'curated' );
		}
		return $out;
	}

	/** Up to five Google reviews via the Places API. Key from wp-config only; result cached 12h. */
	private function google_reviews() {
		if ( 'on' !== $this->opt( 'google_enable', 'off' ) ) {
			return array();
		}
		$key      = defined( 'NURA_GOOGLE_PLACES_KEY' ) ? NURA_GOOGLE_PLACES_KEY : '';
		$place_id = (string) $this->opt( 'place_id', '' );
		if ( ! $key || ! $place_id ) {
			return array();
		}

		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = add_query_arg( array(
			'place_id'     => rawurlencode( $place_id ),
			'fields'       => 'reviews',
			'reviews_sort' => 'newest',
			'key'          => rawurlencode( $key ),
		), 'https://maps.googleapis.com/maps/api/place/details/json' );

		$resp = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $resp ) ) {
			set_transient( self::CACHE, array(), 2 * HOUR_IN_SECONDS );
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$min  = (int) $this->opt( 'min_stars', 4 );
		$out  = array();
		if ( isset( $body['result']['reviews'] ) && is_array( $body['result']['reviews'] ) ) {
			foreach ( $body['result']['reviews'] as $r ) {
				$stars = isset( $r['rating'] ) ? (int) $r['rating'] : 5;
				if ( $stars < $min ) {
					continue;
				}
				$out[] = array(
					'name'   => isset( $r['author_name'] ) ? sanitize_text_field( $r['author_name'] ) : '',
					'stars'  => $stars,
					'text'   => isset( $r['text'] ) ? wp_strip_all_tags( $r['text'] ) : '',
					'source' => 'google',
				);
			}
		}
		set_transient( self::CACHE, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	private function stars_html( $n ) {
		$n    = max( 0, min( 5, (int) $n ) );
		$full = str_repeat( '&#9733;', $n );
		$rest = str_repeat( '&#9734;', 5 - $n );
		return '<span class="nura-stars" aria-label="' . esc_attr( sprintf( '%d out of 5', $n ) ) . '">' . $full . $rest . '</span>';
	}

	public function render_reviews( $atts = array() ) {
		$atts = shortcode_atts( array(
			'title'  => __( 'Customer Reviews', 'nura-experience' ),
			'source' => 'all',
		), $atts, 'nura_reviews' );

		$reviews = array();
		if ( 'curated' !== $atts['source'] ) {
			$reviews = array_merge( $reviews, $this->google_reviews() );
		}
		if ( 'google' !== $atts['source'] ) {
			$reviews = array_merge( $reviews, $this->curated() );
		}

		if ( empty( $reviews ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p style="text-align:center;color:#999">' . esc_html__( '[NURA Reviews] Add curated testimonials or enable Google reviews under WooCommerce > NURA Trust. This note is visible to admins only.', 'nura-experience' ) . '</p>';
			}
			return '';
		}

		ob_start(); ?>
		<section class="nura-reviews">
			<?php if ( $atts['title'] ) : ?><h2 class="nura-reviews__title"><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
			<div class="nura-reviews__grid">
				<?php foreach ( $reviews as $r ) : ?>
					<figure class="nura-review">
						<?php echo $this->stars_html( $r['stars'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<blockquote><?php echo esc_html( $r['text'] ); ?></blockquote>
						<figcaption>
							<?php echo esc_html( $r['name'] ); ?>
							<?php if ( 'google' === $r['source'] ) : ?><span class="nura-review__g"><?php esc_html_e( 'via Google', 'nura-experience' ); ?></span><?php endif; ?>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</section>
		<style>
		.nura-reviews{max-width:1100px;margin:2.5rem auto;padding:0 1rem}
		.nura-reviews__title{text-align:center;font-size:1.6rem;margin:0 0 1.5rem}
		.nura-reviews__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.2rem}
		.nura-review{margin:0;padding:1.4rem;border:1px solid #eee;border-radius:14px;background:#fff}
		.nura-review .nura-stars{color:#b8975a;letter-spacing:2px}
		.nura-review blockquote{margin:.6rem 0;font-size:.96rem;color:#333;line-height:1.5}
		.nura-review figcaption{font-weight:600;color:#1a1a1a;font-size:.9rem}
		.nura-review__g{font-weight:400;color:#888;margin-left:.4rem;font-size:.82rem}
		</style>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page( 'woocommerce', 'NURA Trust', 'NURA Trust', 'manage_woocommerce', 'nurax-trust', array( $this, 'page' ) );
	}

	public function register() {
		register_setting( 'nurax_trust_group', self::OPTION );
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$has_key = defined( 'NURA_GOOGLE_PLACES_KEY' ) && NURA_GOOGLE_PLACES_KEY;
		$o       = get_option( self::OPTION, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA Trust', 'nura-experience' ); ?></h1>
			<p style="max-width:720px"><?php esc_html_e( 'Place [nura_why_shop] and [nura_reviews] on any page (for example the homepage or About page). The trust badges work out of the box. Reviews show your curated testimonials, plus live Google reviews when enabled below.', 'nura-experience' ); ?></p>

			<p style="max-width:720px;padding:.8rem 1rem;border-left:4px solid <?php echo $has_key ? '#2e7d32' : '#dba617'; ?>;background:#fff">
				<?php if ( $has_key ) : ?>
					<strong><?php esc_html_e( 'Google Places API key detected in wp-config.php.', 'nura-experience' ); ?></strong>
				<?php else : ?>
					<strong><?php esc_html_e( 'To pull Google reviews, add this line to wp-config.php:', 'nura-experience' ); ?></strong><br>
					<code>define( 'NURA_GOOGLE_PLACES_KEY', 'your-google-api-key' );</code>
				<?php endif; ?>
				<br><?php esc_html_e( 'Google returns at most five reviews and you cannot choose which. Use curated testimonials for the rest.', 'nura-experience' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'nurax_trust_group' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Google reviews', 'nura-experience' ); ?></th>
						<td><select name="<?php echo esc_attr( self::OPTION ); ?>[google_enable]">
							<option value="off" <?php selected( isset( $o['google_enable'] ) ? $o['google_enable'] : 'off', 'off' ); ?>><?php esc_html_e( 'Off', 'nura-experience' ); ?></option>
							<option value="on" <?php selected( isset( $o['google_enable'] ) ? $o['google_enable'] : 'off', 'on' ); ?>><?php esc_html_e( 'On', 'nura-experience' ); ?></option>
						</select></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Google Place ID', 'nura-experience' ); ?></th>
						<td><input type="text" style="width:420px" name="<?php echo esc_attr( self::OPTION ); ?>[place_id]" value="<?php echo esc_attr( isset( $o['place_id'] ) ? $o['place_id'] : '' ); ?>" placeholder="ChIJ...">
						<p class="description"><?php esc_html_e( 'Find it with Google\'s Place ID Finder. The API key stays in wp-config.php.', 'nura-experience' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Minimum stars to show', 'nura-experience' ); ?></th>
						<td><input type="number" min="1" max="5" name="<?php echo esc_attr( self::OPTION ); ?>[min_stars]" value="<?php echo esc_attr( isset( $o['min_stars'] ) ? $o['min_stars'] : '4' ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Curated testimonials', 'nura-experience' ); ?></th>
						<td><textarea style="width:520px;height:140px" name="<?php echo esc_attr( self::OPTION ); ?>[testimonials]" placeholder="Wanjiku | 5 | The lace melts perfectly, I get compliments every day."><?php echo esc_textarea( isset( $o['testimonials'] ) ? $o['testimonials'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One review per line, in the format: Name | Stars (1-5) | Quote', 'nura-experience' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
