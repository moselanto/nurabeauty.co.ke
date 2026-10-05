<?php
/**
 * Brand + form shortcodes.
 *
 * These let page copy reference live Customizer values (phone, email, address,
 * WhatsApp) instead of hardcoding them, and provide simple contact/booking
 * forms that upgrade to Contact Form 7 automatically when it is installed.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Brand value shortcodes: [nura_phone] [nura_email] [nura_address] [nura_hours] [nura_whatsapp] */
foreach ( array( 'nura_phone', 'nura_email', 'nura_address', 'nura_hours', 'nura_whatsapp' ) as $sc ) {
	add_shortcode( $sc, function () use ( $sc ) {
		return esc_html( nura_opt( $sc ) );
	} );
}

/**
 * [nura_contact_form] - falls back to a simple form; uses CF7 form if one exists.
 */
add_shortcode( 'nura_contact_form', function () {
	// If Contact Form 7 is active and a form exists, prefer it.
	if ( defined( 'WPCF7_VERSION' ) ) {
		$forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 1 ) );
		if ( $forms ) {
			return do_shortcode( '[contact-form-7 id="' . $forms[0]->ID . '"]' );
		}
	}
	ob_start(); ?>
	<form class="nura-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="nura_contact">
		<?php wp_nonce_field( 'nura_contact' ); ?>
		<div class="nura-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;"><label>Leave this field empty<input type="text" name="nura_hp" tabindex="-1" autocomplete="off" value=""></label></div>
		<input type="hidden" name="nura_ct_ts" value="<?php echo esc_attr( time() ); ?>">
		<p><input type="text" name="nura_name" placeholder="<?php esc_attr_e( 'Your name', 'nura-beauty' ); ?>" required></p>
		<p><input type="text" name="nura_contact_val" placeholder="<?php esc_attr_e( 'Phone / WhatsApp or email', 'nura-beauty' ); ?>" required></p>
		<p><textarea name="nura_message" rows="5" placeholder="<?php esc_attr_e( 'How can we help?', 'nura-beauty' ); ?>" required></textarea></p>
		<p><button type="submit" class="nura-btn nura-btn--gold"><?php esc_html_e( 'Send message', 'nura-beauty' ); ?></button></p>
	</form>
	<?php
	return ob_get_clean();
} );

/**
 * [nura_booking_form] - a simple appointment request form.
 */
add_shortcode( 'nura_booking_form', function () {
	ob_start(); ?>
	<form class="nura-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="nura_contact">
		<input type="hidden" name="nura_kind" value="booking">
		<?php wp_nonce_field( 'nura_contact' ); ?>
		<div class="nura-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;"><label>Leave this field empty<input type="text" name="nura_hp" tabindex="-1" autocomplete="off" value=""></label></div>
		<input type="hidden" name="nura_ct_ts" value="<?php echo esc_attr( time() ); ?>">
		<p><input type="text" name="nura_name" placeholder="<?php esc_attr_e( 'Your name', 'nura-beauty' ); ?>" required></p>
		<p><input type="text" name="nura_contact_val" placeholder="<?php esc_attr_e( 'Phone / WhatsApp', 'nura-beauty' ); ?>" required></p>
		<p>
			<select name="nura_service">
				<option><?php esc_html_e( 'Virtual consultation (free)', 'nura-beauty' ); ?></option>
				<option><?php esc_html_e( 'In-studio consultation & fitting', 'nura-beauty' ); ?></option>
				<option><?php esc_html_e( 'Installation', 'nura-beauty' ); ?></option>
				<option><?php esc_html_e( 'Styling / colour / revamp', 'nura-beauty' ); ?></option>
				<option><?php esc_html_e( 'Bridal & occasion', 'nura-beauty' ); ?></option>
			</select>
		</p>
		<p><input type="date" name="nura_date"></p>
		<p><textarea name="nura_message" rows="4" placeholder="<?php esc_attr_e( 'Tell us the look you want', 'nura-beauty' ); ?>"></textarea></p>
		<p><button type="submit" class="nura-btn nura-btn--gold"><?php esc_html_e( 'Request appointment', 'nura-beauty' ); ?></button></p>
	</form>
	<?php
	return ob_get_clean();
} );

/**
 * Handle contact/booking + newsletter submissions by emailing the site admin.
 * (A real deployment can route these to a CRM / WhatsApp instead.)
 */
