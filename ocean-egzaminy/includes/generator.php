<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Metabox z przyciskami ─────────────────────────────────────────────────

add_action( 'add_meta_boxes', function() {
    if ( ! current_user_can( 'oe_generate_documents' ) ) return;
    add_meta_box( 'oe_generuj_dokumenty', 'Generuj dokumenty DOCX', 'oe_metabox_generuj', 'oe_egzamin', 'side', 'default' );
} );

function oe_metabox_generuj( $post ) {
    if ( $post->post_status !== 'publish' ) {
        echo '<p style="font-size:12px;color:#ba7517">Opublikuj egzamin aby generować dokumenty.</p>';
        return;
    }
    $ikony = array(
        'zgloszenie'    => '📋',
        'protokol'      => '📄',
        'zal1'          => '📊',
        'zal2'          => '⛵',
        'zal3'          => '📜',
        'karty'         => '🪪',
        'zaswiadczenia' => '🏅',
        'arkusze'       => '📝',
        'arkusze_wzor'  => '📋',
    );
    $dokumenty = oe_egzamin_dokumenty( oe_egzamin_rodzaj( $post->ID ) );
    $n_uczestnikow = count( oe_get_uu( $post->ID ) );
    $url_zapisy    = admin_url( 'edit.php?post_type=oe_zapis&oe_egzamin_id=' . $post->ID );
    echo '<style>.oe-gb{display:flex;align-items:center;gap:8px;width:100%;margin-bottom:6px;padding:7px 10px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;font-size:13px;font-family:inherit;text-decoration:none;color:#1e1e1e}.oe-gb:hover{background:#e8f5e9;border-color:#a5d6a7;color:#1e1e1e}.oe-gb-off{opacity:.6;cursor:not-allowed}</style>';
    if ( $n_uczestnikow ) {
        echo '<p style="font-size:12px;margin:0 0 8px">Zatwierdzeni uczestnicy: <strong>' . (int) $n_uczestnikow . '</strong></p>';
    } else {
        echo '<p style="font-size:12px;margin:0 0 8px;padding:8px;background:#fff8e5;border-left:3px solid #dba617">Brak zatwierdzonych uczestników. Karty, zaświadczenia i arkusze tworzą stronę dla każdej zatwierdzonej osoby. <a href="' . esc_url( $url_zapisy ) . '">Zatwierdź zapisy</a>.</p>';
    }
    if ( ! $dokumenty ) {
        echo '<p style="font-size:12px;color:#888">Wszystkie dokumenty są wyłączone w typie egzaminu.</p>';
    }
    foreach ( $dokumenty as $dok ) {
        $ikona = isset( $ikony[ $dok['key'] ] ) && $dok['tryb'] === 'wbudowany' ? $ikony[ $dok['key'] ] : '🗎';
        $opis  = $dok['tryb'] === 'szablon' ? 'szablon' : '';
        if ( $dok['problem'] === '' && ! $n_uczestnikow && oe_dokument_na_uczestnika( $dok ) ) {
            $dok['problem'] = 'brak zatwierdzonych';
        }
        if ( $dok['problem'] !== '' ) {
            echo "<span class='oe-gb oe-gb-off' title='" . esc_attr( $dok['problem'] ) . "'><span style='font-size:16px'>{$ikona}</span><span>" . esc_html( $dok['label'] ) . "</span><span style='margin-left:auto;font-size:11px;color:#b32d2e'>" . esc_html( $dok['problem'] ) . "</span></span>";
            continue;
        }
        $url = wp_nonce_url( admin_url( "admin-post.php?action=oe_generuj_docx&egzamin_id={$post->ID}&typ=" . rawurlencode( $dok['key'] ) ), 'oe_generuj_'.$post->ID );
        echo "<a href='".esc_url($url)."' class='oe-gb'><span style='font-size:16px'>{$ikona}</span><span>".esc_html($dok['label'])."</span><span style='margin-left:auto;font-size:11px;color:#999'>" . esc_html( trim( $opis . ' ↓ docx' ) ) . "</span></a>";
    }
    echo '<p style="font-size:11px;color:#888;margin-top:8px">Tylko zatwierdzeni uczestnicy.</p>';
}

/** True for documents that contain one page per approved participant (empty without participants). */
function oe_dokument_na_uczestnika( $dok ) {
    if ( $dok['tryb'] === 'szablon' ) return ! empty( $dok['na_uczestnika'] );
    return in_array( $dok['key'], array( 'karty', 'zaswiadczenia', 'arkusze' ), true );
}

// ── Handler ───────────────────────────────────────────────────────────────

add_action( 'admin_post_oe_generuj_docx', 'oe_handle_generuj_docx' );

