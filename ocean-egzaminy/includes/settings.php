<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Organization settings, stored in the single option `oe_settings`.
 * Every organization-specific value (name, address, registry numbers,
 * bank account, contacts, email signature, consent text) is read from here.
 */

define( 'OE_SETTINGS_OPTION', 'oe_settings' );

/**
 * Field definitions: section => [ 'title' => ..., 'fields' => [ key => [ label, type, help ] ] ].
 * Types: text, textarea, email, number, url.
 */
function oe_settings_fields() {
    return array(
        'organizacja' => array(
            'title'  => 'Organizacja',
            'fields' => array(
                'org_nazwa_pelna'  => array( 'Pełna nazwa', 'text', 'Nagłówki dokumentów, stopka e-maili, klauzula RODO na kartach egzaminacyjnych.' ),
                'org_nazwa_krotka' => array( 'Nazwa skrócona', 'text', 'Nagłówek e-maili. Puste: pełna nazwa.' ),
                'org_nazwa_biernik' => array( 'Pełna nazwa w bierniku', 'text', 'Protokół: „Komisja Egzaminacyjna powołana przez …”, np. Fundację … Puste: pełna nazwa.' ),
                'org_ulica'        => array( 'Ulica i numer', 'text', 'np. ul. Morska 1/2' ),
                'org_kod'          => array( 'Kod pocztowy', 'text', '' ),
                'org_miasto'       => array( 'Miejscowość', 'text', '' ),
                'org_krs'          => array( 'KRS', 'text', 'Puste pola rejestrowe są pomijane.' ),
                'org_nip'          => array( 'NIP', 'text', '' ),
                'org_regon'        => array( 'REGON', 'text', '' ),
                'org_logo_url'     => array( 'Logo (URL)', 'url', 'Opcjonalne. Wyświetlane w nagłówku e-maili.' ),
            ),
        ),
        'kontakt' => array(
            'title'  => 'Kontakt i e-maile',
            'fields' => array(
                'org_telefon'      => array( 'Telefon kontaktowy', 'text', 'Podawany uczestnikom w e-mailach.' ),
                'email_reply_to'   => array( 'Adres do odpowiedzi (Reply-To)', 'email', 'Puste: odpowiedzi trafiają na adres nadawcy.' ),
                'email_podtytul'   => array( 'Podtytuł w nagłówku e-maili', 'text', 'np. Egzaminy żeglarskie i motorowodne' ),
                'podpis_imie'      => array( 'Podpis: imię i nazwisko', 'text', 'Pod e-mailem potwierdzającym. Puste: bez podpisu.' ),
                'podpis_funkcja'   => array( 'Podpis: funkcja', 'text', 'np. Prezes Zarządu' ),
            ),
        ),
        'platnosci' => array(
            'title'  => 'Płatności',
            'fields' => array(
                'bank_nazwa'       => array( 'Nazwa banku', 'text', '' ),
                'bank_konto'       => array( 'Nr rachunku', 'text', 'Bez prefiksu PL, np. 00 0000 0000 0000 0000 0000 0000. Domyślna wartość dla nowych egzaminów.' ),
                'bank_wlasciciel'  => array( 'Właściciel rachunku', 'text', 'Puste: nazwa skrócona i adres.' ),
                'kwota_domyslna'   => array( 'Domyślna opłata (PLN)', 'number', 'Domyślna wartość dla nowych egzaminów.' ),
                'blik_telefon'     => array( 'Telefon do przelewu BLIK', 'text', 'Puste: e-maile nie pokazują opcji BLIK.' ),
                'blik_logo_url'    => array( 'Logo BLIK (URL)', 'url', 'Opcjonalne. Puste: tekst "BLIK".' ),
            ),
        ),
        'dokumenty' => array(
            'title'  => 'Dokumenty i zgody',
            'fields' => array(
                'dok_miasto'       => array( 'Miejscowość wystawienia dokumentów', 'text', 'Puste: miejscowość organizacji.' ),
                'zgloszenie_osoba' => array( 'Osoba uprawniona do zgłaszania egzaminu', 'text', 'Imię i nazwisko pod zgłoszeniem egzaminu (osoba upoważniona do sporządzenia informacji). Puste: druga osoba ze składu komisji egzaminu.' ),
                'zapisy_zamkniecie_godz' => array( 'Zamknij zapisy (godzin przed egzaminem)', 'number', 'Zapisy zamykają się zawsze najpóźniej o godzinie rozpoczęcia egzaminu (bez godziny: o północy w dniu egzaminu). Np. 24 zamyka je dobę wcześniej. Puste lub 0: przy rozpoczęciu.' ),
                'zgoda_formularz'  => array( 'Treść zgody w formularzu zapisu', 'textarea', '{nazwa} zostanie zastąpione nazwą skróconą organizacji.' ),
            ),
        ),
    );
}

