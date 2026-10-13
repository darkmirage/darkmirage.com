<?php
/**
 * Data and helpers for lyrics.darkmirage.com. Loads every song with its artists and series in one
 * query; the whole catalogue is under a hundred songs, so the pages filter and group in PHP.
 */

function ly_e( $s ): string {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/**
 * @return array<int,array> SongID => song
 */
function ly_songs(): array {
	global $db, $given_name_first;
	$name = $given_name_first ? "CONCAT(GivenName,' ',FamilyName)" : "CONCAT(FamilyName,' ',GivenName)";
	$note = $given_name_first ? "TRIM(CONCAT(COALESCE(Note,''),' ',COALESCE(Note2,'')))" : "TRIM(CONCAT(COALESCE(Note2,''),' ',COALESCE(Note,'')))";
	$res  = mysqli_query(
		$db,
		"SELECT s.SongID, s.SongTitle, s.KanjiSongTitle, s.EnglishSongTitle, s.SongType, s.Filename, s.DateAdded,
		        se.SeriesID, se.Title, se.KanjiTitle,
		        a.ArtistID, TRIM($name) AS Name, a.KanjiName, $note AS Note, c.KanjiNote
		 FROM song s
		 JOIN series se ON se.SeriesID = s.SeriesID
		 JOIN credit c ON c.SongID = s.SongID
		 JOIN artist a ON a.ArtistID = c.ArtistID
		 ORDER BY s.SongID, c.CreditID"
	);
	$songs = array();
	while ( $r = mysqli_fetch_assoc( $res ) ) {
		// Some names were entered as HTML (May&apos;n); decode so they are escaped exactly once.
		$r = array_map( static fn( $v ) => null === $v ? null : html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $r );
		$id = (int) $r['SongID'];
		if ( ! isset( $songs[ $id ] ) ) {
			$songs[ $id ] = array(
				'id'      => $id,
				'title'   => $r['SongTitle'],
				'kanji'   => (string) $r['KanjiSongTitle'],
				'english' => (string) $r['EnglishSongTitle'],
				'type'    => (string) $r['SongType'],
				'file'    => $r['Filename'],
				'date'    => $r['DateAdded'],
				'series'  => array( 'id' => (int) $r['SeriesID'], 'title' => $r['Title'], 'kanji' => (string) $r['KanjiTitle'] ),
				'artists' => array(),
			);
		}
		$songs[ $id ]['artists'][] = array(
			'id'    => (int) $r['ArtistID'],
			'name'  => $r['Name'],
			'kanji' => (string) $r['KanjiName'],
			'note'  => (string) $r['Note'],
			'knote' => (string) $r['KanjiNote'],
		);
	}
	return $songs;
}

/**
 * Display name in the chosen script, falling back to romaji.
 */
function ly_pick( string $romaji, string $kanji, bool $ja ): string {
	return ( $ja && '' !== $kanji ) ? $kanji : $romaji;
}

/**
 * The other script, shown small under the main name.
 */
function ly_alt( string $romaji, string $kanji, bool $ja ): string {
	if ( '' === $kanji ) {
		return '';
	}
	return $ja ? $romaji : $kanji;
}

/**
 * Short code for a song type: OP, ED, IN(sert), TH(eme), IM(age)…, and its colour family.
 *
 * @return array{0:string,1:string}
 */
function ly_type( string $type ): array {
	if ( '' === $type ) {
		return array( 'SONG', 'song' );
	}
	if ( preg_match( '/\bOP\b/i', $type ) ) {
		return array( 'OP', 'op' );
	}
	if ( preg_match( '/\bED\b/i', $type ) ) {
		return array( 'ED', 'ed' );
	}
	if ( preg_match( '/insert/i', $type ) ) {
		return array( 'INS', 'ins' );
	}
	return array( strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $type ), 0, 4 ) ), 'song' );
}

function ly_url( array $q ): string {
	$q = array_filter( $q, static fn( $v ) => null !== $v && '' !== $v && false !== $v );
	return '?' . http_build_query( $q );
}

/**
 * Lyric text as lines, BOM and trailing whitespace removed; null if the file is missing.
 */
function ly_lines( string $file, string $kind ): ?array {
	global $txt_folder;
	$path = __DIR__ . '/' . $txt_folder . '/' . basename( $file ) . '/' . $kind . '.txt';
	if ( ! is_file( $path ) ) {
		return null;
	}
	$text = (string) file_get_contents( $path );
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
	if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		$text = mb_convert_encoding( $text, 'UTF-8', 'SJIS-win' );
	}
	$lines = array_map( 'rtrim', preg_split( '/\r\n|\r|\n/', $text ) );
	while ( $lines && '' === end( $lines ) ) {
		array_pop( $lines );
	}
	return $lines;
}

function ly_cover( string $file ): ?string {
	global $txt_folder;
	$rel = $txt_folder . '/' . rawurlencode( basename( $file ) ) . '/cover.jpg';
	return is_file( __DIR__ . '/' . $txt_folder . '/' . basename( $file ) . '/cover.jpg' ) ? $rel : null;
}
