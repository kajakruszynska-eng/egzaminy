<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Kolumny listy: Egzaminy ───────────────────────────────────────────────

add_filter( 'manage_oe_egzamin_posts_columns', function( $cols ) {
    unset( $cols['date'] );
    return array_merge( $cols, [
        'nr_egzaminu'  => 'Nr egzaminu',
        'data_egz'     => 'Data egzaminu',
        'miejscowosc'  => 'Miejscowość',
        'rodzaj'       => 'Rodzaj',
        'zapisow'      => 'Zapisów',
    ] );
} );

add_action( 'manage_oe_egzamin_posts_custom_column', function( $col, $post_id ) {
    switch ( $col ) {
        case 'nr_egzaminu':
            echo esc_html( get_post_meta( $post_id, '_oe_nr_egzaminu', true ) );
            break;
        case 'data_egz':
            $d = get_post_meta( $post_id, '_oe_data_egzaminu', true );
            echo $d ? esc_html( date_i18n( 'd.m.Y', strtotime( $d ) ) ) : '-';
            break;
        case 'miejscowosc':
            echo esc_html( get_post_meta( $post_id, '_oe_miejscowosc', true ) );
            break;
        case 'rodzaj':
            echo esc_html( get_post_meta( $post_id, '_oe_rodzaj_egzaminu', true ) );
            break;
        case 'zapisow':
            $q = new WP_Query( [
                'post_type'   => 'oe_zapis',
                'post_status' => array_keys( oe_get_statusy() ),
                'meta_query'  => [ [ 'key' => '_oe_egzamin_id', 'value' => $post_id ] ],
                'fields'      => 'ids',
                'posts_per_page' => -1,
            ] );
            $statusy  = oe_get_statusy();
            $zatw     = new WP_Query( [
                'post_type'   => 'oe_zapis',
                'post_status' => [ 'oe_zatwierdzony' ],
                'meta_query'  => [ [ 'key' => '_oe_egzamin_id', 'value' => $post_id ] ],
                'fields'      => 'ids',
                'posts_per_page' => -1,
            ] );
            $total = $q->found_posts;
            $zatw_n = $zatw->found_posts;
            echo "<strong>{$total}</strong> (<span style='color:#2e7d32'>{$zatw_n} zatw.</span>)";
            // Link do zapisów
            $url = admin_url( 'edit.php?post_type=oe_zapis&oe_egzamin_id=' . $post_id );
            echo " <a href='" . esc_url( $url ) . "' style='font-size:11px'>→ lista</a>";
            break;
    }
}, 10, 2 );

// ── Sortowanie listy egzaminów po dacie egzaminu ──────────────────────────

add_filter( 'manage_edit-oe_egzamin_sortable_columns', function( $cols ) {
    $cols['data_egz'] = 'data_egz';
    return $cols;
} );

// Default order: exam date, newest first. Exams without a date are kept (named
// meta_query clauses with EXISTS / NOT EXISTS instead of meta_key, which would hide them).
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'oe_egzamin' ) return;
    $orderby = $query->get( 'orderby' );
    if ( $orderby && $orderby !== 'data_egz' ) return;
    $query->set( 'meta_query', array(
        'relation'  => 'OR',
        'data_egz'  => array( 'key' => '_oe_data_egzaminu', 'compare' => 'EXISTS' ),
        'brak_daty' => array( 'key' => '_oe_data_egzaminu', 'compare' => 'NOT EXISTS' ),
    ) );
    $order = strtoupper( (string) $query->get( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
    $query->set( 'orderby', array( 'data_egz' => $order, 'date' => $order ) );
} );

// ── Kolumny listy: Zapisy ─────────────────────────────────────────────────

add_filter( 'manage_oe_zapis_posts_columns', function( $cols ) {
    unset( $cols['date'], $cols['title'] );
    return [
        'cb'           => $cols['cb'],
        'uczestnik'    => 'Uczestnik',
        'egzamin'      => 'Nr egzaminu',
        'data_ur'      => 'Data ur.',
        'adres'        => 'Adres',
        'kontakt'      => 'Kontakt',
        'status'       => 'Status',
        'data_zapisu'  => 'Data zapisu',
        'akcja'        => 'Akcja',
    ];
} );

