<?php
/**
 * Customer questions hub, stylist picks and "Ask the stylist" chips (theme 1.78.0).
 *
 * Built on the plugin's customer-question engine (NURAX_Knowledge, plugin 1.81.0):
 * - /wig-questions/ : every question Kenyan shoppers ask, grouped into 12 intents,
 *   with NURA's honest answer, three live product picks per group, related
 *   journal guides and one FAQPage schema (answers mirror the visible text).
 * - [nura_stylist_picks q="..." title="..."] : three live picks for any question,
 *   usable in journal posts and pages.
 * - "Ask the stylist" chips on product and collection pages that open the NURA
 *   Stylist chat with a ready question.
 * - Customer Q&A appended to /llms-full.txt for AI search engines.
 * Everything degrades to nothing if the plugin is inactive.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_wq_ready() {
	return class_exists( 'NURAX_Knowledge' );
}

/** Product cards HTML. */
function nura_wq_cards( $cards, $title = '' ) {
	if ( ! $cards ) {
		return '';
	}
	$h = '<div class="nura-wq-picks">' . ( $title ? '<p class="nura-wq-picks__t">' . esc_html( $title ) . '</p>' : '' ) . '<ul>';
	foreach ( $cards as $c ) {
		$h .= '<li><a href="' . esc_url( $c['url'] ) . '"><img src="' . esc_url( $c['img'] ) . '" alt="' . esc_attr( $c['name'] ) . '" loading="lazy" width="160" height="160"><span class="nura-wq-picks__n">' . esc_html( $c['name'] ) . '</span><span class="nura-wq-picks__m">' . esc_html( $c['hair'] ) . '</span><span class="nura-wq-picks__p">' . esc_html( $c['price'] ) . '</span></a></li>';
	}
	return $h . '</ul></div>';
}

/** [nura_stylist_picks q="Which wig doesn't need glue?" title="Stylist picks"] */
add_shortcode( 'nura_stylist_picks', function ( $atts ) {
	if ( ! nura_wq_ready() ) {
		return '';
	}
	$a = shortcode_atts( array( 'q' => '', 'title' => __( 'NURA Stylist picks', 'nura-beauty' ), 'max' => '' ), $atts, 'nura_stylist_picks' );
	$r = NURAX_Knowledge::respond( $a['q'] . ( $a['max'] ? ' under ' . absint( $a['max'] ) : '' ) );
	return nura_wq_cards( $r['products'], $a['title'] );
} );

/** Chip button that opens the stylist chat with a question. */
function nura_wq_chip( $q, $label = '' ) {
	return '<button type="button" class="nura-ask-chip" data-nura-ask="' . esc_attr( $q ) . '">' . esc_html( $label ? $label : $q ) . '</button>';
}

