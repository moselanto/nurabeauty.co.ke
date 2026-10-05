<?php
/**
 * The NURA Circle - luxury client portal + membership.
 *
 * WooCommerce My Account experience for NURA clients:
 *  - Dashboard with live stat cards (Radiance Points, orders, active
 *    warranties, next care date) and a loyalty-tier ladder with progress.
 *  - Care schedule: milestone reminders (fit, first wash, revamp) computed from
 *    each order and aligned to the post-purchase care email journey.
 *  - Warranty certificates: per-unit certificates with validity + status and a
 *    printable certificate view.
 *  - Radiance Points loyalty + VIP membership, plus the [nura_circle_portal]
 *    marketing shortcode.
 *
 * Endpoints and meta keys are unchanged from prior versions, so existing
 * customer data and permalinks keep working (no rewrite flush required).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Circle {

	const POINTS_META    = '_nurax_points';
	const MEMBER_META    = '_nurax_member';
	const WARRANTY_META  = '_nurax_warranties';
	const PROCESSED_META = '_nurax_circle_processed';
	const RATE           = 100; // 1 Radiance Point per KES 100 spent.

	public function __construct() {
		add_shortcode( 'nura_circle_portal', array( $this, 'portal_shortcode' ) );

		// Award points + create warranty records when an order completes.
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_complete' ) );

		// My Account endpoints.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_action( 'woocommerce_account_nura-circle_endpoint', array( $this, 'render_dashboard' ) );
		add_action( 'woocommerce_account_care-schedule_endpoint', array( $this, 'render_care' ) );
		add_action( 'woocommerce_account_warranties_endpoint', array( $this, 'render_warranties' ) );
	}

	public function add_endpoints() {
		add_rewrite_endpoint( 'nura-circle', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'care-schedule', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'warranties', EP_ROOT | EP_PAGES );
	}

	public function menu_items( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				$new['nura-circle']   = __( 'The NURA Circle', 'nura-experience' );
				$new['care-schedule'] = __( 'Care Schedule', 'nura-experience' );
				$new['warranties']    = __( 'Warranty Certificates', 'nura-experience' );
			}
		}
		return $new;
	}

	/* ---------------------------------------------------------------------
	 * Loyalty tiers
	 * ------------------------------------------------------------------- */

	/** The Radiance tier ladder (by lifetime points). */
	private function tiers() {
		return array(
			array( 'key' => 'client', 'name' => __( 'Client', 'nura-experience' ), 'min' => 0 ),
			array( 'key' => 'radiant', 'name' => __( 'Radiant', 'nura-experience' ), 'min' => 500 ),
			array( 'key' => 'icon', 'name' => __( 'Icon', 'nura-experience' ), 'min' => 1500 ),
			array( 'key' => 'muse', 'name' => __( 'Muse (VIP)', 'nura-experience' ), 'min' => 4000 ),
		);
	}

	/** Return [ current tier, next tier|null ] for a points balance. */
	private function tier_for( $points ) {
		$tiers   = $this->tiers();
		$current = $tiers[0];
		$next    = isset( $tiers[1] ) ? $tiers[1] : null;
		foreach ( $tiers as $i => $t ) {
			if ( $points >= $t['min'] ) {
				$current = $t;
				$next    = isset( $tiers[ $i + 1 ] ) ? $tiers[ $i + 1 ] : null;
			}
		}
		return array( $current, $next );
	}

	public function get_points( $user_id ) {
		return (int) get_user_meta( $user_id, self::POINTS_META, true );
	}

	public function is_member( $user_id ) {
		return (bool) get_user_meta( $user_id, self::MEMBER_META, true );
	}

	/* ---------------------------------------------------------------------
	 * Order completion: points, warranties, tier upgrade
	 * ------------------------------------------------------------------- */

	public function on_order_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::PROCESSED_META ) ) {
			return;
		}
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Radiance Points.
		$earned  = (int) floor( $order->get_total() / self::RATE );
		$current = (int) get_user_meta( $user_id, self::POINTS_META, true );
		$total   = $current + $earned;
		update_user_meta( $user_id, self::POINTS_META, $total );

		// Auto-upgrade to VIP membership at Icon tier and above.
		list( $tier ) = $this->tier_for( $total );
		if ( in_array( $tier['key'], array( 'icon', 'muse' ), true ) ) {
			update_user_meta( $user_id, self::MEMBER_META, 1 );
		}

		// Warranty certificate per line item.
		$issued = $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$expiry = gmdate( 'Y-m-d', strtotime( $issued . ' +' . $this->warranty_months() . ' months' ) );
		$certs  = get_user_meta( $user_id, self::WARRANTY_META, true );
		$certs  = is_array( $certs ) ? $certs : array();
		foreach ( $order->get_items() as $item ) {
			$certs[] = array(
				'number'  => 'NURA-W-' . $order_id . '-' . $item->get_id(),
				'product' => $item->get_name(),
				'date'    => $issued,
				'expires' => $expiry,
				'order'   => $order_id,
			);
		}
		update_user_meta( $user_id, self::WARRANTY_META, $certs );

		$order->update_meta_data( self::PROCESSED_META, current_time( 'mysql' ) );
		$order->save();
	}

	/* ---------------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------------- */

	public function render_dashboard() {
		$uid    = get_current_user_id();
		$points = $this->get_points( $uid );
		list( $tier, $next ) = $this->tier_for( $points );

		$order_ids = wc_get_orders( array(
			'customer_id' => $uid,
			'limit'       => -1,
			'return'      => 'ids',
			'status'      => array( 'completed', 'processing' ),
		) );
		$order_count = is_array( $order_ids ) ? count( $order_ids ) : 0;

		$certs      = get_user_meta( $uid, self::WARRANTY_META, true );
		$active_wty = 0;
		if ( is_array( $certs ) ) {
			foreach ( $certs as $c ) {
				if ( $this->cert_active( $c ) ) {
					$active_wty++;
				}
			}
		}

		$next_care = $this->next_care_date( $uid );
		$care_txt  = $next_care ? $next_care->date_i18n( get_option( 'date_format' ) ) : __( 'After your first order', 'nura-experience' );

		echo '<div class="nurax-portal">';
		echo '<h3>' . esc_html__( 'Welcome to The NURA Circle', 'nura-experience' ) . '</h3>';
		echo '<p class="nura-portal-lede">' . esc_html__( 'Your radiance, rewarded. Track your care, warranties and Radiance Points all in one place.', 'nura-experience' ) . '</p>';

		// Stat cards.
		echo '<div class="nura-stats">';
		$this->stat( __( 'Radiance Points', 'nura-experience' ), number_format_i18n( $points ) );
		$this->stat( __( 'Membership', 'nura-experience' ), esc_html( $tier['name'] ) );
		$this->stat( __( 'Orders', 'nura-experience' ), number_format_i18n( $order_count ) );
		$this->stat( __( 'Active warranties', 'nura-experience' ), number_format_i18n( $active_wty ) );
		$this->stat( __( 'Next care date', 'nura-experience' ), esc_html( $care_txt ) );
		echo '</div>';

		// Tier progress.
		if ( $next ) {
			$span   = max( 1, $next['min'] - $tier['min'] );
			$done   = min( $span, max( 0, $points - $tier['min'] ) );
			$pct    = (int) round( ( $done / $span ) * 100 );
			$to_go  = max( 0, $next['min'] - $points );
			echo '<div class="nura-tier">';
			echo '<div class="nura-tier__labels"><span>' . esc_html( $tier['name'] ) . '</span><span>' . esc_html( $next['name'] ) . '</span></div>';
			echo '<div class="nura-tier__bar"><span style="width:' . esc_attr( $pct ) . '%"></span></div>';
			echo '<p class="nura-tier__note">' . esc_html( sprintf( /* translators: 1: points, 2: next tier */ __( '%1$s more Radiance Points to reach %2$s.', 'nura-experience' ), number_format_i18n( $to_go ), $next['name'] ) ) . '</p>';
			echo '</div>';
		} else {
			echo '<div class="nura-tier"><p class="nura-tier__note">' . esc_html__( 'You have reached Muse, our highest tier. Thank you for being part of The NURA Circle.', 'nura-experience' ) . '</p></div>';
		}

		// Quick links.
		echo '<div class="nura-portal-links">';
		echo '<a class="nura-btn nura-btn--gold" href="' . esc_url( wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">' . esc_html__( 'My orders', 'nura-experience' ) . '</a>';
		echo '<a class="nura-btn" href="' . esc_url( wc_get_endpoint_url( 'care-schedule', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">' . esc_html__( 'Care schedule', 'nura-experience' ) . '</a>';
		echo '<a class="nura-btn" href="' . esc_url( wc_get_endpoint_url( 'warranties', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">' . esc_html__( 'Warranties', 'nura-experience' ) . '</a>';
		echo '<a class="nura-btn" href="' . esc_url( home_url( '/book-appointment/' ) ) . '">' . esc_html__( 'Book a revamp', 'nura-experience' ) . '</a>';
		echo '</div>';

		echo '</div>';
	}

	private function stat( $label, $value ) {
		echo '<div class="nura-stat"><span class="nura-stat__v">' . wp_kses_post( $value ) . '</span><span class="nura-stat__k">' . esc_html( $label ) . '</span></div>';
	}

	/* ---------------------------------------------------------------------
	 * Care schedule
	 * ------------------------------------------------------------------- */

	/** The soonest upcoming care date across the customer's most recent order. */
	private function next_care_date( $uid ) {
		$orders = wc_get_orders( array(
			'customer_id' => $uid,
			'limit'       => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array( 'completed', 'processing' ),
		) );
		if ( empty( $orders ) ) {
			return null;
		}
		$d = $orders[0]->get_date_created();
		if ( ! $d ) {
			return null;
		}
		$next  = clone $d;
		$next->modify( '+42 days' );
		$guard = 0;
		while ( $next->getTimestamp() < time() && $guard < 36 ) {
			$next->modify( '+49 days' );
			$guard++;
		}
		return $next;
	}

	public function render_care() {
		$uid    = get_current_user_id();
		$orders = wc_get_orders( array(
			'customer_id' => $uid,
			'limit'       => 3,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array( 'completed', 'processing' ),
		) );

		echo '<div class="nurax-portal"><h3>' . esc_html__( 'Your care schedule', 'nura-experience' ) . '</h3>';
		if ( empty( $orders ) ) {
			echo '<p>' . esc_html__( 'Once you receive your first unit, your wash & revamp milestones will appear here, and we will email you a reminder at each one.', 'nura-experience' ) . '</p></div>';
			return;
		}
		echo '<p>' . esc_html__( 'We recommend a wash & revamp every 6-8 weeks of regular wear. We email you a reminder at each milestone below.', 'nura-experience' ) . '</p>';

		$milestones = array(
			array( __( 'Fit & first-week care', 'nura-experience' ), 2 ),
			array( __( 'First wash & revamp', 'nura-experience' ), 42 ),
			array( __( 'Revamp & refresh', 'nura-experience' ), 84 ),
		);

		foreach ( $orders as $order ) {
			$d = $order->get_date_created();
			if ( ! $d ) {
				continue;
			}
			echo '<div class="nura-care-order">';
			echo '<h4>' . esc_html( sprintf( /* translators: 1: order number, 2: date */ __( 'Order #%1$s - %2$s', 'nura-experience' ), $order->get_order_number(), $d->date_i18n( get_option( 'date_format' ) ) ) ) . '</h4>';
			echo '<ul class="nura-care-list">';
			foreach ( $milestones as $ms ) {
				$when   = clone $d;
				$when->modify( '+' . (int) $ms[1] . ' days' );
				$past   = $when->getTimestamp() < time();
				$state  = $past ? 'past' : 'upcoming';
				$badge  = $past ? __( 'was due', 'nura-experience' ) : __( 'upcoming', 'nura-experience' );
				echo '<li class="is-' . esc_attr( $state ) . '">';
				echo '<span class="nura-care-when">' . esc_html( $when->date_i18n( get_option( 'date_format' ) ) ) . '</span>';
				echo '<span class="nura-care-what">' . esc_html( $ms[0] ) . '</span>';
				echo '<span class="nura-care-badge">' . esc_html( $badge ) . '</span>';
				echo '<a class="nura-care-book" href="' . esc_url( home_url( '/book-appointment/' ) ) . '">' . esc_html__( 'Book', 'nura-experience' ) . '</a>';
				echo '</li>';
			}
			echo '</ul></div>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Warranty certificates
	 * ------------------------------------------------------------------- */

	private function warranty_months() {
		return (int) apply_filters( 'nurax_warranty_months', 12 );
	}

	private function cert_expiry( $c ) {
		if ( ! empty( $c['expires'] ) ) {
			return $c['expires'];
		}
		if ( empty( $c['date'] ) ) {
			return '';
		}
		$ts = strtotime( $c['date'] . ' +' . $this->warranty_months() . ' months' );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	private function cert_active( $c ) {
		$exp = $this->cert_expiry( $c );
		return $exp ? ( strtotime( $exp ) >= time() ) : true;
	}

	public function render_warranties() {
		$uid   = get_current_user_id();
		$certs = get_user_meta( $uid, self::WARRANTY_META, true );
		$certs = is_array( $certs ) ? $certs : array();

		// Printable single certificate view.
		if ( isset( $_GET['cert'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$number = sanitize_text_field( wp_unslash( $_GET['cert'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_certificate( $uid, $certs, $number );
			return;
		}

		echo '<div class="nurax-portal"><h3>' . esc_html__( 'Your warranty certificates', 'nura-experience' ) . '</h3>';
		if ( empty( $certs ) ) {
			echo '<p>' . esc_html__( 'Your provenance and warranty certificates will appear here after your orders are completed.', 'nura-experience' ) . '</p></div>';
			return;
		}

		$fmt = get_option( 'date_format' );
		echo '<table class="shop_table nura-wty-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Certificate', 'nura-experience' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'nura-experience' ) . '</th>';
		echo '<th>' . esc_html__( 'Issued', 'nura-experience' ) . '</th>';
		echo '<th>' . esc_html__( 'Valid until', 'nura-experience' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'nura-experience' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';
		foreach ( array_reverse( $certs ) as $c ) {
			$exp    = $this->cert_expiry( $c );
			$active = $this->cert_active( $c );
			$view   = add_query_arg( 'cert', rawurlencode( $c['number'] ), wc_get_endpoint_url( 'warranties', '', wc_get_page_permalink( 'myaccount' ) ) );
			echo '<tr>';
			echo '<td><code>' . esc_html( $c['number'] ) . '</code></td>';
			echo '<td>' . esc_html( $c['product'] ) . '</td>';
			echo '<td>' . esc_html( ! empty( $c['date'] ) ? date_i18n( $fmt, strtotime( $c['date'] ) ) : '-' ) . '</td>';
			echo '<td>' . esc_html( $exp ? date_i18n( $fmt, strtotime( $exp ) ) : '-' ) . '</td>';
			echo '<td><span class="nura-wty-status is-' . ( $active ? 'active' : 'expired' ) . '">' . esc_html( $active ? __( 'Active', 'nura-experience' ) : __( 'Expired', 'nura-experience' ) ) . '</span></td>';
			echo '<td><a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'nura-experience' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private function render_certificate( $uid, $certs, $number ) {
		$match = null;
		foreach ( $certs as $c ) {
			if ( isset( $c['number'] ) && $c['number'] === $number ) {
				$match = $c;
				break;
			}
		}
		$back = wc_get_endpoint_url( 'warranties', '', wc_get_page_permalink( 'myaccount' ) );

		echo '<div class="nurax-portal">';
		if ( ! $match ) {
			echo '<p>' . esc_html__( 'Certificate not found.', 'nura-experience' ) . '</p>';
			echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( 'Back to certificates', 'nura-experience' ) . '</a></p></div>';
			return;
		}

		$user = get_userdata( $uid );
		$name = $user ? trim( $user->first_name . ' ' . $user->last_name ) : '';
		if ( '' === $name && $user ) {
			$name = $user->display_name;
		}
		$fmt  = get_option( 'date_format' );
		$exp  = $this->cert_expiry( $match );

		echo '<div class="nura-cert" id="nura-cert">';
		echo '<p class="nura-cert__brand">' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		echo '<p class="nura-cert__title">' . esc_html__( 'Certificate of Authenticity & Warranty', 'nura-experience' ) . '</p>';
		echo '<div class="nura-cert__rows">';
		if ( $name ) {
			echo '<div><span>' . esc_html__( 'Issued to', 'nura-experience' ) . '</span><strong>' . esc_html( $name ) . '</strong></div>';
		}
		echo '<div><span>' . esc_html__( 'Unit', 'nura-experience' ) . '</span><strong>' . esc_html( $match['product'] ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Certificate no.', 'nura-experience' ) . '</span><strong>' . esc_html( $match['number'] ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Issued', 'nura-experience' ) . '</span><strong>' . esc_html( ! empty( $match['date'] ) ? date_i18n( $fmt, strtotime( $match['date'] ) ) : '-' ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Valid until', 'nura-experience' ) . '</span><strong>' . esc_html( $exp ? date_i18n( $fmt, strtotime( $exp ) ) : '-' ) . '</strong></div>';
		echo '</div>';
		echo '<p class="nura-cert__note">' . esc_html__( 'This certifies that the unit above is a genuine NURA product, backed by our written longevity guarantee. Present this certificate for warranty service and revamps.', 'nura-experience' ) . '</p>';
		echo '</div>';

		echo '<p class="nura-cert__actions">';
		echo '<button type="button" class="nura-btn nura-btn--gold" onclick="window.print()">' . esc_html__( 'Print certificate', 'nura-experience' ) . '</button> ';
		echo '<a class="nura-btn" href="' . esc_url( $back ) . '">' . esc_html__( 'Back to certificates', 'nura-experience' ) . '</a>';
		echo '</p>';
		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Marketing shortcode
	 * ------------------------------------------------------------------- */

	public function portal_shortcode() {
		if ( ! is_user_logged_in() ) {
			$login = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
			return '<p><a class="nura-btn nura-btn--gold" href="' . esc_url( $login ) . '">' . esc_html__( 'Sign in / Join The Circle', 'nura-experience' ) . '</a></p>';
		}
		$uid = get_current_user_id();
		list( $tier ) = $this->tier_for( $this->get_points( $uid ) );
		ob_start();
		echo '<div class="nurax-portal-card">';
		echo '<p>' . wp_kses_post( sprintf( /* translators: 1: points, 2: tier */ __( 'You have <strong>%1$s</strong> Radiance Points. Current tier: <strong>%2$s</strong>.', 'nura-experience' ), esc_html( number_format_i18n( $this->get_points( $uid ) ) ), esc_html( $tier['name'] ) ) ) . '</p>';
		echo '<p><a class="nura-btn" href="' . esc_url( wc_get_endpoint_url( 'nura-circle', '', wc_get_page_permalink( 'myaccount' ) ) ) . '">' . esc_html__( 'Open my portal', 'nura-experience' ) . '</a></p>';
		echo '</div>';
		return ob_get_clean();
	}
}
