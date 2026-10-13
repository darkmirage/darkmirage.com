<?php
/**
 * Data helpers shared by the blocks.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

/**
 * Upgrades links to the site's own hosts to https. The 2005–2013 posts hard-code http://, which
 * browsers block or flag as mixed content.
 */
function dmdune_https( string $url ): string {
	return preg_replace( '#^http://((?:www\.|gallery\.|lyrics\.)?darkmirage\.com)(?=[/:]|$)#i', 'https://$1', $url );
}

/**
 * Old images replaced at render time by newer artwork. "So Raven" (2013) pointed readers to
 * soraven.com with a 256px palette PNG that stair-steps when scaled; it now shows So Raven's
 * current skyline (on cards) and raven logo (in the post).
 *
 * @return array<string,array{card:string,content:string}> path fragment => replacements
 */
function dmdune_image_overrides(): array {
	$base = get_template_directory_uri() . '/assets/brand/soraven/';
	return array(
		'/uploads/2013/06/sr.png' => array(
			'card'    => $base . 'skyline-1600.webp',
			'content' => $base . 'raven-logo.webp',
		),
	);
}

function dmdune_override_image( string $src, string $where ): string {
	foreach ( dmdune_image_overrides() as $fragment => $new ) {
		if ( false !== strpos( $src, $fragment ) ) {
			return $new[ $where ];
		}
	}
	return $src;
}

/**
 * The first real image in a post, for card thumbnails. Posts predate featured images, so this
 * reads the content instead of writing thumbnails to the database.
 */
function dmdune_first_image( WP_Post $post ): ?string {
	if ( has_post_thumbnail( $post ) ) {
		$src = get_the_post_thumbnail_url( $post, 'medium_large' );
		return $src ?: null;
	}
	$cache = wp_cache_get( $post->ID, 'dmdune_first_image' );
	if ( false !== $cache ) {
		return '' === $cache ? null : $cache;
	}
	$found = '';
	if ( preg_match_all( '#(?:<a\b[^>]*\bhref=(["\'])([^"\']+)\1[^>]*>\s*)?(<img\b[^>]*>)#i', $post->post_content, $tags, PREG_SET_ORDER ) ) {
		foreach ( $tags as $t ) {
			$tag = $t[3];
			if ( ! preg_match( '#\bsrc=(["\'])(.*?)\1#i', $tag, $m ) ) {
				continue;
			}
			$src = html_entity_decode( $m[2] );
			// Smilies, badges, counters and ad pixels aren't post art.
			if ( preg_match( '#smilies|admin\.gif|googlesyndication|qrcode|creativecommons|w3\.org|/wp-content/plugins/|\.(?:svg)$#i', $src ) ) {
				continue;
			}
			$size = 0;
			if ( preg_match( '#\b(?:width|height)\s*[=:]\s*["\']?(\d+)#i', $tag, $d ) ) {
				$size = (int) $d[1];
				if ( $size < 100 ) {
					continue;
				}
			}
			// A small inline thumbnail that links to the full picture: the card wants the full one.
			$href = isset( $t[2] ) ? html_entity_decode( $t[2] ) : '';
			$thumbish = ( $size && $size <= 200 ) || preg_match( '#[._-]thumb(?:nail)?[._-]|thumb-|/thumb/|s\.(?:jpe?g|png|gif)$#i', $src );
			if ( $thumbish && preg_match( '#\.(?:jpe?g|png|gif|webp)$#i', $href ) ) {
				$src = $href;
			}
			$found = dmdune_override_image( dmdune_https( $src ), 'card' );
			if ( 0 === strpos( $found, '/' ) ) {
				$found = home_url( $found );
			}
			break;
		}
	}
	// Posts that only embed YouTube videos: use the first video's cover art that still exists.
	if ( '' === $found && preg_match_all( '#youtube(?:-nocookie)?\.com/(?:v|embed)/([A-Za-z0-9_-]{11})|youtu\.be/([A-Za-z0-9_-]{11})|youtube\.com/watch\?v=([A-Za-z0-9_-]{11})#i', $post->post_content, $yt, PREG_SET_ORDER ) ) {
		foreach ( $yt as $m ) {
			$id = $m[1] ?: ( $m[2] ?? '' ) ?: ( $m[3] ?? '' );
			$cover = $id ? dmdune_youtube_cover( $id ) : null;
			if ( $cover ) {
				$found = $cover;
				break;
			}
		}
	}
	wp_cache_set( $post->ID, $found, 'dmdune_first_image', HOUR_IN_SECONDS );
	return '' === $found ? null : $found;
}

/**
 * Cover art for a YouTube video, or null if the video is gone (removed videos' thumbnails 404).
 * Prefers the 1280x720 image; old uploads only have the 480x360 one, which has letterbox bars
 * (cropped off in CSS via .dm-media--yt). Cached for a month, so each video costs a request or two.
 */
