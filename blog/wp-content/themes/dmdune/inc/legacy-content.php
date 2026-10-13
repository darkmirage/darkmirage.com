<?php
/**
 * Render-time fixes for the blog's 2005–2013 post markup. Nothing here writes to the database:
 * the stored posts stay exactly as they were, and only the HTML sent to readers changes.
 *
 * - <spoiler>…</spoiler> (the old themes' custom tag) becomes a tap-to-reveal <details>.
 * - <p class="caption"><img>text</p> becomes <figure><img><figcaption>.
 * - Flash YouTube players become click-to-play YouTube embeds; other Flash gets a notice.
 * - Images lose their fixed 400px sizes, load lazily, and links to the site go over https.
 * - Lightbox/thickbox links open in the theme's own viewer, browsing each post's picture sets.
 * - [audio:file.mp3] (Audio Player plugin) becomes a native audio player.
 * - Old theme classes (.meaning glossary terms, .navigation chapter links, …) keep working.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

/**
 * Before wpautop: turn spoilers into <details> so wpautop treats them as blocks.
 */
function dmdune_legacy_before_autop( string $content ): string {
	if ( false !== strpos( $content, '[audio:' ) ) {
		$content = preg_replace_callback(
			'#(?:<p class="caption">\s*)?\[audio:([^\]]+)\](?:\s*</p>)?#i',
			static function ( $m ) {
				$out = '';
				// Audio Player allowed several files separated by commas, options after a pipe.
				foreach ( explode( ',', explode( '|', $m[1] )[0] ) as $file ) {
					$file = trim( $file );
					if ( '' === $file ) {
						continue;
					}
					$url  = preg_match( '#^https?://#i', $file ) ? dmdune_https( $file ) : site_url( '/audio/' . rawurlencode( $file ) );
					$name = preg_replace( '#\.\w+$#', '', str_replace( '_', ' ', basename( $file ) ) );
					$out .= sprintf(
						'<figure class="dm-audio"><audio controls preload="none" src="%1$s"></audio><figcaption>♪ %2$s</figcaption></figure>',
						esc_url( $url ),
						esc_html( $name )
					);
				}
				return "\n\n" . $out . "\n\n";
			},
			$content
		);
	}
	if ( false === stripos( $content, '<spoiler' ) ) {
		return $content;
	}
	return preg_replace_callback(
		'#<spoiler\b[^>]*>(.*?)</spoiler>#is',
		static function ( $m ) {
			return "\n\n<details class=\"dm-spoiler\"><summary>" . esc_html__( 'Spoiler · tap to reveal', 'dmdune' ) . "</summary>\n\n" . trim( $m[1] ) . "\n\n</details>\n\n";
		},
		$content
	);
}
add_filter( 'the_content', 'dmdune_legacy_before_autop', 5 );

/**
 * A click-to-play YouTube placeholder: no YouTube request until the reader asks for the video.
 */
function dmdune_video_facade( string $id ): string {
	$id = preg_replace( '/[^A-Za-z0-9_-]/', '', $id );
	return sprintf(
		'<figure class="dm-video"><button type="button" class="dm-video__play" data-yt="%1$s" aria-label="%2$s"><img src="https://i.ytimg.com/vi/%1$s/hqdefault.jpg" alt="" loading="lazy" decoding="async"><span class="dm-video__btn">%3$s</span></button><figcaption><a href="https://www.youtube.com/watch?v=%1$s">%4$s</a><span class="dm-video__gone">%5$s</span></figcaption></figure>',
		esc_attr( $id ),
		esc_attr__( 'Play video', 'dmdune' ),
		dmdune_icon( 'play', 28 ),
		esc_html__( 'Watch on YouTube', 'dmdune' ),
		esc_html__( 'This video seems to have been removed from YouTube.', 'dmdune' )
	);
}

/**
 * After wpautop and shortcodes.
 */
