#!/bin/sh
# Pull server-side changes (WordPress/plugin auto-updates, edits made on the server)
# for the tracked sites into the working tree. Excluded content (uploads, albums,
# lyric text, caches) is never downloaded. Review with `git status`, then commit.
#
# Pulled config files (wp-config.php etc.) stay gitignored.
set -e
cd "$(dirname "$0")/.."
[ -f .env ] || { echo "Missing .env: copy .env.example to .env" >&2; exit 1; }
. ./.env
HOST=${DEPLOY_HOST:?DEPLOY_HOST not set in .env}
ROOT=${DEPLOY_ROOT:-public_html}

# One transfer rooted at public_html so the anchored patterns in rsync-exclude.txt
# line up. First match wins: excluded content, then the tracked top-level whitelist.
set -- --exclude-from=tools/rsync-exclude.txt
for p in /blog/ /lyrics/ /gallery/ /fansub-faq/ /index.php /.htaccess /robots.txt /favicon.ico \
         /apple-touch-icon.png /403.php /404.php /googlef45eb971d0691abc.html /keybase.txt; do
  set -- "$@" --include="$p"
done
set -- "$@" --exclude='/*'

# HostGator drops SSH connections sporadically; rsync resumes, so just retry.
for i in 1 2 3 4; do
  if rsync -az "$@" -e 'ssh -o BatchMode=yes -o ConnectTimeout=30' "$HOST:$ROOT/" ./; then
    ok=1; break
  fi
  sleep $((i * 15))
done
[ -n "$ok" ] || { echo "pull failed" >&2; exit 1; }

git status --short
echo
echo "If the server changed tracked files, commit them (e.g. 'Sync from server')."
echo "When the commit matches the server exactly, move the tag: git tag -f deployed"
