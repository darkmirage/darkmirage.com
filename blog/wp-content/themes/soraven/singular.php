<?php
/**
 * A post or page.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
?>
<main id="main">
	<article <?php post_class( 'entry' ); ?>>
		<header class="entry-header">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="entry-subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<?php
			if ( is_single() ) {
				soraven_post_meta( true );
			}
			?>
		</header>
		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</article>

	<?php if ( is_single() ) : ?>
		<p class="colophon" aria-hidden="true"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/brand/raven-logo.webp' ) ); ?>" width="36" height="36" alt=""></p>
		<nav class="post-nav" aria-label="<?php esc_attr_e( 'More essays', 'soraven' ); ?>">
			<?php previous_post_link( '<div class="post-nav-prev"><span class="post-nav-label">' . esc_html__( 'Earlier', 'soraven' ) . '</span>%link</div>' ); ?>
			<?php next_post_link( '<div class="post-nav-next"><span class="post-nav-label">' . esc_html__( 'Later', 'soraven' ) . '</span>%link</div>' ); ?>
		</nav>
	<?php endif; ?>

	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
	?>
</main>
<?php
get_footer();
