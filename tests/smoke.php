<?php
/**
 * Smoke test against a real local WordPress (see .tools/wordpress, SQLite).
 *
 * Usage: php tests/smoke.php [path/to/wordpress]
 * Default path: .tools/wordpress. OE_WP_PATH env var also works.
 *
 * It wipes this plugin's data in that WordPress, so never point it at a real site.
 * Any PHP warning, notice or deprecation raised from plugin files counts as a failure.
 * Written in PHP 7.4 syntax.
 */

$root    = dirname( __DIR__ );
$wp_path = isset( $argv[1] ) && $argv[1] !== 'doc' ? $argv[1] : ( getenv( 'OE_WP_PATH' ) ?: $root . '/.tools/wordpress' );
$mode    = ( isset( $argv[1] ) && $argv[1] === 'doc' ) ? 'doc' : 'main';
if ( $mode === 'doc' ) $wp_path = getenv( 'OE_WP_PATH' ) ?: $root . '/.tools/wordpress';

$GLOBALS['oe_t_fail']   = 0;
$GLOBALS['oe_t_issues'] = array();

set_error_handler( function( $no, $str, $file, $line ) {
    if ( strpos( str_replace( '\\', '/', $file ), '/ocean-egzaminy/' ) !== false ) {
        $GLOBALS['oe_t_issues'][] = "PHP error $no: $str in " . basename( $file ) . ":$line";
        return true;
    }
    return false;
} );

$_SERVER['HTTP_HOST']   = 'localhost:8099';
$_SERVER['REQUEST_URI'] = '/wp-admin/';
define( 'WP_ADMIN', true );
require $wp_path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

function t_ok( $cond, $label ) {
    if ( $cond ) {
        echo "  ok    $label\n";
    } else {
        $GLOBALS['oe_t_fail']++;
        echo "  FAIL  $label\n";
    }
}

function t_issues() {
    foreach ( $GLOBALS['oe_t_issues'] as $i ) {
        $GLOBALS['oe_t_fail']++;
        echo "  FAIL  $i\n";
    }
    $GLOBALS['oe_t_issues'] = array();
}

if ( ! function_exists( 'oe_setting' ) ) {
    activate_plugin( 'ocean-egzaminy/ocean-egzaminy.php' );
    echo "Plugin was inactive, activated. Run the test again.\n";
    exit( 1 );
}

// ── Doc mode: generate one document in a child process (download() calls exit) ──
if ( $mode === 'doc' ) {
    list( , , $typ, $eid, $out ) = $argv;
    wp_set_current_user( 1 );
    $_GET['egzamin_id']   = $eid;
    $_GET['typ']          = $typ;
    $_REQUEST['_wpnonce'] = wp_create_nonce( 'oe_generuj_' . $eid );
    @unlink( $out );
    register_shutdown_function( function() use ( $out ) {
        if ( $GLOBALS['oe_t_issues'] ) file_put_contents( $out . '.err', implode( "\n", $GLOBALS['oe_t_issues'] ) );
    } );
    ob_start( function( $buf ) use ( $out ) {
        file_put_contents( $out, $buf, FILE_APPEND );
        return '';
    } );
    oe_handle_generuj_docx();
    exit;
}

// Buffer all output, like a host with output_buffering on. Without it the signup
// shortcode's session_start() fails because headers are already sent (known issue,
// tracked separately; it is not caused by the code under test here).
ob_start();

echo 'PHP ' . PHP_VERSION . ', WordPress ' . get_bloginfo( 'version' ) . "\n";

