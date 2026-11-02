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

/** Create the hub page once (and when the theme updates the version). */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || '1.78.0' === get_option( 'nura_wq_page' ) ) {
		return;
	}
	update_option( 'nura_wq_page', '1.78.0', false );
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
