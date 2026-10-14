<?php
/**
 * Era skins: the site's older themes, rebuilt over today's markup so they work on phones and
 * across all three sites (lyrics and gallery get the versions those eras never had).
 *
 * Readers pick an era from the time machine in the header (each labelled by name and years); the
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
 * key => name (the theme), short (its time machine label), era (the year it is named by), years,
 * first moment it was live.
 *
 * The first three are DarkMirage's Weblog, the b2 (cafelog) blog at darkmirage.com/blog/ before
 * WordPress, which changed its look twice (theme-archaeology/b2-era/ has the evidence): Nadesico
 * from July 2003 (the April–July 2003 frames layout is lost, so it shows that era too), Aegis from
 * 2004-04-17 and Lacus from 2005-05-10. WordPress's first post went up at 13:36 on 2005-11-03, so
 * that day is split by time, not date. default2 is the customized WordPress Default (Kubrick with
 * DarkMirage's own anime headers) the blog wore until Kubrick DM in October 2006.
 */
function dmc_skins(): array {
	return array(
		'nadesico'  => array( 'name' => 'Nadesico', 'short' => 'Nadesico', 'era' => '2003', 'years' => '2003–04', 'from' => '0000-00-00' ),
		'aegis'     => array( 'name' => 'Aegis', 'short' => 'Aegis', 'era' => '2004', 'years' => '2004–05', 'from' => '2004-04-17 00:00:00' ),
		'lacus'     => array( 'name' => 'Lacus', 'short' => 'Lacus', 'era' => '2005', 'years' => '2005', 'from' => '2005-05-10 00:00:00' ),
		'default2'  => array( 'name' => 'default2', 'short' => 'default2', 'era' => '2005', 'years' => '2005–06', 'from' => '2005-11-03 13:00:00' ),
		'kubrick'   => array( 'name' => 'Kubrick DM', 'short' => 'Kubrick DM', 'era' => '2006', 'years' => '2006', 'from' => '2006-09-18 21:00:00' ),
		'dmbrickv2' => array( 'name' => 'dmbrickv2', 'short' => 'dmbrickv2', 'era' => '2007', 'years' => '2007', 'from' => '2006-12-01' ),
		'blocks'    => array( 'name' => 'DarkMirage Blocks', 'short' => 'Blocks', 'era' => '2008', 'years' => '2008–16', 'from' => '2007-12-01' ),
	);
}

/**
 * Older names for a skin: until 1.12 the Aegis look was the only b2 era, keyed 'b2' (cookies and
 * shared ?era=b2 links still carry it).
 */
function dmc_skin_key( string $key ): string {
	return array( 'b2' => 'aegis' )[ $key ] ?? $key;
}

function dmc_skin(): string {
	static $skin = null;
	if ( null === $skin ) {
		$want = dmc_skin_key( (string) ( $_COOKIE['dm-skin'] ?? '' ) );
		$skin = isset( dmc_skins()[ $want ] ) ? $want : '';
	}
	return $skin;
}

/**
 * Whether $skin (default: the active one) is one of the b2 weblog's looks.
 */
function dmc_is_b2( ?string $skin = null ): bool {
	return in_array( $skin ?? dmc_skin(), array( 'nadesico', 'aegis', 'lacus' ), true );
}

/**
 * The tagline the b2 weblog showed in its title bar on $date ('Y-m-d…'; '' = the era's last).
 */
function dmc_b2_tagline( string $skin, string $date = '' ): string {
	$lines = array(
		'nadesico' => array( '0000' => 'because you have nothing better to do' ),
		'aegis'    => array(
			'0000'       => 'because you have nothing better to do',
			'2004-10-01' => 'where the impossible do not happen',
			'2004-12-14' => 'making the world a better place one rant at a time.',
			'2005-03-01' => 'I blog. Do you?',
		),
		'lacus'    => array(
			'0000'       => 'Anime, J-Pop and Whatnot.',
			'2005-10-01' => 'official fanboy of Maaya Sakamoto',
		),
	)[ $skin ] ?? array( '0000' => '' );
	$found = '';
	foreach ( $lines as $from => $line ) {
		if ( '' === $date || $date >= $from ) {
			$found = $line;
		}
	}
	return $found;
}