function oe_handle_generuj_docx() {
    $eid = isset($_GET['egzamin_id']) ? intval($_GET['egzamin_id']) : 0;
    $typ = isset($_GET['typ'])        ? sanitize_text_field($_GET['typ']) : '';
    if (!$eid || !$typ) wp_die('Nieprawidłowe żądanie.');
    check_admin_referer('oe_generuj_'.$eid);
    if (!current_user_can('oe_generate_documents')) wp_die('Brak uprawnień.');

    $eg = oe_get_eg($eid);
    $uu = oe_get_uu($eid);

    // Format nazwy pliku: RRRR_MM_DD_SKROT_MIASTO
    $skrot  = ($eg['rodzaj'] && $eg['rodzaj']['skrot'] !== '') ? $eg['rodzaj']['skrot'] : 'EGZ';
    $miasto = oe_ascii(oe_upper(preg_replace('/[^a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/', '', $eg['miejscowosc'])));
    $data_p = $eg['data_egzaminu'] ? date('Y_m_d', strtotime($eg['data_egzaminu'].'T00:00:00')) : date('Y_m_d');
    $prefix = "{$data_p}_{$skrot}_{$miasto}";
    $nr_s   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $eg['nr_egzaminu'] ?? 'egzamin');

    $fn_map = array(
        'zgloszenie'    => "{$prefix}.docx",
        'protokol'      => "{$prefix}_protokol_KE.docx",
        'zal1'          => "{$prefix}_zal1_wyniki.docx",
        'zal2'          => "{$prefix}_zal2_praktyka.docx",
        'zal3'          => "{$prefix}_zal3_zaswiadczenia.docx",
        'karty'         => "{$prefix}_karty_egzaminacyjne.docx",
        'zaswiadczenia' => "{$prefix}_zaswiadczenia.docx",
        'arkusze'       => "{$prefix}_arkusze_odpowiedzi.docx",
        'arkusze_wzor'  => "{$prefix}_arkusz_wzor1.docx",
    );
    // Which documents this exam's type offers, and how (built-in layout or template).
    $dok = null;
    foreach (oe_egzamin_dokumenty($eg['rodzaj']) as $d) {
        if ($d['key'] === $typ) $dok = $d;
    }
    if (!$dok) wp_die('Ten dokument jest wyłączony dla tego typu egzaminu.');
    if (!$uu && oe_dokument_na_uczestnika($dok)) {
        wp_die(
            '<p><strong>Brak zatwierdzonych uczestników.</strong></p><p>Dokument „' . esc_html($dok['label']) . '” zawiera stronę dla każdej zatwierdzonej osoby, więc bez nich byłby pusty. Zmień status zapisów na „Zatwierdzony”.</p>'
            . '<p><a href="' . esc_url(admin_url('edit.php?post_type=oe_zapis&oe_egzamin_id=' . $eid)) . '">Przejdź do zapisów tego egzaminu</a></p>',
            'Brak zatwierdzonych uczestników',
            array('response' => 200, 'back_link' => true)
        );
    }

    if ($dok['tryb'] === 'szablon') {
        $fn = isset($fn_map[$typ]) ? $fn_map[$typ] : $prefix . '_' . sanitize_file_name(oe_ascii(str_replace(' ', '_', $dok['label']))) . '.docx';
        $sid = oe_szablon_poprawny_id($dok['szablon_id']);
        if (!$sid) wp_die('Brak pliku szablonu dla dokumentu „' . esc_html($dok['label']) . '”. Wybierz plik w ustawieniach typu egzaminu.');
        $plik = oe_szablon_wypelnij(get_attached_file($sid), $eg, $uu, $dok['na_uczestnika']);
        if (is_wp_error($plik)) wp_die(esc_html($plik->get_error_message()));
        oe_docx_wyslij($plik, $fn);
    }

    $fn = isset($fn_map[$typ]) ? $fn_map[$typ] : "{$prefix}_{$typ}.docx";

    if      ($typ==='zgloszenie')    oe_doc_zgloszenie($eg,$fn);
    elseif  ($typ==='protokol')      oe_doc_protokol($eg,$uu,$fn);
    elseif  ($typ==='zal1')          oe_doc_zal1($eg,$uu,$fn);
    elseif  ($typ==='zal2')          oe_doc_zal2($eg,$uu,$fn);
    elseif  ($typ==='zal3')          oe_doc_zal3($eg,$uu,$fn);
    elseif  ($typ==='karty')         oe_doc_karty($eg,$uu,$fn);
    elseif  ($typ==='zaswiadczenia') oe_doc_zaswiadczenia($eg,$uu,$fn);
    elseif  ($typ==='arkusze')       oe_doc_arkusze($eg,$uu,$fn);
    elseif  ($typ==='arkusze_wzor')  oe_doc_arkusze_wzor($eg,$fn);
    else    wp_die('Nieznany typ.');
}

// ── Dane ──────────────────────────────────────────────────────────────────

function oe_get_eg($id) {
    $kom = get_post_meta($id,'_oe_komisja',true);
    $d   = get_post_meta($id,'_oe_data_egzaminu',true);
    $p   = get_post($id);
    $typ = oe_egzamin_rodzaj($id);
    return array(
        'nr_egzaminu'        => get_post_meta($id,'_oe_nr_egzaminu',true),
        'nr_decyzji'         => get_post_meta($id,'_oe_nr_decyzji',true) ?: ($typ ? $typ['nr_decyzji'] : ''),
        'rodzaj'             => $typ,
        'rodzaj_egzaminu'    => $typ ? $typ['nazwa'] : get_post_meta($id,'_oe_rodzaj_egzaminu',true),
        'data_egzaminu'      => $d,
        'data_fmt'           => oe_dfmt($d),
        'data_sl'            => oe_dsl($d),
        'data_utworzenia'    => $p ? oe_dfmt(substr($p->post_date,0,10)) : '',
        'godzina'            => get_post_meta($id,'_oe_godzina',true),
        'miejscowosc'        => get_post_meta($id,'_oe_miejscowosc',true),
        'miejsce_pelne'      => get_post_meta($id,'_oe_miejsce_pelne',true),
        'miejsce_praktyczne' => get_post_meta($id,'_oe_miejsce_praktyczne',true),
        'miejsce_teoria'     => get_post_meta($id,'_oe_miejsce_teoria',true),
        'miejsce_praktyka'   => get_post_meta($id,'_oe_miejsce_praktyka',true),
        'nr_konta'           => get_post_meta($id,'_oe_nr_konta',true),
        'wlasciciel_konta'   => get_post_meta($id,'_oe_wlasciciel_konta',true),
        'komisja'            => is_array($kom) ? $kom : array(),
        'osoba_podpisujaca'  => (is_array($kom) && !empty($kom)) ? (isset($kom[1]['imie']) ? $kom[1]['imie'] : (isset($kom[0]['imie']) ? $kom[0]['imie'] : '')) : '',
    );
}

