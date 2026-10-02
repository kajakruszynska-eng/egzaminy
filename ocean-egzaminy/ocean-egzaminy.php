<?php
/**
 * Plugin Name: Zapisy na Egzaminy
 * Description: System zapisów uczestników na egzaminy żeglarskie z panelem admina i generowaniem dokumentów.
 * Version: 1.3.5
 * Author: Ocean Wiedzy
 * Text Domain: ocean-egzaminy
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OE_PATH', plugin_dir_path( __FILE__ ) );
define( 'OE_URL',  plugin_dir_url( __FILE__ ) );
define( 'OE_VERSION', '1.3.5' );

require_once OE_PATH . 'includes/settings.php';
require_once OE_PATH . 'includes/capabilities.php';
require_once OE_PATH . 'includes/post-type.php';
require_once OE_PATH . 'includes/admin-columns.php';
require_once OE_PATH . 'includes/admin-metabox.php';
require_once OE_PATH . 'includes/shortcode.php';
require_once OE_PATH . 'includes/form-handler.php';
require_once OE_PATH . 'includes/emails.php';
require_once OE_PATH . 'includes/export.php';
require_once OE_PATH . 'includes/assets.php';
require_once OE_PATH . 'includes/docx-builder.php';
require_once OE_PATH . 'includes/rodzaje.php';
require_once OE_PATH . 'includes/rodzaje-standardowe.php';
require_once OE_PATH . 'includes/szablony.php';
require_once OE_PATH . 'includes/generator.php';
require_once OE_PATH . 'includes/hide-meta.php';

register_activation_hook( __FILE__, 'oe_activate' );
function oe_activate() {
    oe_register_post_types();
    flush_rewrite_rules();
    oe_grant_admin_caps();
}
