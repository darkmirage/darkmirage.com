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
