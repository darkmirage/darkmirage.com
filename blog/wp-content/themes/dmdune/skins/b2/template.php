<?php
/**
 * DarkMirage's Weblog (b2, 2003–2005), rebuilt: one template for every blog screen while the
 * reader has picked the 2003 era (see chrome/skins.php). As layout2b.css drew it: a sky-blue page,
 * the Aegis banner in a blue band, a left "menu" column of ruled lists, and posts grouped under
 * dd.mm.yy date tabs as "Title [Category] - Author at hh:mm:ss". b2 printed whole posts on every
 * list, so this does too (search results get a summary). Styles: assets/skins/b2/skin.css.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

/**
 * The dd.mm.yy tab b2 printed above the first post of each day, or '' within the same day.
 */
function dmb2_day( WP_Post $post, ?string &$last ): string {
	$day = get_the_date( 'Y-m-d', $post );
	if ( $day === $last ) {
		return '';
	}
	$last = $day;
	return '<h2 class="b2-day"><time datetime="' . esc_attr( $day ) . '">' . esc_html( get_the_date( 'd.m.y', $post ) ) . '</time></h2>';
}

function dmb2_comments_link( WP_Post $post ): string {
	$n = (int) get_comments_number( $post );
	// b2's own wording: "comments ?" until someone answered.
	$text = $n ? sprintf( _n( '%s comment', '%s comments', $n, 'dmdune' ), number_format_i18n( $n ) ) : 'comments ?';
	return '<p class="b2-more"><a href="' . esc_url( get_comments_link( $post ) ) . '">' . esc_html( $text ) . '</a></p>';
}

/**
 * One story: the title line, then the text. $mode: 'full' (as b2 listed them), 'summary' (search)
 * or 'single'. A password-protected post shows only its title until unlocked on its own page.
 */
function dmb2_story( WP_Post $post, string $mode ): string {
	$GLOBALS['post'] = $post; // phpcs:ignore
	setup_postdata( $post );
	$link   = get_permalink( $post );
	$cats   = get_the_category( $post->ID );
	$cat    = $cats ? '<a class="b2-cat" href="' . esc_url( get_category_link( $cats[0] ) ) . '">[' . esc_html( $cats[0]->name ) . ']</a>' : '';
	$author = get_the_author_meta( 'display_name', (int) $post->post_author ) ?: 'DarkMirage';
	$title  = wp_strip_all_tags( get_the_title( $post ) );
	$head   = 'single' === $mode
		? '<h1 class="b2-title">' . esc_html( $title ) . '</h1>'
		: '<h3 class="b2-title"><a href="' . esc_url( $link ) . '" rel="bookmark">' . esc_html( $title ) . '</a></h3>';
	$line   = sprintf(
		'<div class="b2-storytitle">%1$s %2$s <span class="b2-dash">-</span> <span class="b2-author">%3$s</span> <span class="b2-at">at</span> <a class="b2-time" href="%4$s"><time datetime="%5$s">%6$s</time></a></div>',
		$head,
		$cat,
		esc_html( $author ),
		esc_url( $link ),
		esc_attr( get_the_date( 'c', $post ) ),
		esc_html( get_the_time( 'H:i:s', $post ) )
	);
	if ( post_password_required( $post ) ) {
		$body = 'single' === $mode
			? get_the_password_form( $post )
			: '<p class="b2-locked">This entry is password protected. <a href="' . esc_url( $link ) . '">Enter the password</a> to read it.</p>';
	} elseif ( 'summary' === $mode ) {
		$body = '<p>' . esc_html( dmc_summary( $post->post_content, 300 ) ) . '</p>';
	} else {
		$body = apply_filters( 'the_content', get_the_content( 'more...' ) ); // phpcs:ignore -- core content filters
	}
	return sprintf(
		'<article class="b2-story" id="post-%1$d">%2$s<div class="b2-content">%3$s</div>%4$s</article>',
		(int) $post->ID,
		$line,
		$body,
		'single' === $mode ? '' : dmb2_comments_link( $post )
	);
}

