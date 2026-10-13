<?php
/**
 * Era skins: the site's older themes, rebuilt over today's markup so they work on phones and
 * across all three sites (lyrics and gallery get the versions those eras never had).
 *
 * Readers pick an era from the time machine in the header (labelled by year, not theme name); the
 * choice is the dm-skin cookie on .darkmirage.com, so it follows them between sites until they
 * pick "Now". Blog posts also offer "see it as it looked then", which jumps to the era the post
 * was written in. Skinned pages keep their canonical URLs, so search engines only see Dune.
 *
 * In a skin the shared header prints the era's banner (title, tagline, tabs) and opens the page;
 * the shared footer closes it with the sidebars and footer. Each site's own <main> sits in
 * between and is restyled by assets/skins/<skin>/skin.css, which also decides what shows where
 * (Blocks keeps navigation in the sidebar; the 2006 and 2007 themes use the tabs).
 *
 * @package dmdune
 */

/**
 * key => name (the theme), era (what readers see), years, first day it was live.
 */
function dmc_skins(): array {
	return array(
		'kubrick'   => array( 'name' => 'Kubrick DM', 'era' => '2006', 'years' => '2005–2006', 'from' => '0000-00-00' ),
		'dmbrickv2' => array( 'name' => 'dmbrickv2', 'era' => '2007', 'years' => '2007', 'from' => '2006-12-01' ),
		'blocks'    => array( 'name' => 'DarkMirage Blocks', 'era' => '2008', 'years' => '2008–2016', 'from' => '2007-12-01' ),
	);
}

function dmc_skin(): string {
	static $skin = null;
	if ( null === $skin ) {
		$want = (string) ( $_COOKIE['dm-skin'] ?? '' );
		$skin = isset( dmc_skins()[ $want ] ) ? $want : '';
	}
	return $skin;
}

/**
 * The era a post written on $date (Y-m-d) was first shown in.
 */
function dmc_skin_for_date( string $date ): string {
	$found = '';
	foreach ( dmc_skins() as $key => $s ) {
		if ( $date >= $s['from'] ) {
			$found = $key;
		}
	}
	return $found;
}

/**
 * Stylesheet URL for the active skin, or ''.
 */
function dmc_skin_css( array $c ): string {
	$skin = dmc_skin();
	return $skin ? dmc_config( $c )['assets'] . 'skins/' . $skin . '/skin.css?v=' . DMC_VERSION : '';
}

/**
 * The time machine: one button per era plus "Now". Used at the top of skinned pages and in the
 * Dune header's menu.
 */
function dmc_era_buttons(): string {
	$skin = dmc_skin();
	$out  = '';
	foreach ( dmc_skins() as $key => $s ) {
		$out .= sprintf( '<button type="button" data-dm-skin="%s"%s title="The site as it looked in %s">%s</button>', $key, $skin === $key ? ' aria-pressed="true"' : '', dmc_e( $s['years'] ), dmc_e( $s['era'] ) );
	}
	return $out . sprintf( '<button type="button" data-dm-skin=""%s title="Today’s site">Now</button>', $skin ? '' : ' aria-pressed="true"' );
}

/**
 * Header control for Dune: a clock that opens the era list.
 */
function dmc_era_menu_html(): string {
	return sprintf(
		'<details class="dm-era"><summary aria-label="Time machine: see the site as it looked in earlier years">%s<span class="dm-era__label">Time machine</span></summary><div class="dm-era__panel"><p>See DarkMirage as it looked back then</p><div class="dm-era__years">%s</div></div></details>',
		dmc_icon( 'clock', 18 ),
		dmc_era_buttons()
	);
}

/**
 * A post's "see it as it looked then" switch, or '' if that era is already showing.
 */
function dmc_era_post_link( string $date ): string {
	$key = dmc_skin_for_date( $date );
	if ( ! $key || dmc_skin() === $key ) {
		return '';
	}
	return sprintf( '<button type="button" class="dm-era-post" data-dm-skin="%s">%s<span>See this post as it looked in %s</span></button>', $key, dmc_icon( 'clock', 14 ), dmc_e( dmc_skins()[ $key ]['era'] ) );
}

/**
 * The footer lineage: the theme names, for the curious.
 */
function dmc_lineage_html(): string {
	$skin  = dmc_skin();
	$parts = array();
	foreach ( dmc_skins() as $key => $s ) {
		$parts[] = sprintf( '<button type="button" class="dm-era-switch" data-dm-skin="%s"%s title="%s (%s)">%s</button>', $key, $skin === $key ? ' aria-pressed="true"' : '', dmc_e( $s['name'] ), dmc_e( $s['years'] ), dmc_e( 'blocks' === $key ? 'Blocks' : $s['name'] ) );
	}
	$parts[] = sprintf( '<button type="button" class="dm-era-switch dm-era-switch--now" data-dm-skin=""%s title="Today’s theme">Dune</button>', $skin ? '' : ' aria-pressed="true"' );
	return implode( ' <span>→</span> ', $parts );
}

/**
 * Top of a skinned page: the time machine, the banner (title, tagline, tabs) and the page wrapper.
 */
