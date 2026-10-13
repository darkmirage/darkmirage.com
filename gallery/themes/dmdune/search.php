<?php
// force UTF-8 Ø
if (!defined('WEBPATH')) die();

$words = getSearchWords();
$n_alb = getNumAlbums();
$n_img = getNumImages();
?>
<!DOCTYPE html>
<html lang="en">
<head><?php dmg_head(($words ? 'Search: ' . $words : 'Search') . ' · DarkMirage Gallery', array('robots' => 'noindex, follow')); ?></head>
<body>
<?php dmg_body_open(); ?>
	<header class="dmg-head">
		<?php echo dmg_crumbs(array('Search' => '')); ?>
		<h1><?php echo $words ? 'Scanning for “' . dmg_e($words) . '”' : 'Search the gallery'; ?></h1>
		<form class="dmg-search" role="search" method="get" action="<?php echo html_encode(dmc_config(dmg_chrome())['search']); ?>">
			<label class="screen-reader-text" for="dmg-words">Search photos</label>
			<?php echo dmc_icon('search', 16); ?>
			<input id="dmg-words" type="search" name="s" value="<?php echo dmg_e($words); ?>" placeholder="Album, place or caption">
			<button type="submit">Scan</button>
		</form>
		<?php if ($words) { ?><p class="dm-meta"><?php echo dmc_e($n_alb . ($n_alb === 1 ? ' album' : ' albums') . ' · ' . $n_img . ($n_img === 1 ? ' photo' : ' photos')); ?></p><?php } ?>
	</header>
	<?php if ($n_alb) { ?>
		<ul class="dmg-albums"><?php while (next_album()) { global $_zp_current_album; echo dmg_album_card($_zp_current_album); } ?></ul>
	<?php } ?>
	<?php echo dmg_image_grid(); ?>
	<?php if ($words && !$n_alb && !$n_img) { ?><p class="dmg-empty">No signal. Try a place name like “Kyoto” or “Stanford”.</p><?php } ?>
	<?php echo dmg_pager(); ?>
<?php dmg_body_close(); ?>
</body>
</html>
