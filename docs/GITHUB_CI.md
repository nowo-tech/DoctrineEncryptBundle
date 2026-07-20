# GitHub Actions CI — requirements and configuration

Canonical per-repo doc for **REQ-GIT-001** (no Cursor co-author trailers). Copy this file to `docs/GITHUB_CI.md` in every nowo-tech bundle and keep it in sync with the shared template under `repositories/bundles/.scripts/templates/GITHUB_CI.md`.

## Scope

| Applies to | Does not apply to |
|------------|-------------------|
| Every auditable nowo-tech bundle git repository | Parent monorepo checkouts without a bundle-local `.git` |
| Push / PR CI on `main` / `master` | Shallow clones used as a substitute for full history |

## REQ-GIT-001 — History without Cursor co-author

### Normative rule

Commit messages **must not** include Cursor agent trailers:

```text
Co-authored-by: Cursor <cursoragent@cursor.com>
```

or any `Co-authored-by:` line containing `cursoragent@cursor.com`.

Commits list **human authors only**. Tooling attribution belongs in PR descriptions, release notes, or changelogs — not in `git log`.

### Why CI enforces it

Local hooks and Cursor rules are not enough: clones without `make setup-hooks`, or local `git replace` refs, can hide dirty history. A fresh CI clone with full history is the source of truth.

---

## Mandatory artifacts (adoption checklist)

Use this when rolling REQ-GIT-001 into a bundle. All items are required for audit pass.

### Files to install (from `.scripts/templates/`)

| Artifact | Path | Role |
|----------|------|------|
| Verification script | `.scripts/check-no-cursor-coauthor.sh` | Fails if any reachable commit has forbidden trailers |
| Cleanup script | `.scripts/strip-cursor-coauthor-from-history.sh` | Rewrites local branch messages; then force-push |
| Hook | `.githooks/commit-msg` | Strips trailers before the commit is created |
| Cursor rule | `.cursor/rules/01-git-commits.mdc` | `alwaysApply: true` — agents must not add trailers |
| This doc | `docs/GITHUB_CI.md` | Operator + CI reference |

Scripts must be executable (`chmod +x`).

### Makefile targets

```makefile
.PHONY: check-no-cursor-coauthor strip-cursor-coauthor-from-history setup-hooks

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main

setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."
```

Wire into release:

```makefile
release-check: check-no-cursor-coauthor ensure-up …
```

`check-no-cursor-coauthor` must appear on the `release-check:` dependency line (or as the first recipe step).

### Docs touchpoints

| File | Required content |
|------|------------------|
| `README.md` | Link under Documentation → `[GitHub Actions CI requirements](docs/GITHUB_CI.md)` |
| `docs/CONTRIBUTING.md` | `make setup-hooks`, `make check-no-cursor-coauthor`, and `make strip-cursor-coauthor-from-history` when CI fails |
| `docs/RELEASE.md` | Re-run `make check-no-cursor-coauthor` **after** the release commit and **before** `git push` |

### CI job (`.github/workflows/ci.yml`)

```yaml
git-hygiene:
  name: Git history (no Cursor co-author)
  runs-on: ubuntu-latest
  steps:
    - name: Checkout code
      uses: actions/checkout@v6
      with:
        fetch-depth: 0

    - name: Check for Cursor co-author trailers (REQ-GIT-001)
      run: |
        chmod +x .scripts/check-no-cursor-coauthor.sh
        ./.scripts/check-no-cursor-coauthor.sh HEAD
```

**`fetch-depth: 0` is mandatory.** A shallow clone can hide dirty ancestors and make the job pass incorrectly.

Trigger the workflow on `push` and `pull_request` to `main` / `master` (same as the rest of CI).

---

## Verify (local or CI)

```bash
chmod +x .scripts/check-no-cursor-coauthor.sh
./.scripts/check-no-cursor-coauthor.sh HEAD
# or: make check-no-cursor-coauthor
```

On failure the script lists offending commits.

Verification **must** use `git --no-replace-objects` (built into the shared check script) so local `git replace` refs cannot hide trailers that CI would still see.

Sanity checks:

```bash
git replace -l                    # should be empty
git --no-replace-objects log HEAD --format=%B | grep -i co-authored-by || echo clean
```

---

## Clean already-published history

When CI fails on a fresh clone, **`git replace` does not help**: it only remaps objects on your machine and does not change `origin`.

### Per-repo (inside the bundle)

1. Clean working tree (no unstaged/staged changes). `git filter-branch` refuses dirty trees.
2. Remove any local replace refs: `git replace -d $(git replace -l)` (if any).
3. Rewrite:

```bash
chmod +x .scripts/strip-cursor-coauthor-from-history.sh
./.scripts/strip-cursor-coauthor-from-history.sh main
# or: make strip-cursor-coauthor-from-history
```

4. Verify: `make check-no-cursor-coauthor`
5. Publish (coordinate with the team):

```bash
git push --force-with-lease origin main
```

6. If release tags pointed at rewritten commits, recreate annotated tags on the clean commits and force-push those tags.

### Multi-bundle (from `repositories/bundles/`)

Scaffold / docs / CI wiring:

```bash
# from repositories/bundles/
python3 .scripts/rollout-req-git-001-full.py
python3 .scripts/rollout-req-git-001-strip.py
```

Audit or rewrite history across repos:

```bash
./.scripts/strip-cursor-coauthor-history.sh              # dry-run audit
./.scripts/strip-cursor-coauthor-history.sh --apply      # local rewrite
./.scripts/strip-cursor-coauthor-history.sh --apply --push --yes  # destructive
```

Compliance matrix: `python3 .scripts/audit-bundles-checklist.py` (checker `REQ-GIT-001`).

---

## Prevention

- Run `make setup-hooks` once per clone (ideally from `make up`).
- Do not add `Co-authored-by: Cursor` manually.
- Before every release push: `make check-no-cursor-coauthor` (again after the release commit).
- Never use `git replace` as a substitute for rewriting and force-pushing.

---

## Common pitfalls

| Gap | Symptom | Fix |
|-----|---------|-----|
| Hook present but `setup-hooks` never run | Trailer appears on pushed commits | `make setup-hooks` once per clone |
| Check only before `release-check`, not after `git commit` | Last commit on `main` dirty; earlier HEAD was clean | Re-run check after every release commit |
| Cursor rule only | IDE injects trailer at commit time | Installed `commit-msg` + CI `git-hygiene` |
| Shallow CI checkout | Job green while history is dirty | `fetch-depth: 0` |
| Local `git replace` | Local check green; CI red | Delete replace refs; strip; force-push |
| Uncommitted changes during strip | `filter-branch` aborts | Commit or stash first |

---

## Acceptance criteria

A bundle complies when:

- [ ] All mandatory files above exist and scripts are executable
- [ ] `release-check` depends on `check-no-cursor-coauthor`
- [ ] CI has `git-hygiene` with `fetch-depth: 0`
- [ ] README / CONTRIBUTING / RELEASE document the workflow
- [ ] `make check-no-cursor-coauthor` exits 0 on `HEAD`
- [ ] `git replace -l` is empty (or removed before strip/push)
- [ ] Fresh clone of `origin/main` also passes the check

---

## References

- [CONTRIBUTING.md](CONTRIBUTING.md) — hooks and contribution flow
- [RELEASE.md](RELEASE.md) — post-tag co-author check before push
- [.github/workflows/ci.yml](../.github/workflows/ci.yml) — `git-hygiene` job
- Shared templates: `repositories/bundles/.scripts/templates/`
- Spec detail: `repositories/bundles/BUNDLES_FULL_SPECS_DETAILS.md` → REQ-GIT-001
