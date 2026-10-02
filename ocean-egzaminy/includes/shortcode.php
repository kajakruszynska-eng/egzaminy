<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'formularz_egzaminu', 'oe_shortcode_formularz' );

/**
 * True when signups for the exam are closed: from its start (date and time in
 * the site's time zone; without a time, midnight of the exam day), minus the
 * "zapisy_zamkniecie_godz" setting. Exams without a date stay open.
 */
function oe_zapisy_zamkniete( $egzamin_id ) {
    $data = (string) get_post_meta( $egzamin_id, '_oe_data_egzaminu', true );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ) return false;
    $godz = (string) get_post_meta( $egzamin_id, '_oe_godzina', true );
    if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $godz ) ) $godz = '00:00';
    try {
        $start = new DateTime( $data . ' ' . $godz, wp_timezone() );
    } catch ( Exception $e ) {
        return false;
    }
    $wczesniej = (float) oe_setting( 'zapisy_zamkniecie_godz' );
    if ( $wczesniej > 0 ) {
        $start->modify( '-' . (int) round( $wczesniej * 60 ) . ' minutes' );
    }
    return time() >= $start->getTimestamp();
}

function oe_prev( $prev, $key, $default = '' ) {
    return isset($prev[$key]) ? esc_attr(sanitize_text_field($prev[$key])) : esc_attr($default);
}

