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
	$key = 'nura_cardimg3_' . $post->ID . '_' . md5( $post->post_modified_gmt );
	$hit = get_transient( $key );
	if ( false !== $hit ) {
		return (int) $hit;
	}
	$id      = 0;
	// Render shortcodes/blocks: product pictures in posts come from shortcodes, not raw <img> tags.
	$content = (string) $post->post_content;
	$content = do_shortcode( function_exists( 'do_blocks' ) ? do_blocks( $content ) : $content );
	$GLOBALS['nura_cardimg_src'][ $post->ID ] = preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $rs0 ) ? $rs0[1] : '';
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
		$src = isset( $GLOBALS['nura_cardimg_src'][ $post->ID ] ) ? $GLOBALS['nura_cardimg_src'][ $post->ID ] : '';
		if ( $src ) {
			return '<img src="' . esc_url( $src ) . '" width="600" height="800" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
		}
		// 3. Standard NURA photo (alternates between two so a page of them does not look repeated).
		$files = array( 'look-bob.webp', 'look-curls.webp' );
		$file  = $files[ $post->ID % count( $files ) ];
		return '<img src="' . esc_url( get_template_directory_uri() . '/assets/images/' . $file ) . '" width="896" height="1200" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	}
	return '';
}

/**
 * 1.161.15 One-time job: set featured images on the 26 journal posts that had none.
 * Runs once, only for an administrator who opens wp-admin/?nura_set_journal_thumbs=1.
 * Never replaces a featured image that is already set. Afterwards it does nothing.
 */
add_action( 'admin_init', function () {
	if ( empty( $_GET['nura_set_journal_thumbs'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$map = array(
		2233 => 1284, 2232 => 1317, 2231 => 2284, 2221 => 1354, 2220 => 1343, 2219 => 2015, 2218 => 1763,
		1942 => 1295, 1941 => 997, 1940 => 1303, 1939 => 1307, 1938 => 2014, 1937 => 1326, 1848 => 1270,
		1847 => 1366, 1846 => 2287, 1845 => 1261, 1844 => 1297, 1843 => 1364, 1842 => 1329, 1841 => 2275,
		1840 => 2181, 1839 => 1281, 1838 => 1352, 1837 => 1361, 1752 => 1277,
	);
	$out = array();
	foreach ( $map as $pid => $aid ) {
		$p = get_post( $pid );
		if ( ! $p || 'post' !== $p->post_type ) {
			$out[] = $pid . ': post not found';
			continue;
		}
		if ( has_post_thumbnail( $pid ) ) {
			$out[] = $pid . ': already had ' . get_post_thumbnail_id( $pid ) . ' (kept)';
			continue;
		}
		if ( ! wp_attachment_is_image( $aid ) ) {
			$out[] = $pid . ': media ' . $aid . ' is not an image (skipped)';
			continue;
		}
		$ok    = set_post_thumbnail( $pid, $aid );
		$out[] = $pid . ': ' . ( $ok ? 'set ' . $aid : 'FAILED ' . $aid );
	}
	update_option( 'nura_journal_thumbs_done', gmdate( 'c' ), false );
	set_transient( 'nura_journal_thumbs_report', $out, HOUR_IN_SECONDS );
	wp_safe_redirect( admin_url( 'index.php?nura_journal_thumbs_report=1' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( empty( $_GET['nura_journal_thumbs_report'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$r = get_transient( 'nura_journal_thumbs_report' );
	if ( ! $r ) {
		return;
	}
	$set = count( array_filter( $r, function ( $l ) { return false !== strpos( $l, ': set ' ); } ) );
	echo '<div class="notice notice-success"><p><strong>NURA journal featured images: ' . (int) $set . ' of ' . count( $r ) . ' set.</strong></p><p>' . esc_html( implode( ' | ', $r ) ) . '</p></div>';
} );