/** The hub page body. */
function nura_wq_render() {
	if ( ! nura_wq_ready() ) {
		return '';
	}
	$cats = NURAX_Knowledge::categories();
	$h    = '<div class="nura-wq">';
	$h   .= '<p class="nura-answer"><strong>' . esc_html__( 'Short answer:', 'nura-beauty' ) . '</strong> ' . esc_html( NURAX_Knowledge::fill( 'At NURA Beauty, heat-resistant fibre wigs cost {fibre_min} to {fibre_max}, human-hair blend wigs start at {blend_min} and 100% human hair wigs cost {human_min} to {human_max}. Headband and 4x4 closure wigs need no glue, our studio at {address} installs and revamps wigs, and we deliver countrywide. Every answer below comes from our stylists and our live shop.' ) ) . '</p>';
	$h   .= '<p class="nura-wq-ask">' . nura_wq_chip( __( 'Help me choose a wig', 'nura-beauty' ), __( 'Ask NURA Stylist', 'nura-beauty' ) ) . ' <span>' . esc_html__( 'English, Kiswahili or Sheng: "Ni wig gani poa ya daily?"', 'nura-beauty' ) . '</span></p>';
	$h   .= nura_sf_render();
	$h   .= '<nav class="nura-wq-toc" aria-label="' . esc_attr__( 'Question topics', 'nura-beauty' ) . '"><ol>';
	foreach ( $cats as $c ) {
		$h .= '<li><a href="#' . esc_attr( $c['id'] ) . '">' . esc_html( $c['title'] ) . '</a></li>';
	}
	$h .= '</ol></nav>';
	foreach ( $cats as $c ) {
		$h .= '<section class="nura-wq-sec" id="' . esc_attr( $c['id'] ) . '"><h2>' . esc_html( $c['title'] ) . '</h2>';
		foreach ( (array) $c['q'] as $q ) {
			$h .= '<details class="nura-wq-q"><summary><h3>' . esc_html( $q[0] ) . '</h3></summary><p>' . esc_html( NURAX_Knowledge::fill( $q[1] ) ) . '</p></details>';
		}
		$h .= nura_wq_cards( NURAX_Knowledge::cards( NURAX_Knowledge::pick( (array) $c['h'] + ( isset( $c['q'][0][2] ) ? (array) $c['q'][0][2] : array() ), 3 ) ), __( 'Stylist picks for this', 'nura-beauty' ) );
		if ( function_exists( 'nura_hub_link_list' ) ) {
			$h .= nura_hub_link_list( (array) $c['guides'], __( 'Read more', 'nura-beauty' ) );
		}
		$h .= '<p class="nura-wq-ask">' . nura_wq_chip( $c['q'][0][0], __( 'Ask the stylist about this', 'nura-beauty' ) ) . '</p></section>';
	}
	return $h . '</div>';
}
add_shortcode( 'nura_wig_questions', 'nura_wq_render' );

/* ------------------------------------------------------------------
 * 1.79.0: NURA AI Stylist 5-step finder (owner strategy doc, section 3).
 * Budget band -> wig type -> look -> installation -> length, then three
 * in-stock live products with "why it fits" and the right next step.
 * Plain HTML form (GET), no JS required, works on cached pages.
 * ------------------------------------------------------------------ */
function nura_sf_steps() {
	return array(
		'b' => array( __( 'What is your budget?', 'nura-beauty' ), array( 'u3' => __( 'Under KES 3,000', 'nura-beauty' ), '35' => __( 'KES 3,000 - 5,000', 'nura-beauty' ), '510' => __( 'KES 5,000 - 10,000', 'nura-beauty' ), '1015' => __( 'KES 10,000 - 15,000', 'nura-beauty' ), '15' => __( 'KES 15,000+', 'nura-beauty' ) ) ),
		't' => array( __( 'What type of wig do you prefer?', 'nura-beauty' ), array( 'easy' => __( 'Easy to wear / glueless', 'nura-beauty' ), 'lace' => __( 'Lace front', 'nura-beauty' ), 'closure' => __( '4x4 closure', 'nura-beauty' ), 'headband' => __( 'Headband wig', 'nura-beauty' ), 'any' => __( 'No preference - recommend for me', 'nura-beauty' ) ) ),
		'l' => array( __( 'What look are you going for?', 'nura-beauty' ), array( 'natural' => __( 'Natural everyday look', 'nura-beauty' ), 'glam' => __( 'Elegant and glamorous', 'nura-beauty' ), 'office' => __( 'Professional / office look', 'nura-beauty' ), 'curly' => __( 'Curly and voluminous', 'nura-beauty' ), 'bob' => __( 'Short bob hairstyle', 'nura-beauty' ), 'trendy' => __( 'Something trendy', 'nura-beauty' ) ) ),
		'i' => array( __( 'Do you need installation?', 'nura-beauty' ), array( 'pro' => __( 'Yes, professional installation', 'nura-beauty' ), 'self' => __( 'No, I will install it myself', 'nura-beauty' ), 'ready' => __( 'I want ready-to-wear', 'nura-beauty' ) ) ),
		'n' => array( __( 'Preferred length', 'nura-beauty' ), array( 'short' => __( 'Short (8-10 inches)', 'nura-beauty' ), 'medium' => __( 'Medium (12-16 inches)', 'nura-beauty' ), 'long' => __( 'Long (18-22 inches)', 'nura-beauty' ), 'any' => __( 'No preference', 'nura-beauty' ) ) ),
	);
}

