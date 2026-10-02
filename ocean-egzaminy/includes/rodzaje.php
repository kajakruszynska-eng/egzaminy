<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Exam types (CPT oe_rodzaj). Each type holds everything that used to be
 * hardcoded per exam type: short code for file names, decision number,
 * theory and practice venues, exam card rows, practical task sections with
 * drawing rules, parental consent flag, number of test questions and the
 * answer key.
 *
 * Data lives in post meta `_oe_rodzaj_dane` (array, see oe_rodzaj_normalize()).
 * Exams store the type ID in `_oe_rodzaj_id` and keep the type name in
 * `_oe_rodzaj_egzaminu` (kept in sync, used by list columns and shortcodes).
 */

define( 'OE_RODZAJ_META', '_oe_rodzaj_dane' );

add_action( 'init', function() {
    $caps = array();
    foreach ( array(
        'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts',
        'publish_posts', 'read_private_posts', 'create_posts',
        'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts',
    ) as $c ) {
        $caps[ $c ] = 'manage_options';
    }
    register_post_type( 'oe_rodzaj', array(
        'labels' => array(
            'name'          => 'Typy egzaminów',
            'singular_name' => 'Typ egzaminu',
            'add_new'       => 'Dodaj typ',
            'add_new_item'  => 'Dodaj typ egzaminu',
            'edit_item'     => 'Edytuj typ egzaminu',
            'all_items'     => 'Typy egzaminów',
            'not_found'     => 'Brak typów egzaminów.',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'edit.php?post_type=oe_egzamin',
        'supports'     => array( 'title' ),
        'show_in_rest' => false,
        'capabilities' => $caps,
        'map_meta_cap' => true,
    ) );
} );

// ── Data access ───────────────────────────────────────────────────────────

/** Empty type data with every key present. */
function oe_rodzaj_puste() {
    return array(
        'skrot'            => '',
        'nr_decyzji'       => '',
        'miejsca_teoria'   => array(),
        'miejsca_praktyka' => array(),
        'karta_wiersze'    => array(),
        'zgoda_rodzicow'   => false,
        'sekcje'           => array(),
        'liczba_pytan'     => 0,
        'klucz'            => '',
        'dokumenty'           => array(), // see oe_rodzaj_normalize_dokumenty()
        'dokumenty_dodatkowe' => array(),
    );
}

function oe_rodzaj_lista_tekstu( $v ) {
    // Explicit newlines: '\R' without /u also matches byte 0x85, which is part of UTF-8 letters like "ą".
    if ( is_string( $v ) ) $v = preg_split( '/\r\n|\r|\n/', $v );
    $out = array();
    foreach ( (array) $v as $line ) {
        $line = trim( sanitize_text_field( (string) $line ) );
        if ( $line !== '' ) $out[] = $line;
    }
    return $out;
}

/**
 * Parse task lines. "[zawsze] name" = always passed, "[nigdy] name" = never drawn.
 * Accepts either such lines (string) or an array of task arrays.
 */
function oe_rodzaj_parsuj_zadania( $v ) {
    $out = array();
    if ( is_string( $v ) ) {
        foreach ( preg_split( '/\r\n|\r|\n/', $v ) as $line ) {
            $line = trim( (string) $line );
            $poz  = false;
            $nie  = false;
            if ( preg_match( '/^\[zawsze\]\s*/iu', $line, $m ) ) {
                $poz  = true;
                $line = substr( $line, strlen( $m[0] ) );
            } elseif ( preg_match( '/^\[nigdy\]\s*/iu', $line, $m ) ) {
                $nie  = true;
                $line = substr( $line, strlen( $m[0] ) );
            }
            $line = trim( sanitize_text_field( $line ) );
            if ( $line !== '' ) $out[] = array( 'nazwa' => $line, 'zawsze_poz' => $poz, 'zawsze_nie' => $nie );
        }
        return $out;
    }
    foreach ( (array) $v as $t ) {
        if ( ! is_array( $t ) ) continue;
        $nazwa = isset( $t['nazwa'] ) ? trim( sanitize_text_field( (string) $t['nazwa'] ) ) : '';
        if ( $nazwa === '' ) continue;
        $poz = ! empty( $t['zawsze_poz'] );
        $out[] = array( 'nazwa' => $nazwa, 'zawsze_poz' => $poz, 'zawsze_nie' => ! $poz && ! empty( $t['zawsze_nie'] ) );
    }
    return $out;
}

/** Sanitize raw type data (from the edit form or an import file) into the stored shape. */
function oe_rodzaj_normalize( $raw ) {
    $raw = is_array( $raw ) ? $raw : array();
    $d   = oe_rodzaj_puste();

    $d['skrot']            = isset( $raw['skrot'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $raw['skrot'] ) ) : '';
    $d['nr_decyzji']       = isset( $raw['nr_decyzji'] ) ? sanitize_text_field( (string) $raw['nr_decyzji'] ) : '';
    $d['miejsca_teoria']   = oe_rodzaj_lista_tekstu( isset( $raw['miejsca_teoria'] ) ? $raw['miejsca_teoria'] : array() );
    $d['miejsca_praktyka'] = oe_rodzaj_lista_tekstu( isset( $raw['miejsca_praktyka'] ) ? $raw['miejsca_praktyka'] : array() );
    $d['karta_wiersze']    = oe_rodzaj_lista_tekstu( isset( $raw['karta_wiersze'] ) ? $raw['karta_wiersze'] : array() );
    $d['zgoda_rodzicow']   = ! empty( $raw['zgoda_rodzicow'] );
    $d['liczba_pytan']     = isset( $raw['liczba_pytan'] ) ? max( 0, (int) $raw['liczba_pytan'] ) : 0;
    $d['klucz']            = isset( $raw['klucz'] ) ? preg_replace( '/[^ABC]/', '', strtoupper( (string) $raw['klucz'] ) ) : '';

    foreach ( isset( $raw['sekcje'] ) && is_array( $raw['sekcje'] ) ? $raw['sekcje'] : array() as $s ) {
        if ( ! is_array( $s ) ) continue;
        $nazwa   = isset( $s['nazwa'] ) ? trim( sanitize_text_field( (string) $s['nazwa'] ) ) : '';
        $zadania = oe_rodzaj_parsuj_zadania( isset( $s['zadania'] ) ? $s['zadania'] : array() );
        if ( $nazwa === '' && ! $zadania ) continue;
        if ( $nazwa === '' ) $nazwa = 'zadania';
        $min = isset( $s['min'] ) ? max( 0, (int) $s['min'] ) : 0;
        $max = isset( $s['max'] ) ? max( 0, (int) $s['max'] ) : 0;
        if ( $max < $min ) $max = $min;
        $d['sekcje'][] = array( 'nazwa' => $nazwa, 'min' => $min, 'max' => $max, 'zadania' => $zadania );
    }

    list( $d['dokumenty'], $d['dokumenty_dodatkowe'] ) = oe_rodzaj_normalize_dokumenty(
        isset( $raw['dokumenty'] ) ? $raw['dokumenty'] : array(),
        isset( $raw['dokumenty_dodatkowe'] ) ? $raw['dokumenty_dodatkowe'] : array()
    );
    return $d;
}

/** Type as array (oe_rodzaj_puste() keys plus id and nazwa), or null. */
function oe_rodzaj_get( $id ) {
    $id = (int) $id;
    if ( ! $id || get_post_type( $id ) !== 'oe_rodzaj' || get_post_status( $id ) === 'trash' ) return null;
    $data = get_post_meta( $id, OE_RODZAJ_META, true );
    $data = array_merge( oe_rodzaj_puste(), is_array( $data ) ? $data : array() );
    // Types saved before document settings existed: every document built in.
    if ( ! $data['dokumenty'] ) {
        list( $data['dokumenty'], $data['dokumenty_dodatkowe'] ) = oe_rodzaj_normalize_dokumenty( array(), $data['dokumenty_dodatkowe'] );
    }
    $data['id']    = $id;
    $data['nazwa'] = get_the_title( $id );
    return $data;
}

/** All published types, id => type array, ordered by name. Cached per request; pass true after changes. */
function oe_rodzaje_all( $refresh = false ) {
    static $cache = null;
    if ( $cache !== null && ! $refresh ) return $cache;
    $cache = array();
    foreach ( get_posts( array( 'post_type' => 'oe_rodzaj', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) ) as $id ) {
        $cache[ $id ] = oe_rodzaj_get( $id );
    }
    return $cache;
}

// Any change to a type invalidates the cache.
add_action( 'save_post_oe_rodzaj', function() { oe_rodzaje_all( true ); }, 1 );
add_action( 'deleted_post', function() { oe_rodzaje_all( true ); } );
add_action( 'trashed_post', function() { oe_rodzaje_all( true ); } );
add_action( 'updated_post_meta', function( $mid, $oid, $key ) { if ( $key === OE_RODZAJ_META ) oe_rodzaje_all( true ); }, 10, 3 );
add_action( 'added_post_meta', function( $mid, $oid, $key ) { if ( $key === OE_RODZAJ_META ) oe_rodzaje_all( true ); }, 10, 3 );

/** Find a type ID by its name: exact, then case-insensitive, then one contained in the other. */
function oe_rodzaj_find_by_label( $label ) {
    $label = trim( (string) $label );
    if ( $label === '' ) return 0;
    $all = oe_rodzaje_all();
    foreach ( $all as $id => $t ) {
        if ( $t['nazwa'] === $label ) return $id;
    }
    foreach ( $all as $id => $t ) {
        if ( mb_strtolower( $t['nazwa'], 'UTF-8' ) === mb_strtolower( $label, 'UTF-8' ) ) return $id;
    }
    foreach ( $all as $id => $t ) {
        if ( mb_stripos( $label, $t['nazwa'], 0, 'UTF-8' ) !== false || mb_stripos( $t['nazwa'], $label, 0, 'UTF-8' ) !== false ) return $id;
    }
    return 0;
}

/** Type of an exam: by stored ID, falling back to the stored name (exams not migrated yet). */
function oe_egzamin_rodzaj( $egzamin_id ) {
    $t = oe_rodzaj_get( get_post_meta( $egzamin_id, '_oe_rodzaj_id', true ) );
    if ( $t ) return $t;
    return oe_rodzaj_get( oe_rodzaj_find_by_label( get_post_meta( $egzamin_id, '_oe_rodzaj_egzaminu', true ) ) );
}

/** Task definition in the shape oe_losuj_zadania_v2() expects (sections keyed by name). */
function oe_rodzaj_def_losowania( $typ ) {
    $def = array( 'zadania' => array(), 'losowanie' => array() );
    if ( ! $typ ) return $def;
    foreach ( $typ['sekcje'] as $s ) {
        foreach ( $s['zadania'] as $z ) {
            $def['zadania'][] = array( 'nazwa' => $z['nazwa'], 'sekcja' => $s['nazwa'], 'zawsze_poz' => $z['zawsze_poz'], 'zawsze_nie' => $z['zawsze_nie'] );
        }
        $def['losowanie'][ $s['nazwa'] ] = array( 'min' => (int) $s['min'], 'max' => (int) $s['max'] );
    }
    return $def;
}

/** Answer key as question number (1-based) => 0, 1 or 2 (A, B, C). */
function oe_rodzaj_klucz_map( $typ ) {
    $map = array();
    if ( ! $typ ) return $map;
    $len = strlen( $typ['klucz'] );
    for ( $i = 0; $i < $len; $i++ ) {
        $map[ $i + 1 ] = strpos( 'ABC', $typ['klucz'][ $i ] );
    }
    return $map;
}

/**
 * Deterministic draw of passed tasks for a participant (seeded by name).
 * Returns task indexes. Unchanged from the original implementation so
 * regenerated documents keep the same tasks.
 */
function oe_losuj_zadania_v2($u, $zadania_def) {
    // Obsługa nowego formatu (z kluczem 'zadania') i starego (flat array)
    if (isset($zadania_def['zadania'])) {
        $zadania  = $zadania_def['zadania'];
        $losowanie = isset($zadania_def['losowanie']) ? $zadania_def['losowanie'] : array();
    } else {
        $zadania  = $zadania_def;
        $losowanie = array();
    }

    $seed = crc32($u['imie'] . $u['nazwisko']);
    mt_srand($seed);

    $poz = array();

    if (!empty($losowanie)) {
        // Grupuj zadania wg sekcji
        $sekcje = array();
        foreach ($zadania as $idx => $z) {
            $s = $z['sekcja'];
            if (!isset($sekcje[$s])) $sekcje[$s] = array();
            $sekcje[$s][] = $idx;
        }

        foreach ($sekcje as $sname => $idxs) {
            foreach ($idxs as $idx) {
                $z = $zadania[$idx];
                if (!empty($z['zawsze_poz'])) {
                    $poz[] = $idx;
                }
            }
        }

        // Losuj per sekcja
        foreach ($losowanie as $sname => $range) {
            $min = $range['min'];
            $max = $range['max'];
            if ($min === 0 && $max === 0) continue;

            // Pula = zadania w sekcji bez zawsze_poz i bez zawsze_nie
            $pula = array();
            if (isset($sekcje[$sname])) {
                foreach ($sekcje[$sname] as $idx) {
                    $z = $zadania[$idx];
                    if (empty($z['zawsze_poz']) && empty($z['zawsze_nie'])) {
                        $pula[] = $idx;
                    }
                }
            }

            if (empty($pula)) continue;

            $ile = ($min === $max) ? $min : mt_rand($min, $max);
            $ile = min($ile, count($pula));

            // Deterministyczny shuffle
            for ($i = count($pula)-1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $tmp = $pula[$i]; $pula[$i] = $pula[$j]; $pula[$j] = $tmp;
            }
            $poz = array_merge($poz, array_slice($pula, 0, $ile));
        }
    } else {
        // Brak reguł losowania: stara heurystyka
        $zawsze = array();
        $pula   = array();
        foreach ($zadania as $idx => $z) {
            if (!empty($z['zawsze_poz'])) $zawsze[] = $idx;
            else $pula[] = $idx;
        }
        $n_puli = count($pula);
        if (count($zawsze) === 0 && $n_puli >= 6) {
            $ile = mt_rand(4, 5);
        } elseif ($n_puli <= 4) {
            $ile = mt_rand(2, 3);
        } else {
            $ile = mt_rand(3, 4);
        }
        for ($i = $n_puli-1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $pula[$i]; $pula[$i] = $pula[$j]; $pula[$j] = $tmp;
        }
        $poz = array_merge($zawsze, array_slice($pula, 0, min($ile, $n_puli)));
    }

    return array_unique($poz);
}

// ── Import, export, migration ─────────────────────────────────────────────

/** Export shape used in the settings JSON file ("rodzaje"). */
function oe_rodzaj_export( $typ ) {
    $out = array( 'nazwa' => $typ['nazwa'] );
    foreach ( oe_rodzaj_puste() as $k => $v ) {
        $out[ $k ] = $typ[ $k ];
    }
    return $out;
}

/** Create or update (matched by exact name) one type from import data. Returns the post ID or 0. */
function oe_rodzaj_import( $raw ) {
    if ( ! is_array( $raw ) ) return 0;
    $nazwa = isset( $raw['nazwa'] ) ? trim( sanitize_text_field( (string) $raw['nazwa'] ) ) : '';
    if ( $nazwa === '' ) return 0;
    $id = 0;
    foreach ( oe_rodzaje_all() as $tid => $t ) {
        if ( $t['nazwa'] === $nazwa ) {
            $id = $tid;
            break;
        }
    }
    if ( ! $id ) {
        $id = wp_insert_post( array( 'post_type' => 'oe_rodzaj', 'post_status' => 'publish', 'post_title' => $nazwa ) );
        if ( ! $id || is_wp_error( $id ) ) return 0;
    }
    update_post_meta( $id, OE_RODZAJ_META, wp_slash( oe_rodzaj_normalize( $raw ) ) );
    return $id;
}

/**
 * Link exams to types: sets `_oe_rodzaj_id` where it is missing or points to a
 * deleted type, matching by the stored name. Returns array( linked, unmatched ).
 */
function oe_migruj_rodzaje_egzaminow() {
    $linked    = 0;
    $unmatched = 0;
    $exams = get_posts( array( 'post_type' => 'oe_egzamin', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'fields' => 'ids' ) );
    foreach ( $exams as $eid ) {
        if ( oe_rodzaj_get( get_post_meta( $eid, '_oe_rodzaj_id', true ) ) ) continue;
        $label = get_post_meta( $eid, '_oe_rodzaj_egzaminu', true );
        if ( $label === '' ) continue;
        $rid = oe_rodzaj_find_by_label( $label );
        if ( $rid ) {
            update_post_meta( $eid, '_oe_rodzaj_id', $rid );
            update_post_meta( $eid, '_oe_rodzaj_egzaminu', wp_slash( get_the_title( $rid ) ) );
            $linked++;
        } else {
            $unmatched++;
        }
    }
    update_option( 'oe_rodzaje_niedopasowane', $unmatched, false );
    return array( $linked, $unmatched );
}

/** Keep the denormalized type name on exams in sync after a rename. */
function oe_rodzaj_synchronizuj_nazwe( $rid ) {
    $nazwa = get_the_title( $rid );
    $exams = get_posts( array( 'post_type' => 'oe_egzamin', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_oe_rodzaj_id', 'meta_value' => $rid ) );
    foreach ( $exams as $eid ) {
        if ( get_post_meta( $eid, '_oe_rodzaj_egzaminu', true ) !== $nazwa ) {
            update_post_meta( $eid, '_oe_rodzaj_egzaminu', wp_slash( $nazwa ) );
        }
    }
}

/**
 * Create the standard exam types once, on a site that has no exam types at all
 * (none in any status, so deleted ones are not recreated). Existing exams are
 * then linked to them by name. Returns the number of types created.
 */
function oe_utworz_rodzaje_standardowe() {
    if ( get_option( 'oe_rodzaje_standardowe' ) ) return 0;
    update_option( 'oe_rodzaje_standardowe', 1 );
    $istniejace = get_posts( array( 'post_type' => 'oe_rodzaj', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
    if ( $istniejace ) return 0;
    $n = 0;
    $GLOBALS['oe_import_trwa'] = true;
    foreach ( oe_rodzaje_standardowe() as $raw ) {
        if ( oe_rodzaj_import( $raw ) ) $n++;
    }
    $GLOBALS['oe_import_trwa'] = false;
    oe_rodzaje_all( true );
    oe_migruj_rodzaje_egzaminow();
    return $n;
}

// Activation does not run when a zip replaces an older version, so also check on admin load.
add_action( 'admin_init', 'oe_utworz_rodzaje_standardowe', 5 );

// ── Edit screen ───────────────────────────────────────────────────────────

add_action( 'add_meta_boxes_oe_rodzaj', function() {
    add_meta_box( 'oe_rodzaj_dane', 'Dane typu egzaminu', 'oe_metabox_rodzaj', 'oe_rodzaj', 'normal', 'high' );
} );

function oe_metabox_rodzaj( $post ) {
    wp_nonce_field( 'oe_save_rodzaj', 'oe_rodzaj_nonce' );
    $t = oe_rodzaj_get( $post->ID );
    if ( ! $t ) $t = array_merge( oe_rodzaj_puste(), array( 'id' => 0, 'nazwa' => '' ) );

    $sekcje   = $t['sekcje'];
    $sekcje[] = array( 'nazwa' => '', 'min' => 0, 'max' => 0, 'zadania' => array() ); // one empty block for a new section

    $klucz_txt = trim( chunk_split( $t['klucz'], 5, ' ' ) );
    $klucz_txt = implode( "\n", array_map( 'trim', str_split( $klucz_txt, 30 ) ) );
    $klucz_len = strlen( $t['klucz'] );
    ?>
    <style>
    .oe-rodzaj textarea { width:100%; font-family:inherit; }
    .oe-rodzaj .oe-sekcja { border:1px solid #dcdcde; border-radius:4px; padding:10px 12px; margin:0 0 12px; background:#fbfbfb; }
    .oe-rodzaj .oe-sekcja-gora { display:flex; gap:12px; align-items:flex-end; margin-bottom:8px; flex-wrap:wrap; }
    .oe-rodzaj .oe-sekcja-gora label { display:flex; flex-direction:column; font-size:12px; font-weight:600; }
    .oe-rodzaj .oe-klucz { font-family:Consolas,Monaco,monospace; letter-spacing:.05em; }
    </style>
    <div class="oe-rodzaj">
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="oe-r-skrot">Skrót</label></th>
            <td><input type="text" id="oe-r-skrot" name="oe_rodzaj[skrot]" value="<?php echo esc_attr( $t['skrot'] ); ?>" class="small-text" style="width:100px">
                <p class="description">W nazwach plików dokumentów, np. SM, ZJ.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="oe-r-decyzja">Nr decyzji MSiT</label></th>
            <td><input type="text" id="oe-r-decyzja" name="oe_rodzaj[nr_decyzji]" value="<?php echo esc_attr( $t['nr_decyzji'] ); ?>" class="regular-text">
                <p class="description">Wpisywany automatycznie do nowych egzaminów tego typu.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="oe-r-teoria">Miejsca teorii</label></th>
            <td><textarea id="oe-r-teoria" name="oe_rodzaj[miejsca_teoria]" rows="6" wrap="off"><?php echo esc_textarea( implode( "\n", $t['miejsca_teoria'] ) ); ?></textarea>
                <p class="description">Jedno miejsce w linii. Podpowiedzi w polu „Miejsce teorii” egzaminu.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="oe-r-praktyka">Miejsca praktyki</label></th>
            <td><textarea id="oe-r-praktyka" name="oe_rodzaj[miejsca_praktyka]" rows="6" wrap="off"><?php echo esc_textarea( implode( "\n", $t['miejsca_praktyka'] ) ); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="oe-r-wiersze">Karta egzaminacyjna: wiersze</label></th>
            <td><textarea id="oe-r-wiersze" name="oe_rodzaj[karta_wiersze]" rows="3"><?php echo esc_textarea( implode( "\n", $t['karta_wiersze'] ) ); ?></textarea>
                <p class="description">Pierwsza tabela karty („zadanie egzaminacyjne:”), jeden wiersz w linii, np. test z teorii, praktyka. Puste: test z teorii i praktyka.</p>
                <label><input type="checkbox" name="oe_rodzaj[zgoda_rodzicow]" value="1" <?php checked( $t['zgoda_rodzicow'] ); ?>> Na karcie pole „Dołączono zgodę rodziców/opiekunów prawnych”</label></td>
        </tr>
        <tr>
            <th scope="row">Zadania praktyczne</th>
            <td>
                <p class="description" style="margin-top:0">Każda sekcja to osobna tabela na karcie i grupa kolumn w załączniku nr 2. Jedno zadanie w linii. Przedrostek <code>[zawsze]</code> = zawsze zaliczone, <code>[nigdy]</code> = nigdy nielosowane. Z pozostałych losuje się od „min” do „max” zadań na osobę. Aby usunąć sekcję, wyczyść jej nazwę i zadania. Po zapisaniu pojawi się kolejny pusty blok.</p>
                <p class="description"><strong>Uwaga:</strong> losowanie zależy od kolejności zadań i sekcji. Zmiana kolejności, dodanie lub usunięcie zadania zmienia wylosowane zadania wszystkich uczestników tego typu, także w dokumentach generowanych ponownie dla minionych egzaminów.</p>
                <?php foreach ( $sekcje as $i => $s ) :
                    $lines = array();
                    foreach ( $s['zadania'] as $z ) {
                        $lines[] = ( $z['zawsze_poz'] ? '[zawsze] ' : ( $z['zawsze_nie'] ? '[nigdy] ' : '' ) ) . $z['nazwa'];
                    }
                    $base = 'oe_rodzaj[sekcje][' . $i . ']';
                    ?>
                    <div class="oe-sekcja">
                        <div class="oe-sekcja-gora">
                            <label style="flex:1;min-width:220px">Nazwa sekcji<input type="text" name="<?php echo esc_attr( $base ); ?>[nazwa]" value="<?php echo esc_attr( $s['nazwa'] ); ?>" placeholder="<?php echo $s['nazwa'] === '' ? 'nowa sekcja, np. manewry na silniku - zadania' : ''; ?>"></label>
                            <label>Losuj min<input type="number" min="0" name="<?php echo esc_attr( $base ); ?>[min]" value="<?php echo (int) $s['min']; ?>" style="width:80px"></label>
                            <label>max<input type="number" min="0" name="<?php echo esc_attr( $base ); ?>[max]" value="<?php echo (int) $s['max']; ?>" style="width:80px"></label>
                        </div>
                        <textarea name="<?php echo esc_attr( $base ); ?>[zadania]" rows="<?php echo max( 3, count( $lines ) ); ?>"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="oe-r-pytania">Arkusz odpowiedzi: liczba pytań</label></th>
            <td><input type="number" min="0" id="oe-r-pytania" name="oe_rodzaj[liczba_pytan]" value="<?php echo (int) $t['liczba_pytan']; ?>" style="width:100px">
                <p class="description">Puste lub 0: 75.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="oe-r-klucz">Klucz odpowiedzi (arkusz WZÓR)</label></th>
            <td><textarea id="oe-r-klucz" class="oe-klucz" name="oe_rodzaj[klucz]" rows="4"><?php echo esc_textarea( $klucz_txt ); ?></textarea>
                <p class="description">Litery A, B lub C kolejno dla pytań 1, 2, 3... Spacje i nowe linie są pomijane.</p>
                <?php if ( $klucz_len && $t['liczba_pytan'] && $klucz_len !== (int) $t['liczba_pytan'] ) : ?>
                    <p style="color:#b32d2e"><strong>Uwaga:</strong> klucz ma <?php echo (int) $klucz_len; ?> odpowiedzi, a liczba pytań to <?php echo (int) $t['liczba_pytan']; ?>.</p>
                <?php endif; ?>
            </td>
        </tr>
        <?php oe_metabox_rodzaj_dokumenty( $t ); ?>
    </table>
    </div>
    <?php
}

add_action( 'save_post_oe_rodzaj', function( $post_id ) {
    if ( ! isset( $_POST['oe_rodzaj_nonce'] ) || ! wp_verify_nonce( $_POST['oe_rodzaj_nonce'], 'oe_save_rodzaj' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $raw = isset( $_POST['oe_rodzaj'] ) && is_array( $_POST['oe_rodzaj'] ) ? wp_unslash( $_POST['oe_rodzaj'] ) : array();
    update_post_meta( $post_id, OE_RODZAJ_META, wp_slash( oe_rodzaj_normalize( $raw ) ) );
} );

// After any save (form, import, code): push a renamed title to exams and link unlinked exams.
add_action( 'save_post_oe_rodzaj', function( $post_id ) {
    if ( wp_is_post_revision( $post_id ) || get_post_status( $post_id ) !== 'publish' ) return;
    if ( ! empty( $GLOBALS['oe_import_trwa'] ) ) return; // the importer migrates once at the end
    oe_rodzaje_all( true );
    oe_rodzaj_synchronizuj_nazwe( $post_id );
    oe_migruj_rodzaje_egzaminow();
}, 20 );

// ── List table ────────────────────────────────────────────────────────────

add_filter( 'manage_oe_rodzaj_posts_columns', function( $cols ) {
    unset( $cols['date'] );
    $cols['skrot']    = 'Skrót';
    $cols['decyzja']  = 'Nr decyzji';
    $cols['zawartosc'] = 'Zawartość';
    return $cols;
} );

add_action( 'manage_oe_rodzaj_posts_custom_column', function( $col, $post_id ) {
    $t = oe_rodzaj_get( $post_id );
    if ( ! $t ) return;
    if ( $col === 'skrot' ) echo esc_html( $t['skrot'] );
    if ( $col === 'decyzja' ) echo esc_html( $t['nr_decyzji'] );
    if ( $col === 'zawartosc' ) {
        $n_zad = 0;
        foreach ( $t['sekcje'] as $s ) $n_zad += count( $s['zadania'] );
        printf(
            'miejsca: %d teoria, %d praktyka; zadania: %d; pytania: %d',
            count( $t['miejsca_teoria'] ), count( $t['miejsca_praktyka'] ), $n_zad, $t['liczba_pytan'] ?: 75
        );
    }
}, 10, 2 );

// ── Notices ───────────────────────────────────────────────────────────────

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, array( 'oe_egzamin', 'oe_zapis', 'oe_rodzaj' ), true ) ) return;
    $url = admin_url( 'edit.php?post_type=oe_egzamin&page=oe-ustawienia' );
    if ( ! oe_rodzaje_all() ) {
        echo '<div class="notice notice-warning"><p><strong>Egzaminy:</strong> brak typów egzaminów. Dodaj je w Egzaminy &gt; Typy egzaminów albo zaimportuj plik w <a href="' . esc_url( $url ) . '">ustawieniach</a>.</p></div>';
        return;
    }
    $bez_decyzji = array();
    foreach ( oe_rodzaje_all() as $t ) {
        if ( $t['nr_decyzji'] === '' ) $bez_decyzji[] = $t['nazwa'];
    }
    if ( $bez_decyzji ) {
        echo '<div class="notice notice-info"><p><strong>Egzaminy:</strong> typy bez numeru decyzji: ' . esc_html( implode( ', ', $bez_decyzji ) ) . '. Uzupełnij je w Egzaminy &gt; Typy egzaminów (razem z miejscami i kluczem odpowiedzi) albo zaimportuj plik z danymi organizacji w <a href="' . esc_url( $url ) . '">ustawieniach</a>.</p></div>';
    }
    $n = (int) get_option( 'oe_rodzaje_niedopasowane', 0 );
    if ( $n > 0 ) {
        echo '<div class="notice notice-warning"><p><strong>Egzaminy:</strong> ' . (int) $n . ' egzamin(y) mają rodzaj, który nie pasuje do żadnego typu egzaminu. Otwórz je i wybierz typ z listy.</p></div>';
    }
} );
