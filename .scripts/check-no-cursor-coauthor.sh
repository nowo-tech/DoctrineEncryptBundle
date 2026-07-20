#!/usr/bin/env sh
# Fail if git history contains Cursor agent Co-authored-by trailers (REQ-GIT-001).
set -eu

REF="${1:-HEAD}"

if [ ! -d .git ]; then
  echo "ERROR: .git not found — run from the bundle repository root (REQ-GIT-001)." >&2
  echo "GitLab-only copies synced without .git must be cloned or re-synced with git history." >&2
  exit 1
fi

if ! git rev-parse --verify "${REF}" >/dev/null 2>&1; then
  echo "ERROR: git ref not found: ${REF}" >&2
  exit 1
fi

TOPLEVEL="$(git rev-parse --show-toplevel 2>/dev/null || true)"
HERE="$(cd "$(dirname "$0")/.." && pwd -P)"
if [ -n "${TOPLEVEL}" ] && [ "$(cd "${TOPLEVEL}" && pwd -P)" != "${HERE}" ]; then
  echo "ERROR: git toplevel is not this bundle (found: ${TOPLEVEL}, expected: ${HERE})" >&2
  echo "Do not run from a parent monorepo checkout without a bundle-local .git." >&2
  exit 1
fi

PATTERN='(^Co-authored-by: Cursor <cursoragent@cursor.com>$|^Co-authored-by:.*cursoragent@cursor\.com| Co-authored-by: Cursor <cursoragent@cursor.com>| Co-authored-by:.*cursoragent@cursor\.com)'

# Use --no-replace-objects so local `git replace` refs cannot hide dirty history
# that CI (fresh clone) would still see.
MATCHES="$(
  git --no-replace-objects log "${REF}" --format=%B \
    | grep -E "${PATTERN}" \
    || true
)"

if [ -n "${MATCHES}" ]; then
  echo "ERROR: Cursor co-author trailers found in git history (ref: ${REF})" >&2
  echo "Offending commits:" >&2
  git --no-replace-objects log "${REF}" --format='%H %s' | while read -r hash subject; do
    if git --no-replace-objects log -1 --format=%B "${hash}" | grep -qE 'cursoragent@cursor\.com'; then
      echo "  ${hash} ${subject}" >&2
    fi
  done
  echo "Run: git --no-replace-objects log ${REF} --format=%B | grep -i co-authored-by" >&2
  echo "${MATCHES}" | head -5 >&2
  exit 1
fi

echo "OK: no Cursor co-author trailers in git history (ref: ${REF})"
