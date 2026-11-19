#!/usr/bin/env python3
"""Demo lock freshness: does each demo/*/composer.lock satisfy the bundle's own composer.json `require`?

A demo installs the bundle from a path repository with `composer install`, i.e. from its lock. The lock stores the
bundle's requirements as they were when the lock was last updated, so a requirement added or raised later (for example
nowo-tech/transaction-doctrine ^1.5) is not installed and the demo fails with "class not found". This script reports,
for every requirement of the bundle, the version the demo lock resolves and whether it satisfies the constraint
(caret / tilde / >= / || / * only, enough for nowo-tech constraints).

Usage: check-demo-lock.py <bundle-dir> [--only 'nowo-tech/transaction-*'] ; exit 1 when a demo lock is stale.
"""
import fnmatch, glob, json, os, re, sys


def vtuple(v):
    v = v.lstrip('v').split('-')[0].split('+')[0]
    parts = [int(p) if p.isdigit() else 0 for p in v.split('.')[:3]]
    return tuple(parts + [0] * (3 - len(parts)))


def satisfies(ver, constraint):
    if ver.startswith('dev-') or constraint.strip() in ('*', '@dev', ''):
        return True
    v = vtuple(ver)
    for alt in re.split(r'\s*\|\|?\s*', constraint.strip()):
        ok = True
        for part in alt.replace(',', ' ').split():
            part = part.split('@')[0]
            if not part or part == '*':
                continue
            if part.startswith('^'):
                b = vtuple(part[1:]); n = len(part[1:].split('.'))
                hi = (b[0] + 1, 0, 0) if b[0] > 0 else ((0, b[1] + 1, 0) if n > 1 else (1, 0, 0))
                ok &= b <= v < hi
            elif part.startswith('~'):
                b = vtuple(part[1:]); n = len(part[1:].split('.'))
                hi = (b[0] + 1, 0, 0) if n <= 2 else (b[0], b[1] + 1, 0)
                ok &= b <= v < hi
            elif part.startswith('>='):
                ok &= v >= vtuple(part[2:])
            elif part.startswith('<'):
                ok &= v < vtuple(part[1:].lstrip('='))
            elif part.startswith('>'):
                ok &= v > vtuple(part[1:])
            elif re.match(r'^\d', part):
                ok &= v == vtuple(part)
        if ok:
            return True
    return False


def main():
    bundle = sys.argv[1]
    only = sys.argv[sys.argv.index('--only') + 1] if '--only' in sys.argv else '*'
    req = json.load(open(os.path.join(bundle, 'composer.json'))).get('require', {})
    stale = False
    for lock in sorted(glob.glob(os.path.join(bundle, 'demo', '*', 'composer.lock'))):
        pk = {p['name']: p['version'] for p in json.load(open(lock)).get('packages', [])}
        for name, c in req.items():
            if '/' not in name or not fnmatch.fnmatch(name, only):
                continue
            got = pk.get(name)
            if got is None or not satisfies(got, c):
                stale = True
                print(f'STALE {os.path.relpath(lock, bundle)}: {name} {c} -> {got or "not installed"}')
    return 1 if stale else 0


if __name__ == '__main__':
    sys.exit(main())
