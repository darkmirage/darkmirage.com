<?php
// force UTF-8 Ø
/**
 * gallery.darkmirage.com theme. The header, footer, tokens and HUD pieces come from the blog
 * theme's chrome (public_html/blog/wp-content/themes/dmdune/chrome), so all three sites match.
 */

require_once SERVERPATH . '/../blog/wp-content/themes/dmdune/chrome/chrome.php';

enableExtension('zenpage', 0, false);
$_zp_page_check = 'checkPageValidity';

// Runtime only (not saved), so these hold before the theme's options exist and vanish if it is switched off.
setOption('albums_per_page', 60, false);
setOption('images_per_page', 60, false);

function dmg_chrome(): array {
	global $_zp_conf_vars;
	return array_merge(
		array(
			'site'        => 'gallery',
			'placeholder' => 'Search photos',
			'note'        => 'Photos by DarkMirage · 2005–2011',
		),
		$_zp_conf_vars['dm_chrome'] ?? array()
	);
}

function dmg_e($s): string {
	return dmc_e(html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function dmg_era(int $y): string {
	return $y <= 2004 ? 'b2' : ($y <= 2005 ? 'default' : ($y <= 2007 ? 'kubrick' : 'blocks'));
}

/**
 * <head> contents shared by every template.
 */
function dmg_head(string $title, array $meta = array()): void {
	global $_zp_themeroot;
	$c = dmg_chrome();
	?>
	<meta charset="<?php echo LOCAL_CHARSET; ?>">
	<title><?php echo dmg_e($title); ?></title>
	<?php dmc_meta($c, $meta + array('title' => $title)); ?>
	<?php zp_apply_filter('theme_head'); ?>
	<?php dmc_head($c); ?>
	<link rel="stylesheet" href="<?php echo html_encode($_zp_themeroot); ?>/gallery.css?v=<?php echo DMC_VERSION; ?>">
	<?php if (class_exists('RSS')) printRSSHeaderLink('Gallery', gettext('Gallery RSS')); ?>
	<?php
}

/**
 * Absolute URL on the gallery host for a root-relative Zenphoto link.
 */
function dmg_abs(string $url): string {
	return 0 === strpos($url, '/') ? rtrim(dmc_config(dmg_chrome())['gallery'], '/') . $url : $url;
}

/**
 * A 1200×630 crop of an image for share previews.
 */
function dmg_share_image($image, string $alt): array {
	return array(
		'image'     => dmg_abs($image->getCustomImage(null, 1200, 630, 1200, 630, null, null, true)),
		'image_w'   => 1200,
		'image_h'   => 630,
		'image_alt' => $alt,
	);
}

/**
 * Camera file names ("IMG_5208") aren't titles.
 */
function dmg_is_filename(string $title): bool {
	return (bool) preg_match('/^(?:IMG|DSC[NF]?|P\d|DSCF|CIMG|PICT|_MG)[_-]?\d+/i', $title) || preg_match('/^\d+$/', $title);
}

function dmg_body_open(): void {
	zp_apply_filter('theme_body_open');
	dmc_header(dmg_chrome());
	echo '<main id="dm-main" class="dmg-main">';
}

function dmg_body_close(): void {
	echo '</main>';
	dmc_footer(dmg_chrome());
	zp_apply_filter('theme_body_close');
}

/**
 * Crumbs above a title: "// GALLERY / Japan 2011 / Nagasaki".
 */
function dmg_crumbs(array $extra = array()): string {
	$out = array('<a href="' . html_encode(getGalleryIndexURL()) . '">Gallery</a>');
	foreach ($extra as $label => $url) {
		$out[] = $url ? '<a href="' . html_encode($url) . '">' . dmg_e($label) . '</a>' : dmg_e($label);
	}
	return '<p class="dm-hud-code dmg-crumbs">// ' . implode(' <span>/</span> ', $out) . '</p>';
}

/**
 * Parent albums of an album, oldest ancestor first, as label => URL.
 */
function dmg_parents($album): array {
	$chain = array();
	$p = $album->getParent();
	while ($p) {
		$chain = array($p->getTitle() => $p->getLink()) + $chain;
		$p = $p->getParent();
	}
	return $chain;
}

/**
 * Albums added per year, as the blog's spectrum.
 *
 * @param int[] $years year => count
 */
function dmg_spectrum(array $years, string $unit): string {
	if (!$years) {
		return '';
	}
	$max = max($years);
	$cols = '';
	for ($y = min(array_keys($years)); $y <= max(array_keys($years)); $y++) {
		$n = $years[$y] ?? 0;
		$cols .= sprintf(
			'<span class="dm-spec__col%s is-tick" data-era="%s" title="%d · %d %s"><span class="dm-spec__bar" style="--h:%.3f"></span><span class="dm-spec__tick">\'%s</span></span>',
			$n ? '' : ' is-empty', dmg_era($y), $y, $n, $unit, $n ? max(0.06, $n / $max) : 0, substr((string) $y, 2)
		);
	}
	return '<div class="dm-spec dm-spec--years">' . $cols . '</div>';
}

/**
 * When an album was shot. The stored album dates are all the 2010 import, so use the earliest
 * photo in it or its sub-albums; a year in the folder name ("2005tokyo") wins over a camera
 * clock that disagrees.
 *
 * @return array{0:string,1:int} display stamp, year
 */
function dmg_when($album): array {
	global $_zp_db;
	static $memo = array();
	$folder = $album->name;
	if (isset($memo[$folder])) {
		return $memo[$folder];
	}
	$row = $_zp_db->querySingleRow(
		'SELECT MIN(i.date) AS d FROM ' . $_zp_db->prefix('images') . ' i JOIN ' . $_zp_db->prefix('albums') . ' a ON a.id = i.albumid'
		. ' WHERE a.folder = ' . $_zp_db->quote($folder) . ' OR a.folder LIKE ' . $_zp_db->quote($folder . '/%')
	);
	$date = $row && $row['d'] ? $row['d'] : (string) $album->getDateTime();
	$y = (int) substr($date, 0, 4);
	$stamp = str_replace('-', '.', substr($date, 0, 10));
	if (preg_match('~(?:^|/)((?:19|20)\d\d)~', $folder, $m) && (int) $m[1] !== $y) {
		$y = (int) $m[1];
		$stamp = (string) $y;
	}
	return $memo[$folder] = array($stamp, $y);
}

/**
 * Albums newest-shot first.
 *
 * @param string[] $folders
 * @return array list of album objects
 */
function dmg_sorted_albums(array $folders): array {
	$albums = array_map(static fn($f) => AlbumBase::newAlbum($f), $folders);
	usort($albums, static fn($a, $b) => strcmp(dmg_when($b)[0] . '~', dmg_when($a)[0] . '~'));
	return $albums;
}

/**
 * One album as a recorded frame: cover, title, date, counts.
 */
function dmg_album_card($album): string {
	list($stamp, $y) = dmg_when($album);
	$n_img = $album->getNumImages();
	$n_alb = $album->getNumAlbums();
	$counts = array();
	if ($n_alb) {
		$counts[] = $n_alb . ($n_alb === 1 ? ' album' : ' albums');
	}
	if ($n_img) {
		$counts[] = $n_img . ($n_img === 1 ? ' photo' : ' photos');
	}
	$thumb = $album->getAlbumThumbImage();
	return sprintf(
		'<li class="dmg-album" data-era="%1$s"><a href="%2$s"><figure class="dm-frame dmg-album__cover"><img src="%3$s" alt="" loading="lazy" width="600" height="450"><span class="dm-frame__scan"></span><span class="dm-frame__rec">REC %4$s</span></figure><span class="dmg-album__text"><span class="dmg-album__title">%5$s</span><span class="dmg-album__meta">%6$s</span></span></a></li>',
		dmg_era($y ?: 2008),
		html_encode($album->getLink()),
		html_encode($thumb->getCustomImage(null, 600, 450, 600, 450, null, null, true)),
		dmg_e($stamp),
		dmg_e($album->getTitle()),
		dmg_e(implode(' · ', $counts))
	);
}

/**
 * Previous / page numbers / next for album and search pages.
 */
function dmg_pager(): string {
	$total = getTotalPages();
	if ($total < 2) {
		return '';
	}
	$cur = getCurrentPage();
	$out = '<nav class="dmg-pager" aria-label="Pages">';
	$out .= $cur > 1 ? '<a href="' . html_encode(getPrevPageURL()) . '" rel="prev">← Newer</a>' : '<span></span>';
	$out .= '<span class="dmg-pager__nums">';
	for ($i = 1; $i <= $total; $i++) {
		$out .= $i === $cur ? '<b aria-current="page">' . $i . '</b>' : '<a href="' . html_encode(getPageNumURL($i)) . '">' . $i . '</a>';
	}
	$out .= '</span>';
	$out .= $cur < $total ? '<a href="' . html_encode(getNextPageURL()) . '" rel="next">More →</a>' : '<span></span>';
	return $out . '</nav>';
}

/**
 * The thumbnail grid for the images of the current album or search.
 */
function dmg_image_grid(): string {
	$out = '';
	while (next_image()) {
		$out .= sprintf(
			'<li><a href="%s" title="%s"><img src="%s" alt="%s" loading="lazy" width="480" height="360"></a></li>',
			html_encode(getImageURL()),
			dmg_e(getBareImageTitle()),
			html_encode(getCustomImageURL(null, 480, 360, 480, 360, null, null, true)),
			dmg_e(getBareImageTitle())
		);
	}
	return $out ? '<ul class="dmg-grid">' . $out . '</ul>' : '';
}
