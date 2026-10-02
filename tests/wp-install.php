<?php
// Installs the local test WordPress (SQLite). Usage: php tests/wp-install.php
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = 'localhost:8099';
$_SERVER['REQUEST_URI'] = '/';
require (getenv('OE_WP_PATH') ?: dirname(__DIR__) . '/.tools/wordpress') . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
echo get_class( $GLOBALS['wpdb'] ), "\n";
if ( ! is_blog_installed() ) {
    $r = wp_install( 'Test Egzaminy', 'admin', 'admin@example.test', true, '', wp_generate_password( 24 ) );
    echo 'installed user ', $r['user_id'], "\n";
}
update_option( 'siteurl', 'http://localhost:8099' );
update_option( 'home', 'http://localhost:8099' );
echo 'installed: ', is_blog_installed() ? 'yes' : 'no', "\n";
