<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function oe_get_nr_decyzji_auto( $rodzaj ) {
    $mapa = [
        'Żeglarz Jachtowy'                 => 'DSW-ZKS.442.39.2022',
        'Jachtowy Sternik Morski'          => 'DSW-ZKS.442.40.2022',
        'Sternik Motorowodny'              => 'DSW-ZKS.442.41.2022',
        'Motorowodny Sternik Morski'       => 'DSW-ZKS.442.42.2022',
        'Licencja do holowania narciarza'  => 'DSW-ZKS.442.43.2022',
    ];
    foreach ( $mapa as $klucz => $nr ) {
        if ( stripos( $rodzaj, $klucz ) !== false ) return $nr;
    }
    return '';
}

function oe_wyslij_email_potwierdzenie( $zapis_id ) {
    $m = function($k) use ($zapis_id) { return get_post_meta($zapis_id, $k, true); };

    $egzamin_id  = $m('_oe_egzamin_id');
    $email       = $m('_oe_email');
    $imie        = $m('_oe_imie');
    $nazwisko    = $m('_oe_nazwisko');
    $znizka      = (int) $m('_oe_znizka_mlodzi');

    $nr_egz      = get_post_meta($egzamin_id, '_oe_nr_egzaminu',      true);
    $rodzaj      = get_post_meta($egzamin_id, '_oe_rodzaj_egzaminu',  true);
    $data_egz    = get_post_meta($egzamin_id, '_oe_data_egzaminu',    true);
    $godzina     = get_post_meta($egzamin_id, '_oe_godzina',          true);
    $miejsce     = get_post_meta($egzamin_id, '_oe_miejsce_pelne',    true);
    $kwota_raw   = floatval( get_post_meta($egzamin_id, '_oe_kwota_oplaty', true) );
    $konto       = get_post_meta($egzamin_id, '_oe_nr_konta',         true) ?: '37 1870 1045 2083 1069 7105 0001';
    $wlasciciel  = get_post_meta($egzamin_id, '_oe_wlasciciel_konta', true) ?: 'Fundacja Ocean Wiedzy, ul. Przemysłowa 10/303, 40-020 Katowice';
    $nr_decyzji  = get_post_meta($egzamin_id, '_oe_nr_decyzji',       true) ?: oe_get_nr_decyzji_auto($rodzaj);

    $data_fmt    = $data_egz ? date_i18n('d.m.Y', strtotime($data_egz)) : '';
    $kwota       = $znizka ? $kwota_raw / 2 : $kwota_raw;
    $kwota_str   = number_format($kwota, 2, ',', ' ') . ' PLN';
    $kwota_full  = number_format($kwota_raw, 2, ',', ' ') . ' PLN';
    $tytul_prz   = "Opłata za egzamin na patent {$rodzaj}: {$data_fmt}, {$imie} {$nazwisko}";
    $blik_url    = 'https://serwer1816332.home.pl/ocean-wiedzy/ocean_wiedzy/wp-content/uploads/2026/04/Blik_logo.svg-scaled.png';
    $temat       = "Potwierdzenie zapisu — egzamin {$rodzaj} {$data_fmt}";

    ob_start();
    echo oe_email_naglowek();
    ?>
<p>Cześć!</p>
<p>Twój egzamin na stopień <strong><?php echo esc_html($rodzaj); ?></strong> odbędzie się <strong><?php echo esc_html($data_fmt); ?> r. o godz. <?php echo esc_html($godzina); ?></strong>, w <strong><?php echo esc_html($miejsce); ?></strong>.</p>
<p>Jeśli chcesz przystąpić do egzaminu, to proszę:</p>

<div style="background:#EBF5FB;border:1px solid #AED6F1;border-radius:8px;padding:20px 24px;margin:20px 0">
  <p style="margin:0 0 14px;font-weight:600;color:#1a5276;font-size:15px">Przelej opłatę egzaminacyjną</p>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr>
      <td style="padding:6px 0;color:#555;width:38%;vertical-align:top">Odbiorca</td>
      <td style="padding:6px 0;font-weight:600"><?php echo esc_html($wlasciciel); ?></td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#555;vertical-align:top">Nr konta</td>
      <td style="padding:6px 0;font-family:monospace;font-size:13px;font-weight:600">PL<?php echo esc_html($konto); ?></td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#555;vertical-align:top">Kwota</td>
      <td style="padding:6px 0">
        <span style="font-size:18px;font-weight:700;color:#1a5276"><?php echo esc_html($kwota_str); ?></span>
        <?php if ($znizka) : ?>
          <span style="font-size:12px;color:#2e7d32;background:#E8F5E9;padding:2px 8px;border-radius:3px;margin-left:8px">50% — ulga uczniowska/studencka</span><br>
          <span style="font-size:12px;color:#777">cena regularna: <?php echo esc_html($kwota_full); ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#555;vertical-align:top">Tytuł przelewu</td>
      <td style="padding:6px 0;font-style:italic">&bdquo;<?php echo esc_html($tytul_prz); ?>&rdquo;</td>
    </tr>
  </table>

  <div style="margin-top:16px;padding-top:14px;border-top:1px solid #AED6F1">
    <p style="margin:0 0 10px;font-size:13px;color:#555">lub <strong>przelew BLIK na telefon:</strong></p>
    <table><tr>
      <td style="padding-right:12px"><img src="<?php echo esc_url($blik_url); ?>" alt="BLIK" style="height:28px;width:auto;display:block"></td>
      <td style="font-size:18px;font-weight:700;color:#1a5276">+48 609 133 311</td>
    </tr></table>
    <p style="margin:8px 0 0;font-size:12px;color:#777">Kwota: <strong><?php echo esc_html($kwota_str); ?></strong> &nbsp;|&nbsp; Tytuł: <?php echo esc_html($tytul_prz); ?></p>
  </div>
</div>

<?php if ($znizka) : ?>
<div style="background:#E8F5E9;border:1px solid #A5D6A7;border-radius:6px;padding:12px 16px;margin:0 0 20px;font-size:13px">
  <strong style="color:#2e7d32">Ulga 50%</strong> — przysługuje Ci obniżona opłata. <strong>Pamiętaj, żeby zabrać na egzamin ważną legitymację ucznia lub studenta.</strong>
</div>
<?php endif; ?>

<p style="font-size:14px">Na egzamin zabierz:</p>
<ul style="margin:8px 0 16px 20px;font-size:14px;line-height:1.9">
  <li>dowód tożsamości</li>
  <li>dowód dokonania przelewu (wydruk lub ekran telefonu)</li>
  <?php if ($znizka) : ?><li><strong>ważną legitymację ucznia lub studenta</strong></li><?php endif; ?>
</ul>

<p style="font-size:14px">Cena egzaminu jest ceną urzędową.</p>
<p style="font-size:14px">Odeślij nam albo weź ze sobą dowód przelewu.</p>
<p style="font-size:14px">W razie potrzeby dzwoń do nas bez skrępowania: <strong>tel. +48 660 785 002</strong></p>

<table style="width:100%;margin-top:20px;border-top:1px solid #e8e8e8"><tr><td style="padding-top:14px;font-size:14px">
  <p style="margin:0 0 2px">Pozdrawiam,</p>
  <p style="margin:0 0 2px;font-weight:600">Marek Kruszyński</p>
  <p style="margin:0;color:#777;font-size:12px">Wiceprezes Zarządu</p>
</td></tr></table>

<?php if ($nr_decyzji) : ?>
<p style="margin-top:16px;font-size:11px;color:#aaa">Decyzja MSiT: <?php echo esc_html($nr_decyzji); ?></p>
<?php endif; ?>
    <?php
    $tresc = ob_get_clean();
    $tresc .= oe_email_stopka();
    oe_wyslij_html( $email, $temat, $tresc );
}

