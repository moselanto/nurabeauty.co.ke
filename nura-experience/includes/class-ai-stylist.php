<?php
/**
 * NURA AI Stylist - a floating chat concierge.
 *
 * Renders a luxury chat widget in the site footer and exposes a REST endpoint
 * that proxies to an LLM (OpenAI or Google Gemini) using the API key set in
 * NURA Experience settings. With no key it falls back to a guided assistant
 * (smart canned replies + product picks + WhatsApp hand-off) so the widget
 * always works. Nothing is hardcoded - everything reads from settings.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_AI_Stylist {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'wp_footer', array( $this, 'widget' ) );
	}

	public function routes() {
		register_rest_route( 'nurax/v1', '/stylist', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'chat' ),
			'permission_callback' => '__return_true',
		) );
	}

	/** Is a real LLM configured? */
	public static function has_ai() {
		$prov = NURAX_Settings::get( 'stylist_provider', 'openai' );
		$key  = NURAX_Settings::get( 'stylist_key', '' );
		return ( ! empty( $key ) && 'off' !== $prov );
	}

	/** Render the floating widget in the footer. */
	public function widget() {
		if ( is_admin() ) {
			return;
		}
		if ( 'off' === NURAX_Settings::get( 'stylist_enable', 'on' ) ) {
			return;
		}
		$greeting = NURAX_Settings::get( 'stylist_greeting', '' );
		if ( empty( $greeting ) ) {
			$greeting = __( 'Hi, I am your NURA Stylist. Tell me the occasion, your budget, or the look you love, and I will help you find your perfect wig.', 'nura-experience' );
		}
		$wa = NURAX_Settings::get( 'whatsapp', get_theme_mod( 'nura_whatsapp', '' ) );
		?>
		<div class="nurax-stylist" data-nurax-stylist data-has-ai="<?php echo self::has_ai() ? '1' : '0'; ?>" data-wa="<?php echo esc_attr( $wa ); ?>">
			<button type="button" class="nurax-stylist__fab" data-stylist-toggle aria-label="<?php esc_attr_e( 'Chat with the NURA Stylist', 'nura-experience' ); ?>">
				<span class="nurax-stylist__fab-icon" aria-hidden="true">&#9826;</span>
				<span class="nurax-stylist__fab-label"><?php esc_html_e( 'NURA Stylist', 'nura-experience' ); ?></span>
			</button>
			<div class="nurax-stylist__panel" data-stylist-panel hidden>
				<div class="nurax-stylist__head">
					<div>
						<strong><?php esc_html_e( 'NURA Stylist', 'nura-experience' ); ?></strong>
						<small><?php esc_html_e( 'Your personal wig concierge', 'nura-experience' ); ?></small>
					</div>
					<button type="button" class="nurax-stylist__close" data-stylist-close aria-label="<?php esc_attr_e( 'Close', 'nura-experience' ); ?>">&times;</button>
				</div>
				<div class="nurax-stylist__log" data-stylist-log>
					<div class="nurax-msg nurax-msg--bot"><?php echo esc_html( $greeting ); ?></div>
				</div>
				<div class="nurax-stylist__quick" data-stylist-quick>
					<button type="button" data-q="<?php esc_attr_e( 'I need a wig for my wedding', 'nura-experience' ); ?>"><?php esc_html_e( 'Bridal', 'nura-experience' ); ?></button>
					<button type="button" data-q="<?php esc_attr_e( 'Show me HD lace front wigs', 'nura-experience' ); ?>"><?php esc_html_e( 'HD Lace', 'nura-experience' ); ?></button>
					<button type="button" data-q="<?php esc_attr_e( 'What do you have under KES 5000?', 'nura-experience' ); ?>"><?php esc_html_e( 'Under 5K', 'nura-experience' ); ?></button>
					<button type="button" data-q="<?php esc_attr_e( 'How do I care for my wig?', 'nura-experience' ); ?>"><?php esc_html_e( 'Wig care', 'nura-experience' ); ?></button>
				</div>
				<form class="nurax-stylist__input" data-stylist-form>
					<input type="text" name="msg" autocomplete="off" placeholder="<?php esc_attr_e( 'Ask me anything...', 'nura-experience' ); ?>" required>
					<button type="submit" aria-label="<?php esc_attr_e( 'Send', 'nura-experience' ); ?>">&#10148;</button>
				</form>
			</div>
		</div>
		<?php
	}

	/** REST chat handler. */
	public function chat( WP_REST_Request $req ) {
		$messages = $req->get_param( 'messages' );
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}
		$clean = array();
		foreach ( array_slice( $messages, -10 ) as $m ) {
			$role    = ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'assistant' : 'user';
			$content = isset( $m['content'] ) ? wp_strip_all_tags( (string) $m['content'] ) : '';
			$content = trim( mb_substr( $content, 0, 800 ) );
			if ( '' !== $content ) {
				$clean[] = array( 'role' => $role, 'content' => $content );
			}
		}
		if ( empty( $clean ) ) {
			return rest_ensure_response( array( 'reply' => __( 'Tell me a little about the look you want and I will help.', 'nura-experience' ), 'products' => array() ) );
		}

		$last      = end( $clean );
		$last_text = isset( $last['content'] ) ? $last['content'] : '';

		// Prefer the multi-provider NURA AI brain (Gemini / Groq / OpenAI, keys
		// in wp-config) when it is enabled and available. Any miss falls through
		// to the legacy path below, so the widget never regresses.
		if ( class_exists( 'NURAX_AI' ) && NURAX_AI::instance()->is_enabled() ) {
			$history = array_slice( $clean, 0, -1 );
			$out     = NURAX_AI::instance()->handle_message( $last_text, $history, array(), 'web', true );
			if ( ! empty( $out['ok'] ) && '' !== trim( (string) $out['reply'] ) ) {
				return rest_ensure_response( array( 'reply' => (string) $out['reply'], 'products' => $this->suggest_products( $last_text ) ) );
			}
		}

		if ( ! self::has_ai() ) {
			return rest_ensure_response( $this->guided( $last_text ) );
		}

		$system   = $this->system_prompt();
		$provider = NURAX_Settings::get( 'stylist_provider', 'openai' );
		$reply    = ( 'gemini' === $provider ) ? $this->call_gemini( $system, $clean ) : $this->call_openai( $system, $clean );

		if ( is_wp_error( $reply ) || '' === $reply ) {
			$g         = $this->guided( $last_text );
			$g['note'] = __( 'AI is busy right now - here is a quick hand pick.', 'nura-experience' );
			return rest_ensure_response( $g );
		}

		return rest_ensure_response( array( 'reply' => $reply, 'products' => $this->suggest_products( $last_text ) ) );
	}

	/** Build the system prompt with a live catalog snapshot. */
	private function system_prompt() {
		$brand  = get_bloginfo( 'name' );
		$cat    = $this->catalog_snippet();
		$custom = NURAX_Settings::get( 'stylist_prompt', '' );
		$base   = "You are the NURA Stylist, a warm, expert personal wig concierge for {$brand}, a luxury human-hair wig boutique in Nairobi, Kenya. "
			. "Help customers choose the right wig by face shape, occasion, texture, length, lace type and budget (prices are in Kenyan Shillings, KES). "
			. "Be concise, friendly and practical - reply in 2 to 4 short sentences. Recommend specific units from the catalog below by name when relevant, and suggest booking a free consultation or chatting on WhatsApp for fittings and orders. "
			. "Mention same-day Nairobi delivery, M-Pesa and pay-on-delivery when relevant. Only discuss NURA, wigs, hair care, orders and delivery. Never invent prices or products that are not in the catalog; if unsure, offer a free consultation.\n\nCATALOG:\n{$cat}";
		if ( ! empty( $custom ) ) {
			$base .= "\n\nADDITIONAL BRAND NOTES:\n" . $custom;
		}
		return $base;
	}

	private function catalog_snippet() {
		$out = '';
		if ( ! function_exists( 'wc_get_products' ) ) {
			return $out;
		}
		$products = wc_get_products( array( 'status' => 'publish', 'limit' => 24, 'orderby' => 'popularity' ) );
		foreach ( $products as $prod ) {
			$out .= '- ' . $prod->get_name() . ' (' . wp_strip_all_tags( wc_price( $prod->get_price() ) ) . ') ' . get_permalink( $prod->get_id() ) . "\n";
		}
		return $out;
	}

	private function call_openai( $system, $messages ) {
		$key   = NURAX_Settings::get( 'stylist_key', '' );
		$model = NURAX_Settings::get( 'stylist_model', '' );
		if ( empty( $model ) ) {
			$model = 'gpt-4o-mini';
		}
		$msgs = array( array( 'role' => 'system', 'content' => $system ) );
		foreach ( $messages as $m ) {
			$msgs[] = $m;
		}
		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 25,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			),
			'body'    => wp_json_encode( array(
				'model'       => $model,
				'messages'    => $msgs,
				'temperature' => 0.6,
				'max_tokens'  => 320,
			) ),
		) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return trim( $data['choices'][0]['message']['content'] );
		}
		return '';
	}

	private function call_gemini( $system, $messages ) {
		$key   = NURAX_Settings::get( 'stylist_key', '' );
		$model = NURAX_Settings::get( 'stylist_model', '' );
		if ( empty( $model ) ) {
			$model = 'gemini-1.5-flash';
		}
		$contents = array();
		foreach ( $messages as $m ) {
			$role       = ( 'assistant' === $m['role'] ) ? 'model' : 'user';
			$contents[] = array( 'role' => $role, 'parts' => array( array( 'text' => $m['content'] ) ) );
		}
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );
		$resp = wp_remote_post( $url, array(
			'timeout' => 25,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'system_instruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
				'contents'           => $contents,
				'generationConfig'   => array( 'temperature' => 0.6, 'maxOutputTokens' => 320 ),
			) ),
		) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
		}
		return '';
	}

	/** Rule-based fallback so the widget always helps, even with no key. */
	private function guided( $text ) {
		$t     = strtolower( $text );
		$reply = __( 'I can help with that. Tell me your budget and the occasion, or book a free consultation and a stylist will guide you.', 'nura-experience' );
		if ( false !== strpos( $t, 'bridal' ) || false !== strpos( $t, 'wedding' ) ) {
			$reply = __( 'Congratulations! For weddings our brides love our HD-lace bridal units for a flawless, photo-ready hairline. Book a free bridal fitting and we will tailor the look to your dress and face shape.', 'nura-experience' );
		} elseif ( false !== strpos( $t, 'care' ) || false !== strpos( $t, 'wash' ) || false !== strpos( $t, 'maintain' ) ) {
			$reply = __( 'To keep your unit gorgeous: wash gently with sulphate-free shampoo every 2 to 3 weeks, condition mid-to-ends, air-dry on a stand, and wrap in silk at night. Every NURA order ships with a full care guide.', 'nura-experience' );
		} elseif ( false !== strpos( $t, '5000' ) || false !== strpos( $t, '5,000' ) || false !== strpos( $t, 'cheap' ) || false !== strpos( $t, 'budget' ) || false !== strpos( $t, 'afford' ) ) {
			$reply = __( 'We have beautiful units at friendly prices - here are a few great-value picks. Would you like me to filter by length or texture?', 'nura-experience' );
		} elseif ( false !== strpos( $t, 'lace' ) || false !== strpos( $t, 'hd' ) || false !== strpos( $t, 'frontal' ) ) {
			$reply = __( 'Our HD lace fronts give an invisible, melt-into-skin hairline. Here are some favourites - I can also help you book a fitting.', 'nura-experience' );
		} elseif ( false !== strpos( $t, 'deliver' ) || false !== strpos( $t, 'ship' ) || false !== strpos( $t, 'mpesa' ) || false !== strpos( $t, 'm-pesa' ) || false !== strpos( $t, 'pay' ) ) {
			$reply = __( 'We offer same-day delivery in Nairobi and countrywide shipping, with M-Pesa, card or pay-on-delivery. Ready to order?', 'nura-experience' );
		}
		return array( 'reply' => $reply, 'products' => $this->suggest_products( $text ) );
	}

	/**
	 * Pick a few genuinely relevant products for the chat cards.
	 *
	 * Reads the shopper's message for real catalogue signals (texture,
	 * construction, length, colour, hair type, occasion and budget), matches
	 * them to the live NURA attribute taxonomies, then progressively relaxes the
	 * query so it always returns something sensible.
	 */
	private function suggest_products( $text ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$signals = $this->parse_signals( $text );
		$ids     = $this->recommend_ids( $signals, 3 );
		return $this->cards( $ids );
	}

	/** Resolve a keyword to an existing term slug in a taxonomy (tolerant). */
	private function term_slug( $taxonomy, $candidates ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		foreach ( (array) $candidates as $c ) {
			$term = get_term_by( 'slug', sanitize_title( $c ), $taxonomy );
			if ( $term ) {
				return $term->slug;
			}
			$term = get_term_by( 'name', $c, $taxonomy );
			if ( $term ) {
				return $term->slug;
			}
		}
		$search = is_array( $candidates ) ? reset( $candidates ) : $candidates;
		$found  = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 1, 'search' => $search ) );
		if ( ! is_wp_error( $found ) && ! empty( $found ) ) {
			return $found[0]->slug;
		}
		return '';
	}

	/** Extract catalogue signals from free text. */
	private function parse_signals( $text ) {
		$t   = ' ' . strtolower( $text ) . ' ';
		$sig = array( 'tax' => array(), 'cat' => '', 'max_price' => 0, 'cheapest' => false );

		$texture = array(
			'body wave' => 'Body Wave', 'bodywave' => 'Body Wave', 'loose wave' => 'Loose Wave',
			'deep wave' => 'Deep Wave', 'water' => 'Water Wave', 'kinky' => 'Kinky Straight',
			'yaki' => 'Yaki', 'curly' => 'Curly', 'straight' => 'Straight',
		);
		foreach ( $texture as $kw => $name ) {
			if ( false !== strpos( $t, $kw ) ) {
				$slug = $this->term_slug( 'pa_texture', array( $name, $kw ) );
				if ( $slug ) {
					$sig['tax']['pa_texture'] = $slug;
					break;
				}
			}
		}

		$construction = array(
			'hd lace' => 'HD Lace Front', 'lace front' => 'Lace Front', 'frontal' => 'Frontal',
			'closure' => 'Closure', 'glueless' => 'Glueless', 'headband' => 'Headband',
			'u-part' => 'U-Part', 'u part' => 'U-Part', 'wear and go' => 'Glueless', 'ready to wear' => 'Glueless',
		);
		foreach ( $construction as $kw => $name ) {
			if ( false !== strpos( $t, $kw ) ) {
				$slug = $this->term_slug( 'pa_construction', array( $name, $kw ) );
				if ( $slug ) {
					$sig['tax']['pa_construction'] = $slug;
					break;
				}
			}
		}

		$colour = array(
			'natural black' => 'Natural Black', 'jet black' => 'Jet Black', 'black' => 'Natural Black',
			'honey blonde' => 'Honey Blonde', 'blonde' => 'Blonde', 'brown' => 'Brown',
			'burgundy' => 'Burgundy', 'ginger' => 'Ginger', 'ombre' => 'Ombre', 'highlight' => 'Highlighted',
		);
		foreach ( $colour as $kw => $name ) {
			if ( false !== strpos( $t, $kw ) ) {
				$slug = $this->term_slug( 'pa_colour', array( $name, $kw ) );
				if ( $slug ) {
					$sig['tax']['pa_colour'] = $slug;
					break;
				}
			}
		}

		if ( false !== strpos( $t, 'human' ) ) {
			$slug = $this->term_slug( 'pa_hair-type', array( '100% Human Hair', 'Human Hair' ) );
			if ( $slug ) {
				$sig['tax']['pa_hair-type'] = $slug;
			}
		} elseif ( false !== strpos( $t, 'synthetic' ) ) {
			$slug = $this->term_slug( 'pa_hair-type', array( 'Synthetic' ) );
			if ( $slug ) {
				$sig['tax']['pa_hair-type'] = $slug;
			}
		}

		if ( preg_match( '/(\d{1,2})\s*(?:"|inch|inches|in)\b/', $t, $m ) ) {
			$n    = (int) $m[1];
			$slug = $this->term_slug( 'pa_length', array( $n . '"', $n . ' inch', $n . 'inch', (string) $n ) );
			if ( $slug ) {
				$sig['tax']['pa_length'] = $slug;
			}
		}

		foreach ( array( 'bridal' => 'bridal-occasion', 'wedding' => 'bridal-occasion' ) as $kw => $slug ) {
			if ( false !== strpos( $t, $kw ) && get_term_by( 'slug', $slug, 'product_cat' ) ) {
				$sig['cat'] = $slug;
				break;
			}
		}

		// Budget parsing.
		if ( preg_match( '/(?:under|below|less than|max(?:imum)?|budget(?: of)?|up ?to|within)\s*(?:kes|ksh|sh)?\s*([\d,]+)\s*(k)?/i', $text, $mp ) ) {
			$n = (int) str_replace( ',', '', $mp[1] );
			if ( ! empty( $mp[2] ) ) {
				$n *= 1000;
			}
			$sig['max_price'] = $n;
			$sig['cheapest']  = true;
		} elseif ( preg_match( '/\b(\d{1,3})\s*k\b/i', $text, $mk ) ) {
			$sig['max_price'] = (int) $mk[1] * 1000;
			$sig['cheapest']  = true;
		} elseif ( preg_match( '/(?:kes|ksh|sh)\s*([\d,]{3,})/i', $text, $mc ) ) {
			$sig['max_price'] = (int) str_replace( ',', '', $mc[1] );
		}
		if ( false !== strpos( $t, 'cheap' ) || false !== strpos( $t, 'afford' ) || false !== strpos( $t, 'budget' ) ) {
			$sig['cheapest'] = true;
		}

		return $sig;
	}

	/** Progressive-relaxation product query returning product IDs. */
	private function recommend_ids( $signals, $limit ) {
		$order  = array( 'pa_texture', 'pa_construction', 'pa_length', 'pa_hair-type', 'pa_colour' );
		$active = array();
		foreach ( $order as $k ) {
			if ( ! empty( $signals['tax'][ $k ] ) ) {
				$active[ $k ] = $signals['tax'][ $k ];
			}
		}
		$keys     = array_keys( $active );
		$attempts = array();
		for ( $i = count( $keys ); $i >= 0; $i-- ) {
			$subset = array_slice( $keys, 0, $i );
			$tx     = array();
			foreach ( $subset as $k ) {
				$tx[ $k ] = $active[ $k ];
			}
			$attempts[] = array( 'tax' => $tx, 'price' => $signals['max_price'], 'cat' => $signals['cat'] );
			if ( $signals['max_price'] ) {
				$attempts[] = array( 'tax' => $tx, 'price' => 0, 'cat' => $signals['cat'] );
			}
			if ( empty( $subset ) && $signals['cat'] ) {
				$attempts[] = array( 'tax' => $tx, 'price' => 0, 'cat' => '' );
			}
		}
		foreach ( $attempts as $a ) {
			$ids = $this->query_ids( $a['tax'], $a['price'], $a['cat'], $signals['cheapest'], $limit );
			if ( ! empty( $ids ) ) {
				return $ids;
			}
		}
		// Final fallback: any published, in-catalogue products.
		return $this->query_ids( array(), 0, '', false, $limit );
	}

	/** Run one WP_Query for product IDs matching the given constraints. */
	private function query_ids( $tax, $max_price, $cat, $cheapest, $limit ) {
		$tax_query = array( 'relation' => 'AND' );
		foreach ( $tax as $taxonomy => $slug ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug );
		}
		if ( $cat ) {
			$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat );
		}
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'slug',
			'terms'    => array( 'exclude-from-catalog' ),
			'operator' => 'NOT IN',
		);

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'tax_query'      => $tax_query,
		);
		if ( $max_price > 0 ) {
			$args['meta_query'] = array(
				array( 'key' => '_price', 'value' => $max_price, 'compare' => '<=', 'type' => 'NUMERIC' ),
			);
		}
		if ( $cheapest ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_price';
			$args['order']    = 'ASC';
		} else {
			$args['orderby'] = array( 'menu_order' => 'ASC', 'date' => 'DESC' );
		}

		$q = new WP_Query( $args );
		return $q->posts;
	}

	/** Build chat product cards from an array of product IDs. */
	private function cards( $ids ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$prod = wc_get_product( $id );
			if ( ! $prod ) {
				continue;
			}
			$img   = wp_get_attachment_image_url( $prod->get_image_id(), 'woocommerce_thumbnail' );
			$out[] = array(
				'name'  => $prod->get_name(),
				'price' => wp_strip_all_tags( wc_price( wc_get_price_to_display( $prod ) ) ),
				'img'   => $img ? $img : wc_placeholder_img_src(),
				'url'   => get_permalink( $prod->get_id() ),
			);
		}
		return $out;
	}
}
