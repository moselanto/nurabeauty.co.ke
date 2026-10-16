<?php
/**
 * NURA automatic fixes (v1.59.0). Everything here applies on theme upload,
 * with no manual edits in WooCommerce:
 *
 * 1. One set of opening hours everywhere (Customizer > NURA Options > Brand).
 * 2. Customer reviews: automatic review-request email 5 days after an order is
 *    completed, plus a one-click "ask my last 20 customers" tool under
 *    WooCommerce > Review requests.
 * 3. Product text: "Synthetic" shows as "Heat-Resistant Fibre" (matching the
 *    shop cards), and colour lists in descriptions / specs / additional
 *    information show the product's real shade options.
 * 4. Duplicate listings never show twice in the same product grid, and the
 *    owner gets an admin notice naming the duplicates to merge.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * 1. Opening hours.
 * ------------------------------------------------------------------------- */

/** The single source for opening hours. */
function nura_hours_text() {
	$h = function_exists( 'nura_opt' ) ? trim( (string) nura_opt( 'nura_hours' ) ) : '';
	return '' !== $h ? $h : 'Mon–Sat 8:00–20:00';
}

/* ---------------------------------------------------------------------------
 * 2. Review requests.
 * ------------------------------------------------------------------------- */

/** Schedule a review request 5 days after an order is completed. */
add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
	$order_id = absint( $order_id );
	if ( ! $order_id || wp_next_scheduled( 'nura_review_request', array( $order_id ) ) ) {
		return;
	}
	wp_schedule_single_event( time() + 5 * DAY_IN_SECONDS, 'nura_review_request', array( $order_id ) );
} );

/**
 * Send the review-request email for one order (once only).
 *
 * @param int $order_id Order ID.
 * @return bool Sent.
 */