function oe_settings_defaults() {
    $defaults = array();
    foreach ( oe_settings_fields() as $section ) {
        foreach ( $section['fields'] as $key => $f ) {
            $defaults[ $key ] = '';
        }
    }
    $defaults['zgoda_formularz'] = 'Wyrażam zgodę na przetwarzanie moich danych osobowych przez organizatora egzaminu ({nazwa}) w celu przeprowadzenia egzaminu, zgodnie z RODO (Rozporządzenie PE i Rady (UE) 2016/679).';
    return $defaults;
}

function oe_setting( $key ) {
    $saved    = get_option( OE_SETTINGS_OPTION, array() );
    $defaults = oe_settings_defaults();
    if ( is_array( $saved ) && isset( $saved[ $key ] ) && $saved[ $key ] !== '' ) {
        return $saved[ $key ];
    }
    return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

// ── Derived values ────────────────────────────────────────────────────────

function oe_org_nazwa_pelna() {
    return oe_setting( 'org_nazwa_pelna' ) ?: get_bloginfo( 'name' );
}

function oe_org_nazwa_krotka() {
    return oe_setting( 'org_nazwa_krotka' ) ?: oe_org_nazwa_pelna();
}

function oe_org_nazwa_biernik() {
    return oe_setting( 'org_nazwa_biernik' ) ?: oe_org_nazwa_pelna();
}

function oe_org_kod_miasto() {
    return trim( oe_setting( 'org_kod' ) . ' ' . oe_setting( 'org_miasto' ) );
}

function oe_org_adres() {
    return implode( ', ', array_filter( array( oe_setting( 'org_ulica' ), oe_org_kod_miasto() ) ) );
}

/** "KRS ... NIP ... REGON ..." with empty entries skipped. */
function oe_org_rejestry( $sep = '  ' ) {
    $parts = array();
    foreach ( array( 'KRS' => 'org_krs', 'NIP' => 'org_nip', 'REGON' => 'org_regon' ) as $label => $key ) {
        if ( oe_setting( $key ) !== '' ) $parts[] = $label . ' ' . oe_setting( $key );
    }
    return implode( $sep, $parts );
}

function oe_bank_wlasciciel() {
    return oe_setting( 'bank_wlasciciel' ) ?: implode( ', ', array_filter( array( oe_org_nazwa_krotka(), oe_org_adres() ) ) );
}

function oe_dok_miasto() {
    return oe_setting( 'dok_miasto' ) ?: oe_setting( 'org_miasto' );
}

/** "Miasto, data" or just the date when no city is configured. */
function oe_dok_miasto_data( $data ) {
    $miasto = oe_dok_miasto();
    return $miasto !== '' ? $miasto . ', ' . $data : $data;
}

function oe_zgoda_formularz() {
    return str_replace( '{nazwa}', oe_org_nazwa_krotka(), oe_setting( 'zgoda_formularz' ) );
}

// ── Sanitization ──────────────────────────────────────────────────────────

function oe_sanitize_settings( $input ) {
    $out = array();
    if ( ! is_array( $input ) ) return $out;
    foreach ( oe_settings_fields() as $section ) {
        foreach ( $section['fields'] as $key => $f ) {
            if ( ! isset( $input[ $key ] ) ) continue;
            // options.php unslashes before calling this, so no wp_unslash here.
            $v = is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
            if ( $f[1] === 'textarea' ) {
                $out[ $key ] = sanitize_textarea_field( $v );
            } elseif ( $f[1] === 'email' ) {
                $out[ $key ] = sanitize_email( $v );
            } elseif ( $f[1] === 'url' ) {
                $out[ $key ] = esc_url_raw( trim( $v ) );
            } elseif ( $f[1] === 'number' ) {
                $v = str_replace( ',', '.', trim( $v ) );
                $out[ $key ] = is_numeric( $v ) ? (string) ( 0 + $v ) : '';
            } else {
                $out[ $key ] = sanitize_text_field( $v );
            }
        }
    }
    return $out;
}

// ── Admin page ────────────────────────────────────────────────────────────

add_action( 'admin_init', function() {
    register_setting( 'oe_settings_group', OE_SETTINGS_OPTION, array(
        'type'              => 'array',
        'sanitize_callback' => 'oe_sanitize_settings',
        'default'           => array(),
    ) );
} );

add_action( 'admin_menu', function() {
    $GLOBALS['oe_settings_hook'] = add_submenu_page(
        'edit.php?post_type=oe_egzamin',
        'Ustawienia egzaminów',
        'Ustawienia',
        'manage_options',
        'oe-ustawienia',
        'oe_render_settings_page'
    );
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( ! empty( $GLOBALS['oe_settings_hook'] ) && $hook === $GLOBALS['oe_settings_hook'] ) wp_enqueue_media();
} );

function oe_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $saved = get_option( OE_SETTINGS_OPTION, array() );
    $saved = is_array( $saved ) ? $saved : array();
    $defaults = oe_settings_defaults();
    $msg = isset( $_GET['oe_msg'] ) ? sanitize_key( $_GET['oe_msg'] ) : '';
    ?>
    <div class="wrap">
        <h1>Ustawienia egzaminów</h1>
        <?php if ( $msg === 'import_ok' ) : ?>
            <div class="notice notice-success is-dismissible"><p>Import zakończony. Typy egzaminów: <?php echo isset( $_GET['oe_rodzaje'] ) ? (int) $_GET['oe_rodzaje'] : 0; ?>, egzaminy powiązane z typem: <?php echo isset( $_GET['oe_powiazane'] ) ? (int) $_GET['oe_powiazane'] : 0; ?>.</p></div>
        <?php elseif ( $msg === 'import_blad' ) : ?>
            <div class="notice notice-error is-dismissible"><p>Nie udało się odczytać pliku. Wybierz plik JSON wyeksportowany z tej wtyczki.</p></div>
        <?php elseif ( $msg === 'uprawnienia_ok' ) : ?>
            <div class="notice notice-success is-dismissible"><p>Uprawnienia zapisane.</p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'oe_settings_group' ); ?>
            <?php foreach ( oe_settings_fields() as $section ) : ?>
                <h2><?php echo esc_html( $section['title'] ); ?></h2>
                <table class="form-table" role="presentation">
                <?php foreach ( $section['fields'] as $key => $f ) :
                    $id    = 'oe_set_' . $key;
                    $name  = OE_SETTINGS_OPTION . '[' . $key . ']';
                    $value = isset( $saved[ $key ] ) ? $saved[ $key ] : ( $f[1] === 'textarea' ? $defaults[ $key ] : '' );
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
                        <td>
                        <?php if ( $f[1] === 'textarea' ) : ?>
                            <textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
                        <?php else : ?>
                            <input type="<?php echo $f[1] === 'number' ? 'number' : ( $f[1] === 'email' ? 'email' : 'text' ); ?>"
                                   <?php echo $f[1] === 'number' ? 'step="0.01" min="0"' : ''; ?>
                                   id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                                   value="<?php echo esc_attr( $value ); ?>" class="regular-text">
                            <?php if ( $f[1] === 'url' ) : ?>
                                <button type="button" class="button oe-media-btn" data-target="<?php echo esc_attr( $id ); ?>">Wybierz z biblioteki</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( $f[2] !== '' ) : ?><p class="description"><?php echo esc_html( $f[2] ); ?></p><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </table>
            <?php endforeach; ?>
            <?php submit_button( 'Zapisz ustawienia' ); ?>
        </form>

        <hr>
        <?php oe_render_capabilities_form(); ?>

        <hr>
        <h2>Import i eksport</h2>
        <p>Eksport zapisuje powyższe ustawienia i wszystkie typy egzaminów do pliku JSON. Import nadpisuje pola obecne w pliku (pozostałe zostają bez zmian), tworzy lub aktualizuje typy egzaminów o tej samej nazwie i łączy istniejące egzaminy z typami.</p>
        <p>
            <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oe_eksport_ustawien' ), 'oe_eksport_ustawien' ) ); ?>">Eksportuj ustawienia (JSON)</a>
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="oe_import_ustawien">
            <?php wp_nonce_field( 'oe_import_ustawien' ); ?>
            <input type="file" name="oe_plik" accept=".json,application/json" required>
            <?php submit_button( 'Importuj ustawienia', 'secondary', 'submit', false ); ?>
        </form>
    </div>
    <script>
    (function(){
        document.querySelectorAll('.oe-media-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                if (!window.wp || !wp.media) return;
                var frame = wp.media({ title: 'Wybierz obraz', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    document.getElementById(btn.getAttribute('data-target')).value = a.url;
                });
                frame.open();
            });
        });
    })();
    </script>
    <?php
}