function dmc_skin_header_html( array $c ): string {
	$current = dmc_current( $c );
	$title   = array(
		'blog'    => array( 'Ramblings of', 'DarkMirage' ),
		'lyrics'  => array( 'Lyrics of', 'DarkMirage' ),
		'gallery' => array( 'Gallery of', 'DarkMirage' ),
	)[ $c['site'] ];
	$tagline = $c['tagline'] ?? array( 'blog' => 'Anime, J-pop and whatever else', 'lyrics' => '歌詞の翻訳', 'gallery' => '写真' )[ $c['site'] ];
	$home    = array( 'blog' => $c['www'], 'lyrics' => $c['lyrics'], 'gallery' => $c['gallery'] )[ $c['site'] ];
	$tabs    = '';
	foreach ( dmc_nav( $c ) as $key => $item ) {
		if ( 'capsule' === $key ) {
			continue;
		}
		$tabs .= sprintf( '<li><a href="%s"%s>%s</a></li>', dmc_e( $item[1] ), $current === $key || ( '' === $current && 'home' === $key ) ? ' aria-current="page"' : '', dmc_e( $item[0] ) );
	}
	return sprintf(
		'<a class="dm-skip" href="%1$s">Skip to content</a><nav class="dm-skinbar" aria-label="Time machine"><span class="dm-skinbar__lead">%2$s<span>Time machine</span></span><span class="dm-skinbar__years">%3$s</span></nav><div class="bk-shell"><header class="bk-banner bk-banner--%4$s"><div class="bk-banner__text"><a class="bk-title" href="%5$s" rel="home"><span class="bk-title__of">%6$s</span> <span class="bk-title__name">%7$s</span></a><p class="bk-tagline">%8$s</p></div><nav class="bk-tabs" aria-label="Main"><ul>%9$s</ul></nav>%10$s</header><div class="bk-page">',
		dmc_e( $c['skip'] ),
		dmc_icon( 'clock', 14 ),
		dmc_era_buttons(),
		dmc_e( $c['site'] ),
		dmc_e( $home ),
		dmc_e( $title[0] ),
		dmc_e( $title[1] ),
		dmc_e( $tagline ),
		$tabs,
		'blog' === $c['site'] ? '<a class="bk-feed" href="' . dmc_e( $c['www'] . 'feed/' ) . '" title="RSS feed"><span>RSS</span></a>' : ''
	);
}

/**
 * One sidebar block: label over a panel. Each skin styles it its own way.
 */
function dmc_bk_block( string $label, string $html, string $class = '' ): string {
	return sprintf( '<section class="bk-block%s"><h2 class="bk-label">%s</h2><div class="bk-block__body">%s</div></section>', $class ? ' ' . dmc_e( $class ) : '', dmc_e( $label ), $html );
}

/**
 * Close the page: the sidebars, the footer and the mobile tab bar. $c['side'] adds blocks:
 * [label, html, class, column] with column 'left' or 'right' (default).
 */
function dmc_skin_footer_html( array $c, string $tabbar ): string {
	$skin    = dmc_skin();
	$field   = array( 'blog' => 's', 'lyrics' => 'q', 'gallery' => 's' )[ $c['site'] ];
	$current = dmc_current( $c );
	$nav     = '';
	foreach ( dmc_nav( $c ) as $key => $item ) {
		if ( in_array( $key, array( 'home', 'tag', 'archives', 'gallery', 'lyrics', 'contact' ), true ) ) {
			$nav .= sprintf( '<li><a href="%s"%s>%s</a></li>', dmc_e( $item[1] ), $current === $key || ( '' === $current && 'home' === $key ) ? ' aria-current="page"' : '', dmc_e( $item[0] ) );
		}
	}
	$cols           = array( 'left' => '', 'right' => '' );
	$cols['right'] .= dmc_bk_block( 'Search', sprintf( '<form class="bk-search" role="search" method="get" action="%s"><label class="screen-reader-text" for="bk-s">Search</label><input id="bk-s" type="search" name="%s" placeholder="%s"><button type="submit" aria-label="Search">%s</button></form>', dmc_e( $c['search'] ), dmc_e( $field ), dmc_e( $c['placeholder'] ), dmc_icon( 'search', 14 ) ), 'bk-block--search' );
	// Blocks kept its navigation in the sidebar; the 2007 theme listed it on the left.
	$cols[ 'dmbrickv2' === $skin ? 'left' : 'right' ] .= dmc_bk_block( 'Navigation', '<ul class="bk-nav">' . $nav . '</ul>', 'bk-block--nav' );
	foreach ( (array) ( $c['side'] ?? array() ) as $block ) {
		$cols[ 'left' === ( $block[3] ?? '' ) ? 'left' : 'right' ] .= dmc_bk_block( $block[0], $block[1], $block[2] ?? '' );
	}
	$note = $c['note'] ?? 'Ramblings of DarkMirage · 2005–' . date( 'Y' );
	return sprintf(
		'%1$s<aside class="bk-side bk-side--right" aria-label="Sidebar">%2$s</aside></div><footer class="bk-footer"><p>%3$s</p><p class="dm-footer__lineage">%4$s <span>·</span> <a class="dm-footer__src" href="https://github.com/darkmirage/darkmirage.com">Source on GitHub</a></p></footer></div>%5$s',
		$cols['left'] ? '<aside class="bk-side bk-side--left" aria-label="More">' . $cols['left'] . '</aside>' : '',
		$cols['right'],
		$note,
		dmc_lineage_html(),
		$tabbar
	);
}