function dmb2_comment( WP_Comment $comment, array $args, int $depth ): void {
	$url = get_comment_author_url( $comment );
	printf(
		'<li id="comment-%1$d" class="%2$s"><p class="b2-comment__by"><b>%3$s</b> <span class="b2-at">said on</span> <a href="#comment-%1$d">%4$s</a></p><div class="b2-comment__text">%5$s</div>',
		(int) $comment->comment_ID,
		esc_attr( implode( ' ', get_comment_class( 'b2-comment', $comment ) ) ),
		$url ? '<a href="' . esc_url( $url ) . '" rel="external nofollow ugc">' . esc_html( get_comment_author( $comment ) ) . '</a>' : esc_html( get_comment_author( $comment ) ),
		esc_html( get_comment_date( 'd.m.y \a\t H:i:s', $comment ) ),
		apply_filters( 'comment_text', get_comment_text( $comment ), $comment, $args ) // phpcs:ignore -- core comment filters
	);
}

function dmb2_pager(): string {
	$links = paginate_links( array( 'type' => 'array', 'prev_text' => '&laquo; newer', 'next_text' => 'older &raquo;', 'mid_size' => 1 ) );
	return $links ? '<nav class="b2-pager" aria-label="Pages">' . implode( ' ', $links ) . '</nav>' : '';
}

$cfg  = array( 'note' => esc_html__( 'DarkMirage’s Weblog · 2003–2005, powered by b2 · Art: all rights reserved · Theme code: GPL-2.0', 'dmdune' ) ) + dmdune_chrome_config();
$side = array();
$last = null;
ob_start();

if ( is_singular() ) {
	the_post();
	$post = get_post();
	if ( 'post' === $post->post_type ) {
		echo dmb2_day( $post, $last ); // phpcs:ignore
		echo dmb2_story( $post, 'single' ); // phpcs:ignore
		echo dmdune_era_post_link( $post ); // phpcs:ignore
		$prev = get_previous_post();
		$next = get_next_post();
		if ( $prev || $next ) {
			echo '<nav class="b2-postnav" aria-label="Previous and next post">';
			echo $prev ? '<a href="' . esc_url( get_permalink( $prev ) ) . '">&laquo; ' . esc_html( get_the_title( $prev ) ) . '</a>' : '<span></span>'; // phpcs:ignore
			echo $next ? '<a class="b2-postnav__next" href="' . esc_url( get_permalink( $next ) ) . '">' . esc_html( get_the_title( $next ) ) . ' &raquo;</a>' : '<span></span>'; // phpcs:ignore
			echo '</nav>';
		}
	} else {
		echo '<article class="b2-story b2-story--page"><div class="b2-storytitle"><h1 class="b2-title">' . esc_html( get_the_title() ) . '</h1></div><div class="b2-content">'; // phpcs:ignore
		the_content();
		echo '</div></article>';
	}
	if ( ! post_password_required( $post ) && ( comments_open() || get_comments_number() ) ) {
		$comments = get_comments( array( 'post_id' => $post->ID, 'status' => 'approve', 'order' => 'ASC' ) );
		$total    = count( $comments );
		$per      = (int) get_option( 'comments_per_page' );
		$pages    = get_option( 'page_comments' ) ? max( 1, (int) ceil( $total / max( 1, $per ) ) ) : 1;
		$cpage    = min( $pages, max( 1, (int) get_query_var( 'cpage' ) ) );
		echo '<section class="b2-comments" id="comments">';
		printf( '<h2 class="b2-day">%s</h2>', esc_html( $total ? sprintf( _n( '%s comment', '%s comments', $total, 'dmdune' ), number_format_i18n( $total ) ) : 'comments' ) );
		if ( $total ) {
			$nav = $pages > 1 ? '<nav class="b2-pager" aria-label="Comment pages">' . paginate_comments_links( array( 'echo' => false, 'total' => $pages, 'current' => $cpage, 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ) . '</nav>' : '';
			echo $nav; // phpcs:ignore
			echo '<ol class="b2-commentlist">';
			wp_list_comments( array( 'callback' => 'dmb2_comment', 'style' => 'ol', 'per_page' => $pages > 1 ? $per : 0, 'page' => $cpage, 'max_depth' => 1 ), $comments );
			echo '</ol>';
			echo $nav; // phpcs:ignore
		} else {
			echo '<p class="b2-none">No comments yet.</p>';
		}
		if ( comments_open() ) {
			comment_form( array( 'title_reply' => 'Leave a comment', 'class_container' => 'b2-respond', 'label_submit' => 'say it!' ) );
		}
		echo '</section>';
	}
} else {
	$heading = '';
	if ( is_search() ) {
		$heading = 'search results for &#8220;' . esc_html( get_search_query() ) . '&#8221;';
	} elseif ( is_tag() ) {
		$heading = 'tag: ' . esc_html( single_tag_title( '', false ) );
	} elseif ( is_category() ) {
		$heading = 'category: ' . esc_html( single_cat_title( '', false ) );
	} elseif ( is_404() ) {
		$heading = 'error 404 - not found';
	} elseif ( is_archive() ) {
		$heading = 'archives: ' . esc_html( wp_strip_all_tags( get_the_archive_title() ) );
	}
	if ( $heading ) {
		echo '<h1 class="b2-pagetitle">' . $heading . '</h1>'; // phpcs:ignore -- escaped above
	}
	$any = false;
	while ( have_posts() ) {
		the_post();
		$any = true;
		echo dmb2_day( get_post(), $last ); // phpcs:ignore
		echo dmb2_story( get_post(), is_search() ? 'summary' : 'full' ); // phpcs:ignore
	}
	if ( ! $any ) {
		echo '<p class="b2-none">Sorry, no posts matched your criteria.</p>';
	}
	echo dmb2_pager(); // phpcs:ignore
}
$main = ob_get_clean();

// Archive and Tags are Dune screens built from blocks; here they are plain b2 lists again.
if ( is_page( 'archives' ) ) {
	$years = '';
	$counts = dmdune_month_counts();
	krsort( $counts );
	foreach ( $counts as $year => $months ) {
		krsort( $months );
		$cells = '';
		foreach ( $months as $m => $n ) {
			$cells .= sprintf( '<li><a href="%s">%s %d</a> (%d)</li>', esc_url( get_month_link( $year, $m ) ), esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ), $year, $n );
		}
		$years .= '<h2 class="b2-day">' . (int) $year . '</h2><ul class="b2-list">' . $cells . '</ul>';
	}
	$main = '<h1 class="b2-pagetitle">archives</h1><div class="b2-content b2-archives">' . $years . '</div>';
} elseif ( is_page( 'tag' ) ) {
	$main = '<h1 class="b2-pagetitle">tags</h1><div class="b2-content b2-tagcloud">' . wp_tag_cloud( array( 'echo' => false, 'number' => 0, 'smallest' => 9, 'largest' => 22, 'unit' => 'px' ) ) . '</div>';
}

