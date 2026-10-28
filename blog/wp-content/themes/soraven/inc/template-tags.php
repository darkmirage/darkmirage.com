<?php
/**
 * Small template helpers.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Skyline banner, shown on every page. The header art is the brand, so it is never lazy-loaded.
 */
function soraven_skyline() {
	$dir = get_theme_file_uri( 'assets/brand/' );
	printf(
		'<img class="skyline" src="%1$s" srcset="%1$s 1600w, %2$s 2400w" sizes="100vw" width="2400" height="600" alt="%3$s" fetchpriority="high">',
		esc_url( $dir . 'skyline-1600.webp' ),
		esc_url( $dir . 'skyline-2400.webp' ),
		esc_attr__( 'A skyline running from San Francisco through Singapore to Tokyo, with a raven flying over the bay', 'soraven' )
	);
}

/**
 * Minutes to read, at a relaxed 230 words a minute.
 *
 * @param WP_Post|int|null $post Post.
 */
function soraven_reading_minutes( $post = null ) {
	$words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post ) ) );
	return max( 1, (int) round( $words / 230 ) );
}

/**
 * Date and reading time, set in small capitals; with the byline on a post's own page.
 *
 * @param bool $byline Lead with the author's name.
 */
function soraven_post_meta( $byline = false ) {
	$parts = array(
		sprintf( '<time datetime="%s">%s</time>', esc_attr( get_the_date( 'c' ) ), esc_html( get_the_date( 'j F Y' ) ) ),
		/* translators: %d: minutes */
		esc_html( sprintf( _n( '%d minute', '%d minutes', soraven_reading_minutes(), 'soraven' ), soraven_reading_minutes() ) ),
	);
	if ( $byline ) {
		array_unshift( $parts, '<span class="byline">' . esc_html( SORAVEN_BYLINE ) . '</span>' );
	}
	echo '<p class="post-meta">' . implode( '<span class="sep" aria-hidden="true"> · </span>', $parts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- parts escaped above.
}

/**
 * Card art: the post's featured image, or a colour field from the skyline's palette for posts without one.
 */
function soraven_card_art() {
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( 'medium_large', array( 'class' => 'card-image', 'sizes' => '(min-width: 1100px) 340px, (min-width: 700px) 45vw, 100vw' ) );
		return;
	}
	$tones = array( 'vermilion', 'navy', 'teal', 'mustard', 'bay' );
	printf(
		'<div class="card-field card-field--%s" aria-hidden="true"><span>%s</span></div>',
		esc_attr( $tones[ get_the_ID() % count( $tones ) ] ),
		esc_html( get_the_date( 'Y' ) )
	);
}

/**
 * Comment markup for wp_list_comments(). Comments brought over from Medium say so.
 *
 * @param WP_Comment $comment Comment.
 * @param array      $args    Arguments.
 * @param int        $depth   Depth.
 */
function soraven_comment( $comment, $args, $depth ) {
	$from_medium = (bool) get_comment_meta( $comment->comment_ID, '_medium_id', true );
	?>
	<li id="comment-<?php comment_ID(); ?>" <?php comment_class( $from_medium ? 'from-medium' : '' ); ?>>
		<article class="comment-body">
			<header class="comment-head">
				<span class="comment-author"><?php comment_author_link( $comment ); ?></span>
				<a class="comment-date" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><time datetime="<?php comment_time( 'c' ); ?>"><?php comment_date(); ?></time></a>
				<?php if ( $from_medium ) : ?>
					<span class="comment-origin"><?php esc_html_e( 'on Medium', 'soraven' ); ?></span>
				<?php endif; ?>
			</header>
			<?php if ( '0' === $comment->comment_approved ) : ?>
				<p class="comment-awaiting"><?php esc_html_e( 'Your comment is awaiting moderation.', 'soraven' ); ?></p>
			<?php endif; ?>
			<div class="comment-text"><?php comment_text(); ?></div>
			<?php
			comment_reply_link(
				array_merge(
					$args,
					array(
						'depth'     => $depth,
						'max_depth' => $args['max_depth'],
						'before'    => '<p class="comment-reply">',
						'after'     => '</p>',
					)
				)
			);
			?>
		</article>
	<?php
}
