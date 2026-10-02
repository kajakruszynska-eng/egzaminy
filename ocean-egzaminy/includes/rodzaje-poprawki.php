<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Corrections to the standard exam types requested by K on 2026-10-02.
 * Pure function so it can rewrite the shipped standard types, the seed file
 * and, once, the types already saved on a site (see rodzaje.php).
 *
 *  - JSM: remove "alarm człowiek za burtą" (duplicate of "człowiek za burtą"); navigation task.
 *  - MSM: navigation task.
 *  - SM: tasks 1-4 drawn, tasks 5 and 6 always passed (was: 1 and 6 always, 2-5 drawn).
 *  - LHN: code LDHN, name "... innych obiektów pływających".
 *
 * Takes and returns type data (oe_rodzaj_puste() keys) plus 'nazwa'. Idempotent.
 */
function oe_rodzaj_zastosuj_poprawki( $t ) {
    $nazwa = isset( $t['nazwa'] ) ? $t['nazwa'] : '';
    $skrot = isset( $t['skrot'] ) ? $t['skrot'] : '';

    if ( $skrot === 'JSM' || $nazwa === 'Jachtowy Sternik Morski' ) {
        foreach ( $t['sekcje'] as $si => $s ) {
            $t['sekcje'][ $si ]['zadania'] = array_values( array_filter( $s['zadania'], function( $z ) {
                return $z['nazwa'] !== 'alarm człowiek za burtą';
            } ) );
        }
        $t['nawigacja'] = true;
    }

    if ( $skrot === 'MSM' || $nazwa === 'Motorowodny Sternik Morski' ) {
        $t['nawigacja'] = true;
    }

    if ( $skrot === 'SM' || $nazwa === 'Sternik Motorowodny' ) {
        foreach ( $t['sekcje'] as $si => $s ) {
            foreach ( $s['zadania'] as $zi => $z ) {
                if ( $z['nazwa'] === 'kierowanie załogą' ) {
                    $t['sekcje'][ $si ]['zadania'][ $zi ]['zawsze_poz'] = false;
                }
                if ( $z['nazwa'] === 'praca w charakterze członka załogi' ) {
                    $t['sekcje'][ $si ]['zadania'][ $zi ]['zawsze_poz'] = true;
                    $t['sekcje'][ $si ]['zadania'][ $zi ]['zawsze_nie'] = false;
                }
            }
        }
    }

    if ( $skrot === 'LHN' ) {
        $t['skrot'] = 'LDHN';
    }
    if ( $nazwa === 'Licencja do holowania narciarza wodnego lub innych obiektów' ) {
        $t['nazwa'] = 'Licencja do holowania narciarza wodnego lub innych obiektów pływających';
    }
    return $t;
}
