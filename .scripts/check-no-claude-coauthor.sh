#!/usr/bin/env sh
# Fail if commits after the Claude baseline carry Claude / anthropic.com Co-authored-by trailers (REQ-GIT-001).
# Older history is accepted as-is (no force-push / retag); the baseline is `.scripts/claude-coauthor-baseline`.
set -eu

REF="${1:-HEAD}"
HERE="$(cd "$(dirname "$0")/.." && pwd -P)"
BASELINE_FILE="${HERE}/.scripts/claude-coauthor-baseline"
PATTERN='co-authored-by:.*(claude|anthropic\.com)'

RANGE="${REF}"
if [ -f "${BASELINE_FILE}" ]; then
  BASE="$(tr -d '[:space:]' < "${BASELINE_FILE}")"
  # A rewritten history (strip script) no longer contains the baseline: audit everything then.
  if [ -n "${BASE}" ] && git --no-replace-objects merge-base --is-ancestor "${BASE}" "${REF}" 2>/dev/null; then
    RANGE="${BASE}..${REF}"
  fi
fi

BAD="$(git --no-replace-objects log "${RANGE}" --format='%H %s' | while read -r hash subject; do
  if git --no-replace-objects log -1 --format=%B "${hash}" | grep -qiE "${PATTERN}"; then
    echo "  ${hash} ${subject}"
  fi
done)"

if [ -n "${BAD}" ]; then
  echo "ERROR: Claude co-author trailers found in commits ${RANGE} (REQ-GIT-001):" >&2
  echo "${BAD}" >&2
  echo "Amend them (or run the commit-msg hook: make setup-hooks) before pushing." >&2
  exit 1
fi
echo "OK: no Claude co-author trailers in ${RANGE}"