function dmdune_youtube_cover( string $id ): ?string {
	$key   = 'dmdune_ytc_' . $id;
	$cover = get_transient( $key );
	if ( false === $cover ) {
		$cover = '';
		foreach ( array( 'maxresdefault', 'hqdefault' ) as $size ) {
			$url  = 'https://i.ytimg.com/vi/' . $id . '/' . $size . '.jpg';
			$res  = wp_remote_head( $url, array( 'timeout' => 4, 'redirection' => 0 ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( 0 === $code ) {
				return null; // Network trouble: don't cache, try again next time.
			}
			if ( 200 === $code ) {
				$cover = $url;
				break;
			}
		}
		set_transient( $key, $cover, MONTH_IN_SECONDS );
	}
	return '' === $cover ? null : $cover;
}

/**
 * Published post counts per year, oldest first.
 *
 * @return array<int,int> year => count
 */
function dmdune_year_counts(): array {
	$counts = get_transient( 'dmdune_year_counts' );
	if ( is_array( $counts ) ) {
		return $counts;
	}
	global $wpdb;
	$rows   = $wpdb->get_results(
		"SELECT YEAR(post_date) AS y, COUNT(*) AS n FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish' GROUP BY y ORDER BY y"
	);
	$counts = array();
	foreach ( $rows as $row ) {
		$counts[ (int) $row->y ] = (int) $row->n;
	}
	set_transient( 'dmdune_year_counts', $counts, DAY_IN_SECONDS );
	return $counts;
}

/**
 * Published post counts per month, newest year first.
 *
 * @return array<int,array<int,int>> year => month => count
 */
function dmdune_month_counts(): array {
	$counts = get_transient( 'dmdune_month_counts' );
	if ( is_array( $counts ) ) {
		return $counts;
	}
	global $wpdb;
	$rows   = $wpdb->get_results(
		"SELECT YEAR(post_date) AS y, MONTH(post_date) AS m, COUNT(*) AS n FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish' GROUP BY y, m ORDER BY y DESC, m"
	);
	$counts = array();
	foreach ( $rows as $row ) {
		$counts[ (int) $row->y ][ (int) $row->m ] = (int) $row->n;
	}
	set_transient( 'dmdune_month_counts', $counts, DAY_IN_SECONDS );
	return $counts;
}

/**
 * The rotating subtitles. From 2005 the Randomize plugin showed one of ~30 lines under the blog
 * title on each visit; the "Taglines" post (slug: taglines) lists them in a blockquote.
 *
 * @return string[]
 */
function dmdune_taglines(): array {
	$lines = get_transient( 'dmdune_taglines' );
	if ( is_array( $lines ) ) {
		return $lines;
	}
	$lines = array();
	$post  = get_page_by_path( 'taglines', OBJECT, 'post' );
	if ( $post && preg_match( '#<blockquote[^>]*>(.*?)</blockquote>#is', $post->post_content, $m ) ) {
		foreach ( preg_split( '#<br\s*/?>|\r?\n#i', $m[1] ) as $line ) {
			$line = trim( html_entity_decode( wp_strip_all_tags( $line ) ) );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
	}
	set_transient( 'dmdune_taglines', $lines, DAY_IN_SECONDS );
	return $lines;
}

function dmdune_flush_counts(): void {
	delete_transient( 'dmdune_taglines' );
	delete_transient( 'dmdune_year_counts' );
	delete_transient( 'dmdune_month_counts' );
	delete_transient( 'dmdune_most_discussed' );
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_dmdune\\_loud\\_%' OR option_name LIKE '\\_transient\\_dmdune\\_yc\\_%'" );
}
add_action( 'save_post_post', 'dmdune_flush_counts' );
add_action( 'deleted_post', 'dmdune_flush_counts' );
add_action( 'wp_update_comment_count', 'dmdune_flush_counts' );

/**
 * Which of the blog's themes was live in a year; the archive strip colours its bars with it.
 */
function dmdune_era( int $year ): string {
	if ( $year <= 2005 ) {
		return 'default';
	}
	if ( $year <= 2007 ) {
		return 'kubrick';
	}
	return 'blocks';
}

/**
 * Posts sharing the most tags with $post (falling back to its categories).
 *
 * @return WP_Post[]
 */
function dmdune_related_posts( WP_Post $post, int $limit = 3 ): array {
	$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) );
	$args = array(
		'post__not_in'        => array( $post->ID ),
		'posts_per_page'      => 24,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( $tags && ! is_wp_error( $tags ) ) {
		$candidates = get_posts( $args + array( 'tag__in' => $tags ) );
		$score      = array();
		foreach ( $candidates as $c ) {
			$shared          = wp_get_post_terms( $c->ID, 'post_tag', array( 'fields' => 'ids' ) );
			$score[ $c->ID ] = count( array_intersect( $tags, is_array( $shared ) ? $shared : array() ) );
		}
		usort(
			$candidates,
			static function ( $a, $b ) use ( $score ) {
				return array( $score[ $b->ID ], $b->post_date ) <=> array( $score[ $a->ID ], $a->post_date );
			}
		);
		if ( count( $candidates ) >= $limit ) {
			return array_slice( $candidates, 0, $limit );
		}
	}
	// Not enough tag matches: fall back to the newest posts in the same category.
	$args['posts_per_page'] = $limit;
	$args['category__in']   = wp_get_post_categories( $post->ID );
	return get_posts( $args );
}

/**
 * The site's main navigation. Pages are looked up by slug so links survive permalink changes.
 *
 * @return array<int,array{label:string,url:string,external?:bool,key:string,icon:string}>
 */
function dmdune_nav_items(): array {
	$page = static function ( string $slug ): string {
		$p = get_page_by_path( $slug );
		return $p ? get_permalink( $p ) : home_url( "/$slug/" );
	};
	return array(
		array( 'key' => 'home', 'label' => __( 'Home', 'dmdune' ), 'url' => home_url( '/' ), 'icon' => 'home' ),
		array( 'key' => 'archives', 'label' => __( 'Archive', 'dmdune' ), 'url' => $page( 'archives' ), 'icon' => 'archive' ),
		array( 'key' => 'tag', 'label' => __( 'Tags', 'dmdune' ), 'url' => $page( 'tag' ), 'icon' => 'tag' ),
		array( 'key' => 'japanese', 'label' => '日本語', 'url' => $page( 'beginners-japanese-lessons-by-darkmirage' ), 'icon' => 'kana' ),
		array( 'key' => 'lyrics', 'label' => __( 'Lyrics', 'dmdune' ), 'url' => 'https://lyrics.darkmirage.com/', 'external' => true, 'icon' => 'music' ),
		array( 'key' => 'gallery', 'label' => __( 'Gallery', 'dmdune' ), 'url' => 'https://gallery.darkmirage.com/', 'external' => true, 'icon' => 'camera' ),
		array( 'key' => 'capsule', 'label' => __( 'Time Capsule', 'dmdune' ), 'url' => $page( 'haruhiism-time-capsule-project' ), 'icon' => 'capsule' ),
		array( 'key' => 'contact', 'label' => __( 'Contact', 'dmdune' ), 'url' => $page( 'contact' ), 'icon' => 'mail' ),
	);
}

/**
 * The nav key for the current request, for aria-current.
 */
function dmdune_current_nav(): string {
	if ( is_front_page() || is_home() ) {
		return 'home';
	}
	if ( is_page() ) {
		$slugs = array(
			'archives'                                => 'archives',
			'tag'                                     => 'tag',
			'beginners-japanese-lessons-by-darkmirage' => 'japanese',
			'haruhiism-time-capsule-project'          => 'capsule',
			'contact'                                 => 'contact',
		);
		return $slugs[ get_post_field( 'post_name', get_queried_object_id() ) ] ?? '';
	}
	if ( is_date() ) {
		return 'archives';
	}
	if ( is_tag() || is_category() ) {
		return 'tag';
	}
	return '';
}

/**
 * Inline SVG icons (stroke style, 24px grid).
 */
function dmdune_icon( string $name, int $size = 20 ): string {
	$paths = array(
		'home'    => '<path d="M4 11l8-7 8 7v9H4z"/>',
		'archive' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M10 14h4"/>',
		'tag'     => '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
		'kana'    => '<path d="M5 7h10M10 4c0 8-2 12-6 15M9 11c3 0 7 2 9 7"/>',
		'music'   => '<path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="16" r="2"/>',
		'camera'  => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.5"/><path d="M8 7l2-3h4l2 3"/>',
		'capsule' => '<rect x="5" y="3" width="14" height="18" rx="7"/><path d="M5 12h14"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'search'  => '<circle cx="11" cy="11" r="6"/><path d="M16 16l4 4"/>',
		'more'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'moon'    => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
		'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'out'     => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'play'    => '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
	);
	return sprintf(
		'<svg class="dm-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>',
		$size,
		$paths[ $name ] ?? ''
	);
}

/**
 * Commenter's country, from the ip2nation tables the old "IP to Nation" plugin left in the
 * database. Its data is from the same years as the comments, so it places 2005–2013 IPs better
 * than a current database would. Returns null when the tables are gone or the IP isn't public.
 *
 * @return array{code:string,name:string}|null
 */
function dmdune_comment_country( WP_Comment $comment ): ?array {
	static $has_tables = null, $cache = array();
	global $wpdb;
	if ( null === $has_tables ) {
		$has_tables = (bool) $wpdb->get_var( "SHOW TABLES LIKE 'ip2nation'" );
	}
	$ip = $comment->comment_author_IP;
	if ( ! $has_tables || ( '' !== $comment->comment_type && 'comment' !== $comment->comment_type )
		|| ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return null;
	}
	if ( ! array_key_exists( $ip, $cache ) ) {
		$row          = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT n.country AS code, c.country AS name FROM ip2nation n
				 LEFT JOIN ip2nationCountries c ON c.code = n.country
				 WHERE n.ip < INET_ATON(%s) ORDER BY n.ip DESC LIMIT 1',
				$ip
			)
		);
		$cache[ $ip ] = ( $row && ! in_array( $row->code, array( '01', '' ), true ) )
			? array( 'code' => 'uk' === $row->code ? 'gb' : $row->code, 'name' => (string) $row->name )
			: null;
	}
	return $cache[ $ip ];
}
