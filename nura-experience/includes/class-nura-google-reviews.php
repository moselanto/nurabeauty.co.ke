<?php
/**
 * Google Business Profile reviews (Places API).
 *
 * Fetches the store's Google rating and up to 5 reviews via the Google Places
 * Details API, caches them for 12 hours, and exposes a [nura_google_reviews]
 * shortcode plus a get_aggregate() helper. Reviews are DISPLAYED ONLY: per
 * Google's structured-data policy we do not emit AggregateRating schema for
 * reviews collected by a third party (Google).
 *
 * Configuration (constants are preferred so the API key never touches the
 * database or the repo). Add to wp-config.php:
 *   define( 'NURA_GOOGLE_PLACES_API_KEY', 'your-key' );
 *   define( 'NURA_GOOGLE_PLACE_ID', 'ChIJ...' );
 * Filters nurax_google_places_api_key / nurax_google_place_id and the options
 * nura_google_places_api_key / nura_google_place_id are also honoured.
 *
 * @package NURA_Experience
 */

if ( defined( 'ABSPATH' ) === false ) {
	exit;
}

class NURAX_Google_Reviews {

	const CACHE_KEY = 'nurax_google_reviews_v1';
	const CACHE_TTL = 43200;

	public function __construct() {
		add_shortcode( 'nura_google_reviews', array( $this, 'shortcode' ) );
	}

	/** Resolve a value from a constant, then a filter, then an option. */
	private function conf( $const, $filter, $option ) {
		if ( defined( $const ) ) {
			$v = constant( $const );
			if ( empty( $v ) === false ) {
				return trim( (string) $v );
			}
		}
		$v = apply_filters( $filter, '' );
		if ( empty( $v ) === false ) {
			return trim( (string) $v );
		}
		return trim( (string) get_option( $option, '' ) );
	}

	private function api_key() {
		return $this->conf( 'NURA_GOOGLE_PLACES_API_KEY', 'nurax_google_places_api_key', 'nura_google_places_api_key' );
	}

	private function place_id() {
		return $this->conf( 'NURA_GOOGLE_PLACE_ID', 'nurax_google_place_id', 'nura_google_place_id' );
	}

	public function is_configured() {
		return ( empty( $this->api_key() ) === false && empty( $this->place_id() ) === false );
	}

	/** Cached review data, fetching from Google when the cache is cold. */
	public function get_data() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$data = $this->fetch();
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/** Live fetch from the Places Details API. Always returns an array. */
	private function fetch() {
		$empty = array( 'ok' => false, 'rating' => 0, 'total' => 0, 'url' => '', 'reviews' => array() );
		if ( $this->is_configured() === false ) {
			return $empty;
		}
		$endpoint = add_query_arg(
			array(
				'place_id'     => rawurlencode( $this->place_id() ),
				'fields'       => 'name,rating,user_ratings_total,reviews,url',
				'reviews_sort' => 'newest',
				'language'     => 'en',
				'key'          => rawurlencode( $this->api_key() ),
			),
			'https://maps.googleapis.com/maps/api/place/details/json'
		);
		$resp = wp_remote_get( $endpoint, array( 'timeout' => 12 ) );
		if ( is_wp_error( $resp ) ) {
			return $empty;
		}
		if ( 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( is_array( $body ) && isset( $body['status'] ) && 'OK' === $body['status'] && isset( $body['result'] ) ) {
				$r       = $body['result'];
				$reviews = array();
				if ( isset( $r['reviews'] ) && is_array( $r['reviews'] ) ) {
					foreach ( $r['reviews'] as $rev ) {
						$reviews[] = array(
							'author' => isset( $rev['author_name'] ) ? $rev['author_name'] : '',
							'rating' => isset( $rev['rating'] ) ? (int) $rev['rating'] : 0,
							'text'   => isset( $rev['text'] ) ? $rev['text'] : '',
							'when'   => isset( $rev['relative_time_description'] ) ? $rev['relative_time_description'] : '',
							'photo'  => isset( $rev['profile_photo_url'] ) ? $rev['profile_photo_url'] : '',
						);
					}
				}
				return array(
					'ok'      => true,
					'rating'  => isset( $r['rating'] ) ? (float) $r['rating'] : 0,
					'total'   => isset( $r['user_ratings_total'] ) ? (int) $r['user_ratings_total'] : 0,
					'url'     => isset( $r['url'] ) ? $r['url'] : '',
					'reviews' => $reviews,
				);
			}
		}
		return $empty;
	}

	/** Aggregate rating helper: array( rating, total, url ). */
	public function get_aggregate() {
		$d = $this->get_data();
		if ( is_array( $d ) && empty( $d['ok'] ) === false ) {
			return array( 'rating' => $d['rating'], 'total' => $d['total'], 'url' => $d['url'] );
		}
		return array( 'rating' => 0, 'total' => 0, 'url' => '' );
	}

	private function stars( $rating ) {
		$full = (int) round( (float) $rating );
		$out  = '<span class="nura-gr-stars" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$cls  = ( $i <= $full ) ? 'on' : 'off';
			$out .= '<span class="' . $cls . '">&#9733;</span>';
		}
		return $out . '</span>';
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'      => 'Loved by women across Kenya',
				'count'      => 6,
				'min_rating' => 0,
			),
			$atts,
			'nura_google_reviews'
		);
		$d = $this->get_data();
		if ( is_array( $d ) === false || empty( $d['ok'] ) ) {
			return '';
		}
		$max   = (int) $atts['count'];
		$min   = (int) $atts['min_rating'];
		$cards = '';
		$shown = 0;
		foreach ( $d['reviews'] as $rev ) {
			$text = trim( (string) $rev['text'] );
			if ( (int) $rev['rating'] < $min || '' === $text ) {
				continue;
			}
			$avatar = '';
			if ( empty( $rev['photo'] ) === false ) {
				$avatar = '<img class="nura-gr-ava" src="' . esc_url( $rev['photo'] ) . '" alt="" loading="lazy" width="40" height="40" />';
			}
			$when = '';
			if ( empty( $rev['when'] ) === false ) {
				$when = '<span class="nura-gr-when">' . esc_html( $rev['when'] ) . '</span>';
			}
			$cards .= '<article class="nura-gr-card"><header class="nura-gr-card-head">' . $avatar
				. '<span class="nura-gr-name">' . esc_html( $rev['author'] ) . '</span></header>'
				. $this->stars( $rev['rating'] )
				. '<p class="nura-gr-text">' . esc_html( wp_trim_words( $text, 45 ) ) . '</p>'
				. $when . '</article>';
			$shown++;
			if ( $shown >= $max ) {
				break;
			}
		}
		if ( 0 === $shown ) {
			return '';
		}
		$agg = '';
		if ( $d['rating'] > 0 ) {
			$agg = '<p class="nura-gr-agg">' . $this->stars( $d['rating'] )
				. ' <strong>' . esc_html( number_format_i18n( $d['rating'], 1 ) ) . '</strong> '
				. '<span>' . esc_html( sprintf( 'based on %s Google reviews', number_format_i18n( $d['total'] ) ) ) . '</span></p>';
		}
		$more = '';
		if ( empty( $d['url'] ) === false ) {
			$more = '<p class="nura-gr-more"><a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener nofollow">Read all reviews on Google</a></p>';
		}
		return '<section class="nura-gr" aria-label="Google reviews">'
			. '<div class="nura-gr-head"><h2 class="nura-gr-title">' . esc_html( $atts['title'] ) . '</h2>' . $agg . '</div>'
			. '<div class="nura-gr-grid">' . $cards . '</div>' . $more . '</section>';
	}
}
