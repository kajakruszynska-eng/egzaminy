<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Definicje zadań egzaminacyjnych.
 *
 * Struktura zadania:
 *   'nazwa'      => string
 *   'sekcja'     => string  - klucz sekcji
 *   'zawsze_poz' => bool    - zawsze poz. w zał. 2
 *   'zawsze_nie' => bool    - zawsze - - - w zał. 2 (nigdy nie losowane)
 *
 * Sekcje w karcie egzaminacyjnej definiowane przez 'sekcje_karty' per typ.
 * Logika losowania zał. 2 per typ w 'losowanie'.
 */
function oe_get_zadania() {
    return array(

        // ── ŻEGLARZ JACHTOWY ─────────────────────────────────────────────
        'Żeglarz Jachtowy' => array(
            'zadania' => array(
                // sekcja żagle
                array('nazwa'=>'zwrot przez sztag',                                          'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zwrot przez rufę',                                           'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście do boi',                                             'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zejście z boi',                                              'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście do nabrzeża',                                        'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'odejście od nabrzeża',                                       'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'alarm człowiek za burtą',                                    'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'stawanie w dryf',                                            'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dobór żagli / trym',                                         'sekcja'=>'zagle',    'zawsze_poz'=>true,  'zawsze_nie'=>false),
                array('nazwa'=>'prowadzenie jachtu przy spotkaniu z inną jednostką / wyprzedzanie', 'sekcja'=>'zagle', 'zawsze_poz'=>true, 'zawsze_nie'=>false),
                // sekcja silnik
                array('nazwa'=>'kotwiczenie',                                                'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście do nabrzeża (silnik)',                               'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'odejście od nabrzeża (silnik)',                              'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                // sekcja kierowanie
                array('nazwa'=>'wydawanie i egzekwowanie komend',                            'sekcja'=>'kierowanie','zawsze_poz'=>true, 'zawsze_nie'=>false),
                // sekcja inne
                array('nazwa'=>'prace bosmańskie',                                           'sekcja'=>'inne',     'zawsze_poz'=>true,  'zawsze_nie'=>false),
            ),
            // Losowanie per sekcja: array(min, max) losowych z puli danej sekcji
            'losowanie' => array(
                'zagle'    => array('min'=>4, 'max'=>5),
                'silnik'   => array('min'=>1, 'max'=>2),
                'kierowanie'=> array('min'=>0, 'max'=>0),
                'inne'     => array('min'=>0, 'max'=>0),
            ),
        ),

        // ── JACHTOWY STERNIK MORSKI ───────────────────────────────────────
        'Jachtowy Sternik Morski' => array(
            'zadania' => array(
                // sekcja żagle
                array('nazwa'=>'człowiek za burtą',                                          'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zwrot przez sztag',                                          'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zwrot przez rufę',                                           'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście do boi',                                             'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zejście z boi',                                              'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście do nabrzeża',                                        'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>true),
                array('nazwa'=>'odejście od nabrzeża',                                       'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>true),
                array('nazwa'=>'alarm człowiek za burtą',                                    'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dobór żagli / trym',                                         'sekcja'=>'zagle',    'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'prowadzenie jachtu przy spotkaniu z inną jednostką / wyprzedzanie', 'sekcja'=>'zagle', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                // sekcja silnik
                array('nazwa'=>'kotwiczenie',                                                'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście do nabrzeża (silnik)',                               'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'odejście od nabrzeża (silnik)',                              'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                // sekcja kierowanie
                array('nazwa'=>'wydawanie i egzekwowanie komend',                            'sekcja'=>'kierowanie','zawsze_poz'=>true, 'zawsze_nie'=>false),
            ),
            'losowanie' => array(
                'zagle'    => array('min'=>3, 'max'=>5),
                'silnik'   => array('min'=>1, 'max'=>3),
                'kierowanie'=> array('min'=>0, 'max'=>0),
            ),
        ),

        // ── STERNIK MOTOROWODNY ───────────────────────────────────────────
        'Sternik Motorowodny' => array(
            'zadania' => array(
                array('nazwa'=>'kierowanie załogą',                                          'sekcja'=>'silnik',   'zawsze_poz'=>true,  'zawsze_nie'=>false),
                array('nazwa'=>'człowiek za burtą',                                          'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście i odejście od boi',                                  'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście i odejście od nabrzeża',                             'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'praca w charakterze członka załogi',                         'sekcja'=>'silnik',   'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'prace bosmańskie',                                           'sekcja'=>'silnik',   'zawsze_poz'=>true,  'zawsze_nie'=>false),
            ),
            'losowanie' => array(
                'silnik' => array('min'=>3, 'max'=>3),
            ),
        ),

        // ── MOTOROWODNY STERNIK MORSKI ────────────────────────────────────
        'Motorowodny Sternik Morski' => array(
            'zadania' => array(
                array('nazwa'=>'manewry na silniku - zadania',                               'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'kierowanie załogą',                                          'sekcja'=>'praktyka', 'zawsze_poz'=>true,  'zawsze_nie'=>false),
                array('nazwa'=>'człowiek za burtą',                                          'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście i odejście od boi',                                  'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'dojście i odejście od nabrzeża',                             'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'praca w charakterze członka załogi',                         'sekcja'=>'praktyka', 'zawsze_poz'=>true,  'zawsze_nie'=>false),
            ),
            'losowanie' => array(
                'praktyka' => array('min'=>3, 'max'=>3),
            ),
        ),

        // ── LICENCJA HOLOWANIA ────────────────────────────────────────────
        'Licencja do holowania narciarza wodnego lub innych obiektów' => array(
            'zadania' => array(
                array('nazwa'=>'start z wody',                                               'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'start z pomostu lub innego pełniącego podobne funkcje urządzenia', 'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'holowanie narciarza lub innych obiektów pływających po prostej',   'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'holowanie narciarza lub innych obiektów pływających slalomem',     'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zakończenie holowania na wodzie',                             'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'zakończenie holowania na brzegu',                             'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'manewry jachtem, w tym zwroty',                               'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
                array('nazwa'=>'podejście do narciarza po upadku',                            'sekcja'=>'praktyka', 'zawsze_poz'=>false, 'zawsze_nie'=>false),
            ),
            'losowanie' => array(
                'praktyka' => array('min'=>4, 'max'=>5),
            ),
        ),
    );
}

/**
 * Losuje indeksy zadań które mają być poz. dla uczestnika.
 * Uwzględnia zawsze_poz, zawsze_nie oraz limity per sekcja.
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
        // Nowy system: losowanie per sekcja
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
        // Stary system fallback
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
