<?php
// force UTF-8 Ø
if (!defined('WEBPATH')) die();

$album = $_zp_current_album;
$total = getNumImages();
$num = imageNumber();
$date = getImageDate('Y.m.d');
$prev = hasPrevImage() ? getPrevImageURL() : '';
$next = hasNextImage() ? getNextImageURL() : '';
$is_photo = $_zp_current_image->isPhoto();
$full = $is_photo ? getFullImageURL() : '';
$title = getBareImageTitle();
$desc = getImageDesc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$named = !dmg_is_filename($title);
$label = $named ? $title . ' · ' . getBareAlbumTitle() : getBareAlbumTitle() . ' · photo ' . $num . ' of ' . $total;
$w = (int) $_zp_current_image->getWidth();
$h = (int) $_zp_current_image->getHeight();
$scale = ($w && $h) ? min(1, 1600 / max($w, $h)) : 1;
dmg_head($label . ' · DarkMirage Gallery', array(
	'title'       => $label,
	'description' => trim(strip_tags($desc)) ?: ($named ? $title . ' · ' : 'Photo ' . $num . ' of ' . $total . ' from ') . getBareAlbumTitle() . ', taken ' . getImageDate('j F Y') . ' by DarkMirage.',
	'url'         => dmg_abs(getImageURL()),
	'type'        => 'article',
	'published'   => getImageDate('c'),
	'image'       => $is_photo ? dmg_abs(getCustomSizedImageMaxSpace(1600, 1200)) : null,
	'image_w'     => $is_photo && $w ? (int) round($w * $scale) : null,
	'image_h'     => $is_photo && $h ? (int) round($h * $scale) : null,
	'image_alt'   => $label,
	'jsonld'      => $is_photo ? array('@context' => 'https://schema.org', '@type' => 'Photograph', 'name' => $label, 'url' => dmg_abs(getImageURL()), 'image' => dmg_abs(getCustomSizedImageMaxSpace(1600, 1200)), 'dateCreated' => getImageDate('c'), 'creator' => dmc_person(dmg_chrome()), 'isPartOf' => dmg_abs(getAlbumURL())) : null,
));
?>
<?php if ($prev) { ?><link rel="prev" href="<?php echo html_encode($prev); ?>"><?php } ?>
<?php if ($next) { ?><link rel="next" href="<?php echo html_encode($next); ?>"><?php } ?>
</head>
<body class="dmg-viewer-page">
<?php dmg_body_open(); ?>
	<header class="dmg-head dmg-head--image">
		<?php echo dmg_crumbs(dmg_parents($album) + array(getBareAlbumTitle() => getAlbumURL())); ?>
		<div class="dmg-head__row">
			<h1><?php echo dmg_e($title); ?></h1>
			<p class="dmg-count"><b><?php echo $num; ?></b> / <?php echo $total; ?></p>
		</div>
	</header>
	<figure class="dmg-viewer">
		<div class="dmg-viewer__stage">
			<?php if ($is_photo) { ?>
				<a href="<?php echo html_encode(pathurlencode($full)); ?>" title="Open the original">
					<img src="<?php echo html_encode(getCustomSizedImageMaxSpace(1600, 1200)); ?>" alt="<?php echo dmg_e($title); ?>">
				</a>
			<?php } else {
				printDefaultSizedImage($title);
			} ?>
			<?php if ($prev) { ?><a class="dmg-viewer__nav dmg-viewer__nav--prev" href="<?php echo html_encode($prev); ?>" aria-label="Previous photo"><span>←</span></a><?php } ?>
			<?php if ($next) { ?><a class="dmg-viewer__nav dmg-viewer__nav--next" href="<?php echo html_encode($next); ?>" aria-label="Next photo"><span>→</span></a><?php } ?>
		</div>
		<figcaption class="dmg-viewer__cap">
			<span class="dmg-viewer__rec">REC <?php echo dmc_e($date); ?></span>
			<?php if (trim(strip_tags($desc))) { ?><span class="dmg-viewer__desc"><?php echo $desc; ?></span><?php } ?>
			<?php if ($full) { ?><a class="dmg-viewer__full" href="<?php echo html_encode(pathurlencode($full)); ?>">Original</a><?php } ?>
		</figcaption>
	</figure>
	<nav class="dmg-strip" aria-label="Photos in this album">
		<?php
		$images = $album->getImages(0);
		$at = $num - 1;
		$from = max(0, min($at - 4, count($images) - 9));
		foreach (array_slice($images, $from, 9) as $i => $file) {
			$img = Image::newImage($album, $file);
			printf(
				'<a href="%s"%s><img src="%s" alt="" loading="lazy" width="160" height="120"></a>',
				html_encode($img->getLink()),
				$from + $i === $at ? ' aria-current="true"' : '',
				html_encode($img->getCustomImage(null, 160, 120, 160, 120, null, null, true))
			);
		}
		?>
	</nav>
<?php dmg_body_close(); ?>
<script>
document.addEventListener('keydown', function (e) {
	if (e.target.closest('input, textarea') || e.metaKey || e.ctrlKey || e.altKey) return;
	var rel = e.key === 'ArrowLeft' ? 'prev' : e.key === 'ArrowRight' ? 'next' : '';
	var link = rel && document.querySelector('link[rel="' + rel + '"]');
	if (link) location.href = link.href;
});
</script>
</body>
</html>