add_action( 'manage_oe_zapis_posts_custom_column', function( $col, $post_id ) {
    $m = function( $key ) use ( $post_id ) {
        return get_post_meta( $post_id, $key, true );
    };
    $statusy = oe_get_statusy();
    $status  = get_post_field( 'post_status', $post_id );
    $nonce   = wp_create_nonce( 'oe_zmien_status_' . $post_id );

    switch ( $col ) {
        case 'uczestnik':
            echo '<strong>' . esc_html( $m('_oe_imie') . ' ' . $m('_oe_nazwisko') ) . '</strong>';
            break;
        case 'egzamin':
            $eid = $m('_oe_egzamin_id');
            $nr  = $eid ? get_post_meta( $eid, '_oe_nr_egzaminu', true ) : '-';
            echo esc_html( $nr );
            break;
        case 'data_ur':
            echo esc_html( $m('_oe_data_urodzenia') . ', ' . $m('_oe_miejsce_urodzenia') );
            break;
        case 'adres':
            echo esc_html( $m('_oe_ulica') . ', ' . $m('_oe_kod') . ' ' . $m('_oe_miasto') );
            break;
        case 'kontakt':
            echo esc_html( $m('_oe_email') );
            echo '<br><small>' . esc_html( $m('_oe_telefon') ) . '</small>';
            break;
        case 'status':
            if ( isset( $statusy[ $status ] ) ) {
                $s = $statusy[ $status ];
                echo "<span style='background:{$s['bg']};color:{$s['color']};padding:3px 8px;border-radius:4px;font-size:12px;font-weight:500'>"
                     . esc_html( $s['label'] ) . "</span>";
            }
            break;
        case 'data_zapisu':
            echo esc_html( get_the_date( 'd.m.Y H:i', $post_id ) );
            break;
        case 'akcja':
            if ( $status === 'oe_oczekuje' ) {
                $url_z = wp_nonce_url(
                    admin_url( "admin-post.php?action=oe_zmien_status&post_id={$post_id}&nowy_status=oe_zatwierdzony" ),
                    'oe_zmien_status_' . $post_id
                );
                $url_o = wp_nonce_url(
                    admin_url( "admin-post.php?action=oe_zmien_status&post_id={$post_id}&nowy_status=oe_odrzucony" ),
                    'oe_zmien_status_' . $post_id
                );
                echo "<a href='" . esc_url( $url_z ) . "' class='button button-primary button-small' style='background:#2e7d32;border-color:#2e7d32;margin-right:4px'>✓ Zatwierdź</a>";
                echo "<a href='" . esc_url( $url_o ) . "' class='button button-small' style='color:#c62828;border-color:#c62828'>✗ Odrzuć</a>";
            } elseif ( $status === 'oe_zatwierdzony' ) {
                $url_a = wp_nonce_url(
                    admin_url( "admin-post.php?action=oe_zmien_status&post_id={$post_id}&nowy_status=oe_anulowany" ),
                    'oe_zmien_status_' . $post_id
                );
                echo "<a href='" . esc_url( $url_a ) . "' class='button button-small' style='color:#555'>Anuluj</a>";
            }
            break;
    }
}, 10, 2 );

// ── Filtrowanie listy zapisów po egzaminie ────────────────────────────────

add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get('post_type') !== 'oe_zapis' ) return;

    // Pokaż wszystkie nasze statusy domyślnie
    if ( ! $query->get('post_status') ) {
        $query->set( 'post_status', array_keys( oe_get_statusy() ) );
    }

    $eid = isset( $_GET['oe_egzamin_id'] ) ? intval( $_GET['oe_egzamin_id'] ) : 0;
    if ( $eid ) {
        $query->set( 'meta_query', [ [ 'key' => '_oe_egzamin_id', 'value' => $eid ] ] );
    }
} );

