<?php
/**
 * Server-rendered blocks used by the templates. Each one is a dynamic block: the markup is built
 * here on every request, and the editor previews it through ServerSideRender (assets/js/editor.js).
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		wp_register_script(
			'dmdune-editor',
			get_template_directory_uri() . '/assets/js/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor' ),
			DMDUNE_VERSION,
			true
		);
		$blocks = array(
			'site-header'    => array( 'title' => __( 'Site header', 'dmdune' ) ),
			'site-footer'    => array( 'title' => __( 'Site footer', 'dmdune' ) ),
			'hero'           => array( 'title' => __( 'Desert banner', 'dmdune' ) ),
			'card'           => array( 'title' => __( 'Post card', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'tx'             => array( 'title' => __( 'Transmission log entry', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'post-meta'      => array( 'title' => __( 'Post categories', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'byline'         => array( 'title' => __( 'Post byline', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'archive-head'   => array( 'title' => __( 'Archive header', 'dmdune' ) ),
			'row'            => array( 'title' => __( 'Log row', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'channels'       => array( 'title' => __( 'Channels and frequencies', 'dmdune' ) ),
			'related'        => array( 'title' => __( 'Related posts', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'year-strip'     => array( 'title' => __( 'Archive by year', 'dmdune' ), 'attributes' => array( 'always' => array( 'type' => 'boolean', 'default' => false ) ) ),
			'archive-index'  => array( 'title' => __( 'Archive index', 'dmdune' ) ),
			'most-discussed' => array( 'title' => __( 'Most discussed', 'dmdune' ), 'attributes' => array( 'limit' => array( 'type' => 'integer', 'default' => 6 ) ) ),
			'featured'       => array( 'title' => __( 'Featured posts', 'dmdune' ) ),
			'not-found'      => array( 'title' => __( 'Not found', 'dmdune' ) ),
		);
		foreach ( $blocks as $slug => $b ) {
			$fn = 'dmdune_render_' . str_replace( '-', '_', $slug );
			register_block_type(
				"dmdune/$slug",
				array(
					'api_version'     => 3,
					'title'           => $b['title'],
					'category'        => 'theme',
					'editor_script'   => 'dmdune-editor',
					'attributes'      => $b['attributes'] ?? array(),
					'uses_context'    => $b['uses_context'] ?? array(),
					'supports'        => array( 'html' => false, 'inserter' => true ),
					'render_callback' => static fn( $attrs, $content, $block ) => $fn( $attrs, $block ),
				)
			);
		}
	}
);

function dmdune_primary_category( WP_Post $post ): ?WP_Term {
	$cats = get_the_category( $post->ID );
	return $cats ? $cats[0] : null;
}

/**
 * Category label with its colour dot (colours match the category plates).
 */
function dmdune_cat_label( WP_Post $post, bool $link = false ): string {
	$cat = dmdune_primary_category( $post );
	if ( ! $cat ) {
		return '';
	}
	$name = esc_html( $cat->name );
	$tag  = $link ? sprintf( '<a href="%s">%s</a>', esc_url( get_category_link( $cat ) ), $name ) : $name;
	return sprintf( '<span class="dm-cat" data-cat="%s">%s</span>', esc_attr( $cat->slug ), $tag );
}

function dmdune_time( WP_Post $post, string $format = 'j M Y' ): string {
	return sprintf( '<time datetime="%s">%s</time>', esc_attr( get_the_date( 'c', $post ) ), esc_html( get_the_date( $format, $post ) ) );
}

/**
 * Comment count as icon + number; the full phrase stays available to screen readers.
 */
function dmdune_comment_count( WP_Post $post ): string {
	$n = (int) $post->comment_count;
	if ( $n < 1 ) {
		return '';
	}
	/* translators: %s: number of comments */
	$label = sprintf( _n( '%s comment', '%s comments', $n, 'dmdune' ), number_format_i18n( $n ) );
	return sprintf(
		'<span class="dm-count" title="%1$s"><svg class="dm-icon" aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.1-4.6A8 8 0 1 1 21 12z"/></svg><span aria-hidden="true">%2$s</span><span class="screen-reader-text">%1$s</span></span>',
		esc_attr( $label ),
		esc_html( number_format_i18n( $n ) )
	);
}

function dmdune_reading_minutes( WP_Post $post ): int {
	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = str_word_count( $text );
	// Japanese has no spaces: count its characters at ~500 a minute alongside English words at ~230.
	$cjk = preg_match_all( '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u', $text );
	return max( 1, (int) round( $words / 230 + $cjk / 500 ) );
}

/**
 * Thumbnail for a post: its first image, or a tinted plate with the category name.
 */
