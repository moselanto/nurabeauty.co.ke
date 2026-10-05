<?php
// Uninstall NURA Experience: remove plugin option. (User meta / orders are left intact on purpose.)
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'nurax_settings' );
