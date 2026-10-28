<?php
/**
 * Share previews and description tags.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	function () {
		$title = is_singular() ? get_the_title() : get_bloginfo( 'name' );
		$desc  = is_singular() ? wp_strip_all_tags( get_the_excerpt() ) : get_bloginfo( 'description' );
		$url   = is_singular() ? get_permalink() : home_url( '/' );
		$image = is_singular() && has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'large' ) : get_theme_file_uri( 'assets/brand/skyline-share.jpg' );
		$tags  = array(
			'og:site_name'  => get_bloginfo( 'name' ),
			'og:type'       => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'      => $title,
			'og:description' => $desc,
			'og:url'        => $url,
			'og:image'      => $image,
			'twitter:card'  => 'summary_large_image',
		);
		if ( $desc ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $desc ) );
		}
		foreach ( array_filter( $tags ) as $property => $content ) {
			printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $content ) );
		}
		printf( "<link rel=\"icon\" href=\"%s\">\n", esc_url( get_theme_file_uri( 'assets/brand/raven-logo.webp' ) ) );
	},
	5
);
