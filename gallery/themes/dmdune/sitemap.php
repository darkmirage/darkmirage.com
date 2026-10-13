<?php
// force UTF-8 Ø
/**
 * XML sitemap, served at /page/sitemap/ (listed in robots.txt): every album page and every photo,
 * with the photo as an image entry.
 */
if (!defined('WEBPATH')) die();

$entries = array();
$walk = function ($folders) use (&$walk, &$entries) {
	foreach ($folders as $folder) {
		$album = AlbumBase::newAlbum($folder);
		if (!$album->exists || $album->isProtected()) {
			continue;
		}
		$entries[] = array(dmg_abs($album->getLink()), str_replace('.', '-', substr((string) dmg_when($album)[0], 0, 10)), null);
		foreach ($album->getImages(0) as $file) {
			$image = Image::newImage($album, $file);
			$entries[] = array(dmg_abs($image->getLink()), substr((string) $image->getDateTime(), 0, 10), $image->isPhoto() ? dmg_abs($image->getCustomImage(null, 1600, 1600, null, null, null, null)) : null);
		}
		$walk($album->getAlbums(0));
	}
};
$walk($_zp_gallery->getAlbums(0));

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
printf("<url><loc>%s</loc></url>\n", htmlspecialchars(dmc_config(dmg_chrome())['gallery'], ENT_XML1));
foreach ($entries as list($loc, $mod, $img)) {
	printf(
		"<url><loc>%s</loc>%s%s</url>\n",
		htmlspecialchars($loc, ENT_XML1),
		preg_match('/^\d{4}-\d\d-\d\d$/', $mod) ? '<lastmod>' . $mod . '</lastmod>' : '',
		$img ? '<image:image><image:loc>' . htmlspecialchars($img, ENT_XML1) . '</image:loc></image:image>' : ''
	);
}
echo "</urlset>\n";
exit;
