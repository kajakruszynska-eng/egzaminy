<?php
/**
 * Snapshot of all built-in documents, to prove a change did not alter them.
 *
 *   php tests/docs-snapshot.php <wp_path> <seed.json> <outdir>   generate word/document.xml of all 9 documents
 *                                                                for every exam type in the seed (3 participants each)
 *   php tests/docs-snapshot.php compare <dir_a> <dir_b>          byte-compare two snapshots
 *
 * Typical use: snapshot with the old code (git worktree of the previous commit linked as the
 * plugin), snapshot with the new code, compare. See docs/TESTING.md. It wipes the plugin's data
 * in that WordPress. Works with code from 1.1.0 on.
 */
if ( isset( $argv[1] ) && $argv[1] === 'compare' ) {
    $diff = 0;
    $a = glob( rtrim( $argv[2], '/\\' ) . '/*.xml' ) ?: array();
    foreach ( $a as $f ) {
        $g = rtrim( $argv[3], '/\\' ) . '/' . basename( $f );
        if ( ! is_file( $g ) || md5_file( $f ) !== md5_file( $g ) ) {
            echo 'DIFF ', basename( $f ), "\n";
            $diff++;
        }
    }
    echo count( $a ), " files compared, $diff differences\n";
    exit( ( $diff || ! $a ) ? 1 : 0 );
}
$wp_path = $argv[1];
$_SERVER['HTTP_HOST'] = 'localhost:8099';
$_SERVER['REQUEST_URI'] = '/wp-admin/';
define( 'WP_ADMIN', true );
require $wp_path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
get_role( 'administrator' )->add_cap( 'oe_manage_exams' );
get_role( 'administrator' )->add_cap( 'oe_generate_documents' );
wp_set_current_user( 0 ); wp_set_current_user( 1 );

if ( $argv[2] === 'doc' ) {
    list( , , , $typ, $eid, $out ) = $argv;
    $_GET['egzamin_id'] = $eid; $_GET['typ'] = $typ;
    $_REQUEST['_wpnonce'] = wp_create_nonce( 'oe_generuj_' . $eid );
    ob_start( function( $b ) use ( $out ) { file_put_contents( $out, $b, FILE_APPEND ); return ''; } );
    oe_handle_generuj_docx();
    exit;
}

list( , , $seedf, $outdir ) = $argv;
@mkdir( $outdir, 0777, true );
foreach ( get_posts( array( 'post_type' => array( 'oe_egzamin', 'oe_zapis', 'oe_rodzaj' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $p ) wp_delete_post( $p, true );
delete_option( 'oe_settings' );
$seed = json_decode( file_get_contents( $seedf ), true );
update_option( 'oe_settings', oe_sanitize_settings( $seed['settings'] ) );

$people = array( array( 'Ewa', 'Próbna' ), array( 'Jan', 'Kowalski' ), array( 'Zofia', 'Wiśniewska-Nowak' ) );
$exams = array();
foreach ( $seed['rodzaje'] as $i => $t ) {
    $eid = wp_insert_post( array( 'post_type' => 'oe_egzamin', 'post_status' => 'publish', 'post_title' => 'Reg ' . $t['skrot'], 'post_date' => '2026-09-01 10:00:00' ) );
    foreach ( array(
        '_oe_rodzaj_egzaminu' => $t['nazwa'], '_oe_nr_egzaminu' => $t['skrot'] . '/00' . $i . '/T/2026', '_oe_nr_decyzji' => $t['nr_decyzji'],
        '_oe_data_egzaminu' => '2026-11-15', '_oe_godzina' => '10:00', '_oe_miejscowosc' => 'Gliwice',
        '_oe_miejsce_teoria' => $t['miejsca_teoria'][0], '_oe_miejsce_praktyka' => $t['miejsca_praktyka'][0],
        '_oe_komisja' => array( array( 'rola' => 'przewodniczący', 'imie' => 'Jan Test' ), array( 'rola' => 'sekretarz', 'imie' => 'Anna Test' ), array( 'rola' => 'członek', 'imie' => 'Piotr Test' ) ),
    ) as $k => $v ) update_post_meta( $eid, $k, $v );
    foreach ( $people as $j => $p ) {
        $zid = wp_insert_post( array( 'post_type' => 'oe_zapis', 'post_status' => 'oe_zatwierdzony', 'post_title' => $p[0] . ' ' . $p[1], 'post_date' => '2026-09-0' . ( $j + 2 ) . ' 10:00:00' ) );
        foreach ( array( '_oe_egzamin_id' => $eid, '_oe_imie' => $p[0], '_oe_nazwisko' => $p[1], '_oe_data_urodzenia' => '2000-01-0' . ( $j + 1 ), '_oe_miejsce_urodzenia' => 'Zabrze', '_oe_ulica' => 'ul. Polna ' . $j, '_oe_kod' => '44-100', '_oe_miasto' => 'Gliwice' ) as $k => $v ) update_post_meta( $zid, $k, $v );
    }
    $exams[ $t['skrot'] ] = $eid;
}
if ( function_exists( 'oe_importuj_dane' ) ) {
    $r = oe_importuj_dane( $seed );
    echo "new code: imported {$r['rodzaje']} types, linked {$r['powiazane']} exams\n";
} else {
    echo "old code\n";
}
foreach ( $exams as $skrot => $eid ) {
    foreach ( array( 'zgloszenie', 'protokol', 'zal1', 'zal2', 'zal3', 'karty', 'zaswiadczenia', 'arkusze', 'arkusze_wzor' ) as $typ ) {
        $out = "$outdir/{$skrot}_{$typ}.docx";
        @unlink( $out );
        $cmd = array( PHP_BINARY, __FILE__, $wp_path, 'doc', $typ, (string) $eid, $out );
        $proc = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
        $err = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
        fclose( $pipes[1] ); fclose( $pipes[2] ); proc_close( $proc );
        $z = new ZipArchive();
        if ( is_file( $out ) && $z->open( $out ) === true ) {
            file_put_contents( "$outdir/{$skrot}_{$typ}.xml", $z->getFromName( 'word/document.xml' ) );
            $z->close();
            unlink( $out );
        } else {
            echo "FAILED $skrot $typ: " . substr( $err, 0, 300 ) . "\n";
        }
    }
}
echo "done\n";
