<?php
/**
 * Required & recommended plugins - self-contained one-click installer.
 *
 * Rather than bundle a heavy library, this installs plugins straight from the
 * WordPress.org repository using core's Plugin_Upgrader, behind a nonce-guarded
 * admin action. WooCommerce is required (the store cannot run without it); the
 * rest are recommended and can be skipped.
 *
 * @package NURA_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin manifest. Edit freely - slug is the wp.org repository slug.
 */
function nura_required_plugins() {
	return array(
		array( 'slug' => 'woocommerce', 'name' => 'WooCommerce', 'file' => 'woocommerce/woocommerce.php', 'required' => true ),
		array( 'slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'file' => 'contact-form-7/wp-contact-form-7.php', 'required' => false ),
		array( 'slug' => 'wordpress-seo', 'name' => 'Yoast SEO', 'file' => 'wordpress-seo/wp-seo.php', 'required' => false ),
		array( 'slug' => 'kadence-blocks', 'name' => 'Kadence Blocks (layout)', 'file' => 'kadence-blocks/kadence-blocks.php', 'required' => false ),
	);
}

/**
 * Status of a single plugin: 'active' | 'installed' | 'missing'.
 */
function nura_plugin_status( $file ) {
	if ( is_plugin_active( $file ) ) {
		return 'active';
	}
	$installed = get_plugins();
	return isset( $installed[ $file ] ) ? 'installed' : 'missing';
}

/**
 * Admin notice nudging the user to finish setup, shown until everything required is active.
 */
function nura_setup_notice() {
	if ( ! current_user_can( 'install_plugins' ) ) {
		return;
	}
	$pending = false;
	foreach ( nura_required_plugins() as $p ) {
		if ( $p['required'] && 'active' !== nura_plugin_status( $p['file'] ) ) {
			$pending = true;
			break;
		}
	}
	$screen = get_current_screen();
	if ( $screen && 'appearance_page_nura-welcome' === $screen->id ) {
		return; // Don't nag on the welcome page itself.
	}
	if ( $pending || ! get_option( 'nura_sample_imported' ) ) {
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <a class="button button-primary" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'NURA Beauty', 'nura-beauty' ),
			esc_html__( 'Finish setup: install required plugins and import the sample store.', 'nura-beauty' ),
			esc_url( admin_url( 'themes.php?page=nura-welcome' ) ),
			esc_html__( 'Open Setup', 'nura-beauty' )
		);
	}
}
add_action( 'admin_notices', 'nura_setup_notice' );

/**
 * Register the welcome / setup page under Appearance.
 */
function nura_welcome_menu() {
	add_theme_page(
		__( 'NURA Setup', 'nura-beauty' ),
		__( 'NURA Setup', 'nura-beauty' ),
		'manage_options',
		'nura-welcome',
		'nura_welcome_page'
	);
}
add_action( 'admin_menu', 'nura_welcome_menu' );

/**
 * One-click: install (if needed) and activate a plugin from wp.org.
 */
function nura_install_activate_plugin( $slug, $file ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$status = nura_plugin_status( $file );
	if ( 'missing' === $status ) {
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) || ! $result ) {
			return is_wp_error( $result ) ? $result : new WP_Error( 'install_failed', 'Install failed' );
		}
	}
	if ( ! is_plugin_active( $file ) ) {
		$activate = activate_plugin( $file );
		if ( is_wp_error( $activate ) ) {
			return $activate;
		}
	}
	return true;
}

/**
 * Handle the "install all required plugins" action.
 */