/** Answers -> product hints. */
function nura_sf_hints( $a ) {
	$h     = array();
	$bands = array( 'u3' => array( 0, 3000 ), '35' => array( 3000, 5000 ), '510' => array( 5000, 10000 ), '1015' => array( 10000, 15000 ), '15' => array( 15000, 0 ) );
	if ( isset( $bands[ $a['b'] ] ) ) {
		list( $lo, $hi ) = $bands[ $a['b'] ];
		if ( $lo ) {
			$h['min'] = $lo;
		}
		if ( $hi ) {
			$h['max'] = $hi;
		}
		$h['sort'] = 'u3' === $a['b'] ? 'cheap' : '';
	}
	$type = array( 'easy' => 'headband', 'lace' => 'lace', 'closure' => 'closure', 'headband' => 'headband' );
	if ( isset( $type[ $a['t'] ] ) ) {
		$h['construction'] = $type[ $a['t'] ];
	}
	switch ( $a['l'] ) {
		case 'natural':
			$h['texture'] = 'yaki';
			break;
		case 'glam':
			$h['hair']    = 'human';
			$h['texture'] = 'curl';
			break;
		case 'office':
			$h['occasion'] = 'work';
			break;
		case 'curly':
			$h['texture'] = 'curl';
			break;
		case 'bob':
			$h['style'] = 'bob';
			break;
		case 'trendy':
			$h['colour'] = 'ombre';
			break;
	}
	if ( 'pro' === $a['i'] && empty( $h['construction'] ) ) {
		$h['construction'] = 'lace';
	} elseif ( in_array( $a['i'], array( 'self', 'ready' ), true ) && empty( $h['construction'] ) ) {
		$h['construction'] = 'headband';
	}
	$len = array( 'short' => array( 'bob', '8"', '10"' ), 'medium' => array( '12"', '14"', '16"' ), 'long' => array( '18"', '20"', '22"' ) );
	if ( isset( $len[ $a['n'] ] ) ) {
		$h['lenset'] = $len[ $a['n'] ];
	}
	return $h;
}