// The menu column: b2 listed its two categories and every month; years keep that list short now.
$cats  = wp_list_categories( array( 'echo' => false, 'title_li' => '', 'show_count' => false, 'hide_empty' => true ) );
$years = '';
$counts = dmdune_year_counts();
krsort( $counts );
foreach ( $counts as $year => $n ) {
	$years .= '<li><a href="' . esc_url( get_year_link( $year ) ) . '">' . (int) $year . '</a> (' . (int) $n . ')</li>';
}
$side[] = array( 'categories', '<ul class="bk-nav">' . $cats . '</ul>' );
$side[] = array( 'archives', '<ul class="bk-nav">' . $years . '</ul>' );
$side[] = array(
	'links',
	'<ul class="bk-nav">'
	. '<li><a href="https://megatokyo.com/">MegaTokyo</a></li>'
	. '<li><a href="https://www.penny-arcade.com/">Penny Arcade</a></li>'
	. '<li><a href="https://pvponline.com/">PvPonline</a></li>'
	. '<li><a href="https://www.animelyrics.com/">Anime Lyrics</a></li>'
	. '</ul>',
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'b2-body' ); ?>>
<?php wp_body_open(); ?>
<?php echo dmc_header_html( $cfg ); // phpcs:ignore ?>
<main id="dm-main" class="bk-main b2-main">
<?php echo $main; // phpcs:ignore ?>
</main>
<?php echo dmc_footer_html( $cfg + array( 'side' => $side ) ); // phpcs:ignore ?>
<?php wp_footer(); ?>
</body>
</html>
