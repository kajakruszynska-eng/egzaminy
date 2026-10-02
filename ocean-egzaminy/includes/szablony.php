<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Document configuration per exam type and the DOCX template engine.
 *
 * Every exam type decides, for each of the nine standard documents, whether it
 * uses the built-in layout (generator.php), an uploaded DOCX template, or is
 * switched off, and may add extra documents from templates.
 *
 * Templates are ordinary DOCX files with placeholders such as {nr_egzaminu}.
 *   - A table row containing {u.*} placeholders is repeated for every approved
 *     participant, a row containing {k.*} for every commission member.
 *   - With "one copy per participant" the whole document is repeated, one page
 *     per participant, and {u.*} placeholders work anywhere.
 *   - Unknown placeholders are left as they are, so typos stay visible.
 */

define( 'OE_W_NS', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

/** Standard documents: key => label. Keys are used in URLs and file names. */
function oe_dokumenty_wbudowane() {
    return array(
        'lista'         => '0 Lista uczestników',
        'zgloszenie'    => 'Zgłoszenie egzaminu',
        'protokol'      => 'Protokół KE',
        'zal1'          => 'Zał. nr 1 - wyniki',
        'zal2'          => 'Zał. nr 2 - praktyka',
        'zal3'          => 'Zał. nr 3 - zaświadczenia',
        'karty'         => 'Karty egzaminacyjne',
        'zaswiadczenia' => 'Zaświadczenia',
        'arkusze'       => 'Arkusze odpowiedzi',
        'arkusze_wzor'  => 'Arkusz odpowiedzi WZÓR 1',
    );
}

/** Placeholder reference: name => description (shown in the type editor and the sample template). */
function oe_szablon_znaczniki() {
    return array(
        'Egzamin' => array(
            'nr_egzaminu'        => 'numer egzaminu w rejestrze',
            'rodzaj'             => 'nazwa typu egzaminu',
            'rodzaj_wielkie'     => 'nazwa typu wielkimi literami',
            'skrot'              => 'skrót typu, np. SM',
            'nr_decyzji'         => 'numer decyzji',
            'data'               => 'data egzaminu, np. 15.11.2026 r.',
            'data_slownie'       => 'data egzaminu słownie, np. 15 listopada 2026 r',
            'godzina'            => 'godzina egzaminu',
            'miejscowosc'        => 'miejscowość egzaminu',
            'miejsce_teoria'     => 'miejsce części teoretycznej',
            'miejsce_praktyka'   => 'miejsce części praktycznej',
            'data_utworzenia'    => 'data utworzenia egzaminu w systemie',
            'miasto_data'        => 'miejscowość wystawienia i data utworzenia, np. Katowice, 01.09.2026 r.',
            'liczba_uczestnikow' => 'liczba zatwierdzonych uczestników',
            'liczba_pytan'       => 'liczba pytań testu',
            'komisja'            => 'skład komisji, jedna osoba w linii (imię i nazwisko - funkcja)',
            'przewodniczacy'     => 'imię i nazwisko przewodniczącego',
            'sekretarz'          => 'imię i nazwisko sekretarza',
            'osoba_podpisujaca'  => 'osoba uprawniona do zgłaszania egzaminu (z ustawień; puste: druga osoba z komisji)',
        ),
        'Organizacja' => array(
            'org_nazwa'          => 'pełna nazwa',
            'org_nazwa_krotka'   => 'nazwa skrócona',
            'org_nazwa_biernik'  => 'pełna nazwa w bierniku',
            'org_adres'          => 'adres w jednej linii',
            'org_ulica'          => 'ulica i numer',
            'org_kod_miasto'     => 'kod pocztowy i miejscowość',
            'org_rejestry'       => 'KRS, NIP, REGON',
            'org_telefon'        => 'telefon kontaktowy',
        ),
        'Uczestnik (wiersz tabeli powtarzany dla każdej osoby albo dokument na uczestnika)' => array(
            'u.lp'               => 'liczba porządkowa',
            'u.imie'             => 'imię',
            'u.nazwisko'         => 'nazwisko',
            'u.imie_nazwisko'    => 'imię i nazwisko',
            'u.data_ur'          => 'data urodzenia',
            'u.miejsce_ur'       => 'miejsce urodzenia',
            'u.adres'            => 'adres zamieszkania',
            'u.nr_zaswiadczenia' => 'numer zaświadczenia, np. 001/SM/001/2026',
            'u.zadania'          => 'zadania praktyczne zaliczone przez tę osobę (to samo losowanie co w zał. nr 2)',
        ),
        'Komisja (wiersz tabeli powtarzany dla każdego członka)' => array(
            'k.lp'               => 'liczba porządkowa',
            'k.imie'             => 'imię i nazwisko',
            'k.rola'             => 'funkcja, np. przewodniczący',
        ),
    );
}

/** Normalize the "dokumenty" part of exam type data. */
function oe_rodzaj_normalize_dokumenty( $raw_docs, $raw_extra ) {
    $docs = array();
    $raw_docs = is_array( $raw_docs ) ? $raw_docs : array();
    foreach ( oe_dokumenty_wbudowane() as $key => $label ) {
        $r    = isset( $raw_docs[ $key ] ) && is_array( $raw_docs[ $key ] ) ? $raw_docs[ $key ] : array();
        $tryb = isset( $r['tryb'] ) && in_array( $r['tryb'], array( 'wbudowany', 'szablon', 'wylaczony' ), true ) ? $r['tryb'] : 'wbudowany';
        $docs[ $key ] = array(
            'tryb'          => $tryb,
            'szablon_id'    => oe_szablon_poprawny_id( isset( $r['szablon_id'] ) ? $r['szablon_id'] : 0 ),
            'na_uczestnika' => ! empty( $r['na_uczestnika'] ),
        );
    }
    $extra = array();
    foreach ( is_array( $raw_extra ) ? $raw_extra : array() as $r ) {
        if ( ! is_array( $r ) ) continue;
        $nazwa = isset( $r['nazwa'] ) ? trim( sanitize_text_field( (string) $r['nazwa'] ) ) : '';
        $sid   = oe_szablon_poprawny_id( isset( $r['szablon_id'] ) ? $r['szablon_id'] : 0 );
        if ( $nazwa === '' && ! $sid ) continue;
        if ( $nazwa === '' ) $nazwa = 'Dokument';
        $extra[] = array( 'nazwa' => $nazwa, 'szablon_id' => $sid, 'na_uczestnika' => ! empty( $r['na_uczestnika'] ) );
    }
    return array( $docs, $extra );
}

/** Attachment ID if it is an existing .docx attachment, else 0. */
function oe_szablon_poprawny_id( $id ) {
    $id = (int) $id;
    if ( ! $id || get_post_type( $id ) !== 'attachment' ) return 0;
    $file = get_attached_file( $id );
    return ( $file && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'docx' ) ? $id : 0;
}

/**
 * Documents offered for an exam: list of
 * array( key, label, tryb ('wbudowany'|'szablon'), szablon_id, na_uczestnika, problem ).
 * Exams without a type get all built-in documents.
 */
function oe_egzamin_dokumenty( $typ ) {
    $out  = array();
    $conf = ( $typ && ! empty( $typ['dokumenty'] ) ) ? $typ['dokumenty'] : array();
    foreach ( oe_dokumenty_wbudowane() as $key => $label ) {
        $c = isset( $conf[ $key ] ) ? $conf[ $key ] : array( 'tryb' => 'wbudowany', 'szablon_id' => 0, 'na_uczestnika' => false );
        if ( $c['tryb'] === 'wylaczony' ) continue;
        $out[] = array(
            'key'           => $key,
            'label'         => $label,
            'tryb'          => $c['tryb'],
            'szablon_id'    => (int) $c['szablon_id'],
            'na_uczestnika' => ! empty( $c['na_uczestnika'] ),
            'problem'       => ( $c['tryb'] === 'szablon' && ! oe_szablon_poprawny_id( $c['szablon_id'] ) ) ? 'brak pliku szablonu' : '',
        );
    }
    if ( $typ && ! empty( $typ['dokumenty_dodatkowe'] ) ) {
        foreach ( $typ['dokumenty_dodatkowe'] as $i => $d ) {
            $out[] = array(
                'key'           => 'dodatkowy_' . $i,
                'label'         => $d['nazwa'],
                'tryb'          => 'szablon',
                'szablon_id'    => (int) $d['szablon_id'],
                'na_uczestnika' => ! empty( $d['na_uczestnika'] ),
                'problem'       => oe_szablon_poprawny_id( $d['szablon_id'] ) ? '' : 'brak pliku szablonu',
            );
        }
    }
    return $out;
}

// ── Values ────────────────────────────────────────────────────────────────

/** Values for exam and organization placeholders. */
function oe_szablon_wartosci( $eg, $uu ) {
    $typ = $eg['rodzaj'];
    $komisja_linie = array();
    $przew = '';
    $sekr  = '';
    foreach ( $eg['komisja'] as $k ) {
        $imie = isset( $k['imie'] ) ? $k['imie'] : '';
        $rola = isset( $k['rola'] ) ? $k['rola'] : '';
        $komisja_linie[] = trim( $imie . ' - ' . $rola, ' -' );
        if ( $przew === '' && $rola === 'przewodniczący' ) $przew = $imie;
        if ( $sekr === '' && $rola === 'sekretarz' ) $sekr = $imie;
    }
    return array(
        'nr_egzaminu'        => (string) $eg['nr_egzaminu'],
        'rodzaj'             => (string) $eg['rodzaj_egzaminu'],
        'rodzaj_wielkie'     => oe_upper( (string) $eg['rodzaj_egzaminu'] ),
        'skrot'              => $typ ? $typ['skrot'] : '',
        'nr_decyzji'         => (string) $eg['nr_decyzji'],
        'data'               => (string) $eg['data_fmt'],
        'data_slownie'       => (string) $eg['data_sl'],
        'godzina'            => (string) $eg['godzina'],
        'miejscowosc'        => (string) $eg['miejscowosc'],
        'miejsce_teoria'     => (string) ( $eg['miejsce_teoria'] ?: $eg['miejsce_pelne'] ),
        'miejsce_praktyka'   => (string) ( $eg['miejsce_praktyka'] ?: $eg['miejsce_praktyczne'] ),
        'data_utworzenia'    => (string) $eg['data_utworzenia'],
        'miasto_data'        => oe_dok_miasto_data( $eg['data_utworzenia'] ),
        'liczba_uczestnikow' => (string) count( $uu ),
        'liczba_pytan'       => (string) ( ( $typ && $typ['liczba_pytan'] ) ? $typ['liczba_pytan'] : 75 ),
        'komisja'            => implode( "\n", $komisja_linie ),
        'przewodniczacy'     => $przew,
        'sekretarz'          => $sekr,
        'osoba_podpisujaca'  => (string) $eg['osoba_podpisujaca'],
        'org_nazwa'          => oe_org_nazwa_pelna(),
        'org_nazwa_krotka'   => oe_org_nazwa_krotka(),
        'org_nazwa_biernik'  => oe_org_nazwa_biernik(),
        'org_adres'          => oe_org_adres(),
        'org_ulica'          => oe_setting( 'org_ulica' ),
        'org_kod_miasto'     => oe_org_kod_miasto(),
        'org_rejestry'       => oe_org_rejestry(),
        'org_telefon'        => oe_setting( 'org_telefon' ),
    );
}

/** Values for one participant ({u.*}). $i is the 0-based position. */
function oe_szablon_wartosci_uczestnika( $u, $i, $eg ) {
    $def     = oe_rodzaj_def_losowania( $eg['rodzaj'] );
    $zadania = array();
    if ( $def['zadania'] ) {
        $poz = oe_losuj_zadania_v2( $u, $def );
        foreach ( $def['zadania'] as $idx => $z ) {
            if ( empty( $z['zawsze_nie'] ) && ( ! empty( $z['zawsze_poz'] ) || in_array( $idx, $poz ) ) ) {
                $zadania[] = $z['nazwa'];
            }
        }
    }
    return array(
        'u.lp'               => ( $i + 1 ) . '.',
        'u.imie'             => (string) $u['imie'],
        'u.nazwisko'         => (string) $u['nazwisko'],
        'u.imie_nazwisko'    => trim( $u['imie'] . ' ' . $u['nazwisko'] ),
        'u.data_ur'          => (string) $u['data_ur'],
        'u.miejsce_ur'       => (string) $u['msc_ur'],
        'u.adres'            => trim( $u['ulica'] . ', ' . trim( $u['kod'] . ' ' . $u['miasto'] ), ', ' ),
        'u.nr_zaswiadczenia' => str_pad( $i + 1, 3, '0', STR_PAD_LEFT ) . '/' . $eg['nr_egzaminu'],
        'u.zadania'          => implode( ', ', $zadania ),
    );
}

function oe_szablon_wartosci_komisji( $k, $i ) {
    return array(
        'k.lp'   => ( $i + 1 ) . '.',
        'k.imie' => isset( $k['imie'] ) ? (string) $k['imie'] : '',
        'k.rola' => isset( $k['rola'] ) ? (string) $k['rola'] : '',
    );
}

// ── Engine ────────────────────────────────────────────────────────────────

/** Closest ancestor of $node with the given w: local name, or null. */
function oe_docx_przodek( $node, $name ) {
    for ( $n = $node->parentNode; $n; $n = $n->parentNode ) {
        if ( $n->nodeType === XML_ELEMENT_NODE && $n->namespaceURI === OE_W_NS && $n->localName === $name ) return $n;
    }
    return null;
}

/**
 * Word often splits "{nr_egzaminu}" into several runs ("{nr_", "egzaminu}").
 * Join every placeholder back into the first text node it starts in.
 */
function oe_docx_sklej_znaczniki( DOMDocument $dom ) {
    $grupy = array();
    foreach ( $dom->getElementsByTagNameNS( OE_W_NS, 't' ) as $t ) {
        $p = oe_docx_przodek( $t, 'p' );
        if ( ! $p ) continue;
        $id = spl_object_id( $p );
        if ( ! isset( $grupy[ $id ] ) ) $grupy[ $id ] = array();
        $grupy[ $id ][] = $t;
    }
    foreach ( $grupy as $nodes ) {
        if ( count( $nodes ) < 2 ) continue;
        $full   = '';
        $starts = array();
        foreach ( $nodes as $n ) {
            $starts[] = strlen( $full );
            $full    .= $n->textContent;
        }
        if ( strpos( $full, '{' ) === false || ! preg_match_all( '/\{[A-Za-z_.]+\}/', $full, $m, PREG_OFFSET_CAPTURE ) ) continue;
        // Last match first, so earlier offsets stay valid.
        foreach ( array_reverse( $m[0] ) as $match ) {
            $start = $match[1];
            $end   = $start + strlen( $match[0] );
            $first = null;
            $last  = null;
            foreach ( $nodes as $i => $n ) {
                $ns = $starts[ $i ];
                $ne = $ns + strlen( $n->textContent );
                if ( $first === null && $start >= $ns && $start < $ne ) $first = $i;
                if ( $end > $ns && $end <= $ne ) $last = $i;
            }
            if ( $first === null || $last === null || $first === $last ) continue;
            $a = $nodes[ $first ];
            $b = $nodes[ $last ];
            $a_text = substr( $a->textContent, 0, $start - $starts[ $first ] ) . $match[0];
            $b_text = substr( $b->textContent, $end - $starts[ $last ] );
            oe_docx_ustaw_tekst( $a, $a_text );
            for ( $i = $first + 1; $i < $last; $i++ ) oe_docx_ustaw_tekst( $nodes[ $i ], '' );
            oe_docx_ustaw_tekst( $b, $b_text );
            // Recompute offsets for the remaining (earlier) matches.
            $full   = '';
            $starts = array();
            foreach ( $nodes as $n ) {
                $starts[] = strlen( $full );
                $full    .= $n->textContent;
            }
        }
    }
}

function oe_docx_ustaw_tekst( DOMElement $t, $text ) {
    while ( $t->firstChild ) $t->removeChild( $t->firstChild );
    $t->appendChild( $t->ownerDocument->createTextNode( $text ) );
    $t->setAttributeNS( 'http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve' );
}

/** Replace placeholders in every w:t under $root. Newlines in values become line breaks. */
function oe_docx_zamien( DOMNode $root, array $vals ) {
    $dom = $root instanceof DOMDocument ? $root : $root->ownerDocument;
    $ts  = array();
    foreach ( $root->getElementsByTagNameNS( OE_W_NS, 't' ) as $t ) $ts[] = $t;
    foreach ( $ts as $t ) {
        $text = $t->textContent;
        if ( strpos( $text, '{' ) === false ) continue;
        $new = preg_replace_callback( '/\{([A-Za-z_.]+)\}/', function( $m ) use ( $vals ) {
            return array_key_exists( $m[1], $vals ) ? (string) $vals[ $m[1] ] : $m[0];
        }, $text );
        if ( $new === $text ) continue;
        $lines = preg_split( '/\r\n|\r|\n/', $new );
        oe_docx_ustaw_tekst( $t, array_shift( $lines ) );
        $after = $t;
        foreach ( $lines as $line ) {
            $br = $dom->createElementNS( OE_W_NS, 'w:br' );
            $nt = $dom->createElementNS( OE_W_NS, 'w:t' );
            oe_docx_ustaw_tekst( $nt, $line );
            $after->parentNode->insertBefore( $br, $after->nextSibling );
            $br->parentNode->insertBefore( $nt, $br->nextSibling );
            $after = $nt;
        }
    }
}

/**
 * Make a cloned fragment safe to sit next to its original: Word rejects or
 * repairs documents with duplicate bookmark IDs and duplicate paragraph IDs.
 */
function oe_docx_oczysc_kopie( DOMNode $node ) {
    $usun = array();
    foreach ( array( 'bookmarkStart', 'bookmarkEnd' ) as $tag ) {
        foreach ( $node->getElementsByTagNameNS( OE_W_NS, $tag ) as $el ) $usun[] = $el;
    }
    if ( $node instanceof DOMElement && in_array( $node->localName, array( 'bookmarkStart', 'bookmarkEnd' ), true ) ) $usun[] = $node;
    foreach ( $usun as $el ) {
        if ( $el->parentNode ) $el->parentNode->removeChild( $el );
    }
    $w14   = 'http://schemas.microsoft.com/office/word/2010/wordml';
    $nodes = $node instanceof DOMElement ? array( $node ) : array();
    if ( $node instanceof DOMElement || $node instanceof DOMDocument ) {
        foreach ( $node->getElementsByTagName( '*' ) as $el ) $nodes[] = $el;
    }
    foreach ( $nodes as $el ) {
        if ( $el->hasAttributeNS( $w14, 'paraId' ) ) $el->removeAttributeNS( $w14, 'paraId' );
        if ( $el->hasAttributeNS( $w14, 'textId' ) ) $el->removeAttributeNS( $w14, 'textId' );
    }
}

/** Repeat every table row that mentions "{$prefix" once per item. */
function oe_docx_powiel_wiersze( DOMDocument $dom, $prefix, array $items, $callback ) {
    $rows = array();
    foreach ( $dom->getElementsByTagNameNS( OE_W_NS, 'tr' ) as $tr ) {
        if ( strpos( $tr->textContent, '{' . $prefix ) !== false ) $rows[] = $tr;
    }
    foreach ( $rows as $tr ) {
        foreach ( $items as $i => $item ) {
            $clone = $tr->cloneNode( true );
            oe_docx_oczysc_kopie( $clone );
            oe_docx_zamien( $clone, call_user_func( $callback, $item, $i ) );
            $tr->parentNode->insertBefore( $clone, $tr );
        }
        $tr->parentNode->removeChild( $tr );
    }
}

/**
 * Fill a DOCX template. Returns the path of a temporary filled file, or WP_Error.
 */
function oe_szablon_wypelnij( $template_path, $eg, $uu, $na_uczestnika ) {
    if ( ! class_exists( 'ZipArchive' ) ) return new WP_Error( 'oe_zip', 'ZipArchive nie jest dostępne.' );
    if ( ! is_readable( $template_path ) ) return new WP_Error( 'oe_brak', 'Nie można odczytać pliku szablonu.' );

    $tmp = tempnam( get_temp_dir(), 'oe_szablon_' );
    if ( ! $tmp || ! copy( $template_path, $tmp ) ) return new WP_Error( 'oe_tmp', 'Nie można utworzyć pliku tymczasowego.' );
    $zip = new ZipArchive();
    if ( $zip->open( $tmp ) !== true || $zip->locateName( 'word/document.xml' ) === false ) {
        @unlink( $tmp );
        return new WP_Error( 'oe_docx', 'Plik szablonu nie jest poprawnym dokumentem DOCX.' );
    }

    $globalne = oe_szablon_wartosci( $eg, $uu );
    $czesci   = array( 'word/document.xml' );
    for ( $i = 0; $i < $zip->numFiles; $i++ ) {
        $name = $zip->getNameIndex( $i );
        if ( preg_match( '#^word/(header|footer)\d*\.xml$#', $name ) ) $czesci[] = $name;
    }

    foreach ( $czesci as $czesc ) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        if ( ! @$dom->loadXML( $zip->getFromName( $czesc ), LIBXML_NONET ) ) {
            $zip->close();
            @unlink( $tmp );
            return new WP_Error( 'oe_xml', 'Nie można odczytać ' . $czesc . ' z szablonu.' );
        }
        oe_docx_sklej_znaczniki( $dom );

        if ( $czesc === 'word/document.xml' ) {
            oe_docx_powiel_wiersze( $dom, 'k.', $eg['komisja'], 'oe_szablon_wartosci_komisji' );
            if ( $na_uczestnika ) {
                oe_docx_kopia_na_uczestnika( $dom, $eg, $uu );
            } else {
                oe_docx_powiel_wiersze( $dom, 'u.', $uu, function( $u, $i ) use ( $eg ) {
                    return oe_szablon_wartosci_uczestnika( $u, $i, $eg );
                } );
            }
        }
        oe_docx_zamien( $dom, $globalne );
        $zip->addFromString( $czesc, $dom->saveXML() );
    }
    $zip->close();
    return $tmp;
}