function oe_wyslij_email_admin_nowy_zapis( $zapis_id ) {
    $m = function($k) use ($zapis_id) { return get_post_meta($zapis_id, $k, true); };

    $egzamin_id = $m('_oe_egzamin_id');
    $imie       = $m('_oe_imie');
    $nazwisko   = $m('_oe_nazwisko');
    $email      = $m('_oe_email');
    $telefon    = $m('_oe_telefon');
    $znizka     = (int) $m('_oe_znizka_mlodzi');
    $kraj       = $m('_oe_kraj') ?: 'Polska';
    $nr_egz     = get_post_meta($egzamin_id, '_oe_nr_egzaminu',     true);
    $rodzaj     = get_post_meta($egzamin_id, '_oe_rodzaj_egzaminu', true);
    $data_egz   = get_post_meta($egzamin_id, '_oe_data_egzaminu',   true);
    $kwota_raw  = floatval( get_post_meta($egzamin_id, '_oe_kwota_oplaty', true) );
    $data_fmt   = $data_egz ? date_i18n('d.m.Y', strtotime($data_egz)) : '';
    $kwota      = $znizka ? $kwota_raw / 2 : $kwota_raw;

    $admin_email = get_option('admin_email');
    $temat       = "Nowy zapis: {$imie} {$nazwisko} — {$nr_egz}";
    $url_zatw    = wp_nonce_url( admin_url("admin-post.php?action=oe_zmien_status&post_id={$zapis_id}&nowy_status=oe_zatwierdzony"), 'oe_zmien_status_' . $zapis_id );
    $url_listy   = admin_url("edit.php?post_type=oe_zapis&oe_egzamin_id={$egzamin_id}");

    $znizka_badge = $znizka ? "<span style='background:#E8F5E9;color:#2e7d32;padding:2px 8px;border-radius:3px;font-size:12px;margin-left:6px'>50% ulga</span>" : '';

    $tresc  = oe_email_naglowek();
    $tresc .= "
<h2 style='color:#1a5276;margin:0 0 16px'>Nowy zapis na egzamin</h2>
<table style='width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px'>
  <tr><td style='padding:7px 12px;background:#f4f6f9;border:1px solid #e1e4e8;width:36%;font-weight:600'>Egzamin</td>
      <td style='padding:7px 12px;border:1px solid #e1e4e8'>" . esc_html($rodzaj) . " — " . esc_html($nr_egz) . " (" . esc_html($data_fmt) . ")</td></tr>
  <tr><td style='padding:7px 12px;background:#f4f6f9;border:1px solid #e1e4e8;font-weight:600'>Uczestnik</td>
      <td style='padding:7px 12px;border:1px solid #e1e4e8'><strong>" . esc_html($imie . ' ' . $nazwisko) . "</strong></td></tr>
  <tr><td style='padding:7px 12px;background:#f4f6f9;border:1px solid #e1e4e8;font-weight:600'>E-mail</td>
      <td style='padding:7px 12px;border:1px solid #e1e4e8'>" . esc_html($email) . "</td></tr>
  <tr><td style='padding:7px 12px;background:#f4f6f9;border:1px solid #e1e4e8;font-weight:600'>Telefon</td>
      <td style='padding:7px 12px;border:1px solid #e1e4e8'>" . esc_html($telefon) . "</td></tr>
  <tr><td style='padding:7px 12px;background:#f4f6f9;border:1px solid #e1e4e8;font-weight:600'>Kraj</td>
      <td style='padding:7px 12px;border:1px solid #e1e4e8'>" . esc_html($kraj) . "</td></tr>
  <tr><td style='padding:7px 12px;background:#f4f6f9;border:1px solid #e1e4e8;font-weight:600'>Oczekiwana opłata</td>
      <td style='padding:7px 12px;border:1px solid #e1e4e8'><strong>" . number_format($kwota,2,',',' ') . " PLN</strong>{$znizka_badge}</td></tr>
</table>
<p>Po zaksięgowaniu przelewu zatwierdź uczestnika:</p>
<a href='" . esc_url($url_zatw) . "' style='background:#2e7d32;color:#fff;padding:11px 22px;border-radius:5px;text-decoration:none;font-weight:600;display:inline-block;margin-right:10px'>✓ Zatwierdź</a>
<a href='" . esc_url($url_listy) . "' style='background:#1a5276;color:#fff;padding:11px 22px;border-radius:5px;text-decoration:none;font-weight:600;display:inline-block'>→ Lista zapisów</a>
";
    $tresc .= oe_email_stopka();
    oe_wyslij_html( $admin_email, $temat, $tresc );
}

