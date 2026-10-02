<?php
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OE_ACCESS_HASH', 'REDACTED' );
// Original hash and plaintext password removed before the first commit.

define( 'OE_COOKIE_NAME', 'oe_access' );
define( 'OE_COOKIE_EXPIRE', 8 * HOUR_IN_SECONDS );

function oe_verify_password( $input ) {
    return password_verify( $input, OE_ACCESS_HASH );
}

function oe_is_authenticated() {
    if ( ! isset( $_COOKIE[ OE_COOKIE_NAME ] ) ) {
        return false;
    }
    $val = $_COOKIE[ OE_COOKIE_NAME ];
    $parts = explode( '|', $val, 2 );
    if ( count( $parts ) !== 2 ) {
        return false;
    }
    $expires = (int) $parts[0];
    $sig     = $parts[1];
    if ( time() > $expires ) {
        return false;
    }
    $expected = hash_hmac( 'sha256', $expires . OE_ACCESS_HASH, AUTH_KEY );
    return hash_equals( $expected, $sig );
}

function oe_set_auth_cookie() {
    $expires = time() + OE_COOKIE_EXPIRE;
    $sig     = hash_hmac( 'sha256', $expires . OE_ACCESS_HASH, AUTH_KEY );
    $val     = $expires . '|' . $sig;
    setcookie( OE_COOKIE_NAME, $val, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

function oe_clear_auth_cookie() {
    setcookie( OE_COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

function oe_render_login_form( $error = false ) {
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
    <meta charset="UTF-8">
    <title>Ocean Wiedzy — dostep</title>
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
    .oe-box{background:#fff;border-radius:8px;box-shadow:0 2px 16px rgba(0,0,0,.12);padding:40px 36px;width:340px;max-width:95vw}
    .oe-logo{text-align:center;margin-bottom:28px;font-size:18px;font-weight:700;color:#1e3a5f;letter-spacing:.02em}
    .oe-logo span{display:block;font-size:12px;font-weight:400;color:#6b7280;margin-top:4px}
    label{display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px}
    input[type=password]{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:5px;font-size:14px;outline:none;transition:border-color .15s}
    input[type=password]:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
    .oe-btn{display:block;width:100%;margin-top:20px;padding:11px;background:#1e3a5f;color:#fff;border:none;border-radius:5px;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s}
    .oe-btn:hover{background:#163059}
    .oe-err{margin-top:14px;padding:10px 12px;background:#fef2f2;border:1px solid #fca5a5;border-radius:5px;font-size:13px;color:#b91c1c}
    </style>
    </head>
    <body>
    <div class="oe-box">
      <div class="oe-logo">Ocean Wiedzy<span>System egzaminow zegarskich</span></div>
      <form method="post" action="">
        <?php wp_nonce_field( 'oe_login', 'oe_login_nonce' ); ?>
        <label for="oe_pass">Haslo dostępu</label>
        <input type="password" id="oe_pass" name="oe_pass" autofocus autocomplete="current-password">
        <button type="submit" class="oe-btn">Zaloguj</button>
        <?php if ( $error ) : ?>
        <div class="oe-err">Nieprawidlowe haslo.</div>
        <?php endif; ?>
      </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle login POST
add_action( 'init', function() {
    if ( ! is_admin() ) return;
    if ( ! isset( $_POST['oe_login_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['oe_login_nonce'], 'oe_login' ) ) return;

    $pass = isset( $_POST['oe_pass'] ) ? $_POST['oe_pass'] : '';
    if ( oe_verify_password( $pass ) ) {
        oe_set_auth_cookie();
        wp_redirect( remove_query_arg( 'oe_login_nonce' ) );
        exit;
    } else {
        // render form with error - handled below
        add_action( 'admin_init', function() {
            oe_render_login_form( true );
        }, 1 );
    }
} );

// Guard all oe_egzamin admin screens and oe_generuj_docx action
add_action( 'admin_init', function() {
    // Check if we're on a relevant screen
    $action    = isset( $_GET['action'] ) ? $_GET['action'] : '';
    $post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : '';
    $post_id   = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
    $page_act  = isset( $_GET['action'] ) ? $_GET['action'] : '';
    $is_oe_docx = ( isset( $_GET['action'] ) && $_GET['action'] === 'oe_generuj_docx' );

    // Detect oe_egzamin post edit screen
    $is_oe_screen = false;
    if ( $post_type === 'oe_egzamin' ) {
        $is_oe_screen = true;
    }
    if ( $post_id && get_post_type( $post_id ) === 'oe_egzamin' ) {
        $is_oe_screen = true;
    }
    if ( $is_oe_docx ) {
        $is_oe_screen = true;
    }

    if ( ! $is_oe_screen ) return;

    if ( oe_is_authenticated() ) return;

    // Not authenticated — show login or handle POST
    if ( isset( $_POST['oe_login_nonce'] ) ) return; // handled in 'init'
    oe_render_login_form( false );
}, 5 );