/** Repeat the whole body once per participant, separated by page breaks. */
function oe_docx_kopia_na_uczestnika( DOMDocument $dom, $eg, $uu ) {
    $body = $dom->getElementsByTagNameNS( OE_W_NS, 'body' )->item( 0 );
    if ( ! $body ) return;
    $tresc = array();
    $sect  = null;
    foreach ( iterator_to_array( $body->childNodes ) as $ch ) {
        if ( $ch->nodeType === XML_ELEMENT_NODE && $ch->localName === 'sectPr' ) {
            $sect = $ch;
            continue;
        }
        $tresc[] = $ch;
    }
    foreach ( $uu as $i => $u ) {
        if ( $i > 0 ) {
            $p  = $dom->createElementNS( OE_W_NS, 'w:p' );
            $r  = $dom->createElementNS( OE_W_NS, 'w:r' );
            $br = $dom->createElementNS( OE_W_NS, 'w:br' );
            $br->setAttributeNS( OE_W_NS, 'w:type', 'page' );
            $r->appendChild( $br );
            $p->appendChild( $r );
            $body->insertBefore( $p, $sect );
        }
        $vals = oe_szablon_wartosci_uczestnika( $u, $i, $eg );
        foreach ( $tresc as $node ) {
            $clone = $node->cloneNode( true );
            if ( $i > 0 ) oe_docx_oczysc_kopie( $clone );
            $body->insertBefore( $clone, $sect );
            oe_docx_zamien( $clone, $vals );
        }
    }
    foreach ( $tresc as $node ) $body->removeChild( $node );
}