function dmdune_legacy_after_autop( string $content ): string {
	if ( is_admin() || is_feed() ) {
		return $content;
	}

	// Flash players. An <object> on its own line arrives wrapped in <p>/<div>; drop that wrapper.
	$content = preg_replace_callback(
		'#(?:<p>\s*)?(<object\b.*?</object>|<embed\b[^>]*>(?:\s*</embed>)?)(?:\s*</p>)?#is',
		static function ( $m ) {
			if ( preg_match( '#youtube(?:-nocookie)?\.com/(?:v|embed)/([A-Za-z0-9_-]{6,})#i', $m[1], $id ) ) {
				return dmdune_video_facade( $id[1] );
			}
			return '<p class="dm-gone">' . esc_html__( 'This post embedded a Flash video that no longer plays in modern browsers.', 'dmdune' ) . '</p>';
		},
		$content
	);

	// Captioned images: <p class="caption"> + leading image(s) + optional text → <figure>.
	$content = preg_replace_callback(
		'#<p class="caption">\s*((?:(?:<a\b[^>]*>\s*)?<img\b[^>]*>(?:\s*</a>)?\s*(?:<br\s*/?>\s*)?)+)(.*?)</p>#is',
		static function ( $m ) {
			$media   = preg_replace( '#<br\s*/?>#i', '', $m[1] );
			$caption = trim( preg_replace( '#^(?:<br\s*/?>\s*)+|(?:<br\s*/?>\s*)+$#i', '', trim( $m[2] ) ) );
			$out     = '<figure class="dm-figure">' . $media;
			if ( '' !== $caption ) {
				$out .= '<figcaption>' . $caption . '</figcaption>';
			}
			return $out . '</figure>';
		},
		$content
	);

	// Images: drop fixed sizing, lazy-load, https.
	$content = preg_replace_callback(
		'#<img\b[^>]*>#i',
		static function ( $m ) {
			$tag = $m[0];
			// Some old tags have no space before style= (alt="…"style="width:283px").
			$tag = preg_replace( '#(?<=["\'\s])style=(["\'])[^"\']*\1#i', ' ', $tag );
			$tag = preg_replace_callback(
				'#\ssrc=(["\'])(.*?)\1#i',
				static fn( $s ) => ' src="' . esc_url( dmdune_override_image( dmdune_https( html_entity_decode( $s[2] ) ), 'content' ) ) . '"',
				$tag
			);
			// Icons injected by plugins deleted long ago (e.g. Post Thumb's youtube.png) now 404.
			if ( preg_match( '#\ssrc="[^"]*/wp-content/plugins/#i', $tag ) ) {
				return '';
			}
			// A replaced image can't keep WordPress's srcset/sizes for the old file.
			if ( preg_match( '#/assets/brand/#', $tag ) ) {
				$tag = preg_replace( '#\s(?:srcset|sizes|width|height)=(["\'])[^"\']*\1#i', '', $tag );
				$tag = preg_replace( '#^<img\b#i', '<img width="320" height="320"', $tag );
			}
			if ( ! preg_match( '#\sloading=#i', $tag ) ) {
				$tag = preg_replace( '#^<img\b#i', '<img loading="lazy"', $tag );
			}
			if ( ! preg_match( '#\sdecoding=#i', $tag ) ) {
				$tag = preg_replace( '#^<img\b#i', '<img decoding="async"', $tag );
			}
			return $tag;
		},
		$content
	);

	// Links to the site's own hosts → https; old lightbox/thickbox links → the theme's viewer.
	$content = preg_replace_callback(
		'#<a\b[^>]*>#i',
		static function ( $m ) {
			$tag = preg_replace_callback(
				'#\shref=(["\'])(.*?)\1#i',
				static fn( $h ) => ' href="' . esc_url( dmdune_https( html_entity_decode( $h[2] ) ) ) . '"',
				$m[0]
			);
			if ( preg_match( '#\brel=["\'][^"\']*lightbox|\bclass=["\'][^"\']*thickbox#i', $tag ) ) {
				$tag = preg_replace( '#^<a\b#i', '<a data-dm-lightbox', $tag );
			}
			return $tag;
		},
		$content
	);

	// Glossary terms (<span class="meaning" title="…">) showed their meaning on hover; make them
	// focusable so a tap shows it too (CSS draws the tooltip from the title).
	$content = preg_replace( '#<span\b(?=[^>]*\bclass="meaning")(?![^>]*tabindex)#i', '<span tabindex="0"', $content );

	// Empty paragraphs left behind by the old editor (and by the Flash wrappers removed above).
	$content = preg_replace( '#<p>(?:\s|&nbsp;|<br\s*/?>)*</p>#i', '', $content );

	// 2000s ad and tracking scripts inside posts.
	$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $content );

	return $content;
}
add_filter( 'the_content', 'dmdune_legacy_after_autop', 20 );

/**
 * Excerpts are built from post text; spoilers shouldn't leak into card previews.
 */
add_filter(
	'get_the_excerpt',
	static function ( $excerpt, $post ) {
		if ( has_excerpt( $post ) ) {
			return $excerpt;
		}
		$text = $post->post_content;
		// The author's own teaser: everything before <!--more-->.
		if ( preg_match( '#^(.*?)<!--more(.*?)?-->#s', $text, $more ) && '' !== trim( wp_strip_all_tags( $more[1] ) ) ) {
			$text = $more[1];
		}
		$text = preg_replace( '#<spoiler\b[^>]*>.*?</spoiler>#is', '', $text );
		$text = preg_replace( '#\[audio:[^\]]*\]#i', '', $text );
		$text = preg_replace( '#<(p|div) class="caption">.*?</\1>#is', '', $text );
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		return wp_trim_words( $text, (int) apply_filters( 'excerpt_length', 34 ), '…' );
	},
	5,
	2
);
