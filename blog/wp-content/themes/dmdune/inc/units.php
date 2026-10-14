<?php
/**
 * The transmission-log unit family. Every list on the site is built from the same parts:
 * a section head in the HUD voice, the recorded frame, the signal meter, outlined numerals,
 * and era colours (the theme that was live when a post was written).
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

/**
 * Section head: "// SIGNAL HISTORY", a display title, and a note on the right.
 */
function dmdune_hud_head( string $code, string $title, string $note = '', string $id = '', string $tag = 'h2' ): string {
	return sprintf(
		'<div class="dm-hud-head"><p class="dm-hud-code">// %1$s</p><%5$s class="dm-hud-title"%4$s>%2$s</%5$s>%3$s</div>',
		esc_html( $code ),
		esc_html( $title ),
		$note ? '<p class="dm-hud-note">' . $note . '</p>' : '',
		$id ? ' id="' . esc_attr( $id ) . '"' : '',
		tag_escape( $tag )
	);
}

/**
 * Five-bar signal meter (log scale of comment count).
 */
function dmdune_signal( int $n ): string {
	$bars = $n ? min( 5, max( 1, (int) round( log( $n + 1, 2 ) ) ) ) : 0;
	$out  = '';
	for ( $i = 0; $i < 5; $i++ ) {
		$out .= sprintf( '<b class="%s" style="height:%dpx"></b>', $i < $bars ? 'on' : '', 4 + $i * 3 );
	}
	return '<span class="dm-sig" aria-hidden="true">' . $out . '</span>';
}

function dmdune_replies( WP_Post $post ): string {
	$n = (int) $post->comment_count;
	/* translators: %s: number of comments */
	return $n ? sprintf( _n( '%s reply', '%s replies', $n, 'dmdune' ), number_format_i18n( $n ) ) : __( 'No replies', 'dmdune' );
}

/**
 * The post's picture as a recorded frame (scanlines, REC stamp), or NO SIGNAL.
 */
function dmdune_frame( WP_Post $post, string $class = '', bool $stamp = true ): string {
	$ts    = get_post_timestamp( $post );
	$media = dmdune_first_image( $post )
		? dmdune_thumb( $post, 'dm-frame__media' )
		: '<div class="dm-frame__media dm-frame__nosignal"><span>NO SIGNAL</span></div>';
	return sprintf(
		'<a class="dm-frame %1$s" href="%2$s" tabindex="-1" aria-hidden="true">%3$s<span class="dm-frame__scan"></span>%4$s</a>',
		esc_attr( $class ),
		esc_url( get_permalink( $post ) ),
		$media,
		$stamp ? '<span class="dm-frame__rec">REC ' . esc_html( wp_date( 'Y.m.d', $ts ) ) . '</span>' : ''
	);
}

/**
 * Compact log row: small frame, stamp line, title, signal. Used for search results, Keep reading,
 * and the archive page's loudest posts.
 */
function dmdune_row_html( WP_Post $post, string $heading = 'h3', bool $number = true ): string {
	$ts    = get_post_timestamp( $post );
	$title = wp_strip_all_tags( get_the_title( $post ) );
	return sprintf(
		'<article class="dm-row" data-era="%1$s">%2$s<div class="dm-row__body"><p class="dm-row__stamp"><time datetime="%3$s">%4$s</time>%5$s</p><%6$s class="dm-row__title"><a href="%7$s">%8$s</a></%6$s><p class="dm-row__meta">%9$s%10$s<span class="dm-sep" aria-hidden="true">·</span>%11$s</p></div></article>',
		esc_attr( dmdune_era( (int) wp_date( 'Y', $ts ), (int) wp_date( 'n', $ts ) ) ),
		dmdune_frame( $post, 'dm-frame--sm', false ),
		esc_attr( wp_date( 'c', $ts ) ),
		esc_html( wp_date( 'Y.m.d', $ts ) ),
		$number ? '<span class="dm-row__no">TX·' . (int) $post->ID . '</span>' : '',
		tag_escape( $heading ),
		esc_url( get_permalink( $post ) ),
		esc_html( '' !== $title ? $title : __( '(untitled)', 'dmdune' ) ),
		dmdune_cat_label( $post ),
		dmdune_signal( (int) $post->comment_count ),
		esc_html( dmdune_replies( $post ) )
	);
}

/**
 * Spectrum: one bar per period, coloured by era. $points is a list of
 * [label, count, url, era, tick-label-or-empty].
 */
