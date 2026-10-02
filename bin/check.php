<?php
/**
 * Pre-finish checks for the ocean-egzaminy plugin (see CLAUDE.md).
 *
 * Usage:
 *   php bin/check.php                 lint with every PHP found in .tools/ (or the current PHP)
 *   php bin/check.php path/to/php ... lint with the given PHP binaries
 *
 * The OE_PHP_BINARIES environment variable (list separated by PATH_SEPARATOR)
 * can also name the binaries. Exit code is 0 when every check passes.
 *
 * Written in PHP 7.4 syntax so it runs on the oldest supported version.
 */

$root       = dirname( __DIR__ );
$plugin_dir = $root . '/ocean-egzaminy';
$failures   = 0;

function oe_check_fail( $msg ) {
    global $failures;
    $failures++;
    echo "  FAIL  $msg\n";
}

function oe_check_php_files( $dirs ) {
    $out = array();
    foreach ( $dirs as $dir ) {
        if ( ! is_dir( $dir ) ) continue;
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $f ) {
            $path = str_replace( '\\', '/', $f->getPathname() );
            if ( substr( $path, -4 ) === '.php' && strpos( $path, '/vendor/' ) === false ) {
                $out[] = $path;
            }
        }
    }
    sort( $out );
    return $out;
}

function oe_check_rel( $path ) {
    global $root;
    return ltrim( substr( $path, strlen( str_replace( '\\', '/', $root ) ) ), '/' );
}

function oe_check_binaries( $argv, $root ) {
    $bins = array_slice( $argv, 1 );
    if ( ! $bins && getenv( 'OE_PHP_BINARIES' ) ) {
        $bins = array_filter( explode( PATH_SEPARATOR, getenv( 'OE_PHP_BINARIES' ) ) );
    }
    if ( ! $bins ) {
        foreach ( glob( $root . '/.tools/php*/php{,.exe}', GLOB_BRACE ) ?: array() as $b ) {
            $bins[] = $b;
        }
    }
    if ( ! $bins ) {
        $bins[] = PHP_BINARY;
    }
    return array_values( array_unique( $bins ) );
}

function oe_check_run( $cmd ) {
    $proc = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    if ( ! is_resource( $proc ) ) return array( 1, 'could not start ' . $cmd[0] );
    $out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    return array( proc_close( $proc ), trim( $out ) );
}

$files = oe_check_php_files( array( $plugin_dir, $root . '/bin' ) );

// 1. Syntax on every PHP version we target.
foreach ( oe_check_binaries( $argv, $root ) as $bin ) {
    list( , $ver ) = oe_check_run( array( $bin, '-r', 'echo PHP_VERSION;' ) );
    echo "[lint] PHP $ver\n";
    foreach ( $files as $f ) {
        list( $code, $out ) = oe_check_run( array( $bin, '-l', $f ) );
        if ( $code !== 0 ) oe_check_fail( oe_check_rel( $f ) . ': ' . $out );
    }
}

// 2. Duplicate top-level function names (PHP function names are case-insensitive).
echo "[functions] duplicate names\n";
$seen = array();
foreach ( oe_check_php_files( array( $plugin_dir ) ) as $f ) {
    if ( preg_match_all( '/^function\s+&?\s*([a-zA-Z_0-9]+)\s*\(/m', file_get_contents( $f ), $m ) ) {
        foreach ( $m[1] as $name ) {
            $key = strtolower( $name );
            if ( isset( $seen[ $key ] ) ) {
                oe_check_fail( "$name defined in " . oe_check_rel( $seen[ $key ] ) . ' and ' . oe_check_rel( $f ) );
            } else {
                $seen[ $key ] = $f;
            }
        }
    }
}

// 3. Forbidden syntax, em dash, BOM, unbalanced braces.
echo "[source] arrow functions, match expressions, em dash, BOM, braces\n";
foreach ( $files as $f ) {
    $src = file_get_contents( $f );
    $rel = oe_check_rel( $f );
    if ( substr( $src, 0, 3 ) === "\xEF\xBB\xBF" ) oe_check_fail( "$rel: starts with a UTF-8 BOM" );
    foreach ( preg_split( '/\R/', $src ) as $i => $line ) {
        $n = $i + 1;
        if ( preg_match( '/(?<![\w$>:])(fn|match)\s*\(/', $line, $mm ) ) {
            oe_check_fail( "$rel:$n: {$mm[1]}( is not allowed: " . trim( $line ) );
        }
        if ( strpos( $line, "\xE2\x80\x94" ) !== false ) {
            oe_check_fail( "$rel:$n: em dash: " . trim( $line ) );
        }
    }
    // Brace balance on PHP tokens only, so braces inside strings and inline HTML/JS do not count.
    $depth = 0;
    foreach ( token_get_all( $src ) as $tok ) {
        if ( is_array( $tok ) ) {
            if ( $tok[0] === T_CURLY_OPEN || $tok[0] === T_DOLLAR_OPEN_CURLY_BRACES ) $depth++;
            continue;
        }
        if ( $tok === '{' ) $depth++;
        if ( $tok === '}' ) $depth--;
    }
    if ( $depth !== 0 ) oe_check_fail( "$rel: unbalanced braces (depth $depth at end of file)" );
}

// 4. Bootstrap requires every include, and every required file exists.
echo "[bootstrap] includes\n";
$boot = file_get_contents( $plugin_dir . '/ocean-egzaminy.php' );
preg_match_all( "/require_once\s+OE_PATH\s*\.\s*'([^']+)'/", $boot, $req );
$required = $req[1];
foreach ( $required as $r ) {
    if ( ! is_file( $plugin_dir . '/' . $r ) ) oe_check_fail( "ocean-egzaminy.php requires missing file $r" );
}
foreach ( glob( $plugin_dir . '/includes/*.php' ) ?: array() as $inc ) {
    $r = 'includes/' . basename( $inc );
    if ( ! in_array( $r, $required, true ) ) oe_check_fail( "$r is not required in ocean-egzaminy.php" );
}

echo $failures ? "\n$failures problem(s) found.\n" : "\nAll checks passed.\n";
exit( $failures ? 1 : 0 );
