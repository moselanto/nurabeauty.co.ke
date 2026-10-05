<?php
/**
 * NURA Journal - editorial content structure + SEO.
 *
 *  - BlogPosting (Article) JSON-LD on single posts so search engines can surface
 *    Journal articles as rich results. Skipped when a dedicated SEO plugin
 *    (Yoast, Rank Math, SEOPress) is active, to avoid duplicate schema, and
 *    filterable via nurax_journal_schema_enabled.
 *  - An editorial byline (author, date, reading time, section) prepended to
 *    single-post content for cleaner article structure.
 *  - A reusable [nura_journal count="3" category="..."] shortcode that renders a
 *    grid of recent Journal posts for any page.
 *
 * The theme's own seo-schema.php already covers Organization, WebSite, Product,
 * Breadcrumb and LocalBusiness; this fills the Article gap without touching it.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Journal {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'article_schema' ), 8 );
		add_filter( 'the_content', array( $this, 'article_meta' ), 9 );
		add_filter( 'the_content', array( $this, 'related_reading' ), 20 );
		add_shortcode( 'nura_journal', array( $this, 'shortcode' ) );
	}

	/** True when a dedicated SEO plugin is already emitting schema. */
	private function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' );
	}

	/** Estimated reading time in minutes (~200 wpm). */
	private function reading_minutes( $content ) {
		$words = str_word_count( wp_strip_all_tags( (string) $content ) );
		return max( 1, (int) ceil( $words / 200 ) );
	}

	/** BlogPosting JSON-LD on single posts. */
	public function article_schema() {
		if ( ! is_singular( 'post' ) || $this->seo_plugin_active() ) {
			return;
		}
		if ( ! apply_filters( 'nurax_journal_schema_enabled', true ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		$img  = has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'full' ) : '';
		$cats = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		$logo = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );

		$publisher = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		);
		if ( $logo ) {
			$publisher['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
		}

		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => get_permalink( $post ) ),
			'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
			'description'      => $desc,
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ),
			'publisher'        => $publisher,
			'wordCount'        => str_word_count( wp_strip_all_tags( $post->post_content ) ),
		);
		if ( $img ) {
			$data['image'] = $img;
		}
		if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
			$data['articleSection'] = $cats[0];
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
	}

	/** Prepend an editorial byline to single-post content. */
	public function article_meta( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post ) {
			return $content;
		}
		$mins = $this->reading_minutes( $content );
		$cats = get_the_category_list( ', ', '', $post->ID );

		$meta  = '<div class="nura-article-meta">';
		$meta .= '<span class="nura-article-meta__author">' . esc_html( get_the_author_meta( 'display_name', $post->post_author ) ) . '</span>';
		$meta .= '<span class="nura-article-meta__date">' . esc_html( get_the_date( '', $post ) ) . '</span>';
		$meta .= '<span class="nura-article-meta__read">' . esc_html( sprintf( /* translators: %d: minutes */ _n( '%d min read', '%d min read', $mins, 'nura-experience' ), $mins ) ) . '</span>';
		if ( $cats ) {
			$meta .= '<span class="nura-article-meta__cats">' . wp_kses_post( $cats ) . '</span>';
		}
		$meta .= '</div>';

		return $meta . $content;
	}

	/**
	 * Cluster-aware "Keep reading" block appended to single posts (#18/#23).
	 *
	 * Surfaces up to three sibling articles from the same category (the topical
	 * cluster), topped up with recent posts when the cluster is small, plus a
	 * link up to the cluster's pillar. The pillar URL/label default to the
	 * primary category archive and are filterable (nurax_cluster_pillar_url /
	 * nurax_cluster_pillar_label) so a dedicated pillar page can be targeted.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function related_reading( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post ) {
			return $content;
		}
		$cats    = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
		$primary = ( ! empty( $cats ) && ! is_wp_error( $cats ) ) ? (int) $cats[0] : 0;

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $post->ID ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		);
		if ( $primary ) {
			$args['category__in'] = array( $primary );
		}
		$q     = new WP_Query( $args );
		$posts = $q->posts;

		// Top up with recent posts if the cluster has fewer than three siblings.
		if ( count( $posts ) < 3 ) {
			$have = wp_list_pluck( $posts, 'ID' );
			$fill = new WP_Query( array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3 - count( $posts ),
				'post__not_in'        => array_merge( array( $post->ID ), $have ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			) );
			$posts = array_merge( $posts, $fill->posts );
		}
		if ( empty( $posts ) ) {
			return $content;
		}

		// Pillar link (default: primary category archive; filterable to a page).
		$pillar_url   = '';
		$pillar_label = '';
		if ( $primary ) {
			$link = get_category_link( $primary );
			$term = get_term( $primary, 'category' );
			if ( ! is_wp_error( $link ) && $term && ! is_wp_error( $term ) ) {
				$pillar_url   = $link;
				$pillar_label = $term->name;
			}
		}
		$pillar_url   = apply_filters( 'nurax_cluster_pillar_url', $pillar_url, $primary, $post );
		$pillar_label = apply_filters( 'nurax_cluster_pillar_label', $pillar_label, $primary, $post );

		ob_start();
		echo '<section class="nura-related">';
		echo '<div class="nura-related__head"><h2>' . esc_html__( 'Keep reading', 'nura-experience' ) . '</h2>';
		if ( $pillar_url && $pillar_label ) {
			echo '<a class="nura-related__pillar" href="' . esc_url( $pillar_url ) . '">'
				. esc_html( sprintf( /* translators: %s: cluster/category name */ __( 'More on %s', 'nura-experience' ), $pillar_label ) )
				. ' &#8594;</a>';
		}
		echo '</div>';
		echo '<div class="nura-journal-grid">';
		foreach ( $posts as $p ) {
			$permalink = get_permalink( $p );
			$mins      = $this->reading_minutes( $p->post_content );
			echo '<article class="nura-jcard">';
			echo '<a class="nura-jcard__media" href="' . esc_url( $permalink ) . '">';
			if ( has_post_thumbnail( $p ) ) {
				echo get_the_post_thumbnail( $p->ID, 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</a>';
			echo '<div class="nura-jcard__body">';
			$pcats = get_the_category( $p->ID );
			if ( ! empty( $pcats ) ) {
				echo '<span class="nura-jcard__cat">' . esc_html( $pcats[0]->name ) . '</span>';
			}
			echo '<h3 class="nura-jcard__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>';
			echo '<span class="nura-jcard__meta">' . esc_html( get_the_date( '', $p ) ) . ' &middot; ' . esc_html( sprintf( /* translators: %d: minutes */ _n( '%d min read', '%d min read', $mins, 'nura-experience' ), $mins ) ) . '</span>';
			echo '<a class="nura-jcard__more" href="' . esc_url( $permalink ) . '">' . esc_html__( 'Read more', 'nura-experience' ) . '</a>';
			echo '</div></article>';
		}
		echo '</div></section>';
		return $content . ob_get_clean();
	}

	/** [nura_journal count="3" category="slug"] recent-posts grid. */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'count' => 3, 'category' => '' ), $atts, 'nura_journal' );
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $atts['count'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( $atts['category'] ) {
			$args['category_name'] = sanitize_title( $atts['category'] );
		}
		$q = new WP_Query( $args );
		if ( ! $q->have_posts() ) {
			return '';
		}
		ob_start();
		echo '<div class="nura-journal-grid">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$mins = $this->reading_minutes( get_the_content() );
			echo '<article class="nura-jcard">';
			echo '<a class="nura-jcard__media" href="' . esc_url( get_permalink() ) . '">';
			if ( has_post_thumbnail() ) {
				echo get_the_post_thumbnail( get_the_ID(), 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) );
			}
			echo '</a>';
			echo '<div class="nura-jcard__body">';
			$cat = get_the_category();
			if ( ! empty( $cat ) ) {
				echo '<span class="nura-jcard__cat">' . esc_html( $cat[0]->name ) . '</span>';
			}
			echo '<h3 class="nura-jcard__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
			echo '<p class="nura-jcard__excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), 20 ) ) . '</p>';
			echo '<span class="nura-jcard__meta">' . esc_html( get_the_date() ) . ' &middot; ' . esc_html( sprintf( /* translators: %d: minutes */ _n( '%d min read', '%d min read', $mins, 'nura-experience' ), $mins ) ) . '</span>';
			echo '<a class="nura-jcard__more" href="' . esc_url( get_permalink() ) . '">' . esc_html__( 'Read more', 'nura-experience' ) . '</a>';
			echo '</div></article>';
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}
}
