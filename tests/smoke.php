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
    if ( $typ === '__przyklad' ) {
        $_REQUEST['_wpnonce'] = wp_create_nonce( 'oe_szablon_przyklad' );
        do_action( 'admin_post_oe_szablon_przyklad' );
    } else {
        oe_handle_generuj_docx();
    }
    exit;
}

/** Run one document download in a child process. Returns array( docx path or '', process output ). */
function t_generate( $typ, $eid, $out ) {
    $env  = array_merge( getenv(), array( 'OE_WP_PATH' => $GLOBALS['wp_path'] ) );
    $proc = proc_open( array( PHP_BINARY, __FILE__, 'doc', $typ, (string) $eid, $out ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
    $stdout = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    proc_close( $proc );
    if ( is_file( $out . '.err' ) ) {
        t_ok( false, "$typ: " . file_get_contents( $out . '.err' ) );
        unlink( $out . '.err' );
    }
    $zip = new ZipArchive();
    $ok  = is_file( $out ) && filesize( $out ) > 0 && $zip->open( $out ) === true;
    if ( $ok ) {
        $zip->close();
        // OE_SMOKE_KEEP=<dir> keeps a copy of every generated file, e.g. to open them in Word.
        if ( getenv( 'OE_SMOKE_KEEP' ) && is_dir( getenv( 'OE_SMOKE_KEEP' ) ) ) copy( $out, getenv( 'OE_SMOKE_KEEP' ) . '/' . basename( $out ) );
    } elseif ( is_file( $out ) ) {
        $stdout .= file_get_contents( $out ); // wp_die() output lands in the buffer file
    }
    return array( $ok ? $out : '', $stdout );
}

/** Plain text of a DOCX part: paragraphs end with "\n", w:br becomes "\n", page breaks "\f". */
function t_docx_text( $path, $part = 'word/document.xml' ) {
    $zip = new ZipArchive();
    if ( $zip->open( $path ) !== true ) return '';
    $xml = (string) $zip->getFromName( $part );
    $zip->close();
    $xml = preg_replace( '#<w:br w:type="page"/>#', "\f", $xml );
    $xml = preg_replace( '#<w:br/>#', "\n", $xml );
    $xml = preg_replace( '#</w:p>#', "\n", $xml );
    return html_entity_decode( strip_tags( $xml ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/** Build a minimal DOCX in Word's style (header and footer optional). */
function t_make_docx( $path, $body, $header = null, $footer = null ) {
    $ns   = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"';
    $rels = '';
    $sect = '<w:sectPr w:rsidR="00C41B2A">';
    $ct   = '';
    if ( $header !== null ) {
        $rels .= '<Relationship Id="rIdH1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>';
        $sect .= '<w:headerReference w:type="default" r:id="rIdH1"/>';
        $ct   .= '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>';
    }
    if ( $footer !== null ) {
        $rels .= '<Relationship Id="rIdF1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>';
        $sect .= '<w:footerReference w:type="default" r:id="rIdF1"/>';
        $ct   .= '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>';
    }
    $sect .= '<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>';
    $zip = new ZipArchive();
    $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
    $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' . $ct . '</Types>' );
    $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>' );
    $zip->addFromString( 'word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>' );
    $zip->addFromString( 'word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . $ns . '><w:body>' . $body . $sect . '</w:body></w:document>' );
    if ( $header !== null ) $zip->addFromString( 'word/header1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr ' . $ns . '>' . $header . '</w:hdr>' );
    if ( $footer !== null ) $zip->addFromString( 'word/footer1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' . $ns . '>' . $footer . '</w:ftr>' );
    $zip->close();
}

/** Register a DOCX file as a media library attachment. */
function t_attach_docx( $src, $name ) {
    $up   = wp_upload_dir();
    $dest = trailingslashit( $up['path'] ) . wp_unique_filename( $up['path'], $name );
    copy( $src, $dest );
    $id = wp_insert_attachment( array( 'post_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'post_title' => $name, 'post_status' => 'inherit' ), $dest );
    return (int) $id;
}

echo 'PHP ' . PHP_VERSION . ', WordPress ' . get_bloginfo( 'version' ) . "\n";

// ── Reset plugin state ──────────────────────────────────────────────────
foreach ( get_posts( array( 'post_type' => array( 'oe_egzamin', 'oe_zapis', 'oe_rodzaj' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
    wp_delete_post( $pid, true );
}
delete_option( 'oe_settings' );
delete_option( 'oe_rodzaje_niedopasowane' );
delete_option( 'oe_rodzaje_standardowe' );
oe_rodzaje_all( true );

// ── Standard types on a fresh site ──────────────────────────────────────
echo "[standard types]\n";
$e_std = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Przed typami' ) );
update_post_meta( $e_std, '_oe_rodzaj_egzaminu', 'Żeglarz Jachtowy' );
t_ok( oe_utworz_rodzaje_standardowe() === 5 && count( oe_rodzaje_all( true ) ) === 5, 'five standard types created when the site has none' );
$std_zj = oe_egzamin_rodzaj( $e_std );
t_ok( $std_zj && $std_zj['skrot'] === 'ZJ' && (int) get_post_meta( $e_std, '_oe_rodzaj_id', true ) === $std_zj['id'], 'existing exam linked to its standard type' );
$seed_std = json_decode( file_get_contents( $root . '/seed/ocean-wiedzy.json' ), true );
$same = true;
foreach ( $seed_std['rodzaje'] as $raw ) {
    $t = oe_rodzaj_get( oe_rodzaj_find_by_label( $raw['nazwa'] ) );
    $n = oe_rodzaj_normalize( $raw );
    foreach ( array( 'skrot', 'sekcje', 'karta_wiersze', 'zgoda_rodzicow', 'liczba_pytan' ) as $k ) {
        if ( $t[ $k ] !== $n[ $k ] ) $same = false;
    }
    if ( $t['nr_decyzji'] !== '' || $t['miejsca_teoria'] || $t['klucz'] !== '' ) $same = false;
}
t_ok( $same, 'standard types match the seed tasks and carry no organization data' );
t_ok( oe_utworz_rodzaje_standardowe() === 0, 'standard types are created only once' );
ob_start();
oe_metabox_rodzaj( get_default_post_to_edit( 'oe_rodzaj', true ) );
$html = ob_get_clean();
preg_match( '/var wzory = (\[.*?\]);\n/', $html, $mw );
$wz = isset( $mw[1] ) ? json_decode( $mw[1], true ) : array();
$wz_jsm = array_values( array_filter( $wz, function( $w ) { return $w['skrot'] === 'JSM'; } ) );
t_ok( substr_count( $html, '<option value="' ) >= 5 && strpos( $html, 'oe-r-wzor-wczytaj' ) !== false, 'new type screen offers the standard type picker' );
t_ok( $wz_jsm && count( $wz_jsm[0]['sekcje'] ) === 3 && $wz_jsm[0]['sekcje'][0]['linie'][0] === 'człowiek za burtą', 'picker carries all sections and tasks of each standard type' );
t_ok( strpos( $html, 'oe-r-dodaj-sekcje' ) !== false && substr_count( $html, 'class="oe-sekcja"' ) === 2, 'new type: one empty section, add-section button and a block template' );
foreach ( get_posts( array( 'post_type' => array( 'oe_rodzaj', 'oe_egzamin' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) wp_delete_post( $pid, true );
delete_option( 'oe_rodzaje_standardowe' );
$t_one = wp_insert_post( array( 'post_type' => 'oe_rodzaj', 'post_status' => 'draft', 'post_title' => 'Własny' ) );
t_ok( oe_utworz_rodzaje_standardowe() === 0, 'no standard types when the site already has a type' );
wp_delete_post( $t_one, true );
oe_rodzaje_all( true );
// The rest of the test starts without types and imports them from the seed.
update_option( 'oe_rodzaje_standardowe', 1 );
t_issues();
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
t_ok( oe_egzamin_rodzaj( $eid ) === null, 'no exam type before import' );
$imp = oe_importuj_dane( $seed );
t_ok( $imp['rodzaje'] === 5 && $imp['powiazane'] === 1, 'import created 5 types and linked the exam' );
$sm = oe_egzamin_rodzaj( $eid );
t_ok( $sm && $sm['nazwa'] === 'Sternik Motorowodny' && (int) get_post_meta( $eid, '_oe_rodzaj_id', true ) === $sm['id'], 'exam linked to its type by ID' );
t_ok( $sm && $sm['skrot'] === 'SM' && count( $sm['miejsca_teoria'] ) === 33 && strlen( $sm['klucz'] ) === 75, 'type data imported' );
t_ok( oe_importuj_dane( $seed )['rodzaje'] === 5 && count( oe_rodzaje_all( true ) ) === 5, 'second import updates instead of duplicating' );
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
t_issues();

// ── Signup submission: message handoff via transient + ?oe_msg= ─────────
echo "[signup messages]\n";
class OE_T_Redirect extends Exception {}
function t_submit( $post ) {
    $_POST = $post;
    $catch = function( $location ) { throw new OE_T_Redirect( $location ); };
    add_filter( 'wp_redirect', $catch, 1 );
    $location = '';
    try {
        oe_obsluga_formularza();
    } catch ( OE_T_Redirect $e ) {
        $location = $e->getMessage();
    }
    remove_filter( 'wp_redirect', $catch, 1 );
    $_POST = array();
    return $location;
}
function t_render_after( $location, $eid ) {
    $query = array();
    parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
    $_GET = $query;
    $html = do_shortcode( '[formularz_egzaminu id="' . $eid . '"]' );
    $_GET = array();
    return array( isset( $query['oe_msg'] ) ? $query['oe_msg'] : '', $html );
}
$form = array(
    'oe_egzamin_id' => (string) $eid, 'oe_redirect' => home_url( '/egzamin/?oe_msg=stary' ),
    'oe_nonce' => wp_create_nonce( 'oe_zapis_' . $eid ), 'oe_zgoda_rodo' => '1',
    'oe_imie' => 'Olek', 'oe_nazwisko' => "O'Test", 'oe_data_urodzenia' => '1999-05-05', 'oe_miejsce_urodzenia' => 'Bytom',
    'oe_ulica' => 'ul. Krótka 1', 'oe_kod' => '41-900', 'oe_miasto' => 'Bytom', 'oe_email' => 'zly-adres', 'oe_telefon' => '500 100 200',
);
$loc = t_submit( wp_slash( $form ) );
list( $token, $html ) = t_render_after( $loc, $eid );
t_ok( $token !== '' && $token !== 'stary' && substr_count( $loc, 'oe_msg=' ) === 1, 'error redirect carries one fresh oe_msg token' );
t_ok( strpos( $html, 'oe-komunikat oe-blad' ) !== false && strpos( $html, 'Nieprawidłowy adres e-mail.' ) !== false, 'shortcode shows the error message' );
t_ok( strpos( $html, 'value="Bytom"' ) !== false && strpos( $html, 'value="zly-adres"' ) !== false, 'shortcode restores submitted form data' );
t_ok( strpos( $html, 'value="O&#039;Test"' ) !== false, 'restored data is unslashed' );
t_ok( get_transient( 'oe_msg_' . $token ) === false, 'message transient deleted after display' );
list( , $html ) = t_render_after( $loc, $eid );
t_ok( strpos( $html, 'Nieprawidłowy adres e-mail.' ) !== false, 'second render in the same request still shows the message' );
t_ok( oe_pobierz_komunikat( $eid ) === null, 'no message without oe_msg in the URL' );
$other = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Test inny' ) );
$loc2  = t_submit( wp_slash( array_merge( $form, array( 'oe_imie' => '' ) ) ) );
list( , $html ) = t_render_after( $loc2, $other );
t_ok( strpos( $html, 'oe-komunikat' ) === false, 'message is not shown on another exam form' );
wp_delete_post( $other, true );

$GLOBALS['oe_t_mail'] = array();
$loc = t_submit( wp_slash( array_merge( $form, array( 'oe_email' => 'olek@example.test' ) ) ) );
list( $token, $html ) = t_render_after( $loc, $eid );
t_ok( strpos( $html, 'oe-komunikat oe-sukces' ) !== false && strpos( $html, 'Zapis przyjęty!' ) !== false && strpos( $html, 'SM/001/T/2026' ) !== false, 'shortcode shows the success message' );
t_ok( count( $GLOBALS['oe_t_mail'] ) === 2, 'successful signup sends 2 emails' );
$nowe = get_posts( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_oczekuje', 'numberposts' => -1, 'fields' => 'ids' ) );
t_ok( count( $nowe ) === 1, 'successful signup creates one pending oe_zapis' );
foreach ( $nowe as $pid ) wp_delete_post( $pid, true );

// Taking the last free seat: the person must see the success message, not "no free seats".
update_post_meta( $eid, '_oe_limit_miejsc', 2 ); // 1 approved already, so this signup fills the exam
$loc = t_submit( wp_slash( array_merge( $form, array( 'oe_email' => 'ostatni@example.test' ) ) ) );
list( , $html ) = t_render_after( $loc, $eid );
t_ok( strpos( $html, 'Zapis przyjęty!' ) !== false && strpos( $html, 'Brak wolnych miejsc' ) === false, 'last free seat: signup sees the success message' );
t_ok( strpos( $html, '<form class="oe-formularz"' ) === false, 'last free seat: no form after success' );
$html = do_shortcode( '[formularz_egzaminu id="' . $eid . '"]' );
t_ok( strpos( $html, 'Brak wolnych miejsc' ) !== false, 'full exam: other visitors see "no free seats"' );
$loc = t_submit( wp_slash( array_merge( $form, array( 'oe_email' => 'za-pozno@example.test' ) ) ) );
list( , $html ) = t_render_after( $loc, $eid );
t_ok( strpos( $html, 'Brak wolnych miejsc' ) !== false && count( get_posts( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_oczekuje', 'numberposts' => -1, 'fields' => 'ids' ) ) ) === 1, 'full exam: further signups are refused' );
foreach ( get_posts( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_oczekuje', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) wp_delete_post( $pid, true );
update_post_meta( $eid, '_oe_limit_miejsc', 10 );

$draft = get_default_post_to_edit( 'oe_egzamin', true );
ob_start();
oe_metabox_egzamin( $draft );
$html = ob_get_clean();
t_ok( strpos( $html, '37 1870 1045 2083 1069 7105 0001' ) !== false, 'new exam gets default account from settings' );
t_ok( strpos( $html, 'value="250"' ) !== false, 'new exam gets default fee from settings' );
t_ok( strpos( $html, '"' . $sm['id'] . '":"DSW-ZKS.442.41.2022"' ) !== false, 'metabox JS decision map comes from exam types' );
t_ok( strpos( $html, 'name="oe_rodzaj_id"' ) !== false && substr_count( $html, '<option value="' ) >= 5, 'metabox lists exam types' );
wp_delete_post( $draft->ID, true );

ob_start();
oe_render_settings_page();
$html = ob_get_clean();
t_ok( strpos( $html, 'Ustawienia egzaminów' ) !== false && strpos( $html, 'value="6342991899"' ) !== false, 'settings page renders saved values' );
t_ok( strpos( $html, 'oe_role[editor][]' ) !== false, 'capabilities table lists editor' );
t_issues();

// ── Decision numbers ────────────────────────────────────────────────────
echo "[exam types]\n";
$lhn = oe_rodzaj_find_by_label( 'Licencja do holowania narciarza' );
t_ok( $lhn && oe_rodzaj_get( $lhn )['skrot'] === 'LDHN', 'short legacy label resolves' );
t_ok( oe_rodzaj_find_by_label( 'żeglarz jachtowy' ) === oe_rodzaj_find_by_label( 'Żeglarz Jachtowy' ), 'case-insensitive match' );
t_ok( oe_rodzaj_find_by_label( '' ) === 0 && oe_rodzaj_find_by_label( 'Nieznany patent' ) === 0, 'empty or unknown label gives 0' );

$n = oe_rodzaj_normalize( array(
    'skrot' => 'ab-1 x', 'klucz' => "ab c\nx a", 'liczba_pytan' => '5', 'miejsca_teoria' => "A\n\n B ",
    'sekcje' => array( array( 'nazwa' => 'S1', 'min' => '3', 'max' => '1', 'zadania' => "[zawsze] t1\n[nigdy] t2\nt3\n" ), array( 'nazwa' => '', 'zadania' => '' ) ),
) );
t_ok( $n['skrot'] === 'AB-1X' && $n['klucz'] === 'ABCA' && $n['liczba_pytan'] === 5 && $n['miejsca_teoria'] === array( 'A', 'B' ), 'normalize: code, key, count, lines' );
t_ok( count( $n['sekcje'] ) === 1 && $n['sekcje'][0]['max'] === 3 && $n['sekcje'][0]['zadania'][0]['zawsze_poz'] && $n['sekcje'][0]['zadania'][1]['zawsze_nie'] && $n['sekcje'][0]['zadania'][2]['nazwa'] === 't3', 'normalize: sections and task markers' );

// Edit form round trip, including quotes and a backslash.
$tid = wp_insert_post( array( 'post_type' => 'oe_rodzaj', 'post_status' => 'publish', 'post_title' => 'Typ testowy' ) );
$_POST = wp_slash( array(
    'oe_rodzaj_nonce' => wp_create_nonce( 'oe_save_rodzaj' ),
    'oe_rodzaj'       => array( 'skrot' => 'TT', 'miejsca_teoria' => "Klub \"Test\" \\ 1\r\nJastrząb, Dąbrowa Górnicza\r\nPrzystań", 'sekcje' => array( array( 'nazwa' => 'praktyka', 'min' => 1, 'max' => 2, 'zadania' => "[zawsze] człowiek za burtą\r\nspotkanie z inną jednostką / wyprzedzanie\r\nslalom" ) ), 'klucz' => 'abc' ),
) );
wp_update_post( array( 'ID' => $tid, 'post_title' => 'Typ testowy' ) );
$_POST = array();
$tt = oe_rodzaj_get( $tid );
t_ok( $tt['skrot'] === 'TT' && $tt['miejsca_teoria'][0] === 'Klub "Test" \\ 1' && $tt['klucz'] === 'ABC', 'edit form saves data with quotes and backslash intact' );
t_ok( $tt['miejsca_teoria'] === array( 'Klub "Test" \\ 1', 'Jastrząb, Dąbrowa Górnicza', 'Przystań' ), 'edit form keeps lines with Polish letters whole' );
$zz = $tt['sekcje'][0]['zadania'];
t_ok( count( $zz ) === 3 && $zz[0]['nazwa'] === 'człowiek za burtą' && $zz[0]['zawsze_poz'] && $zz[1]['nazwa'] === 'spotkanie z inną jednostką / wyprzedzanie', 'edit form keeps tasks with Polish letters whole' );
ob_start();
oe_metabox_rodzaj( get_post( $tid ) );
$html = ob_get_clean();
t_ok( strpos( $html, '[zawsze] człowiek za burtą' ) !== false && strpos( $html, 'Klub &quot;Test&quot; \\ 1' ) !== false, 'edit form shows saved values' );

// Rename sync and migration.
$e2 = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Stary' ) );
update_post_meta( $e2, '_oe_rodzaj_egzaminu', 'Typ testow' );
oe_migruj_rodzaje_egzaminow();
t_ok( (int) get_post_meta( $e2, '_oe_rodzaj_id', true ) === $tid && get_post_meta( $e2, '_oe_rodzaj_egzaminu', true ) === 'Typ testowy', 'migration links a legacy label and canonicalizes it' );
wp_update_post( array( 'ID' => $tid, 'post_title' => 'Typ przemianowany' ) );
t_ok( get_post_meta( $e2, '_oe_rodzaj_egzaminu', true ) === 'Typ przemianowany', 'renaming a type updates its exams' );
$e3 = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Bez typu' ) );
update_post_meta( $e3, '_oe_rodzaj_egzaminu', 'Kajak górski' );
$mig = oe_migruj_rodzaje_egzaminow();
t_ok( $mig[1] === 1 && (int) get_option( 'oe_rodzaje_niedopasowane' ) === 1, 'unmatched exam is counted' );

// Exam save picks the type and its decision number.
$zj = oe_rodzaj_find_by_label( 'Żeglarz Jachtowy' );
$_POST = wp_slash( array( 'oe_egzamin_nonce' => wp_create_nonce( 'oe_save_egzamin' ), 'oe_rodzaj_id' => $zj, 'oe_nr_decyzji' => 'DSW-ZKS.442.41.2022' ) );
wp_update_post( array( 'ID' => $e3, 'post_title' => 'Bez typu' ) );
$_POST = array();
t_ok( (int) get_post_meta( $e3, '_oe_rodzaj_id', true ) === $zj && get_post_meta( $e3, '_oe_rodzaj_egzaminu', true ) === 'Żeglarz Jachtowy', 'exam save stores type ID and name' );
t_ok( get_post_meta( $e3, '_oe_nr_decyzji', true ) === 'DSW-ZKS.442.39.2022', 'auto decision number follows the new type' );

// Every seeded type must survive render -> browser-style form post -> save unchanged.
foreach ( oe_rodzaje_all( true ) as $rid => $before ) {
    if ( $rid === $tid ) continue;
    ob_start();
    oe_metabox_rodzaj( get_post( $rid ) );
    $form = ob_get_clean();
    $dom = new DOMDocument();
    @$dom->loadHTML( '<?xml encoding="UTF-8">' . $form );
    $pairs = array();
    foreach ( $dom->getElementsByTagName( 'input' ) as $el ) {
        $name = $el->getAttribute( 'name' );
        if ( strpos( $name, 'oe_rodzaj' ) !== 0 ) continue;
        if ( $el->getAttribute( 'type' ) === 'checkbox' && ! $el->hasAttribute( 'checked' ) ) continue;
        $pairs[] = rawurlencode( $name ) . '=' . rawurlencode( $el->getAttribute( 'value' ) );
    }
    foreach ( $dom->getElementsByTagName( 'textarea' ) as $el ) {
        // Browsers submit textarea newlines as CRLF.
        $pairs[] = rawurlencode( $el->getAttribute( 'name' ) ) . '=' . rawurlencode( str_replace( "\n", "\r\n", $el->textContent ) );
    }
    parse_str( implode( '&', $pairs ), $posted );
    $_POST = wp_slash( $posted );
    wp_update_post( array( 'ID' => $rid, 'post_title' => $before['nazwa'] ) );
    $_POST = array();
    $after = oe_rodzaj_get( $rid );
    t_ok( array_intersect_key( $after, oe_rodzaj_puste() ) === array_intersect_key( $before, oe_rodzaj_puste() ), "form round trip keeps {$before['skrot']} unchanged" );
}

$exp = oe_rodzaj_export( $sm );
t_ok( oe_rodzaj_normalize( $exp ) === array_intersect_key( $sm, oe_rodzaj_puste() ), 'export round-trips through normalize' );
foreach ( array( $e2, $e3, $tid ) as $pid ) wp_delete_post( $pid, true );
oe_migruj_rodzaje_egzaminow();
t_issues();

// ── Documents (each in a child process) ─────────────────────────────────
echo "[documents]\n";
$tmpdir = sys_get_temp_dir() . '/oe-smoke-' . getmypid();
@mkdir( $tmpdir );
foreach ( array( 'zgloszenie', 'protokol', 'zal1', 'zal2', 'zal3', 'karty', 'zaswiadczenia', 'arkusze', 'arkusze_wzor' ) as $typ ) {
    $out  = $tmpdir . '/' . $typ . '.docx';
    list( , $stdout ) = t_generate( $typ, $eid, $out );

    $xml = '';
    $zip = new ZipArchive();
    if ( is_file( $out ) && $zip->open( $out ) === true ) {
        $xml = (string) $zip->getFromName( 'word/document.xml' );
        $zip->close();
    }
    $dom   = new DOMDocument();
    $valid = $xml !== '' && @$dom->loadXML( $xml );
    t_ok( $valid, "$typ: valid DOCX" . ( $valid ? '' : ' (' . trim( substr( $stdout, 0, 300 ) ) . ')' ) );
    if ( $typ === 'zgloszenie' ) {
        t_ok( strpos( $xml, 'Katowice, ' ) !== false && strpos( $xml, 'ul. Przemysłowa 10/303' ) !== false, 'zgloszenie: org address and city from settings' );
    }
    if ( $typ === 'karty' ) {
        t_ok( strpos( $xml, 'KRS 0000881696' ) !== false, 'karty: header registry line' );
        t_ok( strpos( $xml, 'są: Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy oraz' ) !== false, 'karty: data controller from settings' );
        t_ok( strpos( $xml, 'manewry na silniku - zadania' ) !== false && strpos( $xml, 'Dołączono zgodę rodziców' ) !== false, 'karty: sections and parental consent from type' );
    }
    if ( $typ === 'protokol' ) {
        t_ok( strpos( $xml, 'powołana przez Fundację propagowania sportów wodnych dla każdego OCEAN WIEDZY w składzie' ) !== false, 'protokol: accusative org name from settings' );
    }
    if ( $typ === 'arkusze_wzor' ) {
        t_ok( substr_count( $xml, '>X<' ) === 75, 'arkusze_wzor: 75 answers marked from the type key' );
    }
}
t_issues();

// ── Templates ───────────────────────────────────────────────────────────
echo "[templates]\n";
// Two more approved participants, so row repetition is visible.
$extra_z = array();
foreach ( array( array( 'Jan', 'Kowalski' ), array( 'Zofia', 'Wiśniewska' ) ) as $p ) {
    $id = wp_insert_post( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_zatwierdzony', 'post_title' => $p[0] . ' ' . $p[1] ) );
    foreach ( array( '_oe_egzamin_id' => $eid, '_oe_imie' => $p[0], '_oe_nazwisko' => $p[1], '_oe_data_urodzenia' => '1999-05-06', '_oe_miejsce_urodzenia' => 'Bytom', '_oe_ulica' => 'ul. Długa 1', '_oe_kod' => '41-900', '_oe_miasto' => 'Bytom' ) as $k => $v ) update_post_meta( $id, $k, $v );
    $extra_z[] = $id;
}

// Word-style XML: placeholders split across runs with different formatting, proofErr, bookmarks, a hyperlink.
$tpl_listy = $tmpdir . '/tpl-listy.docx';
t_make_docx( $tpl_listy,
    '<w:p w:rsidR="00A1"><w:r><w:t xml:space="preserve">Egzamin </w:t></w:r><w:r w:rsidRPr="00B2"><w:rPr><w:b/></w:rPr><w:t>{nr_</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:rPr><w:i/></w:rPr><w:t>egza</w:t></w:r><w:bookmarkStart w:id="0" w:name="x"/><w:r><w:t>minu}</w:t></w:r><w:bookmarkEnd w:id="0"/><w:proofErr w:type="spellEnd"/><w:r><w:t xml:space="preserve"> z dnia {data}, typ {rodzaj}.</w:t></w:r></w:p>'
    . '<w:p><w:hyperlink w:anchor="x"><w:r><w:t>{org_</w:t></w:r></w:hyperlink><w:r><w:t>nazwa_biernik} / {zly_znacznik} / {u.imie}</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>{komisja}</w:t></w:r></w:p>'
    . '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid><w:gridCol w:w="1000"/><w:gridCol w:w="3000"/><w:gridCol w:w="4000"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Lp.</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Osoba</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Zadania</w:t></w:r></w:p></w:tc></w:tr>'
    . '<w:tr><w:tc><w:p><w:r><w:t>{u.lp</w:t></w:r><w:r><w:t>}</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{u.imie_nazwisko}</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{u.zadania}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
    . '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid><w:gridCol w:w="3000"/><w:gridCol w:w="4000"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>{k.rola}</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>{k.imie}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
    '<w:p><w:r><w:t xml:space="preserve">Nagłówek: {org_nazwa}</w:t></w:r></w:p>',
    '<w:p><w:r><w:t>Stopka {nr_</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>egzaminu}</w:t></w:r></w:p>'
);
$tpl_osoba = $tmpdir . '/tpl-osoba.docx';
t_make_docx( $tpl_osoba,
    '<w:p w14:paraId="1A2B3C4D" w14:textId="77777777"><w:bookmarkStart w:id="0" w:name="_GoBack"/><w:r><w:t>{org_nazwa}</w:t></w:r><w:bookmarkEnd w:id="0"/></w:p>'
    . '<w:p><w:r><w:rPr><w:sz w:val="40"/></w:rPr><w:t xml:space="preserve">Zaświadczenie nr {u.nr_zaswiadczenia}</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t xml:space="preserve">Zaświadcza się, że </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>{u.imie</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>_nazwisko}</w:t></w:r><w:r><w:t xml:space="preserve">, ur. {u.data_ur} w {u.miejsce_ur}.</w:t></w:r></w:p>'
);
if ( getenv( 'OE_SMOKE_KEEP' ) && is_dir( getenv( 'OE_SMOKE_KEEP' ) ) ) {
    copy( $tpl_listy, getenv( 'OE_SMOKE_KEEP' ) . '/tpl-listy.docx' );
    copy( $tpl_osoba, getenv( 'OE_SMOKE_KEEP' ) . '/tpl-osoba.docx' );
}
$att_listy = t_attach_docx( $tpl_listy, 'tpl-listy.docx' );
$att_osoba = t_attach_docx( $tpl_osoba, 'tpl-osoba.docx' );

// Type configuration: zal1 from a template, protokol template without a file, zal3 off, one extra per-participant document.
$smd = get_post_meta( $sm['id'], OE_RODZAJ_META, true );
$smd['dokumenty'] = array(
    'zal1'     => array( 'tryb' => 'szablon', 'szablon_id' => $att_listy, 'na_uczestnika' => false ),
    'protokol' => array( 'tryb' => 'szablon', 'szablon_id' => 0 ),
    'zal3'     => array( 'tryb' => 'wylaczony' ),
);
$smd['dokumenty_dodatkowe'] = array( array( 'nazwa' => 'Zaświadczenie własne', 'szablon_id' => $att_osoba, 'na_uczestnika' => true ), array( 'nazwa' => 'Bez pliku', 'szablon_id' => 999999 ) );
update_post_meta( $sm['id'], OE_RODZAJ_META, wp_slash( oe_rodzaj_normalize( $smd ) ) );
$smt = oe_rodzaj_get( $sm['id'] );
t_ok( $smt['dokumenty']['zal1']['szablon_id'] === $att_listy && $smt['dokumenty']['zgloszenie']['tryb'] === 'wbudowany', 'document settings saved, unspecified documents stay built in' );
t_ok( count( $smt['dokumenty_dodatkowe'] ) === 2 && $smt['dokumenty_dodatkowe'][1]['szablon_id'] === 0, 'invalid template ID is dropped' );

$lista = oe_egzamin_dokumenty( $smt );
$keys  = array_map( function( $d ) { return $d['key']; }, $lista );
t_ok( ! in_array( 'zal3', $keys, true ) && in_array( 'dodatkowy_0', $keys, true ), 'switched-off document hidden, extra document listed' );
ob_start();
oe_metabox_generuj( get_post( $eid ) );
$html = ob_get_clean();
t_ok( strpos( $html, 'Zaświadczenie własne' ) !== false && substr_count( $html, 'oe-gb oe-gb-off' ) === 2 && strpos( $html, 'typ=zal3' ) === false, 'generate box: extra document, missing files flagged, zal3 gone' );
ob_start();
oe_metabox_rodzaj( get_post( $sm['id'] ) );
$html = ob_get_clean();
t_ok( strpos( $html, 'tpl-listy' ) !== false && strpos( $html, '{u.imie_nazwisko}' ) !== false && strpos( $html, 'oe_szablon_przyklad' ) !== false, 'type editor shows templates, placeholder list and sample link' );

// Lists template.
list( $f, $o ) = t_generate( 'zal1', $eid, $tmpdir . '/out-zal1.docx' );
t_ok( $f !== '', 'zal1 from template downloads' . ( $f ? '' : ': ' . substr( trim( strip_tags( $o ) ), 0, 200 ) ) );
if ( $f ) {
    $txt = t_docx_text( $f );
    t_ok( strpos( $txt, 'Egzamin SM/001/T/2026 z dnia 15.11.2026 r., typ Sternik Motorowodny.' ) !== false, 'placeholder split across runs, proofErr and bookmark is filled' );
    t_ok( strpos( $txt, 'Fundację propagowania sportów wodnych dla każdego OCEAN WIEDZY / {zly_znacznik} / {u.imie}' ) !== false, 'hyperlink-split placeholder filled; unknown and out-of-row {u.} kept' );
    t_ok( strpos( $txt, "Jan Test - przewodniczący\nAnna Test - sekretarz" ) !== false, 'multi-line value becomes line breaks' );
    t_ok( preg_match( '/1\.\s*Ewa Próbna.*2\.\s*Jan Kowalski.*3\.\s*Zofia Wiśniewska/s', $txt ) === 1, 'participant row repeated for 3 people in order' );
    t_ok( preg_match( '/przewodniczący\s*Jan Test\s*sekretarz\s*Anna Test/', $txt ) === 1, 'commission row repeated' );
    t_ok( strpos( $txt, 'kierowanie załogą' ) !== false, 'participant tasks filled' );
    t_ok( strpos( t_docx_text( $f, 'word/header1.xml' ), 'Nagłówek: Fundacja propagowania' ) !== false && strpos( t_docx_text( $f, 'word/footer1.xml' ), 'Stopka SM/001/T/2026' ) !== false, 'header and footer filled' );
    $zip = new ZipArchive();
    $zip->open( $f );
    $dom = new DOMDocument();
    t_ok( @$dom->loadXML( $zip->getFromName( 'word/document.xml' ) ) && $zip->getFromName( '[Content_Types].xml' ) !== false, 'filled template is valid XML with all package parts' );
    $zip->close();
    // Same tasks as zal2's draw for this person.
    $u0  = oe_get_uu( $eid )[0];
    $exp = oe_szablon_wartosci_uczestnika( $u0, 0, oe_get_eg( $eid ) );
    t_ok( strpos( $txt, $exp['u.zadania'] ) !== false && $exp['u.zadania'] !== '', 'tasks match the deterministic draw' );
}

// Per-participant template.
list( $f, $o ) = t_generate( 'dodatkowy_0', $eid, $tmpdir . '/out-osoba.docx' );
t_ok( $f !== '', 'extra per-participant document downloads' . ( $f ? '' : ': ' . substr( trim( strip_tags( $o ) ), 0, 200 ) ) );
if ( $f ) {
    $txt = t_docx_text( $f );
    t_ok( substr_count( $txt, "\f" ) === 2 && substr_count( $txt, 'Fundacja propagowania' ) === 3, 'one copy per participant, separated by page breaks' );
    t_ok( strpos( $txt, 'Zaświadczenie nr 002/SM/001/T/2026' ) !== false && strpos( $txt, 'Zaświadcza się, że Jan Kowalski, ur. 06.05.1999 r. w Bytom.' ) !== false, 'participant values in each copy' );
    $zip = new ZipArchive();
    $zip->open( $f );
    $xml = $zip->getFromName( 'word/document.xml' );
    $zip->close();
    t_ok( substr_count( $xml, '<w:sectPr' ) === 1 && preg_match( '#<w:sectPr.*</w:sectPr></w:body>#s', $xml ) === 1, 'section properties kept once, at the end of the body' );
    t_ok( substr_count( $xml, 'w:bookmarkStart' ) === 1 && substr_count( $xml, '1A2B3C4D' ) === 1, 'copies drop duplicate bookmarks and paragraph IDs' );
}

// Error paths.
list( $f, $o ) = t_generate( 'protokol', $eid, $tmpdir . '/out-brak.docx' );
t_ok( $f === '' && strpos( $o, 'Brak pliku szablonu' ) !== false, 'template without a file: clear error' );
list( $f, $o ) = t_generate( 'zal3', $eid, $tmpdir . '/out-off.docx' );
t_ok( $f === '' && strpos( $o, 'wyłączony' ) !== false, 'switched-off document: clear error' );

// The sample template downloads, and works as a template itself.
list( $f, $o ) = t_generate( '__przyklad', $eid, $tmpdir . '/przyklad.docx' );
t_ok( $f !== '', 'sample template downloads' );
if ( $f ) {
    $att = t_attach_docx( $f, 'przyklad.docx' );
    $smd = get_post_meta( $sm['id'], OE_RODZAJ_META, true );
    $smd['dokumenty']['zal2'] = array( 'tryb' => 'szablon', 'szablon_id' => $att, 'na_uczestnika' => false );
    update_post_meta( $sm['id'], OE_RODZAJ_META, wp_slash( oe_rodzaj_normalize( $smd ) ) );
    list( $g, $o ) = t_generate( 'zal2', $eid, $tmpdir . '/przyklad-wyp.docx' );
    $txt = $g ? t_docx_text( $g ) : '';
    t_ok( $g !== '' && strpos( $txt, 'Uczestnicy (3)' ) !== false && strpos( $txt, 'Zofia Wiśniewska' ) !== false && strpos( $txt, 'PRZYKŁADOWY SZABLON - STERNIK MOTOROWODNY' ) !== false, 'sample template fills correctly' );
    t_ok( strpos( $txt, 'u.imie_nazwisko' ) !== false, 'placeholder reference page is left readable' );
    wp_delete_attachment( $att, true );
}

// Restore SM documents to built-in.
$smd = get_post_meta( $sm['id'], OE_RODZAJ_META, true );
$smd['dokumenty'] = array();
$smd['dokumenty_dodatkowe'] = array();
update_post_meta( $sm['id'], OE_RODZAJ_META, wp_slash( oe_rodzaj_normalize( $smd ) ) );
wp_delete_attachment( $att_listy, true );
wp_delete_attachment( $att_osoba, true );
foreach ( $extra_z as $id ) wp_delete_post( $id, true );
t_issues();

// ── K's corrections of 2026-10-02 ───────────────────────────────────────
echo "[corrections]\n";
// Types saved before the corrections are fixed once on admin load.
$stare = array(
    array( 'nazwa' => 'Jachtowy Sternik Morski (stary)', 'skrot' => 'JSM', 'sekcje' => array( array( 'nazwa' => 'żagle', 'min' => 3, 'max' => 5, 'zadania' => array( array( 'nazwa' => 'człowiek za burtą' ), array( 'nazwa' => 'zwrot' ), array( 'nazwa' => 'alarm człowiek za burtą' ) ) ) ) ),
    array( 'nazwa' => 'Sternik Motorowodny (stary)', 'skrot' => 'SM', 'sekcje' => array( array( 'nazwa' => 'silnik', 'min' => 3, 'max' => 3, 'zadania' => array( array( 'nazwa' => 'kierowanie załogą', 'zawsze_poz' => true ), array( 'nazwa' => 'człowiek za burtą' ), array( 'nazwa' => 'praca w charakterze członka załogi' ), array( 'nazwa' => 'prace bosmańskie', 'zawsze_poz' => true ) ) ) ) ),
    array( 'nazwa' => 'Licencja do holowania narciarza wodnego lub innych obiektów', 'skrot' => 'LHN' ),
);
$stare_id = array();
foreach ( $stare as $raw ) {
    $id = wp_insert_post( array( 'post_type' => 'oe_rodzaj', 'post_status' => 'publish', 'post_title' => $raw['nazwa'] ) );
    update_post_meta( $id, OE_RODZAJ_META, wp_slash( oe_rodzaj_normalize( $raw ) ) );
    $stare_id[] = $id;
}
$e_hol = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Holowanie' ) );
update_post_meta( $e_hol, '_oe_rodzaj_id', $stare_id[2] );
update_post_meta( $e_hol, '_oe_rodzaj_egzaminu', 'Licencja do holowania narciarza wodnego lub innych obiektów' );
delete_option( 'oe_rodzaje_poprawki' );
t_ok( oe_popraw_zapisane_rodzaje() >= 3, 'saved types corrected on admin load' );
$j = oe_rodzaj_get( $stare_id[0] );
$s = oe_rodzaj_get( $stare_id[1] );
$h = oe_rodzaj_get( $stare_id[2] );
t_ok( count( $j['sekcje'][0]['zadania'] ) === 2 && $j['nawigacja'], 'JSM: duplicate "alarm człowiek za burtą" removed, navigation task on' );
t_ok( ! $s['sekcje'][0]['zadania'][0]['zawsze_poz'] && $s['sekcje'][0]['zadania'][2]['zawsze_poz'] && $s['sekcje'][0]['zadania'][3]['zawsze_poz'], 'SM: tasks 1-4 drawn, 5-6 always passed' );
t_ok( $h['skrot'] === 'LDHN' && $h['nazwa'] === 'Licencja do holowania narciarza wodnego lub innych obiektów pływających', 'LHN renamed to LDHN with "pływających"' );
t_ok( get_post_meta( $e_hol, '_oe_rodzaj_egzaminu', true ) === $h['nazwa'], 'exam keeps the renamed type name' );
t_ok( oe_popraw_zapisane_rodzaje() === 0, 'corrections run once' );
foreach ( $stare_id as $id ) wp_delete_post( $id, true );
wp_delete_post( $e_hol, true );
oe_rodzaje_all( true );
$std = array();
foreach ( oe_rodzaje_standardowe() as $w ) $std[ $w['skrot'] ] = $w;
t_ok( isset( $std['LDHN'] ) && ! isset( $std['LHN'] ) && $std['JSM']['nawigacja'] && $std['MSM']['nawigacja'] && ! $std['SM']['nawigacja'], 'standard types carry the corrections' );

// Documents: page numbers, underlined passed tasks, protocol attachments, participant list.
list( $f, ) = t_generate( 'karty', $eid, $tmpdir . '/k-karty.docx' );
$zip = new ZipArchive();
$zip->open( $f );
$kx  = $zip->getFromName( 'word/document.xml' );
$ftr = (string) $zip->getFromName( 'word/footer1.xml' );
$rel = (string) $zip->getFromName( 'word/_rels/document.xml.rels' );
$zip->close();
t_ok( strpos( $ftr, 'str. ' ) !== false && strpos( $ftr, ' PAGE ' ) !== false && strpos( $ftr, ' NUMPAGES ' ) !== false && strpos( $rel, 'footer1.xml' ) !== false && strpos( $kx, 'footerReference' ) !== false, 'documents have a "str. X z Y" footer' );
$u0  = oe_get_uu( $eid )[0];
$def = oe_rodzaj_def_losowania( oe_get_eg( $eid )['rodzaj'] );
$poz = oe_losuj_zadania_v2( $u0, $def );
$n_poz = 0;
foreach ( $def['zadania'] as $idx => $z ) {
    if ( empty( $z['zawsze_nie'] ) && ( ! empty( $z['zawsze_poz'] ) || in_array( $idx, $poz ) ) ) $n_poz++;
}
t_ok( $n_poz > 0 && substr_count( $kx, '<w:u w:val="single"/>' ) === $n_poz, "karty: the $n_poz passed tasks are underlined" );
list( $f, ) = t_generate( 'protokol', $eid, $tmpdir . '/k-prot-sm.docx' );
t_ok( $f && strpos( t_docx_text( $f ), 'zadanie nawigacyjne użyte' ) === false && strpos( t_docx_text( $f ), '– arkusz prawidłowych odpowiedzi' ) !== false, 'SM protocol: two attachments, no navigation task' );
$e_jsm = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'JSM' ) );
update_post_meta( $e_jsm, '_oe_rodzaj_egzaminu', 'Jachtowy Sternik Morski' );
oe_migruj_rodzaje_egzaminow();
list( $f, ) = t_generate( 'protokol', $e_jsm, $tmpdir . '/k-prot-jsm.docx' );
$txt = $f ? preg_replace( '/\n+/', "\n", t_docx_text( $f ) ) : '';
t_ok( strpos( $txt, "– listę pytań użytą do przeprowadzenia egzaminu,\n– arkusz prawidłowych odpowiedzi,\n– zadanie nawigacyjne użyte podczas egzaminu wraz z prawidłowymi odpowiedziami." ) !== false, 'JSM protocol lists the navigation task' . ( strpos( $txt, 'zadanie nawigacyjne użyte' ) === false ? ' (nawigacja=' . var_export( oe_egzamin_rodzaj( $e_jsm )['nawigacja'] ?? null, true ) . ', ' . substr( $txt, strpos( $txt, 'Do niniejszego' ), 200 ) . ')' : '' ) );
wp_delete_post( $e_jsm, true );
$zp = wp_insert_post( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_oczekuje', 'post_title' => 'Pending' ) );
foreach ( array( '_oe_egzamin_id' => $eid, '_oe_imie' => 'Piotr', '_oe_nazwisko' => 'Oczekujący', '_oe_email' => 'piotr@example.test', '_oe_telefon' => '600 700 800', '_oe_kraj' => 'Niemcy', '_oe_znizka_mlodzi' => 1 ) as $k => $v ) update_post_meta( $zp, $k, $v );
list( $f, ) = t_generate( 'lista', $eid, $tmpdir . '/k-lista.docx' );
$txt = $f ? t_docx_text( $f ) : '';
$zip = new ZipArchive();
$zip->open( $f );
$lx = $zip->getFromName( 'word/document.xml' );
$zip->close();
t_ok( strpos( $txt, 'ewa@example.test' ) !== false && strpos( $txt, '+48 500 000 000' ) !== false && strpos( $txt, 'piotr@example.test' ) !== false && strpos( $txt, 'Oczekuje na opłatę' ) !== false, '0 lista: all signups with e-mails, phones and status' );
t_ok( strpos( $txt, 'Niemcy' ) !== false && strpos( $txt, 'ulga 50%' ) !== false && strpos( $txt, 'Zatwierdzeni: 1, oczekujący na opłatę: 1.' ) !== false && strpos( $lx, 'w:orient="landscape"' ) !== false, '0 lista: country, discount, totals, landscape' );
wp_delete_post( $zp, true );
ob_start();
oe_metabox_generuj( get_post( $eid ) );
$html = ob_get_clean();
t_ok( strpos( $html, '0 Lista uczestników' ) !== false && strpos( $html, 'typ=lista' ) < strpos( $html, 'typ=zgloszenie' ), '0 lista is the first document in the generate box' );

// E-mail: transfer title in red, bold and underlined.
$GLOBALS['oe_t_mail'] = array();
oe_wyslij_email_potwierdzenie( $zid );
$msg = $GLOBALS['oe_t_mail'][0]['message'];
t_ok( preg_match( '#color:\#c62828"><strong><u>W tytule przelewu koniecznie podaj:</u></strong><br>\s*<span[^>]*>&bdquo;Opłata za egzamin#u', $msg ) === 1 && strpos( $msg, '>Tytuł przelewu<' ) === false, 'e-mail: red, bold, underlined transfer title' );

// Signups close when the exam starts.
$e_t = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Czas' ) );
$teraz = new DateTime( 'now', wp_timezone() );
update_post_meta( $e_t, '_oe_data_egzaminu', ( clone $teraz )->modify( '-1 day' )->format( 'Y-m-d' ) );
t_ok( oe_zapisy_zamkniete( $e_t ), 'closed: exam yesterday' );
update_post_meta( $e_t, '_oe_data_egzaminu', $teraz->format( 'Y-m-d' ) );
update_post_meta( $e_t, '_oe_godzina', ( clone $teraz )->modify( '-5 minutes' )->format( 'H:i' ) );
$minelo = ( clone $teraz )->modify( '-5 minutes' )->format( 'Y-m-d' ) === $teraz->format( 'Y-m-d' );
t_ok( ! $minelo || oe_zapisy_zamkniete( $e_t ), 'closed: exam started 5 minutes ago' );
update_post_meta( $e_t, '_oe_data_egzaminu', ( clone $teraz )->modify( '+1 day' )->format( 'Y-m-d' ) );
update_post_meta( $e_t, '_oe_godzina', '10:00' );
t_ok( ! oe_zapisy_zamkniete( $e_t ), 'open: exam tomorrow' );
$set = get_option( 'oe_settings' );
update_option( 'oe_settings', array_merge( $set, array( 'zapisy_zamkniecie_godz' => '48' ) ) );
t_ok( oe_zapisy_zamkniete( $e_t ), 'closed: setting closes signups 48 hours before' );
update_option( 'oe_settings', $set );
update_post_meta( $e_t, '_oe_data_egzaminu', ( clone $teraz )->modify( '-1 day' )->format( 'Y-m-d' ) );
$html = do_shortcode( '[formularz_egzaminu id="' . $e_t . '"]' );
t_ok( strpos( $html, 'Zapisy zamknięte' ) !== false && strpos( $html, '<form' ) === false, 'closed exam: form replaced by a notice' );
$loc = t_submit( wp_slash( array( 'oe_egzamin_id' => (string) $e_t, 'oe_redirect' => home_url( '/' ), 'oe_nonce' => wp_create_nonce( 'oe_zapis_' . $e_t ), 'oe_zgoda_rodo' => '1', 'oe_imie' => 'Jan', 'oe_nazwisko' => 'Spóźniony', 'oe_data_urodzenia' => '1990-01-01', 'oe_miejsce_urodzenia' => 'Bytom', 'oe_ulica' => 'ul. A 1', 'oe_kod' => '41-900', 'oe_miasto' => 'Bytom', 'oe_email' => 'spoznialski@example.test', 'oe_telefon' => '500 100 200' ) ) );
t_ok( count( get_posts( array( 'post_type' => 'oe_zapis', 'post_status' => 'any', 'meta_key' => '_oe_egzamin_id', 'meta_value' => $e_t, 'fields' => 'ids' ) ) ) === 0, 'closed exam: late signup refused' );
$html = do_shortcode( '[lista_egzaminow pokaz_minione="tak"]' );
t_ok( strpos( $html, 'Zapisy zamknięte' ) !== false, 'exam list shows "Zapisy zamknięte"' );

// Admin list sorted by exam date, newest first, exams without a date kept.
$e_bez = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Bez daty' ) );
$q = new WP_Query();
$prev_main = $GLOBALS['wp_the_query'];
$GLOBALS['wp_the_query'] = $q;
$ids = $q->query( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$GLOBALS['wp_the_query'] = $prev_main;
$daty = array();
foreach ( $ids as $id ) $daty[] = (string) get_post_meta( $id, '_oe_data_egzaminu', true );
$z_data = array_values( array_filter( $daty ) );
$sorted = $z_data;
rsort( $sorted );
t_ok( in_array( $e_bez, $ids ) && $z_data === $sorted && count( $z_data ) >= 2, 'admin exam list: by exam date, newest first, undated kept' );
wp_delete_post( $e_t, true );
wp_delete_post( $e_bez, true );
t_issues();

// ── Exam without approved participants ──────────────────────────────────
echo "[no approved participants]\n";
$e0 = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Pusty' ) );
update_post_meta( $e0, '_oe_rodzaj_egzaminu', 'Sternik Motorowodny' );
oe_migruj_rodzaje_egzaminow();
$z0 = wp_insert_post( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_oczekuje', 'post_title' => 'Oczekuje' ) );
update_post_meta( $z0, '_oe_egzamin_id', $e0 );
ob_start();
oe_metabox_generuj( get_post( $e0 ) );
$html = ob_get_clean();
t_ok( strpos( $html, 'Brak zatwierdzonych uczestników' ) !== false && substr_count( $html, 'brak zatwierdzonych' ) >= 3 && strpos( $html, 'typ=karty' ) === false, 'generate box warns and disables per-participant documents' );
list( $f, $o ) = t_generate( 'karty', $e0, $tmpdir . '/out-pusty.docx' );
t_ok( $f === '' && strpos( $o, 'Brak zatwierdzonych uczestników' ) !== false, 'karty without approved participants: message instead of an empty file' );
list( $f, $o ) = t_generate( 'zal1', $e0, $tmpdir . '/out-pusty-zal1.docx' );
t_ok( $f !== '', 'list documents still download without participants' );
ob_start();
oe_metabox_egzamin( get_post( $e0 ) );
$html = ob_get_clean();
preg_match( '/var oeZadania = (\{.*?\});\n/', $html, $mz );
$podglad = isset( $mz[1] ) ? json_decode( $mz[1], true ) : array();
$sm_lista = isset( $podglad[ $sm['id'] ] ) ? $podglad[ $sm['id'] ][0]['zadania'] : array();
t_ok( strpos( $html, 'oe-zadania-podglad' ) !== false && in_array( 'kierowanie załogą', $sm_lista, true ) && in_array( 'praca w charakterze członka załogi (zawsze zaliczone)', $sm_lista, true ), 'exam screen previews the practical tasks of its type' );
wp_delete_post( $z0, true );
wp_delete_post( $e0, true );
t_issues();

array_map( 'unlink', glob( $tmpdir . '/*' ) ?: array() );
@rmdir( $tmpdir );

// ── Cleanup ─────────────────────────────────────────────────────────────
wp_delete_post( $zid, true );
wp_delete_post( $eid, true );
t_issues();

echo $GLOBALS['oe_t_fail'] ? "\n{$GLOBALS['oe_t_fail']} failure(s).\n" : "\nSmoke test passed.\n";
exit( $GLOBALS['oe_t_fail'] ? 1 : 0 );