function nura_send_review_request( $order_id ) {
	$order = wc_get_order( absint( $order_id ) );
	if ( ! $order || $order->get_meta( '_nura_review_requested' ) ) {
		return false;
	}
	$email = sanitize_email( $order->get_billing_email() );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$links = array();
	foreach ( $order->get_items() as $item ) {
		$pid = $item->get_product_id();
		if ( $pid && 'publish' === get_post_status( $pid ) && ! isset( $links[ $pid ] ) ) {
			$links[ $pid ] = array( 'name' => $item->get_name(), 'url' => get_permalink( $pid ) . '#reviews' );
		}
	}
	if ( ! $links ) {
		return false;
	}
	$first = $order->get_billing_first_name();
	$brand = function_exists( 'nura_opt' ) ? nura_opt( 'nura_brand_name' ) : 'NURA';
	/* translators: %s: brand name. */
	$subject = sprintf( __( 'How is your new crown? A quick favour from %s', 'nura-beauty' ), $brand );
	$body    = '<p>' . esc_html( sprintf( /* translators: %s: first name. */ __( 'Hi %s,', 'nura-beauty' ), $first ? $first : __( 'there', 'nura-beauty' ) ) ) . '</p>';
	$body   .= '<p>' . esc_html__( 'Thank you for shopping with NURA. We hope you are loving your new look. Would you take one minute to leave a short review? It helps other women choose with confidence, and we read every one.', 'nura-beauty' ) . '</p><ul>';
	foreach ( $links as $l ) {
		$body .= '<li><a href="' . esc_url( $l['url'] ) . '">' . esc_html( sprintf( /* translators: %s: product name. */ __( 'Review %s', 'nura-beauty' ), $l['name'] ) ) . '</a></li>';
	}
	$body .= '</ul><p>' . esc_html__( 'A photo of you wearing it makes your review even more helpful. Thank you!', 'nura-beauty' ) . '</p>';
	$sent = false;
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$mailer = WC()->mailer();
		$sent   = $mailer->send( $email, $subject, $mailer->wrap_message( __( 'Share your NURA experience', 'nura-beauty' ), $body ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	} else {
		$sent = wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
	if ( $sent ) {
		$order->update_meta_data( '_nura_review_requested', time() );
		$order->save();
	}
	return (bool) $sent;
}
add_action( 'nura_review_request', 'nura_send_review_request' );

/** WooCommerce > Review requests: send to the last 20 completed orders. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', __( 'Review requests', 'nura-beauty' ), __( 'Review requests', 'nura-beauty' ), 'manage_woocommerce', 'nura-review-requests', 'nura_review_requests_screen' );
}, 60 );

function nura_review_requests_screen() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$done = isset( $_GET['nura_sent'] ) ? absint( wp_unslash( $_GET['nura_sent'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	echo '<div class="wrap"><h1>' . esc_html__( 'Review requests', 'nura-beauty' ) . '</h1>';
	echo '<p>' . esc_html__( 'Every completed order automatically gets a friendly review request 5 days later. Use the button below once to ask your most recent customers too. Nobody is ever asked twice.', 'nura-beauty' ) . '</p>';
	if ( null !== $done ) {
		/* translators: %d: emails sent. */
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d review requests sent.', 'nura-beauty' ), $done ) ) . '</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="nura_review_requests_send">';
	wp_nonce_field( 'nura_review_requests_send' );
	submit_button( __( 'Ask my last 20 customers for a review', 'nura-beauty' ) );
	echo '</form></div>';
}

add_action( 'admin_post_nura_review_requests_send', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'nura-beauty' ) );
	}
	check_admin_referer( 'nura_review_requests_send' );
	$sent = 0;
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( array( 'status' => array( 'wc-completed' ), 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' ) );
		foreach ( $orders as $oid ) {
			if ( nura_send_review_request( $oid ) ) {
				$sent++;
			}
		}
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'nura-review-requests', 'nura_sent' => $sent ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ---------------------------------------------------------------------------
 * 3. Product text: hair type wording + real colour lists.
 * ------------------------------------------------------------------------- */

/** Front-end only. */
function nura_autofix_front() {
	return ! is_admin() || wp_doing_ajax();
}

/** "Synthetic" -> "Heat-Resistant Fibre" for the pa_hair-type taxonomy. */
function nura_autofix_hair_name( $name ) {
	return preg_replace( '/\bsynthetic(\s+fib(re|er))?\b/i', 'Heat-Resistant Fibre', (string) $name );
}
function nura_autofix_term( $t ) {
	if ( $t instanceof WP_Term && 'pa_hair-type' === $t->taxonomy ) {
		$t->name = nura_autofix_hair_name( $t->name );
	}
	return $t;
}
add_filter( 'get_term', function ( $t ) {
	return nura_autofix_front() ? nura_autofix_term( $t ) : $t;
} );
/**
 * Apply the hair-type wording to a term list.
 *
 * @param mixed $terms      Terms (objects or strings).
 * @param array $taxonomies Taxonomies the list was queried from.
 * @return mixed
 */
function nura_autofix_term_list( $terms, $taxonomies ) {
	if ( ! nura_autofix_front() || ! is_array( $terms ) ) {
		return $terms;
	}
	$is_hair = in_array( 'pa_hair-type', (array) $taxonomies, true );
	foreach ( $terms as $i => $t ) {
		if ( $t instanceof WP_Term ) {
			$terms[ $i ] = nura_autofix_term( $t );
		} elseif ( $is_hair && is_string( $t ) ) {
			$terms[ $i ] = nura_autofix_hair_name( $t );
		}
	}
	return $terms;
}
add_filter( 'get_terms', function ( $terms, $taxonomies = array() ) {
	return nura_autofix_term_list( $terms, $taxonomies );
}, 20, 2 );
add_filter( 'get_the_terms', function ( $terms, $post_id = 0, $taxonomy = '' ) {
	return nura_autofix_term_list( $terms, array( $taxonomy ) );
}, 20, 3 );
add_filter( 'wp_get_object_terms', function ( $terms, $object_ids = array(), $taxonomies = array() ) {
	return nura_autofix_term_list( $terms, (array) $taxonomies );
}, 20, 3 );
add_filter( 'woocommerce_attribute', function ( $html, $attribute = null ) {
	if ( $attribute && is_object( $attribute ) && method_exists( $attribute, 'get_name' ) && false !== stripos( $attribute->get_name(), 'hair' ) ) {
		return nura_autofix_hair_name( $html );
	}
	return $html;
}, 20, 2 );

/**
 * The product's real shade options (variation colour names), or array().
 *
 * @param WC_Product $p Product.
 * @return string[]
 */
function nura_product_shades( $p ) {
	if ( ! $p instanceof WC_Product || ! $p->is_type( 'variable' ) ) {
		return array();
	}
	foreach ( $p->get_variation_attributes() as $key => $values ) {
		$k = strtolower( $key );
		if ( false === strpos( $k, 'colour' ) && false === strpos( $k, 'color' ) ) {
			continue;
		}
		$names = array();
		foreach ( (array) $values as $v ) {
			$name = $v;
			if ( taxonomy_exists( $key ) ) {
				$term = get_term_by( 'slug', $v, $key );
				if ( $term && ! is_wp_error( $term ) ) {
					$name = $term->name;
				}
			}
			$name = trim( (string) $name );
			if ( '' !== $name && ! in_array( $name, $names, true ) ) {
				$names[] = $name;
			}
		}
		return $names;
	}
	return array();
}

/** Current single product, if any. */
function nura_autofix_current_product() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return null;
	}
	$p = wc_get_product( get_queried_object_id() );
	return $p ? $p : null;
}

