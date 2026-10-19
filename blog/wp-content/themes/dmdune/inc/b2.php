<?php
/**
 * The 2003–2005 b2 (cafelog) blog, imported into WordPress in 2026. Each imported post keeps its
 * b2 ID in _dm_b2_id and, when a friend wrote it, the name they posted under in _dm_b2_author.
 *
 * - Old b2 addresses (/blog/index.php?p=123, ?m=200305, ?cat=3, b2commentspopup.php?p=123)
 *   redirect to the imported posts and archives.
 * - Guest posts say who wrote them.
 * - Relative links and images in b2 posts resolve against /blog/, where b2 lived.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

/**
 * WordPress post ID for a b2 post ID, or 0.
 */
function dmdune_b2_post( int $b2_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_dm_b2_id' AND meta_value = %s LIMIT 1", (string) $b2_id ) );
}

/**
 * Runs before WordPress reads ?p=, which would otherwise send /blog/index.php?p=5 to the
 * unrelated WordPress post 5.
 */
add_action(
	'parse_request',
	static function () {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( ! preg_match( '#^/blog/(?:index\.php|b2commentspopup\.php|b2rss\.php)?$#', $path ) ) {
			return;
		}
		$to = '';
		if ( isset( $_GET['p'] ) ) {
			$post = dmdune_b2_post( absint( $_GET['p'] ) );
			$to   = $post ? get_permalink( $post ) . ( isset( $_GET['c'] ) || str_ends_with( $path, 'popup.php' ) ? '#comments' : '' ) : '';
		} elseif ( isset( $_GET['m'] ) && preg_match( '/^(\d{4})(\d{2})$/', (string) $_GET['m'], $m ) ) {
			$to = get_month_link( (int) $m[1], (int) $m[2] );
		} elseif ( isset( $_GET['cat'] ) ) {
			// b2 category IDs: 1 Blog, 3 Rants, 9 News, 11 Reviews, 12 Shia.
			$slug = array( 1 => 'blog', 3 => 'rant', 9 => 'news', 11 => 'reviews', 12 => 'blog' )[ absint( $_GET['cat'] ) ] ?? '';
			$term = $slug ? get_category_by_slug( $slug ) : null;
			$to   = $term ? get_category_link( $term ) : '';
		} elseif ( str_ends_with( $path, 'b2rss.php' ) ) {
			$to = get_feed_link();
		}
		if ( '' === $to && ( isset( $_GET['p'] ) || ( '/blog/' !== $path && '/blog/index.php' !== $path ) ) ) {
			$to = home_url( '/' );
		}
		if ( $to ) {
			wp_safe_redirect( $to, 301 );
			exit;
		}
	},
	1
);

add_filter(
	'the_content',
	static function ( string $content ): string {
		$post = get_post();
		if ( ! $post || ! get_post_meta( $post->ID, '_dm_b2_id', true ) ) {
			return $content;
		}
		// b2 pages lived at /blog/, so "../images/x.jpg" meant /images/x.jpg and "index.php?p=5" meant /blog/index.php?p=5.
		$content = preg_replace_callback(
			'#\b(href|src)=(["\'])(?!(?:[a-z][a-z0-9+.-]*:|/|\#))([^"\']+)\2#i',
			static fn( $m ) => $m[1] . '=' . $m[2] . esc_url( dmdune_b2_url( $m[3] ) ) . $m[2],
			$content
		);
		$author = post_password_required( $post ) ? '' : get_post_meta( $post->ID, '_dm_b2_author', true );
		if ( $author ) {
			$content = sprintf( '<p class="dm-guest">%s</p>', sprintf( esc_html__( 'Guest post by %s', 'dmdune' ), '<b>' . esc_html( $author ) . '</b>' ) ) . $content;
		}
		return $content;
	},
	4
);

/**
 * Password form in the site's own style. The b2 posts locked for privacy (friends' posts, or
 * classmates named) say so, so readers know why.
 */
add_filter(
	'the_password_form',
	static function ( string $form, $post = null ): string {
		$post   = get_post( $post );
		$reason = $post && get_post_meta( $post->ID, '_dm_b2_id', true )
			? __( 'This post from the school years names friends who never signed up to be on the internet, so it’s locked. Ask DarkMirage for the password.', 'dmdune' )
			: __( 'This post is password-protected.', 'dmdune' );
		$id = 'pwbox-' . ( $post ? $post->ID : 0 );
		return sprintf(
			'<form action="%1$s" class="post-password-form dm-locked" method="post"><p class="dm-locked__why">%2$s</p><div class="dm-locked__row"><label class="screen-reader-text" for="%3$s">%4$s</label><input name="post_password" id="%3$s" type="password" autocomplete="current-password" spellcheck="false" placeholder="%4$s" required><button type="submit">%5$s</button></div></form>',
			esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ),
			esc_html( $reason ),
			esc_attr( $id ),
			esc_attr__( 'Password', 'dmdune' ),
			esc_html__( 'Unlock', 'dmdune' )
		);
	},
	10,
	2
);

// The lock box explains itself, so titles stay as written instead of "Protected: …".
add_filter( 'protected_title_format', static fn() => '%s' );

/**
 * Locked posts stay out of search results, so a search for a friend's name doesn't list them.
 */
add_filter(
	'posts_where',
	static function ( string $where, WP_Query $q ): string {
		global $wpdb;
		return ! is_admin() && $q->is_search() ? $where . " AND {$wpdb->posts}.post_password = ''" : $where;
	},
	10,
	2
);

/**
 * A few locked b2 posts are titled with a friend's name; until unlocked they show a neutral title.
 */
function dmdune_b2_title( $title, $post = 0 ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	if ( ! $id || is_admin() || ! get_post_meta( $id, '_dm_b2_hide_title', true ) || ! post_password_required( $id ) ) {
		return $title;
	}
	return __( 'Locked post', 'dmdune' );
}
add_filter( 'the_title', 'dmdune_b2_title', 10, 2 );
// The <title> tag comes from single_post_title(), which skips the_title.
add_filter( 'single_post_title', 'dmdune_b2_title', 10, 2 );

/**
 * A link from a b2 post, made absolute. b2 pages lived at /blog/, so "../images/x.jpg" meant
 * /images/x.jpg and "index.php?p=5" meant /blog/index.php?p=5. Absolute and root-relative links,
 * and #anchors, are returned unchanged.
 */
function dmdune_b2_url( string $url ): string {
	if ( preg_match( '#^(?:[a-z][a-z0-9+.-]*:|/|\#)#i', $url ) ) {
		return $url;
	}
	return home_url( str_starts_with( $url, '../' ) ? '/' . substr( $url, 3 ) : '/blog/' . ltrim( $url, './' ) );
}
