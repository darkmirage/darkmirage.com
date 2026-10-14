<?php
/**
 * Theme supports, assets and <head> output.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		add_editor_style( array( 'assets/css/chrome.css', 'assets/css/dmdune.css' ) );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$uri = get_template_directory_uri();
		wp_enqueue_style( 'dmdune-chrome', $uri . '/assets/css/chrome.css', array(), DMDUNE_VERSION );
		wp_enqueue_style( 'dmdune', $uri . '/assets/css/dmdune.css', array( 'dmdune-chrome' ), DMDUNE_VERSION );
		wp_enqueue_script( 'dmdune-chrome', $uri . '/assets/js/chrome.js', array(), DMDUNE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_enqueue_script( 'dmdune', $uri . '/assets/js/dmdune.js', array(), DMDUNE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'dmdune',
			'dmdune',
			array(
				'toLight'  => __( 'Switch to light mode', 'dmdune' ),
				'toDark'   => __( 'Switch to dark mode', 'dmdune' ),
				'close'    => __( 'Close', 'dmdune' ),
				'prev'     => __( 'Previous picture', 'dmdune' ),
				'next'     => __( 'Next picture', 'dmdune' ),
				'viewer'   => __( 'Picture viewer', 'dmdune' ),
				'playLabel' => __( 'Play video', 'dmdune' ),
			)
		);
	}
);

/**
 * Applies a saved light/dark choice before first paint so the page never flashes the wrong mode.
 * Without a saved choice the CSS follows prefers-color-scheme.
 */
add_action(
	'wp_head',
	static function () {
		dmc_head_early();
	},
	1
);

/**
 * Falls back to the roundel as the favicon until a site icon is set in the Customizer. The admin
 * and login screens don't run wp_head, so they get it too (otherwise browsers show the 2007
 * /favicon.ico there).
 */
$dmdune_icons = static function () {
	if ( has_site_icon() ) {
		return;
	}
	$base = get_template_directory_uri() . '/assets/brand/icons/';
	// SVG first: it switches to night colours in dark mode. PNG for browsers without SVG icons.
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( $base . 'favicon.svg' ) );
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( $base . 'site-icon-32.png' ) );
	printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( $base . 'site-icon-192.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $base . 'site-icon-180.png' ) );
};
add_action( 'wp_head', $dmdune_icons, 5 );
add_action( 'admin_head', $dmdune_icons );
add_action( 'login_head', $dmdune_icons );

add_filter( 'excerpt_length', static fn() => 34, 999 );
add_filter( 'excerpt_more', static fn() => '…' );

/**
 * Country flag after each commenter's name, as the IP to Nation plugin showed from 2006.
 */
add_filter(
	'render_block_core/comment-author-name',
	static function ( $html, $parsed, $block ) {
		$comment = get_comment( $block->context['commentId'] ?? 0 );
		$country = $comment ? dmdune_comment_country( $comment ) : null;
		if ( ! $country ) {
			return $html;
		}
		$file  = '/assets/flags/' . $country['code'] . '.svg';
		$label = $country['name'] ?: strtoupper( $country['code'] );
		$flag  = file_exists( get_template_directory() . $file )
			? sprintf( '<img src="%s" alt="" width="20" height="15" loading="lazy" decoding="async">', esc_url( get_template_directory_uri() . $file ) )
			: '<span>' . esc_html( strtoupper( $country['code'] ) ) . '</span>';
		return $html . sprintf( '<span class="dm-flag" title="%1$s" role="img" aria-label="%1$s">%2$s</span>', esc_attr( $label ), $flag );
	},
	10,
	3
);

/**
 * Long threads open on their first page and read top to bottom; the WordPress default jumps to the
 * newest page, which for a 2011 post means landing mid-argument.
 */
add_filter( 'option_default_comments_page', static fn() => 'oldest' );

/**
 * Utility pages from the old themes. Archives and Tags are real screens now (templates
 * page-archives / page-tag); the rest were empty shells the old themes filled in. None of them
 * should collect comments.
 */
function dmdune_utility_pages(): array {
	return array( 'contact', 'links', 'search', '404-2', '403', 'tag', 'archives' );
}

add_filter(
	'comments_open',
	static function ( $open, $post_id ) {
		$post = get_post( $post_id );
		return ( $post && 'page' === $post->post_type && in_array( $post->post_name, dmdune_utility_pages(), true ) ) ? false : $open;
	},
	10,
	2
);

/**
 * Retired pages: /links/ was always empty (its content came from the old theme's blogroll),
 * /search/ duplicated the real search, and /404-2/ and /403/ were bodies for error pages that the
 * server no longer uses. Send readers somewhere useful, or answer 404 like any missing page.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_page() ) {
			return;
		}
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( 'links' === $slug ) {
			wp_safe_redirect( home_url( '/contact/' ), 301 );
			exit;
		}
		if ( 'search' === $slug ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		if ( in_array( $slug, array( '404-2', '403' ), true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);