function nura_handle_contact() {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'nura_contact' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$name    = sanitize_text_field( wp_unslash( $_POST['nura_name'] ?? '' ) );
	$contact = sanitize_text_field( wp_unslash( $_POST['nura_contact_val'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['nura_message'] ?? '' ) );
	$kind    = sanitize_text_field( wp_unslash( $_POST['nura_kind'] ?? 'enquiry' ) );
	$service = sanitize_text_field( wp_unslash( $_POST['nura_service'] ?? '' ) );
	$date    = sanitize_text_field( wp_unslash( $_POST['nura_date'] ?? '' ) );

	$body = "New {$kind} from the NURA site:\n\nName: {$name}\nContact: {$contact}\nService: {$service}\nDate: {$date}\n\nMessage:\n{$message}";
	wp_mail( nura_opt( 'nura_email' ), 'NURA - new ' . $kind, $body );

	wp_safe_redirect( add_query_arg( 'nura_sent', '1', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_nura_contact', 'nura_handle_contact' );
add_action( 'admin_post_nura_contact', 'nura_handle_contact' );

/** Simple newsletter capture -> admin email. */
function nura_handle_subscribe() {
	$email = sanitize_email( wp_unslash( $_POST['nura_email'] ?? '' ) );
	if ( is_email( $email ) ) {
		wp_mail( nura_opt( 'nura_email' ), 'NURA - newsletter signup', $email );
	}
	wp_safe_redirect( add_query_arg( 'nura_sub', '1', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_nura_subscribe', 'nura_handle_subscribe' );
add_action( 'admin_post_nura_subscribe', 'nura_handle_subscribe' );

/** Success notices after form submit. */
add_filter( 'the_content', function ( $content ) {
	if ( isset( $_GET['nura_sent'] ) ) {
		$content = '<div class="woocommerce-message">' . esc_html__( 'Thank you - we have received your message and will reply shortly.', 'nura-beauty' ) . '</div>' . $content;
	}
	return $content;
} );


/**
 * [nura_lookbook] - an editorial gallery of signature looks (uses bundled imagery, links to shop).
 */
add_shortcode( 'nura_lookbook', function () {
	$shop  = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
	$items = array(
		array( 'img' => 'hero.webp',            'title' => 'The Signature', 'tag' => 'Everyday luxe' ),
		array( 'img' => 'hero-2.webp',          'title' => 'The Bride',     'tag' => 'Bridal & occasion' ),
		array( 'img' => 'model-editorial.webp', 'title' => 'The Icon',      'tag' => 'HD lace front' ),
		array( 'img' => 'hero-3.webp',          'title' => 'The Statement', 'tag' => 'Confidence line' ),
		array( 'img' => 'look-bob.webp',        'title' => 'The Bob',       'tag' => 'Ready to wear' ),
		array( 'img' => 'look-curls.webp',      'title' => 'The Curls',     'tag' => 'Textured' ),
	);
	ob_start();
	echo '<div class="nura-lookbook">';
	foreach ( $items as $it ) {
		printf(
			'<a class="nura-look" href="%1$s"><img src="%2$s" alt="%3$s" loading="lazy"><span class="nura-look__cap"><small>%4$s</small><strong>%3$s</strong></span></a>',
			esc_url( $shop ),
			esc_url( NURA_URI . 'assets/images/' . $it['img'] ),
			esc_attr( $it['title'] ),
			esc_html( $it['tag'] )
		);
	}
	echo '</div>';
	return ob_get_clean();
} );


/**
 * [nura_reviews limit="6"] - a grid of recent real customer reviews.
 */
add_shortcode( 'nura_reviews', function ( $atts ) {
	$atts    = shortcode_atts( array( 'limit' => 6 ), $atts, 'nura_reviews' );
	$reviews = function_exists( 'nura_recent_reviews' ) ? nura_recent_reviews( (int) $atts['limit'] ) : array();
	if ( empty( $reviews ) ) {
		return '<p class="nura-reviews-empty">' . esc_html__( 'Be the first to review a NURA unit - your words will appear here.', 'nura-beauty' ) . '</p>';
	}
	ob_start();
	echo '<div class="nura-reviews">';
	foreach ( $reviews as $rv ) {
		echo '<figure class="nura-review">';
		echo '<div class="nura-review__stars" aria-hidden="true">' . str_repeat( '&#9733;', max( 1, min( 5, (int) $rv['rating'] ) ) ) . '</div>';
		echo '<blockquote>' . esc_html( $rv['text'] ) . '</blockquote>';
		echo '<figcaption><span class="nura-review__avatar">' . esc_html( mb_substr( $rv['author'], 0, 1 ) ) . '</span><span><strong>' . esc_html( $rv['author'] ) . '</strong>';
		if ( ! empty( $rv['product'] ) ) {
			echo '<br><small><a href="' . esc_url( $rv['url'] ) . '">' . esc_html( $rv['product'] ) . '</a></small>';
		}
		echo '</span></figcaption></figure>';
	}
	echo '</div>';
	return ob_get_clean();
} );


/**
 * One wig-finder question: a labelled <select> built from a live attribute taxonomy.
 * Returns '' when the taxonomy has no terms yet, so unavailable questions vanish.
 *
 * @param string $tax      Attribute taxonomy (e.g. pa_texture).
 * @param string $label    Question label.
 * @param string $intro    Helper line under the label.
 * @param string $anylabel The "no preference" first option.
 * @return string
 */
function nura_finder_select( $tax, $label, $intro, $anylabel ) {
	if ( ! taxonomy_exists( $tax ) ) {
		return '';
	}
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}
	$name = 'filter_' . str_replace( 'pa_', '', $tax );
	ob_start();
	echo '<div class="nura-finder__q">';
	printf( '<label class="nura-finder__label" for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
	if ( $intro ) {
		echo '<p class="nura-finder__hint">' . esc_html( $intro ) . '</p>';
	}
	printf( '<select class="nura-finder__select" id="%1$s" name="%1$s">', esc_attr( $name ) );
	printf( '<option value="">%s</option>', esc_html( $anylabel ) );
	foreach ( $terms as $term ) {
		printf( '<option value="%1$s">%2$s</option>', esc_attr( $term->slug ), esc_html( $term->name ) );
	}
	echo '</select>';
	echo '</div>';
	return ob_get_clean();
}

/**
 * [nura_ai_wig_finder] / [nura_wig_finder] - a real, attribute-matching wig finder.
 *
 * Honest matching (brief #29): a few quick questions, each built from the store's
 * actual global attributes (Texture, Length, Hair Type, Colour, Lace). Submitting
 * maps the answers to the faceted-shop filters and lands the shopper on matching,
 * in-stock NURA units - genuine matching to real inventory, no invented "AI selfie"
 * analysis. Works with a normal page load (no JS required); unavailable attributes
 * are simply skipped.
 */
function nura_render_wig_finder() {
	$shop = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );

	$questions  = nura_finder_select( 'pa_texture', __( 'What texture do you love?', 'nura-beauty' ), __( 'The overall feel of the hair.', 'nura-beauty' ), __( 'Any texture', 'nura-beauty' ) );
	$questions .= nura_finder_select( 'pa_length', __( 'How long?', 'nura-beauty' ), __( 'From cropped bobs to long and dramatic.', 'nura-beauty' ), __( 'Any length', 'nura-beauty' ) );
	$questions .= nura_finder_select( 'pa_hair-type', __( 'Hair type & budget', 'nura-beauty' ), __( 'Synthetic is budget-friendly; human hair is premium.', 'nura-beauty' ), __( 'Any type', 'nura-beauty' ) );
	$questions .= nura_finder_select( 'pa_colour', __( 'Colour family', 'nura-beauty' ), __( 'Pick a shade family, or explore them all.', 'nura-beauty' ), __( 'Any colour', 'nura-beauty' ) );
	$questions .= nura_finder_select( 'pa_lace', __( 'Lace / cap style', 'nura-beauty' ), __( 'Lace fronts and closures give a natural hairline.', 'nura-beauty' ), __( 'Any style', 'nura-beauty' ) );
	$questions .= nura_finder_select( 'pa_occasion', __( 'What is it for?', 'nura-beauty' ), __( 'Everyday, work, a wedding or a night out.', 'nura-beauty' ), __( 'Any occasion', 'nura-beauty' ) );

	if ( '' === trim( $questions ) ) {
		return '<div class="nura-finder nura-finder--empty"><p>' . esc_html__( 'Our wig finder is being prepared. Browse the full collection in the meantime.', 'nura-beauty' ) . '</p><p><a class="nura-btn nura-btn--gold" href="' . esc_url( $shop ) . '">' . esc_html__( 'Shop all wigs', 'nura-beauty' ) . '</a></p></div>';
	}

	ob_start();
	?>
	<form class="nura-finder" method="get" action="<?php echo esc_url( $shop ); ?>">
		<div class="nura-finder__intro">
			<p class="nura-eyebrow"><?php esc_html_e( 'NURA Wig Finder', 'nura-beauty' ); ?></p>
			<h3><?php esc_html_e( 'Answer a few quick questions and meet your match.', 'nura-beauty' ); ?></h3>
		</div>
		<div class="nura-finder__grid">
			<?php echo $questions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in nura_finder_select(). ?>
		</div>
		<div class="nura-finder__foot">
			<button type="submit" class="nura-btn nura-btn--gold"><?php esc_html_e( 'Show my matches', 'nura-beauty' ); ?></button>
			<a class="nura-finder__skip" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'or browse everything', 'nura-beauty' ); ?></a>
		</div>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'nura_ai_wig_finder', 'nura_render_wig_finder' );
add_shortcode( 'nura_wig_finder', 'nura_render_wig_finder' );
