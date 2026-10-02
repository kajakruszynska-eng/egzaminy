<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Eksport CSV z listy zapisów
add_action( 'admin_post_oe_eksport_csv', 'oe_eksport_csv' );

function oe_eksport_csv() {
    if ( ! current_user_can('oe_manage_exams') ) wp_die('Brak uprawnień.');
    check_admin_referer('oe_eksport_csv');

    $egzamin_id = isset($_GET['egzamin_id']) ? intval($_GET['egzamin_id']) : 0;
    $status_filtr = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'oe_zatwierdzony';

    $nr_egz = $egzamin_id
        ? get_post_meta($egzamin_id, '_oe_nr_egzaminu', true)
        : 'wszystkie';
    $nr_egz_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nr_egz);

    $meta_query = $egzamin_id
        ? [ [ 'key' => '_oe_egzamin_id', 'value' => $egzamin_id ] ]
        : [];

    $zapisy = get_posts([
        'post_type'      => 'oe_zapis',
        'post_status'    => [ $status_filtr ],
        'meta_query'     => $meta_query,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);

    $filename = "egzamin_{$nr_egz_safe}_zapisy_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM dla Excela

    // Nagłówek
    fputcsv($out, [
        'Lp.',
        'Imię',
        'Nazwisko',
        'Data urodzenia',
        'Miejsce urodzenia',
        'Ulica i numer',
        'Kod pocztowy',
        'Miejscowość',
        'E-mail',
        'Telefon',
        'Nr egzaminu',
        'Status',
        'Data zapisu',
    ], ';');

    $statusy = oe_get_statusy();
    $lp = 1;

    foreach ( $zapisy as $z ) {
        $m = function($key) use ($z) {
            return get_post_meta($z->ID, $key, true);
        };
        $eid    = $m('_oe_egzamin_id');
        $nr     = $eid ? get_post_meta($eid, '_oe_nr_egzaminu', true) : '';
        $st     = get_post_field('post_status', $z->ID);
        $st_lab = isset($statusy[$st]) ? $statusy[$st]['label'] : $st;

        fputcsv($out, [
            $lp++,
            $m('_oe_imie'),
            $m('_oe_nazwisko'),
            $m('_oe_data_urodzenia'),
            $m('_oe_miejsce_urodzenia'),
            $m('_oe_ulica'),
            $m('_oe_kod'),
            $m('_oe_miasto'),
            $m('_oe_email'),
            $m('_oe_telefon'),
            $nr,
            $st_lab,
            get_the_date('d.m.Y H:i', $z->ID),
        ], ';');
    }

    fclose($out);
    exit;
}

// Przycisk eksportu nad listą zapisów
add_action( 'manage_posts_extra_tablenav', function($which) {
    if ( get_current_screen()->post_type !== 'oe_zapis' || $which !== 'top' ) return;

    $egzamin_id = isset($_GET['oe_egzamin_id']) ? intval($_GET['oe_egzamin_id']) : 0;

    $url_zatw = wp_nonce_url(
        admin_url('admin-post.php?action=oe_eksport_csv&status=oe_zatwierdzony&egzamin_id=' . $egzamin_id),
        'oe_eksport_csv'
    );
    $url_wszyst = wp_nonce_url(
        admin_url('admin-post.php?action=oe_eksport_csv&status=oe_oczekuje&egzamin_id=' . $egzamin_id),
        'oe_eksport_csv'
    );

    echo '<div class="alignleft actions" style="display:flex;gap:6px;align-items:center">';
    echo '<a href="' . esc_url($url_zatw) . '" class="button" style="background:#1a5276;color:#fff;border-color:#1a5276">↓ Eksport zatwierdzonych (CSV)</a>';
    echo '<a href="' . esc_url($url_wszyst) . '" class="button">↓ Eksport oczekujących (CSV)</a>';
    echo '</div>';
} );
