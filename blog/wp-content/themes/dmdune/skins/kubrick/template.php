<?php
/**
 * Kubrick DM (2006) and dmbrickv2 (2007), rebuilt: both grew from WordPress's Kubrick, so one
 * template serves both and the stylesheets tell them apart (2006: one right sidebar, wide post
 * pages; 2007: three columns, with Current / Related / Random beside posts). See chrome/skins.php.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

$dmkb_v2 = 'dmbrickv2' === dmc_skin();

function dmkb_postmeta( WP_Post $post, bool $tags ): string {
	$cats = get_the_category( $post->ID );
	$n    = (int) get_comments_number( $post );
	$out  = 'Posted in ' . implode( ', ', array_map( static fn( $c ) => '<a href="' . esc_url( get_category_link( $c ) ) . '">' . esc_html( $c->name ) . '</a>', $cats ) );
	$out .= ' <strong>|</strong> <a href="' . esc_url( get_comments_link( $post ) ) . '">' . esc_html( $n ? sprintf( _n( '%s Comment', '%s Comments', $n, 'dmdune' ), number_format_i18n( $n ) ) : 'No Comments' ) . ' &#187;</a>';
	if ( $tags && get_the_tags( $post->ID ) ) {
		$out .= '<br>Tagged ' . implode( ', ', array_map( static fn( $t ) => '<a href="' . esc_url( get_tag_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>', get_the_tags( $post->ID ) ) );
	}
	return '<p class="kb-postmeta">' . $out . '</p>';
}

/**
 * A post as the home page and lists showed it: title, date, text (up to the <!--more--> mark on
 * home, a summary in lists) and the "続きを読む continue" button.
 */
function dmkb_post( WP_Post $post, bool $full, bool $tags ): string {
	$GLOBALS['post'] = $post; // phpcs:ignore
	setup_postdata( $post );
	$link = get_permalink( $post );
	if ( $full ) {
		$more = false !== strpos( $post->post_content, '<!--more' );
		$body = apply_filters( 'the_content', get_the_content( '' ) ); // phpcs:ignore
	} else {
		$more = true;
		$img  = dmdune_first_image( $post );
		$body = ( $img ? '<p class="kb-thumb"><a href="' . esc_url( $link ) . '"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"></a></p>' : '' ) . '<p>' . esc_html( dmc_summary( preg_replace( '#<(p|div|span)\b[^>]*class=["\'][^"\']*\bcaption\b[^>]*>.*?</\1>#is', '', $post->post_content ), 300 ) ) . '</p>';
	}
	return sprintf(
		'<article class="kb-post"><h2 class="kb-title"><a href="%1$s" rel="bookmark">%2$s</a></h2><small class="kb-date">%3$s</small><div class="kb-entry">%4$s%5$s</div>%6$s</article>',
		esc_url( $link ),
		esc_html( wp_strip_all_tags( get_the_title( $post ) ) ),
		esc_html( get_the_date( 'F jS, Y', $post ) ),
		$body,
		$more ? '<p class="kb-continue"><a href="' . esc_url( $link ) . '#more-' . (int) $post->ID . '" aria-label="Continue reading ' . esc_attr( wp_strip_all_tags( get_the_title( $post ) ) ) . '"><span lang="ja">続きを読む</span> continue</a></p>' : '',
		dmkb_postmeta( $post, $tags )
	);
}

function dmkb_comment( WP_Comment $comment, array $args, int $depth ): void {
	static $n = 0;
	$n++;
	$page    = max( 1, (int) get_query_var( 'cpage' ) );
	$per     = (int) get_option( 'comments_per_page' );
	$number  = get_option( 'page_comments' ) ? ( $page - 1 ) * $per + $n : $n;
	$country = dmdune_comment_country( $comment );
	$flag    = '';
	if ( $country && file_exists( get_template_directory() . '/assets/flags/' . $country['code'] . '.svg' ) ) {
		$flag = sprintf( ' <img class="kb-flag" src="%s" alt="%s" title="%s" width="16" height="12" loading="lazy">', esc_url( get_template_directory_uri() . '/assets/flags/' . $country['code'] . '.svg' ), esc_attr( $country['name'] ), esc_attr( $country['name'] ) );
	}
	printf(
		'<li id="comment-%1$d" class="%2$s"><span class="kb-comment__no">%3$d</span>%4$s<cite>%5$s</cite>%6$s <span class="kb-says">Says:</span><br><small class="kb-comment__date"><a href="#comment-%1$d">%7$s</a></small><div class="kb-comment__text">%8$s</div>',
		(int) $comment->comment_ID,
		esc_attr( implode( ' ', get_comment_class( 0 === $n % 2 ? 'kb-comment' : 'kb-comment alt', $comment ) ) ),
		$number,
		get_avatar( $comment, 32, '', '', array( 'loading' => 'lazy', 'class' => 'kb-avatar' ) ),
		get_comment_author_link( $comment ),
		$flag,
		esc_html( get_comment_date( 'F jS, Y \a\t g:i a', $comment ) ),
		apply_filters( 'comment_text', get_comment_text( $comment ), $comment, $args ) // phpcs:ignore -- core comment filters
	);
}

