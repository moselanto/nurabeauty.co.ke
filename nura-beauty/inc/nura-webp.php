<?php
/**
 * NURA WebP product photos (theme 1.101.0).
 *
 * Product page photos were JPEGs (765 x 1024 main photo on the Minky closure wig page, LCP 4.6 s
 * on mobile). This builds WebP copies of every product gallery photo at 480, 640, 828 and
 * 1024 px wide (never wider than the upload) in uploads/nura-webp/<attachment>-<width>.webp,
 * and on product pages serves them through srcset with a sizes value that matches the
 * displayed gallery width (full width on phones, about 640 px on desktop), so a phone downloads
 * a ~640-828 px WebP instead of a 765 px JPEG.
 *
 * - Built in the background (WP-Cron, a few photos per run) the first time a product page is
 *   viewed or a product is saved; until then the JPEGs are served as before. The product's
 *   LiteSpeed page cache is purged when its photos are ready.
 * - Tools > NURA images: status and "Build WebP for every product now".
 * - The zoom / lightbox image (data-large_image) stays the JPEG; colour swaps work as before.
 * - Needs WebP support in the server's image library (GD or Imagick); if missing, nothing changes.
 * Off: add_filter( 'nura_webp', '__return_false' ).
 *
 * @package NURA_Beauty
 */

defined( 'ABSPATH' ) || exit;

function nura_webp_on() {
	return (bool) apply_filters( 'nura_webp', true ) && ! get_option( 'nura_webp_unsupported' );
}

function nura_webp_widths() {
	return (array) apply_filters( 'nura_webp_widths', array( 360, 480, 640, 828, 1024 ) ); // 1.147.0: 360 px for the 2-column phone grid.
}

/** 1.130.0: file-name suffix; bump to rebuild every WebP (s2 = sharpened, quality 86). */
function nura_webp_tag() {
	return (string) apply_filters( 'nura_webp_tag', 's2' );
}

function nura_webp_dir() {
	$u = wp_upload_dir( null, false );
	return array( trailingslashit( $u['basedir'] ) . 'nura-webp/', trailingslashit( $u['baseurl'] ) . 'nura-webp/' );
}

/** Widths this attachment should have (never wider than the original). */
function nura_webp_targets( $id ) {
	$meta = wp_get_attachment_metadata( $id );
	$max  = is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
	if ( $max <= 0 ) {
		return array();
	}
	$out = array();
	foreach ( nura_webp_widths() as $w ) {
		if ( $w < $max ) {
			$out[] = (int) $w;
		}
	}
	$out[] = min( $max, 1024 );
	return array_values( array_unique( $out ) );
}

/** [ width => url ] for the WebP files that exist; empty unless all targets are built. */
function nura_webp_variants( $id ) {
	static $cache = array();
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}
	list( $dir, $url ) = nura_webp_dir();
	$out = array();
	foreach ( nura_webp_targets( $id ) as $w ) {
		$f = $id . '-' . $w . '-' . nura_webp_tag() . '.webp';
		if ( ! file_exists( $dir . $f ) ) {
			return $cache[ $id ] = array();
		}
		$out[ $w ] = $url . $f;
	}
	return $cache[ $id ] = $out;
}

function nura_webp_srcset( $id ) {
	$v = nura_webp_variants( $id );
	if ( ! $v ) {
		return '';
	}
	$p = array();
	foreach ( $v as $w => $u ) {
		$p[] = esc_url( $u ) . ' ' . $w . 'w';
	}
	return implode( ', ', $p );
}

function nura_webp_sizes() {
	return (string) apply_filters( 'nura_webp_sizes', '(max-width: 900px) 100vw, 640px' );
}

/** The variant used as src (fallback when srcset is ignored). */
function nura_webp_src( $id ) {
	$v = nura_webp_variants( $id );
	if ( ! $v ) {
		return '';
	}
	foreach ( $v as $w => $u ) {
		if ( $w >= 640 ) {
			return $u;
		}
	}
	return end( $v );
}

/* ------------------------------------------------------------ queue + build */

function nura_webp_queue( $ids, $product_id = 0 ) {
	if ( ! nura_webp_on() ) {
		return;
	}
	$q = (array) get_option( 'nura_webp_queue', array() );
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( $id && ! nura_webp_variants( $id ) ) {
			$q[ $id ] = (int) $product_id;
		}
	}
	update_option( 'nura_webp_queue', $q, false );
	if ( $q && ! wp_next_scheduled( 'nura_webp_build' ) ) {
		wp_schedule_single_event( time() + 30, 'nura_webp_build' );
	}
}

function nura_webp_product_ids( $product ) {
	if ( ! $product ) {
		return array();
	}
	return array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', (array) $product->get_gallery_image_ids() ) ) );
}

