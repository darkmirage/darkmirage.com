<?php
// force UTF-8 Ø
if (!defined('WEBPATH')) die();

list($stamp, $year) = dmg_when($_zp_current_album);
$n_img = getNumImages();
$n_alb = getNumAlbums();
$desc = trim(strip_tags(getAlbumDesc()));
?>
<!DOCTYPE html>
<html lang="en">
<head><?php dmg_head(getBareAlbumTitle() . ' · Gallery · DarkMirage', $desc ?: getBareAlbumTitle() . ', photos by DarkMirage.'); ?></head>
<body>
<?php dmg_body_open(); ?>
	<header class="dmg-head" data-era="<?php echo dmg_era($year); ?>">
		<?php echo dmg_crumbs(dmg_parents($_zp_current_album)); ?>
		<h1><?php echo dmg_e(getBareAlbumTitle()); ?></h1>
		<?php if ($desc) { ?><div class="dmg-desc"><?php echo getAlbumDesc(); ?></div><?php } ?>
		<p class="dm-meta"><?php
			$bits = array('REC ' . $stamp);
			if ($n_alb) $bits[] = $n_alb . ($n_alb === 1 ? ' album' : ' albums');
			if ($n_img) $bits[] = $n_img . ($n_img === 1 ? ' photo' : ' photos');
			echo dmc_e(implode(' · ', $bits));
		?></p>
	</header>
	<?php if ($n_alb) { ?>
		<ul class="dmg-albums"><?php foreach (dmg_sorted_albums($_zp_current_album->getAlbums(0)) as $a) echo dmg_album_card($a); ?></ul>
	<?php } ?>
	<?php echo dmg_image_grid(); ?>
	<?php echo dmg_pager(); ?>
<?php dmg_body_close(); ?>
</body>
</html>