function dmdune_thumb( WP_Post $post, string $class = 'dm-thumb' ): string {
	static $shown = 0;
	$src = dmdune_first_image( $post );
	if ( $src ) {
		// The first few pictures on a page load as usual. Later ones wait for dmdune.js to fetch them
		// as they near the screen: the browser's own lazy loading starts 1,250–2,500px early, which
		// on the home page meant nearly every post's picture loaded up front.
		// Editor previews (REST) and feeds have no dmdune.js, so they always get plain pictures.
		$hold = ++$shown > 3 && ! is_admin() && ! is_feed() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		$attr = $hold ? 'data-dm-src="%2$s"' : 'src="%2$s" loading="lazy"';
		$late = $hold ? '<noscript><img class="dm-media__img" src="%2$s" alt="" loading="lazy"></noscript>' : '';
		// The blurred copy only shows when the picture is letterboxed (see .is-fit and dmdune.js).
		return sprintf(
			'<div class="%1$s dm-media%3$s"><img class="dm-media__bg" ' . $attr . ' alt="" aria-hidden="true" decoding="async"><img class="dm-media__img" ' . $attr . ' alt="" decoding="async">' . $late . '</div>',
			esc_attr( $class ),
			esc_url( $src ),
			false !== strpos( $src, '/hqdefault.jpg' ) ? ' dm-media--yt' : ''
		);
	}
	$cats = get_the_category( $post->ID );
	$slug = $cats ? $cats[0]->slug : 'blog';
	$name = $cats ? $cats[0]->name : '';
	return sprintf( '<div class="%1$s %1$s--plate" data-cat="%2$s"><span>%3$s</span></div>', esc_attr( $class ), esc_attr( $slug ), esc_html( $name ) );
}

function dmdune_card_html( WP_Post $post, string $heading = 'h2' ): string {
	$title = get_the_title( $post );
	$cat   = dmdune_primary_category( $post );
	return sprintf(
		'<article class="dm-card"%7$s><a class="dm-card__link" href="%1$s">%2$s<div class="dm-card__body"><p class="dm-card__cat">%3$s</p><%5$s class="dm-card__title">%4$s</%5$s><p class="dm-card__excerpt">%6$s</p><p class="dm-meta dm-card__meta">%8$s%9$s</p></div></a></article>',
		esc_url( get_permalink( $post ) ),
		dmdune_thumb( $post, 'dm-card__media' ),
		dmdune_cat_label( $post ),
		'' !== $title ? esc_html( wp_strip_all_tags( $title ) ) : esc_html__( '(untitled)', 'dmdune' ),
		tag_escape( $heading ),
		esc_html( get_the_excerpt( $post ) ),
		$cat ? ' data-cat="' . esc_attr( $cat->slug ) . '"' : '',
		dmdune_time( $post ),
		dmdune_comment_count( $post )
	);
}

/**
 * Category names in Japanese, shown beside the English label in the transmission log.
 */
function dmdune_cat_ja( string $slug ): string {
	$ja = array(
		'anime' => 'アニメ', 'blog' => 'ブログ', 'otaku' => 'オタク', 'games' => 'ゲーム', 'music' => '音楽', 'money' => 'お金',
		'reviews' => 'レビュー', 'tech' => '技術', 'rant' => '愚痴', 'figures' => 'フィギュア', 'manga' => '漫画', 'world' => '世界', 'japanese' => '日本語',
	);
	return $ja[ $slug ] ?? '';
}

/**
 * One entry in the transmission log: a timeline row with a date stamp, the post's picture as a
 * recorded frame, and a signal meter for how much discussion it drew. A year marker is printed
 * before the first entry of each year (and at the top of every page).
 */
function dmdune_render_tx( array $attrs, WP_Block $block ): string {
	static $last_year = null;
	$post = get_post( $block->context['postId'] ?? 0 );
	if ( ! $post ) {
		return '';
	}
	return dmdune_tx_html( $post, $last_year );
}

/**
 * A transmission-log entry for $post, preceded by a year marker when its year differs from
 * $last_year (which it then updates). Shared by the home log and the Featured page.
 */
