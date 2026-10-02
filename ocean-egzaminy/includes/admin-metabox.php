<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'oe_egzamin_dane',
        'Dane egzaminu',
        'oe_metabox_egzamin',
        'oe_egzamin',
        'normal',
        'high'
    );
    add_meta_box(
        'oe_egzamin_shortcode',
        'Shortcode formularza',
        'oe_metabox_shortcode',
        'oe_egzamin',
        'side',
        'high'
    );
    add_meta_box(
        'oe_zapis_dane',
        'Dane uczestnika',
        'oe_metabox_zapis',
        'oe_zapis',
        'normal',
        'high'
    );
} );

// ── Metabox: dane egzaminu ────────────────────────────────────────────────

function oe_metabox_egzamin( $post ) {
    wp_nonce_field( 'oe_save_egzamin', 'oe_egzamin_nonce' );

    $m = function( $key ) use ( $post ) {
        return esc_attr( get_post_meta( $post->ID, $key, true ) );
    };

    $rodzaje = [
        'Sternik Motorowodny'                                         => 'Sternik Motorowodny',
        'Żeglarz Jachtowy'                                            => 'Żeglarz Jachtowy',
        'Jachtowy Sternik Morski'                                     => 'Jachtowy Sternik Morski',
        'Motorowodny Sternik Morski'                                  => 'Motorowodny Sternik Morski',
        'Licencja do holowania narciarza wodnego lub innych obiektów' => 'Licencja do holowania narciarza wodnego lub innych obiektów',
    ];

    $komisja_raw = get_post_meta( $post->ID, '_oe_komisja', true );
    $komisja = is_array( $komisja_raw ) ? $komisja_raw : [
        [ 'rola' => 'przewodniczący', 'imie' => '' ],
        [ 'rola' => 'sekretarz',      'imie' => '' ],
    ];

    ?>
    <style>
    .oe-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
    .oe-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px 16px; }
    .oe-field { display:flex; flex-direction:column; gap:4px; }
    .oe-field label { font-size:12px; font-weight:600; color:#555; text-transform:uppercase; letter-spacing:.03em; }
    .oe-field input, .oe-field select, .oe-field textarea { width:100%; padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:14px; }
    .oe-section { margin:16px 0 8px; font-size:13px; font-weight:600; color:#1e1e1e; border-bottom:1px solid #eee; padding-bottom:6px; }
    .oe-komisja-row { display:grid; grid-template-columns:160px 1fr 32px; gap:8px; align-items:center; margin-bottom:8px; }
    .oe-komisja-row select, .oe-komisja-row input { width:100%; }
    .oe-remove-btn { background:#fff; border:1px solid #ddd; border-radius:4px; cursor:pointer; color:#c62828; font-size:16px; line-height:1; padding:2px 6px; }
    </style>

    <p class="oe-section">Identyfikacja</p>
    <div class="oe-grid">
        <div class="oe-field">
            <label>Rodzaj egzaminu</label>
            <select name="oe_rodzaj_egzaminu" id="oe-rodzaj-select" onchange="oeAutoDecyzja(this.value)">
                <?php foreach ( $rodzaje as $val => $label ) : ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected( $m('_oe_rodzaj_egzaminu'), $val ); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="oe-field">
            <label>Nr egzaminu (w rejestrze)</label>
            <input type="text" name="oe_nr_egzaminu" value="<?php echo $m('_oe_nr_egzaminu'); ?>" placeholder="np. SM/001/JM/2026">
        </div>

        <div class="oe-field">
            <label>Nr decyzji MSiT</label>
            <?php
            $decyzje_mapa = [
                'Sternik Motorowodny'                                         => 'DSW-ZKS.442.41.2022',
                'Żeglarz Jachtowy'                                            => 'DSW-ZKS.442.39.2022',
                'Jachtowy Sternik Morski'                                     => 'DSW-ZKS.442.40.2022',
                'Motorowodny Sternik Morski'                                  => 'DSW-ZKS.442.42.2022',
                'Licencja do holowania narciarza wodnego lub innych obiektów' => 'DSW-ZKS.442.43.2022',
            ];
            $wybrany_rodzaj   = get_post_meta( $post->ID, '_oe_rodzaj_egzaminu', true );
            // Przy nowym wpisie meta jeszcze nie istnieje — użyj pierwszego klucza z $rodzaje
            if ( ! $wybrany_rodzaj ) {
                $wybrany_rodzaj = array_key_first( $rodzaje );
            }
            $zapisana_decyzja = get_post_meta( $post->ID, '_oe_nr_decyzji', true );
            $auto_decyzja     = $decyzje_mapa[ $wybrany_rodzaj ] ?? '';
            $val_decyzji      = $zapisana_decyzja ?: $auto_decyzja;
            ?>
            <input type="text" name="oe_nr_decyzji"
                   value="<?php echo esc_attr( $val_decyzji ); ?>"
                   style="background:#eafaf1;color:#1a5276;font-weight:600">
            <span style="font-size:11px;color:#2e7d32">✓ Uzupełniane automatycznie po zapisaniu. Możesz nadpisać ręcznie.</span>
        </div>
        <div class="oe-field">
            <label>Limit miejsc</label>
            <input type="number" name="oe_limit_miejsc" value="<?php echo $m('_oe_limit_miejsc'); ?>" min="1" max="100" placeholder="np. 15">
        </div>
    </div>

    <p class="oe-section">Termin i miejsce</p>
    <div class="oe-grid-3">
        <div class="oe-field">
            <label>Data egzaminu</label>
            <input type="date" name="oe_data_egzaminu" value="<?php echo $m('_oe_data_egzaminu'); ?>">
        </div>
        <div class="oe-field">
            <label>Godzina</label>
            <input type="time" name="oe_godzina" value="<?php echo $m('_oe_godzina'); ?>">
        </div>
        <div class="oe-field">
            <label>Miejscowość</label>
            <input type="text" name="oe_miejscowosc" value="<?php echo $m('_oe_miejscowosc'); ?>" placeholder="np. Koszalin">
        </div>

    </div>

    <p class="oe-section">Miejsca egzaminów (do zgłoszenia)</p>
    <?php
    $saved_teoria   = get_post_meta($post->ID, '_oe_miejsce_teoria', true);
    $saved_praktyka = get_post_meta($post->ID, '_oe_miejsce_praktyka', true);
    ?>
    <style>
    .oe-miejsce-wrap { position:relative; }
    .oe-miejsce-wrap input[type=text] { width:100%; box-sizing:border-box; }
    </style>
    <div class="oe-grid">
        <div class="oe-field" style="grid-column:1/-1">
            <label>Miejsce teorii</label>
            <div class="oe-miejsce-wrap">
                <input type="text" name="oe_miejsce_teoria" id="oe-miejsce-teoria"
                    value="<?php echo esc_attr($saved_teoria); ?>"
                    placeholder="Zacznij pisać aby wyszukać..."
                    autocomplete="off" list="oe-datalist-teoria">
                <datalist id="oe-datalist-teoria"></datalist>
            </div>
        </div>
        <div class="oe-field" style="grid-column:1/-1">
            <label>Miejsce praktyki</label>
            <div class="oe-miejsce-wrap">
                <input type="text" name="oe_miejsce_praktyka" id="oe-miejsce-praktyka"
                    value="<?php echo esc_attr($saved_praktyka); ?>"
                    placeholder="Zacznij pisać aby wyszukać..."
                    autocomplete="off" list="oe-datalist-praktyka">
                <datalist id="oe-datalist-praktyka"></datalist>
            </div>
        </div>
    </div>

    <p class="oe-section">Skład komisji egzaminacyjnej</p>
    <div id="oe-komisja-wrapper">
        <?php foreach ( $komisja as $i => $czl ) : ?>
        <div class="oe-komisja-row">
            <select name="oe_komisja[<?php echo $i; ?>][rola]">
                <?php
                $role = [ 'przewodniczący', 'sekretarz', 'członek' ];
                foreach ( $role as $r ) {
                    echo '<option value="' . esc_attr($r) . '"' . selected($czl['rola'], $r, false) . '>' . esc_html(ucfirst($r)) . '</option>';
                }
                ?>
            </select>
            <input type="text" name="oe_komisja[<?php echo $i; ?>][imie]"
                   value="<?php echo esc_attr($czl['imie']); ?>"
                   placeholder="Imię i nazwisko">
            <?php if ( $i >= 2 ) : ?>
            <button type="button" class="oe-remove-btn" onclick="this.closest('.oe-komisja-row').remove()">×</button>
            <?php else : ?>
            <span></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" id="oe-dodaj-czlonka" class="button" style="margin-top:6px">+ Dodaj członka komisji</button>

    <p class="oe-section">Dane do przelewu (pojawią się w e-mailu potwierdzającym)</p>
    <div class="oe-grid">
        <div class="oe-field">
            <label>Nr rachunku bankowego</label>
            <input type="text" name="oe_nr_konta" id="oe-nr-konta"
                   value="<?php echo $m('_oe_nr_konta') ?: '37 1870 1045 2083 1069 7105 0001'; ?>">
            <span style="font-size:11px;color:#888">Nest Bank — zmień jeśli inny rachunek</span>
        </div>
        <div class="oe-field">
            <label>Kwota opłaty (PLN)</label>
            <input type="number" name="oe_kwota_oplaty" id="oe-kwota-oplaty"
                   value="<?php echo $m('_oe_kwota_oplaty') ?: '250'; ?>" min="0" step="1">
        </div>
        <div class="oe-field" style="grid-column:1/-1">
            <label>Właściciel konta</label>
            <input type="text" name="oe_wlasciciel_konta"
                   value="<?php echo $m('_oe_wlasciciel_konta') ?: 'Fundacja Ocean Wiedzy, ul. Przemysłowa 10/303, 40-020 Katowice'; ?>">
        </div>
    </div>

    <script>
    (function(){
        var decyzje = {
            'Sternik Motorowodny':             'DSW-ZKS.442.41.2022',
            'Żeglarz Jachtowy':                'DSW-ZKS.442.39.2022',
            'Jachtowy Sternik Morski':         'DSW-ZKS.442.40.2022',
            'Motorowodny Sternik Morski':      'DSW-ZKS.442.42.2022',
            'Licencja do holowania narciarza wodnego lub innych obiektów': 'DSW-ZKS.442.43.2022'
        };
        var oeKomisjaIdx = <?php echo count($komisja); ?>;

        function oeInitMetabox() {
            // Nr decyzji auto-fill
            var selRodzaj  = document.querySelector('select[name="oe_rodzaj_egzaminu"]');
            var inpDecyzja = document.querySelector('input[name="oe_nr_decyzji"]');
            if (selRodzaj && inpDecyzja) {
                selRodzaj.addEventListener('change', function() {
                    var nr = decyzje[this.value] || '';
                    inpDecyzja.value = nr;
                    inpDecyzja.style.background = nr ? '#eafaf1' : '#fff';
                });
            }

            // Dodawanie członka komisji
            var btnDodaj = document.getElementById('oe-dodaj-czlonka');
            var wrapper  = document.getElementById('oe-komisja-wrapper');
            if (btnDodaj && wrapper) {
                btnDodaj.addEventListener('click', function() {
                    var div = document.createElement('div');
                    div.className = 'oe-komisja-row';
                    var sel = document.createElement('select');
                    sel.name = 'oe_komisja[' + oeKomisjaIdx + '][rola]';
                    sel.innerHTML = '<option value="przewodniczący">Przewodniczący</option>'
                        + '<option value="sekretarz">Sekretarz</option>'
                        + '<option value="członek" selected>Członek</option>';
                    var inp = document.createElement('input');
                    inp.type = 'text';
                    inp.name = 'oe_komisja[' + oeKomisjaIdx + '][imie]';
                    inp.placeholder = 'Imię i nazwisko';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'oe-remove-btn';
                    btn.textContent = '×';
                    btn.addEventListener('click', function() {
                        div.parentNode.removeChild(div);
                    });
                    div.appendChild(sel);
                    div.appendChild(inp);
                    div.appendChild(btn);
                    wrapper.appendChild(div);
                    oeKomisjaIdx++;
                });
            }
        }

        // Dane miejsc dla każdego rodzaju egzaminu
        var oeMiejsca = <?php
            $miejsca_json = array();
            if (function_exists('oe_get_miejsca')) {
                foreach (oe_get_miejsca() as $rodzaj => $typy) {
                    $miejsca_json[$rodzaj] = $typy;
                }
            }
            echo json_encode($miejsca_json, JSON_UNESCAPED_UNICODE);
        ?>;

        function oeOdswiezMiejsca(rodzaj) {
            var dlT = document.getElementById('oe-datalist-teoria');
            var dlP = document.getElementById('oe-datalist-praktyka');
            if (!dlT || !dlP) return;

            var miejsca = oeMiejsca[rodzaj] || {teoria: [], praktyka: []};

            function fillDatalist(dl, lista) {
                dl.innerHTML = '';
                for (var i=0; i<lista.length; i++) {
                    var opt = document.createElement('option');
                    opt.value = lista[i];
                    dl.appendChild(opt);
                }
            }

            fillDatalist(dlT, miejsca.teoria   || []);
            fillDatalist(dlP, miejsca.praktyka || []);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', oeInitMetabox);
        } else {
            oeInitMetabox();
        }

        // Odśwież miejsca po zmianie rodzaju
        document.addEventListener('DOMContentLoaded', function() {
            var selRodzaj = document.querySelector('select[name="oe_rodzaj_egzaminu"]');
            if (selRodzaj) {
                oeOdswiezMiejsca(selRodzaj.value);
                selRodzaj.addEventListener('change', function() {
                    oeOdswiezMiejsca(this.value);
                });
            }
        });
    })();
    </script>
    <?php
}

// ── Metabox: shortcode ────────────────────────────────────────────────────

function oe_metabox_shortcode( $post ) {
    $zapisany = $post->ID && ! in_array( $post->post_status, array( 'auto-draft' ), true );
    ?>
    <p style="font-size:13px;color:#555;margin-bottom:8px">Wklej ten shortcode w treść wpisu, aby wyświetlić formularz zapisów:</p>
    <?php if ( $zapisany ) :
        $sc = '[formularz_egzaminu id="' . intval( $post->ID ) . '"]';
        ?>
    <code id="oe-sc-text" style="display:block;background:#f0f0f0;padding:8px;border-radius:4px;font-size:13px;word-break:break-all"><?php echo esc_html( $sc ); ?></code>
    <button type="button" class="button" style="margin-top:8px;width:100%" onclick="var b=this;var t=document.getElementById('oe-sc-text').textContent.trim();var ok=function(){b.textContent='Skopiowano!';setTimeout(function(){b.textContent='Kopiuj'},2000)};if(navigator.clipboard){navigator.clipboard.writeText(t).then(ok)}else{var r=document.createRange();r.selectNode(document.getElementById('oe-sc-text'));var sel=window.getSelection();sel.removeAllRanges();sel.addRange(r);document.execCommand('copy');ok()}">Kopiuj</button>
    <?php else : ?>
    <p style="color:#ba7517;font-size:12px">Najpierw zapisz egzamin, aby otrzymać shortcode.</p>
    <?php endif; ?>
    <?php
}

// ── Metabox: dane zapisu (podgląd) ────────────────────────────────────────

function oe_metabox_zapis( $post ) {
    $m = function( $key ) use ( $post ) {
        return esc_html( get_post_meta( $post->ID, $key, true ) );
    };

    $eid  = get_post_meta( $post->ID, '_oe_egzamin_id', true );
    $nr   = $eid ? get_post_meta( $eid, '_oe_nr_egzaminu', true ) : '—';
    $status = get_post_field('post_status', $post->ID);
    $statusy = oe_get_statusy();
    $status_label = isset($statusy[$status]) ? $statusy[$status]['label'] : $status;
    $status_color = isset($statusy[$status]) ? $statusy[$status]['color'] : '#555';

    echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
    $rows = [
        'Egzamin'           => $nr,
        'Imię i nazwisko'   => $m('_oe_imie') . ' ' . $m('_oe_nazwisko'),
        'Data urodzenia'    => $m('_oe_data_urodzenia') . ' r., ' . $m('_oe_miejsce_urodzenia'),
        'Adres'             => $m('_oe_ulica') . ', ' . $m('_oe_kod') . ' ' . $m('_oe_miasto'),
        'E-mail'            => $m('_oe_email'),
        'Telefon'           => $m('_oe_telefon'),
        'Status'            => "<span style='color:{$status_color};font-weight:600'>{$status_label}</span>",
        'Data zapisu'       => get_the_date('d.m.Y H:i', $post->ID),
    ];
    foreach ( $rows as $label => $val ) {
        echo "<tr><td style='padding:5px 8px;background:#f9f9f9;width:35%;border:1px solid #eee;font-weight:600;color:#555'>"
             . esc_html($label) . "</td><td style='padding:5px 8px;border:1px solid #eee'>" . $val . "</td></tr>";
    }
    echo '</table>';

    // Przyciski zmiany statusu
    if ( $status === 'oe_oczekuje' ) {
        echo '<div style="margin-top:12px;display:flex;gap:8px">';
        $url_z = wp_nonce_url( admin_url("admin-post.php?action=oe_zmien_status&post_id={$post->ID}&nowy_status=oe_zatwierdzony"), 'oe_zmien_status_' . $post->ID );
        $url_o = wp_nonce_url( admin_url("admin-post.php?action=oe_zmien_status&post_id={$post->ID}&nowy_status=oe_odrzucony"), 'oe_zmien_status_' . $post->ID );
        echo "<a href='" . esc_url($url_z) . "' class='button button-primary' style='background:#2e7d32;border-color:#2e7d32'>✓ Zatwierdź</a>";
        echo "<a href='" . esc_url($url_o) . "' class='button' style='color:#c62828;border-color:#c62828'>✗ Odrzuć</a>";
        echo '</div>';
    }
}

// ── Zapis meta pól egzaminu ───────────────────────────────────────────────

add_action( 'save_post_oe_egzamin', function( $post_id ) {
    if ( ! isset($_POST['oe_egzamin_nonce']) ) return;
    if ( ! wp_verify_nonce( $_POST['oe_egzamin_nonce'], 'oe_save_egzamin' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $decyzje_auto = [
        'Sternik Motorowodny'                                         => 'DSW-ZKS.442.41.2022',
        'Żeglarz Jachtowy'                                            => 'DSW-ZKS.442.39.2022',
        'Jachtowy Sternik Morski'                                     => 'DSW-ZKS.442.40.2022',
        'Motorowodny Sternik Morski'                                  => 'DSW-ZKS.442.42.2022',
        'Licencja do holowania narciarza wodnego lub innych obiektów' => 'DSW-ZKS.442.43.2022',
    ];
    $rodzaj_zapisywany = sanitize_text_field( $_POST['oe_rodzaj_egzaminu'] ?? '' );
    $decyzja_reczna    = sanitize_text_field( $_POST['oe_nr_decyzji'] ?? '' );
    $decyzja_auto      = $decyzje_auto[ $rodzaj_zapisywany ] ?? '';
    // Jeśli pole decyzji puste lub jest auto-wartością — zawsze wpisz właściwą dla wybranego rodzaju
    if ( ! $decyzja_reczna || in_array( $decyzja_reczna, array_values($decyzje_auto) ) ) {
        update_post_meta( $post_id, '_oe_nr_decyzji', $decyzja_auto );
    } else {
        update_post_meta( $post_id, '_oe_nr_decyzji', $decyzja_reczna );
    }

    $pola_tekstowe = [
        'oe_rodzaj_egzaminu', 'oe_nr_egzaminu', 'oe_miejsce_teoria', 'oe_miejsce_praktyka',
        'oe_data_egzaminu', 'oe_godzina', 'oe_miejscowosc',
        'oe_nr_konta', 'oe_kwota_oplaty', 'oe_wlasciciel_konta',
    ];
    foreach ( $pola_tekstowe as $pole ) {
        if ( isset($_POST[$pole]) ) {
            update_post_meta( $post_id, '_' . $pole, sanitize_text_field( $_POST[$pole] ) );
        }
    }

    $limit = isset($_POST['oe_limit_miejsc']) ? intval($_POST['oe_limit_miejsc']) : 0;
    update_post_meta( $post_id, '_oe_limit_miejsc', $limit );

    if ( isset($_POST['oe_komisja']) && is_array($_POST['oe_komisja']) ) {
        $komisja = [];
        foreach ( $_POST['oe_komisja'] as $czl ) {
            $rola = sanitize_text_field( $czl['rola'] ?? '' );
            $imie = sanitize_text_field( $czl['imie'] ?? '' );
            if ( $imie ) {
                $komisja[] = compact('rola', 'imie');
            }
        }
        update_post_meta( $post_id, '_oe_komisja', $komisja );
    }
} );