// ── Import / export ───────────────────────────────────────────────────────

add_action( 'admin_post_oe_eksport_ustawien', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Brak uprawnień.' );
    check_admin_referer( 'oe_eksport_ustawien' );
    $saved = get_option( OE_SETTINGS_OPTION, array() );
    $data  = array(
        'plugin'   => 'ocean-egzaminy',
        'version'  => OE_VERSION,
        'settings' => is_array( $saved ) ? $saved : array(),
        'rodzaje'  => array_values( array_map( 'oe_rodzaj_export', oe_rodzaje_all() ) ),
    );
    header( 'Content-Type: application/json; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="egzaminy-ustawienia-' . date( 'Y-m-d' ) . '.json"' );
    echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    exit;
} );

add_action( 'admin_post_oe_import_ustawien', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Brak uprawnień.' );
    check_admin_referer( 'oe_import_ustawien' );
    $back = admin_url( 'edit.php?post_type=oe_egzamin&page=oe-ustawienia' );

    $tmp  = isset( $_FILES['oe_plik']['tmp_name'] ) ? $_FILES['oe_plik']['tmp_name'] : '';
    $json = ( $tmp && is_uploaded_file( $tmp ) ) ? file_get_contents( $tmp ) : '';
    $data = $json ? json_decode( $json, true ) : null;
    if ( ! is_array( $data ) ) {
        wp_safe_redirect( add_query_arg( 'oe_msg', 'import_blad', $back ) );
        exit;
    }
    $wynik = oe_importuj_dane( $data );
    wp_safe_redirect( add_query_arg( array( 'oe_msg' => 'import_ok', 'oe_rodzaje' => $wynik['rodzaje'], 'oe_powiazane' => $wynik['powiazane'] ), $back ) );
    exit;
} );

