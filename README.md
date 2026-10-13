# darkmirage.com

Code for the active darkmirage.com sites on HostGator. The repo root maps 1:1 to the server docroot set in `.env` (`DEPLOY_HOST`, `DEPLOY_ROOT`). Copy `.env.example` to `.env` to use the tools.

| Path | Site |
|---|---|
| `blog/` + root `index.php`, `.htaccess` | www.darkmirage.com (WordPress core only) |
| `lyrics/` | lyrics.darkmirage.com (PHP + MySQL) |
| `gallery/` | gallery.darkmirage.com (Zenphoto 1.6.8) |
| `fansub-faq/` | fansub-faq.darkmirage.com |

## What is *not* in git

Server-managed content stays on the server, and the deploy tool never touches it:

- WordPress **themes and plugins** (`blog/wp-content/themes/`, `plugins/`): managed on the server via WP admin or WP-CLI (over SSH: `cd <docroot>/blog && wp plugin list`). Copies are in the backup mirror. lyrics.darkmirage.com loads CSS and art from `themes/default2`, so keep that theme on the server.
- WordPress uploads (`blog/wp-content/uploads/`, 2006-era images loose in `blog/wp-content/`, `blog/audio/`), gallery albums, `lyrics/txt/` (lyric text and cover art; song metadata is in MySQL)
- Config files with DB passwords: `wp-config.php`, `zp-config.php`, `lyrics/config.php`. Local copies sit in the working tree but are gitignored.
- Caches, logs, commenter gravatars
- Everything else in `public_html` (old projects, offline blogs, `stuff/`)

`.gitignore` defines what git tracks; `tools/rsync-exclude.txt` mirrors its content exclusions so `tools/pull.sh` never downloads them. Keep the two in sync.

A full server mirror lives at `../backups/public_html_2026-10-12/`. See `../public_html_inventory_2026-10-12.md` for what each folder is. `rib/`, `stanfordvisualized/`, `hirefaq.com/` and `ethereumpixels/` have their own GitHub repos.

## Workflow

```
tools/pull.sh                 # bring down server-side changes (WP auto-updates etc.)
git commit ...                # review and commit
tools/deploy.py               # dry run: lists files to upload, checks the server
tools/deploy.py --go          # upload
```

How `deploy.py` keeps server content safe:

- It uploads only files changed in git since the `deployed` tag, exported from the commit (`git archive`). It never does a directory sync.
- It never deletes anything unless `--delete-removed` is given, and then only files removed from git.
- Before writing, it checks that each target file on the server still matches the `deployed` version. If WordPress auto-updated a file, or it exists untracked on the server, the deploy aborts instead of overwriting. Run `tools/pull.sh`, commit, retry.
- After uploading it verifies checksums, then moves the `deployed` tag.
- Repo-only files (`README.md`, `LICENSE`, `.gitignore`, `.env.example`, `tools/`) are never uploaded. `.env` is gitignored.

Never `rsync --delete` into the server's `public_html`.

## License

GPL-2.0-or-later (see `LICENSE`). This matches the bundled third-party code, which keeps its own copyright and license terms:

- WordPress core (`blog/`): GPLv2 or later. See `blog/license.txt`.
- Zenphoto 1.6.8 (`gallery/`): GPLv2, with some bundled libraries under their own licenses. See `gallery/LICENSE` and the license files under `gallery/zp-core/`.

The original code here (`lyrics/` PHP, `tools/`) is released under the same license.

**Not covered:** site content kept out of git (lyric text and cover art, uploads, gallery photos, themes) isn't part of this repository or this license. The DarkMirage logo and banner (`favicon.ico`, `apple-touch-icon.png`, `lyrics/lyrics_banner.jpg`) are site branding; please don't reuse them to represent another site.
