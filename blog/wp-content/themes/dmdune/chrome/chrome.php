<?php
/**
 * DarkMirage chrome: the header, footer and mobile tab bar shared by www (this WordPress theme),
 * lyrics.darkmirage.com and gallery.darkmirage.com. Plain PHP with no WordPress dependency, so the
 * subsites can include it straight from the theme folder.
 *
 * Usage on a subsite:
 *   require '/path/to/themes/dmdune/chrome/chrome.php';
 *   dmc_head( array( 'site' => 'lyrics' ) );         // inside <head>
 *   dmc_header( array( 'site' => 'lyrics' ) );       // first thing in <body>
 *   dmc_footer( array( 'site' => 'lyrics' ) );       // last thing in <body>
 *
 * @package dmdune
 */

if ( defined( 'DMC_LOADED' ) ) {
	return;
}
define( 'DMC_LOADED', true );
define( 'DMC_VERSION', '1.7.2' );

function dmc_e( $s ): string {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/**
 * Site addresses and options, with defaults for production. Any key can be overridden per call
 * (the blog passes its own home URL; local copies point at localhost).
 */
function dmc_config( array $c = array() ): array {
	$c += array(
		'site'        => 'blog',
		'current'     => '',
		'www'         => 'https://www.darkmirage.com/',
		'lyrics'      => 'https://lyrics.darkmirage.com/',
		'gallery'     => 'https://gallery.darkmirage.com/',
		'search'      => null,
		'placeholder' => 'Search',
		'skip'        => '#dm-main',
	);
	$c['www']    = rtrim( $c['www'], '/' ) . '/';
	$c['assets'] = $c['assets'] ?? $c['www'] . 'blog/wp-content/themes/dmdune/assets/';
	if ( null === $c['search'] ) {
		$c['search'] = array(
			'blog'    => $c['www'],
			'lyrics'  => $c['lyrics'],
			'gallery' => $c['gallery'] . 'page/search/',
		)[ $c['site'] ];
	}
	return $c;
}

function dmc_nav( array $c ): array {
	$w = $c['www'];
	return array(
		'home'     => array( 'Home', $w, 'home' ),
		'archives' => array( 'Archive', $w . 'archives/', 'archive' ),
		'tag'      => array( 'Tags', $w . 'tag/', 'tag' ),
		'japanese' => array( '日本語', $w . 'beginners-japanese-lessons-by-darkmirage/', 'kana' ),
		'lyrics'   => array( 'Lyrics', $c['lyrics'], 'music' ),
		'gallery'  => array( 'Gallery', $c['gallery'], 'camera' ),
		'capsule'  => array( 'Time Capsule', $w . 'haruhiism-time-capsule-project/', 'capsule' ),
		'contact'  => array( 'Contact', $w . 'contact/', 'mail' ),
	);
}

function dmc_current( array $c ): string {
	return $c['current'] ?: ( 'blog' === $c['site'] ? '' : $c['site'] );
}

function dmc_icon( string $name, int $size = 20, string $class = '' ): string {
	$paths = array(
		'home'    => '<path d="M4 11l8-7 8 7v9H4z"/>',
		'archive' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M10 14h4"/>',
		'tag'     => '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
		'kana'    => '<path d="M5 7h10M10 4c0 8-2 12-6 15M9 11c3 0 7 2 9 7"/>',
		'music'   => '<path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="16" r="2"/>',
		'camera'  => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.5"/><path d="M8 7l2-3h4l2 3"/>',
		'capsule' => '<rect x="5" y="3" width="14" height="18" rx="7"/><path d="M5 12h14"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'search'  => '<circle cx="11" cy="11" r="6"/><path d="M16 16l4 4"/>',
		'more'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'moon'    => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
		'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'play'    => '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
	);
	return sprintf(
		'<svg class="dm-icon%3$s" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>',
		$size,
		$paths[ $name ] ?? '',
		$class ? ' ' . $class : ''
	);
}

/**
 * <head> pieces for the subsites (the blog gets the same through WordPress): colour scheme, the
 * saved light/dark choice applied before first paint, icons, and the shared stylesheet.
 */
function dmc_head( array $c = array() ): void {
	$c = dmc_config( $c );
	$a = $c['assets'];
	echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
	dmc_head_early();
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", dmc_e( $a . 'brand/icons/favicon.svg' ) );
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", dmc_e( $a . 'brand/icons/site-icon-32.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", dmc_e( $a . 'brand/icons/site-icon-180.png' ) );
	printf( '<link rel="stylesheet" href="%s">' . "\n", dmc_e( $a . 'css/chrome.css?v=' . DMC_VERSION ) );
	printf( '<script src="%s" defer></script>' . "\n", dmc_e( $a . 'js/chrome.js?v=' . DMC_VERSION ) );
}

/**
 * The light/dark choice is a cookie on .darkmirage.com, so it follows the reader across all three
 * sites; without one, the OS setting applies.
 */
function dmc_head_early(): void {
	echo '<meta name="color-scheme" content="light dark">' . "\n";
	echo '<meta name="theme-color" content="#f2efe9" media="(prefers-color-scheme: light)">' . "\n";
	echo '<meta name="theme-color" content="#0d1017" media="(prefers-color-scheme: dark)">' . "\n";
	// The shared cookie decides. A choice saved by dmdune 1.6 and earlier lives only in this site's
	// localStorage, so it is copied into the cookie once; otherwise it would override the other sites.
	echo "<script>(function(){try{var m=(document.cookie.match(/(?:^|; )dm-theme=(light|dark|system)/)||[])[1];if(!m){m=localStorage.getItem('dm-theme');if(m==='light'||m==='dark'){document.cookie='dm-theme='+m+'; path=/; SameSite=Lax; max-age=31536000'+(/(^|\\.)darkmirage\\.com$/.test(location.hostname)?'; domain=.darkmirage.com':'');}}if(m==='light'||m==='dark'){document.documentElement.setAttribute('data-theme',m);}}catch(e){}})();</script>\n";
}

