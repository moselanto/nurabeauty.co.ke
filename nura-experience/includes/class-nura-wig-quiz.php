<?php
/**
 * NURA "Find Your Wig" quiz - a friction-light guided finder.
 *
 * A 4-question quiz (style / texture / length / budget) that sends the shopper
 * straight to matching products by building a URL for the native NURA shop
 * filters (WooCommerce layered-nav filter_* + price query vars). No lead form
 * and no server round-trip - it complements, and does not replace, the fuller
 * lead-capture consultation in NURAX_AI_Wig_Finder. Renders only where the
 * [nura_wig_quiz] (or [nura_find_your_wig]) shortcode is placed, so it stays
 * dormant until you add it to a page.
 *
 * Every answer -> filter mapping is resolved from the REAL attribute terms that
 * exist in the catalogue (matched by term name), so a term the store has not
 * created is simply omitted - never a broken filter that lands on an empty
 * page. Mappings can be replaced wholesale via the nurax_wig_quiz_questions
 * filter.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Wig_Quiz {

	public function __construct() {
		add_shortcode( 'nura_wig_quiz', array( $this, 'render' ) );
		add_shortcode( 'nura_find_your_wig', array( $this, 'render' ) );
	}

	/** Real term slugs for the given attribute (slug without pa_) matching a list of term names. */
	private function slugs_for( $attr, $names ) {
		if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return array();
		}
		$tax = wc_attribute_taxonomy_name( $attr );
		if ( ! taxonomy_exists( $tax ) ) {
			return array();
		}
		$out = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$out[] = $term->slug;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Length term slugs bucketed by the inches parsed from each term name. */
	private function length_buckets() {
		$buckets = array( 'short' => array(), 'medium' => array(), 'long' => array(), 'extra' => array() );
		if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return $buckets;
		}
		$tax = wc_attribute_taxonomy_name( 'length' );
		if ( ! taxonomy_exists( $tax ) ) {
			return $buckets;
		}
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $buckets;
		}
		foreach ( $terms as $t ) {
			$in = (int) preg_replace( '/[^0-9]/', '', $t->name );
			if ( $in <= 0 ) {
				continue;
			}
			if ( $in <= 12 ) {
				$buckets['short'][] = $t->slug;
			} elseif ( $in <= 18 ) {
				$buckets['medium'][] = $t->slug;
			} elseif ( $in <= 24 ) {
				$buckets['long'][] = $t->slug;
			} else {
				$buckets['extra'][] = $t->slug;
			}
		}
		return $buckets;
	}

	/** One filter param fragment for an attribute, dropping empties. */
	private function facet( $attr, $names ) {
		$slugs = $this->slugs_for( $attr, $names );
		if ( empty( $slugs ) ) {
			return array();
		}
		return array( 'filter_' . $attr => implode( ',', $slugs ) );
	}

	private function length_param( $slugs ) {
		if ( empty( $slugs ) ) {
			return array();
		}
		return array( 'filter_length' => implode( ',', $slugs ) );
	}

	/** The four questions with answer -> shop-filter mappings, built from real catalogue terms. */
	private function questions() {
		$len = $this->length_buckets();

		$questions = array(
			array(
				'key'   => 'style',
				'label' => __( 'What is your preferred style?', 'nura-experience' ),
				'opts'  => array(
					array( 'label' => __( 'Natural', 'nura-experience' ),    'params' => $this->facet( 'style', array( 'Natural', 'Middle Part', 'Side Part' ) ) ),
					array( 'label' => __( 'Glamorous', 'nura-experience' ),  'params' => $this->facet( 'occasion', array( 'Party', 'Red Carpet', 'Photoshoot', 'Date Night' ) ) ),
					array( 'label' => __( 'Everyday', 'nura-experience' ),   'params' => $this->facet( 'occasion', array( 'Everyday', 'Work' ) ) ),
					array( 'label' => __( 'Protective', 'nura-experience' ), 'params' => $this->facet( 'construction', array( 'Glueless', 'Headband', 'Wear & Go' ) ) ),
					array( 'label' => __( 'Bold', 'nura-experience' ),       'params' => $this->facet( 'colour', array( 'Burgundy', 'Blonde', 'Honey Blonde', 'Ginger', 'Ombre', 'Highlighted' ) ) ),
				),
			),
			array(
				'key'   => 'texture',
				'label' => __( 'What texture do you love?', 'nura-experience' ),
				'opts'  => array(
					array( 'label' => __( 'Straight', 'nura-experience' ),   'params' => $this->facet( 'texture', array( 'Bone Straight', 'Silky Straight', 'Yaki Straight', 'Natural Straight' ) ) ),
					array( 'label' => __( 'Body Wave', 'nura-experience' ),  'params' => $this->facet( 'texture', array( 'Body Wave', 'Loose Wave', 'Beach Wave' ) ) ),
					array( 'label' => __( 'Water Curl', 'nura-experience' ), 'params' => $this->facet( 'texture', array( 'Water Curl', 'Water Wave' ) ) ),
					array( 'label' => __( 'Curly', 'nura-experience' ),      'params' => $this->facet( 'texture', array( 'Curly', 'Deep Curly', 'Jerry Curl', 'Kinky Curly', 'Afro Curly' ) ) ),
					array( 'label' => __( 'Kinky', 'nura-experience' ),      'params' => $this->facet( 'texture', array( 'Kinky', 'Kinky Curly', 'Afro' ) ) ),
					array( 'label' => __( 'Deep Wave', 'nura-experience' ),  'params' => $this->facet( 'texture', array( 'Deep Wave' ) ) ),
				),
			),
			array(
				'key'   => 'length',
				'label' => __( 'What length?', 'nura-experience' ),
				'opts'  => array(
					array( 'label' => __( 'Short', 'nura-experience' ),      'params' => $this->length_param( $len['short'] ) ),
					array( 'label' => __( 'Medium', 'nura-experience' ),     'params' => $this->length_param( $len['medium'] ) ),
					array( 'label' => __( 'Long', 'nura-experience' ),       'params' => $this->length_param( $len['long'] ) ),
					array( 'label' => __( 'Extra Long', 'nura-experience' ), 'params' => $this->length_param( $len['extra'] ) ),
				),
			),
			array(
				'key'   => 'budget',
				'label' => __( 'What is your budget?', 'nura-experience' ),
				'opts'  => array(
					array( 'label' => __( 'Under KSh 5,000', 'nura-experience' ),    'params' => array( 'max_price' => '5000' ) ),
					array( 'label' => __( 'KSh 5,000 - 10,000', 'nura-experience' ),  'params' => array( 'min_price' => '5000', 'max_price' => '10000' ) ),
					array( 'label' => __( 'KSh 10,000 - 20,000', 'nura-experience' ), 'params' => array( 'min_price' => '10000', 'max_price' => '20000' ) ),
					array( 'label' => __( 'KSh 20,000+', 'nura-experience' ),         'params' => array( 'min_price' => '20000' ) ),
				),
			),
		);

		/** Allow full customisation of the quiz questions and their filter mappings. */
		return apply_filters( 'nurax_wig_quiz_questions', $questions );
	}

	private function shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/shop/' );
	}

	public function render( $atts = array() ) {
		$atts = shortcode_atts( array(
			'title'    => __( 'Find Your Wig', 'nura-experience' ),
			'subtitle' => __( 'Answer four quick questions and we will show you the wigs that fit.', 'nura-experience' ),
			'button'   => __( 'Show My Wigs', 'nura-experience' ),
		), $atts, 'nura_wig_quiz' );

		$questions = $this->questions();

		ob_start(); ?>
		<div class="nura-wig-quiz" data-shop="<?php echo esc_url( $this->shop_url() ); ?>">
			<div class="nqz-head">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
				<p><?php echo esc_html( $atts['subtitle'] ); ?></p>
			</div>
			<?php foreach ( $questions as $q ) : ?>
				<fieldset class="nqz-q">
					<legend><?php echo esc_html( $q['label'] ); ?></legend>
					<div class="nqz-opts">
						<?php foreach ( $q['opts'] as $opt ) : ?>
							<?php if ( empty( $opt['params'] ) ) { continue; } ?>
							<button type="button" class="nqz-opt" data-q="<?php echo esc_attr( $q['key'] ); ?>" data-params="<?php echo esc_attr( wp_json_encode( $opt['params'] ) ); ?>"><?php echo esc_html( $opt['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>
			<div class="nqz-actions">
				<button type="button" class="nqz-go nura-btn nura-btn--gold"><?php echo esc_html( $atts['button'] ); ?></button>
				<a class="nqz-all" href="<?php echo esc_url( $this->shop_url() ); ?>"><?php esc_html_e( 'Browse everything', 'nura-experience' ); ?></a>
			</div>
		</div>
		<style>
		.nura-wig-quiz{max-width:820px;margin:2rem auto;padding:1.75rem;border:1px solid #eee;border-radius:16px;background:#fff}
		.nura-wig-quiz .nqz-head{text-align:center;margin-bottom:1.25rem}
		.nura-wig-quiz .nqz-head h2{margin:0 0 .35rem;font-size:1.6rem}
		.nura-wig-quiz .nqz-head p{margin:0;color:#666}
		.nura-wig-quiz .nqz-q{border:0;margin:0 0 1.1rem;padding:0}
		.nura-wig-quiz .nqz-q legend{font-weight:600;margin-bottom:.5rem;padding:0}
		.nura-wig-quiz .nqz-opts{display:flex;flex-wrap:wrap;gap:.5rem}
		.nura-wig-quiz .nqz-opt{padding:.55rem 1rem;border:1px solid #d9d2c5;border-radius:999px;background:#faf8f4;cursor:pointer;font:inherit;transition:all .15s}
		.nura-wig-quiz .nqz-opt:hover{border-color:#b8975a}
		.nura-wig-quiz .nqz-opt.is-on{background:#1a1a1a;color:#fff;border-color:#1a1a1a}
		.nura-wig-quiz .nqz-actions{display:flex;align-items:center;gap:1rem;margin-top:1.4rem;flex-wrap:wrap}
		.nura-wig-quiz .nqz-go{padding:.8rem 1.8rem;border:0;border-radius:999px;background:#b8975a;color:#1a1a1a;font-weight:700;cursor:pointer}
		.nura-wig-quiz .nqz-all{color:#666;text-decoration:underline}
		</style>
		<script>
		(function(){
			var quizzes = document.querySelectorAll('.nura-wig-quiz');
			Array.prototype.forEach.call(quizzes, function(quiz){
				var chosen = {};
				Array.prototype.forEach.call(quiz.querySelectorAll('.nqz-opt'), function(btn){
					btn.addEventListener('click', function(){
						var q = btn.getAttribute('data-q');
						Array.prototype.forEach.call(quiz.querySelectorAll('.nqz-opt[data-q="' + q + '"]'), function(b){ b.classList.remove('is-on'); });
						btn.classList.add('is-on');
						try { chosen[q] = JSON.parse(btn.getAttribute('data-params') || '{}'); } catch (e) { chosen[q] = {}; }
					});
				});
				var go = quiz.querySelector('.nqz-go');
				if (!go) { return; }
				go.addEventListener('click', function(){
					var params = {};
					Object.keys(chosen).forEach(function(q){
						var p = chosen[q] || {};
						Object.keys(p).forEach(function(k){ if (p[k] !== '' && p[k] != null) { params[k] = p[k]; } });
					});
					Object.keys(params).forEach(function(k){
						if (k.indexOf('filter_') === 0 && String(params[k]).indexOf(',') > -1) {
							params['query_type_' + k.slice(7)] = 'or';
						}
					});
					var qs = Object.keys(params).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
					var shop = quiz.getAttribute('data-shop') || '/shop/';
					window.location.href = qs ? (shop + (shop.indexOf('?') > -1 ? '&' : '?') + qs) : shop;
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