function nura_handle_install_plugins() {
	if ( ! current_user_can( 'install_plugins' ) || ! check_admin_referer( 'nura_install_plugins' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'nura-beauty' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$errors = array();
	foreach ( nura_required_plugins() as $p ) {
		// Install everything on first run; owner can deactivate optional ones later.
		$res = nura_install_activate_plugin( $p['slug'], $p['file'] );
		if ( is_wp_error( $res ) ) {
			$errors[] = $p['name'] . ': ' . $res->get_error_message();
		}
	}
	$redirect = admin_url( 'themes.php?page=nura-welcome' );
	$redirect = add_query_arg( 'nura_plugins', empty( $errors ) ? 'done' : 'partial', $redirect );
	if ( $errors ) {
		set_transient( 'nura_plugin_errors', $errors, 60 );
	}
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_nura_install_plugins', 'nura_handle_install_plugins' );

/**
 * The welcome / setup screen: two guided steps.
 */
function nura_welcome_page() {
	$woo_active = class_exists( 'WooCommerce' );
	?>
	<div class="wrap">
		<div class="nura-welcome">
			<h1><?php esc_html_e( 'Welcome to NURA Beauty', 'nura-beauty' ); ?></h1>
			<p><?php esc_html_e( 'The House of Radiant Confidence. Two steps to a complete, ready-to-sell luxury store.', 'nura-beauty' ); ?></p>

			<?php
			if ( isset( $_GET['nura_plugins'] ) ) {
				$errs = get_transient( 'nura_plugin_errors' );
				if ( $errs ) {
					echo '<div class="notice notice-warning"><p>' . esc_html__( 'Some plugins could not be installed automatically:', 'nura-beauty' ) . '<br>' . esc_html( implode( ' | ', (array) $errs ) ) . '</p></div>';
					delete_transient( 'nura_plugin_errors' );
				} else {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Plugins installed and activated.', 'nura-beauty' ) . '</p></div>';
				}
			}
			if ( isset( $_GET['nura_import'] ) && 'done' === $_GET['nura_import'] ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Sample store imported. Your site is ready - view the homepage.', 'nura-beauty' ) . '</p></div>';
			}
			?>

			<div class="nura-step">
				<h3><?php esc_html_e( 'Step 1 - Install required plugins', 'nura-beauty' ); ?></h3>
				<ul>
					<?php foreach ( nura_required_plugins() as $p ) :
						$st = nura_plugin_status( $p['file'] );
						$label = 'active' === $st ? '&#10003; ' . __( 'Active', 'nura-beauty' ) : ( 'installed' === $st ? __( 'Installed (inactive)', 'nura-beauty' ) : __( 'Not installed', 'nura-beauty' ) );
						?>
						<li><?php echo esc_html( $p['name'] ); ?><?php echo $p['required'] ? ' <em>(' . esc_html__( 'required', 'nura-beauty' ) . ')</em>' : ''; ?> &mdash; <?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nura_install_plugins">
					<?php wp_nonce_field( 'nura_install_plugins' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Install & activate plugins', 'nura-beauty' ); ?></button>
				</form>
			</div>

			<div class="nura-step">
				<h3><?php esc_html_e( 'Step 2 - Import the sample store', 'nura-beauty' ); ?></h3>
				<p><?php esc_html_e( 'Creates all pages (Track Order, Shipping, Returns, Warranty, FAQ, About, Contact, Delivery, Installation, Bulk & Corporate, Help & Support, Book Appointment), sample wig collections and variable products, menus, and sets the luxury homepage. Everything is editable afterwards.', 'nura-beauty' ); ?></p>
				<?php if ( ! $woo_active ) : ?>
					<p><em><?php esc_html_e( 'Activate WooCommerce first (Step 1) to import products.', 'nura-beauty' ); ?></em></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nura_import_sample">
					<?php wp_nonce_field( 'nura_import_sample' ); ?>
					<button type="submit" class="button button-primary" <?php disabled( ! $woo_active ); ?>><?php echo get_option( 'nura_sample_imported' ) ? esc_html__( 'Re-run sample import', 'nura-beauty' ) : esc_html__( 'Import sample data', 'nura-beauty' ); ?></button>
				</form>
			</div>

			<div class="nura-step">
				<h3><?php esc_html_e( 'Step 3 - Make it yours', 'nura-beauty' ); ?></h3>
				<p><?php esc_html_e( 'Open the Customizer to set your logo, colours, fonts, phone/WhatsApp, prices and homepage text. Nothing is hardcoded.', 'nura-beauty' ); ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'Open Customizer', 'nura-beauty' ); ?></a>
				</p>
			</div>
		</div>
	</div>
	<?php
}