/**
 * The era a post written at $date ('Y-m-d H:i:s'; a bare date counts as its midnight) was first
 * shown in.
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
		$out .= sprintf( '<button type="button" data-dm-skin="%1$s" data-era="%2$s"%3$s aria-label="%4$s, %5$s: the site as it looked then"><span class="dm-era__name">%6$s</span><span class="dm-era__yrs">%5$s</span>%7$s</button>', $key, dmc_skin_era( $key ), $skin === $key ? ' aria-pressed="true"' : '', dmc_e( $s['name'] ), dmc_e( $s['years'] ), dmc_e( $s['short'] ), dmc_era_peek( $key, $s['name'], $s['years'] ) );
	}
	return $out . sprintf( '<button type="button" data-dm-skin="" data-era="now"%s aria-label="Dune: today’s site"><span class="dm-era__name">Dune</span><span class="dm-era__yrs">Now</span>%s</button>', $skin ? '' : ' aria-pressed="true"', dmc_era_peek( 'now', 'Dune', 'today' ) );
}

/**
 * The timeline colour each skin is drawn in (the chart groups Kubrick DM with dmbrickv2).
 */
function dmc_skin_era( string $key ): string {
	return array( 'nadesico' => 'b2', 'aegis' => 'b2', 'lacus' => 'b2', 'default2' => 'default', 'kubrick' => 'kubrick', 'dmbrickv2' => 'kubrick', 'blocks' => 'blocks' )[ $key ] ?? 'now';
}

/**
 * default2 showed one of its five headers, and one of its taglines, at random on every page load.
 * Here the pick follows the page address instead, so each page keeps its header on reload while
 * moving around the site still cycles through them.
 */
