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
			'post-meta'      => array( 'title' => __( 'Post categories', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'byline'         => array( 'title' => __( 'Post byline', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'archive-head'   => array( 'title' => __( 'Archive header', 'dmdune' ) ),
			'related'        => array( 'title' => __( 'Related posts', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
			'year-strip'     => array( 'title' => __( 'Archive by year', 'dmdune' ), 'attributes' => array( 'always' => array( 'type' => 'boolean', 'default' => false ) ) ),
			'archive-index'  => array( 'title' => __( 'Archive index', 'dmdune' ) ),
			'most-discussed' => array( 'title' => __( 'Most discussed', 'dmdune' ) ),
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
	$src = dmdune_first_image( $post );
	if ( $src ) {
		// The blurred copy only shows when the picture is letterboxed (see .is-fit and dmdune.js).
		return sprintf(
			'<div class="%1$s dm-media%3$s"><img class="dm-media__bg" src="%2$s" alt="" aria-hidden="true" loading="lazy" decoding="async"><img class="dm-media__img" src="%2$s" alt="" loading="lazy" decoding="async"></div>',
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
	return '<p class="dm-meta dm-byline">' . implode( '<span class="dm-sep" aria-hidden="true">·</span>', $bits ) . '</p>';
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
			'<img class="dm-hero__scene is-%1$s" src="%2$sscene-%1$s-1536.webp" srcset="%2$sscene-%1$s-960.webp 960w, %2$sscene-%1$s-1536.webp 1536w" sizes="(max-width: 1180px) 120vw, 1180px" width="%3$d" height="%4$d" alt="" %5$s decoding="async">',
			$mode,
			esc_url( $base ),
			$w,
			$h,
			$priority
		);
		$layers  .= sprintf(
			'<img class="dm-hero__kiro is-%1$s" src="%2$skiro-%1$s.webp" width="%3$d" height="%4$d" alt="" %5$s decoding="async" style="left:%6$.3f%%;top:%7$.3f%%;width:%8$.3f%%">',
			$mode,
			esc_url( $base ),
			$k['w'],
			$k['h'],
			$priority,
			100 * $k['x'] / $w,
			100 * $k['y'] / $h,
			100 * $k['w'] / $w
		);
	}

	return sprintf(
		'<section class="dm-hero alignwide" aria-labelledby="dm-hero-title"><div class="dm-hero__stage" role="img" aria-label="%1$s">%2$s</div><div class="dm-hero__text"><p class="dm-kicker">%3$s</p><%4$s id="dm-hero-title" class="dm-hero__title">%5$s</%4$s><p class="dm-hero__tag"%7$s>%6$s</p></div></section>',
		esc_attr__( 'Kiro, a black-and-tan shiba in goggles and a DM団 armband, keeps watch over desert dunes and a distant mirage city. At night he sleeps on the dune under a crescent moon.', 'dmdune' ),
		$layers,
		/* translators: %s: number of posts */
		esc_html( sprintf( __( 'Est. 2005 · %s transmissions', 'dmdune' ), number_format_i18n( $total ) ) ),
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
	$counts = dmdune_year_counts();
	if ( ! $counts ) {
		return '';
	}
	$max  = max( $counts );
	$bars = '';
	foreach ( $counts as $year => $n ) {
		$bars .= sprintf(
			'<li><a href="%1$s" class="dm-years__bar" data-era="%2$s"><span class="dm-years__fill" style="--h:%3$.3f"></span><span class="dm-years__label">%4$d <span>%5$s</span></span></a></li>',
			esc_url( get_year_link( $year ) ),
			esc_attr( dmdune_era( $year ) ),
			max( 0.03, $n / $max ),
			$year,
			esc_html( number_format_i18n( $n ) )
		);
	}
	return sprintf(
		'<section class="dm-years alignwide" aria-labelledby="dm-years-title"><div class="dm-years__head"><h2 id="dm-years-title" class="dm-section-title">%1$s</h2><p class="dm-years__legend"><span data-era="default">%2$s</span><span data-era="kubrick">%3$s</span><span data-era="blocks">%4$s</span></p></div><ol class="dm-years__bars">%5$s</ol></section>',
		esc_html__( 'The archive, year by year', 'dmdune' ),
		esc_html__( 'WP Default', 'dmdune' ),
		esc_html__( 'Kubrick DM · dmbrickv2', 'dmdune' ),
		esc_html__( 'Blocks', 'dmdune' ),
		$bars
	);
}

function dmdune_render_archive_index( array $attrs, WP_Block $block ): string {
	global $wp_locale;
	$out = '';
	foreach ( dmdune_month_counts() as $year => $months ) {
		$items = '';
		foreach ( $months as $m => $n ) {
			$items .= sprintf(
				'<li><a href="%s">%s <span>%s</span></a></li>',
				esc_url( get_month_link( $year, $m ) ),
				esc_html( $wp_locale->get_month_abbrev( $wp_locale->get_month( $m ) ) ),
				esc_html( number_format_i18n( $n ) )
			);
		}
		$out .= sprintf(
			'<section class="dm-archive__year" data-era="%1$s"><h2><a href="%2$s">%3$d</a> <span>%4$s</span></h2><ul>%5$s</ul></section>',
			esc_attr( dmdune_era( $year ) ),
			esc_url( get_year_link( $year ) ),
			$year,
			/* translators: %s: number of posts */
			esc_html( sprintf( _n( '%s post', '%s posts', array_sum( $months ), 'dmdune' ), number_format_i18n( array_sum( $months ) ) ) ),
			$items
		);
	}
	return '<div class="dm-archive alignwide">' . $out . '</div>';
}

function dmdune_render_most_discussed( array $attrs, WP_Block $block ): string {
	if ( is_paged() ) {
		return '';
	}
	$ids = get_transient( 'dmdune_most_discussed' );
	if ( ! is_array( $ids ) ) {
		$ids = get_posts(
			array(
				'orderby'        => 'comment_count',
				'order'          => 'DESC',
				'posts_per_page' => 6,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		set_transient( 'dmdune_most_discussed', $ids, DAY_IN_SECONDS );
	}
	$cards = '';
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( $p ) {
			$cards .= '<li>' . dmdune_card_html( $p, 'h3' ) . '</li>';
		}
	}
	return sprintf(
		'<section class="dm-discussed alignwide" aria-labelledby="dm-discussed-title"><div class="dm-section-head"><h2 id="dm-discussed-title" class="dm-section-title">%s</h2><p class="dm-meta">%s</p></div><ul class="dm-grid">%s</ul></section>',
		esc_html__( 'Most discussed', 'dmdune' ),
		esc_html__( 'The posts readers argued about most', 'dmdune' ),
		$cards
	);
}

function dmdune_render_related( array $attrs, WP_Block $block ): string {
	$post = get_post( $block->context['postId'] ?? 0 );
	if ( ! $post || 'post' !== $post->post_type ) {
		return '';
	}
	$items = '';
	foreach ( dmdune_related_posts( $post ) as $r ) {
		$items .= '<li>' . dmdune_card_html( $r, 'h3' ) . '</li>';
	}
	if ( '' === $items ) {
		return '';
	}
	return sprintf(
		'<nav class="dm-related" aria-labelledby="dm-related-title"><div class="dm-section-head"><h2 id="dm-related-title" class="dm-section-title">%s</h2></div><ul class="dm-grid dm-grid--related">%s</ul></nav>',
		esc_html__( 'Keep reading', 'dmdune' ),
		$items
	);
}

/**
 * Header for archive, category, tag and search pages: what kind of list this is, its name, and
 * how many posts it holds.
 */
function dmdune_render_archive_head( array $attrs, WP_Block $block ): string {
	global $wp_query;
	$count = (int) $wp_query->found_posts;
	$desc  = '';
	if ( is_search() ) {
		$kind  = __( 'Search', 'dmdune' );
		/* translators: %s: search terms */
		$title = sprintf( __( '“%s”', 'dmdune' ), get_search_query() );
	} elseif ( is_category() ) {
		$kind  = __( 'Category', 'dmdune' );
		$title = single_cat_title( '', false );
		$desc  = category_description();
	} elseif ( is_tag() ) {
		$kind  = __( 'Tag', 'dmdune' );
		$title = single_tag_title( '', false );
		// Tags were typed in lowercase; give them a title's capitalisation, leave mixed-case ones alone.
		if ( $title === mb_strtolower( $title ) ) {
			$title = mb_convert_case( $title, MB_CASE_TITLE );
		}
	} elseif ( is_year() ) {
		$kind  = __( 'Archive', 'dmdune' );
		$title = get_the_date( 'Y' );
	} elseif ( is_month() ) {
		$kind  = __( 'Archive', 'dmdune' );
		$title = get_the_date( 'F Y' );
	} elseif ( is_day() ) {
		$kind  = __( 'Archive', 'dmdune' );
		$title = get_the_date( 'j F Y' );
	} else {
		$kind  = __( 'Archive', 'dmdune' );
		$title = wp_strip_all_tags( get_the_archive_title() );
	}
	/* translators: %s: number of posts */
	$n    = sprintf( _n( '%s post', '%s posts', $count, 'dmdune' ), number_format_i18n( $count ) );
	$desc = trim( wp_strip_all_tags( (string) $desc ) );
	return sprintf(
		'<header class="dm-archive-head alignwide"><p class="dm-eyebrow">%1$s</p><h1>%2$s</h1><p class="dm-meta">%3$s%4$s</p>%5$s</header>',
		esc_html( $kind ),
		esc_html( $title ),
		esc_html( $n ),
		$desc ? '<span class="dm-sep" aria-hidden="true">·</span><span lang="ja">' . esc_html( $desc ) . '</span>' : '',
		is_search() ? get_search_form( array( 'echo' => false ) ) : ''
	);
}

function dmdune_mode_button( string $class ): string {
	return sprintf(
		'<button type="button" class="dm-mode %1$s" data-dm-toggle aria-label="%2$s">%3$s%4$s<span class="dm-mode__label"><span class="is-day">%5$s</span><span class="is-night">%6$s</span></span></button>',
		esc_attr( $class ),
		esc_attr__( 'Switch between light and dark mode', 'dmdune' ),
		str_replace( 'class="dm-icon"', 'class="dm-icon is-day"', dmdune_icon( 'moon', 16 ) ),
		str_replace( 'class="dm-icon"', 'class="dm-icon is-night"', dmdune_icon( 'sun', 16 ) ),
		esc_html__( 'Dark', 'dmdune' ),
		esc_html__( 'Light', 'dmdune' )
	);
}

function dmdune_render_site_header( array $attrs, WP_Block $block ): string {
	$current = dmdune_current_nav();
	$links   = '';
	foreach ( dmdune_nav_items() as $item ) {
		$links .= sprintf(
			'<li><a href="%1$s"%2$s%3$s>%4$s%5$s</a></li>',
			esc_url( $item['url'] ),
			$current === $item['key'] ? ' aria-current="page"' : '',
			! empty( $item['external'] ) ? ' class="is-external"' : '',
			esc_html( $item['label'] ),
			! empty( $item['external'] ) ? '<span class="dm-out" aria-hidden="true">↗</span>' : ''
		);
	}
	$icon = get_template_directory_uri() . '/assets/brand/icons/';
	return sprintf(
		'<a class="dm-skip" href="#dm-main">%1$s</a><div class="dm-topbar"><nav class="dm-nav" aria-label="%2$s"><a class="dm-brand" href="%3$s" rel="home"><img class="is-day" src="%4$sroundel-day.webp" alt="" width="34" height="34"><img class="is-night" src="%4$sroundel-night.webp" alt="" width="34" height="34" loading="lazy"><span>Dark<b>Mirage</b></span></a><ul class="dm-nav__links">%5$s</ul><details class="dm-nav__more" hidden><summary>%10$s</summary><ul></ul></details><div class="dm-nav__tools"><a class="dm-iconbtn" href="%6$s" aria-label="%7$s">%8$s</a>%9$s</div></nav></div>',
		esc_html__( 'Skip to content', 'dmdune' ),
		esc_attr__( 'Main', 'dmdune' ),
		esc_url( home_url( '/' ) ),
		esc_url( $icon ),
		$links,
		esc_url( home_url( '/?s=' ) ),
		esc_attr__( 'Search', 'dmdune' ),
		dmdune_icon( 'search', 18 ),
		dmdune_mode_button( 'dm-mode--top' ),
		esc_html__( 'More', 'dmdune' )
	);
}

function dmdune_render_site_footer( array $attrs, WP_Block $block ): string {
	$current = dmdune_current_nav();
	$items   = dmdune_nav_items();
	$tab     = static function ( array $item ) use ( $current ) {
		return sprintf(
			'<a href="%1$s"%2$s>%3$s<span>%4$s</span></a>',
			esc_url( $item['url'] ),
			$current === $item['key'] ? ' aria-current="page"' : '',
			dmdune_icon( $item['icon'], 22 ),
			esc_html( $item['label'] )
		);
	};
	$by_key = array_column( $items, null, 'key' );
	$more   = '';
	foreach ( $items as $item ) {
		if ( in_array( $item['key'], array( 'home', 'archives', 'tag' ), true ) ) {
			continue;
		}
		$more .= sprintf(
			'<li><a href="%1$s"%2$s>%3$s<span>%4$s</span>%5$s</a></li>',
			esc_url( $item['url'] ),
			$current === $item['key'] ? ' aria-current="page"' : '',
			dmdune_icon( $item['icon'], 20 ),
			esc_html( $item['label'] ),
			! empty( $item['external'] ) ? dmdune_icon( 'out', 16 ) : ''
		);
	}
	$search = sprintf(
		'<form class="dm-search" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="dm-sheet-search">%2$s</label><input id="dm-sheet-search" type="search" name="s" placeholder="%3$s"><button type="submit" aria-label="%2$s">%4$s</button></form>',
		esc_url( home_url( '/' ) ),
		esc_attr__( 'Search', 'dmdune' ),
		/* translators: %s: number of posts */
		esc_attr( sprintf( __( 'Search %s posts', 'dmdune' ), number_format_i18n( array_sum( dmdune_year_counts() ) ) ) ),
		dmdune_icon( 'search', 18 )
	);

	return sprintf(
		'<div class="dm-footer"><p>%1$s</p><p class="dm-footer__lineage" aria-label="%2$s">Kubrick DM <span>→</span> dmbrickv2 <span>→</span> Blocks <span>→</span> <b>Dune</b></p></div>
		<nav class="dm-tabbar" aria-label="%3$s">%4$s%5$s%6$s<details class="dm-sheet"><summary>%7$s<span>%8$s</span></summary><div class="dm-sheet__panel">%9$s<ul>%10$s</ul>%11$s</div></details></nav>',
		/* translators: 1: site name, 2: year */
		esc_html( sprintf( __( '%1$s · 2005–%2$s · Kiro and the art: all rights reserved · Theme code: GPL-2.0', 'dmdune' ), get_bloginfo( 'name' ), wp_date( 'Y' ) ) ),
		esc_attr__( 'This theme follows Kubrick DM, dmbrickv2 and DarkMirage Blocks', 'dmdune' ),
		esc_attr__( 'Main (mobile)', 'dmdune' ),
		$tab( $by_key['home'] ),
		$tab( $by_key['archives'] ),
		$tab( $by_key['tag'] ),
		dmdune_icon( 'more', 22 ),
		esc_html__( 'More', 'dmdune' ),
		$search,
		$more,
		dmdune_mode_button( 'dm-mode--sheet' )
	);
}

function dmdune_render_not_found( array $attrs, WP_Block $block ): string {
	return sprintf(
		'<section class="dm-404"><img class="dm-404__kiro" src="%1$s" alt="" width="380" height="172"><p class="dm-kicker">404 · %2$s</p><h1>%3$s</h1><p>%4$s</p>%5$s</section>',
		esc_url( get_template_directory_uri() . '/assets/brand/banner/kiro-night.webp' ),
		esc_html__( 'Not found', 'dmdune' ),
		esc_html__( 'Kiro dozed off looking for this page.', 'dmdune' ),
		esc_html__( 'The link may be from an old version of the site. Try a search, or browse the archive.', 'dmdune' ),
		get_search_form( array( 'echo' => false ) )
	);
}