function nura_sf_render() {
	if ( ! nura_wq_ready() ) {
		return '';
	}
	$steps = nura_sf_steps();
	$a     = array();
	foreach ( $steps as $k => $s ) {
		$v       = isset( $_GET[ 'nsf_' . $k ] ) ? sanitize_key( wp_unslash( $_GET[ 'nsf_' . $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter form.
		$a[ $k ] = isset( $s[1][ $v ] ) ? $v : '';
	}
	$done = '' !== $a['b'];
	$h    = '<div class="nura-sf" id="nura-stylist-finder"><p class="nura-sf__kicker">' . esc_html__( 'NURA AI Stylist', 'nura-beauty' ) . '</p><h2>' . esc_html__( 'Find your perfect wig', 'nura-beauty' ) . '</h2>';
	$h   .= '<form method="get" action="#nura-stylist-finder" class="nura-sf__form">';
	$i    = 1;
	foreach ( $steps as $k => $s ) {
		$h .= '<fieldset class="nura-sf__step"><legend>' . esc_html( sprintf( __( 'Step %1$d. %2$s', 'nura-beauty' ), $i++, $s[0] ) ) . '</legend>';
		foreach ( $s[1] as $v => $label ) {
			$h .= '<label class="nura-sf__opt"><input type="radio" name="nsf_' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '"' . checked( $a[ $k ], $v, false ) . ( 'b' === $k ? ' required' : '' ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		$h .= '</fieldset>';
	}
	$h .= '<button type="submit" class="nura-btn nura-btn--gold">' . esc_html__( 'Find my perfect wig', 'nura-beauty' ) . '</button></form>';
	if ( $done ) {
		$hints = nura_sf_hints( $a );
		$ids   = NURAX_Knowledge::pick( $hints, 3 );
		$cards = NURAX_Knowledge::cards( $ids );
		$h    .= '<div class="nura-sf__res" aria-live="polite"><h3>' . esc_html__( 'Your top 3 matches', 'nura-beauty' ) . '</h3>';
		if ( $cards ) {
			$h .= '<ul class="nura-sf__cards">';
			foreach ( $cards as $c ) {
				$why = NURAX_Knowledge::why( $c['id'], $hints );
				$h  .= '<li><a href="' . esc_url( $c['url'] ) . '"><img src="' . esc_url( $c['img'] ) . '" alt="' . esc_attr( $c['name'] ) . '" loading="lazy" width="200" height="200"><strong>' . esc_html( $c['name'] ) . '</strong><span>' . esc_html( $c['price'] ) . ' &middot; ' . esc_html( $c['hair'] ) . '</span></a>';
				if ( $why ) {
					$h .= '<ul class="nura-sf__why"><li>' . implode( '</li><li>', array_map( 'esc_html', $why ) ) . '</li></ul>';
				}
				$h .= '</li>';
			}
			$h .= '</ul>';
		}
		$book = home_url( '/book-appointment/?service=installation' );
		$h   .= '<p class="nura-sf__next">' . ( 'pro' === $a['i'] ? '<a class="nura-btn nura-btn--ghost" href="' . esc_url( $book ) . '">' . esc_html__( 'Book installation (from KSh 1,500)', 'nura-beauty' ) . '</a> ' : '' ) . nura_wq_chip( __( 'Help me choose between these wigs', 'nura-beauty' ), __( 'Ask NURA Stylist', 'nura-beauty' ) ) . '</p>';
		$h   .= '<p class="nura-sf__note">' . esc_html__( 'Live prices and stock from our shop. Prefer a person? Visit our studio or message us on WhatsApp.', 'nura-beauty' ) . '</p></div>';
	}
	return $h . '</div>';
}
add_shortcode( 'nura_stylist_finder', 'nura_sf_render' );

add_action( 'wp_head', function () {
	if ( nura_wq_ready() ) {
		echo '<style>.nura-sf{border:1px solid rgba(0,0,0,.12);border-radius:14px;padding:20px;margin:24px 0}.nura-sf__kicker{font-size:12px;letter-spacing:.1em;text-transform:uppercase;opacity:.7;margin:0}.nura-sf__step{border:0;padding:0;margin:14px 0}.nura-sf__step legend{font-weight:600;margin-bottom:8px}.nura-sf__opt{display:inline-block;margin:0 6px 6px 0}.nura-sf__opt input{position:absolute;opacity:0}.nura-sf__opt span{display:inline-block;border:1px solid rgba(0,0,0,.25);border-radius:999px;padding:7px 14px;cursor:pointer;font-size:14px}.nura-sf__opt input:checked+span{background:#111;color:#fff;border-color:#111}.nura-sf__opt input:focus-visible+span{outline:2px solid #b8925a}.nura-sf__cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;list-style:none;padding:0}.nura-sf__cards a{display:flex;flex-direction:column;gap:4px;color:inherit;text-decoration:none}.nura-sf__cards img{width:100%;height:auto;aspect-ratio:1;object-fit:cover;border-radius:10px}.nura-sf__why{font-size:13px;margin:6px 0 0;padding-left:18px}.nura-sf__note{font-size:13px;opacity:.7}</style>' . "\n";
	}
}, 40 );


/** Create the hub page once (and when the theme updates the version). */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.79.0' === get_option( 'nura_wq_page' ) ) {
		return;
	}
	update_option( 'nura_wq_page', '1.79.0', false );
	if ( ! get_page_by_path( 'find-your-wig' ) ) { // 1.79.0: 5-step AI Stylist finder page.
		$sid = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => 'find-your-wig',
			'post_title'   => 'Find Your Perfect Wig: NURA AI Stylist',
			'post_status'  => 'publish',
			'post_content' => '<p class="nura-answer"><strong>Short answer:</strong> answer five quick questions (budget, wig type, look, installation and length) and NURA AI Stylist shows three wigs from our live shop that fit, with the reasons why.</p>[nura_stylist_finder]<p>Want to read first? See <a href="/wig-questions/">120+ wig questions answered</a>.</p>',
		), true );
		if ( $sid && ! is_wp_error( $sid ) ) {
			update_post_meta( $sid, 'rank_math_title', 'Find Your Perfect Wig in 5 Questions | NURA AI Stylist Kenya' );
			update_post_meta( $sid, 'rank_math_description', 'Tell NURA AI Stylist your budget, wig type, look, installation and length, and get three live wig matches from our Nairobi shop, with prices and why each fits.' );
			update_post_meta( $sid, 'rank_math_focus_keyword', 'find your perfect wig' );
		}
	}
	$p = get_page_by_path( 'wig-questions' );
	if ( ! $p ) {
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_name'    => 'wig-questions',
			'post_title'   => 'Wig Questions Kenya: Honest Answers from NURA Stylists',
			'post_status'  => 'publish',
			'post_content' => '[nura_wig_questions]',
		), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'rank_math_title', 'Wig Questions Kenya: Prices, Glueless, Installation & Care | NURA Beauty' );
			update_post_meta( $id, 'rank_math_description', 'Honest answers to 120+ wig questions Kenyans ask: prices, human hair vs semi-human vs fibre, glueless wigs, installation in Nairobi, face shape, colours, care and delivery.' );
			update_post_meta( $id, 'rank_math_focus_keyword', 'wig questions kenya' );
		}
	}
	delete_transient( 'nura_llms' );
	delete_transient( 'nura_llms_full' );
} );