function nura_webp_build_one( $id ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		return true; // nothing to do; drop from queue
	}
	list( $dir ) = nura_webp_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return true;
	}
	foreach ( nura_webp_targets( $id ) as $w ) {
		$dest = $dir . $id . '-' . $w . '-' . nura_webp_tag() . '.webp';
		if ( file_exists( $dest ) ) {
			continue;
		}
		if ( nura_webp_build_sharp( $file, $dest, $w ) ) {
			continue; // 1.130.0: resized with Lanczos + unsharp mask.
		}
		$ed = wp_get_image_editor( $file );
		if ( is_wp_error( $ed ) ) {
			return true;
		}
		$size = $ed->get_size();
		if ( ! empty( $size['width'] ) && (int) $size['width'] > $w ) {
			$ed->resize( $w, null, false );
		}
		$ed->set_quality( (int) apply_filters( 'nura_webp_quality', 86 ) );
		$saved = $ed->save( $dest, 'image/webp' );
		if ( is_wp_error( $saved ) || ! file_exists( $dest ) ) {
			return true;
		}
	}
	return true;
}


/**
 * 1.130.0: product photos looked soft (owner, 11 Nov). Most wig originals are only
 * 768-896 px wide, so resize with a high-quality filter and add a light unsharp
 * mask, then save WebP at quality 86. Imagick first, GD second; false = let the
 * normal WordPress editor do it. Strength: filter nura_webp_sharpen (0 = off).
 */
function nura_webp_build_sharp( $file, $dest, $w ) {
	$amount = (float) apply_filters( 'nura_webp_sharpen', 0.8 );
	$q      = (int) apply_filters( 'nura_webp_quality', 86 );
	if ( $amount <= 0 ) {
		return false;
	}
	if ( class_exists( 'Imagick' ) ) {
		try {
			$im = new Imagick( $file );
			if ( method_exists( $im, 'autoOrient' ) ) {
				$im->autoOrient();
			}
			if ( $im->getImageWidth() > $w ) {
				$im->resizeImage( $w, 0, Imagick::FILTER_LANCZOS, 1 );
			}
			$im->unsharpMaskImage( 0, 0.9, $amount, 0.02 );
			$im->setImageFormat( 'webp' );
			$im->setImageCompressionQuality( $q );
			$im->stripImage();
			$ok = $im->writeImage( $dest );
			$im->clear();
			if ( $ok && file_exists( $dest ) ) {
				return true;
			}
		} catch ( Exception $e ) {
			// Fall through to GD.
		}
	}
	if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagewebp' ) && function_exists( 'imageconvolution' ) ) {
		$src = @imagecreatefromstring( (string) file_get_contents( $file ) ); // phpcs:ignore
		if ( ! $src ) {
			return false;
		}
		$sw = imagesx( $src );
		$sh = imagesy( $src );
		if ( $sw > $w ) {
			$nh  = (int) round( $sh * $w / $sw );
			$dst = imagecreatetruecolor( $w, $nh );
			imagealphablending( $dst, false );
			imagesavealpha( $dst, true );
			imagecopyresampled( $dst, $src, 0, 0, 0, 0, $w, $nh, $sw, $sh );
			imagedestroy( $src );
			$src = $dst;
		}
		$k = 1 + 8 * $amount / 2; // mild 3x3 sharpen
		imageconvolution( $src, array( array( 0, -$amount / 2, 0 ), array( -$amount / 2, 1 + 2 * $amount, -$amount / 2 ), array( 0, -$amount / 2, 0 ) ), 1, 0 );
		$ok = imagewebp( $src, $dest, $q );
		imagedestroy( $src );
		return $ok && file_exists( $dest );
	}
	return false;
}

add_action( 'nura_webp_build', function () {
	if ( ! nura_webp_on() ) {
		return;
	}
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		update_option( 'nura_webp_unsupported', time(), false );
		return;
	}
	$q     = (array) get_option( 'nura_webp_queue', array() );
	$start = time();
	$done_products = array();
	foreach ( $q as $id => $pid ) {
		if ( time() - $start > 20 ) {
			break;
		}
		nura_webp_build_one( (int) $id );
		unset( $q[ $id ] );
		if ( $pid ) {
			$done_products[ $pid ] = true;
		}
	}
	update_option( 'nura_webp_queue', $q, false );
	update_option( 'nura_webp_last', array( 'time' => current_time( 'mysql' ), 'left' => count( $q ) ), false );
	foreach ( array_keys( $done_products ) as $pid ) {
		if ( ! in_array( $pid, array_values( $q ), true ) ) {
			do_action( 'litespeed_purge_post', $pid ); // LiteSpeed: re-cache the page with the WebP photos.
		}
	}
	if ( $q ) {
		wp_schedule_single_event( time() + 60, 'nura_webp_build' );
	}
} );

