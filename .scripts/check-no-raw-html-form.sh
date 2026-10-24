#!/usr/bin/env bash
# REQ-TWIG-005 — fail when bundle or demo Twig uses raw <form> / <input> / <textarea> / <select>.
# No exceptions: dashboard dialogs and forms render through Symfony Form views (form_start / form_row).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

paths=()
[[ -d src/Resources/views ]] && paths+=(src/Resources/views)
for dir in demo/*/templates; do
  [[ -d "$dir" ]] && paths+=("$dir")
done
if [[ ${#paths[@]} -eq 0 ]]; then
  echo "check-no-raw-html-form: no Twig directories — OK"
  exit 0
fi

matches="$(grep -RInE '<(form|input|textarea|select)[[:space:]>]' "${paths[@]}" --include='*.twig' || true)"
if [[ -n "$matches" ]]; then
  echo "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):" >&2
  echo "$matches" >&2
  exit 1
fi

echo "check-no-raw-html-form: OK"