function oe_get_uu($id) {
    $zz = get_posts(array(
        'post_type'      => 'oe_zapis',
        'post_status'    => array('oe_zatwierdzony'),
        'meta_query'     => array(array('key'=>'_oe_egzamin_id','value'=>$id)),
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ));
    $result = array();
    foreach ($zz as $z) {
        $result[] = array(
            'imie'    => get_post_meta($z->ID,'_oe_imie',true),
            'nazwisko'=> get_post_meta($z->ID,'_oe_nazwisko',true),
            'data_ur' => oe_dfmt(get_post_meta($z->ID,'_oe_data_urodzenia',true)),
            'msc_ur'  => get_post_meta($z->ID,'_oe_miejsce_urodzenia',true),
            'ulica'   => get_post_meta($z->ID,'_oe_ulica',true),
            'kod'     => get_post_meta($z->ID,'_oe_kod',true),
            'miasto'  => get_post_meta($z->ID,'_oe_miasto',true),
        );
    }
    return $result;
}

function oe_dfmt($d) {
    if(!$d) return '';
    return date('d.m.Y', strtotime($d.'T00:00:00')).' r.';
}
function oe_dsl($d) {
    if(!$d) return '';
    $m=array('','stycznia','lutego','marca','kwietnia','maja','czerwca','lipca','sierpnia','września','października','listopada','grudnia');
    $ts=strtotime($d.'T00:00:00');
    return (int)date('j',$ts).' '.$m[(int)date('n',$ts)].' '.date('Y',$ts).' r';
}

// ── Bloki wspólne ─────────────────────────────────────────────────────────

function oe_upper($str) {
    return mb_strtoupper($str, 'UTF-8');
}

function oe_ascii($str) {
    $from = array('ą','ć','ę','ł','ń','ó','ś','ź','ż','Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż');
    $to   = array('a','c','e','l','n','o','s','z','z','A','C','E','L','N','O','S','Z','Z');
    return str_replace($from, $to, $str);
}



// Nagłówek fundacji (wyśrodkowany)
function oe_hdr(OE_Docx $d, $eg) {
    $d->pRuns(array(array('text'=>oe_org_nazwa_pelna(),'bold'=>true,'size'=>18)),array('align'=>'center','before'=>0,'after'=>80));
    $d->pRuns(array(array('text'=>oe_org_adres(),'size'=>16)),array('align'=>'center','after'=>40));
    if (oe_org_rejestry() !== '') {
        $d->pRuns(array(array('text'=>oe_org_rejestry(),'size'=>16)),array('align'=>'center','after'=>40));
    }
    $d->pRuns(array(array('text'=>'DECYZJA MSiT'.($eg['nr_decyzji']??''),'bold'=>true,'size'=>16)),array('align'=>'center','after'=>280));
}

// Miejscowość + data (wyrównane do prawej)
function oe_msd(OE_Docx $d, $eg, $data_override='') {
    $data = $data_override ? $data_override : $eg['data_fmt'];
    $d->pRuns(array(array('text'=>($eg['miejscowosc']??'').', '.$data)),array('align'=>'right','after'=>80));
}

// Tabela składu komisji (pod dokumentem)
function oe_tabela_komisji(OE_Docx $d, $eg) {
    $komisja = $eg['komisja'];
    if (empty($komisja)) return;
    $cw = array(3500,4000,2966);
    $rows = array(array(
        OE_Docx::tc('Skład Komisji Egzaminacyjnej',$cw[0],array('textOpts'=>array('bold'=>true,'size'=>18))),
        OE_Docx::tc('Imię i Nazwisko',$cw[1],array('textOpts'=>array('bold'=>true,'size'=>18))),
        OE_Docx::tc('Podpis',$cw[2],array('textOpts'=>array('bold'=>true,'size'=>18))),
    ));
    foreach ($komisja as $k) {
        $rows[]=array(
            OE_Docx::tc(ucfirst(isset($k['rola'])?$k['rola']:'').' KE',$cw[0]),
            OE_Docx::tc(isset($k['imie'])?$k['imie']:'',$cw[1]),
            OE_Docx::tc('',$cw[2]),
        );
    }
    $d->br();
    $d->table($rows,$cw);
}

// ── ZGŁOSZENIE EGZAMINU ───────────────────────────────────────────────────

