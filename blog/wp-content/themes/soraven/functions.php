<?php
/**
 * So Raven theme bootstrap.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

define( 'SORAVEN_VERSION', '1.1.2' );
// One author on this site; the network account's display name belongs to darkmirage.com.
define( 'SORAVEN_BYLINE', 'Raven Jiang' );

require __DIR__ . '/inc/template-tags.php';
require __DIR__ . '/inc/redirects.php';
require __DIR__ . '/inc/meta.php';

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		// Imported posts carry <figure class="wide"> for Medium's outset images.
		$GLOBALS['content_width'] = 680;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'soraven', get_theme_file_uri( 'assets/css/soraven.css' ), array(), SORAVEN_VERSION );
		if ( is_singular() && comments_open() ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
);

// Full rows of three: the home's first page is the lead essay plus nine, every other listing page twelve.
const SORAVEN_FIRST_PAGE = 10;
const SORAVEN_PER_PAGE   = 12;

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! ( $query->is_home() || $query->is_archive() || $query->is_search() ) ) {
			return;
		}
		if ( ! $query->is_home() ) {
			$query->set( 'posts_per_page', SORAVEN_PER_PAGE );
			return;
		}
		$page = max( 1, (int) $query->get( 'paged' ) );
		$query->set( 'posts_per_page', 1 === $page ? SORAVEN_FIRST_PAGE : SORAVEN_PER_PAGE );
		if ( $page > 1 ) {
			$query->set( 'offset', SORAVEN_FIRST_PAGE + ( $page - 2 ) * SORAVEN_PER_PAGE );
		}
	}
);

// With a shorter first page WordPress's own page count is off. the_posts runs after it is computed.
add_filter(
	'the_posts',
	function ( $posts, $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
			$query->max_num_pages = 1 + (int) ceil( max( 0, $query->found_posts - SORAVEN_FIRST_PAGE ) / SORAVEN_PER_PAGE );
		}
		return $posts;
	},
	10,
	2
);

add_filter(
	'excerpt_length',
	function () {
		return 32;
	}
);

add_filter(
	'excerpt_more',
	function () {
		return '…';
	}
);
