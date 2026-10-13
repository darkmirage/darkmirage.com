<?php
// force UTF-8 Ø
if (!defined('WEBPATH')) die();

$albums = dmg_sorted_albums($_zp_gallery->getAlbums(0));
$years = array();
foreach ($albums as $a) {
	$y = dmg_when($a)[1];
	$years[$y] = ($years[$y] ?? 0) + 1;
}
$n_photos = $_zp_gallery->getNumImages(2);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php dmg_head(getBareGalleryTitle() . ' · DarkMirage', 'Photos by DarkMirage from Japan, Scandinavia, Singapore and Stanford, 2006–2011.'); ?></head>
<body>
<?php dmg_body_open(); ?>
	<header class="dm-archive-head">
		<div class="dm-archive-head__text">
			<p class="dm-hud-code">// GALLERY · FIELD RECORDINGS</p>
			<h1>Photos <span class="dm-archive-head__ja" lang="ja">写真</span></h1>
			<p class="dm-meta"><?php echo dmc_e(count($albums) . ' albums · ' . number_format($n_photos) . ' photos · ' . ($years ? min(array_keys($years)) . '–' . max(array_keys($years)) : '')); ?></p>
		</div>
		<div class="dm-archive-head__spec"><?php echo dmg_spectrum($years, 'albums'); ?></div>
	</header>
	<ul class="dmg-albums">
		<?php foreach ($albums as $a) echo dmg_album_card($a); ?>
	</ul>
<?php dmg_body_close(); ?>
</body>
</html>
