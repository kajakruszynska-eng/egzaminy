<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The five standard Polish exam types with the practical tasks set by the
 * ministry, including the corrections in rodzaje-poprawki.php. Created
 * automatically when a site has no exam types. Organization-specific data
 * (decision numbers, venues, answer keys) is not here; it comes from the
 * settings import or is entered on the type screen.
 *
 * Generated from seed/ocean-wiedzy.json; keep the task order, the
 * deterministic draw depends on it.
 */
function oe_rodzaje_standardowe() {
    return array(
  0 =>
  array(
    'nazwa' => 'Sternik Motorowodny',
    'skrot' => 'SM',
    'karta_wiersze' =>
    array(
      0 => 'test z teorii',
      1 => 'praktyka',
    ),
    'zgoda_rodzicow' => true,
    'nawigacja' => false,
    'sekcje' =>
    array(
      0 =>
      array(
        'nazwa' => 'manewry na silniku - zadania',
        'min' => 3,
        'max' => 3,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'kierowanie załogą',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'człowiek za burtą',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'dojście i odejście od boi',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          3 =>
          array(
            'nazwa' => 'dojście i odejście od nabrzeża',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          4 =>
          array(
            'nazwa' => 'praca w charakterze członka załogi',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
          5 =>
          array(
            'nazwa' => 'prace bosmańskie',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
        ),
      ),
    ),
    'liczba_pytan' => 75,
  ),
  1 =>
  array(
    'nazwa' => 'Żeglarz Jachtowy',
    'skrot' => 'ZJ',
    'karta_wiersze' =>
    array(
      0 => 'test/y z teorii',
      1 => 'praktyka',
    ),
    'zgoda_rodzicow' => true,
    'nawigacja' => false,
    'sekcje' =>
    array(
      0 =>
      array(
        'nazwa' => 'manewry na żaglach - zadania',
        'min' => 4,
        'max' => 5,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'zwrot przez sztag',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'zwrot przez rufę',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'dojście do boi',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          3 =>
          array(
            'nazwa' => 'zejście z boi',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          4 =>
          array(
            'nazwa' => 'dojście do nabrzeża',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          5 =>
          array(
            'nazwa' => 'odejście od nabrzeża',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          6 =>
          array(
            'nazwa' => 'alarm człowiek za burtą',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          7 =>
          array(
            'nazwa' => 'stawanie w dryf',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          8 =>
          array(
            'nazwa' => 'dobór żagli / trym',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
          9 =>
          array(
            'nazwa' => 'prowadzenie jachtu przy spotkaniu z inną jednostką / wyprzedzanie',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
        ),
      ),
      1 =>
      array(
        'nazwa' => 'manewry na silniku - zadania',
        'min' => 1,
        'max' => 2,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'kotwiczenie',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'dojście do nabrzeża (silnik)',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'odejście od nabrzeża (silnik)',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
        ),
      ),
      2 =>
      array(
        'nazwa' => 'kierowanie załogą',
        'min' => 0,
        'max' => 0,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'wydawanie i egzekwowanie komend',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
        ),
      ),
      3 =>
      array(
        'nazwa' => 'inne',
        'min' => 0,
        'max' => 0,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'prace bosmańskie',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
        ),
      ),
    ),
    'liczba_pytan' => 75,
  ),
  2 =>
  array(
    'nazwa' => 'Jachtowy Sternik Morski',
    'skrot' => 'JSM',
    'karta_wiersze' =>
    array(
      0 => 'test/y z teorii',
      1 => 'zadanie nawigacyjne',
      2 => 'praktyka',
    ),
    'zgoda_rodzicow' => false,
    'nawigacja' => true,
    'sekcje' =>
    array(
      0 =>
      array(
        'nazwa' => 'manewry na żaglach - zadania',
        'min' => 3,
        'max' => 5,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'człowiek za burtą',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'zwrot przez sztag',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'zwrot przez rufę',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          3 =>
          array(
            'nazwa' => 'dojście do boi',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          4 =>
          array(
            'nazwa' => 'zejście z boi',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          5 =>
          array(
            'nazwa' => 'dojście do nabrzeża',
            'zawsze_poz' => false,
            'zawsze_nie' => true,
          ),
          6 =>
          array(
            'nazwa' => 'odejście od nabrzeża',
            'zawsze_poz' => false,
            'zawsze_nie' => true,
          ),
          7 =>
          array(
            'nazwa' => 'dobór żagli / trym',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          8 =>
          array(
            'nazwa' => 'prowadzenie jachtu przy spotkaniu z inną jednostką / wyprzedzanie',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
        ),
      ),
      1 =>
      array(
        'nazwa' => 'manewry na silniku - zadania',
        'min' => 1,
        'max' => 3,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'kotwiczenie',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'dojście do nabrzeża (silnik)',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'odejście od nabrzeża (silnik)',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
        ),
      ),
      2 =>
      array(
        'nazwa' => 'kierowanie załogą',
        'min' => 0,
        'max' => 0,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'wydawanie i egzekwowanie komend',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
        ),
      ),
    ),
    'liczba_pytan' => 75,
  ),
  3 =>
  array(
    'nazwa' => 'Motorowodny Sternik Morski',
    'skrot' => 'MSM',
    'karta_wiersze' =>
    array(
      0 => 'test z teorii',
      1 => 'praktyka',
    ),
    'zgoda_rodzicow' => false,
    'nawigacja' => true,
    'sekcje' =>
    array(
      0 =>
      array(
        'nazwa' => 'praktyka',
        'min' => 3,
        'max' => 3,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'manewry na silniku - zadania',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'kierowanie załogą',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'człowiek za burtą',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          3 =>
          array(
            'nazwa' => 'dojście i odejście od boi',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          4 =>
          array(
            'nazwa' => 'dojście i odejście od nabrzeża',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          5 =>
          array(
            'nazwa' => 'praca w charakterze członka załogi',
            'zawsze_poz' => true,
            'zawsze_nie' => false,
          ),
        ),
      ),
    ),
    'liczba_pytan' => 75,
  ),
  4 =>
  array(
    'nazwa' => 'Licencja do holowania narciarza wodnego lub innych obiektów pływających',
    'skrot' => 'LDHN',
    'karta_wiersze' =>
    array(
      0 => 'test z teorii',
      1 => 'praktyka',
    ),
    'zgoda_rodzicow' => false,
    'nawigacja' => false,
    'sekcje' =>
    array(
      0 =>
      array(
        'nazwa' => 'praktyka',
        'min' => 4,
        'max' => 5,
        'zadania' =>
        array(
          0 =>
          array(
            'nazwa' => 'start z wody',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          1 =>
          array(
            'nazwa' => 'start z pomostu lub innego pełniącego podobne funkcje urządzenia',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          2 =>
          array(
            'nazwa' => 'holowanie narciarza lub innych obiektów pływających po prostej',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          3 =>
          array(
            'nazwa' => 'holowanie narciarza lub innych obiektów pływających slalomem',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          4 =>
          array(
            'nazwa' => 'zakończenie holowania na wodzie',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          5 =>
          array(
            'nazwa' => 'zakończenie holowania na brzegu',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          6 =>
          array(
            'nazwa' => 'manewry jachtem, w tym zwroty',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
          7 =>
          array(
            'nazwa' => 'podejście do narciarza po upadku',
            'zawsze_poz' => false,
            'zawsze_nie' => false,
          ),
        ),
      ),
    ),
    'liczba_pytan' => 25,
  ),
);
}
