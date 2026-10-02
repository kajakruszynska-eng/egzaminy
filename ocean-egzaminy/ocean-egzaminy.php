<?php
/**
 * Plugin Name: Ocean Wiedzy - Zapisy na Egzaminy
 * Description: System zapisów uczestników na egzaminy żeglarskie z panelem admina.
 * Version: 1.0.0
 * Author: Ocean Wiedzy
 * Text Domain: ocean-egzaminy
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OE_PATH', plugin_dir_path( __FILE__ ) );
define( 'OE_URL',  plugin_dir_url( __FILE__ ) );
define( 'OE_VERSION', '1.0.0' );

require_once OE_PATH . 'includes/post-type.php';
require_once OE_PATH . 'includes/admin-columns.php';
require_once OE_PATH . 'includes/admin-metabox.php';
require_once OE_PATH . 'includes/shortcode.php';
require_once OE_PATH . 'includes/form-handler.php';
require_once OE_PATH . 'includes/emails.php';
require_once OE_PATH . 'includes/export.php';
require_once OE_PATH . 'includes/assets.php';
require_once OE_PATH . 'includes/docx-builder.php';
require_once OE_PATH . 'includes/miejsca-egzaminow.php';
require_once OE_PATH . 'includes/zadania-egzaminow.php';
require_once OE_PATH . 'includes/generator.php';
require_once OE_PATH . 'includes/hide-meta.php';

register_activation_hook( __FILE__, 'oe_activate' );
function oe_activate() {
    oe_register_post_types();
    flush_rewrite_rules();
}
