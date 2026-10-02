<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_post_nopriv_oe_zapisz_uczestnika', 'oe_obsluga_formularza' );
add_action( 'admin_post_oe_zapisz_uczestnika',         'oe_obsluga_formularza' );

function oe_obsluga_formularza() {
    if ( session_status() === PHP_SESSION_NONE ) session_start();

    $egzamin_id = isset($_POST['oe_egzamin_id']) ? intval($_POST['oe_egzamin_id']) : 0;
    $redirect   = isset($_POST['oe_redirect']) ? esc_url_raw($_POST['oe_redirect']) : home_url('/');

    // ── Walidacja nonce ───────────────────────────────────────────────────
    if ( ! $egzamin_id || ! wp_verify_nonce( $_POST['oe_nonce'] ?? '', 'oe_zapis_' . $egzamin_id ) ) {
        $_SESSION['oe_blad_' . $egzamin_id] = 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── Funkcja pomocnicza: zapisz dane i przekieruj z błędem ───────────
    $pola_formularza = [ 'oe_imie', 'oe_nazwisko', 'oe_data_urodzenia', 'oe_miejsce_urodzenia',
        'oe_ulica', 'oe_kod', 'oe_miasto', 'oe_kraj',
        'oe_email', 'oe_telefon', 'oe_znizka_mlodzi', 'oe_znizka_zaznaczona' ];

    // ── Walidacja wymaganych pól ──────────────────────────────────────────
    $wymagane = [ 'oe_imie', 'oe_nazwisko', 'oe_data_urodzenia', 'oe_miejsce_urodzenia',
                  'oe_ulica', 'oe_kod', 'oe_miasto', 'oe_email', 'oe_telefon' ];
    foreach ( $wymagane as $pole ) {
        if ( empty( trim( $_POST[$pole] ?? '' ) ) ) {
            $_SESSION['oe_blad_' . $egzamin_id] = 'Wypełnij wszystkie wymagane pola.';
            foreach ( $pola_formularza as $p ) {
                if ( isset($_POST[$p]) ) $_SESSION['oe_dane_' . $egzamin_id][$p] = $_POST[$p];
            }
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    if ( empty($_POST['oe_zgoda_rodo']) ) {
        $_SESSION['oe_blad_' . $egzamin_id] = 'Wymagana jest zgoda na przetwarzanie danych osobowych.';
        foreach ( $pola_formularza as $p ) {
            if ( isset($_POST[$p]) ) $_SESSION['oe_dane_' . $egzamin_id][$p] = $_POST[$p];
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── Sanityzacja danych ────────────────────────────────────────────────
    $imie           = sanitize_text_field( $_POST['oe_imie'] );
    $nazwisko       = sanitize_text_field( $_POST['oe_nazwisko'] );
    $data_urodzenia = sanitize_text_field( $_POST['oe_data_urodzenia'] );
    $miejsce_ur     = sanitize_text_field( $_POST['oe_miejsce_urodzenia'] );
    $ulica          = sanitize_text_field( $_POST['oe_ulica'] );
    $kod            = sanitize_text_field( $_POST['oe_kod'] );
    $miasto         = sanitize_text_field( $_POST['oe_miasto'] );
    $email          = sanitize_email( $_POST['oe_email'] );
    $telefon        = sanitize_text_field( $_POST['oe_telefon'] );
    $kraj           = sanitize_text_field( $_POST['oe_kraj'] ?? 'Polska' );
    $kraj           = $kraj ?: 'Polska';
    $znizka         = ( isset($_POST['oe_znizka_zaznaczona']) && $_POST['oe_znizka_zaznaczona'] === '1' ) ? 1 : 0;

    // ── Walidacja e-mail ──────────────────────────────────────────────────
    if ( ! is_email($email) ) {
        $_SESSION['oe_blad_' . $egzamin_id] = 'Nieprawidłowy adres e-mail.';
        foreach ( $pola_formularza as $p ) {
            if ( isset($_POST[$p]) ) $_SESSION['oe_dane_' . $egzamin_id][$p] = $_POST[$p];
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── Walidacja kodu pocztowego ─────────────────────────────────────────
    if ( ! preg_match('/^\d{2}-\d{3}$/', $kod) ) {
        $_SESSION['oe_blad_' . $egzamin_id] = 'Nieprawidłowy format kodu pocztowego (00-000).';
        foreach ( $pola_formularza as $p ) {
            if ( isset($_POST[$p]) ) $_SESSION['oe_dane_' . $egzamin_id][$p] = $_POST[$p];
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── Duplikat: sprawdź czy ten e-mail już jest na tym egzaminie ────────
    $duplikat = new WP_Query([
        'post_type'   => 'oe_zapis',
        'post_status' => [ 'oe_oczekuje', 'oe_zatwierdzony' ],
        'meta_query'  => [
            [ 'key' => '_oe_egzamin_id', 'value' => $egzamin_id ],
            [ 'key' => '_oe_email',      'value' => $email ],
        ],
        'posts_per_page' => 1,
        'fields'      => 'ids',
    ]);
    if ( $duplikat->have_posts() ) {
        $_SESSION['oe_blad_' . $egzamin_id] = 'Ten adres e-mail jest już zarejestrowany na ten egzamin.';
        foreach ( $pola_formularza as $p ) {
            if ( isset($_POST[$p]) ) $_SESSION['oe_dane_' . $egzamin_id][$p] = $_POST[$p];
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── Sprawdź limit miejsc ──────────────────────────────────────────────
    $limit = intval( get_post_meta( $egzamin_id, '_oe_limit_miejsc', true ) );
    if ( $limit > 0 ) {
        $zajete = new WP_Query([
            'post_type'   => 'oe_zapis',
            'post_status' => [ 'oe_oczekuje', 'oe_zatwierdzony' ],
            'meta_query'  => [ [ 'key' => '_oe_egzamin_id', 'value' => $egzamin_id ] ],
            'posts_per_page' => -1,
            'fields'      => 'ids',
        ]);
        if ( $zajete->found_posts >= $limit ) {
            $_SESSION['oe_blad_' . $egzamin_id] = 'Brak wolnych miejsc na ten egzamin.';
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    // ── Utwórz CPT zapis ─────────────────────────────────────────────────
    $tytul = "{$imie} {$nazwisko} - " . get_post_meta($egzamin_id, '_oe_nr_egzaminu', true);

    $post_data = [
        'post_type'   => 'oe_zapis',
        'post_title'  => $tytul,
        'post_status' => 'oe_oczekuje',
        'post_author' => 0,
    ];

    $zapis_id = wp_insert_post( $post_data, true );

    if ( is_wp_error($zapis_id) ) {
        $_SESSION['oe_blad_' . $egzamin_id] = 'Błąd systemu. Spróbuj ponownie za chwilę.';
        wp_safe_redirect( $redirect );
        exit;
    }

    // ── Zapisz meta ───────────────────────────────────────────────────────
    $meta = [
        '_oe_egzamin_id'       => $egzamin_id,
        '_oe_imie'             => $imie,
        '_oe_nazwisko'         => $nazwisko,
        '_oe_data_urodzenia'   => $data_urodzenia,
        '_oe_miejsce_urodzenia'=> $miejsce_ur,
        '_oe_ulica'            => $ulica,
        '_oe_kod'              => $kod,
        '_oe_miasto'           => $miasto,
        '_oe_email'            => $email,
        '_oe_telefon'          => $telefon,
        '_oe_kraj'             => $kraj,
        '_oe_znizka_mlodzi'    => $znizka,
        '_oe_ip'               => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ( $meta as $key => $val ) {
        update_post_meta( $zapis_id, $key, $val );
    }

    // ── Wyślij e-maile ────────────────────────────────────────────────────
    oe_wyslij_email_potwierdzenie( $zapis_id );
    oe_wyslij_email_admin_nowy_zapis( $zapis_id );

    // ── Sukces ────────────────────────────────────────────────────────────
    $nr_egz = get_post_meta($egzamin_id, '_oe_nr_egzaminu', true);
    $_SESSION['oe_sukces_' . $egzamin_id] =
        "<strong>Zapis przyjęty!</strong> Twoje zgłoszenie na egzamin <strong>{$nr_egz}</strong> zostało zarejestrowane. "
        . "Na podany adres e-mail wysłaliśmy potwierdzenie z danymi do przelewu opłaty egzaminacyjnej. "
        . "Po zaksięgowaniu wpłaty otrzymasz potwierdzenie zatwierdzenia miejsca.";

    wp_safe_redirect( $redirect );
    exit;
}
