<?php
/**
 * NURA Experience 1.50.0 - Reviews & Photos.
 *
 * WooCommerce > NURA Reviews & Photos:
 *  - Connect Google reviews: paste the Google Places API key and Place ID (a key set in
 *    wp-config.php as NURA_GOOGLE_PLACES_API_KEY always wins and is never shown here).
 *  - Community photos: pick real client photos from the Media Library for the homepage
 *    "Real women. Real crowns." section (use only photos clients agreed to share).
 *  - Order screen: an "Ask for a review on WhatsApp" box that opens WhatsApp with a ready
 *    message to the customer (works without the WhatsApp Cloud API).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Community {

	const GROUP = 'nurax_community';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 32 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'order_box' ) );
		add_action( 'update_option_nura_google_place_id', array( __CLASS__, 'flush' ) );
		add_action( 'update_option_nura_google_places_api_key', array( __CLASS__, 'flush' ) );
		add_action( 'parse_request', array( __CLASS__, 'review_redirect' ), 1 );
	}

	/**
	 * 1.73.8: short, shareable review link - nurabeauty.co.ke/review
	 * Goes to the Google review form once a Place ID is saved, otherwise to the shop.
	 * Use it in WhatsApp messages, receipts, packaging cards and QR codes.
	 */
	public static function review_link() {
		return home_url( '/review/' );
	}

	public static function review_redirect() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? untrailingslashit( strtolower( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) ) ) : '';
		if ( '/review' !== $path && '/reviews' !== $path ) {
			return;
		}
		$to = self::google_review_url();
		if ( '' === $to ) {
			$to = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		}
		nocache_headers();
		wp_redirect( $to, 302, 'NURA' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google review form is an external, fixed host.
		exit;
	}

	public static function flush() {
		delete_transient( 'nurax_google_reviews_v1' );
	}

	/** Google "write a review" link when a Place ID is set, else ''. */
	public static function google_review_url() {
		$pid = defined( 'NURA_GOOGLE_PLACE_ID' ) && NURA_GOOGLE_PLACE_ID ? (string) NURA_GOOGLE_PLACE_ID : (string) get_option( 'nura_google_place_id', '' );
		if ( '' === trim( $pid ) ) {
			// 1.73.9: NURA Beauty, Imenti House, Nairobi (verified Google Business Profile).
			// A Place ID saved in WooCommerce > NURA Reviews & Photos always takes priority.
			$pid = 'ChIJGx-_W1sRLxgRxNUNZe1z9ug';
		}
		$pid = trim( apply_filters( 'nurax_google_place_id', $pid ) );
		return '' === $pid ? '' : 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $pid );
	}

	/** Kenyan number to WhatsApp format (2547XXXXXXXX), '' if unusable. */
	public static function wa_number( $phone ) {
		$d = preg_replace( '/\D+/', '', (string) $phone );
		if ( '' === $d ) {
			return '';
		}
		if ( 0 === strpos( $d, '0' ) && 10 === strlen( $d ) ) {
			$d = '254' . substr( $d, 1 );
		} elseif ( 9 === strlen( $d ) && in_array( $d[0], array( '7', '1' ), true ) ) {
			$d = '254' . $d;
		}
		return strlen( $d ) >= 11 ? $d : '';
	}

	public function menu() {
		add_submenu_page( 'woocommerce', 'NURA Reviews & Photos', 'NURA Reviews & Photos', 'manage_woocommerce', 'nurax-community', array( $this, 'page' ) );
	}

	public function register() {
		register_setting( self::GROUP, 'nura_google_place_id', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::GROUP, 'nura_google_places_api_key', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'keep_key' ) ) );
		register_setting( self::GROUP, 'nurax_community_photos', array( 'type' => 'array', 'sanitize_callback' => array( $this, 'clean_ids' ) ) );
	}

	/** Blank key field means "keep the saved key". */
	public function keep_key( $v ) {
		$v = trim( sanitize_text_field( (string) $v ) );
		return '' === $v ? (string) get_option( 'nura_google_places_api_key', '' ) : $v;
	}

	public function clean_ids( $v ) {
		if ( is_string( $v ) ) {
			$v = explode( ',', $v );
		}
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $v ) ) ) ), 0, 12 );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'nurax-community' ) ) {
			return;
		}
		wp_enqueue_media();
		$js = "jQuery(function($){var f;$('#nurax-pick').on('click',function(e){e.preventDefault();if(f){f.open();return;}"
			. "f=wp.media({title:'Choose client photos',button:{text:'Use these photos'},library:{type:'image'},multiple:'add'});"
			. "f.on('open',function(){var s=f.state().get('selection');($('#nurax-ids').val()||'').split(',').forEach(function(id){id=parseInt(id,10);if(id){var a=wp.media.attachment(id);a.fetch();s.add(a);}});});"
			. "f.on('select',function(){var ids=[],h='';f.state().get('selection').each(function(a){a=a.toJSON();ids.push(a.id);var u=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;h+='<img src=\"'+u+'\" style=\"width:90px;height:90px;object-fit:cover;border-radius:6px\">';});"
			. "$('#nurax-ids').val(ids.slice(0,12).join(','));$('#nurax-prev').html(h);});f.open();});"
			. "$('#nurax-clear').on('click',function(e){e.preventDefault();$('#nurax-ids').val('');$('#nurax-prev').empty();});});";
		wp_add_inline_script( 'media-editor', $js );
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$const_key = defined( 'NURA_GOOGLE_PLACES_API_KEY' ) && NURA_GOOGLE_PLACES_API_KEY;
		$has_key   = $const_key || '' !== (string) get_option( 'nura_google_places_api_key', '' );
		$ids       = $this->clean_ids( get_option( 'nurax_community_photos', array() ) );
		$status    = '';
		if ( class_exists( 'NURAX_Google_Reviews' ) ) {
			$gr = new NURAX_Google_Reviews();
			if ( $gr->is_configured() ) {
				$d      = $gr->get_data();
				$status = ! empty( $d['ok'] )
					? sprintf( 'Connected: %1$s stars from %2$s Google reviews. They show on the homepage.', number_format_i18n( (float) $d['rating'], 1 ), number_format_i18n( (int) $d['total'] ) )
					: 'Saved, but Google did not return reviews. Check that the Place ID is right and the Places API is enabled for this key.';
			}
		}
		?>
		<div class="wrap">
			<h1>NURA Reviews &amp; Photos</h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<h2>Google reviews</h2>
				<p style="max-width:760px">Shows your Google rating and latest reviews on the homepage. You need your Google Business Profile Place ID (find it with Google's Place ID Finder) and a Google Cloud API key with the Places API enabled.</p>
				<?php if ( $status ) : ?><p><strong><?php echo esc_html( $status ); ?></strong></p><?php endif; ?>
				<table class="form-table">
					<tr><th>Google Place ID</th><td><input type="text" style="width:420px" name="nura_google_place_id" value="<?php echo esc_attr( (string) get_option( 'nura_google_place_id', '' ) ); ?>" placeholder="ChIJ..."></td></tr>
					<tr><th>Places API key</th><td>
						<?php if ( $const_key ) : ?>
							Set in wp-config.php.
						<?php else : ?>
							<input type="password" style="width:420px" name="nura_google_places_api_key" value="" placeholder="<?php echo $has_key ? esc_attr( 'Saved - leave blank to keep' ) : 'AIza...'; ?>" autocomplete="off">
						<?php endif; ?>
					</td></tr>
				</table>
				<h2>Community photos</h2>
				<p style="max-width:760px">Pick up to 12 real client photos (8 show on the homepage). Add a caption in the Media Library, for example "Wanjiku, Westlands", and it shows under the photo. Only use photos clients agreed to share.</p>
				<input type="hidden" id="nurax-ids" name="nurax_community_photos" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
				<p><button class="button" id="nurax-pick">Choose photos</button> <button class="button-link" id="nurax-clear">Clear</button></p>
				<div id="nurax-prev" style="display:flex;flex-wrap:wrap;gap:8px">
					<?php foreach ( $ids as $id ) { echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'width:90px;height:90px;object-fit:cover;border-radius:6px' ) ); } ?>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------ order: ask for review */

	public function order_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $s ) {
			add_meta_box( 'nurax-review-ask', 'Ask for a review', array( $this, 'order_box_html' ), $s, 'side', 'default' );
		}
	}

	/** Review link for an order: Google first, else the first product's reviews tab. */
	public static function order_review_url( $order ) {
		$g = self::google_review_url();
		if ( '' !== $g ) {
			return $g;
		}
		foreach ( $order->get_items() as $item ) {
			if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) && $item->get_product_id() ) {
				return get_permalink( $item->get_product_id() ) . '#reviews';
			}
		}
		return home_url( '/' );
	}

	public function order_box_html( $obj ) {
		$order = $obj instanceof WP_Post ? wc_get_order( $obj->ID ) : ( is_object( $obj ) && method_exists( $obj, 'get_id' ) ? $obj : null );
		if ( ! $order ) {
			return;
		}
		$num = self::wa_number( $order->get_billing_phone() );
		if ( '' === $num ) {
			echo '<p>No usable phone number on this order.</p>';
			return;
		}
		$name = trim( (string) $order->get_billing_first_name() );
		$msg  = sprintf(
			"Hi %1\$s, thank you for shopping with NURA (order #%2\$s). We hope you are loving your new look.\n\nCould you spare a minute to leave us a review? It really helps other women choose with confidence:\n%3\$s\n\nIf you are happy to, send us a photo wearing it - with your OK we would love to feature you on our website.",
			'' !== $name ? $name : 'there',
			$order->get_order_number(),
			self::order_review_url( $order )
		);
		$url  = 'https://wa.me/' . $num . '?text=' . rawurlencode( $msg );
		$sent = (string) $order->get_meta( '_nurax_review_asked' );
		if ( 'completed' !== $order->get_status() ) {
			echo '<p style="color:#8a6d00">Best sent once the order is delivered (Completed).</p>';
		}
		echo '<p><a class="button button-primary" style="width:100%;text-align:center" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">Ask on WhatsApp</a></p>';
		echo '<p class="description">Opens WhatsApp with the message ready. Ask clients for a photo too, then add it under WooCommerce &gt; NURA Reviews &amp; Photos.</p>';
		if ( $sent ) {
			echo '<p class="description">Automatic review request: ' . esc_html( is_numeric( $sent ) ? wp_date( 'j M Y', (int) $sent ) : $sent ) . '</p>';
		}
	}
}
