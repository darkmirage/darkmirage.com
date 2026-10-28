<?php
/**
 * Page head and the skyline banner.
 *
 * @package soraven
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<link rel="preload" href="<?php echo esc_url( get_theme_file_uri( 'assets/fonts/newsreader-latin-opsz-normal.woff2' ) ); ?>" as="font" type="font/woff2" crossorigin>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'soraven' ); ?></a>
<header class="site-header">
	<?php soraven_skyline(); ?>
	<div class="masthead">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>>
			<img class="brand-mark" src="<?php echo esc_url( get_theme_file_uri( 'assets/brand/raven-logo.webp' ) ); ?>" width="72" height="72" alt="">
			<span class="brand-name"><?php bloginfo( 'name' ); ?></span>
		</a>
		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<p class="tagline"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>
	</div>
</header>
