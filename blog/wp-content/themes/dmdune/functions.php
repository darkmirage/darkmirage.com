<?php
/**
 * DarkMirage Dune theme bootstrap.
 *
 * @package dmdune
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/chrome/chrome.php';

// One number for the blog and the shared chrome, so every asset URL changes on each release.
define( 'DMDUNE_VERSION', DMC_VERSION );
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/setup.php';
require __DIR__ . '/inc/legacy-content.php';
require __DIR__ . '/inc/units.php';
require __DIR__ . '/inc/blocks.php';
require __DIR__ . '/inc/meta.php';
require __DIR__ . '/inc/skins.php';
require __DIR__ . '/inc/b2.php';
require __DIR__ . '/inc/featured.php';
