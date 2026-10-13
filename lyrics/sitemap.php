<?php
/**
 * XML sitemap for lyrics.darkmirage.com: the front page, every song, series and artist page.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
connection();

$base = 'https://lyrics.darkmirage.com/';
$urls = array( $base => null );
$sets = array();
foreach ( ly_songs() as $s ) {
	$urls[ $base . '?SongID=' . $s['id'] ] = substr( $s['date'], 0, 10 );
	if ( 1 !== $s['series']['id'] ) {
		$sets[ $base . '?SeriesID=' . $s['series']['id'] ] = max( $sets[ $base . '?SeriesID=' . $s['series']['id'] ] ?? '', substr( $s['date'], 0, 10 ) );
	}
	foreach ( $s['artists'] as $a ) {
		$sets[ $base . '?ArtistID=' . $a['id'] ] = max( $sets[ $base . '?ArtistID=' . $a['id'] ] ?? '', substr( $s['date'], 0, 10 ) );
	}
}
$urls[ $base ] = max( array_filter( $urls ) );
$urls         += $sets;

header( 'Content-Type: application/xml; charset=UTF-8' );
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ( $urls as $loc => $mod ) {
	printf( "<url><loc>%s</loc>%s</url>\n", htmlspecialchars( $loc, ENT_XML1 ), $mod ? '<lastmod>' . $mod . '</lastmod>' : '' );
}
echo "</urlset>\n";
