<?php
/**
 * Era skins: the site's older themes, rebuilt over today's markup so they work on phones and
 * across all three sites (lyrics and gallery get the versions those eras never had).
 *
 * A reader opts in from the footer lineage ("Kubrick DM → dmbrickv2 → Blocks → Dune"); the choice
 * is the dm-skin cookie on .darkmirage.com, so it follows them between sites, and a bar at the top
 * of every skinned page leads back to Dune. Nothing links to a skin otherwise, and skinned pages
 * keep their canonical URLs, so search engines only ever see Dune.
 *
 * In a skin the shared header prints the era's banner and opens a two-column page; the shared
 * footer closes it with the era's sidebar and footer. Each site's own <main> sits in between and
 * is restyled by assets/skins/<skin>/skin.css.
 *
 * @package dmdune
 */

/**
 * name, years, ready (shown in the switcher only once built).
 */
function dmc_skins(): array {
	return array(
		'kubrick'   => array( 'Kubrick DM', '2006', false ),
		'dmbrickv2' => array( 'dmbrickv2', '2006–2007', false ),
		'blocks'    => array( 'DarkMirage Blocks', '2007–2016', true ),
	);
}

function dmc_skin(): string {
	static $skin = null;
	if ( null === $skin ) {
		$want = (string) ( $_COOKIE['dm-skin'] ?? '' );
		$all  = dmc_skins();
		$skin = isset( $all[ $want ] ) && $all[ $want ][2] ? $want : '';
	}
	return $skin;
}

/**
 * Stylesheet URL for the active skin, or ''.
 */
function dmc_skin_css( array $c ): string {
	$skin = dmc_skin();
	return $skin ? dmc_config( $c )['assets'] . 'skins/' . $skin . '/skin.css?v=' . DMC_VERSION : '';
}

/**
 * The footer lineage, with each finished era as a switch.
 */
function dmc_lineage_html(): string {
	$skin  = dmc_skin();
	$parts = array();
	foreach ( dmc_skins() as $key => $s ) {
		$label   = 'blocks' === $key ? 'Blocks' : $s[0];
		$parts[] = $s[2]
			? sprintf( '<button type="button" class="dm-era-switch" data-dm-skin="%s"%s title="See the site as %s (%s)">%s</button>', $key, $skin === $key ? ' aria-pressed="true"' : '', dmc_e( $s[0] ), dmc_e( $s[1] ), dmc_e( $label ) )
			: dmc_e( $label );
	}
	$parts[] = sprintf( '<button type="button" class="dm-era-switch dm-era-switch--now" data-dm-skin=""%s title="Today\'s theme">Dune</button>', $skin ? '' : ' aria-pressed="true"' );
	return implode( ' <span>→</span> ', $parts );
}

/**
 * Top of a skinned page: "you're in a time machine" bar, the banner, and the page wrapper.
 */
function dmc_skin_header_html( array $c ): string {
	$skin  = dmc_skin();
	$s     = dmc_skins()[ $skin ];
	$title = array(
		'blog'    => array( 'Ramblings of', 'DarkMirage', 'miku' ),
		'lyrics'  => array( 'Lyrics of', 'DarkMirage', 'haruhiyuki' ),
		'gallery' => array( 'Gallery of', 'DarkMirage', 'kallencc' ),
	)[ $c['site'] ];
	$home  = array( 'blog' => $c['www'], 'lyrics' => $c['lyrics'], 'gallery' => $c['gallery'] )[ $c['site'] ];
	return sprintf(
		'<a class="dm-skip" href="%1$s">Skip to content</a><div class="dm-skinbar" role="note"><span><span class="dm-skinbar__long">You’re seeing DarkMirage as it looked in </span><b>%2$s</b> (%3$s)</span> <button type="button" data-dm-skin="">Back to today’s site</button></div><div class="bk-shell"><header class="bk-banner bk-banner--%4$s"><a class="bk-title" href="%5$s" rel="home"><span class="bk-title__of">%6$s</span><span class="bk-title__name">%7$s</span></a>%8$s</header><div class="bk-page">',
		dmc_e( $c['skip'] ),
		dmc_e( $s[0] ),
		dmc_e( $s[1] ),
		dmc_e( $title[2] ),
		dmc_e( $home ),
		dmc_e( $title[0] ),
		dmc_e( $title[1] ),
		'blog' === $c['site'] ? '<a class="bk-feed" href="' . dmc_e( $c['www'] . 'feed/' ) . '" title="RSS feed"><span>RSS</span></a>' : ''
	);
}

/**
 * One sidebar block: a hatched label tab over a grey rounded panel.
 */
function dmc_bk_block( string $label, string $html, string $class = '' ): string {
	return sprintf( '<section class="bk-block%s"><h2 class="bk-label">%s</h2><div class="bk-block__body">%s</div></section>', $class ? ' ' . dmc_e( $class ) : '', dmc_e( $label ), $html );
}

/**
 * Close the page: the sidebar (search, navigation, then anything the page passed in
 * $c['side']: a list of [label, html]), the footer and the mobile tab bar.
 */
function dmc_skin_footer_html( array $c, string $tabbar ): string {
	$field   = array( 'blog' => 's', 'lyrics' => 'q', 'gallery' => 's' )[ $c['site'] ];
	$current = dmc_current( $c );
	$nav     = '';
	foreach ( dmc_nav( $c ) as $key => $item ) {
		if ( in_array( $key, array( 'home', 'tag', 'archives', 'gallery', 'lyrics', 'contact' ), true ) ) {
			$nav .= sprintf( '<li><a href="%s"%s>%s</a></li>', dmc_e( $item[1] ), $current === $key ? ' aria-current="page"' : '', dmc_e( $item[0] ) );
		}
	}
	$side  = dmc_bk_block( 'Search', sprintf( '<form class="bk-search" role="search" method="get" action="%s"><label class="screen-reader-text" for="bk-s">Search</label><input id="bk-s" type="search" name="%s" placeholder="%s"><button type="submit" aria-label="Search">%s</button></form>', dmc_e( $c['search'] ), dmc_e( $field ), dmc_e( $c['placeholder'] ), dmc_icon( 'search', 14 ) ), 'bk-block--search' );
	$side .= dmc_bk_block( 'Navigation', '<ul class="bk-nav">' . $nav . '</ul>', 'bk-block--nav' );
	foreach ( (array) ( $c['side'] ?? array() ) as $block ) {
		$side .= dmc_bk_block( $block[0], $block[1], $block[2] ?? '' );
	}
	$note = $c['note'] ?? 'Ramblings of DarkMirage · 2005–' . date( 'Y' );
	return sprintf(
		'<aside class="bk-side" aria-label="Sidebar">%1$s</aside></div><footer class="bk-footer"><p>%2$s</p><p class="dm-footer__lineage">%3$s <span>·</span> <a class="dm-footer__src" href="https://github.com/darkmirage/darkmirage.com">Source on GitHub</a></p></footer></div>%4$s',
		$side,
		$note,
		dmc_lineage_html(),
		$tabbar
	);
}
