<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Metabox z przyciskami ─────────────────────────────────────────────────

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'oe_generuj_dokumenty', 'Generuj dokumenty DOCX', 'oe_metabox_generuj', 'oe_egzamin', 'side', 'default' );
} );

function oe_metabox_generuj( $post ) {
    if ( $post->post_status !== 'publish' ) {
        echo '<p style="font-size:12px;color:#ba7517">Opublikuj egzamin aby generować dokumenty.</p>';
        return;
    }
    $dokumenty = array(
        'zgloszenie'    => array('📋','Zgłoszenie egzaminu'),
        'protokol'      => array('📄','Protokół KE'),
        'zal1'          => array('📊','Zał. nr 1 - wyniki'),
        'zal2'          => array('⛵','Zał. nr 2 - praktyka'),
        'zal3'          => array('📜','Zał. nr 3 - zaświadczenia'),
        'karty'         => array('🪪','Karty egzaminacyjne'),
        'zaswiadczenia' => array('🏅','Zaświadczenia'),
        'arkusze'       => array('📝','Arkusze odpowiedzi'),
        'arkusze_wzor'  => array('📋','Arkusz odpowiedzi WZÓR 1'),
    );
    echo '<style>.oe-gb{display:flex;align-items:center;gap:8px;width:100%;margin-bottom:6px;padding:7px 10px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;font-size:13px;font-family:inherit;text-decoration:none;color:#1e1e1e}.oe-gb:hover{background:#e8f5e9;border-color:#a5d6a7;color:#1e1e1e}</style>';
    foreach ( $dokumenty as $typ => $info ) {
        $url = wp_nonce_url( admin_url("admin-post.php?action=oe_generuj_docx&egzamin_id={$post->ID}&typ={$typ}"), 'oe_generuj_'.$post->ID );
        echo "<a href='".esc_url($url)."' class='oe-gb'><span style='font-size:16px'>{$info[0]}</span><span>".esc_html($info[1])."</span><span style='margin-left:auto;font-size:11px;color:#999'>↓ docx</span></a>";
    }
    echo '<p style="font-size:11px;color:#888;margin-top:8px">Tylko zatwierdzeni uczestnicy.</p>';
}

// ── Handler ───────────────────────────────────────────────────────────────

add_action( 'admin_post_oe_generuj_docx', 'oe_handle_generuj_docx' );

