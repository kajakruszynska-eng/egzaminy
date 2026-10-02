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
foreach ( get_posts( array( 'post_type' => array( 'oe_egzamin', 'oe_zapis', 'oe_rodzaj' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
    wp_delete_post( $pid, true );
}
delete_option( 'oe_settings' );
delete_option( 'oe_rodzaje_niedopasowane' );
oe_rodzaje_all( true );
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
t_ok( $lhn && oe_rodzaj_get( $lhn )['skrot'] === 'LHN', 'short legacy label resolves' );
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
        t_ok( strpos( $xml, 'manewry na silniku - zadania' ) !== false && strpos( $xml, 'Dołączono zgodę rodziców' ) !== false, 'karty: sections and parental consent from type' );
    }
    if ( $typ === 'protokol' ) {
        t_ok( strpos( $xml, 'powołana przez Fundację propagowania sportów wodnych dla każdego OCEAN WIEDZY w składzie' ) !== false, 'protokol: accusative org name from settings' );
    }
    if ( $typ === 'arkusze_wzor' ) {
        t_ok( substr_count( $xml, '>X<' ) === 75, 'arkusze_wzor: 75 answers marked from the type key' );
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