function dmkb_pager(): string {
	$links = paginate_links( array( 'type' => 'array', 'prev_text' => '&laquo; Newer Entries', 'next_text' => 'Older Entries &raquo;', 'mid_size' => 1 ) );
	return $links ? '<nav class="kb-navigation" aria-label="Pages">' . implode( ' ', $links ) . '</nav>' : '';
}

$cfg  = dmdune_chrome_config();
$side = array();
ob_start();

if ( is_singular() ) {
	the_post();
	$post = get_post();
	if ( 'post' === $post->post_type ) {
		$prev = get_previous_post();
		$next = get_next_post();
		echo '<nav class="kb-postnav" aria-label="Previous and next post">';
		echo $prev ? '<div><b>&laquo; Previous Post</b><br><a href="' . esc_url( get_permalink( $prev ) ) . '">' . esc_html( get_the_title( $prev ) ) . '</a></div>' : '<div></div>'; // phpcs:ignore
		echo $next ? '<div class="kb-postnav__next"><b>Next Post &raquo;</b><br><a href="' . esc_url( get_permalink( $next ) ) . '">' . esc_html( get_the_title( $next ) ) . '</a></div>' : '<div></div>'; // phpcs:ignore
		echo '</nav>';
	}
	?>
	<article class="kb-post kb-post--single">
		<h1 class="kb-title"><?php the_title(); ?></h1>
		<?php if ( 'post' === $post->post_type ) : ?>
			<small class="kb-date"><?php echo esc_html( get_the_date( 'F jS, Y' ) ); ?></small>
			<?php echo dmdune_era_post_link( get_post() ); // phpcs:ignore ?>
		<?php endif; ?>
		<div class="kb-entry"><?php the_content(); ?></div>
		<?php if ( 'post' === $post->post_type ) : ?>
			<p class="kb-postmeta kb-postmeta--single">
				This entry was posted on <?php echo esc_html( get_the_date( 'l, F jS, Y' ) ); ?> at <?php echo esc_html( get_the_time( 'g:i a' ) ); ?>
				and is filed under <?php the_category( ', ' ); ?>.<?php the_tags( ' Tagged ', ', ', '.' ); ?>
				You can follow any responses to this entry through the <a href="<?php echo esc_url( get_post_comments_feed_link() ); ?>">RSS 2.0</a> feed.
				<?php echo comments_open() ? 'You can <a href="#respond">leave a response</a>.' : 'Responses are currently closed.'; ?>
			</p>
		<?php endif; ?>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		$comments = get_comments( array( 'post_id' => $post->ID, 'status' => 'approve', 'order' => 'ASC' ) );
		$total    = count( $comments );
		$per      = (int) get_option( 'comments_per_page' );
		$pages    = get_option( 'page_comments' ) ? max( 1, (int) ceil( $total / max( 1, $per ) ) ) : 1;
		$cpage    = min( $pages, max( 1, (int) get_query_var( 'cpage' ) ) );
		echo '<section class="kb-comments" id="comments">';
		if ( $total ) {
			printf( '<h3 class="kb-comments__title">%s to &#8220;%s&#8221;</h3>', esc_html( sprintf( _n( '%s Response', '%s Responses', $total, 'dmdune' ), number_format_i18n( $total ) ) ), esc_html( get_the_title() ) );
			$nav = $pages > 1 ? '<nav class="kb-navigation kb-navigation--comments" aria-label="Comment pages">Pages: ' . paginate_comments_links( array( 'echo' => false, 'total' => $pages, 'current' => $cpage, 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ) . '</nav>' : '';
			echo $nav; // phpcs:ignore
			echo '<ol class="kb-commentlist">';
			wp_list_comments( array( 'callback' => 'dmkb_comment', 'style' => 'ol', 'per_page' => $pages > 1 ? $per : 0, 'page' => $cpage, 'max_depth' => 1 ), $comments );
			echo '</ol>';
			echo $nav; // phpcs:ignore
		}
		if ( comments_open() ) {
			comment_form( array( 'title_reply' => 'Leave a Reply', 'class_container' => 'kb-respond' ) );
		}
		echo '</section>';
	}
	if ( $dmkb_v2 && 'post' === $post->post_type ) {
		$cats   = get_the_category( $post->ID );
		$tags   = get_the_tags( $post->ID ) ?: array();
		$cur    = '<dl class="kb-current"><dt>Posted</dt><dd>' . esc_html( get_the_time( 'g:i a' ) . ' on ' . get_the_date( 'l F jS, Y' ) ) . '</dd>';
		$cur   .= '<dt>Categories</dt><dd>' . implode( ', ', array_map( static fn( $c ) => '<a href="' . esc_url( get_category_link( $c ) ) . '">' . esc_html( $c->name ) . '</a>', $cats ) ) . '</dd>';
		$cur   .= $tags ? '<dt>Tags</dt><dd>' . implode( ', ', array_map( static fn( $t ) => '<a href="' . esc_url( get_tag_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>', $tags ) ) . '</dd>' : '';
		$cur   .= '<dt>Response</dt><dd><a href="#comments">Comments (' . (int) get_comments_number( $post ) . ')</a></dd></dl>';
		$side[] = array( 'Current', $cur );
		$rel    = dmdune_related_posts( $post, 5 );
		if ( $rel ) {
			$side[] = array( 'Related', '<ul class="kb-links">' . implode( '', array_map( static fn( $p ) => '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>', $rel ) ) . '</ul>' );
		}
	}
} else {
	if ( is_search() ) {
		echo '<h2 class="kb-pagetitle">Search Results for &#8220;' . esc_html( get_search_query() ) . '&#8221;</h2>'; // phpcs:ignore
	} elseif ( is_tag() ) {
		echo '<h2 class="kb-pagetitle">Posts Tagged &#8216;' . esc_html( single_tag_title( '', false ) ) . '&#8217;</h2>'; // phpcs:ignore
	} elseif ( is_category() ) {
		echo '<h2 class="kb-pagetitle">Archive for the &#8216;' . esc_html( single_cat_title( '', false ) ) . '&#8217; Category</h2>'; // phpcs:ignore
	} elseif ( is_404() ) {
		echo '<h2 class="kb-pagetitle">Error 404 - Not Found</h2>'; // phpcs:ignore
	} elseif ( is_archive() ) {
		echo '<h2 class="kb-pagetitle">Archive for ' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</h2>'; // phpcs:ignore
	}
	$home = is_front_page() || is_home();
	$any  = false;
	while ( have_posts() ) {
		the_post();
		$any = true;
		echo dmkb_post( get_post(), $home, $dmkb_v2 ); // phpcs:ignore
	}
	if ( ! $any ) {
		echo '<p class="kb-center">Sorry, but you are looking for something that isn&#8217;t here.</p>'; // phpcs:ignore
	}
	echo dmkb_pager(); // phpcs:ignore
}
$main = ob_get_clean();

// Archive and Tags are Dune screens built from blocks; here they are Kubrick's lists again.
if ( is_page( 'archives' ) ) {
	$years = '';
	foreach ( array_reverse( dmdune_month_counts(), true ) as $year => $months ) {
		$cells = '';
		foreach ( $months as $m => $n ) {
			$cells .= sprintf( '<li><a href="%s">%s %d</a> (%d)</li>', esc_url( get_month_link( $year, $m ) ), esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ), $year, $n );
		}
		$years .= '<h3>' . (int) $year . '</h3><ul class="kb-links">' . $cells . '</ul>';
	}
	$main = '<article class="kb-post kb-post--single"><h1 class="kb-title">Archives by Month</h1><div class="kb-entry kb-archives">' . $years . '</div></article>';
} elseif ( is_page( 'tag' ) ) {
	$main = '<article class="kb-post kb-post--single"><h1 class="kb-title">Taglist</h1><div class="kb-entry kb-tagcloud">' . wp_tag_cloud( array( 'echo' => false, 'number' => 0, 'smallest' => 9, 'largest' => 24, 'unit' => 'px' ) ) . '</div></article>';
}

// Sidebars: categories and years everywhere (left in 2007, right in 2006); random entries in 2007.
$cats   = wp_list_categories( array( 'echo' => false, 'title_li' => '', 'show_count' => false, 'hide_empty' => true ) );
$years  = '';
foreach ( array_reverse( dmdune_year_counts(), true ) as $year => $n ) {
	$years .= '<li><a href="' . esc_url( get_year_link( $year ) ) . '">' . (int) $year . '</a> (' . (int) $n . ')</li>';
}
$col    = $dmkb_v2 ? 'left' : 'right';
$side[] = array( 'Categories', '<ul class="kb-links">' . $cats . '</ul>', '', $col );
$side[] = array( 'Archives', '<ul class="kb-links">' . $years . '</ul>', '', $col );
if ( $dmkb_v2 ) {
	$random = get_posts( array( 'orderby' => 'rand', 'posts_per_page' => 3, 'no_found_rows' => true ) );
	$html   = '';
	foreach ( $random as $p ) {
		$img   = dmdune_first_image( $p );
		$html .= '<a class="kb-random" href="' . esc_url( get_permalink( $p ) ) . '" title="' . esc_attr( get_the_title( $p ) ) . '">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '<span>' . esc_html( get_the_title( $p ) ) . '</span>' ) . '</a>';
	}
	$side[] = array( 'Random Entries', $html );
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'kb-body' ); ?>>
<?php wp_body_open(); ?>
<?php echo dmc_header_html( $cfg + array( 'tagline' => $dmkb_v2 ? 'フリクリ' : 'エッチなのはいけないと思います！' ) ); // phpcs:ignore ?>
<main id="dm-main" class="bk-main kb-main">
<?php echo $main; // phpcs:ignore ?>
</main>
<?php echo dmc_footer_html( $cfg + array( 'side' => $side ) ); // phpcs:ignore ?>
<?php wp_footer(); ?>
</body>
</html>
