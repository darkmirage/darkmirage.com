<?php
// force UTF-8 Ø

class ThemeOptions {

	function __construct() {
		setThemeOptionDefault('albums_per_page', 48);
		setThemeOptionDefault('albums_per_row', 3);
		setThemeOptionDefault('images_per_page', 60);
		setThemeOptionDefault('images_per_row', 5);
		setThemeOptionDefault('thumb_size', 480);
		setThemeOptionDefault('thumb_crop', 1);
		setThemeOptionDefault('thumb_crop_width', 480);
		setThemeOptionDefault('thumb_crop_height', 360);
		setThemeOptionDefault('image_size', 1600);
		setThemeOptionDefault('image_use_side', 'longest');
		setThemeOptionDefault('Allow_search', true);
	}

	function getOptionsSupported() {
		return array();
	}

}
