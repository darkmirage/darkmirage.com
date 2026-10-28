<?php
/**
 * Home, archives, search and 404. The newest essay leads on the first page of the home; the rest are cards.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

get_header();
$soraven_lead = is_home() && ! is_paged();
?>
<main id="main" class="listing">
	<?php if ( is_search() ) : ?>
		<h1 class="listing-title">
			<?php
			/* translators: %s: search terms */
			printf( esc_html__( 'Searching for “%s”', 'soraven' ), esc_html( get_search_query() ) );
			?>
		</h1>
	<?php elseif ( is_archive() ) : ?>
		<h1 class="listing-title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
	<?php elseif ( is_404() ) : ?>
		<h1 class="listing-title"><?php esc_html_e( 'Nothing here. Perhaps one of these?', 'soraven' ); ?></h1>
		<?php
		query_posts( array( 'posts_per_page' => 6 ) ); // phpcs:ignore WordPress.WP.DiscouragedFunctions -- the 404 page has no main query to show.
		?>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="cards">
			<?php
			while ( have_posts() ) :
				the_post();
				$soraven_is_lead = $soraven_lead && 0 === $GLOBALS['wp_query']->current_post;
				?>
				<article <?php post_class( $soraven_is_lead ? 'card card--lead' : 'card' ); ?>>
					<a class="card-link" href="<?php the_permalink(); ?>">
						<?php soraven_card_art(); ?>
						<div class="card-body">
							<?php soraven_post_meta(); ?>
							<h2 class="card-title"><?php the_title(); ?></h2>
							<p class="card-excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
						</div>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		if ( ! is_404() ) {
			the_posts_pagination(
				array(
					'prev_text' => __( 'Newer', 'soraven' ),
					'next_text' => __( 'Older', 'soraven' ),
				)
			);
		}
		?>
	<?php else : ?>
		<p class="listing-empty"><?php esc_html_e( 'No posts found.', 'soraven' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
