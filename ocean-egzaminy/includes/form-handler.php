<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_post_nopriv_oe_zapisz_uczestnika', 'oe_obsluga_formularza' );
add_action( 'admin_post_oe_zapisz_uczestnika',         'oe_obsluga_formularza' );

/**
 * Store a one-time message (and optionally the submitted form data) in a
 * short-lived transient and redirect back with its token in ?oe_msg=.
 * The [formularz_egzaminu] shortcode reads it with oe_pobierz_komunikat().
 */
function oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, $typ, $tresc, $dane = array() ) {
    $token = strtolower( wp_generate_password( 32, false, false ) );
    set_transient( 'oe_msg_' . $token, array(
        'egzamin_id' => (int) $egzamin_id,
        'typ'        => $typ,
        'tresc'      => $tresc,
        'dane'       => $dane,
    ), 15 * MINUTE_IN_SECONDS );
    wp_safe_redirect( add_query_arg( 'oe_msg', $token, remove_query_arg( 'oe_msg', $redirect ) ) );
    exit;
}

/**
 * Message stored by oe_przekieruj_z_komunikatem() for this exam, or null.
 * The transient is deleted on first read; the result is kept for the rest of
 * the request so the shortcode may render more than once.
 */
function oe_pobierz_komunikat( $egzamin_id ) {
    static $cache = array();
    $token = isset( $_GET['oe_msg'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $_GET['oe_msg'] ) ) : '';
    if ( $token === '' ) return null;
    if ( ! array_key_exists( $token, $cache ) ) {
        $msg = get_transient( 'oe_msg_' . $token );
        delete_transient( 'oe_msg_' . $token );
        $cache[ $token ] = is_array( $msg ) ? $msg : null;
    }
    $msg = $cache[ $token ];
    if ( ! $msg || (int) $msg['egzamin_id'] !== (int) $egzamin_id ) return null;
    return $msg;
}

/** Submitted values of the given fields, to refill the form after an error. */
function oe_dane_formularza( $pola ) {
    $dane = array();
    foreach ( $pola as $p ) {
        if ( isset( $_POST[ $p ] ) ) $dane[ $p ] = sanitize_text_field( wp_unslash( $_POST[ $p ] ) );
    }
    return $dane;
}

function oe_obsluga_formularza() {
    $egzamin_id = isset($_POST['oe_egzamin_id']) ? intval($_POST['oe_egzamin_id']) : 0;
    $redirect   = isset($_POST['oe_redirect']) ? esc_url_raw($_POST['oe_redirect']) : home_url('/');

    // ── Walidacja nonce ───────────────────────────────────────────────────
    if ( ! $egzamin_id || ! wp_verify_nonce( $_POST['oe_nonce'] ?? '', 'oe_zapis_' . $egzamin_id ) ) {
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.' );
    }

    // ── Zapisy zamknięte po rozpoczęciu egzaminu ──────────────────────────
    if ( get_post_type( $egzamin_id ) !== 'oe_egzamin' || get_post_status( $egzamin_id ) !== 'publish' || oe_zapisy_zamkniete( $egzamin_id ) ) {
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Zapisy na ten egzamin są zamknięte.' );
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
            oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Wypełnij wszystkie wymagane pola.', oe_dane_formularza( $pola_formularza ) );
        }
    }

    if ( empty($_POST['oe_zgoda_rodo']) ) {
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Wymagana jest zgoda na przetwarzanie danych osobowych.', oe_dane_formularza( $pola_formularza ) );
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
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Nieprawidłowy adres e-mail.', oe_dane_formularza( $pola_formularza ) );
    }

    // ── Walidacja kodu pocztowego ─────────────────────────────────────────
    if ( ! preg_match('/^\d{2}-\d{3}$/', $kod) ) {
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Nieprawidłowy format kodu pocztowego (00-000).', oe_dane_formularza( $pola_formularza ) );
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
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Ten adres e-mail jest już zarejestrowany na ten egzamin.', oe_dane_formularza( $pola_formularza ) );
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
            oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Brak wolnych miejsc na ten egzamin.' );
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
        oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'blad', 'Błąd systemu. Spróbuj ponownie za chwilę.' );
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
    oe_przekieruj_z_komunikatem( $redirect, $egzamin_id, 'sukces',
        "<strong>Zapis przyjęty!</strong> Twoje zgłoszenie na egzamin <strong>{$nr_egz}</strong> zostało zarejestrowane. "
        . "Na podany adres e-mail wysłaliśmy potwierdzenie z danymi do przelewu opłaty egzaminacyjnej. "
        . "Po zaksięgowaniu wpłaty otrzymasz potwierdzenie zatwierdzenia miejsca." );
}