/** Send a finished DOCX file to the browser and delete it. */
function oe_docx_wyslij( $path, $filename ) {
    header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'Pragma: no-cache' );
    readfile( $path );
    @unlink( $path );
    exit;
}

// ── Exam type editor: documents section ───────────────────────────────────

add_action( 'admin_enqueue_scripts', function( $hook ) {
    $screen = get_current_screen();
    if ( $screen && $screen->post_type === 'oe_rodzaj' && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        wp_enqueue_media();
    }
} );

/** Template picker cell: hidden ID, file name, choose and clear buttons. */
function oe_szablon_pole_pliku( $name, $id ) {
    $id   = oe_szablon_poprawny_id( $id );
    $file = $id ? basename( get_attached_file( $id ) ) : '';
    ?>
    <span class="oe-szablon-pole">
        <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo (int) $id; ?>">
        <span class="oe-szablon-nazwa"><?php echo $file !== '' ? esc_html( $file ) : '<em>brak pliku</em>'; ?></span>
        <button type="button" class="button button-small oe-szablon-wybierz">Wybierz plik DOCX</button>
        <button type="button" class="button-link oe-szablon-usun" <?php echo $id ? '' : 'style="display:none"'; ?>>usuń</button>
    </span>
    <?php
}

function oe_metabox_rodzaj_dokumenty( $t ) {
    $docs  = $t['dokumenty'];
    $extra = $t['dokumenty_dodatkowe'];
    $extra[] = array( 'nazwa' => '', 'szablon_id' => 0, 'na_uczestnika' => false ); // one empty row for a new document
    $sample = wp_nonce_url( admin_url( 'admin-post.php?action=oe_szablon_przyklad' ), 'oe_szablon_przyklad' );
    ?>
    <tr>
        <th scope="row">Dokumenty</th>
        <td>
            <p class="description" style="margin-top:0">Wbudowany układ to standardowy formularz. Własny szablon to plik DOCX ze znacznikami, wypełniany danymi egzaminu. <a href="<?php echo esc_url( $sample ); ?>">Pobierz przykładowy szablon</a> ze wszystkimi znacznikami.</p>
            <table class="widefat striped" style="max-width:900px">
                <thead><tr><th>Dokument</th><th>Tryb</th><th>Plik szablonu</th><th>Jeden egzemplarz na uczestnika</th></tr></thead>
                <tbody>
                <?php foreach ( oe_dokumenty_wbudowane() as $key => $label ) :
                    $c    = $docs[ $key ];
                    $base = 'oe_rodzaj[dokumenty][' . $key . ']';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><select name="<?php echo esc_attr( $base ); ?>[tryb]">
                            <option value="wbudowany" <?php selected( $c['tryb'], 'wbudowany' ); ?>>wbudowany</option>
                            <option value="szablon" <?php selected( $c['tryb'], 'szablon' ); ?>>własny szablon</option>
                            <option value="wylaczony" <?php selected( $c['tryb'], 'wylaczony' ); ?>>wyłączony</option>
                        </select></td>
                        <td><?php oe_szablon_pole_pliku( $base . '[szablon_id]', $c['szablon_id'] ); ?></td>
                        <td><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[na_uczestnika]" value="1" <?php checked( $c['na_uczestnika'] ); ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin:16px 0 6px"><strong>Dodatkowe dokumenty z szablonów</strong> <span class="description">(aby usunąć, wyczyść nazwę i plik; po zapisaniu pojawi się kolejny pusty wiersz)</span></p>
            <table class="widefat striped" style="max-width:900px">
                <thead><tr><th>Nazwa</th><th>Plik szablonu</th><th>Jeden egzemplarz na uczestnika</th></tr></thead>
                <tbody>
                <?php foreach ( $extra as $i => $d ) :
                    $base = 'oe_rodzaj[dokumenty_dodatkowe][' . $i . ']';
                    ?>
                    <tr>
                        <td><input type="text" name="<?php echo esc_attr( $base ); ?>[nazwa]" value="<?php echo esc_attr( $d['nazwa'] ); ?>" placeholder="<?php echo $d['nazwa'] === '' ? 'np. Lista obecności' : ''; ?>" class="regular-text"></td>
                        <td><?php oe_szablon_pole_pliku( $base . '[szablon_id]', $d['szablon_id'] ); ?></td>
                        <td><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[na_uczestnika]" value="1" <?php checked( $d['na_uczestnika'] ); ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <details style="margin-top:12px">
                <summary style="cursor:pointer"><strong>Znaczniki dostępne w szablonach</strong></summary>
                <p class="description">Wpisz znacznik w nawiasach klamrowych, np. <code>{nr_egzaminu}</code>. Wiersz tabeli z dowolnym znacznikiem <code>{u.…}</code> powtarza się dla każdego zatwierdzonego uczestnika, z <code>{k.…}</code> dla każdego członka komisji. Nieznane znaczniki zostają w dokumencie bez zmian.</p>
                <?php foreach ( oe_szablon_znaczniki() as $grupa => $lista ) : ?>
                    <p style="margin:10px 0 4px"><strong><?php echo esc_html( $grupa ); ?></strong></p>
                    <table class="widefat" style="max-width:900px"><tbody>
                    <?php foreach ( $lista as $z => $opis ) : ?>
                        <tr><td style="width:220px"><code>{<?php echo esc_html( $z ); ?>}</code></td><td><?php echo esc_html( $opis ); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endforeach; ?>
            </details>

            <script>
            (function(){
                document.querySelectorAll('.oe-szablon-pole').forEach(function(box){
                    var input = box.querySelector('input'), label = box.querySelector('.oe-szablon-nazwa'), clear = box.querySelector('.oe-szablon-usun');
                    box.querySelector('.oe-szablon-wybierz').addEventListener('click', function(){
                        if (!window.wp || !wp.media) return;
                        var frame = wp.media({ title: 'Wybierz szablon DOCX', multiple: false, library: { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' } });
                        frame.on('select', function(){
                            var a = frame.state().get('selection').first().toJSON();
                            if (!/\.docx$/i.test(a.filename || a.url || '')) { alert('Wybierz plik .docx'); return; }
                            input.value = a.id;
                            label.textContent = a.filename;
                            clear.style.display = '';
                        });
                        frame.open();
                    });
                    clear.addEventListener('click', function(){
                        input.value = '0';
                        label.innerHTML = '<em>brak pliku</em>';
                        clear.style.display = 'none';
                    });
                });
            })();
            </script>
        </td>
    </tr>
    <?php
}

// ── Sample template ───────────────────────────────────────────────────────

add_action( 'admin_post_oe_szablon_przyklad', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Brak uprawnień.' );
    check_admin_referer( 'oe_szablon_przyklad' );

    $d   = new OE_Docx();
    $big = array( 'bold' => true, 'size' => 24 );
    $d->pRuns( array( array( 'text' => '{miasto_data}' ) ), array( 'align' => 'right', 'after' => 120 ) );
    $d->pRuns( array( array( 'text' => '{org_nazwa}', 'bold' => true ) ), array( 'align' => 'center', 'after' => 40 ) );
    $d->p( '{org_adres}', array( 'align' => 'center', 'after' => 40 ) );
    $d->p( '{org_rejestry}', array( 'align' => 'center', 'after' => 240 ) );
    $d->pRuns( array( array_merge( array( 'text' => 'PRZYKŁADOWY SZABLON - {rodzaj_wielkie}' ), $big ) ), array( 'align' => 'center', 'after' => 120 ) );
    $d->p( 'Egzamin nr {nr_egzaminu}, decyzja {nr_decyzji}, {data} godz. {godzina}, {miejscowosc}.', array( 'after' => 60 ) );
    $d->p( 'Teoria: {miejsce_teoria}. Praktyka: {miejsce_praktyka}.', array( 'after' => 200 ) );

    $d->pRuns( array( array( 'text' => 'Uczestnicy ({liczba_uczestnikow}) - ten wiersz tabeli powtarza się dla każdej osoby:', 'bold' => true ) ), array( 'after' => 80 ) );
    $cw = array( 600, 2600, 2400, 3266, 1600 );
    $h  = array( 'textOpts' => array( 'bold' => true, 'size' => 18 ) );
    $d->table( array(
        array( OE_Docx::tc( 'Lp.', $cw[0], $h ), OE_Docx::tc( 'Imię i nazwisko', $cw[1], $h ), OE_Docx::tc( 'Data i miejsce ur.', $cw[2], $h ), OE_Docx::tc( 'Adres', $cw[3], $h ), OE_Docx::tc( 'Wynik', $cw[4], $h ) ),
        array( OE_Docx::tc( '{u.lp}', $cw[0] ), OE_Docx::tc( '{u.imie_nazwisko}', $cw[1] ), OE_Docx::tc( '{u.data_ur} {u.miejsce_ur}', $cw[2] ), OE_Docx::tc( '{u.adres}', $cw[3] ), OE_Docx::tc( 'poz.', $cw[4] ) ),
    ), $cw );
    $d->br();
    $d->pRuns( array( array( 'text' => 'Komisja - ten wiersz powtarza się dla każdego członka:', 'bold' => true ) ), array( 'after' => 80 ) );
    $cw2 = array( 3500, 4000, 2966 );
    $d->table( array(
        array( OE_Docx::tc( 'Funkcja', $cw2[0], $h ), OE_Docx::tc( 'Imię i nazwisko', $cw2[1], $h ), OE_Docx::tc( 'Podpis', $cw2[2], $h ) ),
        array( OE_Docx::tc( '{k.rola}', $cw2[0] ), OE_Docx::tc( '{k.imie}', $cw2[1] ), OE_Docx::tc( '', $cw2[2] ) ),
    ), $cw2 );

    $d->pageBreak();
    $d->pRuns( array( array_merge( array( 'text' => 'Wszystkie znaczniki' ), $big ) ), array( 'after' => 120 ) );
    $d->p( 'Usuń tę stronę w swoim szablonie. Poniższe nazwy wpisuj w nawiasach klamrowych, np. nr_egzaminu jako {nr_egzaminu}. Wiersz tabeli z którymkolwiek znacznikiem u. powtarza się dla każdego uczestnika, a z k. dla każdego członka komisji. Przy opcji "jeden egzemplarz na uczestnika" cały dokument powtarza się dla każdej osoby (każda na nowej stronie), a znaczniki u. działają w dowolnym miejscu.', array( 'size' => 18, 'after' => 160 ) );
    foreach ( oe_szablon_znaczniki() as $grupa => $lista ) {
        $d->pRuns( array( array( 'text' => $grupa, 'bold' => true ) ), array( 'before' => 120, 'after' => 60 ) );
        foreach ( $lista as $z => $opis ) {
            // Names without braces, so this list is not filled in if the page stays in a template.
            $d->pRuns( array( array( 'text' => $z, 'bold' => true, 'size' => 18 ), array( 'text' => '  ' . $opis, 'size' => 18 ) ), array( 'after' => 20 ) );
        }
    }
    $d->download( 'szablon-przyklad.docx' );
} );
