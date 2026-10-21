#!/usr/bin/env python3
"""Fail if translation YAML files do not share the same nested key set (REQ-I18N-002)."""

from __future__ import annotations

import sys
from pathlib import Path

REQUIRED_LOCALES = ("en", "es", "it", "fr", "pt", "de", "nl")
DOMAIN = "NowoDoctrineEncryptBundle"
TRANS_DIR = Path(__file__).resolve().parents[1] / "src" / "Resources" / "translations"


def flatten(path: Path) -> set[str]:
    keys: set[str] = set()
    stack: list[tuple[int, str]] = []
    for raw in path.read_text(encoding="utf-8").splitlines():
        if not raw.strip() or raw.lstrip().startswith("#"):
            continue
        indent = len(raw) - len(raw.lstrip(" "))
        key = raw.split(":", 1)[0].strip()
        if not key:
            continue
        while stack and stack[-1][0] >= indent:
            stack.pop()
        stack.append((indent, key))
        keys.add(".".join(item[1] for item in stack))
    return keys


def check_catalogue(trans_dir: Path, filename_tpl: str, label: str) -> bool:
    if not trans_dir.is_dir():
        print(f"ERROR: translations directory missing: {trans_dir}", file=sys.stderr)
        return False

    files = {loc: trans_dir / filename_tpl.format(locale=loc) for loc in REQUIRED_LOCALES}
    missing = [str(path) for path in files.values() if not path.is_file()]
    if missing:
        print(f"ERROR: missing {label} locale files:", file=sys.stderr)
        print("\n".join(missing), file=sys.stderr)
        return False

    reference = flatten(files["en"])
    failed = False
    for loc, path in files.items():
        keys = flatten(path)
        extra = sorted(keys - reference)
        absent = sorted(reference - keys)
        if extra or absent:
            failed = True
            print(f"ERROR: key mismatch in {path.name} vs en", file=sys.stderr)
            for key in absent:
                print(f"  missing: {key}", file=sys.stderr)
            for key in extra:
                print(f"  extra: {key}", file=sys.stderr)

    if failed:
        return False

    print(f"{label} key parity OK ({len(REQUIRED_LOCALES)} locales, {len(reference)} keys).")
    return True


def main() -> int:
    return 0 if check_catalogue(TRANS_DIR, f"{DOMAIN}.{{locale}}.yaml", "Bundle") else 1


if __name__ == "__main__":
    raise SystemExit(main())
