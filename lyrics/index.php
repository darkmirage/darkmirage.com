<?php
/**
 * lyrics.darkmirage.com: anime and J-pop lyrics, transliterated and translated by DarkMirage.
 * Shares the header, footer and design system of www.darkmirage.com (blog theme "dmdune").
 *
 * URLs from the 2006 site still work: ?view=series|artist|title, ?SeriesID=, ?ArtistID=,
 * ?SongID= (&display=jpn for the Japanese text), ?lang=e|j for romaji or Japanese names.
 */

require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/../blog/wp-content/themes/dmdune/chrome/chrome.php';
connection();

$chrome = array_merge(
	array( 'site' => 'lyrics', 'placeholder' => 'Find a song, artist or series', 'note' => 'All lyrics transliterated and translated by DarkMirage · Free to use if you credit the source' ),
	$dm_chrome ?? array()
);

$songs     = ly_songs();
$lang      = in_array( $_GET['lang'] ?? '', array( 'e', 'j' ), true ) ? $_GET['lang'] : '';
$ja        = 'j' === $lang;
$song_id   = (int) ( $_GET['SongID'] ?? 0 );
$artist_id = (int) ( $_GET['ArtistID'] ?? 0 );
$series_id = (int) ( $_GET['SeriesID'] ?? 0 );
$view      = in_array( $_GET['view'] ?? '', array( 'series', 'artist', 'title' ), true ) ? $_GET['view'] : 'series';
$display   = 'jpn' === ( $_GET['display'] ?? '' ) ? 'jpn' : '';
$q         = trim( (string) ( $_GET['q'] ?? '' ) );
$song      = $song_id ? ( $songs[ $song_id ] ?? null ) : null;

if ( $song_id && ! $song ) {
	http_response_code( 404 );
}

function ly_era( int $y ): string {
	return $y <= 2005 ? 'default' : ( $y <= 2007 ? 'kubrick' : 'blocks' );
}

function ly_year( array $s ): int {
	return (int) substr( $s['date'], 0, 4 );
}

/**
 * One song as a log row: type tile (or cover), title in the chosen script, the other script,
 * then artists and series.
 */
function ly_row( array $s, bool $ja, string $lang, string $hide = '' ): string {
	list( $code, $kind ) = ly_type( $s['type'] );
	$cover = ly_cover( $s['file'] );
	$tile  = $cover
		? '<span class="ly-tile ly-tile--cover"><img src="' . ly_e( $cover ) . '" alt="" loading="lazy"></span>'
		: '<span class="ly-tile" data-kind="' . ly_e( $kind ) . '">' . ly_e( $code ) . '</span>';
	$by = array();
	foreach ( $s['artists'] as $a ) {
		$by[] = '<a href="' . ly_e( ly_url( array( 'lang' => $lang, 'ArtistID' => $a['id'] ) ) ) . '">' . ly_e( ly_pick( $a['name'], $a['kanji'], $ja ) ) . '</a>';
	}
	$meta = array();
	if ( 'artist' !== $hide ) {
		$meta[] = implode( ' <span class="ly-slash">/</span> ', $by );
	}
	if ( 'series' !== $hide && 1 !== $s['series']['id'] ) {
		$meta[] = '<a href="' . ly_e( ly_url( array( 'lang' => $lang, 'SeriesID' => $s['series']['id'] ) ) ) . '">' . ly_e( ly_pick( $s['series']['title'], $s['series']['kanji'], $ja ) ) . '</a>';
	}
	if ( '' !== $s['type'] ) {
		$meta[] = '<span class="ly-type">' . ly_e( $s['type'] ) . '</span>';
	}
	$alt  = ly_alt( $s['title'], $s['kanji'], $ja );
	$find = mb_strtolower( implode( ' ', array_merge(
		array( $s['title'], $s['kanji'], $s['english'], $s['series']['title'], $s['series']['kanji'] ),
		array_column( $s['artists'], 'name' ),
		array_column( $s['artists'], 'kanji' )
	) ) );
	return sprintf(
		'<li class="ly-song" data-song="%4$d" data-era="%1$s" data-find="%2$s">%3$s<div class="ly-song__body"><p class="ly-song__stamp"><span>LY·%4$03d</span><time datetime="%5$s">%6$s</time></p><h3 class="ly-song__title"><a href="%7$s">%8$s</a>%9$s</h3><p class="ly-song__meta">%10$s</p></div></li>',
		ly_e( ly_era( ly_year( $s ) ) ),
		ly_e( $find ),
		$tile,
		$s['id'],
		ly_e( substr( $s['date'], 0, 10 ) ),
		ly_e( str_replace( '-', '.', substr( $s['date'], 0, 10 ) ) ),
		ly_e( ly_url( array( 'lang' => $lang, 'SongID' => $s['id'] ) ) ),
		ly_e( ly_pick( $s['title'], $s['kanji'], $ja ) ),
		'' !== $alt ? ' <span class="ly-alt" lang="' . ( $ja ? 'en' : 'ja' ) . '">' . ly_e( $alt ) . '</span>' : '',
		implode( '<span class="dm-sep" aria-hidden="true">·</span>', $meta )
	);
}