function dmc_default2_pick( array $list ) {
	// Plain parse_url: lyrics and gallery load this file without WordPress.
	$path = (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
	return $list[ crc32( $path ) % count( $list ) ];
}

/**
 * Taglines the 2005–06 blog showed under its title (from the Wayback Machine), each quoting the
 * show it linked to.
 */
function dmc_default2_taglines(): array {
	return array(
		'女の嘘には棘がある',
		'世界は全てお芝居だ',
		'The last love song on this little planet',
		'探し物は何ですか？見つけにくいものなんですか？',
		'宇宙と地上にひきさかれる恋人',
		'エッチなのはいけないと思います！',
	);
}

/**
 * A small screenshot of the era's home page, shown while a year button is hovered or focused.
 */
function dmc_era_peek( string $key, string $name, string $years ): string {
	return sprintf(
		'<span class="dm-era-peek" aria-hidden="true"><img src="%1$s" alt="" width="240" height="150" loading="lazy" decoding="async"><span class="dm-era-peek__cap"><b>%2$s</b> %3$s</span></span>',
		dmc_e( dmc_config()['assets'] . 'skins/previews/' . $key . '.webp?v=' . DMC_VERSION ),
		dmc_e( $name ),
		dmc_e( $years )
	);
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
 * A post's "see it as it looked then" switch, or '' if that era is already showing. $date as for
 * dmc_skin_for_date().
 */
function dmc_era_post_link( string $date ): string {
	$key = dmc_skin_for_date( $date );
	if ( ! $key || dmc_skin() === $key ) {
		return '';
	}
	$s = dmc_skins()[ $key ];
	return sprintf( '<button type="button" class="dm-era-post" data-dm-skin="%s" data-era="%s">%s<span>See this post as it looked in %s (%s)</span></button>', $key, dmc_skin_era( $key ), dmc_icon( 'clock', 14 ), dmc_e( $s['era'] ), dmc_e( $s['short'] ) );
}

/**
 * The footer lineage: the theme names, for the curious.
 */
function dmc_lineage_html(): string {
	$skin  = dmc_skin();
	$parts = array();
	foreach ( dmc_skins() as $key => $s ) {
		$parts[] = sprintf( '<button type="button" class="dm-era-switch" data-dm-skin="%s"%s title="%s (%s)">%s</button>', $key, $skin === $key ? ' aria-pressed="true"' : '', dmc_e( $s['name'] ), dmc_e( $s['years'] ), dmc_e( $s['short'] ) );
	}
	$parts[] = sprintf( '<button type="button" class="dm-era-switch dm-era-switch--now" data-dm-skin=""%s title="Today’s theme">Dune</button>', $skin ? '' : ' aria-pressed="true"' );
	return implode( ' <span>→</span> ', $parts );
}

/**
 * Top of a skinned page: the time machine, the banner (title, tagline, tabs) and the page wrapper.
 */
function dmc_skin_header_html( array $c ): string {
	$current = dmc_current( $c );
	$b2      = dmc_is_b2();
	$d2      = 'default2' === dmc_skin();
	$banner  = $d2 ? dmc_default2_pick( array( 'sage', 'nerine', 'nanaka', 'haruhi', 'bakuten' ) ) : '';
	if ( $d2 && 'blog' === $c['site'] && ! isset( $c['tagline'] ) ) {
		$c['tagline'] = dmc_default2_pick( dmc_default2_taglines() );
	}
	// Before WordPress the site was "DarkMirage's Weblog".
	$title   = array(
		'blog'    => $b2 ? array( 'DarkMirage’s', 'Weblog' ) : array( 'Ramblings of', 'DarkMirage' ),
		'lyrics'  => $b2 ? array( 'DarkMirage’s', 'Lyrics' ) : array( 'Lyrics of', 'DarkMirage' ),
		'gallery' => $b2 ? array( 'DarkMirage’s', 'Gallery' ) : array( 'Gallery of', 'DarkMirage' ),
	)[ $c['site'] ];
	$tagline = $c['tagline'] ?? array( 'blog' => $b2 ? dmc_b2_tagline( dmc_skin() ) : 'Anime, J-pop and whatever else', 'lyrics' => '歌詞の翻訳', 'gallery' => '写真' )[ $c['site'] ];
	// Lacus put a tagboard where the banner had been. The blog fills it; elsewhere it says so.
	$extra = 'lacus' === dmc_skin() ? dmc_b2_tagboard( $c['tagboard'] ?? '<p class="b2-tagboard__note">Tag-It! v0.6.1 · the shouts weren’t archived.</p>' ) : '';
	$home    = array( 'blog' => $c['www'], 'lyrics' => $c['lyrics'], 'gallery' => $c['gallery'] )[ $c['site'] ];
	$tabs    = '';
	foreach ( dmc_nav( $c ) as $key => $item ) {
		if ( 'capsule' === $key ) {
			continue;
		}
		$tabs .= sprintf( '<li><a href="%s"%s>%s</a></li>', dmc_e( $item[1] ), $current === $key || ( '' === $current && 'home' === $key ) ? ' aria-current="page"' : '', dmc_e( $item[0] ) );
	}
	return sprintf(
		'<a class="dm-skip" href="%1$s">Skip to content</a><nav class="dm-skinbar" aria-label="Time machine"><span class="dm-skinbar__lead">%2$s<span>Time machine</span></span><span class="dm-skinbar__years">%3$s</span><button type="button" class="dm-skinbar__share">Copy link</button></nav><div class="bk-shell"><header class="bk-banner bk-banner--%4$s"%11$s><div class="bk-banner__text"><a class="bk-title" href="%5$s" rel="home"><span class="bk-title__of">%6$s</span> <span class="bk-title__name">%7$s</span></a><p class="bk-tagline">%8$s</p></div><nav class="bk-tabs" aria-label="Main"><ul>%9$s</ul></nav>%10$s%12$s</header><div class="bk-page">',
		dmc_e( $c['skip'] ),
		dmc_icon( 'clock', 14 ),
		dmc_era_buttons(),
		dmc_e( $c['site'] ),
		dmc_e( $home ),
		dmc_e( $title[0] ),
		dmc_e( $title[1] ),
		dmc_e( $tagline ),
		$tabs,
		'blog' === $c['site'] ? '<a class="bk-feed" href="' . dmc_e( $c['www'] . 'feed/' ) . '" title="RSS feed"><span>RSS</span></a>' : '',
		$banner ? ' data-banner="' . dmc_e( $banner ) . '"' : '',
		$extra
	);
}

/**
 * Lacus's tagboard box (a Tag-It! iframe then; $html is already escaped).
 */
function dmc_b2_tagboard( string $html ): string {
	return '<section class="b2-tagboard" aria-label="Tagboard"><h2 class="b2-tagboard__label">tagboard</h2><div class="b2-tagboard__body">' . $html . '</div></section>';
}

/**
 * Lacus's 120 × 600 "advertisements" slot. In 2005 it held AdSense text ads; this is a joke
 * recreation of the look, not ads: nothing links anywhere and the domain is reserved.
 */
function dmc_b2_fake_ads(): string {
	$ads  = array(
		array( 'Learn Japanese in 3 Days', 'Kanji are just pictures, right? JLPT level 1 by Friday.', 'kanji' ),
		array( 'Download More RAM', 'Run Half-Life 2 on your Pentium 4. Totally legit.', 'ram' ),
		array( 'Maaya Sakamoto CDs, Cheap!', 'You already own all of them. Buy them again anyway.', 'maaya' ),
		array( 'Free iPod Shuffle*', '*After 48 easy offers and your firstborn.', 'ipod' ),
		array( 'NAPFA Gold Guaranteed', 'Chin-ups not included. Neither is the 2.4 km.', 'napfa' ),
	);
	$list = '';
	foreach ( $ads as $ad ) {
		$list .= sprintf( '<li><span class="b2-ads__title">%s</span> <span class="b2-ads__text">%s</span> <span class="b2-ads__url">www.not-a-real-ad.example/%s</span></li>', dmc_e( $ad[0] ), dmc_e( $ad[1] ), dmc_e( $ad[2] ) );
	}
	return '<div class="b2-ads" role="note" aria-label="A joke recreation of the 2005 text ads that sat here. These are not real ads."><ul aria-hidden="true">' . $list . '</ul><p class="b2-ads__by" aria-hidden="true">Ads by Nobody™</p></div><p class="b2-ads__note">2005 had real ads here. These aren’t.</p>';
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
	// b2 kept everything in one left "menu" column, navigation first.
	if ( dmc_is_b2( $skin ) ) {
		$cols['right'] .= dmc_bk_block( 'menu', '<ul class="bk-nav">' . $nav . '</ul>', 'bk-block--nav' );
	}
	$cols['right'] .= dmc_bk_block( 'Search', sprintf( '<form class="bk-search" role="search" method="get" action="%s"><label class="screen-reader-text" for="bk-s">Search</label><input id="bk-s" type="search" name="%s" placeholder="%s"><button type="submit" aria-label="Search">%s</button></form>', dmc_e( $c['search'] ), dmc_e( $field ), dmc_e( $c['placeholder'] ), dmc_icon( 'search', 14 ) ), 'bk-block--search' );
	// Blocks kept its navigation in the sidebar; the 2007 theme listed it on the left.
	if ( ! dmc_is_b2( $skin ) ) {
		// default2 had no tabs: its "Menu" list in the sidebar was the navigation.
		$cols[ 'dmbrickv2' === $skin ? 'left' : 'right' ] .= dmc_bk_block( 'default2' === $skin ? 'Menu' : 'Navigation', '<ul class="bk-nav">' . $nav . '</ul>', 'bk-block--nav' );
	}
	foreach ( (array) ( $c['side'] ?? array() ) as $block ) {
		$cols[ 'left' === ( $block[3] ?? '' ) ? 'left' : 'right' ] .= dmc_bk_block( $block[0], $block[1], $block[2] ?? '' );
	}
	if ( 'lacus' === $skin ) {
		$cols['right'] .= dmc_bk_block( 'advertisements', dmc_b2_fake_ads(), 'bk-block--ads' );
	}
	$note = $c['note'] ?? array(
		'nadesico' => 'DarkMirage’s Weblog · 2003–2004 · powered by b2',
		'aegis'    => 'DarkMirage’s Weblog · 2004–2005 · powered by b2',
		'lacus'    => 'DarkMirage’s Weblog · 2005 · powered by b2',
		'default2' => 'Ramblings of DarkMirage is proudly powered by WordPress · 2005–2006',
	)[ $skin ] ?? 'Ramblings of DarkMirage · 2003–' . date( 'Y' );
	return sprintf(
		'%1$s<aside class="bk-side bk-side--right" aria-label="Sidebar">%2$s</aside></div><footer class="bk-footer"><p>%3$s</p><p class="dm-footer__lineage">%4$s <span>·</span> <a class="dm-footer__src" href="https://github.com/darkmirage/darkmirage.com">Source on GitHub</a></p></footer></div>%5$s',
		$cols['left'] ? '<aside class="bk-side bk-side--left" aria-label="More">' . $cols['left'] . '</aside>' : '',
		$cols['right'],
		$note,
		dmc_lineage_html(),
		$tabbar
	);
}
