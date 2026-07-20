#!/usr/bin/env sh
# Rewrite git history to remove Cursor agent Co-authored-by trailers (REQ-GIT-001).
# Use only when check-no-cursor-coauthor.sh fails and trailers are already pushed.
set -eu

REF="${1:-master}"
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
CHECK_SCRIPT="${SCRIPT_DIR}/check-no-cursor-coauthor.sh"

if ! git rev-parse --verify "${REF}" >/dev/null 2>&1; then
  echo "ERROR: git ref not found: ${REF}" >&2
  exit 1
fi

if [ ! -x "${CHECK_SCRIPT}" ]; then
  chmod +x "${CHECK_SCRIPT}"
fi

if "${CHECK_SCRIPT}" "${REF}" >/dev/null 2>&1; then
  echo "OK: ${REF} already has no Cursor co-author trailers; nothing to rewrite."
  exit 0
fi

echo "WARN: rewriting history for ref ${REF} (local only until force-push)." >&2
echo "WARN: coordinate with the team; tags and open MRs/PRs may need updating." >&2

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "ERROR: working tree is dirty. Commit or stash changes before rewriting history." >&2
  exit 1
fi

# git replace only hides dirty commits locally; it does not fix CI/remotes.
if [ -n "$(git replace -l 2>/dev/null || true)" ]; then
  echo "Removing local git replace refs before rewrite..." >&2
  # shellcheck disable=SC2046
  git replace -d $(git replace -l) >/dev/null 2>&1 || true
fi

FILTER_BRANCH_SQUELCH_WARNING=1 git filter-branch -f --msg-filter \
  'sed -e "/^Co-authored-by: Cursor <cursoragent@cursor.com>$/d" -e "/^Co-authored-by:.*cursoragent@cursor\.com$/d"' \
  "${REF}"

"${CHECK_SCRIPT}" "${REF}"

echo "OK: history rewritten for ${REF}."
echo "Next: git push --force-with-lease origin ${REF}"
echo "If you use release tags, recreate and force-push affected tags."