function dmdune_tx_html( WP_Post $post, ?int &$last_year ): string {
	$ts   = get_post_timestamp( $post );
	$year = (int) wp_date( 'Y', $ts );
	$era  = dmdune_era( $year );
	$out  = '';
	if ( $year !== $last_year ) {
		$out      .= sprintf( '<div class="dm-tx-year" data-era="%1$s"><a href="%2$s">%3$d</a><i aria-hidden="true"></i></div>', esc_attr( $era ), esc_url( get_year_link( $year ) ), $year );
		$last_year = $year;
	}

	$x    = dmdune_tx_bits( $post );
	$out .= sprintf(
		'<article class="dm-tx" data-era="%1$s">
			<div class="dm-tx__rail"><time datetime="%2$s"><span class="dm-tx__day">%3$s</span><span class="dm-tx__mon">%4$s</span></time><span class="dm-tx__no">TX·%5$d</span></div>
			%6$s
			<div class="dm-tx__body">
				<p class="dm-tx__cat">%7$s</p>
				<h2 class="dm-tx__title"><a href="%8$s">%9$s</a></h2>
				<p class="dm-tx__excerpt">%10$s</p>
				%11$s
			</div>
		</article>',
		esc_attr( $era ),
		esc_attr( wp_date( 'c', $ts ) ),
		esc_html( wp_date( 'd', $ts ) ),
		esc_html( strtoupper( wp_date( 'M', $ts ) ) ),
		$post->ID,
		$x['frame'],
		$x['cat'],
		esc_url( $x['url'] ),
		$x['title'],
		esc_html( wp_trim_words( get_the_excerpt( $post ), 28, '…' ) ),
		$x['meta']
	);
	return $out;
}

/**
 * The pieces of a transmission-log entry, already escaped: the recorded frame (picture, scanlines,
 * REC stamp), category line, title, link and the signal/replies/reading-time line.
 */
function dmdune_tx_bits( WP_Post $post ): array {
	$ts    = get_post_timestamp( $post );
	$url   = get_permalink( $post );
	$media = dmdune_first_image( $post )
		? dmdune_thumb( $post, 'dm-tx__media' )
		: '<div class="dm-tx__media dm-tx__nosignal"><span>NO SIGNAL</span></div>';
	$n     = (int) $post->comment_count;
	$bars  = $n ? min( 5, max( 1, (int) round( log( $n + 1, 2 ) ) ) ) : 0;
	$sig   = '';
	for ( $i = 0; $i < 5; $i++ ) {
		$sig .= sprintf( '<b class="%s" style="height:%dpx"></b>', $i < $bars ? 'on' : '', 4 + $i * 3 );
	}
	$cat   = dmdune_primary_category( $post );
	$ja    = $cat ? dmdune_cat_ja( $cat->slug ) : '';
	$title = wp_strip_all_tags( get_the_title( $post ) );
	return array(
		'url'   => $url,
		'frame' => sprintf( '<a class="dm-tx__frame" href="%1$s" tabindex="-1" aria-hidden="true">%2$s<span class="dm-tx__scan"></span><span class="dm-tx__rec">REC %3$s</span></a>', esc_url( $url ), $media, esc_html( wp_date( 'Y.m.d', $ts ) ) ),
		'cat'   => dmdune_cat_label( $post ) . ( $ja ? '<span class="dm-tx__ja" lang="ja">' . esc_html( $ja ) . '</span>' : '' ),
		'title' => '' !== $title ? esc_html( $title ) : esc_html__( '(untitled)', 'dmdune' ),
		'meta'  => sprintf(
			'<p class="dm-tx__meta"><span class="dm-tx__sig" aria-hidden="true">%1$s</span><a href="%2$s#comments">%3$s</a><span class="dm-sep" aria-hidden="true">·</span><span>%4$s</span></p>',
			$sig,
			esc_url( $url ),
			esc_html( dmdune_replies( $post ) ),
			/* translators: %d: minutes */
			esc_html( sprintf( __( '%d min read', 'dmdune' ), dmdune_reading_minutes( $post ) ) )
		),
	);
}

function dmdune_render_card( array $attrs, WP_Block $block ): string {
	$post = get_post( $block->context['postId'] ?? 0 );
	return $post ? dmdune_card_html( $post ) : '';
}

function dmdune_render_post_meta( array $attrs, WP_Block $block ): string {
	$post = get_post( $block->context['postId'] ?? 0 );
	if ( ! $post ) {
		return '';
	}
	$cats = array();
	foreach ( get_the_category( $post->ID ) as $c ) {
		$cats[] = sprintf( '<span class="dm-cat" data-cat="%s"><a href="%s">%s</a></span>', esc_attr( $c->slug ), esc_url( get_category_link( $c ) ), esc_html( $c->name ) );
	}
	// The post's number in the transmission log, as on its row there.
	if ( 'post' === $post->post_type ) {
		$ts     = get_post_timestamp( $post );
		$cats[] = sprintf( '<span class="dm-post-no" data-era="%s">TX·%d</span>', esc_attr( dmdune_era( (int) wp_date( 'Y', $ts ), (int) wp_date( 'n', $ts ) ) ), $post->ID );
	}
	return $cats ? '<p class="dm-post-cats">' . implode( '', $cats ) . '</p>' : '';
}

