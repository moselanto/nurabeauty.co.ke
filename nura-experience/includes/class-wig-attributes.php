<?php
/**
 * NURA Catalogue Setup - one-click WooCommerce catalogue architecture.
 *
 * Provisions, with a single click in the admin, the full NURA catalogue:
 *   1. Global product attributes for variable wigs + faceted filtering
 *      (Construction, Hair Type, Texture, Length, Density, Colour, Lace Type,
 *       Cap Size, Hair Origin, Style, Occasion).
 *   2. The complete product-category tree (Wigs, Hair & Extensions, Wig Care,
 *      Beauty, Accessories, Services) with sub-categories - the information
 *      architecture behind the mega menu, shop filters and SEO landing pages.
 *
 * Everything is idempotent: running it again only creates what is missing.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Wig_Attributes {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_nurax_make_attributes', array( $this, 'handle' ) );
	}

	/**
	 * Global product attributes.
	 * 'archive' => true exposes a public attribute archive that doubles as an
	 * SEO landing page (e.g. /texture/body-wave/).
	 */
	public static function defs() {
		return array(
			'construction' => array(
				'label'   => 'Construction',
				'archive' => true,
				'terms'   => array( 'Lace Front', 'HD Lace', 'Transparent Lace', '13x4 Lace', '13x6 Lace', '360 Lace', 'Full Lace', '4x4 Closure', '5x5 Closure', '6x6 Closure', 'T-Part', 'U-Part', 'Headband', 'Glueless', 'Wear & Go' ),
			),
			'hair-type' => array(
				'label'   => 'Hair Type',
				'archive' => true,
				'terms'   => array( '100% Human Hair', 'Human Hair Blend', 'Synthetic', 'Heat-Friendly Synthetic' ),
			),
			'texture' => array(
				'label'   => 'Texture',
				'archive' => true,
				'terms'   => array( 'Bone Straight', 'Silky Straight', 'Yaki Straight', 'Natural Straight', 'Body Wave', 'Loose Wave', 'Deep Wave', 'Water Wave', 'Beach Wave', 'Curly', 'Deep Curly', 'Jerry Curl', 'Kinky Curly', 'Afro Curly', 'Water Curl', 'Kinky', 'Afro' ),
			),
			'length' => array(
				'label'   => 'Length',
				'archive' => true,
				'terms'   => array( '8"', '10"', '12"', '14"', '16"', '18"', '20"', '22"', '24"', '26"', '28"', '30"' ),
			),
			'density' => array(
				'label'   => 'Density',
				'archive' => false,
				'terms'   => array( '130%', '150%', '180%', '200%', '250%' ),
			),
			'colour' => array(
				'label'   => 'Colour',
				'archive' => false,
				'terms'   => array( 'Natural Black', 'Jet Black', 'Off Black', 'Brown', 'Highlighted', 'Blonde', 'Honey Blonde', 'Ginger', 'Ombre', 'Burgundy' ),
			),
			'lace' => array(
				'label'   => 'Lace Type',
				'archive' => false,
				'terms'   => array( 'Lace Closure', 'Lace Frontal', 'HD Lace', '360 Lace', 'Full Lace', 'U-Part', 'Glueless' ),
			),
			'cap-size' => array(
				'label'   => 'Cap Size',
				'archive' => false,
				'terms'   => array( 'Small', 'Medium', 'Large' ),
			),
			'origin' => array(
				'label'   => 'Hair Origin',
				'archive' => false,
				'terms'   => array( 'Brazilian', 'Vietnamese', 'Peruvian', 'Indian', 'Malaysian', 'Cambodian' ),
			),
			'style' => array(
				'label'   => 'Style',
				'archive' => true,
				'terms'   => array( 'Bob', 'Pixie', 'Layered', 'Long', 'Short', 'Middle Part', 'Side Part', 'Ponytail', 'Fringe', 'Afro', 'Natural' ),
			),
			'occasion' => array(
				'label'   => 'Occasion',
				'archive' => true,
				'terms'   => array( 'Everyday', 'Work', 'Date Night', 'Party', 'Wedding', 'Bridal', 'Graduation', 'Vacation', 'Photoshoot', 'Red Carpet' ),
			),
		);
	}

	/**
	 * Product-category tree: parent => children. Mirrors the mega-menu IA.
	 */
	public static function cats() {
		return array(
			'Wigs' => array(
				'Human Hair Wigs', 'Human Hair Blend Wigs', 'Synthetic Wigs', 'Heat-Friendly Wigs',
				'Lace Front Wigs', 'HD Lace Wigs', 'Glueless Wigs', 'Closure Wigs', '360 Lace Wigs',
				'Full Lace Wigs', 'Headband Wigs', 'Bob Wigs', 'Pixie Wigs',
				'Straight Wigs', 'Body Wave Wigs', 'Loose Wave Wigs', 'Water Wave Wigs', 'Deep Wave Wigs',
				'Curly Wigs', 'Kinky Wigs', 'Afro Wigs', 'Bridal Wigs',
			),
			'Hair & Extensions' => array(
				'Bundles', 'Closures', 'Frontals', 'Clip-In Extensions', 'Tape-In Extensions', 'Ponytail Extensions', 'Braiding Hair',
			),
			'Wig Care' => array(
				'Shampoo', 'Conditioner', 'Treatments', 'Styling Products', 'Maintenance', 'Wig Care Kits',
			),
			'Beauty' => array(
				'Face', 'Eyes', 'Lips', 'Cheeks', 'Makeup Tools', 'Sets',
			),
			'Accessories' => array(
				'Wig Caps', 'Wig Grips', 'Wig Stands', 'Satin Bonnets', 'Brushes & Combs', 'Travel Cases',
			),
			'Services' => array(
				'Installation', 'Wig Revamp', 'Wig Repair', 'Wig Colouring', 'Restyling', 'Consultation', 'Custom Wig Making', 'Bridal Hair',
			),
		);
	}

	public function menu() {
		add_submenu_page( 'woocommerce', 'NURA Catalogue Setup', 'NURA Catalogue Setup', 'manage_woocommerce', 'nurax-wig-setup', array( $this, 'page' ) );
	}

	private function existing() {
		$rows = array();
		foreach ( self::defs() as $slug => $d ) {
			$rows[ $slug ] = taxonomy_exists( wc_attribute_taxonomy_name( $slug ) );
		}
		return $rows;
	}

	private function cats_created() {
		$made = 0;
		$total = 0;
		foreach ( self::cats() as $parent => $children ) {
			$total += 1 + count( $children );
			if ( term_exists( $parent, 'product_cat' ) ) {
				$made++;
			}
			foreach ( $children as $c ) {
				if ( term_exists( $c, 'product_cat' ) ) {
					$made++;
				}
			}
		}
		return array( $made, $total );
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$existing = $this->existing();
		list( $cats_made, $cats_total ) = $this->cats_created();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NURA Catalogue Setup', 'nura-experience' ); ?></h1>
			<p style="max-width:720px"><?php esc_html_e( 'One click provisions the full NURA catalogue: the product attributes used for variable products and faceted shop filters, plus the complete category tree behind the mega menu and SEO landing pages. It is safe to run again - only missing items are created.', 'nura-experience' ); ?></p>
			<?php if ( isset( $_GET['done'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Catalogue is ready. Attributes and the category tree have been created. Now assign products to categories and, for variable wigs, add the attributes on the product Attributes tab (tick "Used for variations") and build the variations.', 'nura-experience' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Product attributes', 'nura-experience' ); ?></h2>
			<table class="widefat" style="max-width:720px;margin:1em 0">
				<thead><tr><th><?php esc_html_e( 'Attribute', 'nura-experience' ); ?></th><th><?php esc_html_e( 'Status', 'nura-experience' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( self::defs() as $slug => $d ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $d['label'] ); ?></strong> <code>pa_<?php echo esc_html( $slug ); ?></code><br><small><?php echo esc_html( implode( ', ', $d['terms'] ) ); ?></small></td>
						<td><?php echo $existing[ $slug ] ? '<span style="color:#2e7d32;font-weight:600">Created</span>' : '<span style="color:#a00">Not yet</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Category tree', 'nura-experience' ); ?></h2>
			<p><?php printf( esc_html__( '%1$d of %2$d categories exist.', 'nura-experience' ), (int) $cats_made, (int) $cats_total ); ?></p>
			<table class="widefat" style="max-width:720px;margin:1em 0">
				<thead><tr><th><?php esc_html_e( 'Parent', 'nura-experience' ); ?></th><th><?php esc_html_e( 'Sub-categories', 'nura-experience' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( self::cats() as $parent => $children ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $parent ); ?></strong></td>
						<td><small><?php echo esc_html( implode( ', ', $children ) ); ?></small></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nurax_make_attributes">
				<?php wp_nonce_field( 'nurax_make_attributes' ); ?>
				<?php submit_button( __( 'Create / update the full NURA catalogue', 'nura-experience' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Turn a product into selectable sizes/lengths', 'nura-experience' ); ?></h2>
			<ol style="max-width:720px">
				<li><?php esc_html_e( 'Click the button above once to create the attributes and categories.', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Edit a product > Product data dropdown > choose "Variable product".', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Attributes tab: add Length / Construction, pick the values, and tick "Used for variations". Save.', 'nura-experience' ); ?></li>
				<li><?php esc_html_e( 'Variations tab: "Generate variations", then set a price (and optional SKU/stock) per variation.', 'nura-experience' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'Prefer a head start? Import the ready-made file in the plugin folder at samples/nura-variable-products-template.csv via WooCommerce > Products > Import.', 'nura-experience' ); ?></p>
		</div>
		<?php
	}

	public function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'nurax_make_attributes' ) ) {
			wp_die( esc_html__( 'Not allowed', 'nura-experience' ) );
		}

		// 1) Global product attributes.
		if ( function_exists( 'wc_create_attribute' ) ) {
			foreach ( self::defs() as $slug => $d ) {
				$tax = wc_attribute_taxonomy_name( $slug );
				if ( ! taxonomy_exists( $tax ) ) {
					wc_create_attribute( array(
						'name'         => $d['label'],
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => ! empty( $d['archive'] ),
					) );
					register_taxonomy( $tax, array( 'product' ), array() );
				}
				foreach ( $d['terms'] as $term ) {
					if ( ! term_exists( $term, $tax ) ) {
						wp_insert_term( $term, $tax );
					}
				}
			}
			delete_transient( 'wc_attribute_taxonomies' );
		}

		// 2) Product-category tree.
		if ( taxonomy_exists( 'product_cat' ) ) {
			foreach ( self::cats() as $parent => $children ) {
				$p = term_exists( $parent, 'product_cat' );
				if ( ! $p ) {
					$p = wp_insert_term( $parent, 'product_cat' );
				}
				if ( is_wp_error( $p ) ) {
					continue;
				}
				$parent_id = is_array( $p ) ? (int) $p['term_id'] : (int) $p;
				foreach ( $children as $child ) {
					if ( ! term_exists( $child, 'product_cat' ) ) {
						wp_insert_term( $child, 'product_cat', array( 'parent' => $parent_id ) );
					}
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nurax-wig-setup&done=1' ) );
		exit;
	}
}
