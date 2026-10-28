<?php
/**
 * Site footer.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="site-footer">
	<?php get_search_form(); ?>
	<p class="footer-line">
		© <?php echo esc_html( SORAVEN_BYLINE ); ?>
		<span class="sep" aria-hidden="true">·</span>
		<a href="<?php echo esc_url( get_feed_link() ); ?>"><?php esc_html_e( 'Feed', 'soraven' ); ?></a>
		<span class="sep" aria-hidden="true">·</span>
		<?php
		printf(
			/* translators: %s: link to darkmirage.com */
			esc_html__( 'Earlier writing at %s', 'soraven' ),
			'<a href="https://www.darkmirage.com/">DarkMirage</a>'
		);
		?>
	</p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
