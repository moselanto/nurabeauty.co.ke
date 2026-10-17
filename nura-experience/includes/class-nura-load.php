<?php
/**
 * NURA Load (plugin 1.74.0): keep MySQL connections free for shoppers.
 *
 * The host allows 12 concurrent MySQL connections. Background work (WP-Cron,
 * WooCommerce Action Scheduler, admin Heartbeat polling) was competing with
 * real visitors during traffic bursts. This module:
 *
 * 1. Runs WooCommerce Action Scheduler one batch at a time (no parallel runners),
 *    in smaller batches with a shorter time limit.
 * 2. Stops the Heartbeat API on the shop front end and slows it to 60 s in wp-admin.
 * 3. When the server cron is configured (DISABLE_WP_CRON), stops visitors' page
 *    loads and admin screens from kicking off Action Scheduler runs; the server
 *    cron runs the queue instead.
 *
 * Every setting is filterable; switch the whole module off with
 * add_filter( 'nurax_load_guard', '__return_false' );
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Load {

	public function __construct() {
		if ( ! apply_filters( 'nurax_load_guard', true ) ) {
			return;
		}
		// 1. Action Scheduler: one runner, small batches.
		add_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'one_batch' ), 99 );
		add_filter( 'action_scheduler_queue_runner_batch_size', array( $this, 'batch_size' ), 99 );
		add_filter( 'action_scheduler_queue_runner_time_limit', array( $this, 'time_limit' ), 99 );

		// 3. With a real server cron, the queue runs from cron only.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', 99 );
		}

		// 2. Heartbeat.
		add_action( 'init', array( $this, 'heartbeat_front' ), 1 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_slow' ), 99 );
	}

	public function one_batch() {
		return (int) apply_filters( 'nurax_as_concurrent_batches', 1 );
	}

	public function batch_size() {
		return (int) apply_filters( 'nurax_as_batch_size', 20 );
	}

	public function time_limit() {
		return (int) apply_filters( 'nurax_as_time_limit', 20 );
	}

	public function heartbeat_front() {
		if ( ! is_admin() ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	public function heartbeat_slow( $settings ) {
		$settings['interval'] = max( 60, isset( $settings['interval'] ) ? (int) $settings['interval'] : 60 );
		return $settings;
	}
}