function dmdune_render_byline( array $attrs, WP_Block $block ): string {
	$post = get_post( $block->context['postId'] ?? 0 );
	if ( ! $post ) {
		return '';
	}
	$bits = array( dmdune_time( $post, 'j F Y' ) );
	if ( 'post' === $post->post_type ) {
		/* translators: %d: minutes */
		$bits[] = esc_html( sprintf( __( '%d min read', 'dmdune' ), dmdune_reading_minutes( $post ) ) );
	}
	$n = (int) $post->comment_count;
	if ( $n > 0 ) {
		/* translators: %s: number of comments */
		$bits[] = '<a href="#comments">' . esc_html( sprintf( _n( '%s comment', '%s comments', $n, 'dmdune' ), number_format_i18n( $n ) ) ) . '</a>';
	}
	$era = 'post' === $post->post_type ? dmdune_era_post_link( $post ) : '';
	return '<div class="dm-byline-row"><p class="dm-meta dm-byline">' . implode( '<span class="dm-sep" aria-hidden="true">·</span>', $bits ) . '</p>' . $era . '</div>';
}

function dmdune_render_hero( array $attrs, WP_Block $block ): string {
	$base   = get_template_directory_uri() . '/assets/brand/banner/';
	$layout = wp_cache_get( 'layout', 'dmdune' );
	if ( false === $layout ) {
		$layout = json_decode( (string) file_get_contents( get_template_directory() . '/assets/brand/banner/layout.json' ), true );
		wp_cache_set( 'layout', $layout, 'dmdune' );
	}
	$w     = $layout['width'];
	$h     = $layout['height'];
	$total = array_sum( dmdune_year_counts() );
	$tag   = is_front_page() || is_home() ? 'h1' : 'p';

	$layers = '';
	foreach ( array( 'day', 'night' ) as $mode ) {
		$k        = $layout[ "kiro-$mode" ];
		$priority = 'day' === $mode ? 'fetchpriority="high"' : 'loading="lazy"';
		$layers  .= sprintf(
			'<img class="dm-hero__scene is-%1$s" src="%2$sscene-%1$s-1536.webp?v=%6$s" srcset="%2$sscene-%1$s-960.webp?v=%6$s 960w, %2$sscene-%1$s-1536.webp?v=%6$s 1536w" sizes="(max-width: 1180px) 120vw, 1180px" width="%3$d" height="%4$d" alt="" %5$s decoding="async">',
			$mode,
			esc_url( $base ),
			$w,
			$h,
			$priority,
			esc_attr( DMDUNE_VERSION )
		);
		$layers  .= sprintf(
			'<img class="dm-hero__kiro is-%1$s" src="%2$skiro-%1$s.webp?v=%9$s" width="%3$d" height="%4$d" alt="" %5$s decoding="async" style="left:%6$.3f%%;top:%7$.3f%%;width:%8$.3f%%">',
			$mode,
			esc_url( $base ),
			$k['w'],
			$k['h'],
			$priority,
			100 * $k['x'] / $w,
			100 * $k['y'] / $h,
			100 * $k['w'] / $w,
			esc_attr( DMDUNE_VERSION )
		);
	}

	return sprintf(
		'<section class="dm-hero alignwide" aria-labelledby="dm-hero-title"><div class="dm-hero__stage" role="img" aria-label="%1$s">%2$s</div><div class="dm-hero__text"><p class="dm-kicker">%3$s</p><%4$s id="dm-hero-title" class="dm-hero__title">%5$s</%4$s><p class="dm-hero__tag"%7$s>%6$s</p></div></section>',
		esc_attr__( 'Kiro, a black-and-tan shiba in goggles and a DM団 armband, keeps watch over desert dunes and a distant mirage city. At night he sleeps on the dune under a crescent moon.', 'dmdune' ),
		$layers,
		/* translators: %s: number of posts */
		esc_html( sprintf( __( 'Est. 2003 · %s transmissions', 'dmdune' ), number_format_i18n( $total ) ) ),
		$tag,
		esc_html( get_bloginfo( 'name' ) ),
		esc_html( get_bloginfo( 'description' ) ),
		dmdune_taglines() ? ' data-taglines="' . esc_attr( wp_json_encode( dmdune_taglines() ) ) . '"' : ''
	);
}