function oe_handle_generuj_docx() {
    $eid = isset($_GET['egzamin_id']) ? intval($_GET['egzamin_id']) : 0;
    $typ = isset($_GET['typ'])        ? sanitize_text_field($_GET['typ']) : '';
    if (!$eid || !$typ) wp_die('Nieprawidłowe żądanie.');
    check_admin_referer('oe_generuj_'.$eid);
    if (!current_user_can('edit_posts')) wp_die('Brak uprawnień.');

    $eg = oe_get_eg($eid);
    $uu = oe_get_uu($eid);

    // Format nazwy pliku: RRRR_MM_DD_SM_MIASTO
    $skroty = array(
        'Sternik Motorowodny'                                         => 'SM',
        'Żeglarz Jachtowy'                                            => 'ZJ',
        'Jachtowy Sternik Morski'                                     => 'JSM',
        'Motorowodny Sternik Morski'                                  => 'MSM',
        'Licencja do holowania narciarza wodnego lub innych obiektów' => 'LHN',
    );
    $skrot  = isset($skroty[$eg['rodzaj_egzaminu']]) ? $skroty[$eg['rodzaj_egzaminu']] : 'EGZ';
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
    return array(
        'nr_egzaminu'        => get_post_meta($id,'_oe_nr_egzaminu',true),
        'nr_decyzji'         => get_post_meta($id,'_oe_nr_decyzji',true),
        'rodzaj_egzaminu'    => get_post_meta($id,'_oe_rodzaj_egzaminu',true),
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
    $d->pRuns(array(array('text'=>'Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy','bold'=>true,'size'=>18)),array('align'=>'center','before'=>0,'after'=>80));
    $d->pRuns(array(array('text'=>'ul. Przemysłowa 10/303, 40-020 Katowice','size'=>16)),array('align'=>'center','after'=>40));
    $d->pRuns(array(array('text'=>'KRS 0000881696  NIP 6342991899  REGON 388227941','size'=>16)),array('align'=>'center','after'=>40));
    $d->pRuns(array(array('text'=>'DECYZJA MSiT '.($eg['nr_decyzji']??''),'bold'=>true,'size'=>16)),array('align'=>'center','after'=>280));
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
    $d->pRuns(array(array('text'=>'Katowice, '.$eg['data_utworzenia'],'size'=>18)),array('align'=>'right','after'=>160));

    // Nagłówek fundacji po lewej (nie wyśrodkowany jak w innych)
    $d->pRuns(array(array('text'=>'Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy','bold'=>true,'size'=>18)),array('after'=>40));
    $d->pRuns(array(array('text'=>'ul. Przemysłowa 10/303','size'=>18)),array('after'=>20));
    $d->pRuns(array(array('text'=>'40-020 Katowice','size'=>18)),array('after'=>240));

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
    $d->pRuns(array(array('text'=>'Katowice, '.$eg['data_utworzenia'],'italic'=>true,'size'=>18)),array('after'=>20));
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
        array('text'=>', Komisja Egzaminacyjna powołana przez Fundację propagowania sportów wodnych dla każdego OCEAN WIEDZY w składzie:'),
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

    $wszystkie   = function_exists('oe_get_zadania') ? oe_get_zadania() : array();
    $def         = isset($wszystkie[$eg['rodzaj_egzaminu']]) ? $wszystkie[$eg['rodzaj_egzaminu']] : array();
    $zadania     = isset($def['zadania']) ? $def['zadania'] : array();

    if (empty($zadania)) {
        $d->p('Brak definicji zadań dla tego rodzaju egzaminu.');
        $d->download($fn);
        return;
    }

    $sekcje_nazwy = array(
        'zagle'      => 'manewry na żaglach - zadania',
        'silnik'     => 'manewry na silniku - zadania',
        'kierowanie' => 'kierowanie załogą',
        'inne'       => 'inne',
        'praktyka'   => 'praktyka',
    );

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
            $snazwa = isset($sekcje_nazwy[$z['sekcja']]) ? $sekcje_nazwy[$z['sekcja']] : $z['sekcja'];
            $legenda[] = array('tekst'=>$snazwa, 'bold'=>true);
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
        $snazwa = isset($sekcje_nazwy[$s['sekcja']]) ? $sekcje_nazwy[$s['sekcja']] : $s['sekcja'];
        $row_sekcje[] = OE_Docx::tc($snazwa, $wz*$s['count'], array(
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

    $wszystkie = function_exists('oe_get_zadania') ? oe_get_zadania() : array();
    $def       = isset($wszystkie[$eg['rodzaj_egzaminu']]) ? $wszystkie[$eg['rodzaj_egzaminu']] : array();
    $zadania   = isset($def['zadania']) ? $def['zadania'] : array();

    $sekcje_nazwy = array(
        'zagle'      => 'manewry na żaglach - zadania',
        'silnik'     => 'manewry na silniku - zadania',
        'kierowanie' => 'kierowanie załogą',
        'inne'       => 'inne',
        'praktyka'   => 'praktyka',
    );

    // Definicje tabel per typ egzaminu
    // Tabela 1: zawsze test z teorii + nagłówki
    // Pozostałe tabele: sekcje zadań praktycznych
    $tabele_def = array(
        'Żeglarz Jachtowy' => array(
            array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array(
                array('nazwa'=>'test/y z teorii', 'wynik'=>'TEORIA'),
                array('nazwa'=>'praktyka',         'wynik'=>'PRAKTYKA'),
            )),
            array('naglowek'=>'manewry na żaglach - zadania', 'sekcja'=>'zagle'),
            array('naglowek'=>'manewry na silniku - zadania', 'sekcja'=>'silnik'),
            array('naglowek'=>'kierowanie załogą',             'sekcja'=>'kierowanie'),
            array('naglowek'=>'inne',                          'sekcja'=>'inne'),
        ),
        'Jachtowy Sternik Morski' => array(
            array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array(
                array('nazwa'=>'test/y z teorii',    'wynik'=>'TEORIA'),
                array('nazwa'=>'zadanie nawigacyjne','wynik'=>''),
                array('nazwa'=>'praktyka',            'wynik'=>'PRAKTYKA'),
            )),
            array('naglowek'=>'manewry na żaglach - zadania', 'sekcja'=>'zagle'),
            array('naglowek'=>'manewry na silniku - zadania', 'sekcja'=>'silnik'),
            array('naglowek'=>'kierowanie załogą',             'sekcja'=>'kierowanie'),
        ),
        'Sternik Motorowodny' => array(
            array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array(
                array('nazwa'=>'test z teorii', 'wynik'=>'TEORIA'),
                array('nazwa'=>'praktyka',       'wynik'=>'PRAKTYKA'),
            )),
            array('naglowek'=>'manewry na silniku - zadania', 'sekcja'=>'silnik'),
        ),
        'Motorowodny Sternik Morski' => array(
            array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array(
                array('nazwa'=>'test z teorii', 'wynik'=>'TEORIA'),
                array('nazwa'=>'praktyka',       'wynik'=>'PRAKTYKA'),
            )),
            array('naglowek'=>'praktyka', 'sekcja'=>'praktyka'),
        ),
        'Licencja do holowania narciarza wodnego lub innych obiektów' => array(
            array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array(
                array('nazwa'=>'test z teorii', 'wynik'=>'TEORIA'),
                array('nazwa'=>'praktyka',       'wynik'=>'PRAKTYKA'),
            )),
            array('naglowek'=>'praktyka', 'sekcja'=>'praktyka'),
        ),
    );

    $rodzaj = isset($eg['rodzaj_egzaminu']) ? $eg['rodzaj_egzaminu'] : '';
    $tabele = isset($tabele_def[$rodzaj]) ? $tabele_def[$rodzaj] : array(
        array('naglowek'=>'zadanie egzaminacyjne:', 'wiersze'=>array(
            array('nazwa'=>'test z teorii','wynik'=>'TEORIA'),
            array('nazwa'=>'praktyka',     'wynik'=>'PRAKTYKA'),
        )),
    );

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
        $d->p('– administratorami zbiorów powyższych danych są: Fundacja propagowania sportów wodnych dla każdego Ocean Wiedzy oraz Ministerstwo Sportu i Turystyki.',array('italic'=>true,'size'=>16,'after'=>60));
        $d->p('– powyższe dane osobowe są zbierane w celach dowodowych zgodnie z § 19 ust. 2 Rozporządzenie Ministra Sportu i Turystyki z 9 kwietnia 2013 r. w sprawie uprawiania turystyki wodnej (Dz. U. 2013 poz. 460) i nie będą udostępniane do innych celów.',array('italic'=>true,'size'=>16,'after'=>80));
        $d->p('Wyrażam zgodę na przetwarzanie moich danych osobowych dla potrzeb niezbędnych do przeprowadzenia egzaminu na patenty żeglarskie, motorowodne i licencje, zgodnie z Rozporządzeniem Parlamentu Europejskiego i Rady (UE) 2016/679 z dnia 27 kwietnia 2016 r.',array('size'=>16,'after'=>80));
        if (in_array($rodzaj, array('Żeglarz Jachtowy','Sternik Motorowodny'))) {
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
    $is_hol  = ($rodzaj === 'Licencja do holowania narciarza wodnego lub innych obiektów');
    $n_pytan = $is_hol ? 25 : 75;

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
    if ($is_hol) {
        // HOL: 25 pytań, 1 grupa
        $wN=560; $wA=3315; $wAL=3316;
        $cw=array($wN,$wA,$wA,$wAL);
        $hdr=array(
            OE_Docx::tc('Nr pytania',$wN,array('textOpts'=>array('bold'=>true,'size'=>16,'align'=>'center'))),
            OE_Docx::tc('A',$wA,array('textOpts'=>array('bold'=>true,'size'=>16,'align'=>'center'))),
            OE_Docx::tc('B',$wA,array('textOpts'=>array('bold'=>true,'size'=>16,'align'=>'center'))),
            OE_Docx::tc('C',$wAL,array('textOpts'=>array('bold'=>true,'size'=>16,'align'=>'center'))),
        );
        $rows=array($hdr);
        for($r=1;$r<=25;$r++){
            $odp=($klucz_odpowiedzi!==null&&isset($klucz_odpowiedzi[$r]))?$klucz_odpowiedzi[$r]:-1;
            $rows[]=array(
                OE_Docx::tc((string)$r,$wN,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center'))),
                OE_Docx::tc($odp===0?'X':'',$wA,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center'))),
                OE_Docx::tc($odp===1?'X':'',$wA,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center'))),
                OE_Docx::tc($odp===2?'X':'',$wAL,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center'))),
            );
        }
        $d->table($rows,$cw,array('rowHeight'=>220,'headerHeight'=>280));
    } else {
        // 75 pytań, 3 grupy
        $wN=560; $wA=980; $wAL=986;
        $cw=array($wN,$wA,$wA,$wA,$wN,$wA,$wA,$wA,$wN,$wA,$wA,$wAL);
        $hdr=array();
        foreach(array('Nr pytania','A','B','C','Nr pytania','A','B','C','Nr pytania','A','B','C') as $li=>$lbl)
            $hdr[]=OE_Docx::tc($lbl,$cw[$li],array('textOpts'=>array('bold'=>true,'size'=>16,'align'=>'center')));
        $rows=array($hdr);
        for($r=0;$r<25;$r++){
            $row=array();
            for($g=0;$g<3;$g++){
                $n=$g*25+$r+1;
                $odp=($klucz_odpowiedzi!==null&&isset($klucz_odpowiedzi[$n]))?$klucz_odpowiedzi[$n]:-1;
                $wLast=($g===2)?$wAL:$wA;
                $row[]=OE_Docx::tc((string)$n,$wN,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
                $row[]=OE_Docx::tc($odp===0?'X':'',$wA,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
                $row[]=OE_Docx::tc($odp===1?'X':'',$wA,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
                $row[]=OE_Docx::tc($odp===2?'X':'',$wLast,array('textOpts'=>array('bold'=>true,'size'=>18,'align'=>'center')));
            }
            $rows[]=$row;
        }
        $d->table($rows,$cw,array('rowHeight'=>220,'headerHeight'=>280));
    }

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
    $klucze=array(
        'Żeglarz Jachtowy'=>array(1=>1,2=>1,3=>2,4=>1,5=>0,6=>0,7=>1,8=>0,9=>1,10=>2,11=>1,12=>1,13=>2,14=>0,15=>0,16=>1,17=>2,18=>1,19=>1,20=>0,21=>0,22=>2,23=>2,24=>0,25=>2,26=>0,27=>2,28=>1,29=>2,30=>2,31=>2,32=>0,33=>2,34=>0,35=>0,36=>0,37=>1,38=>1,39=>0,40=>2,41=>0,42=>1,43=>0,44=>0,45=>1,46=>2,47=>1,48=>1,49=>0,50=>0,51=>2,52=>0,53=>1,54=>2,55=>0,56=>0,57=>0,58=>1,59=>0,60=>2,61=>2,62=>0,63=>0,64=>0,65=>1,66=>0,67=>2,68=>1,69=>2,70=>0,71=>1,72=>0,73=>2,74=>1,75=>1),
        'Jachtowy Sternik Morski'=>array(1=>2,2=>2,3=>2,4=>0,5=>1,6=>1,7=>1,8=>2,9=>1,10=>1,11=>0,12=>2,13=>1,14=>2,15=>2,16=>2,17=>2,18=>2,19=>2,20=>1,21=>1,22=>2,23=>2,24=>2,25=>2,26=>2,27=>0,28=>0,29=>0,30=>2,31=>2,32=>2,33=>2,34=>2,35=>1,36=>2,37=>2,38=>1,39=>1,40=>2,41=>1,42=>2,43=>2,44=>1,45=>0,46=>0,47=>0,48=>2,49=>2,50=>2,51=>2,52=>2,53=>2,54=>0,55=>2,56=>0,57=>2,58=>0,59=>0,60=>0,61=>0,62=>2,63=>1,64=>2,65=>1,66=>1,67=>2,68=>2,69=>1,70=>0,71=>2,72=>2,73=>2,74=>1,75=>2),
        'Sternik Motorowodny'=>array(1=>2,2=>0,3=>2,4=>1,5=>2,6=>0,7=>1,8=>1,9=>0,10=>2,11=>2,12=>1,13=>2,14=>2,15=>0,16=>1,17=>0,18=>2,19=>1,20=>2,21=>0,22=>0,23=>0,24=>2,25=>0,26=>1,27=>1,28=>2,29=>0,30=>0,31=>0,32=>2,33=>1,34=>1,35=>2,36=>0,37=>0,38=>2,39=>0,40=>2,41=>1,42=>0,43=>0,44=>1,45=>1,46=>1,47=>2,48=>1,49=>1,50=>1,51=>0,52=>0,53=>0,54=>2,55=>2,56=>2,57=>1,58=>1,59=>1,60=>0,61=>2,62=>1,63=>0,64=>2,65=>1,66=>1,67=>2,68=>2,69=>0,70=>1,71=>1,72=>0,73=>2,74=>1,75=>2),
        'Motorowodny Sternik Morski'=>array(1=>0,2=>2,3=>1,4=>1,5=>2,6=>2,7=>0,8=>1,9=>1,10=>2,11=>2,12=>0,13=>1,14=>0,15=>0,16=>1,17=>0,18=>2,19=>0,20=>1,21=>0,22=>2,23=>1,24=>2,25=>1,26=>1,27=>2,28=>2,29=>0,30=>1,31=>0,32=>1,33=>1,34=>1,35=>2,36=>0,37=>2,38=>1,39=>2,40=>1,41=>1,42=>1,43=>2,44=>2,45=>0,46=>2,47=>1,48=>0,49=>1,50=>2,51=>0,52=>2,53=>2,54=>1,55=>0,56=>2,57=>1,58=>0,59=>1,60=>0,61=>1,62=>0,63=>1,64=>0,65=>0,66=>2,67=>2,68=>1,69=>0,70=>1,71=>1,72=>2,73=>1,74=>1,75=>2),
        'Licencja do holowania narciarza wodnego lub innych obiektów'=>array(1=>1,2=>2,3=>0,4=>0,5=>2,6=>2,7=>2,8=>2,9=>1,10=>0,11=>0,12=>0,13=>1,14=>1,15=>2,16=>1,17=>2,18=>2,19=>1,20=>2,21=>2,22=>2,23=>1,24=>1,25=>0),
    );
    $rodzaj=isset($eg['rodzaj_egzaminu'])?$eg['rodzaj_egzaminu']:'';
    $klucz=isset($klucze[$rodzaj])?$klucze[$rodzaj]:array();
    oe_arkusz_buduj($d,$eg,'WZÓR','WZÓR',empty($klucz)?null:$klucz);
    $d->download($fn);
}