/**
 * Import an exported file: settings (merged over current ones) and exam types
 * (created or updated by name), then link existing exams to types.
 * Accepts { settings, rodzaje } or a flat settings object.
 * Returns array( 'rodzaje' => imported types, 'powiazane' => exams linked ).
 */
function oe_importuj_dane( $data ) {
    $settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : ( isset( $data['rodzaje'] ) ? array() : $data );
    if ( $settings ) {
        $current = get_option( OE_SETTINGS_OPTION, array() );
        $merged  = array_merge( is_array( $current ) ? $current : array(), oe_sanitize_settings( $settings ) );
        // update_option runs the registered sanitize callback again; it is idempotent on clean values.
        update_option( OE_SETTINGS_OPTION, $merged );
    }
    $n_rodzaje = 0;
    if ( isset( $data['rodzaje'] ) && is_array( $data['rodzaje'] ) ) {
        $GLOBALS['oe_import_trwa'] = true;
        foreach ( $data['rodzaje'] as $raw ) {
            $rid = oe_rodzaj_import( $raw );
            if ( $rid ) {
                $n_rodzaje++;
                oe_rodzaj_synchronizuj_nazwe( $rid );
            }
        }
        $GLOBALS['oe_import_trwa'] = false;
        oe_rodzaje_all( true );
    }
    $migracja = oe_migruj_rodzaje_egzaminow();
    return array( 'rodzaje' => $n_rodzaje, 'powiazane' => $migracja[0] );
}

// ── Reminder when the organization profile is empty ──────────────────────

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( oe_setting( 'org_nazwa_pelna' ) !== '' ) return;
    $screen = get_current_screen();
    if ( ! $screen ) return;
    if ( ! in_array( $screen->post_type, array( 'oe_egzamin', 'oe_zapis' ), true ) && $screen->id !== 'plugins' ) return;
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'oe-ustawienia' ) return;
    $url = admin_url( 'edit.php?post_type=oe_egzamin&page=oe-ustawienia' );
    echo '<div class="notice notice-warning"><p><strong>Egzaminy:</strong> uzupełnij dane organizacji (nazwa, adres, rachunek), bo trafiają do e-maili i dokumentów. <a href="' . esc_url( $url ) . '">Przejdź do ustawień</a></p></div>';
} );
