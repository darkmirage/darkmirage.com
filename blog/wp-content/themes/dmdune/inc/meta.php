<?php
/**
 * Search and share metadata for the blog, through the chrome's dmc_meta() so lyrics and gallery
 * emit the same tag set. No SEO plugin: posts predate featured images and excerpts, so the
 * picture is the post's first image and the description its opening words.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

// dmc_meta() prints the canonical link itself (and omits it on noindex pages).
remove_action( 'wp_head', 'rel_canonical' );

/**
 * Pages kept out of the index but crawlable: date archives repeat the posts, most of the ~1,000 tags hold
 * a post or two (Search Console filed them as duplicates), and a locked post shows only its password form.
 */
function dmdune_noindex(): bool {
	return is_date() || is_author() || is_404() || is_tag() || ( is_singular() && post_password_required() );
}

add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( dmdune_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

/**
 * The fields dmc_meta() needs for the current request.
 */
function dmdune_meta_fields(): array {
	$site = get_bloginfo( 'name' );
	$home = home_url( '/' );
	$m    = array(
		'title'       => $site,
		'description' => 'The DarkMirage blog, 2003–2013: anime, games, J-pop, Japan trips and student life from Singapore to Stanford. Est. 2003 · ' . number_format_i18n( (int) wp_count_posts()->publish ) . ' posts.',
		'url'         => $home,
	);
	$paged = max( 1, (int) get_query_var( 'paged' ) );

	if ( is_front_page() || is_home() ) {
		$m['url']    = $paged > 1 ? get_pagenum_link( $paged ) : $home;
		$m['jsonld'] = array(
			'@context'  => 'https://schema.org',
			'@type'     => 'WebSite',
			'name'      => $site,
			'url'       => $home,
			'inLanguage' => 'en',
			'author'    => dmc_person( dmdune_chrome_config() ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => $home . '?s={search_term_string}',
				'query-input' => 'required name=search_term_string',
			),
		);
	} elseif ( is_singular() ) {
		$post             = get_queried_object();
		// Plain text: old titles hold entities like &mdash;, which the tag escaping would double-encode.
		$m['title']       = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$m['url']         = get_permalink( $post );
		// Password-protected posts give away nothing: no description or picture from the body.
		$locked           = '' !== $post->post_password;
		$body             = $locked ? '' : ( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content );
		// Captions sit above the first paragraph in old posts; they make poor descriptions.
		$body = preg_replace( array( '#\[caption[^\]]*\].*?\[/caption\]#is', '#<figcaption.*?</figcaption>#is', '#<(p|div|span)\b[^>]*class=["\'][^"\']*\bcaption\b[^>]*>.*?</\1>#is', '#<(script|style)\b.*?</\1>#is' ), '', $body );
		if ( '' !== dmc_summary( $body ) ) {
			$m['description'] = $body;
		} elseif ( 'page' === $post->post_type ) {
			$m['description'] = $m['title'] . ' · ' . $m['description'];
		}
		$image            = $locked ? null : dmdune_first_image( $post );
		if ( $image ) {
			$m['image']     = dmdune_share_image( $image );
			$m['image_alt'] = $m['title'];
			$size           = dmdune_image_size( $m['image'] );
			if ( $size ) {
				list( $m['image_w'], $m['image_h'] ) = $size;
			}
		}
		if ( 'post' === $post->post_type ) {
			$cats              = get_the_category( $post->ID );
			$m['type']         = 'article';
			$m['published']    = get_post_time( 'c', true, $post );
			$m['modified']     = get_post_modified_time( 'c', true, $post );
			$m['section']      = $cats ? $cats[0]->name : null;
			$m['tags']         = wp_list_pluck( get_the_tags( $post->ID ) ?: array(), 'name' );
			$m['jsonld']       = array(
				'@context'         => 'https://schema.org',
				'@type'            => 'BlogPosting',
				'headline'         => $m['title'],
				'url'              => $m['url'],
				'datePublished'    => $m['published'],
				'dateModified'     => $m['modified'],
				'author'           => dmc_person( dmdune_chrome_config() ),
				'publisher'        => array( '@type' => 'Organization', 'name' => $site, 'url' => $home ),
				'mainEntityOfPage' => $m['url'],
				'image'            => $image ?: null,
				'commentCount'     => (int) get_comments_number( $post ),
				'inLanguage'       => 'en',
			);
			$m['jsonld'] = array_filter( $m['jsonld'], static fn( $v ) => null !== $v );
		}
	} elseif ( is_category() || is_tag() ) {
		$term             = get_queried_object();
		$m['title']       = ( is_tag() ? 'Posts tagged “' . $term->name . '”' : $term->name ) . ' · ' . $site;
		$m['url']         = $paged > 1 ? get_pagenum_link( $paged ) : get_term_link( $term );
		$m['description'] = $term->description ?: sprintf( '%d posts %s %s on Ramblings of DarkMirage, the blog DarkMirage kept from 2005 to 2013.', $term->count, is_tag() ? 'tagged' : 'filed under', $term->name );
	} elseif ( is_search() ) {
		$m['title']  = 'Search: ' . get_search_query() . ' · ' . $site;
		$m['url']    = null;
		$m['robots'] = 'noindex, follow';
	} elseif ( is_archive() ) {
		$m['title'] = wp_strip_all_tags( get_the_archive_title() ) . ' · ' . $site;
		$m['url']   = null;
	} elseif ( is_404() ) {
		$m['title'] = 'Not found · ' . $site;
		$m['url']   = null;
	}
	return $m;
}

add_action(
	'wp_head',
	static function () {
		$m = dmdune_meta_fields();
		// WordPress prints its own robots tag; only search results need ours, and only in dmc_meta's
		// sense of "skip the canonical".
		if ( ! empty( $m['robots'] ) || dmdune_noindex() ) {
			unset( $m['robots'] );
			$m['url'] = null;
		}
		dmc_meta( dmdune_chrome_config(), $m );
	},
	2
);
