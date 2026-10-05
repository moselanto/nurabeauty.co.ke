<?php
/**
 * Commerce info helpers - a single source of truth for payment + delivery copy.
 *
 * Derives the payment methods and delivery promises shown across the storefront
 * (homepage trust bar, footer badges, the product reassurance strip) from the
 * merchant's REAL WooCommerce configuration - the enabled payment gateways and
 * the configured shipping zones - so the marketing copy can never drift from
 * what a customer actually gets at checkout. Everything degrades gracefully to
 * the curated Kenya defaults when WooCommerce is inactive or nothing has been
 * configured yet.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'nura_payment_methods' ) ) {
	/**
	 * Enabled WooCommerce payment methods as short, brand-friendly labels.
	 *
	 * Reads the gateways the merchant has switched on (not cart-conditional
	 * availability), then maps well-known IDs to concise labels (M-Pesa, Card,
	 * Pay on Delivery, ...), falling back to each gateway's own checkout title.
	 *
	 * @return string[] De-duplicated labels, in the order set in WooCommerce.
	 */
	function nura_payment_methods() {
		$labels = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $gw ) {
				if ( ! isset( $gw->enabled ) || 'yes' !== $gw->enabled ) {
					continue;
				}
				$id    = strtolower( (string) $gw->id );
				$title = trim( wp_strip_all_tags( (string) $gw->get_title() ) );
				if ( false !== strpos( $id, 'mpesa' ) || false !== stripos( $title, 'mpesa' ) || false !== stripos( $title, 'm-pesa' ) ) {
					$label = __( 'M-Pesa', 'nura-beauty' );
				} elseif ( 'cod' === $id || false !== stripos( $title, 'on delivery' ) || false !== stripos( $title, 'cash' ) ) {
					$label = __( 'Cash on Delivery', 'nura-beauty' );
				} elseif ( 'bacs' === $id || false !== stripos( $title, 'bank' ) ) {
					$label = __( 'Bank Transfer', 'nura-beauty' );
				} elseif ( false !== strpos( $id, 'paypal' ) || false !== strpos( $id, 'ppcp' ) ) {
					$label = 'PayPal';
				} elseif ( false !== strpos( $id, 'stripe' ) || false !== strpos( $id, 'card' ) || false !== stripos( $title, 'card' ) || false !== stripos( $title, 'visa' ) || false !== stripos( $title, 'mastercard' ) ) {
					$label = __( 'Card', 'nura-beauty' );
				} else {
					$label = $title ? $title : ucfirst( $id );
				}
				$labels[ $label ] = $label;
			}
		}
		if ( empty( $labels ) ) {
			// Curated NURA payment options shown until the real gateways are switched
			// on in WooCommerce (the M-Pesa Till is still being processed).
			return array(
				__( 'M-Pesa', 'nura-beauty' ),
				__( 'Cash on Delivery', 'nura-beauty' ),
				__( 'Bank Transfer', 'nura-beauty' ),
			);
		}
		return array_values( $labels );
	}
}

if ( ! function_exists( 'nura_payment_summary' ) ) {
	/**
	 * A human summary of the enabled payment methods.
	 *
	 * e.g. "M-Pesa, Card & Pay on Delivery".
	 *
	 * @param string $fallback Copy to use when nothing can be derived.
	 * @return string
	 */
	function nura_payment_summary( $fallback = '' ) {
		$methods = nura_payment_methods();
		if ( empty( $methods ) ) {
			return $fallback;
		}
		if ( 1 === count( $methods ) ) {
			return $methods[0];
		}
		$last = array_pop( $methods );
		/* translators: 1: comma-separated payment methods, 2: final payment method. */
		return sprintf( __( '%1$s & %2$s', 'nura-beauty' ), implode( ', ', $methods ), $last );
	}
}

if ( ! function_exists( 'nura_delivery_lines' ) ) {
	/**
	 * Delivery promise lines derived from configured WooCommerce shipping zones.
	 *
	 * Each zone the merchant has set up (with at least one enabled method) yields
	 * a coverage line; a free-shipping method anywhere surfaces a "Free delivery"
	 * line. Falls back to the curated Kenya promises when no zones are configured.
	 *
	 * @param int $max Maximum lines to return.
	 * @return string[]
	 */
	function nura_delivery_lines( $max = 2 ) {
		$max   = max( 1, (int) $max );
		$lines = array();
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$names    = array();
			$has_free = false;
			foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
				$methods = isset( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array();
				$enabled = false;
				foreach ( $methods as $m ) {
					$on = method_exists( $m, 'is_enabled' ) ? $m->is_enabled() : ( isset( $m->enabled ) && 'yes' === $m->enabled );
					if ( ! $on ) {
						continue;
					}
					$enabled = true;
					if ( isset( $m->id ) && 'free_shipping' === $m->id ) {
						$has_free = true;
					}
				}
				if ( $enabled && ! empty( $zone['zone_name'] ) ) {
					$names[] = $zone['zone_name'];
				}
			}
			if ( $has_free ) {
				$lines[] = __( 'Free delivery available', 'nura-beauty' );
			}
			$slots = max( 0, $max - count( $lines ) );
			foreach ( array_slice( array_values( array_unique( $names ) ), 0, $slots ) as $nm ) {
				/* translators: %s: shipping zone name, e.g. Nairobi. */
				$lines[] = sprintf( __( '%s delivery', 'nura-beauty' ), $nm );
			}
		}
		if ( empty( $lines ) ) {
			$lines = array(
				__( 'Same-Day Nairobi Delivery', 'nura-beauty' ),
				__( 'Kenya-Wide Delivery', 'nura-beauty' ),
			);
		}
		return array_slice( $lines, 0, $max );
	}
}