function dmdune_render_year_strip( array $attrs, WP_Block $block ): string {
	if ( empty( $attrs['always'] ) && is_paged() ) {
		return '';
	}
	$points = dmdune_month_points();
	if ( ! $points ) {
		return '';
	}
	$first = $points[0][0];
	$last  = end( $points )[0];
	$note  = '<span class="dm-legend"><span><i data-era="b2"></i>b2</span> <span><i data-era="default"></i>default2</span> <span><i data-era="kubrick"></i>Kubrick DM · dmbrickv2</span> <span><i data-era="blocks"></i>Blocks</span></span>';
	return sprintf(
		'<section class="dm-unit dm-history alignwide" aria-labelledby="dm-history-title">%1$s%2$s<p class="dm-spec__range"><span>%3$s</span><span>%4$s</span></p></section>',
		dmdune_hud_head( 'SIGNAL HISTORY', __( 'Every month on air', 'dmdune' ), $note, 'dm-history-title' ),
		dmdune_spectrum( $points, 'dm-spec--months' ),
		esc_html( $first ),
		esc_html( $last )
	);
}

function dmdune_render_archive_index( array $attrs, WP_Block $block ): string {
	global $wp_locale;
	$out = '';
	foreach ( dmdune_month_counts() as $year => $months ) {
		$max   = max( $months );
		$cells = '';
		for ( $m = 1; $m <= 12; $m++ ) {
			$n    = $months[ $m ] ?? 0;
			$name = $wp_locale->get_month_abbrev( $wp_locale->get_month( $m ) );
			$cells .= $n
				? sprintf( '<a class="dm-heat__cell" href="%s" style="--i:%.3f"><span>%s</span><b>%s</b></a>', esc_url( get_month_link( $year, $m ) ), 0.15 + 0.85 * $n / max( 1, $max ), esc_html( $name ), esc_html( number_format_i18n( $n ) ) )
				: sprintf( '<span class="dm-heat__cell is-empty"><span>%s</span><b>–</b></span>', esc_html( $name ) );
		}
		$loud = '';
		foreach ( dmdune_loudest( $year, 3 ) as $id ) {
			$p = get_post( $id );
			if ( $p ) {
				$loud .= '<li>' . dmdune_row_html( $p, 'h3', false ) . '</li>';
			}
		}
		$era  = dmdune_era( $year );
		$out .= sprintf(
			'<section class="dm-unit dm-yearbook" data-era="%1$s" aria-labelledby="dm-y%2$d"><header class="dm-yearbook__head"><h2 id="dm-y%2$d"><a href="%3$s">%2$d</a></h2><p class="dm-yearbook__count">%4$s</p></header><div class="dm-heat">%5$s</div><div class="dm-yearbook__loud"><ul class="dm-rows">%6$s</ul></div></section>',
			esc_attr( $era ),
			$year,
			esc_url( get_year_link( $year ) ),
			/* translators: %s: number of posts */
			esc_html( sprintf( _n( '%s transmission', '%s transmissions', array_sum( $months ), 'dmdune' ), number_format_i18n( array_sum( $months ) ) ) ),
			$cells,
			$loud
		);
	}
	return '<div class="dm-yearbooks alignwide">' . $out . '</div>';
}

function dmdune_render_most_discussed( array $attrs, WP_Block $block ): string {
	if ( is_paged() ) {
		return '';
	}
	$ids = dmdune_loudest( null, max( 1, (int) ( $attrs['limit'] ?? 6 ) ) );
	if ( ! $ids ) {
		return '';
	}
	$max  = max( 1, (int) get_post_field( 'comment_count', $ids[0] ) );
	$rows = '';
	foreach ( $ids as $i => $id ) {
		$p = get_post( $id );
		if ( ! $p ) {
			continue;
		}
		$ts    = get_post_timestamp( $p );
		$n     = (int) $p->comment_count;
		$rows .= sprintf(
			'<li class="dm-rank" data-era="%1$s"><span class="dm-rank__no">%2$02d</span>%3$s<div class="dm-rank__body"><p class="dm-row__stamp"><time datetime="%4$s">%5$s</time>%6$s</p><h3 class="dm-rank__title"><a href="%7$s">%8$s</a></h3><p class="dm-rank__signal"><span class="dm-rank__bar" style="--w:%9$.3f"></span><span class="dm-rank__n">%10$s</span></p></div></li>',
			esc_attr( dmdune_era( (int) wp_date( 'Y', $ts ), (int) wp_date( 'n', $ts ) ) ),
			$i + 1,
			dmdune_frame( $p, 'dm-frame--sm', false ),
			esc_attr( wp_date( 'c', $ts ) ),
			esc_html( wp_date( 'Y.m.d', $ts ) ),
			dmdune_cat_label( $p ),
			esc_url( get_permalink( $p ) ),
			esc_html( wp_strip_all_tags( get_the_title( $p ) ) ),
			$n / $max,
			esc_html( dmdune_replies( $p ) )
		);
	}
	return sprintf(
		'<section class="dm-unit dm-loudest alignwide" aria-labelledby="dm-loudest-title">%s<ol class="dm-ranks">%s</ol></section>',
		dmdune_hud_head( 'LOUDEST TRANSMISSIONS', __( 'Most discussed', 'dmdune' ), esc_html__( 'Ranked by replies, all time', 'dmdune' ), 'dm-loudest-title' ),
		$rows
	);
}

