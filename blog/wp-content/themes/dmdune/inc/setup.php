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
		add_editor_style( 'assets/css/dmdune.css' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$uri = get_template_directory_uri();
		wp_enqueue_style( 'dmdune', $uri . '/assets/css/dmdune.css', array(), DMDUNE_VERSION );
		wp_enqueue_script( 'dmdune', $uri . '/assets/js/dmdune.js', array(), DMDUNE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'dmdune',
			'dmdune',
			array(
				'toLight'  => __( 'Switch to light mode', 'dmdune' ),
				'toDark'   => __( 'Switch to dark mode', 'dmdune' ),
				'close'    => __( 'Close', 'dmdune' ),
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
		echo '<meta name="color-scheme" content="light dark">' . "\n";
		echo '<meta name="theme-color" content="#f2efe9" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#0d1017" media="(prefers-color-scheme: dark)">' . "\n";
		echo "<script>(function(){try{var m=localStorage.getItem('dm-theme');if(m==='light'||m==='dark'){document.documentElement.setAttribute('data-theme',m);}}catch(e){}})();</script>\n";
	},
	1
);

/**
 * Falls back to the roundel as the favicon until a site icon is set in the Customizer.
 */
add_action(
	'wp_head',
	static function () {
		if ( has_site_icon() ) {
			return;
		}
		$base = get_template_directory_uri() . '/assets/brand/icons/';
		printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( $base . 'site-icon-32.png' ) );
		printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( $base . 'site-icon-192.png' ) );
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $base . 'site-icon-180.png' ) );
	},
	5
);

add_filter( 'excerpt_length', static fn() => 34, 999 );
add_filter( 'excerpt_more', static fn() => '…' );
