<?php
/**
 * The Featured page (/featured/, template page-featured.html). Posts are picked by giving them
 * the tag "featured" in the editor; outside wp-admin that tag is hidden from tag lists and the
 * tag cloud, and /tag/featured/ goes to the Featured page.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'get_the_terms',
	static function ( $terms, $post_id, $taxonomy ) {
		if ( is_admin() || 'post_tag' !== $taxonomy || ! is_array( $terms ) ) {
			return $terms;
		}
		return array_values( array_filter( $terms, static fn( $t ) => 'featured' !== $t->slug ) );
	},
	10,
	3
);

add_filter(
	'get_terms',
	static function ( $terms, $taxonomies ) {
		if ( is_admin() || ! in_array( 'post_tag', (array) $taxonomies, true ) || ! is_array( $terms ) ) {
			return $terms;
		}
		return array_values( array_filter( $terms, static fn( $t ) => ! ( $t instanceof WP_Term ) || 'featured' !== $t->slug ) );
	},
	10,
	2
);

add_action(
	'template_redirect',
	static function () {
		if ( is_tag( 'featured' ) ) {
			wp_safe_redirect( home_url( '/featured/' ), 301 );
			exit;
		}
	}
);