/**
 * The Featured page's hand-picked posts: eight of those tagged "featured", picked at random on
 * each visit. The tag itself stays out of sight elsewhere (see inc/featured.php).
 */
function dmdune_render_featured( array $attrs, WP_Block $block ): string {
	$posts = get_posts(
		array(
			'tag'            => 'featured',
			'orderby'        => 'rand',
			'posts_per_page' => 8,
			'no_found_rows'  => true,
			'has_password'   => false,
		)
	);
	// Two columns of compact transmission-log entries: the same frame, category line, title and
	// signal line as the home page's rows, without the date rail.
	$cards = '';
	foreach ( $posts as $i => $p ) {
		$x      = dmdune_tx_bits( $p );
		$ts     = get_post_timestamp( $p );
		$cards .= sprintf(
			'<article class="dm-pick" data-era="%1$s">%2$s<div class="dm-pick__body"><p class="dm-tx__cat">%3$s</p><%4$s class="dm-pick__title"><a href="%5$s">%6$s</a></%4$s><p class="dm-pick__excerpt">%7$s</p>%8$s</div></article>',
			esc_attr( dmdune_era( (int) wp_date( 'Y', $ts ), (int) wp_date( 'n', $ts ) ) ),
			$x['frame'],
			$x['cat'],
			0 === $i ? 'h2' : 'h3',
			esc_url( $x['url'] ),
			$x['title'],
			esc_html( wp_trim_words( get_the_excerpt( $p ), 24, '…' ) ),
			$x['meta']
		);
	}
	return sprintf(
		'<header class="dm-page-head dm-featured-head alignwide"><p class="dm-kicker">// %1$s</p><h1>%2$s</h1><p>%3$s</p></header><section class="dm-unit dm-featured alignwide" aria-labelledby="dm-featured-title">%4$s%5$s</section>',
		esc_html__( 'Editor’s picks', 'dmdune' ),
		esc_html__( 'Featured', 'dmdune' ),
		esc_html__( 'The best of ten years of blogging, 2003–2013.', 'dmdune' ),
		dmdune_hud_head( 'HAND-PICKED', __( 'Worth reading', 'dmdune' ), '', 'dm-featured-title' ),
		$cards ? '<div class="dm-featured__grid">' . $cards . '</div>' : '<p class="dm-empty">' . esc_html__( 'Nothing picked yet.', 'dmdune' ) . '</p>'
	);
}

function dmdune_render_related( array $attrs, WP_Block $block ): string {
	$post = get_post( $block->context['postId'] ?? 0 );
	if ( ! $post || 'post' !== $post->post_type ) {
		return '';
	}
	$items = '';
	foreach ( dmdune_related_posts( $post ) as $r ) {
		$items .= '<li>' . dmdune_row_html( $r ) . '</li>';
	}
	if ( '' === $items ) {
		return '';
	}
	return sprintf(
		'<nav class="dm-unit dm-related" aria-labelledby="dm-related-title">%s<ul class="dm-rows">%s</ul></nav>',
		dmdune_hud_head( 'NEARBY FREQUENCIES', __( 'Keep reading', 'dmdune' ), '', 'dm-related-title' ),
		$items
	);
}

/**
 * Search results are rows (relevance order, no timeline).
 */
function dmdune_render_row( array $attrs, WP_Block $block ): string {
	$post = get_post( $block->context['postId'] ?? 0 );
	return $post ? dmdune_row_html( $post, 'h2' ) : '';
}

/**
 * Header for every list screen: HUD code, name, count and span, and the list's own signal over
 * the years (or months, for a year archive).
 */
