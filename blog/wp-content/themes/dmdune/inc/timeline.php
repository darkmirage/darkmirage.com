<?php
/**
 * Data for the zoomable home timeline (assets/js/timeline.js).
 *
 * GET /wp-json/dmdune/v1/timeline           every published post, newest first, as compact rows:
 *                                            [id, unix time, title, category slug, era, replies,
 *                                             locked (0/1), small picture URL, permalink]
 *                                            (URLs on this site are relative, to keep it small)
 * GET /wp-json/dmdune/v1/timeline/excerpts?year=2006
 *                                           {id: excerpt} for that year, fetched as the reader
 *                                            reaches it. Locked posts are left out.
 *
 * Both are built once and cached until a post or comment count changes. Password-protected posts
 * only ever give their title (get_the_title(), so b2 posts titled with a friend's name stay
 * "Locked post"): no picture, no excerpt.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

const DMDUNE_TIMELINE_KEY = 'dmdune_timeline_v2';

/**
 * A smaller copy of the post's first picture when it's a WordPress upload; otherwise the
 * picture itself (old posts link images that never went through the media library).
 */
function dmdune_timeline_thumb( WP_Post $post ): string {
	$src = dmdune_first_image( $post );
	if ( ! $src ) {
		return '';
	}
	if ( false !== strpos( $src, '/wp-content/uploads/' ) ) {
		$id = attachment_url_to_postid( preg_replace( '/-\d+x\d+(\.\w+)$/', '$1', $src ) );
		if ( $id ) {
			$medium = wp_get_attachment_image_url( $id, 'medium' );
			if ( $medium ) {
				return $medium;
			}
		}
	}
	return $src;
}

/**
 * Drop the scheme and host from links to this site (wp_make_link_relative() would strip any host).
 */
function dmdune_timeline_local( string $url ): string {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $host && preg_replace( '/^www\./', '', strtolower( $host ) ) === preg_replace( '/^www\./', '', strtolower( $home ) ) ) {
		return (string) preg_replace( '|^(https?:)?//[^/]+|i', '', $url );
	}
	return $url;
}

function dmdune_timeline_rows(): array {
	$rows = get_transient( DMDUNE_TIMELINE_KEY );
	if ( is_array( $rows ) ) {
		return $rows;
	}
	$rows = array();
	$ids  = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'suppress_filters' => false,
			'no_found_rows'    => true,
		)
	);
	update_object_term_cache( $ids, 'post' );
	update_meta_cache( 'post', $ids );
	foreach ( $ids as $id ) {
		$post   = get_post( $id );
		$ts     = get_post_timestamp( $post );
		$locked = '' !== $post->post_password;
		$cat    = dmdune_primary_category( $post );
		$rows[] = array(
			$post->ID,
			$ts,
			$locked && get_post_meta( $post->ID, '_dm_b2_hide_title', true ) ? __( 'Locked post', 'dmdune' ) : html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			$cat ? $cat->slug : '',
			dmdune_era( (int) wp_date( 'Y', $ts ), (int) wp_date( 'n', $ts ) ),
			(int) $post->comment_count,
			$locked ? 1 : 0,
			$locked ? '' : dmdune_timeline_local( dmdune_timeline_thumb( $post ) ),
			dmdune_timeline_local( get_permalink( $post ) ),
		);
	}
	set_transient( DMDUNE_TIMELINE_KEY, $rows, WEEK_IN_SECONDS );
	return $rows;
}

function dmdune_timeline_excerpts( int $year ): array {
	$key  = DMDUNE_TIMELINE_KEY . '_x' . $year;
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	$data  = array();
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'year'           => $year,
			'has_password'   => false,
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		$text = html_entity_decode( wp_strip_all_tags( get_the_excerpt( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) > 200 ) {
			$text = rtrim( mb_substr( $text, 0, 199 ) ) . '…';
		}
		$data[ (string) $post->ID ] = $text;
	}
	set_transient( $key, $data, WEEK_IN_SECONDS );
	return $data;
}

function dmdune_timeline_flush(): void {
	delete_transient( DMDUNE_TIMELINE_KEY );
	foreach ( range( 2003, (int) wp_date( 'Y' ) ) as $y ) {
		delete_transient( DMDUNE_TIMELINE_KEY . '_x' . $y );
	}
}
add_action( 'save_post_post', 'dmdune_timeline_flush' );
add_action( 'deleted_post', 'dmdune_timeline_flush' );
add_action( 'wp_update_comment_count', 'dmdune_timeline_flush' );

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'dmdune/v1',
			'/timeline',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$res = new WP_REST_Response( dmdune_timeline_rows() );
					$res->header( 'Cache-Control', 'public, max-age=600' );
					return $res;
				},
			)
		);
		register_rest_route(
			'dmdune/v1',
			'/timeline/excerpts',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'year' => array( 'required' => true, 'validate_callback' => static fn( $v ) => is_numeric( $v ) && $v >= 2003 && $v <= 2100 ) ),
				'callback'            => static function ( WP_REST_Request $req ) {
					$res = new WP_REST_Response( (object) dmdune_timeline_excerpts( (int) $req['year'] ) );
					$res->header( 'Cache-Control', 'public, max-age=3600' );
					return $res;
				},
			)
		);
	}
);

/**
 * The timeline script runs on Dune's home page (not in the era skins, which keep their own
 * templates, and not on /page/2/… which stay plain pages for readers without JavaScript).
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( dmc_skin() || ! ( is_front_page() || is_home() ) || is_paged() ) {
			return;
		}
		$uri = get_template_directory_uri();
		wp_enqueue_style( 'dmdune-timeline', $uri . '/assets/css/timeline.css', array( 'dmdune' ), DMDUNE_VERSION );
		wp_enqueue_script( 'dmdune-timeline', $uri . '/assets/js/timeline.js', array(), DMDUNE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'dmdune-timeline',
			'dmTimeline',
			array(
				'api' => esc_url_raw( rest_url( 'dmdune/v1/timeline' ) ),
				'tz'  => (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ),
			)
		);
	},
	30
);