function oe_wyslij_email_zmiana_statusu( $zapis_id, $nowy_status ) {
    $m = function($k) use ($zapis_id) { return get_post_meta($zapis_id, $k, true); };

    $email      = $m('_oe_email');
    $imie       = $m('_oe_imie');
    $nazwisko   = $m('_oe_nazwisko');
    $znizka     = (int) $m('_oe_znizka_mlodzi');
    $egzamin_id = $m('_oe_egzamin_id');
    $nr_egz     = get_post_meta($egzamin_id, '_oe_nr_egzaminu',     true);
    $rodzaj     = get_post_meta($egzamin_id, '_oe_rodzaj_egzaminu', true);
    $data_egz   = get_post_meta($egzamin_id, '_oe_data_egzaminu',   true);
    $godzina    = get_post_meta($egzamin_id, '_oe_godzina',         true);
    $miejsce    = get_post_meta($egzamin_id, '_oe_miejsce_pelne',   true);
    $data_fmt   = $data_egz ? date_i18n('d.m.Y', strtotime($data_egz)) : '';

    if ( $nowy_status === 'oe_zatwierdzony' ) {
        $temat  = "Miejsce potwierdzone — {$rodzaj} {$data_fmt}";
        $tresc  = oe_email_naglowek();
        $tresc .= "
<h2 style='color:#2e7d32;margin:0 0 16px'>✓ Twoje miejsce zostało potwierdzone</h2>
<p>Drogi/a <strong>" . esc_html($imie . ' ' . $nazwisko) . "</strong>,</p>
<p>Potwierdzamy Twój udział w egzaminie.</p>
<div style='background:#E8F5E9;border:1px solid #A5D6A7;border-radius:8px;padding:20px 24px;margin:20px 0'>
  <p style='font-weight:600;color:#2e7d32;margin:0 0 12px'>Szczegóły egzaminu</p>
  <table style='width:100%;border-collapse:collapse;font-size:14px'>
    <tr><td style='padding:5px 0;color:#555;width:36%'>Egzamin</td><td style='padding:5px 0;font-weight:600'>" . esc_html($rodzaj) . "</td></tr>
    <tr><td style='padding:5px 0;color:#555'>Nr egzaminu</td><td style='padding:5px 0'>" . esc_html($nr_egz) . "</td></tr>
    <tr><td style='padding:5px 0;color:#555'>Data i godzina</td><td style='padding:5px 0;font-weight:600'>" . esc_html($data_fmt) . " r., godz. " . esc_html($godzina) . "</td></tr>
    <tr><td style='padding:5px 0;color:#555'>Miejsce</td><td style='padding:5px 0'>" . esc_html($miejsce) . "</td></tr>
  </table>
</div>
<ul style='font-size:14px;line-height:1.9;margin:0 0 16px 20px'>
  <li>Prosimy o przybycie co najmniej <strong>15 minut przed wyznaczoną godziną</strong></li>
  <li>Zabierz <strong>dowód tożsamości</strong></li>" .
  ($znizka ? "<li>Zabierz <strong>ważną legitymację ucznia lub studenta</strong></li>" : "") . "
</ul>
<p>W razie pytań: <strong>tel. +48 660 785 002</strong></p>";
        $tresc .= oe_email_stopka();

    } elseif ( $nowy_status === 'oe_odrzucony' ) {
        $temat  = "Informacja o zapisie — {$rodzaj} {$data_fmt}";
        $tresc  = oe_email_naglowek();
        $tresc .= "
<h2 style='color:#c62828;margin:0 0 16px'>Informacja o Twoim zapisie</h2>
<p>Drogi/a <strong>" . esc_html($imie . ' ' . $nazwisko) . "</strong>,</p>
<p>Z przykrością informujemy, że Twój zapis na egzamin <strong>" . esc_html($rodzaj) . "</strong> (" . esc_html($data_fmt) . ") nie mógł zostać zatwierdzony.</p>
<p>W celu wyjaśnienia prosimy o kontakt: <strong>tel. +48 660 785 002</strong></p>";
        $tresc .= oe_email_stopka();
    } else {
        return;
    }
    oe_wyslij_html( $email, $temat, $tresc );
}