/** FAQPage schema on the hub (mirrors the visible answers). */
add_action( 'wp_head', function () {
	if ( ! nura_wq_ready() || ! is_page( 'wig-questions' ) ) {
		return;
	}
	$items = array();
	foreach ( NURAX_Knowledge::faq() as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => get_permalink() . '#faq', 'mainEntity' => $items ) ) . '</script>' . "\n";
}, 30 );

/** Contextual "Ask the stylist" chips. */
function nura_wq_product_qs() {
	$qs = array();
	$p  = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	if ( $p ) {
		$t = strtolower( $p->get_name() . ' ' . $p->get_attribute( 'pa_construction' ) . ' ' . $p->get_attribute( 'pa_hair-type' ) );
		if ( false !== strpos( $t, 'lace' ) ) {
			$qs[] = __( 'Can you install this lace front for me in Nairobi?', 'nura-beauty' );
		}
		if ( false !== strpos( $t, 'headband' ) || false !== strpos( $t, 'closure' ) ) {
			$qs[] = __( 'Can I wear this wig without glue?', 'nura-beauty' );
		}
		$qs[] = false !== strpos( $t, 'human' ) && false === strpos( $t, 'blend' ) ? __( 'How long does a human hair wig last?', 'nura-beauty' ) : __( 'How long does a fibre wig last?', 'nura-beauty' );
	}
	$qs[] = __( 'Will this suit my face shape?', 'nura-beauty' );
	$qs[] = __( 'Do you deliver to my county?', 'nura-beauty' );
	return array_slice( $qs, 0, 4 );
}
function nura_wq_chips_html( $qs ) {
	$h = '<div class="nura-ask-row"><span class="nura-ask-row__t">' . esc_html__( 'Ask NURA Stylist', 'nura-beauty' ) . '</span>';
	foreach ( $qs as $q ) {
		$h .= nura_wq_chip( $q );
	}
	$u = function_exists( 'nura_hub_url' ) ? nura_hub_url( 'wig-questions', 'page' ) : '';
	$fy = function_exists( 'nura_hub_url' ) ? nura_hub_url( 'find-your-wig', 'page' ) : '';
	if ( $fy ) {
		$h .= '<a class="nura-ask-row__more" href="' . esc_url( $fy ) . '">' . esc_html__( 'Find my wig in 5 questions', 'nura-beauty' ) . '</a>';
	}
	if ( $u ) {
		$h .= '<a class="nura-ask-row__more" href="' . esc_url( $u ) . '">' . esc_html__( 'All wig questions', 'nura-beauty' ) . '</a>';
	}
	return $h . '</div>';
}
add_action( 'woocommerce_single_product_summary', function () {
	if ( nura_wq_ready() ) {
		echo nura_wq_chips_html( nura_wq_product_qs() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	}
}, 46 );
add_action( 'woocommerce_before_shop_loop', function () {
	if ( ! nura_wq_ready() || is_search() ) {
		return;
	}
	echo nura_wq_chips_html( array( __( 'Which wig is best for a beginner?', 'nura-beauty' ), __( 'Do you have good wigs under KES 3,000?', 'nura-beauty' ), __( 'Which wig looks most natural?', 'nura-beauty' ), 'Mnaweka wig?' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
}, 25 );

/** Chips open the stylist chat and send the question (or go to WhatsApp). */
add_action( 'wp_footer', function () {
	if ( ! nura_wq_ready() ) {
		return;
	}
	?>
<style>.nura-ask-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:14px 0}.nura-ask-row__t{font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.7;margin-right:4px}.nura-ask-chip{border:1px solid currentColor;background:transparent;border-radius:999px;padding:6px 12px;font-size:13px;cursor:pointer;color:inherit}.nura-ask-chip:hover{background:#111;color:#fff}.nura-ask-row__more{font-size:13px;text-decoration:underline}.nura-wq-picks ul{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;list-style:none;padding:0;margin:10px 0}.nura-wq-picks a{display:flex;flex-direction:column;gap:4px;text-decoration:none;color:inherit}.nura-wq-picks img{width:100%;height:auto;aspect-ratio:1;object-fit:cover;border-radius:8px}.nura-wq-picks__t{font-weight:600;margin:16px 0 4px}.nura-wq-picks__n{font-weight:600;font-size:14px}.nura-wq-picks__m{font-size:12px;opacity:.7}.nura-wq-picks__p{font-size:14px}.nura-wq-q{border-bottom:1px solid rgba(0,0,0,.1);padding:10px 0}.nura-wq-q summary{cursor:pointer;list-style:none}.nura-wq-q summary h3{display:inline;font-size:1rem;margin:0}.nura-wq-toc ol{columns:2;gap:24px}.nura-wq-sec{margin:36px 0}</style>
<script>(function(){document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-nura-ask]');if(!b){return;}e.preventDefault();var q=b.getAttribute('data-nura-ask');var root=document.querySelector('[data-nurax-stylist]');if(!root){var wa=(window.nuraWa||'https://wa.me/254714994898');window.open(wa+'?text='+encodeURIComponent(q),'_blank');return;}var panel=root.querySelector('[data-stylist-panel]');if(panel&&panel.hidden){var t=root.querySelector('[data-stylist-toggle]');if(t){t.click();}}var f=root.querySelector('[data-stylist-form]');var i=f&&f.querySelector('input[name="msg"]');if(i){i.value=q;if(f.requestSubmit){f.requestSubmit();}else{f.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}));}}});})();</script>
	<?php
}, 99 );

/** Customer Q&A for AI search engines (/llms-full.txt). */
add_filter( 'nura_llms_extra', function ( $lines, $full ) {
	if ( ! nura_wq_ready() || ! $full ) {
		return $lines;
	}
	$lines[] = '## Customer questions answered by NURA stylists';
	foreach ( NURAX_Knowledge::faq() as $qa ) {
		$lines[] = '### ' . $qa[0];
		$lines[] = $qa[1];
		$lines[] = '';
	}
	return $lines;
}, 10, 2 );

/* ------------------------------------------------------------------
 * 1.80.0: "Questions about this wig" on every product page - answers built
 * from the product's own hair type, construction, price and the shop facts,
 * visible text mirrored in a FAQPage node for answer engines.
 * ------------------------------------------------------------------ */
function nura_pq_items( $p ) {
	if ( ! $p || ! nura_wq_ready() ) {
		return array();
	}
	$name  = wp_strip_all_tags( $p->get_name() );
	$t     = strtolower( $name . ' ' . $p->get_attribute( 'pa_construction' ) . ' ' . $p->get_attribute( 'pa_hair-type' ) );
	$hair  = false !== strpos( $t, 'blend' ) || false !== strpos( $t, 'semi' ) ? 'blend' : ( false !== strpos( $t, 'human' ) ? 'human' : 'fibre' );
	$price = $p->is_type( 'variable' ) ? (float) $p->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $p );
	$tok   = NURAX_Knowledge::tokens();
	$q     = array();
	$hq    = array(
		'human' => array( sprintf( __( 'Is the %s real human hair?', 'nura-beauty' ), $name ), __( 'Yes. It is 100% human hair, so you can straighten, curl and colour it (use a heat protectant). With regular wear and good care it lasts about a year or more.', 'nura-beauty' ) ),
		'blend' => array( sprintf( __( 'Is the %s real human hair?', 'nura-beauty' ), $name ), __( 'Partly. It is a human-hair blend (semi-human): human hair mixed with heat-resistant fibre, softer than fibre and less than 100% human hair. Use low to medium heat only. It lasts about 6 to 9 months with good care.', 'nura-beauty' ) ),
		'fibre' => array( sprintf( __( 'Is the %s human hair?', 'nura-beauty' ), $name ), __( 'No. It is heat-resistant fibre: styled out of the box, easy to care for and affordable. Use low heat only. It lasts about 3 to 6 months of regular wear with good care.', 'nura-beauty' ) ),
	);
	$q[] = $hq[ $hair ];
	if ( false !== strpos( $t, 'headband' ) ) {
		$q[] = array( __( 'Do I need glue for this wig?', 'nura-beauty' ), __( 'No. It is a headband wig: no lace and no glue. Slip it on, adjust the band and go, in about five minutes.', 'nura-beauty' ) );
	} elseif ( false !== strpos( $t, 'closure' ) ) {
		$q[] = array( __( 'Do I need glue for this wig?', 'nura-beauty' ), __( 'Usually not. 4x4 closure wigs are normally worn glueless with the adjustable straps and combs, and give a natural parting.', 'nura-beauty' ) );
	} else {
		$q[] = array( __( 'Can you install this lace front for me?', 'nura-beauty' ), sprintf( __( 'Yes. Our stylists fit it, cut the lace and style the hairline at %1$s. Installation is from KSh 1,500; book a morning slot online.', 'nura-beauty' ), $tok['{address}'] ) );
	}
	$q[] = array( __( 'How much is delivery and how fast is it?', 'nura-beauty' ), sprintf( __( 'Same day in Nairobi for orders before %1$s (rider from KSh 300, free in Nairobi over KSh 10,000), and 1 to 3 business days countrywide (%2$s). Pay by M-Pesa or card; there is no cash on delivery.', 'nura-beauty' ), $tok['{cutoff}'], $tok['{delivery_ke}'] ) );
	if ( $price > 0 ) {
		$q[] = array( __( 'Can I pay for this wig in instalments?', 'nura-beauty' ), sprintf( __( 'Yes, with Lipa Mdogo Mdogo: pay a 50%% deposit (about %s) and the balance within 1 or 2 weeks, then we release your wig.', 'nura-beauty' ), 'KSh ' . number_format( ceil( $price / 2 ) ) ) );
	}
	$q[] = array( __( 'Can I return it if it does not suit me?', 'nura-beauty' ), $tok['{returns}'] . ' ' . __( 'Returns are accepted at the studio or by courier.', 'nura-beauty' ) );
	return $q;
}

add_action( 'woocommerce_after_single_product_summary', function () {
	global $product;
	$items = nura_pq_items( $product );
	if ( ! $items ) {
		return;
	}
	echo '<section class="nura-pq"><h2>' . esc_html__( 'Questions about this wig', 'nura-beauty' ) . '</h2>';
	foreach ( $items as $qa ) {
		echo '<details class="nura-wq-q"><summary><h3>' . esc_html( $qa[0] ) . '</h3></summary><p>' . esc_html( $qa[1] ) . '</p></details>';
	}
	echo '</section>';
}, 12 );

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$p     = wc_get_product( get_queried_object_id() );
	$items = array();
	foreach ( nura_pq_items( $p ) as $qa ) {
		$items[] = array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) );
	}
	if ( $items ) {
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => get_permalink( $p->get_id() ) . '#questions', 'mainEntity' => $items ) ) . '</script>' . "\n";
	}
}, 31 );