// Dropdown filtrowania w listview
add_action( 'restrict_manage_posts', function( $post_type ) {
    if ( $post_type !== 'oe_zapis' ) return;

    $egzaminy = get_posts( [
        'post_type'      => 'oe_egzamin',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_oe_data_egzaminu',
        'order'          => 'DESC',
    ] );

    $wybrany = isset( $_GET['oe_egzamin_id'] ) ? intval( $_GET['oe_egzamin_id'] ) : 0;
    echo '<select name="oe_egzamin_id"><option value="">- Wszystkie egzaminy -</option>';
    foreach ( $egzaminy as $e ) {
        $nr = get_post_meta( $e->ID, '_oe_nr_egzaminu', true );
        $d  = get_post_meta( $e->ID, '_oe_data_egzaminu', true );
        $label = $nr . ( $d ? ' (' . date_i18n( 'd.m.Y', strtotime($d) ) . ')' : '' );
        printf(
            '<option value="%d"%s>%s</option>',
            $e->ID,
            selected( $wybrany, $e->ID, false ),
            esc_html( $label )
        );
    }
    echo '</select>';
} );

// ── Zmiana statusu (action handler) ──────────────────────────────────────

add_action( 'admin_post_oe_zmien_status', function() {
    $post_id    = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
    $nowy       = isset( $_GET['nowy_status'] ) ? sanitize_text_field( $_GET['nowy_status'] ) : '';
    $dozwolone  = array_keys( oe_get_statusy() );

    if ( ! $post_id || ! in_array( $nowy, $dozwolone ) ) {
        wp_die( 'Nieprawidłowe żądanie.' );
    }

    check_admin_referer( 'oe_zmien_status_' . $post_id );

    if ( ! current_user_can( 'oe_manage_exams' ) ) {
        wp_die( 'Brak uprawnień.' );
    }

    $stary = get_post_field( 'post_status', $post_id );

    wp_update_post( [
        'ID'          => $post_id,
        'post_status' => $nowy,
    ] );

    // Wyślij e-mail do uczestnika jeśli status zmieniony
    if ( $stary !== $nowy ) {
        oe_wyslij_email_zmiana_statusu( $post_id, $nowy );
    }

    $eid = get_post_meta( $post_id, '_oe_egzamin_id', true );
    $redirect = $eid
        ? admin_url( 'edit.php?post_type=oe_zapis&oe_egzamin_id=' . $eid . '&oe_msg=' . urlencode( oe_get_statusy()[$nowy]['label'] ) )
        : admin_url( 'edit.php?post_type=oe_zapis' );

    wp_safe_redirect( $redirect );
    exit;
} );

// Komunikat po zmianie statusu
add_action( 'admin_notices', function() {
    if ( isset( $_GET['oe_msg'] ) && get_current_screen()->post_type === 'oe_zapis' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Status zmieniony na: <strong>'
             . esc_html( urldecode( $_GET['oe_msg'] ) ) . '</strong></p></div>';
    }
} );

// ── Bulk actions ──────────────────────────────────────────────────────────

add_filter( 'bulk_actions-edit-oe_zapis', function( $actions ) {
    $actions['oe_bulk_zatwierdz'] = 'Zatwierdź wybrane';
    $actions['oe_bulk_odrzuc']    = 'Odrzuć wybrane';
    return $actions;
} );

add_filter( 'handle_bulk_actions-edit-oe_zapis', function( $redirect, $action, $post_ids ) {
    if ( ! in_array( $action, [ 'oe_bulk_zatwierdz', 'oe_bulk_odrzuc' ] ) || ! current_user_can( 'oe_manage_exams' ) ) {
        return $redirect;
    }
    $nowy = ( $action === 'oe_bulk_zatwierdz' ) ? 'oe_zatwierdzony' : 'oe_odrzucony';
    foreach ( $post_ids as $id ) {
        wp_update_post( [ 'ID' => intval($id), 'post_status' => $nowy ] );
        oe_wyslij_email_zmiana_statusu( intval($id), $nowy );
    }
    return add_query_arg( 'oe_bulk_done', count($post_ids), $redirect );
}, 10, 3 );