/**
 * Songs added per year as a spectrum, like the blog's list headers.
 */
function ly_spectrum( array $songs ): string {
	$years = array();
	foreach ( $songs as $s ) {
		$y           = ly_year( $s );
		$years[ $y ] = ( $years[ $y ] ?? 0 ) + 1;
	}
	if ( ! $years ) {
		return '';
	}
	$max  = max( $years );
	$cols = '';
	for ( $y = 2006; $y <= 2011; $y++ ) {
		$n     = $years[ $y ] ?? 0;
		$cols .= sprintf(
			'<span class="dm-spec__col%s is-tick" data-era="%s" title="%d · %d songs added"><span class="dm-spec__bar" style="--h:%.3f"></span><span class="dm-spec__tick">\'%s</span></span>',
			$n ? '' : ' is-empty',
			ly_era( $y ),
			$y,
			$n,
			$n ? max( 0.06, $n / $max ) : 0,
			substr( (string) $y, 2 )
		);
	}
	return '<div class="dm-spec dm-spec--years">' . $cols . '</div>';
}

$base  = dmc_config( $chrome )['lyrics'];
$title = 'Lyrics Translations by DarkMirage';
$meta  = array(
	'title'       => 'Anime & J-pop Lyrics Translations',
	'description' => 'Anime and J-pop song lyrics in rōmaji, Japanese and English, transliterated and translated by DarkMirage since 2006. ' . count( $songs ) . ' songs from Macross Frontier, Gundam SEED Destiny, Bottle Fairy and more. Free to use with credit.',
	'url'         => $base,
	'jsonld'      => array( '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'DarkMirage Lyrics', 'url' => $base, 'inLanguage' => array( 'en', 'ja' ), 'author' => dmc_person( $chrome ) ),
);
if ( $song ) {
	$artist   = implode( ' & ', array_column( $song['artists'], 'name' ) );
	$has      = array_filter( array( 'rōmaji' => ly_lines( $song['file'], 'romaji' ), 'English translation' => ly_lines( $song['file'], 'english' ), 'Japanese' => ly_lines( $song['file'], 'kanji' ) ) );
	$kind     = preg_match( '/^[A-Z][a-z]+$/', $song['type'] ) ? strtolower( $song['type'] ) : $song['type'];
	$from     = 1 !== $song['series']['id'] ? ', ' . ( '' !== $song['type'] ? 'the ' . $kind . ' ' . ( preg_match( '/\b(OP|ED)\b/', $song['type'] ) ? '' : 'song ' ) . 'from ' : 'from ' ) . $song['series']['title'] : '';
	$cover    = ly_cover( $song['file'] );
	$title    = $song['title'] . ( '' !== $song['kanji'] ? ' (' . $song['kanji'] . ')' : '' ) . ' by ' . $artist . ' · Lyrics & Translation';
	$meta     = array(
		'title'       => $song['title'] . ( '' !== $song['kanji'] ? ' ' . $song['kanji'] : '' ) . ' · ' . $artist,
		'description' => 'Lyrics to “' . $song['title'] . '”' . ( '' !== $song['kanji'] ? ' (' . $song['kanji'] . ')' : '' ) . ' by ' . $artist . $from . ', in ' . ( $has ? preg_replace( '/, ([^,]+)$/', ' and $1', implode( ', ', array_keys( $has ) ) ) : 'rōmaji' ) . '. Translated by DarkMirage.',
		'url'         => $base . '?SongID=' . $song['id'],
		'type'        => 'music.song',
		'image'       => $cover ? $base . $cover : null,
		'image_alt'   => $cover ? 'Cover art for ' . $song['title'] : null,
		'image_w'     => $cover ? 300 : null,
		'jsonld'      => array_filter( array(
			'@context'      => 'https://schema.org',
			'@type'         => 'MusicComposition',
			'name'          => $song['title'],
			'alternateName' => '' !== $song['kanji'] ? $song['kanji'] : null,
			'url'           => $base . '?SongID=' . $song['id'],
			'image'         => $cover ? $base . $cover : null,
			'inLanguage'    => 'ja',
			'recordedAs'    => array( '@type' => 'MusicRecording', 'name' => $song['title'], 'byArtist' => array_map( static fn( $a ) => array( '@type' => 'MusicGroup', 'name' => $a['name'] ), $song['artists'] ) ),
			'lyrics'        => array( '@type' => 'CreativeWork', 'inLanguage' => 'en', 'translator' => dmc_person( $chrome ), 'dateCreated' => substr( $song['date'], 0, 10 ) ),
		) ),
	);
	if ( '' === (string) $meta['image'] ) {
		unset( $meta['image'], $meta['image_alt'], $meta['image_w'] );
	}
} elseif ( $song_id ) {
	$title = 'Song not found · ' . $title;
	$meta  = array( 'title' => 'Song not found', 'robots' => 'noindex' );
} elseif ( $series_id || $artist_id ) {
	$one = null;
	foreach ( $songs as $s ) {
		if ( $series_id && $s['series']['id'] === $series_id ) {
			$one = array( $s['series']['title'], $s['series']['kanji'], 'series' );
			break;
		}
		foreach ( $s['artists'] as $a ) {
			if ( $artist_id && $a['id'] === $artist_id ) {
				$one = array( $a['name'], $a['kanji'], 'artist' );
				break 2;
			}
		}
	}
	if ( $one ) {
		$label = $one[0] . ( '' !== $one[1] ? ' (' . $one[1] . ')' : '' );
		$title = $label . ' lyrics · ' . $title;
		$meta  = array(
			'title'       => $label . ' lyrics',
			'description' => 'Rōmaji, Japanese and English lyrics for songs ' . ( 'series' === $one[2] ? 'from ' : 'by ' ) . $label . ', translated by DarkMirage.',
			'url'         => $base . '?' . ( $series_id ? 'SeriesID=' . $series_id : 'ArtistID=' . $artist_id ),
		);
	} else {
		$meta = array( 'title' => 'Not found', 'robots' => 'noindex' );
	}
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo ly_e( $title ); ?></title>
<?php dmc_meta( $chrome, $meta ); ?>
<?php dmc_head( $chrome ); ?>
<link rel="stylesheet" href="lyrics.css?v=<?php echo (int) filemtime( __DIR__ . '/lyrics.css' ); ?>">
<link rel="alternate" type="application/rss+xml" title="New lyrics" href="<?php echo ly_e( $base ); ?>feed.php">
</head>
<body>
<?php dmc_header( $chrome ); ?>
<main id="dm-main" class="ly-main" data-state="<?php echo ly_e( $lang . '|' . $display . '|' . ( $song_id ?: '' ) ); ?>">
<?php if ( $song ) :
	list( $code, $kind ) = ly_type( $song['type'] );
	$romaji  = ly_lines( $song['file'], 'romaji' );
	$english = ly_lines( $song['file'], 'english' );
	$kanji   = ly_lines( $song['file'], 'kanji' );
	if ( 'jpn' === $display && ! $kanji ) {
		$display = '';
	}
	$cover = ly_cover( $song['file'] );
	$alt   = ly_alt( $song['title'], $song['kanji'], $ja || 'jpn' === $display );
	?>
	<article class="ly-sheet" data-era="<?php echo ly_e( ly_era( ly_year( $song ) ) ); ?>">
		<header class="ly-sheet__head">
			<div class="ly-sheet__text">
				<p class="dm-hud-code">// LY·<?php printf( '%03d', $song['id'] ); ?> · <?php echo ly_e( strtoupper( $song['type'] ?: 'Song' ) ); ?> · Added <?php echo ly_e( str_replace( '-', '.', substr( $song['date'], 0, 10 ) ) ); ?></p>
				<h1><?php echo ly_e( ly_pick( $song['title'], $song['kanji'], $ja || 'jpn' === $display ) ); ?></h1>
				<?php if ( '' !== $alt ) : ?><p class="ly-sheet__alt" lang="<?php echo $ja || 'jpn' === $display ? 'en' : 'ja'; ?>"><?php echo ly_e( $alt ); ?></p><?php endif; ?>
				<?php if ( '' !== $song['english'] ) : ?><p class="ly-sheet__english">“<?php echo ly_e( $song['english'] ); ?>”</p><?php endif; ?>
				<dl class="ly-credits">
					<div><dt>Sung by</dt><dd><?php
						$parts = array();
						foreach ( $song['artists'] as $a ) {
							$note    = ly_pick( $a['note'], $a['knote'], $ja );
							$parts[] = '<a href="' . ly_e( ly_url( array( 'lang' => $lang, 'ArtistID' => $a['id'] ) ) ) . '">' . ly_e( ly_pick( $a['name'], $a['kanji'], $ja ) ) . '</a>' . ( '' !== $note ? ' <span class="ly-as">as ' . ly_e( $note ) . '</span>' : '' );
						}
						echo implode( '<br>', $parts );
					?></dd></div>
					<?php if ( 1 !== $song['series']['id'] ) : ?>
					<div><dt>From</dt><dd><a href="<?php echo ly_e( ly_url( array( 'lang' => $lang, 'SeriesID' => $song['series']['id'] ) ) ); ?>"><?php echo ly_e( ly_pick( $song['series']['title'], $song['series']['kanji'], $ja ) ); ?></a><?php if ( '' !== $song['type'] ) : ?> <span class="ly-type"><?php echo ly_e( $song['type'] ); ?></span><?php endif; ?></dd></div>
					<?php endif; ?>
				</dl>
			</div>
			<?php if ( $cover ) : ?>
				<figure class="dm-frame ly-sheet__cover"><img src="<?php echo ly_e( $cover ); ?>" alt="Cover of <?php echo ly_e( $song['title'] ); ?>"><span class="dm-frame__scan"></span></figure>
			<?php else : ?>
				<div class="ly-sheet__disc" data-kind="<?php echo ly_e( $kind ); ?>" aria-hidden="true"><span><?php echo ly_e( $code ); ?></span></div>
			<?php endif; ?>
		</header>

		<nav class="ly-tabs" aria-label="Lyrics version">
			<?php
			$main_label = $romaji && $english ? 'Rōmaji + English' : ( $romaji ? 'Rōmaji' : 'English' );
			if ( $romaji || $english ) {
				printf( '<a href="%s"%s>%s</a>', ly_e( ly_url( array( 'lang' => $lang, 'SongID' => $song['id'] ) ) ), 'jpn' !== $display ? ' aria-current="page"' : '', ly_e( $main_label ) );
			}
			if ( $kanji ) {
				printf( '<a href="%s"%s lang="ja">日本語</a>', ly_e( ly_url( array( 'lang' => $lang, 'SongID' => $song['id'], 'display' => 'jpn' ) ) ), 'jpn' === $display ? ' aria-current="page"' : '' );
			}
			?>
			<a class="ly-tabs__print" href="print.php?SongID=<?php echo (int) $song['id']; ?>">Printable</a>
		</nav>

		<div class="ly-text<?php echo 'jpn' === $display ? ' ly-text--ja' : ( $romaji && $english ? ' ly-text--pair' : '' ); ?>">
			<?php
			if ( 'jpn' === $display ) {
				$lines = $kanji;
			} else {
				$lines = $romaji ?: $english;
			}
			$stanza = array();
			$out    = '';
			$flush  = static function () use ( &$stanza, &$out ) {
				if ( $stanza ) {
					$out   .= '<div class="ly-stanza">' . implode( '', $stanza ) . '</div>';
					$stanza = array();
				}
			};
			foreach ( (array) $lines as $i => $line ) {
				if ( '' === trim( $line ) ) {
					$flush();
					continue;
				}
				if ( 'jpn' !== $display && $romaji && $english ) {
					$stanza[] = '<p class="ly-line"><span class="ly-line__r">' . ly_e( $line ) . '</span><span class="ly-line__e">' . ly_e( $english[ $i ] ?? '' ) . '</span></p>';
				} else {
					$stanza[] = '<p class="ly-line"' . ( 'jpn' === $display ? ' lang="ja"' : '' ) . '>' . ly_e( $line ) . '</p>';
				}
			}
			$flush();
			echo $out ?: '<p class="ly-empty">The text for this song is missing.</p>';
			?>
		</div>
		<p class="ly-credit">Transliterated and translated by <a href="https://www.darkmirage.com/">DarkMirage</a>. You may use these lyrics if you credit the source.</p>
	</article>

	<?php
	$siblings = array_filter( $songs, static fn( $s ) => $s['series']['id'] === $song['series']['id'] && $s['id'] !== $song['id'] && 1 !== $song['series']['id'] );
	if ( $siblings ) :
		?>
		<section class="dm-unit ly-more">
			<?php echo dmc_hud( 'SAME SERIES', 'More from ' . ly_pick( $song['series']['title'], $song['series']['kanji'], $ja ) ); ?>
			<ul class="ly-songs"><?php foreach ( $siblings as $s ) { echo ly_row( $s, $ja, $lang, 'series' ); } ?></ul>
		</section>
	<?php endif; ?>

<?php elseif ( $song_id ) : ?>
	<section class="ly-head"><div><p class="dm-hud-code">// NO SIGNAL</p><h1>Song not found</h1><p class="dm-meta"><a href="./">Back to all lyrics</a></p></div></section>

<?php else :
	$list = $songs;
	$code = 'LYRICS · TRANSLATION LOG';
	$head = 'Lyrics Translations';
	$sub  = '歌詞の翻訳';
	$hide = '';
	if ( $series_id ) {
		$list = array_filter( $songs, static fn( $s ) => $s['series']['id'] === $series_id );
		$one  = reset( $list );
		$code = 'SERIES';
		$head = $one ? ly_pick( $one['series']['title'], $one['series']['kanji'], $ja ) : 'Unknown series';
		$sub  = $one ? ly_alt( $one['series']['title'], $one['series']['kanji'], $ja ) : '';
		$hide = 'series';
	} elseif ( $artist_id ) {
		$list = array_filter( $songs, static fn( $s ) => in_array( $artist_id, array_column( $s['artists'], 'id' ), true ) );
		$one  = $list ? current( array_filter( reset( $list )['artists'], static fn( $a ) => $a['id'] === $artist_id ) ) : null;
		$code = 'ARTIST';
		$head = $one ? ly_pick( $one['name'], $one['kanji'], $ja ) : 'Unknown artist';
		$sub  = $one ? ly_alt( $one['name'], $one['kanji'], $ja ) : '';
		$hide = 'artist';
	}
	$n_series  = count( array_unique( array_map( static fn( $s ) => $s['series']['id'], $list ) ) );
	$n_artists = count( array_unique( array_merge( ...array_map( static fn( $s ) => array_column( $s['artists'], 'id' ), array_values( $list ) ?: array( array( 'artists' => array() ) ) ) ) ) );
	$dates     = array_map( 'ly_year', $list );
	$filtered  = $series_id || $artist_id;
	?>
	<header class="dm-archive-head ly-head">
		<div class="dm-archive-head__text">
			<p class="dm-hud-code">// <?php echo ly_e( $code ); ?></p>
			<h1><?php echo ly_e( $head ); ?><?php if ( '' !== $sub ) : ?> <span class="dm-archive-head__ja" lang="<?php echo $ja && $filtered ? 'en' : 'ja'; ?>"><?php echo ly_e( $sub ); ?></span><?php endif; ?></h1>
			<p class="dm-meta"><?php
				$bits = array( count( $list ) . ( 1 === count( $list ) ? ' song' : ' songs' ) );
				if ( ! $series_id ) { $bits[] = $n_series . ' series'; }
				if ( ! $artist_id ) { $bits[] = $n_artists . ( 1 === $n_artists ? ' artist' : ' artists' ); }
				if ( $dates ) { $bits[] = min( $dates ) === max( $dates ) ? min( $dates ) : min( $dates ) . '–' . max( $dates ); }
				echo ly_e( implode( ' · ', $bits ) );
			?></p>
		</div>
		<div class="dm-archive-head__spec"><?php echo ly_spectrum( $list ); ?></div>
	</header>

	<div class="ly-controls">
		<?php if ( $filtered ) : ?>
			<a class="ly-back" href="<?php echo ly_e( ly_url( array( 'lang' => $lang, 'view' => $artist_id ? 'artist' : 'series' ) ) ); ?>">← All <?php echo $artist_id ? 'artists' : 'series'; ?></a>
		<?php else : ?>
			<nav class="ly-seg" data-group aria-label="Group songs by">
				<?php foreach ( array( 'series' => 'Series', 'artist' => 'Artists', 'title' => 'A–Z' ) as $k => $label ) : ?>
					<a href="<?php echo ly_e( ly_url( array( 'lang' => $lang, 'view' => $k ) ) ); ?>"<?php echo $view === $k ? ' aria-current="page"' : ''; ?>><?php echo ly_e( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<nav class="ly-seg ly-seg--lang" aria-label="Show names in">
			<?php
			$keep = array( 'view' => $filtered ? null : $view, 'SeriesID' => $series_id ?: null, 'ArtistID' => $artist_id ?: null );
			printf( '<a href="%s"%s>Rōmaji</a>', ly_e( ly_url( $keep + array( 'lang' => 'e' ) ) ), $ja ? '' : ' aria-current="true"' );
			printf( '<a href="%s"%s lang="ja">日本語</a>', ly_e( ly_url( $keep + array( 'lang' => 'j' ) ) ), $ja ? ' aria-current="true"' : '' );
			?>
		</nav>
		<form class="ly-find" role="search" onsubmit="return false">
			<label class="screen-reader-text" for="find">Find</label>
			<?php echo dmc_icon( 'search', 16 ); ?>
			<input id="find" type="search" placeholder="Find a song, artist or series" value="<?php echo ly_e( $q ); ?>" autocomplete="off">
		</form>
	</div>

	<?php
	// Every grouping is rendered up front so the Series / Artists / A–Z switch happens in place,
	// with the songs animating to their new spots (see the script below), instead of a reload.
	$build = static function ( string $by ) use ( $list, $ja, $lang, $filtered ): array {
		$groups = array();
		if ( $filtered ) {
			$groups[''] = array( 'label' => '', 'alt' => '', 'url' => '', 'songs' => $list );
		} elseif ( 'title' === $by ) {
			$sorted = $list;
			uasort( $sorted, static fn( $a, $b ) => strcasecmp( ly_pick( $a['title'], $a['kanji'], $ja ), ly_pick( $b['title'], $b['kanji'], $ja ) ) );
			foreach ( $sorted as $s ) {
				$first = mb_strtoupper( mb_substr( ly_pick( $s['title'], $s['kanji'], $ja ), 0, 1 ) );
				$key   = preg_match( '/[A-Z]/', $first ) ? $first : ( $ja ? $first : '#' );
				$groups[ $key ]['label']   = $key;
				$groups[ $key ]['letter']  = true;
				$groups[ $key ]['songs'][] = $s;
			}
		} elseif ( 'artist' === $by ) {
			foreach ( $list as $s ) {
				foreach ( $s['artists'] as $a ) {
					$groups[ $a['id'] ]['label']   = ly_pick( $a['name'], $a['kanji'], $ja );
					$groups[ $a['id'] ]['alt']     = ly_alt( $a['name'], $a['kanji'], $ja );
					$groups[ $a['id'] ]['url']     = ly_url( array( 'lang' => $lang, 'ArtistID' => $a['id'] ) );
					$groups[ $a['id'] ]['songs'][] = $s;
				}
			}
			uasort( $groups, static fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );
		} else {
			foreach ( $list as $s ) {
				$sid                       = $s['series']['id'];
				$groups[ $sid ]['label']   = 1 === $sid ? 'J-pop and others' : ly_pick( $s['series']['title'], $s['series']['kanji'], $ja );
				$groups[ $sid ]['alt']     = 1 === $sid ? '' : ly_alt( $s['series']['title'], $s['series']['kanji'], $ja );
				$groups[ $sid ]['url']     = ly_url( array( 'lang' => $lang, 'SeriesID' => $sid ) );
				$groups[ $sid ]['songs'][] = $s;
			}
			uasort( $groups, static fn( $a, $b ) => ( 'J-pop and others' === $a['label'] ) <=> ( 'J-pop and others' === $b['label'] ) ?: strcasecmp( $a['label'], $b['label'] ) );
		}
		return $groups;
	};
	$views = $filtered ? array( $view ) : array( 'series', 'artist', 'title' );
	?>
	<div class="ly-groups" id="ly-groups">
		<?php foreach ( $views as $by ) : ?>
			<div class="ly-view" data-view="<?php echo ly_e( $by ); ?>"<?php echo $by === $view ? '' : ' hidden'; ?>>
				<?php foreach ( $build( $by ) as $g ) : ?>
					<section class="ly-group<?php echo ! empty( $g['letter'] ) ? ' ly-group--letter' : ''; ?>">
						<?php if ( ! empty( $g['letter'] ) ) : ?>
							<h2 class="ly-letter"><?php echo ly_e( $g['label'] ); ?></h2>
						<?php elseif ( '' !== $g['label'] ) : ?>
							<h2 class="ly-group__head"><a href="<?php echo ly_e( $g['url'] ); ?>"><?php echo ly_e( $g['label'] ); ?></a><?php if ( ! empty( $g['alt'] ) ) : ?> <span class="ly-alt"><?php echo ly_e( $g['alt'] ); ?></span><?php endif; ?><span class="ly-group__n"><?php echo count( $g['songs'] ); ?></span></h2>
						<?php endif; ?>
						<ul class="ly-songs"><?php foreach ( $g['songs'] as $s ) { echo ly_row( $s, $ja, $lang, $filtered ? $hide : ( 'series' === $by ? 'series' : ( 'artist' === $by ? 'artist' : '' ) ) ); } ?></ul>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<p class="ly-none" hidden>No songs match. Try a romaji or Japanese name.</p>
	</div>
<?php endif; ?>
</main>
<?php dmc_footer( $chrome ); ?>
<script>
(function () {
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
	function transition(update) {
		if (document.startViewTransition && !reduce.matches) return document.startViewTransition(update);
		update();
		return null;
	}

	// Find box: filters every grouping at once.
	function apply() {
		var input = document.getElementById('find');
		if (!input) return;
		var view = document.querySelector('.ly-view:not([hidden])');
		var words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
		var shown = 0;
		document.querySelectorAll('.ly-song').forEach(function (r) {
			var hay = r.getAttribute('data-find') || '';
			var ok = words.every(function (w) { return hay.indexOf(w) !== -1; });
			r.hidden = !ok;
			if (ok && view && view.contains(r)) shown++;
		});
		document.querySelectorAll('.ly-group').forEach(function (g) { g.hidden = !g.querySelector('.ly-song:not([hidden])'); });
		var none = document.querySelector('.ly-none');
		if (none) none.hidden = shown > 0;
	}
	document.addEventListener('input', function (e) { if (e.target.id === 'find') apply(); });

	// Series / Artists / A–Z swap the pre-rendered grouping in place. Between Series and Artists the
	// songs on screen slide to their new places; A–Z rearranges everything, so it cross-fades.
	function inView(el) {
		var r = el.getBoundingClientRect();
		return r.bottom > 0 && r.top < innerHeight;
	}
	function label(view, ids) {
		var seen = {};
		document.querySelectorAll('.ly-song').forEach(function (r) { r.style.viewTransitionName = ''; });
		if (!ids) return;
		var v = document.querySelector('.ly-view[data-view="' + view + '"]');
		v.querySelectorAll('.ly-song').forEach(function (r) {
			var id = r.getAttribute('data-song');
			if (ids[id] && !seen[id]) {
				seen[id] = true;
				r.style.viewTransitionName = 'ly-song-' + id;
			}
		});
	}
	function group(view, push) {
		var current = document.querySelector('.ly-view:not([hidden])');
		if (!current || current.getAttribute('data-view') === view) return;
		var from = current.getAttribute('data-view');
		var ids = null;
		if (from !== 'title' && view !== 'title') {
			ids = {};
			current.querySelectorAll('.ly-song:not([hidden])').forEach(function (r) { if (inView(r)) ids[r.getAttribute('data-song')] = true; });
		}
		if (push) {
			var url = new URL(location.href);
			url.searchParams.set('view', view);
			history.pushState(null, '', url.pathname + url.search);
		}
		label(from, ids);
		document.documentElement.classList.toggle('ly-moving', !!ids);
		var t = transition(function () {
			document.querySelectorAll('.ly-view').forEach(function (v) { v.hidden = v.getAttribute('data-view') !== view; });
			document.querySelectorAll('.ly-seg[data-group] a').forEach(function (a) {
				if (new URL(a.href).searchParams.get('view') === view) a.setAttribute('aria-current', 'page');
				else a.removeAttribute('aria-current');
			});
			document.querySelectorAll('.ly-seg--lang a').forEach(function (a) {
				var u = new URL(a.href);
				u.searchParams.set('view', view);
				a.href = u.pathname + u.search;
			});
			label(view, ids);
			apply();
		});
		var done = function () { label(view, null); document.documentElement.classList.remove('ly-moving'); };
		if (t) t.finished.then(done, done); else done();
	}

	// Rōmaji / 日本語 and a song's text versions: fetch that page and swap <main> in place, so the
	// header and footer never repaint.
	var busy = false;
	function swapTo(href, push) {
		if (busy) return;
		busy = true;
		fetch(href, { credentials: 'same-origin' }).then(function (r) {
			if (!r.ok) throw new Error(r.status);
			return r.text();
		}).then(function (html) {
			var doc = new DOMParser().parseFromString(html, 'text/html');
			var next = doc.getElementById('dm-main');
			if (!next) throw new Error('no main');
			var q = document.getElementById('find');
			var keep = q ? q.value : '';
			var y = scrollY;
			if (push) history.pushState(null, '', href);
			transition(function () {
				document.getElementById('dm-main').replaceWith(document.importNode(next, true));
				document.title = doc.title;
				var nq = document.getElementById('find');
				if (nq && keep) { nq.value = keep; }
				apply();
				scrollTo(0, y);
			});
		}).catch(function () {
			location.href = href;
		}).then(function () { busy = false; });
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest('a');
		if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button) return;
		if (a.closest('.ly-seg[data-group]')) {
			e.preventDefault();
			group(new URL(a.href).searchParams.get('view') || 'series', true);
		} else if (a.closest('.ly-seg--lang') || (a.closest('.ly-tabs') && !a.classList.contains('ly-tabs__print'))) {
			e.preventDefault();
			if (!a.hasAttribute('aria-current')) swapTo(a.href, true);
		}
	});
	window.addEventListener('popstate', function () {
		var url = new URL(location.href);
		var main = document.getElementById('dm-main');
		if (main.getAttribute('data-state') !== (url.searchParams.get('lang') || '') + '|' + (url.searchParams.get('display') || '') + '|' + (url.searchParams.get('SongID') || '')) {
			swapTo(location.href, false);
		} else {
			group(url.searchParams.get('view') || 'series', false);
		}
	});

	if (location.hash === '#find' && document.getElementById('find')) document.getElementById('find').focus();
	apply();
})();
</script>
</body>
</html>