function oe_doc_zgloszenie($eg,$fn) {
    $d = new OE_Docx();
    $komisja = $eg['komisja'];

    // Data i miejsce wystawienia - prawy górny róg (samo, bez podpisu)
    $d->pRuns(array(array('text'=>oe_dok_miasto_data($eg['data_utworzenia']),'size'=>18)),array('align'=>'right','after'=>160));

    // Nagłówek organizacji po lewej (nie wyśrodkowany jak w innych)
    $d->pRuns(array(array('text'=>oe_org_nazwa_pelna(),'bold'=>true,'size'=>18)),array('after'=>40));
    $d->pRuns(array(array('text'=>oe_setting('org_ulica'),'size'=>18)),array('after'=>20));
    $d->pRuns(array(array('text'=>oe_org_kod_miasto(),'size'=>18)),array('after'=>240));

    $d->pRuns(array(array('text'=>'ZGŁOSZENIE EGZAMINU W ZAKRESIE','bold'=>true,'size'=>24)),array('align'=>'center','after'=>40));
    $d->pRuns(array(array('text'=>'UPRAWIANIA TURYSTYKI WODNEJ','bold'=>true,'size'=>24)),array('align'=>'center','after'=>200));

    $d->pRuns(array(array('text'=>oe_upper($eg['rodzaj_egzaminu']),'bold'=>true,'italic'=>true,'size'=>22)),array('align'=>'center','after'=>40));
    $d->pRuns(array(array('text'=>'Decyzja: '.($eg['nr_decyzji']??''),'bold'=>true,'italic'=>true,'size'=>20)),array('align'=>'center','after'=>40));
    $d->pRuns(array(array('text'=>'(nazwa patentu/licencji na który ma być przeprowadzony egzamin)','italic'=>true,'size'=>16)),array('align'=>'center','after'=>200));

    // Tabela główna
    $komisja_str = '';
    foreach ($komisja as $k) {
        $rola = oe_upper(isset($k['rola'])?$k['rola']:'');
        $imie = oe_upper(isset($k['imie'])?$k['imie']:'');
        $komisja_str .= $imie.' – '.$rola."\n";
    }
    $komisja_str = trim($komisja_str);

    $miejsce_str = $eg['miejsce_teoria'] ?: ($eg['miejsce_pelne'] ?? '');
    if ($eg['miejsce_praktyka'] ?: ($eg['miejsce_praktyczne'] ?? '')) {
        $miejsce_str .= "\n\n".($eg['miejsce_praktyka'] ?: ($eg['miejsce_praktyczne'] ?? ''));
    }

    $cw = array(440, 1600, 2600, 3400, 1820);
    $rows = array(
        array(
            OE_Docx::tc('L.p.',$cw[0],array('textOpts'=>array('bold'=>true,'size'=>16,'italic'=>true))),
            OE_Docx::tc('Data i godzina egzaminu',$cw[1],array('textOpts'=>array('bold'=>true,'size'=>16,'italic'=>true))),
            OE_Docx::tc('Miejsce egzaminu',$cw[2],array('textOpts'=>array('bold'=>true,'size'=>16,'italic'=>true))),
            OE_Docx::tc('Skład Komisji Egzaminacyjnej (z podaniem funkcji członków KE)',$cw[3],array('textOpts'=>array('bold'=>true,'size'=>16,'italic'=>true))),
            OE_Docx::tc('Nr egzaminu (w rejestrze podmiotu uprawnionego)',$cw[4],array('textOpts'=>array('bold'=>true,'size'=>16,'italic'=>true))),
        ),
        array(
            OE_Docx::tc('1.',$cw[0],array('textOpts'=>array('bold'=>true,'size'=>18))),
            OE_Docx::tcP(array(
                $d->buildP($eg['data_fmt'],array('bold'=>true,'size'=>18)),
                $d->buildP(''),
                $d->buildP('godz. '.$eg['godzina'],array('bold'=>true,'size'=>18)),
            ),$cw[1]),
            OE_Docx::tcP(array_filter(array(
                $eg['miejsce_teoria']   ? $d->buildP($eg['miejsce_teoria'],   array('bold'=>true,'size'=>16)) : null,
                $eg['miejsce_praktyka'] ? $d->buildP($eg['miejsce_praktyka'], array('bold'=>true,'size'=>16)) : null,
            )),$cw[2]),
            OE_Docx::tcP(array_merge(
                array(),
                array_map(function($k) use ($d) {
                    $rola = oe_upper(isset($k['rola'])?$k['rola']:'');
                    $imie = oe_upper(isset($k['imie'])?$k['imie']:'');
                    return $d->buildP($imie.' – '.$rola,array('bold'=>true,'size'=>16));
                }, $komisja)
            ),$cw[3]),
            OE_Docx::tc(isset($eg['nr_egzaminu']) ? $eg['nr_egzaminu'] : '',$cw[4],array('textOpts'=>array('bold'=>true,'size'=>18))),
        ),
    );
    $d->table($rows,$cw);

    $d->br(2);

    // Stopka - pełna
    $d->pRuns(array(array('text'=>oe_dok_miasto_data($eg['data_utworzenia']),'italic'=>true,'size'=>18)),array('after'=>20));
    $d->pRuns(array(array('text'=>'(miejsce i data wystawienia zgłoszenia)','italic'=>true,'size'=>16)),array('after'=>120));
    $d->pRuns(array(array('text'=>$eg['osoba_podpisujaca']??'','italic'=>true,'size'=>18)),array('after'=>20));
    $d->pRuns(array(array('text'=>'(imię i nazwisko osoby upoważnionej do sporządzenia informacji)','italic'=>true,'size'=>16)),array('after'=>0));

    $d->download($fn);
}

// ── PROTOKÓŁ ──────────────────────────────────────────────────────────────

function oe_doc_protokol($eg,$uu,$fn) {
    $d = new OE_Docx();
    $komisja = $eg['komisja'];
    $n = count($uu);

    oe_msd($d,$eg);
    oe_hdr($d,$eg);

    $d->pRuns(array(array('text'=>'PROTOKÓŁ KOMISJI EGZAMINACYJNEJ','bold'=>true,'size'=>26)),array('align'=>'center','before'=>400,'after'=>200));
    $d->pRuns(array(array('text'=>'z egzaminu na patent '.$eg['rodzaj_egzaminu'],'size'=>20)),array('align'=>'center','after'=>60));
    $d->pRuns(array(array('text'=>'nr '.$eg['nr_egzaminu'],'bold'=>true,'size'=>20)),array('align'=>'center','after'=>200));

    $d->pRuns(array(
        array('text'=>'W dniu '.$eg['data_sl'].', o godz. '.$eg['godzina'].', w siedzibie '),
        array('text'=>($eg['miejsce_teoria'] ?: ($eg['miejsce_pelne'] ?? '')),'bold'=>true),
        array('text'=>($eg['miejsce_praktyka'] ?: ($eg['miejsce_praktyczne'] ?? '')) ? ' oraz w '.($eg['miejsce_praktyka'] ?: ($eg['miejsce_praktyczne'] ?? '')) : ''),
        array('text'=>', Komisja Egzaminacyjna powołana przez '.oe_org_nazwa_biernik().' w składzie:'),
    ),array('after'=>160));

    $cw=array(3000,4000,3466);
    $rows=array(array(
        OE_Docx::tc('Skład Komisji Egzaminacyjnej',$cw[0],array('textOpts'=>array('bold'=>true,'size'=>18))),
        OE_Docx::tc('Imię i Nazwisko',$cw[1],array('textOpts'=>array('bold'=>true,'size'=>18))),
        OE_Docx::tc('Podpis',$cw[2],array('textOpts'=>array('bold'=>true,'size'=>18))),
    ));
    foreach ($komisja as $k) {
        $rows[]=array(
            OE_Docx::tc(ucfirst(isset($k['rola'])?$k['rola']:'').' KE',$cw[0]),
            OE_Docx::tc(isset($k['imie'])?$k['imie']:'',$cw[1]),
            OE_Docx::tc('',$cw[2]),
        );
    }
    $d->table($rows,$cw);
    $d->br();

    $d->pRuns(array(
        array('text'=>'działając z upoważnienia przez Ministra Sportu i Turystyki do przeprowadzania egzaminów na patenty i licencje żeglarskie oraz motorowodne upoważnieniem nr '),
        array('text'=>$eg['nr_decyzji'],'bold'=>true),
        array('text'=>', przeprowadziła egzamin, na patent '),
        array('text'=>$eg['rodzaj_egzaminu'],'bold'=>true),
        array('text'=>' do którego przystąpiło '),
        array('text'=>(string)$n,'bold'=>true),
        array('text'=>' osób, z których '),
        array('text'=>(string)$n,'bold'=>true),
        array('text'=>' osób ukończyło egzamin z wynikiem pozytywnym i otrzymało zaświadczenia o zdaniu egzaminu będące podstawą do wystawienia stosownego patentu.'),
    ),array('after'=>160));

    $d->p('Listę uczestników wraz z ogólnymi wynikami cząstkowymi i wynikiem końcowym złącza się do niniejszego protokołu - załącznik nr 1,',array('after'=>240));

    $d->p('OPIS EGZAMINU',array('bold'=>true,'after'=>160));
    $d->p('Część teoretyczna:',array('bold'=>true,'after'=>360));
    $d->p('Część praktyczna:',array('bold'=>true,'after'=>360));

    $d->br();
    $d->p('Do niniejszego protokołu dołącza się:',array('after'=>80));
    $d->p('– listę pytań użytą do przeprowadzenia egzaminu',array('after'=>60));
    $d->p('– arkusz prawidłowych odpowiedzi',array('after'=>240));

    $d->p('Zaświadczam prawidłowość przeprowadzenia niniejszego egzaminu',array('bold'=>true,'after'=>600));
    $d->p('............................................................................',array('align'=>'center','after'=>60));
    $d->p('Przewodniczący KE',array('align'=>'center'));

    $d->download($fn);
}

