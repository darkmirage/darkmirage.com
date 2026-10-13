<?php
/**
 * DarkMirage Blocks (2007–2016), rebuilt: one template for every blog screen while the reader has
 * chosen the Blocks skin (see chrome/skins.php). Layouts follow the 2008 theme: a big "Latest
 * entry" card beside "Previous entry", a grid of short entries, rounded white post cards and grey
 * sidebar blocks. Styles: assets/skins/blocks/skin.css.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text opening of a post (the old posts have no excerpts).
 */
function dmbk_excerpt( WP_Post $post, int $chars = 220 ): string {
	return dmc_summary( has_excerpt( $post ) ? $post->post_excerpt : preg_replace( '#<(p|div|span)\b[^>]*class=["\'][^"\']*\bcaption\b[^>]*>.*?</\1>#is', '', $post->post_content ), $chars );
}

function dmbk_posted_in( WP_Post $post ): string {
	$cats = get_the_category( $post->ID );
	$n    = (int) get_comments_number( $post );
	return sprintf(
		'Posted in %s | <a class="bk-comments-link" href="%s">%s</a>',
		$cats ? '<a href="' . esc_url( get_category_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a>' : 'Uncategorized',
		esc_url( get_comments_link( $post ) ),
		esc_html( $n ? sprintf( _n( '%s Comment', '%s Comments', $n, 'dmdune' ), number_format_i18n( $n ) ) : 'No Comments' ) . ' &raquo;'
	);
}

/**
 * A short entry tile: picture, title, date, opening words. The grid on home, lists and search.
 */
function dmbk_short( WP_Post $post ): string {
	$img = dmdune_first_image( $post );
	return sprintf(
		'<article class="bk-short"><a class="bk-short__thumb%1$s" href="%2$s" tabindex="-1" aria-hidden="true">%3$s</a><h3 class="bk-short__title"><a href="%2$s">%4$s</a></h3><p class="bk-date">%5$s</p><p class="bk-short__text">%6$s</p></article>',
		$img ? '' : ' is-empty',
		esc_url( get_permalink( $post ) ),
		$img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">' : '<span>' . esc_html( wp_date( 'Y', get_post_timestamp( $post ) ) ) . '</span>',
		esc_html( wp_strip_all_tags( get_the_title( $post ) ) ),
		esc_html( get_the_date( 'F jS, Y', $post ) ),
		esc_html( dmbk_excerpt( $post, 140 ) )
	);
}

function dmbk_pager(): string {
	$links = paginate_links( array( 'type' => 'array', 'prev_text' => '&laquo; Newer', 'next_text' => 'Older &raquo;', 'mid_size' => 1 ) );
	return $links ? '<nav class="bk-pager" aria-label="Pages">' . implode( ' ', $links ) . '</nav>' : '';
}

/**
 * One comment in the Blocks list: avatar box, name, flag, date, text, big grey number.
 */
function dmbk_comment( WP_Comment $comment, array $args, int $depth ): void {
	static $n = 0;
	$n++;
	$page    = max( 1, (int) get_query_var( 'cpage' ) );
	$per     = (int) get_option( 'comments_per_page' );
	$number  = get_option( 'page_comments' ) ? ( $page - 1 ) * $per + $n : $n;
	$country = dmdune_comment_country( $comment );
	$flag    = '';
	if ( $country && file_exists( get_template_directory() . '/assets/flags/' . $country['code'] . '.svg' ) ) {
		$flag = sprintf( ' <img class="bk-flag" src="%s" alt="%s" title="%s" width="16" height="12" loading="lazy">', esc_url( get_template_directory_uri() . '/assets/flags/' . $country['code'] . '.svg' ), esc_attr( $country['name'] ), esc_attr( $country['name'] ) );
	}
	printf(
		'<li id="comment-%1$d" class="%2$s"><span class="bk-comment__no">%3$d</span><div class="bk-comment__avatar">%4$s</div><div class="bk-comment__body"><p class="bk-comment__who"><cite>%5$s</cite>%6$s <span class="bk-date">%7$s</span></p><div class="bk-comment__text">%8$s</div></div>',
		(int) $comment->comment_ID,
		esc_attr( implode( ' ', get_comment_class( 1 === $n % 2 ? 'bk-comment' : 'bk-comment alt', $comment ) ) ),
		$number,
		get_avatar( $comment, 64, '', '', array( 'loading' => 'lazy' ) ),
		get_comment_author_link( $comment ),
		$flag,
		esc_html( get_comment_date( 'F jS, Y \a\t g:i a', $comment ) ),
		apply_filters( 'comment_text', get_comment_text( $comment ), $comment, $args ) // phpcs:ignore -- core comment filters (kses, autop)
	);
}

$cfg  = dmdune_chrome_config();
$side = array();
ob_start();

if ( is_front_page() || is_home() ) {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( 1 === $paged && have_posts() ) {
		the_post();
		$latest = get_post();
		?>
		<div class="bk-lead">
			<article class="bk-card bk-card--latest">
				<p class="bk-label bk-label--card">Latest entry</p>
				<h2 class="bk-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="bk-date"><?php echo esc_html( get_the_date( 'F jS, Y' ) ); ?></p>
				<div class="bk-prose"><?php the_content( '' ); ?></div>
				<p class="bk-postmeta"><?php echo wp_kses_post( dmbk_posted_in( $latest ) ); ?><?php the_tags( '<br>Tagged ', ', ' ); ?></p>
			</article>
			<?php
			if ( have_posts() ) :
				the_post();
				?>
				<article class="bk-prev">
					<p class="bk-label bk-label--plain">Previous entry</p>
					<h2 class="bk-prev__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="bk-date"><?php echo esc_html( get_the_date( 'F jS, Y' ) ); ?></p>
					<p class="bk-postmeta"><?php echo wp_kses_post( dmbk_posted_in( get_post() ) ); ?></p>
					<p class="bk-prev__text"><?php echo esc_html( dmbk_excerpt( get_post(), 260 ) ); ?></p>
					<a class="bk-more" href="<?php the_permalink(); ?>">More <span aria-hidden="true">›</span></a>
				</article>
			<?php endif; ?>
		</div>
		<?php
	}
	$tiles = '';
	while ( have_posts() ) {
		the_post();
		$tiles .= dmbk_short( get_post() );
	}
	if ( $tiles ) {
		echo '<section class="bk-section"><h2 class="bk-label bk-label--plain">' . ( 1 === $paged ? 'Recent entries' : 'Entries · page ' . (int) $paged ) . '</h2><div class="bk-shorts">' . $tiles . '</div></section>'; // phpcs:ignore
	}
	echo dmbk_pager(); // phpcs:ignore
	if ( 1 === $paged ) {
		$random = get_posts( array( 'orderby' => 'rand', 'posts_per_page' => 4, 'no_found_rows' => true ) );
		echo '<section class="bk-section"><h2 class="bk-label bk-label--plain">Random entries</h2><div class="bk-shorts">' . implode( '', array_map( 'dmbk_short', $random ) ) . '</div></section>'; // phpcs:ignore
	}
	// The 2008 sidebar listed the latest comments; on an archive that means 2013 trolls, so it
	// shows the most-discussed entries instead.
	$items = '';
	foreach ( dmdune_loudest( null, 8 ) as $id ) {
		$items .= sprintf( '<li><a href="%s">%s</a> <span>%d</span></li>', esc_url( get_permalink( $id ) ), esc_html( wp_strip_all_tags( get_the_title( $id ) ) ), (int) get_comments_number( $id ) );
	}
	$side[] = array( 'Popular', '<ul class="bk-recent-comments bk-popular">' . $items . '</ul>' );
} elseif ( is_singular() ) {
	the_post();
	$post  = get_post();
	$image = dmdune_first_image( $post );
	?>
	<article class="bk-card bk-card--single">
		<h1 class="bk-card__title"><?php the_title(); ?></h1>
		<?php if ( 'post' === $post->post_type ) : ?><p class="bk-date"><?php echo esc_html( get_the_date( 'F jS, Y' ) ); ?></p><?php endif; ?>
		<div class="bk-prose"><?php the_content(); ?></div>
		<?php if ( 'post' === $post->post_type ) : ?>
			<p class="bk-postmeta"><?php echo wp_kses_post( dmbk_posted_in( $post ) ); ?><?php the_tags( '<br>Tagged ', ', ' ); ?></p>
		<?php endif; ?>
	</article>
	<?php
	if ( 'post' === $post->post_type ) {
		$prev = get_previous_post();
		$next = get_next_post();
		if ( $prev || $next ) {
			echo '<nav class="bk-postnav" aria-label="Previous and next post">';
			echo $prev ? '<a class="bk-postnav__prev" href="' . esc_url( get_permalink( $prev ) ) . '"><span>&laquo; Previous post</span>' . esc_html( get_the_title( $prev ) ) . '</a>' : '<span></span>'; // phpcs:ignore
			echo $next ? '<a class="bk-postnav__next" href="' . esc_url( get_permalink( $next ) ) . '"><span>Next post &raquo;</span>' . esc_html( get_the_title( $next ) ) . '</a>' : '<span></span>'; // phpcs:ignore
			echo '</nav>';
		}
		$related = dmdune_related_posts( $post, 4 );
		if ( $related ) {
			echo '<section class="bk-section"><h2 class="bk-label bk-label--plain">Related entries</h2><div class="bk-shorts">' . implode( '', array_map( 'dmbk_short', $related ) ) . '</div></section>'; // phpcs:ignore
		}
	}
	if ( comments_open() || get_comments_number() ) {
		$comments = get_comments( array( 'post_id' => $post->ID, 'status' => 'approve', 'order' => 'ASC' ) );
		echo '<section class="bk-comments" id="comments"><h2 class="bk-label bk-label--plain">Comments <span>' . (int) count( $comments ) . '</span></h2>'; // phpcs:ignore
		$per   = (int) get_option( 'comments_per_page' );
		$pages = get_option( 'page_comments' ) ? max( 1, (int) ceil( count( $comments ) / max( 1, $per ) ) ) : 1;
		$cpage = min( $pages, max( 1, (int) get_query_var( 'cpage' ) ) );
		echo '<ol class="bk-commentlist">';
		wp_list_comments(
			array(
				'callback'  => 'dmbk_comment',
				'style'     => 'ol',
				'per_page'  => $pages > 1 ? $per : 0,
				'page'      => $cpage,
				'max_depth' => 1,
			),
			$comments
		);
		echo '</ol>';
		if ( $pages > 1 ) {
			echo '<nav class="bk-pager" aria-label="Comment pages">' . paginate_comments_links( array( 'echo' => false, 'total' => $pages, 'current' => $cpage, 'prev_text' => '&laquo; Earlier', 'next_text' => 'Later &raquo;' ) ) . '</nav>'; // phpcs:ignore
		}
		if ( comments_open() ) {
			comment_form( array( 'title_reply' => 'Leave a comment', 'class_container' => 'bk-respond' ) );
		}
		echo '</section>';
	}
	if ( $image ) {
		$side[] = array( 'Current', '<img class="bk-current" src="' . esc_url( $image ) . '" alt="">' );
	}
	if ( 'post' === $post->post_type ) {
		$cats   = get_the_category( $post->ID );
		$tags   = get_the_tags( $post->ID ) ?: array();
		$meta   = '<h3>posted</h3><p>' . esc_html( get_the_date( 'Y' ) ) . '<br>' . esc_html( strtolower( get_the_date( 'F jS' ) ) ) . '<br>' . esc_html( get_the_time( 'g:i a' ) ) . '</p>';
		$meta  .= $cats ? '<h3>category</h3><p>' . implode( '<br>', array_map( static fn( $t ) => '<a href="' . esc_url( get_category_link( $t ) ) . '">' . esc_html( strtolower( $t->name ) ) . '</a>', $cats ) ) . '</p>' : '';
		$meta  .= $tags ? '<h3>tags</h3><p>' . implode( '<br>', array_map( static fn( $t ) => '<a href="' . esc_url( get_tag_link( $t ) ) . '">' . esc_html( strtolower( $t->name ) ) . '</a>', $tags ) ) . '</p>' : '';
		$meta  .= '<h3>discussion</h3><p><a href="#comments">comments (' . (int) get_comments_number( $post ) . ')</a><br><a href="' . esc_url( get_permalink( $post ) ) . '">permalink</a></p>';
		$side[] = array( 'Meta', '<div class="bk-meta">' . $meta . '</div>' );
	}
} else {
	if ( is_search() ) {
		$heading = 'Search results for <span>“' . esc_html( get_search_query() ) . '”</span>';
	} elseif ( is_tag() ) {
		$heading = 'Entries tagged <span>' . esc_html( single_tag_title( '', false ) ) . '</span>';
	} elseif ( is_category() ) {
		$heading = 'Archive for <span>' . esc_html( single_cat_title( '', false ) ) . '</span>';
	} elseif ( is_404() ) {
		$heading = 'Not <span>found</span>';
	} else {
		$heading = 'Archive for <span>' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</span>';
	}
	echo '<header class="bk-archive-title"><h1>' . $heading . '</h1></header>'; // phpcs:ignore
	$tiles = '';
	while ( have_posts() ) {
		the_post();
		$tiles .= dmbk_short( get_post() );
	}
	echo $tiles ? '<div class="bk-shorts bk-shorts--wide">' . $tiles . '</div>' : '<div class="bk-card"><p>Nothing here. Try a search, or the archive.</p></div>'; // phpcs:ignore
	echo dmbk_pager(); // phpcs:ignore
}

$main = ob_get_clean();

// Archive and Tags are Dune screens built from blocks; in Blocks they are the 2008 lists again.
if ( is_page( 'archives' ) ) {
	$years = '';
	foreach ( array_reverse( dmdune_month_counts(), true ) as $year => $months ) {
		$cells = '';
		foreach ( $months as $m => $n ) {
			$cells .= sprintf( '<li><a href="%s">%s</a> <span>(%d)</span></li>', esc_url( get_month_link( $year, $m ) ), esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ), $n );
		}
		$years .= '<section class="bk-year"><h2>' . (int) $year . '</h2><ul>' . $cells . '</ul></section>';
	}
	$main = '<article class="bk-card bk-card--single"><h1 class="bk-card__title">Archives</h1><div class="bk-years">' . $years . '</div></article>';
} elseif ( is_page( 'tag' ) ) {
	$main = '<article class="bk-card bk-card--single"><h1 class="bk-card__title">Tags</h1><div class="bk-tagcloud">' . wp_tag_cloud( array( 'echo' => false, 'number' => 0, 'smallest' => 9, 'largest' => 26, 'unit' => 'px' ) ) . '</div></article>';
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'bk-body' ); ?>>
<?php wp_body_open(); ?>
<?php echo dmc_header_html( $cfg ); // phpcs:ignore ?>
<main id="dm-main" class="bk-main">
<?php echo $main; // phpcs:ignore ?>
</main>
<?php echo dmc_footer_html( $cfg + array( 'side' => $side ) ); // phpcs:ignore ?>
<?php wp_footer(); ?>
</body>
</html>
