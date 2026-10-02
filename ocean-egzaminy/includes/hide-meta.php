<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ukrywa datę i autora pod tytułem wpisu dla Custom Post Type oe_egzamin.
 * Działa dla większości motywów przez CSS + filtr the_date/get_the_date.
 */
add_action( 'wp_head', function() {
    // Ukryj dla CPT oe_egzamin
    $hide_selectors = implode(', ', [
        // Universalne klasy używane przez popularne motywy
        '.oe_egzamin .entry-meta',
        '.oe_egzamin .post-meta',
        '.oe_egzamin .entry-header .posted-on',
        '.oe_egzamin .entry-header .byline',
        '.oe_egzamin .entry-header time',
        '.oe_egzamin .entry-header .author',
        // Elementor
        '.elementor-post__meta-data',
        // Astra
        '.ast-post-format-details .entry-meta',
        // Jeśli strona to singular oe_egzamin
        'body.single-oe_egzamin .entry-meta',
        'body.single-oe_egzamin .post-meta',
        'body.single-oe_egzamin .posted-on',
        'body.single-oe_egzamin .byline',
        'body.single-oe_egzamin .entry-date',
        'body.single-oe_egzamin time.entry-date',
        'body.single-oe_egzamin .author.vcard',
        'body.single-oe_egzamin span.posted-on',
        'body.single-oe_egzamin span.byline',
        'body.single-oe_egzamin .ast-blog-single-element',
        // GeneratePress
        'body.single-oe_egzamin .entry-header .entry-meta',
        // Kadence
        'body.single-oe_egzamin .entry-meta',
        // OceanWP (używany przez Ocean Wiedzy?)
        'body.single-oe_egzamin .blog-entry-header .blog-entry-meta',
    ]);
    ?>
    <style id="oe-hide-post-meta">
    <?php echo $hide_selectors; ?> {
        display: none !important;
    }
    </style>
    <?php
} );