// ── ZAŁ. NR 1 ─────────────────────────────────────────────────────────────

function oe_doc_zal1($eg,$uu,$fn) {
    $d = new OE_Docx();
    $cw = array(440,2100,1900,2300,900,900,960);

    $d->pRuns(array(array('text'=>'Załącznik nr 1','bold'=>true,'size'=>18)),array('after'=>80));
    oe_msd($d,$eg);
    oe_hdr($d,$eg);
    $d->pRuns(array(array('text'=>'Lista uczestników – wyniki egzaminu nr '.$eg['nr_egzaminu'],'bold'=>true,'size'=>22)),array('before'=>240,'after'=>240));

    $rows = array(array(
        OE_Docx::tc('Lp.',$cw[0],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Imię i nazwisko',$cw[1],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Data i miejsce ur.',$cw[2],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Adres zamieszkania',$cw[3],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Teoria',$cw[4],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Praktyka',$cw[5],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Ocena końcowa',$cw[6],array('textOpts'=>array('bold'=>true,'size'=>16))),
    ));
    foreach ($uu as $i=>$u) {
        $rows[]=array(
            OE_Docx::tc(($i+1).'.',$cw[0]),
            OE_Docx::tc($u['imie'].' '.$u['nazwisko'],$cw[1],array('textOpts'=>array('bold'=>true,'size'=>16))),
            OE_Docx::tc($u['data_ur'].' '.$u['msc_ur'],$cw[2],array('textOpts'=>array('size'=>16))),
            OE_Docx::tc($u['ulica'].', '.$u['kod'].' '.$u['miasto'],$cw[3],array('textOpts'=>array('size'=>16))),
            OE_Docx::tc('poz.',$cw[4]),
            OE_Docx::tc('poz.',$cw[5]),
            OE_Docx::tc('poz.',$cw[6],array('textOpts'=>array('bold'=>true))),
        );
    }
    $d->table($rows,$cw);
    oe_tabela_komisji($d,$eg);
    $d->download($fn);
}

// ── ZAŁ. NR 2 ─────────────────────────────────────────────────────────────

