<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Frontend CSS ──────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular() ) return;

    global $post;
    if ( ! $post ) return;
    $ma_shortcode = has_shortcode( $post->post_content, 'formularz_egzaminu' )
                 || has_shortcode( $post->post_content, 'lista_egzaminow' );
    if ( ! $ma_shortcode ) return;

    wp_add_inline_style( 'wp-block-library', oe_get_frontend_css() );
} );

function oe_get_frontend_css() {
    return "
.oe-formularz-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    margin: 24px 0;
}
.oe-egzamin-info {
    background: #EBF5FB;
    border: 1px solid #AED6F1;
    border-radius: 6px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.oe-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px 20px;
}
.oe-info-grid > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.oe-info-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #1a5276;
    font-weight: 600;
}
.oe-info-grid strong {
    font-size: 14px;
    color: #1c2833;
}
.oe-komunikat {
    padding: 14px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
}
.oe-sukces {
    background: #E8F5E9;
    border: 1px solid #A5D6A7;
    color: #1b5e20;
}
.oe-blad {
    background: #FFEBEE;
    border: 1px solid #EF9A9A;
    color: #b71c1c;
}
.oe-sekcja-naglowek {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #1a5276;
    border-bottom: 1px solid #AED6F1;
    padding-bottom: 6px;
    margin: 20px 0 14px;
}
.oe-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 16px;
}
@media (max-width: 600px) {
    .oe-form-grid { grid-template-columns: 1fr; }
}
.oe-col-2 {
    grid-column: 1 / -1;
}
.oe-form-pole {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.oe-form-pole label {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
}
.oe-form-pole input[type='text'],
.oe-form-pole input[type='email'],
.oe-form-pole input[type='tel'],
.oe-form-pole input[type='date'],
.oe-form-pole input[type='number'] {
    padding: 9px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color .15s, box-shadow .15s;
    background: #fff;
    color: #1c2833;
    width: 100%;
    box-sizing: border-box;
}
.oe-form-pole input:focus {
    outline: none;
    border-color: #1a5276;
    box-shadow: 0 0 0 3px rgba(26,82,118,.12);
}
.oe-form-pole input:invalid:not(:placeholder-shown) {
    border-color: #e74c3c;
}
.oe-wymagane {
    color: #c0392b;
}
.oe-rodo {
    grid-column: 1 / -1;
    margin-top: 8px;
}
.oe-checkbox-label {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    cursor: pointer;
    font-size: 13px;
    line-height: 1.5;
    color: #555;
}
.oe-checkbox-label input[type='checkbox'] {
    margin-top: 3px;
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.oe-przycisk-zapisz {
    display: block;
    width: 100%;
    margin-top: 20px;
    padding: 13px 20px;
    background: #1a5276;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background .2s;
}
.oe-przycisk-zapisz:hover {
    background: #154360;
}
.oe-przycisk-zapisz:active {
    transform: scale(0.99);
}

/* ── Lista egzaminów [lista_egzaminow] ── */
.oe-lista-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    margin: 24px 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.oe-egz-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: box-shadow .15s, border-color .15s;
}
.oe-egz-card:hover {
    border-color: #AED6F1;
    box-shadow: 0 2px 8px rgba(26,82,118,.08);
}
.oe-egz-data {
    background: #EBF5FB;
    border-radius: 6px;
    padding: 8px 12px;
    text-align: center;
    min-width: 54px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
}
.oe-egz-dzien {
    font-size: 24px;
    font-weight: 600;
    color: #1a5276;
    line-height: 1;
}
.oe-egz-mies {
    font-size: 11px;
    color: #1a5276;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 600;
}
.oe-egz-rok {
    font-size: 10px;
    color: #5a8fa8;
}
.oe-egz-tresc {
    flex: 1;
    min-width: 0;
}
.oe-egz-tytul {
    font-size: 15px;
    font-weight: 600;
    color: #1c2833;
    margin-bottom: 4px;
}
.oe-egz-meta {
    font-size: 12px;
    color: #777;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.oe-sep {
    color: #ccc;
}
.oe-egz-prawa {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.oe-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.oe-badge-wolne {
    background: #E8F5E9;
    color: #2e7d32;
}
.oe-badge-pelny {
    background: #FFEBEE;
    color: #c62828;
}
.oe-btn-zapisz {
    display: inline-block;
    padding: 8px 16px;
    background: #1a5276;
    color: #fff !important;
    border-radius: 5px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none !important;
    white-space: nowrap;
    transition: background .15s;
    font-family: inherit;
}
.oe-btn-zapisz:hover {
    background: #154360;
    color: #fff !important;
}
.oe-btn-disabled {
    background: #bbb !important;
    pointer-events: none;
    cursor: not-allowed;
}
@media (max-width: 600px) {
    .oe-egz-card {
        flex-wrap: wrap;
        gap: 12px;
    }
    .oe-egz-prawa {
        width: 100%;
        justify-content: space-between;
    }
}
";
}

// ── Admin CSS ─────────────────────────────────────────────────────────────
add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->post_type, ['oe_egzamin', 'oe_zapis'] ) ) return;
    ?>
    <style>
    .column-akcja { width: 200px; }
    .column-status { width: 150px; }
    .column-zapisow { width: 120px; }
    .column-data_zapisu { width: 130px; }
    /* Status w liście posta */
    #post-status-display { font-weight: 600; }
    </style>
    <?php
} );

// ── Upewnij się że sesja jest dostępna ───────────────────────────────────
add_action( 'init', function() {
    if ( ! is_admin() && session_status() === PHP_SESSION_NONE ) {
        session_start();
    }
}, 1 );
