<?php
// force UTF-8 Ø
if (!defined('WEBPATH')) die();
?>
<!DOCTYPE html>
<html lang="en">
<head><?php dmg_head('Password required · Gallery · DarkMirage', array('robots' => 'noindex')); ?></head>
<body>
<?php dmg_body_open(); ?>
	<header class="dmg-head">
		<p class="dm-hud-code">// LOCKED CHANNEL</p>
		<h1>This album is private</h1>
	</header>
	<div class="dmg-password"><?php printPasswordForm(isset($hint) ? $hint : null, isset($show) ? $show : false); ?></div>
<?php dmg_body_close(); ?>
</body>
</html>