// ── Reset plugin state ──────────────────────────────────────────────────
foreach ( get_posts( array( 'post_type' => array( 'oe_egzamin', 'oe_zapis' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
    wp_delete_post( $pid, true );
}
delete_option( 'oe_settings' );
delete_option( 'oe_caps_version' );
foreach ( wp_roles()->role_objects as $role ) {
    $role->remove_cap( 'oe_manage_exams' );
    $role->remove_cap( 'oe_generate_documents' );
}
wp_set_current_user( 1 );

// ── Capabilities ────────────────────────────────────────────────────────
echo "[capabilities]\n";
$cpt_edit = get_post_type_object( 'oe_egzamin' )->cap->edit_posts;
t_ok( $cpt_edit === 'oe_manage_exams', 'oe_egzamin edit_posts maps to oe_manage_exams' );
t_ok( get_post_type_object( 'oe_zapis' )->cap->create_posts === 'do_not_allow', 'oe_zapis cannot be created manually' );
t_ok( ! current_user_can( 'oe_manage_exams' ), 'admin has no cap before upgrade routine' );
do_action( 'admin_init' );
t_ok( get_option( 'oe_caps_version' ) === OE_CAPS_VERSION, 'upgrade routine stored caps version' );
t_ok( current_user_can( 'oe_manage_exams' ) && current_user_can( 'oe_generate_documents' ), 'admin gets both caps in the same request' );
$allowed = apply_filters( 'allowed_options', $GLOBALS['new_allowed_options'] ?? array() );
t_ok( isset( $allowed['oe_settings_group'] ) && in_array( 'oe_settings', $allowed['oe_settings_group'], true ), 'options.php accepts oe_settings_group' );

$editor_id = username_exists( 'oe_test_editor' ) ?: wp_create_user( 'oe_test_editor', wp_generate_password(), 'editor@example.test' );
$editor    = new WP_User( $editor_id );
$editor->set_role( 'editor' );
wp_set_current_user( $editor_id );
t_ok( ! current_user_can( 'oe_manage_exams' ), 'editor has no access by default' );
get_role( 'editor' )->add_cap( 'oe_manage_exams' );
wp_set_current_user( 0 );
wp_set_current_user( $editor_id );
t_ok( current_user_can( 'oe_manage_exams' ) && ! current_user_can( 'oe_generate_documents' ), 'editor granted exams only' );
get_role( 'editor' )->remove_cap( 'oe_manage_exams' );
wp_set_current_user( 1 );
t_issues();

// ── Test data ───────────────────────────────────────────────────────────
$eid = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Test SM' ) );
$meta = array(
    '_oe_rodzaj_egzaminu' => 'Sternik Motorowodny',
    '_oe_nr_egzaminu'     => 'SM/001/T/2026',
    '_oe_nr_decyzji'      => 'DSW-ZKS.442.41.2022',
    '_oe_data_egzaminu'   => '2026-11-15',
    '_oe_godzina'         => '10:00',
    '_oe_miejscowosc'     => 'Gliwice',
    '_oe_miejsce_teoria'  => 'Sala A, ul. Testowa 1, Gliwice',
    '_oe_miejsce_praktyka'=> 'Przystań B, Gliwice',
    '_oe_miejsce_pelne'   => 'Sala A, ul. Testowa 1, Gliwice',
    '_oe_kwota_oplaty'    => '250',
    '_oe_limit_miejsc'    => 10,
    '_oe_komisja'         => array( array( 'rola' => 'przewodniczący', 'imie' => 'Jan Test' ), array( 'rola' => 'sekretarz', 'imie' => 'Anna Test' ) ),
);
foreach ( $meta as $k => $v ) update_post_meta( $eid, $k, $v );

$zid = wp_insert_post( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_zatwierdzony', 'post_title' => 'Ewa Próbna' ) );
foreach ( array(
    '_oe_egzamin_id' => $eid, '_oe_imie' => 'Ewa', '_oe_nazwisko' => 'Próbna', '_oe_email' => 'ewa@example.test',
    '_oe_telefon' => '+48 500 000 000', '_oe_data_urodzenia' => '2000-01-02', '_oe_miejsce_urodzenia' => 'Zabrze',
    '_oe_ulica' => 'ul. Polna 3', '_oe_kod' => '44-100', '_oe_miasto' => 'Gliwice', '_oe_znizka_mlodzi' => 1,
) as $k => $v ) update_post_meta( $zid, $k, $v );

$GLOBALS['oe_t_mail'] = array();
add_filter( 'pre_wp_mail', function( $r, $atts ) {
    $GLOBALS['oe_t_mail'][] = $atts;
    return true;
}, 10, 2 );

function t_send_all( $zid ) {
    $GLOBALS['oe_t_mail'] = array();
    oe_wyslij_email_potwierdzenie( $zid );
    oe_wyslij_email_admin_nowy_zapis( $zid );
    oe_wyslij_email_zmiana_statusu( $zid, 'oe_zatwierdzony' );
    oe_wyslij_email_zmiana_statusu( $zid, 'oe_odrzucony' );
    $all = '';
    foreach ( $GLOBALS['oe_t_mail'] as $m ) {
        $all .= $m['message'] . "\n" . implode( "\n", (array) $m['headers'] ) . "\n";
    }
    return $all;
}

// ── Empty settings ──────────────────────────────────────────────────────
echo "[empty settings]\n";
t_ok( oe_org_nazwa_pelna() === get_bloginfo( 'name' ), 'name falls back to site name' );
t_ok( oe_org_rejestry() === '', 'no registry line' );
t_ok( oe_dok_miasto_data( '1.01.2026 r.' ) === '1.01.2026 r.', 'date without city' );
t_ok( strpos( oe_zgoda_formularz(), get_bloginfo( 'name' ) ) !== false, 'default consent uses org name' );
$mail = t_send_all( $zid );
t_ok( count( $GLOBALS['oe_t_mail'] ) === 4, '4 emails sent' );
t_ok( stripos( $mail, 'Ocean' ) === false, 'no Ocean Wiedzy data in emails' );
t_ok( strpos( $mail, 'BLIK' ) === false, 'no BLIK block' );
t_ok( strpos( $mail, 'Reply-To' ) === false, 'no Reply-To header' );
t_ok( strpos( $mail, 'kontakt z organizatorem' ) !== false, 'rejection email has generic contact line' );
t_issues();

// ── Import seed ─────────────────────────────────────────────────────────
echo "[seed import]\n";
$seed = json_decode( file_get_contents( $root . '/seed/ocean-wiedzy.json' ), true );
t_ok( is_array( $seed ) && isset( $seed['settings'] ), 'seed file parses' );
update_option( 'oe_settings', array_merge( (array) get_option( 'oe_settings', array() ), oe_sanitize_settings( $seed['settings'] ) ) );
foreach ( $seed['settings'] as $k => $v ) {
    if ( $v !== '' && oe_setting( $k ) !== $v ) t_ok( false, "setting $k round-trips" );
}
t_ok( oe_org_rejestry() === 'KRS 0000881696  NIP 6342991899  REGON 388227941', 'registry line' );
t_ok( oe_org_adres() === 'ul. Przemysłowa 10/303, 40-020 Katowice', 'address line' );
t_ok( oe_dok_miasto_data( 'X' ) === 'Katowice, X', 'document city' );

$mail = t_send_all( $zid );
foreach ( array(
    'Fundacja Ocean Wiedzy', 'Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy',
    'PL37 1870 1045 2083 1069 7105 0001', 'Konto Nest Bank', '+48 609 133 311', 'Blik_logo',
    'tel. +48 660 785 002', 'Reply-To: biuro@ocean-wiedzy.pl', 'Marek Kruszyński', 'Wiceprezes Zarządu',
    'Egzaminy żeglarskie i motorowodne', 'KRS 0000881696', 'DSW-ZKS.442.41.2022',
) as $needle ) {
    t_ok( strpos( $mail, $needle ) !== false, "emails contain: $needle" );
}
t_issues();

// ── Admin and front-end rendering ───────────────────────────────────────
echo "[rendering]\n";
ob_start();
echo do_shortcode( '[formularz_egzaminu id="' . $eid . '"]' );
$html = ob_get_clean();
t_ok( strpos( $html, 'przez Fundację Ocean Wiedzy' ) !== false, 'signup form shows configured consent' );

$draft = get_default_post_to_edit( 'oe_egzamin', true );
ob_start();
oe_metabox_egzamin( $draft );
$html = ob_get_clean();
t_ok( strpos( $html, '37 1870 1045 2083 1069 7105 0001' ) !== false, 'new exam gets default account from settings' );
t_ok( strpos( $html, 'value="250"' ) !== false, 'new exam gets default fee from settings' );
t_ok( strpos( $html, '"Sternik Motorowodny":"DSW-ZKS.442.41.2022"' ) !== false, 'metabox JS decision map comes from oe_get_decyzje()' );
wp_delete_post( $draft->ID, true );

ob_start();
oe_render_settings_page();
$html = ob_get_clean();
t_ok( strpos( $html, 'Ustawienia egzaminów' ) !== false && strpos( $html, 'value="6342991899"' ) !== false, 'settings page renders saved values' );
t_ok( strpos( $html, 'oe_role[editor][]' ) !== false, 'capabilities table lists editor' );
t_issues();

// ── Decision numbers ────────────────────────────────────────────────────
echo "[decisions]\n";
t_ok( oe_get_nr_decyzji_auto( 'Licencja do holowania narciarza' ) === 'DSW-ZKS.442.43.2022', 'short legacy label still resolves' );
t_ok( oe_get_nr_decyzji_auto( 'Żeglarz Jachtowy' ) === 'DSW-ZKS.442.39.2022', 'exact label resolves' );
t_ok( oe_get_nr_decyzji_auto( '' ) === '', 'empty label gives empty number' );

// ── Documents (each in a child process) ─────────────────────────────────
echo "[documents]\n";
$tmpdir = sys_get_temp_dir() . '/oe-smoke-' . getmypid();
@mkdir( $tmpdir );
foreach ( array( 'zgloszenie', 'protokol', 'zal1', 'zal2', 'zal3', 'karty', 'zaswiadczenia', 'arkusze', 'arkusze_wzor' ) as $typ ) {
    $out  = $tmpdir . '/' . $typ . '.docx';
    $env  = array_merge( getenv(), array( 'OE_WP_PATH' => $wp_path ) );
    $proc = proc_open( array( PHP_BINARY, __FILE__, 'doc', $typ, (string) $eid, $out ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
    $stdout = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    proc_close( $proc );

    $xml = '';
    $zip = new ZipArchive();
    if ( is_file( $out ) && $zip->open( $out ) === true ) {
        $xml = (string) $zip->getFromName( 'word/document.xml' );
        $zip->close();
    }
    $dom   = new DOMDocument();
    $valid = $xml !== '' && @$dom->loadXML( $xml );
    t_ok( $valid, "$typ: valid DOCX" . ( $valid ? '' : ' (' . trim( substr( $stdout, 0, 300 ) ) . ')' ) );
    if ( is_file( $out . '.err' ) ) {
        t_ok( false, "$typ: " . file_get_contents( $out . '.err' ) );
    }
    if ( $typ === 'zgloszenie' ) {
        t_ok( strpos( $xml, 'Katowice, ' ) !== false && strpos( $xml, 'ul. Przemysłowa 10/303' ) !== false, 'zgloszenie: org address and city from settings' );
    }
    if ( $typ === 'karty' ) {
        t_ok( strpos( $xml, 'KRS 0000881696' ) !== false, 'karty: header registry line' );
        t_ok( strpos( $xml, 'są: Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy oraz' ) !== false, 'karty: data controller from settings' );
    }
}
array_map( 'unlink', glob( $tmpdir . '/*' ) ?: array() );
@rmdir( $tmpdir );

// ── Cleanup ─────────────────────────────────────────────────────────────
wp_delete_post( $zid, true );
wp_delete_post( $eid, true );
t_issues();

echo $GLOBALS['oe_t_fail'] ? "\n{$GLOBALS['oe_t_fail']} failure(s).\n" : "\nSmoke test passed.\n";
exit( $GLOBALS['oe_t_fail'] ? 1 : 0 );