function oe_doc_zal2($eg, $uu, $fn) {
    $d = new OE_Docx();

    $def         = oe_rodzaj_def_losowania($eg['rodzaj']);
    $zadania     = $def['zadania'];

    if (empty($zadania)) {
        $d->p('Brak definicji zadań dla tego rodzaju egzaminu.');
        $d->download($fn);
        return;
    }

    // Szerokości: kolumna nazwiska + jedna kolumna per zadanie
    $nz  = count($zadania);
    $w0  = 2400;
    $wz  = intdiv(10466 - $w0, $nz);
    $cw  = array_merge(array($w0), array_fill(0, $nz, $wz));

    $d->pRuns(array(array('text'=>'Załącznik nr 2','bold'=>true,'size'=>18)),array('after'=>80));
    oe_msd($d,$eg);
    oe_hdr($d,$eg);
    $d->pRuns(array(array('text'=>'Zadania praktyczne na egzaminie nr '.$eg['nr_egzaminu'],'bold'=>true,'size'=>22)),array('before'=>240,'after'=>120));

    // Legenda w dwóch kolumnach
    $legenda = array();
    $sekcja_akt = '';
    foreach ($zadania as $idx => $z) {
        if ($z['sekcja'] !== $sekcja_akt) {
            $sekcja_akt = $z['sekcja'];
            $legenda[] = array('tekst'=>$z['sekcja'], 'bold'=>true);
        }
        $legenda[] = array('tekst'=>'  '.($idx+1).'. '.$z['nazwa'], 'bold'=>false);
    }
    $polowa = (int)ceil(count($legenda)/2);
    $lewa   = array_slice($legenda, 0, $polowa);
    $prawa  = array_slice($legenda, $polowa);
    $wleg   = array(5233, 5233);
    $d->table(array(array(
        OE_Docx::tcP(array_map(function($l) use ($d) {
            return $d->buildP($l['tekst'], array('size'=>14,'bold'=>$l['bold'],'after'=>20));
        }, $lewa), $wleg[0], array('borders'=>'none')),
        OE_Docx::tcP(array_map(function($l) use ($d) {
            return $d->buildP($l['tekst'], array('size'=>14,'bold'=>$l['bold'],'after'=>20));
        }, $prawa), $wleg[1], array('borders'=>'none')),
    )), $wleg, array('borders'=>'none'));

    $d->br();

    // Nagłówek tabeli: sekcje (colSpan) + numery
    $sekcje_info = array();
    $sekcja_akt  = '';
    foreach ($zadania as $idx => $z) {
        if ($z['sekcja'] !== $sekcja_akt) {
            $sekcja_akt = $z['sekcja'];
            $sekcje_info[] = array('sekcja'=>$z['sekcja'], 'count'=>1, 'start'=>$idx);
        } else {
            $sekcje_info[count($sekcje_info)-1]['count']++;
        }
    }

    $row_sekcje = array(OE_Docx::tc('', $w0));
    foreach ($sekcje_info as $s) {
        $row_sekcje[] = OE_Docx::tc($s['sekcja'], $wz*$s['count'], array(
            'colSpan'  => $s['count'],
            'textOpts' => array('bold'=>true,'size'=>13,'align'=>'center'),
        ));
    }
    $row_numery = array(OE_Docx::tc('Uczestnik', $w0, array('textOpts'=>array('bold'=>true,'size'=>14))));
    foreach ($zadania as $idx => $z) {
        $row_numery[] = OE_Docx::tc((string)($idx+1), $wz, array('textOpts'=>array('bold'=>true,'size'=>14,'align'=>'center')));
    }
    $rows = array($row_sekcje, $row_numery);

    foreach ($uu as $u) {
        $poz_idx = oe_losuj_zadania_v2($u, $def);
        $row = array(OE_Docx::tc($u['imie'].' '.$u['nazwisko'], $w0, array('textOpts'=>array('bold'=>true,'size'=>14))));
        foreach ($zadania as $idx => $z) {
            if (!empty($z['zawsze_nie'])) {
                $tekst = '- - -';
            } elseif (!empty($z['zawsze_poz']) || in_array($idx, $poz_idx)) {
                $tekst = 'poz.';
            } else {
                $tekst = '- - -';
            }
            $row[] = OE_Docx::tc($tekst, $wz, array('textOpts'=>array('size'=>13,'align'=>'center')));
        }
        $rows[] = $row;
    }

    $d->table($rows, $cw);
    oe_tabela_komisji($d, $eg);
    $d->download($fn);
}

// ── ZAŁ. NR 3 ─────────────────────────────────────────────────────────────