function dmdune_render_archive_head( array $attrs, WP_Block $block ): string {
	global $wp_query, $wp_locale;
	$count  = (int) $wp_query->found_posts;
	$desc   = '';
	$points = array();
	$ja     = '';
	if ( is_search() ) {
		$code   = 'SCAN';
		$title  = '“' . get_search_query() . '”';
		$points = dmdune_year_points( dmdune_year_counts_for( 'search', get_search_query() ), static fn( $y ) => add_query_arg( array( 's' => get_search_query(), 'year' => $y ), home_url( '/' ) ) );
	} elseif ( is_category() || is_tag() ) {
		$term   = get_queried_object();
		$code   = is_category() ? 'CHANNEL' : 'FREQUENCY';
		$title  = $term->name;
		if ( is_tag() && $title === mb_strtolower( $title ) ) {
			$title = mb_convert_case( $title, MB_CASE_TITLE );
		}
		$ja     = is_category() ? dmdune_cat_ja( $term->slug ) : '';
		$desc   = is_category() ? trim( wp_strip_all_tags( (string) $term->description ) ) : '';
		$tt     = (int) $term->term_taxonomy_id;
		$link   = get_term_link( $term );
		$points = dmdune_year_points( dmdune_year_counts_for( 'term', $tt ), static fn( $y ) => add_query_arg( 'year', $y, $link ) );
	} elseif ( is_year() ) {
		$y      = (int) get_query_var( 'year' );
		$code   = 'ARCHIVE · ' . strtoupper( strtr( dmdune_era( $y ), array( 'default' => 'default2', 'now' => 'dune' ) ) ) . ' ERA';
		$title  = (string) $y;
		$months = dmdune_month_counts()[ $y ] ?? array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$points[] = array( $wp_locale->get_month( $m ) . ' ' . $y, $months[ $m ] ?? 0, get_month_link( $y, $m ), dmdune_era( $y, $m ), mb_substr( $wp_locale->get_month_abbrev( $wp_locale->get_month( $m ) ), 0, 1 ) );
		}
	} elseif ( is_page() ) {
		$slug  = get_post_field( 'post_name', get_queried_object_id() );
		$title = 'tag' === $slug ? __( 'Tags', 'dmdune' ) : get_the_title( get_queried_object_id() );
		$years = array_keys( dmdune_year_counts() );
		if ( 'tag' === $slug ) {
			$code  = 'INDEX';
			$count = -1;
			$n     = sprintf(
				/* translators: 1: number of categories, 2: number of tags */
				__( '%1$s channels · %2$s tags', 'dmdune' ),
				number_format_i18n( count( get_categories( array( 'hide_empty' => true ) ) ) ),
				number_format_i18n( (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true ) ) )
			);
		} else {
			$code  = 'ARCHIVE · ' . min( $years ) . '–' . max( $years );
			$count = array_sum( dmdune_year_counts() );
		}
	} elseif ( is_month() || is_day() ) {
		$code  = 'ARCHIVE';
		$title = is_day() ? get_the_date( 'j F Y' ) : get_the_date( 'F Y' );
	} else {
		$code  = 'ARCHIVE';
		$title = wp_strip_all_tags( get_the_archive_title() );
	}
	$years = array_keys( array_filter( array_column( $points, 1, 0 ) ) );
	$span  = ( $points && ! is_year() && $years ) ? ( min( $years ) === max( $years ) ? min( $years ) : min( $years ) . '–' . max( $years ) ) : '';
	if ( $count >= 0 ) {
		/* translators: %s: number of posts */
		$n = sprintf( _n( '%s transmission', '%s transmissions', $count, 'dmdune' ), number_format_i18n( $count ) );
	}
	return sprintf(
		'<header class="dm-archive-head alignwide"><div class="dm-archive-head__text"><p class="dm-hud-code">// %1$s</p><h1>%2$s%3$s</h1><p class="dm-meta">%4$s%5$s%6$s</p>%7$s</div>%8$s</header>',
		esc_html( $code ),
		esc_html( $title ),
		$ja ? ' <span class="dm-archive-head__ja" lang="ja">' . esc_html( $ja ) . '</span>' : '',
		esc_html( $n ),
		$span ? '<span class="dm-sep" aria-hidden="true">·</span>' . esc_html( $span ) : '',
		$desc ? '<span class="dm-sep" aria-hidden="true">·</span><span lang="ja">' . esc_html( $desc ) . '</span>' : '',
		is_search() ? get_search_form( array( 'echo' => false ) ) : '',
		$points ? '<div class="dm-archive-head__spec">' . dmdune_spectrum( $points, 'dm-spec--years' ) . '</div>' : ''
	);
}

/**
 * Tags page: categories as channels with their own signal, tags grouped by strength.
 */
