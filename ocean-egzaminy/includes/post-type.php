<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'oe_register_post_types' );

function oe_register_post_types() {

    // ── CPT: Egzamin (ogłoszenie) ─────────────────────────────────────────
    register_post_type( 'oe_egzamin', [
        'labels' => [
            'name'               => 'Egzaminy',
            'singular_name'      => 'Egzamin',
            'add_new'            => 'Dodaj nowy',
            'add_new_item'       => 'Dodaj nowy egzamin',
            'edit_item'          => 'Edytuj egzamin',
            'all_items'          => 'Wszystkie egzaminy',
            'search_items'       => 'Szukaj egzaminów',
            'not_found'          => 'Nie znaleziono egzaminów.',
        ],
        'public'              => true,
        'show_in_menu'        => true,
        'menu_icon'           => 'dashicons-awards',
        'menu_position'       => 5,
        'supports'            => [ 'title', 'editor' ],
        'has_archive'         => false,
        'rewrite'             => [ 'slug' => 'egzamin' ],
        'show_in_rest'        => false,
        'capabilities'        => oe_cpt_capabilities(),
        'map_meta_cap'        => true,
    ] );

    // ── CPT: Zapis uczestnika ─────────────────────────────────────────────
    register_post_type( 'oe_zapis', [
        'labels' => [
            'name'               => 'Zapisy uczestników',
            'singular_name'      => 'Zapis',
            'all_items'          => 'Wszystkie zapisy',
            'search_items'       => 'Szukaj zapisów',
            'not_found'          => 'Brak zapisów.',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=oe_egzamin',
        'supports'            => [ 'title' ],
        'has_archive'         => false,
        'show_in_rest'        => false,
        'capabilities'        => oe_cpt_capabilities( [
            'create_posts'    => 'do_not_allow',  // blokuj ręczne tworzenie
        ] ),
        'map_meta_cap'        => true,
    ] );

    // ── Statusy zapisu ────────────────────────────────────────────────────
    $statusy = [
        'oczekuje'     => [ 'label' => 'Oczekuje na opłatę',  'color' => '#BA7517' ],
        'zatwierdzony' => [ 'label' => 'Zatwierdzony',         'color' => '#2e7d32' ],
        'odrzucony'    => [ 'label' => 'Odrzucony',            'color' => '#c62828' ],
        'anulowany'    => [ 'label' => 'Anulowany',            'color' => '#555'    ],
    ];

    foreach ( $statusy as $slug => $info ) {
        register_post_status( 'oe_' . $slug, [
            'label'                     => $info['label'],
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                $info['label'] . ' <span class="count">(%s)</span>',
                $info['label'] . ' <span class="count">(%s)</span>'
            ),
        ] );
    }
}

/**
 * Zwraca tablicę statusów z etykietami i kolorami.
 */
function oe_get_statusy() {
    return [
        'oe_oczekuje'     => [ 'label' => 'Oczekuje na opłatę',  'color' => '#BA7517', 'bg' => '#FFF8E7' ],
        'oe_zatwierdzony' => [ 'label' => 'Zatwierdzony',         'color' => '#2e7d32', 'bg' => '#E8F5E9' ],
        'oe_odrzucony'    => [ 'label' => 'Odrzucony',            'color' => '#c62828', 'bg' => '#FFEBEE' ],
        'oe_anulowany'    => [ 'label' => 'Anulowany',            'color' => '#555',    'bg' => '#F5F5F5' ],
    ];
}
