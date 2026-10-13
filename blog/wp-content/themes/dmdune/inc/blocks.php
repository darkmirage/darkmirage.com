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
			'post-meta'      => array( 'title' => __( 'Post meta line', 'dmdune' ), 'uses_context' => array( 'postId' ) ),
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

/**
 * "2013.06.16 · BLOG · 12 COMMENTS"
 */
function dmdune_kicker( WP_Post $post, bool $with_comments = true ): string {
	$parts = array( get_the_date( 'Y.m.d', $post ) );
	$cats  = get_the_category( $post->ID );
	if ( $cats ) {
		$parts[] = $cats[0]->name;
	}
	$n = (int) $post->comment_count;
	if ( $with_comments && $n > 0 ) {
		/* translators: %s: number of comments */
		$parts[] = sprintf( _n( '%s comment', '%s comments', $n, 'dmdune' ), number_format_i18n( $n ) );
	}
	return implode( ' · ', array_map( 'esc_html', $parts ) );
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
	return sprintf(
		'<article class="dm-card"><a class="dm-card__link" href="%1$s">%2$s<div class="dm-card__body"><p class="dm-kicker">%3$s</p><%5$s class="dm-card__title">%4$s</%5$s><p class="dm-card__excerpt">%6$s</p></div></a></article>',
		esc_url( get_permalink( $post ) ),
		dmdune_thumb( $post, 'dm-card__media' ),
		dmdune_kicker( $post ),
		'' !== $title ? esc_html( wp_strip_all_tags( $title ) ) : esc_html__( '(untitled)', 'dmdune' ),
		tag_escape( $heading ),
		esc_html( get_the_excerpt( $post ) )
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
	$cats = get_the_category( $post->ID );
	$bits = array( '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( 'Y.m.d', $post ) ) . '</time>' );
	foreach ( $cats as $c ) {
		$bits[] = '<a href="' . esc_url( get_category_link( $c ) ) . '">' . esc_html( $c->name ) . '</a>';
	}
	$n = (int) $post->comment_count;
	if ( $n > 0 ) {
		/* translators: %s: number of comments */
		$bits[] = '<a href="#comments">' . esc_html( sprintf( _n( '%s comment', '%s comments', $n, 'dmdune' ), number_format_i18n( $n ) ) ) . '</a>';
	}
	return '<p class="dm-kicker dm-post-meta">' . implode( ' · ', $bits ) . '</p>';
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
		'<section class="dm-discussed alignwide" aria-labelledby="dm-discussed-title"><h2 id="dm-discussed-title" class="dm-section-title">%s</h2><ul class="dm-grid">%s</ul></section>',
		esc_html__( 'Most discussed', 'dmdune' ),
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
		$items .= sprintf(
			'<li><a href="%s">%s<span><span class="dm-kicker">%s</span><strong>%s</strong></span></a></li>',
			esc_url( get_permalink( $r ) ),
			dmdune_thumb( $r, 'dm-related__thumb' ),
			dmdune_kicker( $r, false ),
			esc_html( wp_strip_all_tags( get_the_title( $r ) ) )
		);
	}
	if ( '' === $items ) {
		return '';
	}
	return sprintf( '<nav class="dm-related" aria-labelledby="dm-related-title"><h2 id="dm-related-title" class="dm-label">%s</h2><ul>%s</ul></nav>', esc_html__( 'Related', 'dmdune' ), $items );
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
