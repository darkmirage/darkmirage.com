#!/usr/bin/env python3
"""Deploy committed changes to HostGator, touching only files git tracks.

    tools/deploy.py               dry run: show what would change
    tools/deploy.py --go          upload changed files
    tools/deploy.py --go --delete-removed   also delete files removed from git

Safety rules:
- Uploads only files changed between the `deployed` tag and HEAD, taken from the
  commit (git archive), never from the working tree. Uncommitted edits can't leak.
- Anything git doesn't track on the server (uploads, albums, wp-config.php and
  other secrets, inactive folders) is never written or deleted.
- Before writing, each target file on the server must still match its content at
  the `deployed` tag. If something changed it there (e.g. a WordPress auto-update),
  the deploy aborts; run tools/pull.sh, commit, and retry.
- After a successful deploy the `deployed` tag moves to HEAD.
"""
import argparse
import hashlib
import os
import shlex
import subprocess
import sys
import time


def load_env():
    path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".env")
    env = {}
    try:
        with open(path) as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env[k.strip()] = v.strip()
    except FileNotFoundError:
        sys.exit("Missing .env: copy .env.example to .env and fill in DEPLOY_HOST.")
    return env


ENV = load_env()
HOST = ENV.get("DEPLOY_HOST") or sys.exit("DEPLOY_HOST not set in .env")
REMOTE_ROOT = ENV.get("DEPLOY_ROOT", "public_html")
TAG = "deployed"
# Repo bookkeeping that must never land in public_html.
REPO_ONLY = (".gitignore", "README.md", "LICENSE", ".env.example", "tools/")


def git(*args, binary=False):
    out = subprocess.run(["git", *args], check=True, capture_output=True).stdout
    return out if binary else out.decode()


def ssh(cmd, stdin=None, retries=4):
    # HostGator drops SSH connections when several arrive close together.
    for attempt in range(retries):
        r = subprocess.run(["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=30", HOST, cmd],
                           input=stdin, capture_output=True)
        if r.returncode != 255:
            return r
        time.sleep(15 * (attempt + 1))
    sys.exit(f"SSH failed after {retries} attempts: {r.stderr.decode().strip()}")


def remote_md5s(paths):
    """Map path -> md5 for files that exist on the server (missing files are omitted)."""
    if not paths:
        return {}
    # Paths go over stdin: large deploys overflow the remote shell's argument limit.
    script = "cd " + shlex.quote(REMOTE_ROOT) + \
             ' && while IFS= read -r f; do [ -f "$f" ] && md5sum "$f"; done; true'
    r = ssh(script, stdin="\n".join(paths).encode() + b"\n")
    if r.returncode:
        sys.exit(f"md5 check failed: {r.stderr.decode()}")
    out = {}
    for line in r.stdout.decode().splitlines():
        digest, path = line.split(None, 1)
        out[path] = digest
    return out


def md5_at(rev, path):
    try:
        return hashlib.md5(git("show", f"{rev}:{path}", binary=True)).hexdigest()
    except subprocess.CalledProcessError:
        return None


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--go", action="store_true", help="actually deploy (default is a dry run)")
    ap.add_argument("--delete-removed", action="store_true", help="delete files that were removed from git")
    ap.add_argument("--force", action="store_true", help="skip the server-unchanged check (dangerous)")
    args = ap.parse_args()

    if git("status", "--porcelain").strip():
        sys.exit("Working tree has uncommitted changes. Commit or stash first.")
    try:
        base = git("rev-parse", TAG).strip()
    except subprocess.CalledProcessError:
        sys.exit(f"No `{TAG}` tag. Tag the commit that matches the server: git tag {TAG} <commit>")
    head = git("rev-parse", "HEAD").strip()
    if base == head:
        print("Nothing to deploy: HEAD is already deployed.")
        return

    changed, removed = [], []
    for line in git("diff", "--name-status", "--no-renames", base, head).splitlines():
        status, path = line.split("\t", 1)
        if path.startswith(REPO_ONLY) or path in REPO_ONLY:
            continue
        (removed if status == "D" else changed).append(path)

    print(f"Deploying {base[:7]}..{head[:7]}: {len(changed)} to upload, {len(removed)} removed in git")
    for p in changed:
        print(f"  upload  {p}")
    for p in removed:
        print(f"  {'delete' if args.delete_removed else 'keep  '}  {p}")

    # The server must still hold exactly what we last deployed, or nothing for new files.
    conflicts = []
    if not args.force:
        server = remote_md5s(changed + removed)
        for p in changed + removed:
            expected = md5_at(base, p)
            if server.get(p) != expected:
                conflicts.append((p, expected, server.get(p)))
    if conflicts:
        print("\nABORT: these server files changed since the last deploy (or exist untracked):")
        for p, exp, got in conflicts:
            print(f"  {p}: expected {exp or 'absent'}, server has {got or 'absent'}")
        sys.exit("Run tools/pull.sh, review and commit, then deploy again.")

    if not args.go:
        print("\nDry run OK. Re-run with --go to deploy.")
        return

    if changed:
        archive = git("archive", "--format=tar", head, "--", *changed, binary=True)
        r = ssh(f"cd {REMOTE_ROOT} && tar -xf -", stdin=archive)
        if r.returncode:
            sys.exit(f"Upload failed: {r.stderr.decode()}")
    if removed and args.delete_removed:
        # NUL-separated over stdin (no argument limit); then prune directories left empty.
        r = ssh(f"cd {shlex.quote(REMOTE_ROOT)} && xargs -0 rm -f --",
                stdin=b"\0".join(p.encode() for p in removed))
        if r.returncode == 0:
            dirs = sorted({os.path.dirname(p) for p in removed if os.path.dirname(p)}, key=len, reverse=True)
            ssh(f"cd {shlex.quote(REMOTE_ROOT)} && xargs -0 rmdir -p --ignore-fail-on-non-empty -- 2>/dev/null; true",
                stdin=b"\0".join(d.encode() for d in dirs))
        if r.returncode:
            sys.exit(f"Delete failed: {r.stderr.decode()}")

    # Verify the server now matches HEAD for everything we wrote.
    server = remote_md5s(changed)
    bad = [p for p in changed if server.get(p) != md5_at(head, p)]
    if bad:
        sys.exit(f"Verification failed for: {bad}. `{TAG}` tag not moved.")
    git("tag", "-f", TAG, head)
    print(f"\nDeployed and verified. `{TAG}` -> {head[:7]}")


if __name__ == "__main__":
    main()