function dmc_mode_button( string $class ): string {
	return sprintf(
		'<button type="button" class="dm-mode %1$s" data-dm-toggle aria-label="Switch between light and dark mode">%2$s%3$s<span class="dm-mode__label"><span class="is-day">Dark</span><span class="is-night">Light</span></span></button>',
		dmc_e( $class ),
		dmc_icon( 'moon', 16, 'is-day' ),
		dmc_icon( 'sun', 16, 'is-night' )
	);
}

function dmc_header( array $c = array() ): void {
	echo dmc_header_html( $c );
}

function dmc_header_html( array $c = array() ): string {
	$c       = dmc_config( $c );
	$current = dmc_current( $c );
	$links   = '';
	foreach ( dmc_nav( $c ) as $key => $item ) {
		$links .= sprintf( '<li><a href="%s"%s>%s</a></li>', dmc_e( $item[1] ), $current === $key ? ' aria-current="page"' : '', dmc_e( $item[0] ) );
	}
	$icons = $c['assets'] . 'brand/icons/';
	return sprintf(
		'<a class="dm-skip" href="%1$s">Skip to content</a><div class="dm-topbar"><nav class="dm-nav" aria-label="Main"><a class="dm-brand" href="%2$s" rel="home"><img class="is-day" src="%3$sroundel-day.webp" alt="" width="34" height="34"><img class="is-night" src="%3$sroundel-night.webp" alt="" width="34" height="34" loading="lazy"><span>Dark<b>Mirage</b></span></a><ul class="dm-nav__links">%5$s</ul><details class="dm-nav__more" hidden><summary>More</summary><ul></ul></details><div class="dm-nav__tools"><a class="dm-iconbtn" href="%6$s" aria-label="Search">%7$s</a>%8$s</div></nav></div>',
		dmc_e( $c['skip'] ),
		dmc_e( $c['www'] ),
		dmc_e( $icons ),
		'',
		$links,
		dmc_e( $c['search'] . array( 'blog' => '?s=', 'lyrics' => '#find', 'gallery' => '' )[ $c['site'] ] ),
		dmc_icon( 'search', 18 ),
		dmc_mode_button( 'dm-mode--top' )
	);
}

function dmc_footer( array $c = array() ): void {
	echo dmc_footer_html( $c );
}

function dmc_footer_html( array $c = array() ): string {
	$c       = dmc_config( $c );
	$current = dmc_current( $c );
	$nav     = dmc_nav( $c );
	$tab     = static function ( string $key ) use ( $nav, $current ) {
		return sprintf( '<a href="%s"%s>%s<span>%s</span></a>', dmc_e( $nav[ $key ][1] ), $current === $key ? ' aria-current="page"' : '', dmc_icon( $nav[ $key ][2], 22 ), dmc_e( $nav[ $key ][0] ) );
	};
	$tabs = array( 'home', 'archives', 'lyrics', 'gallery' );
	$more = '';
	foreach ( $nav as $key => $item ) {
		if ( in_array( $key, $tabs, true ) ) {
			continue;
		}
		$more .= sprintf( '<li><a href="%s"%s>%s<span>%s</span></a></li>', dmc_e( $item[1] ), $current === $key ? ' aria-current="page"' : '', dmc_icon( $item[2], 20 ), dmc_e( $item[0] ) );
	}
	$field  = array( 'blog' => 's', 'lyrics' => 'q', 'gallery' => 's' )[ $c['site'] ];
	$hidden = '';
	$note  = $c['note'] ?? 'Ramblings of DarkMirage · 2005–' . date( 'Y' ) . ' · Kiro and the art: all rights reserved · Theme code: GPL-2.0';
	return sprintf(
		'<div class="dm-footer"><p>%1$s</p><p class="dm-footer__lineage" aria-label="This theme follows Kubrick DM, dmbrickv2 and DarkMirage Blocks">Kubrick DM <span>→</span> dmbrickv2 <span>→</span> Blocks <span>→</span> <b>Dune</b></p></div><nav class="dm-tabbar" aria-label="Main (mobile)">%2$s<details class="dm-sheet"><summary>%3$s<span>More</span></summary><div class="dm-sheet__panel"><form class="dm-search" role="search" method="get" action="%4$s"><label class="screen-reader-text" for="dm-sheet-search">Search</label>%10$s<input id="dm-sheet-search" type="search" name="%5$s" placeholder="%6$s"><button type="submit" aria-label="Search">%7$s</button></form><ul>%8$s</ul>%9$s</div></details></nav>',
		$note,
		implode( '', array_map( $tab, $tabs ) ),
		dmc_icon( 'more', 22 ),
		dmc_e( $c['search'] ),
		dmc_e( $field ),
		dmc_e( $c['placeholder'] ),
		dmc_icon( 'search', 18 ),
		$more,
		dmc_mode_button( 'dm-mode--sheet' ),
		$hidden
	);
}

/**
 * Section head in the HUD voice: "// CODE", a title, an optional note.
 */
function dmc_hud( string $code, string $title, string $note = '' ): string {
	return sprintf(
		'<div class="dm-hud-head"><p class="dm-hud-code">// %s</p><h2 class="dm-hud-title">%s</h2>%s</div>',
		dmc_e( $code ),
		dmc_e( $title ),
		$note ? '<p class="dm-hud-note">' . dmc_e( $note ) . '</p>' : ''
	);
}
