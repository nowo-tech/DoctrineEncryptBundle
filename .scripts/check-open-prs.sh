#!/usr/bin/env bash
# Fail if the GitHub repo has unresolved open pull requests (REQ-REL-003).
# Allowed temporary exceptions: label hold|do-not-merge AND a future review-by ISO date
# in the PR body (body must carry the date).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
cd "${ROOT}"

if ! command -v gh >/dev/null 2>&1; then
  echo "ERROR: gh CLI is required (REQ-REL-003)." >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "ERROR: gh is not authenticated — log in for nowo-tech (REQ-REL-003)." >&2
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "ERROR: python3 is required to evaluate open PR exceptions (REQ-REL-003)." >&2
  exit 1
fi

# Resolve owner/name for -R (works when SSH remotes confuse `gh` default repo detection).
resolve_repo() {
  local url owner_repo
  if url="$(git remote get-url origin 2>/dev/null)"; then
    # git@github.com:org/repo.git  |  https://github.com/org/repo.git
    owner_repo="$(printf '%s\n' "${url}" | sed -E 's#^(git@github\.com:|https://github\.com/)##; s#\.git$##')"
    if [[ "${owner_repo}" == */* ]]; then
      printf '%s\n' "${owner_repo}"
      return 0
    fi
  fi
  return 1
}

REPO_ARGS=()
if REPO="$(resolve_repo)"; then
  REPO_ARGS=(-R "${REPO}")
fi

PRS_JSON="$(gh pr list "${REPO_ARGS[@]}" --state open --limit 100 --json number,title,labels,body,url)"

export PRS_JSON
python3 <<'PY'
import json
import os
import re
import sys
from datetime import date

prs = json.loads(os.environ.get("PRS_JSON") or "[]")
if not prs:
    print("OK: no open pull requests (REQ-REL-003)")
    sys.exit(0)

HOLD_LABELS = {"hold", "do-not-merge"}
REVIEW_BY_RE = re.compile(
    r"(?i)\breview[-_ ]?by\b\s*[:=]?\s*(\d{4}-\d{2}-\d{2})"
)

today = date.today()
unresolved = []
held_ok = []

for pr in prs:
    labels = {str(l.get("name", "")).lower() for l in (pr.get("labels") or [])}
    body = pr.get("body") or ""
    m = REVIEW_BY_RE.search(body)
    review_by = None
    if m:
        try:
            review_by = date.fromisoformat(m.group(1))
        except ValueError:
            review_by = None

    has_hold = bool(labels & HOLD_LABELS)
    hold_valid = has_hold and review_by is not None and review_by >= today

    entry = f"#{pr['number']} {pr.get('title') or ''} ({pr.get('url') or ''})"
    if hold_valid:
        held_ok.append(f"{entry} [hold until {review_by.isoformat()}]")
    else:
        reasons = []
        if not has_hold:
            reasons.append("missing hold/do-not-merge label")
        if review_by is None:
            reasons.append("missing review-by YYYY-MM-DD in body")
        elif review_by < today:
            reasons.append(f"review-by {review_by.isoformat()} expired")
        unresolved.append(f"{entry} — {', '.join(reasons)}")

if held_ok:
    print("Allowed hold exceptions:")
    for line in held_ok:
        print(f"  {line}")

if unresolved:
    print("ERROR: unresolved open pull requests (REQ-REL-003):", file=sys.stderr)
    for line in unresolved:
        print(f"  {line}", file=sys.stderr)
    print(
        "Merge, close with reason, or label hold/do-not-merge with a future "
        "review-by: YYYY-MM-DD in the PR body.",
        file=sys.stderr,
    )
    sys.exit(1)

print(f"OK: {len(held_ok)} open PR(s) covered by valid hold exceptions (REQ-REL-003)")
sys.exit(0)
PY
