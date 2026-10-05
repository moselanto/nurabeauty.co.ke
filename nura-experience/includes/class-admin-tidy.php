<?php
/**
 * Admin list-table tidy-up.
 *
 * Rank Math SEO adds an "SEO Details" column to the WooCommerce Products list
 * (edit.php?post_type=product). Alongside the core, WooCommerce and Brands
 * columns the table runs out of horizontal room, so the SEO column collapses to
 * a few pixels wide and its text ("Keyword: Not Set / Schema: WooCommerce
 * Product") wraps one character per line - blowing every product row up to a
 * huge height. This module removes that column from the product list (the SEO
 * settings on each product are untouched) and adds a small defensive stylesheet
 * so the list stays tidy even if a column key changes.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Admin_Tidy {

	public function __construct() {
		// Priority 99 so we unset the column after Rank Math has registered it.
		add_filter( 'manage_edit-product_columns', array( $this, 'remove_seo_columns' ), 99 );
		add_filter( 'manage_product_posts_columns', array( $this, 'remove_seo_columns' ), 99 );
		add_action( 'admin_head-edit.php', array( $this, 'admin_css' ) );
	}

	/**
	 * Drop Rank Math / Yoast SEO columns from the product list table. Removing
	 * the column key removes both its header and every row cell, so the table
	 * reflows to a normal height. Nothing about the product's SEO data changes.
	 *
	 * @param array $columns Registered list-table columns.
	 * @return array
	 */
	public function remove_seo_columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		foreach ( array_keys( $columns ) as $key ) {
			if ( 0 === strpos( $key, 'rank_math' ) || 0 === strpos( $key, 'wpseo' ) ) {
				unset( $columns[ $key ] );
			}
		}
		return $columns;
	}

	/**
	 * Defensive stylesheet, product list screen only: hide any residual SEO
	 * column outright so it can never collapse to one character per line.
	 */
	public function admin_css() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		echo '<style id="nurax-admin-tidy">'
			. '.wp-list-table th.column-rank_math_seo_details,'
			. '.wp-list-table td.column-rank_math_seo_details,'
			. '.wp-list-table th.column-wpseo-score,'
			. '.wp-list-table td.column-wpseo-score,'
			. '.wp-list-table th.column-wpseo-score-readability,'
			. '.wp-list-table td.column-wpseo-score-readability{display:none !important}'
			. '</style>';
	}
}