/* Queue on product view (missing only) and on product save. */
add_action( 'template_redirect', function () {
	if ( function_exists( 'is_product' ) && is_product() && nura_webp_on() ) {
		$p   = wc_get_product( get_queried_object_id() );
		$ids = nura_webp_product_ids( $p );
		$miss = array_filter( $ids, function ( $id ) {
			return ! nura_webp_variants( $id );
		} );
		if ( $miss ) {
			nura_webp_queue( $miss, $p->get_id() );
		}
	}
} );
add_action( 'woocommerce_update_product', function ( $pid ) {
	nura_webp_queue( nura_webp_product_ids( wc_get_product( $pid ) ), $pid );
} );

/* ------------------------------------------------------------ serve */

add_filter( 'woocommerce_single_product_image_thumbnail_html', function ( $html, $id ) {
	if ( ! nura_webp_on() || ! is_product() ) {
		return $html;
	}
	$set = nura_webp_srcset( $id );
	if ( '' === $set ) {
		return $html;
	}
	$src = nura_webp_src( $id );
	return preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $set, $src ) {
		$tag = $m[0];
		$tag = preg_replace( '/\ssrcset="[^"]*"/i', '', $tag );
		$tag = preg_replace( '/\ssizes="[^"]*"/i', '', $tag );
		$tag = preg_replace( '/\ssrc="[^"]*"/i', ' src="' . esc_url( $src ) . '"', $tag, 1 );
		return preg_replace( '/^<img\b/i', '<img srcset="' . esc_attr( $set ) . '" sizes="' . esc_attr( nura_webp_sizes() ) . '"', $tag, 1 );
	}, $html, 1 );
}, 30, 2 );


/* 1.130.0: shop / category / homepage cards use the sharpened WebP too (up to 828 px),
 * sized for the slot: 2 per row on phones, 3 on tablets, ~310-340 px on desktop. */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( ! nura_webp_on() || is_admin() || ! $attachment ) {
		return $attr;
	}
	$class = isset( $attr['class'] ) ? (string) $attr['class'] : '';
	if ( false === strpos( $class, 'attachment-nura_card' ) && false === strpos( $class, 'attachment-woocommerce_thumbnail' ) ) {
		return $attr;
	}
	$id = (int) $attachment->ID;
	$v  = nura_webp_variants( $id );
	if ( ! $v ) {
		$pid = (int) $attachment->post_parent;
		nura_webp_queue( array( $id ), $pid );
		return $attr;
	}
	$set = array();
	$src = '';
	foreach ( $v as $w => $u ) {
		if ( $w <= 828 ) {
			$set[] = esc_url( $u ) . ' ' . $w . 'w';
			if ( '' === $src || $w <= 640 ) {
				$src = $u;
			}
		}
	}
	if ( $set ) {
		$attr['srcset'] = implode( ', ', $set );
		$attr['src']    = $src;
		$attr['sizes']  = '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 400px';
	}
	return $attr;
}, 40, 2 );

/* ------------------------------------------------------------ admin */

add_action( 'admin_post_nura_webp_all', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'nura_webp_all' ) ) {
		wp_die( 'No permission' );
	}
	delete_option( 'nura_webp_unsupported' );
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids' ) );
	foreach ( $ids as $pid ) {
		nura_webp_queue( nura_webp_product_ids( wc_get_product( $pid ) ), $pid );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=nura-images&queued=1' ) );
	exit;
} );
add_action( 'admin_menu', function () {
	add_management_page( 'NURA images', 'NURA images', 'manage_options', 'nura-images', function () {
		$q    = (array) get_option( 'nura_webp_queue', array() );
		$last = get_option( 'nura_webp_last' );
		$sup  = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		list( $dir ) = nura_webp_dir();
		$n = is_dir( $dir ) ? count( (array) glob( $dir . '*.webp' ) ) : 0;
		echo '<div class="wrap"><h1>NURA images (WebP)</h1>';
		echo '<p>Product photos are served as WebP at the size shown on screen. Server WebP support: <strong>' . ( $sup ? 'yes' : 'NO - ask your host to enable WebP in GD or Imagick' ) . '</strong>.</p>';
		echo '<p>WebP files built: <strong>' . (int) $n . '</strong>. Photos waiting: <strong>' . count( $q ) . '</strong>. Last run: ' . esc_html( $last ? $last['time'] : 'not yet' ) . '. Runs every minute or so in the background until done.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nura_webp_all">';
		wp_nonce_field( 'nura_webp_all' );
		submit_button( 'Build WebP for every product now' );
		echo '</form></div>';
	} );
} );