function oe_doc_zal3($eg,$uu,$fn) {
    $d=new OE_Docx();
    $cw=array(440,2500,2100,2600,2826);

    $d->pRuns(array(array('text'=>'Załącznik nr 3','bold'=>true,'size'=>18)),array('after'=>80));
    oe_msd($d,$eg);
    oe_hdr($d,$eg);
    $d->pRuns(array(array('text'=>'Lista wydanych zaświadczeń – egzamin nr '.$eg['nr_egzaminu'],'bold'=>true,'size'=>22)),array('before'=>240,'after'=>240));

    $rows=array(array(
        OE_Docx::tc('Lp.',$cw[0],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Imię i nazwisko',$cw[1],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Data i miejsce urodzenia',$cw[2],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Adres zamieszkania',$cw[3],array('textOpts'=>array('bold'=>true,'size'=>16))),
        OE_Docx::tc('Potwierdzenie odbioru',$cw[4],array('textOpts'=>array('bold'=>true,'size'=>16))),
    ));
    foreach ($uu as $i=>$u) {
        $rows[]=array(
            OE_Docx::tc(($i+1).'.',$cw[0]),
            OE_Docx::tc($u['imie'].' '.$u['nazwisko'],$cw[1],array('textOpts'=>array('bold'=>true,'size'=>16))),
            OE_Docx::tc($u['data_ur'].' '.$u['msc_ur'],$cw[2]),
            OE_Docx::tc($u['ulica'].', '.$u['kod'].' '.$u['miasto'],$cw[3]),
            OE_Docx::tc('',$cw[4]),
        );
    }
    $d->table($rows,$cw);
    oe_tabela_komisji($d,$eg);
    $d->download($fn);
}

// ── KARTY EGZAMINACYJNE ───────────────────────────────────────────────────

function oe_doc_karty($eg, $uu, $fn) {
    $d   = new OE_Docx();
    // Kolumny: zadanie | Ocena | Podpis
    $cw  = array(7000, 1800, 1666);

    $typ     = $eg['rodzaj'];
    $def     = oe_rodzaj_def_losowania($typ);
    $zadania = $def['zadania'];

    // Tabela 1: stałe wiersze (np. test z teorii, praktyka); kolejne: sekcje zadań praktycznych
    $wiersze = ($typ && $typ['karta_wiersze']) ? $typ['karta_wiersze'] : array('test z teorii', 'praktyka');
    $tabele  = array(array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array()));
    foreach ($wiersze as $w) {
        $tabele[0]['wiersze'][] = array('nazwa'=>$w);
    }
    if ($typ) {
        foreach ($typ['sekcje'] as $s) {
            $tabele[] = array('naglowek'=>$s['nazwa'], 'sekcja'=>$s['nazwa']);
        }
    }

    foreach ($uu as $idx => $u) {
        if ($idx > 0) $d->pageBreak();

        oe_msd($d, $eg);
        oe_hdr($d, $eg);
        $d->pRuns(array(array('text'=>'KARTA EGZAMINACYJNA','bold'=>true,'size'=>26)),array('align'=>'center','before'=>400,'after'=>200));
        $d->pRuns(array(array('text'=>'egzamin nr '.$eg['nr_egzaminu'],'bold'=>true,'size'=>20)),array('align'=>'center','after'=>160));

        $d->pRuns(array(array('text'=>'Imię i nazwisko: ','bold'=>true),array('text'=>$u['imie'].' '.$u['nazwisko'])),array('after'=>60));
        $d->pRuns(array(array('text'=>'Data i miejsce urodzenia: ','bold'=>true),array('text'=>$u['data_ur'].' '.$u['msc_ur'])),array('after'=>60));
        $d->pRuns(array(array('text'=>'Adres zamieszkania: ','bold'=>true),array('text'=>$u['ulica'].', '.$u['kod'].' '.$u['miasto'])),array('after'=>120));

        // RODO
        $d->p('Oświadczam, że zostałam poinformowana że:',array('italic'=>true,'size'=>18,'after'=>60));
        $d->p('– administratorami zbiorów powyższych danych są: '.oe_org_nazwa_pelna().' oraz Ministerstwo Sportu i Turystyki.',array('italic'=>true,'size'=>16,'after'=>60));
        $d->p('– powyższe dane osobowe są zbierane w celach dowodowych zgodnie z § 19 ust. 2 Rozporządzenie Ministra Sportu i Turystyki z 9 kwietnia 2013 r. w sprawie uprawiania turystyki wodnej (Dz. U. 2013 poz. 460) i nie będą udostępniane do innych celów.',array('italic'=>true,'size'=>16,'after'=>80));
        $d->p('Wyrażam zgodę na przetwarzanie moich danych osobowych dla potrzeb niezbędnych do przeprowadzenia egzaminu na patenty żeglarskie, motorowodne i licencje, zgodnie z Rozporządzeniem Parlamentu Europejskiego i Rady (UE) 2016/679 z dnia 27 kwietnia 2016 r.',array('size'=>16,'after'=>80));
        if ($typ && $typ['zgoda_rodzicow']) {
            $d->pRuns(array(
                array('text'=>'Dołączono zgodę rodziców/opiekunów prawnych na uprawianie turystyki wodnej '),
                array('text'=>'[TAK]','bold'=>true),array('text'=>'  '),
                array('text'=>'[NIE]','bold'=>true),array('text'=>'  *'),
            ),array('size'=>16,'after'=>100));
        }
        $d->p('............................................................................',array('align'=>'right','after'=>20));
        $d->p('podpis osoby egzaminowanej',array('align'=>'right','size'=>16,'after'=>160));

        // Tabele zadań
        $poz_idx = oe_losuj_zadania_v2($u, $def);

        foreach ($tabele as $ti => $tab) {
            // Nagłówek tabeli
            $rows = array(array(
                OE_Docx::tc($tab['naglowek'], $cw[0], array('textOpts'=>array('bold'=>true,'size'=>16))),
                OE_Docx::tc('Ocena', $cw[1], array('textOpts'=>array('bold'=>true,'size'=>16))),
                OE_Docx::tc('Podpis', $cw[2], array('textOpts'=>array('bold'=>true,'size'=>16))),
            ));

            if (isset($tab['wiersze'])) {
                // Tabela 1: stałe wiersze (teoria, praktyka, nawigacja)
                foreach ($tab['wiersze'] as $w) {
                    $rows[] = array(
                        OE_Docx::tc($w['nazwa'], $cw[0], array('textOpts'=>array('size'=>15))),
                        OE_Docx::tc('', $cw[1]),
                        OE_Docx::tc('', $cw[2]),
                    );
                }
            } elseif (isset($tab['sekcja'])) {
                // Tabele z zadaniami praktycznymi
                foreach ($zadania as $zi => $z) {
                    if ($z['sekcja'] !== $tab['sekcja']) continue;
                    $rows[] = array(
                        OE_Docx::tc($z['nazwa'], $cw[0], array('textOpts'=>array('size'=>15))),
                        OE_Docx::tc('', $cw[1]),
                        OE_Docx::tc('', $cw[2]),
                    );
                }
            }

            $d->table($rows, $cw);
            if ($ti < count($tabele)-1) $d->br();
        }

        $d->br();
        $d->p('............................................................................',array('align'=>'right','after'=>20));
        $d->p('podpis przewodniczącego KE',array('align'=>'right','size'=>16));
    }
    $d->download($fn);
}

// ── ZAŚWIADCZENIA ─────────────────────────────────────────────────────────

function oe_doc_zaswiadczenia($eg,$uu,$fn) {
    $d=new OE_Docx();
    foreach ($uu as $idx=>$u) {
        if ($idx>0) $d->pageBreak();
        $nr=str_pad($idx+1,3,'0',STR_PAD_LEFT).'/'.$eg['nr_egzaminu'];

        oe_msd($d,$eg);
        oe_hdr($d,$eg);

        // Tytuł z dużym odstępem przed i po
        $d->pRuns(array(array('text'=>'Zaświadczenie','bold'=>true,'size'=>32)),array('align'=>'center','before'=>480,'after'=>320));
        $d->p('o zdaniu egzaminu potwierdzającego posiadanie wiedzy',array('align'=>'center','size'=>20,'after'=>40));
        $d->p('i umiejętności z zakresu żeglarstwa',array('align'=>'center','size'=>20,'after'=>80));
        $d->pRuns(array(array('text'=>'nr '.$nr,'bold'=>true,'size'=>20)),array('align'=>'center','after'=>240));

        $d->pRuns(array(
            array('text'=>'Zaświadcza się, że Pan/Pani: '),
            array('text'=>$u['imie'].' '.$u['nazwisko'],'bold'=>true),
            array('text'=>','),
        ),array('after'=>80));
        $d->pRuns(array(
            array('text'=>'urodzony(-a) dnia: '),
            array('text'=>$u['data_ur'].' '.$u['msc_ur'],'bold'=>true),
            array('text'=>','),
        ),array('after'=>80));
        $d->pRuns(array(
            array('text'=>'zamieszkały(-a): '),
            array('text'=>$u['ulica'].', '.$u['kod'].' '.$u['miasto'],'bold'=>true),
        ),array('after'=>200));

        $d->p('Zdał(-a) egzamin',array('bold'=>true,'size'=>26,'align'=>'center','after'=>80));
        $d->pRuns(array(array('text'=>'na stopień '.$eg['rodzaj_egzaminu'],'bold'=>true,'size'=>26)),array('align'=>'center','after'=>480));

        $d->p('............................................................................',array('align'=>'center','after'=>60));
        $d->p('pieczątka i podpis przewodniczącego komisji egzaminacyjnej',array('size'=>16,'align'=>'center'));
    }
    $d->download($fn);
}