/** Rewrite colour counts and lists in product text to the real shades. */
function nura_autofix_colour_text( $html ) {
	$p = nura_autofix_current_product();
	if ( ! $p || ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	$shades = nura_product_shades( $p );
	if ( ! $shades ) {
		return $html;
	}
	$list  = implode( ', ', $shades );
	$count = count( $shades );
	/* translators: 1: number of shades, 2: list of shades. */
	$sentence = sprintf( _n( 'Available in %1$d colour (%2$s)', 'Available in %1$d colours (%2$s)', $count, 'nura-beauty' ), $count, esc_html( $list ) );
	// "Available in 12 colours (A, B (99J), C)" - with or without the bracketed list.
	$html = preg_replace( '/Available in \d+ colou?rs?(\s*\((?:[^()]|\([^()]*\))*\))?/i', $sentence, $html );
	// Specification table row: <td>Colours Available</td><td>...</td> (th or td, any attributes).
	$html = preg_replace( '#(<t[hd][^>]*>\s*Colou?rs?(?:\s+Available)?\s*</t[hd]>\s*<t[hd][^>]*>)(.*?)(</t[hd]>)#is', '$1' . esc_html( $list ) . '$3', $html );
	return $html;
}
add_filter( 'the_content', function ( $c ) {
	return ( nura_autofix_front() && in_the_loop() ) ? nura_autofix_colour_text( $c ) : $c;
}, 30 );
add_filter( 'woocommerce_short_description', 'nura_autofix_colour_text', 30 );

/** Additional information + key facts: colour row shows the real shades. */
add_filter( 'woocommerce_display_product_attributes', function ( $attrs, $product = null ) {
	if ( ! is_array( $attrs ) ) {
		return $attrs;
	}
	$p      = $product instanceof WC_Product ? $product : nura_autofix_current_product();
	$shades = $p ? nura_product_shades( $p ) : array();
	foreach ( $attrs as $key => $row ) {
		$label = isset( $row['label'] ) ? strtolower( wp_strip_all_tags( (string) $row['label'] ) ) : '';
		if ( $shades && ( false !== strpos( $label, 'colour' ) || false !== strpos( $label, 'color' ) ) ) {
			$attrs[ $key ]['value'] = '<p>' . esc_html( implode( ', ', $shades ) ) . '</p>';
		} elseif ( false !== strpos( $label, 'hair' ) && isset( $row['value'] ) ) {
			$attrs[ $key ]['value'] = nura_autofix_hair_name( $row['value'] );
		}
	}
	return $attrs;
}, 90, 2 );

/** Schema: colour = real shades. */
add_filter( 'rank_math/json_ld', function ( $data ) {
	$p = nura_autofix_current_product();
	if ( ! $p || ! is_array( $data ) ) {
		return $data;
	}
	$shades = nura_product_shades( $p );
	foreach ( $data as $key => $node ) {
		if ( is_array( $node ) && ! empty( $node['@type'] ) && array_intersect( (array) $node['@type'], array( 'Product', 'ProductGroup' ) ) ) {
			if ( $shades ) {
				$data[ $key ]['color'] = implode( ', ', $shades );
			}
			if ( ! empty( $node['material'] ) ) {
				$data[ $key ]['material'] = nura_autofix_hair_name( $node['material'] );
			}
		}
	}
	return $data;
}, 130 );

/* ---------------------------------------------------------------------------
 * 4. Duplicate listings.
 * ------------------------------------------------------------------------- */

/** Normalised product title for duplicate detection. */
function nura_autofix_title_key( $title ) {
	$t = strtolower( html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' ) );
	$t = str_replace( array( '×', ' x ' ), 'x', $t );
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', $t ) );
}

/** Never show the same product name twice in one front-end product grid. */
add_filter( 'the_posts', function ( $posts, $query ) {
	if ( is_admin() || ! is_array( $posts ) || count( $posts ) < 2 ) {
		return $posts;
	}
	$type = $query instanceof WP_Query ? $query->get( 'post_type' ) : '';
	if ( 'product' !== $type && ! ( is_array( $type ) && in_array( 'product', $type, true ) ) ) {
		return $posts;
	}
	$seen = array();
	$out  = array();
	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			$out[] = $post;
			continue;
		}
		$k = nura_autofix_title_key( $post->post_title );
		if ( isset( $seen[ $k ] ) ) {
			continue;
		}
		$seen[ $k ] = true;
		$out[]      = $post;
	}
	return $out;
}, 20, 2 );

