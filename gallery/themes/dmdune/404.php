<?php
// force UTF-8 Ø
if (!defined('WEBPATH')) die();
?>
<!DOCTYPE html>
<html lang="en">
<head><?php dmg_head('Not found · Gallery · DarkMirage', array('robots' => 'noindex')); ?></head>
<body>
<?php dmg_body_open(); ?>
	<header class="dmg-head">
		<p class="dm-hud-code">// 404 · NO SIGNAL</p>
		<h1>That photo isn’t here</h1>
		<p class="dm-meta"><a href="<?php echo html_encode(getGalleryIndexURL()); ?>">Back to all albums</a></p>
	</header>
<?php dmg_body_close(); ?>
</body>
</html>