// ── ARKUSZ ODPOWIEDZI ─────────────────────────────────────────────────────

function oe_arkusz_buduj(OE_Docx $d, $eg, $u_imie, $u_nazwisko, $klucz_odpowiedzi) {
    $rodzaj  = isset($eg['rodzaj_egzaminu']) ? $eg['rodzaj_egzaminu'] : '';
    $n_pytan = ($eg['rodzaj'] && $eg['rodzaj']['liczba_pytan']) ? (int)$eg['rodzaj']['liczba_pytan'] : 75;

    // ── Tytuł ──
    $d->pRuns(array(array('text'=>oe_upper($rodzaj),'bold'=>true,'size'=>22)),array('align'=>'center','after'=>40));
    $d->pRuns(array(array('text'=>'Egzamin nr '.$eg['nr_egzaminu'],'bold'=>true,'size'=>20)),array('align'=>'center','after'=>40));
    $d->p('Karta odpowiedzi',array('align'=>'center','size'=>20,'after'=>20));
    $d->pRuns(array(array('text'=>'DECYZJA MSiT '.(isset($eg['nr_decyzji'])?$eg['nr_decyzji']:''),'bold'=>true,'size'=>16)),array('align'=>'center','after'=>60));

    // ── Nagłówek: Imię / Nazwisko / Miejsce i data ──
    $wL=2000; $wV=8506;
    $d->table(array(
        array(OE_Docx::tc('Imię',$wL,array('textOpts'=>array('bold'=>true,'size'=>16))), OE_Docx::tc($klucz_odpowiedzi!==null?'WZÓR':'',$wV,array('textOpts'=>array('bold'=>true,'size'=>16)))),
        array(OE_Docx::tc('Nazwisko',$wL,array('textOpts'=>array('bold'=>true,'size'=>16))), OE_Docx::tc($klucz_odpowiedzi!==null?'WZÓR':'',$wV,array('textOpts'=>array('bold'=>true,'size'=>16)))),
        array(OE_Docx::tc('Miejsce i data egzaminu',$wL,array('textOpts'=>array('bold'=>true,'size'=>16))), OE_Docx::tc($eg['miejscowosc'].', '.$eg['data_fmt'],$wV,array('textOpts'=>array('size'=>16)))),
    ),array($wL,$wV));
    $d->br();

    // ── Tabela pytań ──
    // Do 25 pytań jedna grupa kolumn, powyżej trzy (25 i 75 pytań dają dotychczasowy układ).
    $grupy  = $n_pytan <= 25 ? 1 : 3;
    $wiersz = (int)ceil($n_pytan / $grupy);
    $wN     = 560;
    $wA     = (int)floor((10506 / $grupy - $wN) / 3);
    $cw     = array();
    for ($g = 0; $g < $grupy; $g++) {
        array_push($cw, $wN, $wA, $wA, $wA);
    }
    $cw[count($cw)-1] = 10506 - (array_sum($cw) - $wA); // ostatnia kolumna dopełnia szerokość
    $hdr = array();
    foreach ($cw as $li => $w) {
        $lbl   = array('Nr pytania','A','B','C');
        $hdr[] = OE_Docx::tc($lbl[$li % 4],$w,array('textOpts'=>array('bold'=>true,'size'=>16,'align'=>'center')));
    }
    $rows = array($hdr);
    for ($r = 0; $r < $wiersz; $r++) {
        $row = array();
        for ($g = 0; $g < $grupy; $g++) {
            $n   = $g*$wiersz + $r + 1;
            $jest = $n <= $n_pytan;
            $odp = ($jest && $klucz_odpowiedzi!==null && isset($klucz_odpowiedzi[$n])) ? $klucz_odpowiedzi[$n] : -1;
            $row[] = OE_Docx::tc($jest ? (string)$n : '',$cw[$g*4],array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
            $row[] = OE_Docx::tc($odp===0?'X':'',$cw[$g*4+1],array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
            $row[] = OE_Docx::tc($odp===1?'X':'',$cw[$g*4+2],array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
            $row[] = OE_Docx::tc($odp===2?'X':'',$cw[$g*4+3],array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
        }
        $rows[] = $row;
    }
    $d->table($rows,$cw,array('rowHeight'=>220,'headerHeight'=>280));

    // ── Stopka ──
    $d->br();
    $wS=3502;
    $d->table(array(array(
        OE_Docx::tcP(array(
            $d->buildP('Ilość prawidłowych odpowiedzi _______ / '.$n_pytan.' pytań.',array('size'=>16,'after'=>60)),
            $d->buildP('Wynik egzaminu (poz/neg) ____________',array('size'=>16,'after'=>0)),
        ),$wS),
        OE_Docx::tcP(array(
            $d->buildP('...........................................',array('size'=>14,'after'=>40)),
            $d->buildP('podpis egzaminatora  m.p.',array('size'=>14,'after'=>0)),
        ),$wS),
        OE_Docx::tcP(array(
            $d->buildP('...........................................',array('size'=>14,'after'=>40)),
            $d->buildP('podpis zdającego',array('size'=>14,'after'=>0)),
        ),$wS),
    )),array($wS,$wS,$wS));
}

function oe_doc_arkusze($eg, $uu, $fn) {
    $d=new OE_Docx();
    $d->setMargins(700,700,700,700);
    foreach ($uu as $idx=>$u) {
        if ($idx>0) $d->pageBreak();
        oe_arkusz_buduj($d,$eg,$u['imie'],$u['nazwisko'],null);
    }
    $d->download($fn);
}

function oe_doc_arkusze_wzor($eg, $fn) {
    $d=new OE_Docx();
    $d->setMargins(700,700,700,700);
    $klucz=oe_rodzaj_klucz_map($eg['rodzaj']);
    oe_arkusz_buduj($d,$eg,'WZÓR','WZÓR',empty($klucz)?null:$klucz);
    $d->download($fn);
}
