<?php
/**
 * 1.161.12 Journal card photos: posts without a featured image still get a photo.
 * Order: featured image -> first photo inside the post -> a product photo from the
 * first wig category the post links to -> a standard NURA photo.
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

/** Attachment ID (or 0) for a post's first in-content photo, a linked category photo, cached per post revision. */
function nura_card_fallback_id( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$key = 'nura_cardimg2_' . $post->ID . '_' . md5( $post->post_modified_gmt );
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return (int) $hit;
	}
	$id      = 0;
	$content = (string) $post->post_content;
	// 1. First photo inside the post.
	if ( preg_match( '/<img[^>]+>/i', $content, $m ) ) {
		if ( preg_match( '/wp-image-(\d+)/', $m[0], $c ) ) {
			$id = (int) $c[1];
		} elseif ( preg_match( '/src=["\']([^"\']+)["\']/i', $m[0], $s ) ) {
			$url = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $s[1] );
			$id  = (int) attachment_url_to_postid( $url );
			if ( ! $id ) {
				$id = (int) attachment_url_to_postid( $s[1] );
			}
		}
	}
	// 1b. The in-post photo is a product picture: use that product's main photo (full size).
	if ( ! $id && preg_match_all( '#/product/([a-z0-9-]+)/?["\'\?\#]#i', $content, $pm ) ) {
		foreach ( array_unique( $pm[1] ) as $pslug ) {
			$prod = get_page_by_path( $pslug, OBJECT, 'product' );
			if ( $prod && 'publish' === $prod->post_status ) {
				$id = (int) get_post_thumbnail_id( $prod );
				if ( $id ) {
					break;
				}
			}
		}
	}
	// 2. A product photo from the first linked wig category.
	if ( ! $id && taxonomy_exists( 'product_cat' ) && preg_match_all( '#/product-category/(?:[a-z0-9-]+/)*?([a-z0-9-]+)/?["\'\?\#]#i', $content, $cm ) ) {
		foreach ( array_unique( $cm[1] ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $term ) {
				continue;
			}
			$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			if ( $thumb ) {
				$id = $thumb;
				break;
			}
			$q = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			) );
			if ( $q ) {
				$id = (int) get_post_thumbnail_id( $q[0] );
				if ( $id ) {
					break;
				}
			}
		}
	}
	if ( $id && ! wp_attachment_is_image( $id ) ) {
		$id = 0;
	}
	set_transient( $key, $id, 12 * HOUR_IN_SECONDS );
	return $id;
}

/** Card photo HTML for a journal post. */
function nura_card_media( $post = null, $size = 'nura-portrait' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$alt = the_title_attribute( array( 'echo' => false, 'post' => $post ) );
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail( $post, $size );
	}
	if ( 'post' === $post->post_type ) {
		$id = nura_card_fallback_id( $post );
		if ( $id ) {
			return wp_get_attachment_image( $id, $size, false, array( 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async' ) );
		}
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $post->post_content, $rs ) ) {
			return '<img src="' . esc_url( $rs[1] ) . '" width="600" height="800" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
		}
		// 3. Standard NURA photo (alternates between two so a page of them does not look repeated).
		$files = array( 'look-bob.webp', 'look-curls.webp' );
		$file  = $files[ $post->ID % count( $files ) ];
		return '<img src="' . esc_url( get_template_directory_uri() . '/assets/images/' . $file ) . '" width="896" height="1200" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	}
	return '';
}
