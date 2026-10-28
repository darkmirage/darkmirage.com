<?php
/**
 * Comments and the comment form.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			/* translators: %s: number of comments */
			printf( esc_html( _n( '%s response', '%s responses', get_comments_number(), 'soraven' ) ), esc_html( number_format_i18n( get_comments_number() ) ) );
			?>
		</h2>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'callback'   => 'soraven_comment',
					'style'      => 'ol',
					'short_ping' => true,
				)
			);
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'        => __( 'Respond', 'soraven' ),
			'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'  => '</h2>',
			'label_submit'       => __( 'Post response', 'soraven' ),
		)
	);
	?>
</section>
