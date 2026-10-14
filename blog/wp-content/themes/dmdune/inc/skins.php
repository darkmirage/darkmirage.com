<?php
/**
 * Era skins on the blog: while the reader has picked one (chrome/skins.php), every screen renders
 * through that era's template and stylesheet instead of the Dune block templates.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'template_include',
	static function ( string $template ): string {
		$file = get_template_directory() . '/skins/' . dmc_skin() . '/template.php';
		if ( dmc_skin() && ! is_feed() && ! is_embed() && file_exists( $file ) ) {
			return $file;
		}
		return $template;
	},
	99
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$css = dmc_skin_css( dmdune_chrome_config() );
		if ( $css ) {
			wp_enqueue_style( 'dmdune-skin', $css, array( 'dmdune' ), null );
		}
	},
	20
);

/**
 * A post's "see it as it looked then" switch. Posts imported from b2 (tagged _dm_b2_id) belong to
 * that era even on 2005-11-03, when b2's farewell post went up hours after WordPress's first.
 */
function dmdune_era_post_link( WP_Post $post ): string {
	return dmc_era_post_link( get_post_meta( $post->ID, '_dm_b2_id', true ) ? '0000-00-00' : get_the_date( 'Y-m-d H:i:s', $post ) );
}