function oe_shortcode_formularz( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $egzamin_id = intval( $atts['id'] );

    if ( ! $egzamin_id || get_post_type($egzamin_id) !== 'oe_egzamin' ) {
        return '<p style="color:#c62828">Błąd: nieprawidłowy identyfikator egzaminu.</p>';
    }

    $post_status = get_post_status( $egzamin_id );
    if ( $post_status !== 'publish' ) {
        return '';
    }

    $nr         = get_post_meta( $egzamin_id, '_oe_nr_egzaminu',    true );
    $data       = get_post_meta( $egzamin_id, '_oe_data_egzaminu',  true );
    $rodzaj     = get_post_meta( $egzamin_id, '_oe_rodzaj_egzaminu', true );
    $miejscowosc = get_post_meta( $egzamin_id, '_oe_miejscowosc',   true );
    $limit      = intval( get_post_meta( $egzamin_id, '_oe_limit_miejsc', true ) );
    $kwota      = get_post_meta( $egzamin_id, '_oe_kwota_oplaty', true );

    // Success/error message and form data after redirect (?oe_msg=<token>, see form-handler.php)
    $msg_sukces = '';
    $msg_blad   = '';
    $prev       = array();
    $komunikat  = oe_pobierz_komunikat( $egzamin_id );
    if ( $komunikat ) {
        if ( $komunikat['typ'] === 'sukces' ) {
            $msg_sukces = (string) $komunikat['tresc'];
        } else {
            $msg_blad = (string) $komunikat['tresc'];
        }
        if ( ! empty( $komunikat['dane'] ) && is_array( $komunikat['dane'] ) ) {
            $prev = $komunikat['dane'];
        }
    }

    if ( ! $msg_sukces && oe_zapisy_zamkniete( $egzamin_id ) ) {
        return '<div class="oe-formularz-info" style="padding:16px;background:#FFF3E0;border-left:4px solid #BA7517;border-radius:4px">'
             . '<strong>Zapisy zamknięte.</strong> Zapisy na ten egzamin zostały zakończone. Skontaktuj się z organizatorem.'
             . '</div>';
    }

    // Sprawdź limit miejsc. Checked after reading the message: the person who just
    // took the last seat must see "Zapis przyjęty", not "Brak wolnych miejsc".
    if ( $limit > 0 ) {
        $zatwierdzone = new WP_Query([
            'post_type'   => 'oe_zapis',
            'post_status' => [ 'oe_zatwierdzony', 'oe_oczekuje' ],
            'meta_query'  => [ [ 'key' => '_oe_egzamin_id', 'value' => $egzamin_id ] ],
            'fields'      => 'ids',
            'posts_per_page' => -1,
        ]);
        if ( $zatwierdzone->found_posts >= $limit && ! $msg_sukces ) {
            return '<div class="oe-formularz-info" style="padding:16px;background:#FFF3E0;border-left:4px solid #BA7517;border-radius:4px">'
                 . '<strong>Brak wolnych miejsc.</strong> Wszystkie miejsca na ten egzamin zostały zajęte. Skontaktuj się z organizatorem.'
                 . '</div>';
        }
        $wolne = max( 0, $limit - $zatwierdzone->found_posts );
    }

    $data_fmt = $data ? date_i18n('d.m.Y', strtotime($data)) : '';

    // CSS inline - niezależny od Elementora
    static $oe_form_css_dodany = false;
    $form_css = '';
    if ( ! $oe_form_css_dodany ) {
        $oe_form_css_dodany = true;
        $form_css = '<style id="oe-form-css">
.oe-formularz-wrapper{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;margin:24px 0}
.oe-egzamin-info{background:#EBF5FB;border:1px solid #AED6F1;border-radius:6px;padding:16px 20px;margin-bottom:24px}
.oe-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 20px}
.oe-info-grid>div{display:flex;flex-direction:column;gap:2px}
.oe-info-label{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#1a5276;font-weight:600}
.oe-info-grid strong{font-size:14px;color:#1c2833}
.oe-komunikat{padding:14px 18px;border-radius:6px;margin-bottom:20px;font-size:14px;line-height:1.6}
.oe-sukces{background:#E8F5E9;border:1px solid #A5D6A7;color:#1b5e20}
.oe-blad{background:#FFEBEE;border:1px solid #EF9A9A;color:#b71c1c}
.oe-sekcja-naglowek{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#1a5276;border-bottom:1px solid #AED6F1;padding-bottom:6px;margin:20px 0 14px}
.oe-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px}
@media(max-width:600px){.oe-form-grid{grid-template-columns:1fr}}
.oe-col-2{grid-column:1/-1}
.oe-form-pole{display:flex;flex-direction:column;gap:4px}
.oe-form-pole label{font-size:13px;font-weight:600;color:#2c3e50}
.oe-form-pole input[type=text],.oe-form-pole input[type=email],.oe-form-pole input[type=tel],.oe-form-pole input[type=date],.oe-form-pole input[type=number]{padding:9px 12px;border:1px solid #ccc;border-radius:5px;font-size:14px;font-family:inherit;transition:border-color .15s,box-shadow .15s;background:#fff;color:#1c2833;width:100%;box-sizing:border-box}
.oe-form-pole input:focus{outline:none;border-color:#1a5276;box-shadow:0 0 0 3px rgba(26,82,118,.12)}
.oe-wymagane{color:#c0392b}
.oe-rodo{grid-column:1/-1;margin-top:8px}
.oe-checkbox-label{display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-size:13px;line-height:1.5;color:#555}
.oe-checkbox-label input[type=checkbox]{margin-top:3px;flex-shrink:0;width:16px;height:16px;cursor:pointer}
.oe-przycisk-zapisz{display:block;width:100%;margin-top:20px;padding:13px 20px;background:#1a5276;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;transition:background .2s}
.oe-przycisk-zapisz:hover{background:#154360}
</style>';
    }

    ob_start();
    echo $form_css;
    ?>
    <div class="oe-formularz-wrapper" id="oe-formularz-<?php echo $egzamin_id; ?>">

        <div class="oe-egzamin-info">
            <div class="oe-info-grid">
                <div><span class="oe-info-label">Egzamin</span><strong><?php echo esc_html($rodzaj); ?></strong></div>
                <div><span class="oe-info-label">Nr egzaminu</span><strong><?php echo esc_html($nr); ?></strong></div>
                <div><span class="oe-info-label">Data</span><strong><?php echo esc_html($data_fmt); ?></strong></div>
                <div><span class="oe-info-label">Miejscowość</span><strong><?php echo esc_html($miejscowosc); ?></strong></div>
                <?php if ($kwota) : ?><div><span class="oe-info-label">Opłata egzaminacyjna</span><strong><?php echo esc_html($kwota); ?> PLN</strong></div><?php endif; ?>
                <?php if ($limit > 0) : ?><div><span class="oe-info-label">Wolnych miejsc</span><strong><?php echo isset($wolne) ? $wolne : '-'; ?></strong></div><?php endif; ?>
            </div>
        </div>

        <?php if ( $msg_sukces ) : ?>
        <div class="oe-komunikat oe-sukces"><?php echo wp_kses_post($msg_sukces); ?></div>
        <?php endif; ?>

        <?php if ( $msg_blad ) : ?>
        <div class="oe-komunikat oe-blad"><?php echo esc_html($msg_blad); ?></div>
        <?php endif; ?>

        <?php if ( ! $msg_sukces ) : ?>
        <form class="oe-formularz" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" novalidate>
            <?php wp_nonce_field( 'oe_zapis_' . $egzamin_id, 'oe_nonce' ); ?>
            <input type="hidden" name="action" value="oe_zapisz_uczestnika">
            <input type="hidden" name="oe_egzamin_id" value="<?php echo $egzamin_id; ?>">
            <input type="hidden" name="oe_redirect" value="<?php echo esc_url( get_permalink() . '#oe-formularz-' . $egzamin_id ); ?>">

            <p class="oe-sekcja-naglowek">Dane osobowe</p>
            <div class="oe-form-grid">
                <div class="oe-form-pole">
                    <label for="oe_imie_<?php echo $egzamin_id; ?>">Imię <span class="oe-wymagane">*</span></label>
                    <input type="text" id="oe_imie_<?php echo $egzamin_id; ?>" name="oe_imie" required autocomplete="given-name" value="<?php echo oe_prev($prev,'oe_imie'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_nazwisko_<?php echo $egzamin_id; ?>">Nazwisko <span class="oe-wymagane">*</span></label>
                    <input type="text" id="oe_nazwisko_<?php echo $egzamin_id; ?>" name="oe_nazwisko" required autocomplete="family-name" value="<?php echo oe_prev($prev,'oe_nazwisko'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_data_ur_<?php echo $egzamin_id; ?>">Data urodzenia <span class="oe-wymagane">*</span></label>
                    <input type="date" id="oe_data_ur_<?php echo $egzamin_id; ?>" name="oe_data_urodzenia" required value="<?php echo oe_prev($prev,'oe_data_urodzenia'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_miejsce_ur_<?php echo $egzamin_id; ?>">Miejsce urodzenia <span class="oe-wymagane">*</span></label>
                    <input type="text" id="oe_miejsce_ur_<?php echo $egzamin_id; ?>" name="oe_miejsce_urodzenia" required value="<?php echo oe_prev($prev,'oe_miejsce_urodzenia'); ?>">
                </div>

            </div>

            <p class="oe-sekcja-naglowek">Adres zamieszkania</p>
            <div class="oe-form-grid">
                <div class="oe-form-pole oe-col-2">
                    <label for="oe_ulica_<?php echo $egzamin_id; ?>">Ulica i numer domu/lokalu <span class="oe-wymagane">*</span></label>
                    <input type="text" id="oe_ulica_<?php echo $egzamin_id; ?>" name="oe_ulica" required placeholder="np. ul. Konwalii 20" autocomplete="street-address" value="<?php echo oe_prev($prev,'oe_ulica'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_kod_<?php echo $egzamin_id; ?>">Kod pocztowy <span class="oe-wymagane">*</span></label>
                    <input type="text" id="oe_kod_<?php echo $egzamin_id; ?>" name="oe_kod" required pattern="[0-9]{2}-[0-9]{3}" placeholder="00-000" autocomplete="postal-code" value="<?php echo oe_prev($prev,'oe_kod'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_miasto_<?php echo $egzamin_id; ?>">Miejscowość <span class="oe-wymagane">*</span></label>
                    <input type="text" id="oe_miasto_<?php echo $egzamin_id; ?>" name="oe_miasto" required autocomplete="address-level2" value="<?php echo oe_prev($prev,'oe_miasto'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_kraj_<?php echo $egzamin_id; ?>">Kraj <span style="font-size:11px;color:#777">(opcjonalnie, jeśli inny niż Polska)</span></label>
                    <input type="text" id="oe_kraj_<?php echo $egzamin_id; ?>" name="oe_kraj" placeholder="Polska" autocomplete="country-name" value="<?php echo oe_prev($prev,'oe_kraj'); ?>">
                </div>
            </div>

            <p class="oe-sekcja-naglowek">Kontakt</p>
            <div class="oe-form-grid">
                <div class="oe-form-pole">
                    <label for="oe_email_<?php echo $egzamin_id; ?>">Adres e-mail <span class="oe-wymagane">*</span></label>
                    <input type="email" id="oe_email_<?php echo $egzamin_id; ?>" name="oe_email" required autocomplete="email" value="<?php echo oe_prev($prev,'oe_email'); ?>">
                </div>
                <div class="oe-form-pole">
                    <label for="oe_telefon_<?php echo $egzamin_id; ?>">Telefon <span class="oe-wymagane">*</span></label>
                    <input type="tel" id="oe_telefon_<?php echo $egzamin_id; ?>" name="oe_telefon" required placeholder="+48 000 000 000" autocomplete="tel" value="<?php echo oe_prev($prev,'oe_telefon'); ?>">
                </div>
            </div>

            <?php if ($kwota) : ?>
            <div class="oe-form-pole oe-col-2" style="margin-top:4px">
                <label class="oe-checkbox-label oe-znizka-label" id="oe-znizka-box-<?php echo $egzamin_id; ?>">
                    <input type="checkbox" name="oe_znizka_mlodzi" value="1" <?php echo !empty($prev['oe_znizka_mlodzi']) ? 'checked' : ''; ?> id="oe-znizka-<?php echo $egzamin_id; ?>" onchange="oeToggleZnizka(this,<?php echo $egzamin_id; ?>,<?php echo floatval($kwota); ?>)">
                    <span>Mam mniej niż 26 lat i jestem uczniem lub studentem (posiadam ważną legitymację) - przysługuje mi opłata 50%: <strong id="oe-kwota-znizka-<?php echo $egzamin_id; ?>"><?php echo floatval($kwota)/2; ?> PLN</strong> zamiast <?php echo esc_html($kwota); ?> PLN</span>
                </label>
                <input type="hidden" name="oe_znizka_zaznaczona" id="oe-znizka-hidden-<?php echo $egzamin_id; ?>" value="0">
            </div>
            <?php endif; ?>

            <div class="oe-form-pole oe-rodo">
                <label class="oe-checkbox-label">
                    <input type="checkbox" name="oe_zgoda_rodo" value="1" required>
                    <span><?php echo esc_html( oe_zgoda_formularz() ); ?> <span class="oe-wymagane">*</span></span>
                </label>
            </div>

            <button type="submit" class="oe-przycisk-zapisz">Zapisuję się na egzamin</button>
        </form>
        <?php endif; ?>

    </div>
    <script>
    function oeToggleZnizka(cb, eid, kwota) {
        document.getElementById('oe-znizka-hidden-'+eid).value = cb.checked ? '1' : '0';
    }
    </script>
    <?php
    return ob_get_clean();
}


// ═══════════════════════════════════════════════════════════════════════════
// SHORTCODE: [lista_egzaminow] - lista nadchodzących egzaminów
// Parametry:
//   limit="10"          - max liczba wyników (domyślnie 10)
//   rodzaj="Sternik"    - filtruj po rodzaju (opcjonalnie)
//   pokaz_minione="nie" - czy pokazywać minione egzaminy (domyślnie nie)
// ═══════════════════════════════════════════════════════════════════════════

add_shortcode( 'lista_egzaminow', 'oe_shortcode_lista' );

function oe_shortcode_lista( $atts ) {
    $atts = shortcode_atts( [
        'limit'          => 10,
        'rodzaj'         => '',
        'pokaz_minione'  => 'nie',
    ], $atts );

    $dzisiaj = date('Y-m-d');

    $meta_query = [ 'relation' => 'AND' ];

    if ( $atts['pokaz_minione'] !== 'tak' ) {
        $meta_query[] = [
            'key'     => '_oe_data_egzaminu',
            'value'   => $dzisiaj,
            'compare' => '>=',
            'type'    => 'DATE',
        ];
    }

    if ( ! empty( $atts['rodzaj'] ) ) {
        $meta_query[] = [
            'key'     => '_oe_rodzaj_egzaminu',
            'value'   => sanitize_text_field( $atts['rodzaj'] ),
            'compare' => 'LIKE',
        ];
    }

    $egzaminy = get_posts( [
        'post_type'      => 'oe_egzamin',
        'post_status'    => 'publish',
        'posts_per_page' => intval( $atts['limit'] ),
        'meta_query'     => $meta_query,
        'meta_key'       => '_oe_data_egzaminu',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );

    if ( empty( $egzaminy ) ) {
        return '<div class="oe-lista-pusta" style="padding:20px;text-align:center;color:#777;font-family:inherit">
            Brak nadchodzących egzaminów. Zapraszamy wkrótce.
        </div>';
    }

    $miesiac_pl = [
        '01'=>'sty','02'=>'lut','03'=>'mar','04'=>'kwi',
        '05'=>'maj','06'=>'cze','07'=>'lip','08'=>'sie',
        '09'=>'wrz','10'=>'paź','11'=>'lis','12'=>'gru',
    ];

    // Wstrzyknij CSS inline - działa niezależnie od Elementora
    static $oe_lista_css_dodany = false;
    $css_inline = '';
    if ( ! $oe_lista_css_dodany ) {
        $oe_lista_css_dodany = true;
        $css_inline = '<style id="oe-lista-css">
.oe-lista-wrapper{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;margin:24px 0;display:flex;flex-direction:column;gap:10px}
.oe-egz-card{background:#fff;border:1px solid #e8e8e8;border-radius:8px;padding:16px 20px;display:flex;align-items:center;gap:16px;transition:box-shadow .15s,border-color .15s}
.oe-egz-card:hover{border-color:#AED6F1;box-shadow:0 2px 8px rgba(26,82,118,.08)}
.oe-egz-data{background:#EBF5FB;border-radius:6px;padding:8px 12px;text-align:center;min-width:54px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:1px}
.oe-egz-dzien{font-size:24px;font-weight:600;color:#1a5276;line-height:1}
.oe-egz-mies{font-size:11px;color:#1a5276;text-transform:uppercase;letter-spacing:.06em;font-weight:600}
.oe-egz-rok{font-size:10px;color:#5a8fa8}
.oe-egz-tresc{flex:1;min-width:0}
.oe-egz-tytul{font-size:15px;font-weight:600;color:#1c2833;margin-bottom:4px}
.oe-egz-meta{font-size:12px;color:#777;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.oe-sep{color:#ccc}
.oe-egz-prawa{display:flex;align-items:center;gap:10px;flex-shrink:0}
.oe-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
.oe-badge-wolne{background:#E8F5E9;color:#2e7d32}
.oe-badge-pelny{background:#FFEBEE;color:#c62828}
.oe-btn-zapisz{display:inline-block;padding:8px 16px;background:#1a5276;color:#fff!important;border-radius:5px;font-size:13px;font-weight:600;text-decoration:none!important;white-space:nowrap;transition:background .15s}
.oe-btn-zapisz:hover{background:#154360;color:#fff!important}
.oe-btn-disabled{background:#bbb!important;pointer-events:none;cursor:not-allowed}
@media(max-width:600px){.oe-egz-card{flex-wrap:wrap;gap:12px}.oe-egz-prawa{width:100%;justify-content:space-between}}
</style>';
    }

    ob_start();
    echo $css_inline;
    ?>
    <div class="oe-lista-wrapper">
    <?php foreach ( $egzaminy as $e ) :
        $eid        = $e->ID;
        $rodzaj     = get_post_meta( $eid, '_oe_rodzaj_egzaminu',  true );
        $nr         = get_post_meta( $eid, '_oe_nr_egzaminu',      true );
        $data_raw   = get_post_meta( $eid, '_oe_data_egzaminu',    true );
        $godzina    = get_post_meta( $eid, '_oe_godzina',          true );
        $miejscowosc = get_post_meta( $eid, '_oe_miejscowosc',     true );
        $kwota      = get_post_meta( $eid, '_oe_kwota_oplaty',     true );
        $limit      = intval( get_post_meta( $eid, '_oe_limit_miejsc', true ) );
        $url        = get_permalink( $eid );

        // Wolne miejsca
        $wolne = null;
        $pelny = false;
        if ( $limit > 0 ) {
            $zajete = new WP_Query( [
                'post_type'      => 'oe_zapis',
                'post_status'    => [ 'oe_oczekuje', 'oe_zatwierdzony' ],
                'meta_query'     => [ [ 'key' => '_oe_egzamin_id', 'value' => $eid ] ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ] );
            $wolne = max( 0, $limit - $zajete->found_posts );
            $pelny = ( $wolne === 0 );
        }
        $zamkniete = oe_zapisy_zamkniete( $eid );

        // Format daty
        $dzien = $mies = $rok = '';
        if ( $data_raw ) {
            $ts    = strtotime( $data_raw );
            $dzien = date('d', $ts);
            $mies  = $miesiac_pl[ date('m', $ts) ] ?? date('m', $ts);
            $rok   = date('Y', $ts);
        }
    ?>
        <div class="oe-egz-card">
            <div class="oe-egz-data">
                <span class="oe-egz-dzien"><?php echo esc_html($dzien); ?></span>
                <span class="oe-egz-mies"><?php echo esc_html($mies); ?></span>
                <span class="oe-egz-rok"><?php echo esc_html($rok); ?></span>
            </div>

            <div class="oe-egz-tresc">
                <div class="oe-egz-tytul"><?php echo esc_html($rodzaj); ?></div>
                <div class="oe-egz-meta">
                    <?php if ($nr) : ?><span><?php echo esc_html('Nr ' . $nr); ?></span><?php endif; ?>
                    <?php if ($miejscowosc || $godzina) : ?>
                        <span class="oe-sep">·</span>
                        <span><?php echo esc_html( trim( $miejscowosc . ( $godzina ? ', godz. ' . $godzina : '' ) ) ); ?></span>
                    <?php endif; ?>
                    <?php if ($kwota) : ?>
                        <span class="oe-sep">·</span>
                        <span>Opłata: <?php echo esc_html($kwota); ?> zł</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="oe-egz-prawa">
                <?php if ( $zamkniete ) : ?>
                    <span class="oe-badge oe-badge-pelny">Zapisy zamknięte</span>
                <?php elseif ( $limit > 0 ) : ?>
                    <?php if ( $pelny ) : ?>
                        <span class="oe-badge oe-badge-pelny">Brak miejsc</span>
                    <?php else : ?>
                        <span class="oe-badge oe-badge-wolne"><?php echo $wolne; ?> wolnych</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ( $url ) : ?>
                    <a href="<?php echo esc_url($url); ?>"
                       class="oe-btn-zapisz<?php echo ( $pelny || $zamkniete ) ? ' oe-btn-disabled' : ''; ?>"
                       <?php echo ( $pelny || $zamkniete ) ? 'aria-disabled="true"' : ''; ?>>
                        Zapisz się
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
