<?php
/**
 * Old addresses. Posts live at /<title>/; these send the addresses they had before there: Medium's /<title>-<id>/
 * (whatever the title part says), its short /p/<id> and /@darkmirage/ links, and /yyyy/mm/dd/<slug>/ links from
 * soraven.com's WordPress years before Medium.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

// The August 2015 copy of "Video game's place in history" was dropped in favour of the October one.
const SORAVEN_MEDIUM_ALIASES = array( 'b1379f29f3ad' => 'ffb0f147a1fd' );

/**
 * Post with the given Medium id.
 *
 * @param string $medium_id Medium post id.
 * @return int Post ID, or 0.
 */
function soraven_post_by_medium_id( $medium_id ) {
	$medium_id = SORAVEN_MEDIUM_ALIASES[ $medium_id ] ?? $medium_id;
	$ids       = get_posts(
		array(
			'post_type'   => 'post',
			'meta_key'    => '_medium_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- 404s only, 29 posts.
			'meta_value'  => $medium_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Post whose slug, without hyphens (and any Medium id), matches an old WordPress slug.
 *
 * @param string $old_slug Slug from a /yyyy/mm/dd/<slug>/ address.
 * @return int Post ID, or 0.
 */
function soraven_post_by_old_slug( $old_slug ) {
	$key = str_replace( '-', '', sanitize_title( $old_slug ) );
	foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => -1 ) ) as $post ) {
		if ( str_replace( '-', '', preg_replace( '/-[0-9a-f]{10,12}$/', '', $post->post_name ) ) === $key ) {
			return $post->ID;
		}
	}
	return 0;
}

add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id   = 0;
		if ( preg_match( '#^(?:p/|@darkmirage/)?(?:[^/]*-)?([0-9a-f]{10,12})$#', $path, $m ) ) {
			$id = soraven_post_by_medium_id( $m[1] );
		} elseif ( preg_match( '#^\d{4}/\d{2}/\d{2}/([^/]+)$#', $path, $m ) ) {
			$id = soraven_post_by_old_slug( $m[1] );
		}
		if ( $id ) {
			wp_safe_redirect( get_permalink( $id ), 301 );
			exit;
		}
	}
);