function dmdune_spectrum( array $points, string $class = '' ): string {
	$max    = max( 1, max( array_column( $points, 1 ) ) );
	$bars   = '';
	$months = false !== strpos( $class, 'months' );
	// A long silence between posts (2013 to 2026) would otherwise take most of the axis: three or
	// more empty years in a row collapse into a short break, labelled.
	$min_gap = $months ? 36 : 3;
	$first   = null;
	$last    = null;
	foreach ( $points as $i => $p ) {
		if ( $p[1] ) {
			$first = $first ?? $i;
			$last  = $i;
		}
	}
	$skip = array();
	for ( $i = (int) $first; null !== $first && $i < $last; $i++ ) {
		if ( $points[ $i ][1] ) {
			continue;
		}
		$j = $i;
		while ( $j < $last && ! $points[ $j ][1] ) {
			$j++;
		}
		if ( $j - $i >= $min_gap ) {
			$skip[ $i ] = $j;
			// The point after the break names its year, as the one at a year's start would.
			if ( '' === $points[ $j ][4] ) {
				$points[ $j ][4] = substr( (string) $points[ $j ][0], -4 );
			}
		}
		$i = $j;
	}
	foreach ( $points as $i => $p ) {
		if ( isset( $skip[ $i ] ) ) {
			$run   = $skip[ $i ] - $i;
			$years = $months ? (int) round( $run / 12 ) : $run;
			/* translators: %d: number of years */
			$quiet = sprintf( _n( '%d yr off air', '%d yrs off air', $years, 'dmdune' ), $years );
			$bars .= sprintf(
				'<span class="dm-spec__gap" title="%s"><span class="dm-spec__gapl">%s</span></span>',
				esc_attr( $p[0] . ' – ' . $points[ $skip[ $i ] - 1 ][0] . ' · ' . __( 'nothing posted', 'dmdune' ) ),
				esc_html( $quiet )
			);
		}
		foreach ( $skip as $a => $b ) {
			if ( $i >= $a && $i < $b ) {
				continue 2;
			}
		}
		list( $label, $n, $url, $era, $tick ) = $p;
		$h     = $n ? max( 0.04, $n / $max ) : 0;
		$tip   = esc_attr( $label . ' · ' . sprintf( _n( '%s post', '%s posts', $n, 'dmdune' ), number_format_i18n( $n ) ) );
		$inner = sprintf( '<span class="dm-spec__bar" style="--h:%.3f"></span>%s', $h, $tick ? '<span class="dm-spec__tick">' . esc_html( $tick ) . '</span>' : '' );
		$bars .= $n && $url
			? sprintf( '<a class="dm-spec__col%s" data-era="%s" href="%s" title="%s" aria-label="%s">%s</a>', $tick ? ' is-tick' : '', esc_attr( $era ), esc_url( $url ), $tip, $tip, $inner )
			: sprintf( '<span class="dm-spec__col%s%s" data-era="%s" title="%s">%s</span>', $n ? '' : ' is-empty', $tick ? ' is-tick' : '', esc_attr( $era ), $tip, $inner );
	}
	return '<div class="dm-spec ' . esc_attr( $class ) . '">' . $bars . '</div>';
}

/**
 * Monthly points for the whole blog, from the first month with a post to the last.
 */
function dmdune_month_points(): array {
	$counts = dmdune_month_counts();
	if ( ! $counts ) {
		return array();
	}
	$years  = array_keys( $counts );
	$first  = min( $years );
	$last   = max( $years );
	$fm     = min( array_keys( $counts[ $first ] ) );
	$lm     = max( array_keys( $counts[ $last ] ) );
	$points = array();
	for ( $y = $first; $y <= $last; $y++ ) {
		for ( $m = 1; $m <= 12; $m++ ) {
			if ( ( $y === $first && $m < $fm ) || ( $y === $last && $m > $lm ) ) {
				continue;
			}
			$n        = $counts[ $y ][ $m ] ?? 0;
			$points[] = array( wp_date( 'M Y', mktime( 12, 0, 0, $m, 1, $y ) ), $n, get_month_link( $y, $m ), dmdune_era( $y, $m ), 1 === $m ? (string) $y : '' );
		}
	}
	return $points;
}

/**
 * Posts per year for one taxonomy term, or for a search.
 *
 * @return array<int,int>
 */
function dmdune_year_counts_for( string $kind, $value ): array {
	global $wpdb;
	$key    = 'dmdune_yc_' . md5( $kind . '|' . maybe_serialize( $value ) );
	$counts = get_transient( $key );
	if ( is_array( $counts ) ) {
		return $counts;
	}
	if ( 'term' === $kind ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT YEAR(p.post_date) y, COUNT(*) n FROM {$wpdb->posts} p
			 JOIN {$wpdb->term_relationships} r ON r.object_id = p.ID
			 WHERE r.term_taxonomy_id = %d AND p.post_type = 'post' AND p.post_status = 'publish' GROUP BY y",
			(int) $value
		) );
	} else {
		$ids  = get_posts( array( 's' => (string) $value, 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		$rows = array();
		foreach ( $ids as $id ) {
			$y          = (int) get_the_date( 'Y', $id );
			$rows[ $y ] = ( $rows[ $y ] ?? 0 ) + 1;
		}
		$rows = array_map( static fn( $y, $n ) => (object) array( 'y' => $y, 'n' => $n ), array_keys( $rows ), $rows );
	}
	$counts = array();
	foreach ( $rows as $r ) {
		$counts[ (int) $r->y ] = (int) $r->n;
	}
	set_transient( $key, $counts, DAY_IN_SECONDS );
	return $counts;
}

/**
 * Year points across the blog's whole span, for small per-term spectra.
 */
function dmdune_year_points( array $counts, ?callable $url = null ): array {
	$all    = array_keys( dmdune_year_counts() );
	$points = array();
	foreach ( range( min( $all ), max( $all ) ) as $y ) {
		$n        = $counts[ $y ] ?? 0;
		$points[] = array( (string) $y, $n, $url ? $url( $y ) : get_year_link( $y ), dmdune_era( $y ), "'" . substr( (string) $y, 2 ) );
	}
	return $points;
}

/**
 * The loudest posts of a year (most comments), cached.
 *
 * @return int[]
 */
function dmdune_loudest( ?int $year = null, int $limit = 3 ): array {
	$key = 'dmdune_loud_' . ( $year ?? 'all' ) . '_' . $limit;
	$ids = get_transient( $key );
	if ( ! is_array( $ids ) ) {
		$args = array( 'orderby' => 'comment_count', 'order' => 'DESC', 'posts_per_page' => $limit, 'fields' => 'ids', 'no_found_rows' => true, 'ignore_sticky_posts' => true );
		if ( $year ) {
			$args['year'] = $year;
		}
		$ids = get_posts( $args );
		set_transient( $key, $ids, DAY_IN_SECONDS );
	}
	return $ids;
}