/** Admin notice naming duplicate products so they can be merged. */
function nura_autofix_duplicates() {
	$cached = get_transient( 'nura_dup_products_v1' );
	if ( false !== $cached ) {
		return $cached;
	}
	$dups = array();
	$ids  = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1000, 'fields' => 'ids', 'no_found_rows' => true ) );
	$map  = array();
	foreach ( $ids as $id ) {
		$map[ nura_autofix_title_key( get_the_title( $id ) ) ][] = $id;
	}
	foreach ( $map as $group ) {
		if ( count( $group ) > 1 ) {
			$dups[] = $group;
		}
	}
	set_transient( 'nura_dup_products_v1', $dups, 12 * HOUR_IN_SECONDS );
	return $dups;
}
add_action( 'save_post_product', function () {
	delete_transient( 'nura_dup_products_v1' );
} );
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'edit-product', 'dashboard', 'woocommerce_page_wc-admin' ), true ) ) {
		return;
	}
	$dups = nura_autofix_duplicates();
	if ( ! $dups ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'NURA: duplicate products found.', 'nura-beauty' ) . '</strong> ' . esc_html__( 'The shop shows only one of each, but merging them keeps reviews, stock and Google rankings in one place:', 'nura-beauty' ) . '</p><ul style="list-style:disc;padding-left:1.5em">';
	foreach ( array_slice( $dups, 0, 10 ) as $group ) {
		$links = array();
		foreach ( $group as $id ) {
			$links[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . ' (#' . absint( $id ) . ')</a>';
		}
		echo '<li>' . implode( ' &middot; ', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	echo '</ul></div>';
} );
