# DarkMirage Dune (`dmdune`)

The 2026 block theme for Ramblings of DarkMirage, after Kubrick DM (2006), dmbrickv2 (2006–07) and
DarkMirage Blocks (2007–2016). Desert banner that turns to night in dark mode, rounded blocks and
mono HUD labels, Kiro the shiba.

## Licences

- Code (PHP, templates, CSS, JS, `theme.json`): GPL-2.0-or-later.
- `assets/brand/` (banner scenes, Kiro, roundel marks, brigade patch): all rights reserved. See
  `assets/brand/LICENSE.md`.
- `assets/fonts/`: Chakra Petch and JetBrains Mono, SIL OFL 1.1 (licence files alongside).
- `assets/flags/`: country flags from flag-icons 7.2.3, MIT (`assets/flags/LICENSE.txt`). Only the
  countries that appear in the blog's comments are included.

## What it does with the old posts

The 2005–2013 posts are left untouched in the database; `inc/legacy-content.php` fixes them as
they render:

| Old markup | Rendered as |
|---|---|
| `<spoiler>…</spoiler>` | tap-to-reveal `<details>` |
| `<p class="caption"><img>text</p>` | `<figure>` with a caption |
| Flash `<object>` YouTube players | click-to-play YouTube (nocookie); a note if the video is gone |
| other Flash | a "no longer plays" note |
| fixed 400px image sizes, `http://` links to the site | responsive, lazy-loaded images; https |
| `rel="lightbox"` / `thickbox` links | the theme's own image viewer |
| `class="strike"` | struck through in brigade red |
| icons from deleted plugins, ad scripts | removed |

Card thumbnails come from each post's first image (no featured images are written).

Commenters get a country flag, as the old IP to Nation plugin did: their stored IP is looked up in
the `ip2nation` tables that plugin left in the database (no new data is imported).

## Layout

- `templates/`: `home` (banner, post grid with the newest post leading, year strip, most
  discussed), `single`, `page`, `archive`, `search`, `404`, plus `page-archives` and `page-tag`,
  which WordPress picks for the pages with slugs `archives` and `tag`.
- `inc/blocks.php`: server-rendered blocks used by the templates (header, footer and mobile tab
  bar, banner, card, year strip, archive index, related posts, …). Editor previews use
  ServerSideRender (`assets/js/editor.js`), so there is no build step.
- Light/dark follows the OS until the reader picks one; the choice is saved in `localStorage`.

## Rebuilding the art

The banner layers are exported from the working files in
`~/Documents/hostgator/theme-archaeology/mocks/hifi/` (not in this repo) with
`tools/build_layers.py` and `tools/export_brand.py` there.