function dmdune_render_channels( array $attrs, WP_Block $block ): string {
	$cats  = get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true ) );
	$tiles = '';
	foreach ( $cats as $i => $c ) {
		$tiles .= sprintf(
			'<li><a class="dm-channel" data-cat="%1$s" href="%2$s"><span class="dm-channel__ch">CH·%3$02d</span><span class="dm-channel__name">%4$s</span><span class="dm-channel__ja" lang="ja">%5$s</span><span class="dm-channel__n">%6$s</span>%7$s</a></li>',
			esc_attr( $c->slug ),
			esc_url( get_category_link( $c ) ),
			$i + 1,
			esc_html( $c->name ),
			esc_html( dmdune_cat_ja( $c->slug ) ),
			esc_html( number_format_i18n( $c->count ) ),
			// Inside a link, so the bars must not be links themselves.
			dmdune_spectrum( dmdune_year_points( dmdune_year_counts_for( 'term', (int) $c->term_taxonomy_id ), static fn() => '' ), 'dm-spec--mini' )
		);
	}
	$tags  = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 180, 'hide_empty' => true ) );
	$tiers = array(
		'strong' => array( __( 'Strong signal', 'dmdune' ), '20+', array() ),
		'clear'  => array( __( 'Clear', 'dmdune' ), '6–19', array() ),
		'faint'  => array( __( 'Faint', 'dmdune' ), '3–5', array() ),
	);
	foreach ( $tags as $t ) {
		$k = $t->count >= 20 ? 'strong' : ( $t->count >= 6 ? 'clear' : ( $t->count >= 3 ? 'faint' : '' ) );
		if ( $k ) {
			$tiers[ $k ][2][] = $t;
		}
	}
	$bands = '';
	foreach ( $tiers as $k => $tier ) {
		if ( ! $tier[2] ) {
			continue;
		}
		usort( $tier[2], static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
		$chips = '';
		foreach ( $tier[2] as $t ) {
			$chips .= sprintf( '<li><a href="%s">%s<b>%s</b></a></li>', esc_url( get_tag_link( $t ) ), esc_html( $t->name ), esc_html( number_format_i18n( $t->count ) ) );
		}
		$bands .= sprintf( '<section class="dm-band dm-band--%1$s"><p class="dm-hud-code">// %2$s · %3$s</p><ul>%4$s</ul></section>', esc_attr( $k ), esc_html( strtoupper( $tier[0] ) ), esc_html( $tier[1] ), $chips );
	}
	$total_tags = (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true ) );
	return sprintf(
		'<div class="dm-channels alignwide"><section class="dm-unit" aria-labelledby="dm-ch-title">%1$s<ul class="dm-channel-grid">%2$s</ul></section><section class="dm-unit" aria-labelledby="dm-fq-title">%3$s%4$s</section></div>',
		dmdune_hud_head( 'CHANNELS', __( 'Categories', 'dmdune' ), esc_html__( 'Each bar is a year, 2003–2013', 'dmdune' ), 'dm-ch-title' ),
		$tiles,
		/* translators: %s: number of tags */
		dmdune_hud_head( 'FREQUENCIES', __( 'Tags', 'dmdune' ), esc_html( sprintf( __( '%s tags; showing those used 3+ times', 'dmdune' ), number_format_i18n( $total_tags ) ) ), 'dm-fq-title' ),
		$bands
	);
}

/**
 * Header, footer and tab bar come from chrome/chrome.php, shared with the lyrics and gallery sites.
 */
function dmdune_chrome_config(): array {
	return array(
		'site'        => 'blog',
		'current'     => dmdune_current_nav(),
		'www'         => home_url( '/' ),
		'assets'      => get_template_directory_uri() . '/assets/',
		/* translators: %s: number of posts */
		'placeholder' => sprintf( __( 'Search %s posts', 'dmdune' ), number_format_i18n( array_sum( dmdune_year_counts() ) ) ),
		'note'        => esc_html( sprintf( __( '%1$s · 2003–%2$s · Kiro and the art: all rights reserved · Theme code: GPL-2.0', 'dmdune' ), get_bloginfo( 'name' ), wp_date( 'Y' ) ) ),
	);
}

function dmdune_render_site_header( array $attrs, WP_Block $block ): string {
	return dmc_header_html( dmdune_chrome_config() );
}

function dmdune_render_site_footer( array $attrs, WP_Block $block ): string {
	return dmc_footer_html( dmdune_chrome_config() );
}

function dmdune_render_not_found( array $attrs, WP_Block $block ): string {
	return sprintf(
		'<section class="dm-404"><img class="dm-404__art is-day" src="%1$slost-day.webp" alt="" width="960" height="640"><img class="dm-404__art is-night" src="%1$slost-night.webp" alt="" width="960" height="640" loading="lazy"><p class="dm-kicker">404 · %2$s</p><h1>%3$s</h1><p>%4$s</p>%5$s</section>',
		esc_url( get_template_directory_uri() . '/assets/brand/lost/' ),
		esc_html__( 'Not found', 'dmdune' ),
		esc_html__( 'Kiro got lost looking for this page.', 'dmdune' ),
		esc_html__( 'The link may be from an old version of the site. Try a search, or browse the archive.', 'dmdune' ),
		get_search_form( array( 'echo' => false ) )
	);
}
