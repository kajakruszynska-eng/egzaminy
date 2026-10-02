<?php
/**
 * Builds the WP Admin upload zip: dist/ocean-egzaminy-<version>.zip
 * with entries under ocean-egzaminy/ (forward slashes on every OS).
 *
 * Usage: php bin/build-zip.php
 * Run php bin/check.php first.
 */

$root       = dirname( __DIR__ );
$plugin_dir = $root . '/ocean-egzaminy';
$main       = $plugin_dir . '/ocean-egzaminy.php';

if ( ! class_exists( 'ZipArchive' ) ) {
    fwrite( STDERR, "ZipArchive is not available in this PHP build.\n" );
    exit( 1 );
}

$version = '0.0.0';
if ( preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents( $main ), $m ) ) {
    $version = $m[1];
}

$dist = $root . '/dist';
if ( ! is_dir( $dist ) ) mkdir( $dist, 0777, true );
$zip_path = $dist . '/ocean-egzaminy-' . $version . '.zip';
if ( file_exists( $zip_path ) ) unlink( $zip_path );

$zip = new ZipArchive();
if ( $zip->open( $zip_path, ZipArchive::CREATE ) !== true ) {
    fwrite( STDERR, "Cannot create $zip_path\n" );
    exit( 1 );
}

$base = strlen( str_replace( '\\', '/', $plugin_dir ) ) + 1;
$it   = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS ),
    RecursiveIteratorIterator::SELF_FIRST
);
$zip->addEmptyDir( 'ocean-egzaminy' );
foreach ( $it as $f ) {
    $rel = 'ocean-egzaminy/' . substr( str_replace( '\\', '/', $f->getPathname() ), $base );
    if ( $f->isDir() ) {
        $zip->addEmptyDir( $rel );
    } else {
        $zip->addFile( $f->getPathname(), $rel );
    }
}
$zip->close();

$check = new ZipArchive();
$check->open( $zip_path );
if ( $check->locateName( 'ocean-egzaminy/ocean-egzaminy.php' ) === false ) {
    fwrite( STDERR, "Zip is missing ocean-egzaminy/ocean-egzaminy.php\n" );
    exit( 1 );
}
echo 'Built ' . $zip_path . ' (' . $check->numFiles . " entries)\n";
$check->close();