/* ------------------------------------------------------------------
 * 1.80.0: older journal guides get live stylist picks + links to the
 * finder and the questions hub at the end (new guides already embed picks).
 * ------------------------------------------------------------------ */
function nura_guide_pick_queries() {
	return apply_filters( 'nura_guide_pick_queries', array(
		'human-hair-vs-semi-human-vs-fibre-wigs-kenya' => 'best value wig',
		'how-long-does-a-wig-last'                     => 'human hair wig',
		'frontal-vs-closure-wig'                       => '4x4 closure wig',
		'how-to-care-for-a-fibre-wig'                  => 'fibre wig',
		'how-to-care-for-a-human-hair-wig'             => 'human hair wig',
		'headband-wig-vs-lace-front-wig'               => 'headband wig without glue',
		'glueless-wigs-kenya'                          => 'glueless headband wig',
		'how-to-choose-a-wig-for-your-face-shape'      => 'bob wig',
		'how-to-measure-your-head-for-a-wig'           => 'headband wig adjustable',
		'wig-installation-nairobi-what-to-expect'      => 'lace front human hair wig',
		'how-to-buy-wigs-online-in-kenya-safely'       => 'best value wig',
		'bridal-wigs-kenya'                            => 'wedding lace front wig',
		'best-wigs-for-beginners-kenya'                => 'beginner headband wig',
		'semi-human-wigs-kenya-guide'                  => 'semi-human blend wig',
		'wig-revamp-nairobi'                           => 'human hair wig',
	) );
}
add_filter( 'the_content', function ( $content ) {
	if ( ! nura_wq_ready() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || false !== strpos( $content, 'nura-wq-picks' ) ) {
		return $content;
	}
	$map  = nura_guide_pick_queries();
	$slug = get_post_field( 'post_name', get_the_ID() );
	if ( ! isset( $map[ $slug ] ) ) {
		return $content;
	}
	$cards = NURAX_Knowledge::cards( NURAX_Knowledge::pick( array_merge( (array) ( NURAX_Knowledge::match( $map[ $slug ] )['h'] ?? array() ), NURAX_Knowledge::hints_from_text( $map[ $slug ] ) ), 3 ) );
	$fy    = function_exists( 'nura_hub_url' ) ? nura_hub_url( 'find-your-wig', 'page' ) : '';
	$wq    = function_exists( 'nura_hub_url' ) ? nura_hub_url( 'wig-questions', 'page' ) : '';
	$more  = '';
	if ( $fy || $wq ) {
		$more = '<p class="nura-wq-more">' . ( $fy ? '<a href="' . esc_url( $fy ) . '">' . esc_html__( 'Find your wig in 5 questions', 'nura-beauty' ) . '</a>' : '' ) . ( $fy && $wq ? ' &middot; ' : '' ) . ( $wq ? '<a href="' . esc_url( $wq ) . '">' . esc_html__( '120+ wig questions answered', 'nura-beauty' ) . '</a>' : '' ) . '</p>';
	}
	return $content . nura_wq_cards( $cards, __( 'NURA Stylist picks for this guide', 'nura-beauty' ) ) . $more;
}, 30 );