function oe_email_naglowek() {
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#2c3e50'>
<div style='max-width:620px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e1e4e8'>
<div style='background:#1a5276;padding:20px 28px'>
  <p style='color:#fff;margin:0;font-size:19px;font-weight:700'>Fundacja Ocean Wiedzy</p>
  <p style='color:#AED6F1;margin:4px 0 0;font-size:12px'>Egzaminy żeglarskie i motorowodne</p>
</div>
<div style='padding:28px 32px'>
";
}

function oe_email_stopka() {
    $blik = 'https://serwer1816332.home.pl/ocean-wiedzy/ocean_wiedzy/wp-content/uploads/2026/04/Blik_logo.svg-scaled.png';
    $url  = home_url('/');
    $name = get_bloginfo('name');
    return "
</div>
<div style='background:#f4f6f9;padding:18px 32px;border-top:1px solid #e1e4e8;font-size:12px;color:#777'>
  <p style='margin:0 0 4px;font-weight:600;color:#555'>Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy</p>
  <p style='margin:0 0 4px'>ul. Przemysłowa 10/303, 40-020 Katowice &nbsp;|&nbsp; KRS 0000881696 &nbsp;|&nbsp; NIP 6342991899 &nbsp;|&nbsp; REGON 388227941</p>
  <p style='margin:0 0 6px'>Tel.: +48 660 785 002 &nbsp;|&nbsp; <a href='{$url}' style='color:#1a5276'>{$name}</a></p>
  <table><tr>
    <td style='padding-right:6px;color:#555'>Konto Nest Bank: <strong>PL37 1870 1045 2083 1069 7105 0001</strong></td>
  </tr><tr>
    <td style='padding-top:6px'>
      <table><tr>
        <td style='padding-right:8px'><img src='{$blik}' alt='BLIK' style='height:20px;width:auto;vertical-align:middle'></td>
        <td style='font-weight:600;color:#555'>+48 609 133 311</td>
      </tr></table>
    </td>
  </tr></table>
</div>
</div></body></html>";
}

function oe_wyslij_html( $do, $temat, $tresc ) {
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        'Reply-To: biuro@ocean-wiedzy.pl',
    ];
    wp_mail( $do, $temat, $tresc, $headers );
}
