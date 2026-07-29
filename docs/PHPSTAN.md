# PHPStan (REQ-CS-006)

PHPStan runs at **level 8** (`phpstan.neon.dist`) with `nowo-tech/phpstan-frankenphp` classic + worker rulesets (REQ-CS-005).

- `ignoreErrors` in config is **empty** (`[]`).
- Committed **`phpstan-baseline.neon`** is empty (`ignoreErrors: []`) — analysis is clean at level 8.
- `treatPhpDocTypesAsCertain: false` avoids false positives on Doctrine/Twig PHPDoc edges.
- Bootstrap: `phpstan/bootstrap.php` (+ stubs) for optional `AsDoctrineListener` when doctrine-bundle is not installed in the analyser context.

## Baseline policy

- Prefer **fixing** findings over growing the baseline.
- Do **not** reintroduce baseline entries without a linked issue and an entry in this doc.
- New production code must not add baseline noise; CI runs `composer phpstan` on every push (see `.github/workflows/ci.yml`).

## Commands

```bash
make phpstan
# or
composer phpstan
```

PHP floor for analysis matches `composer.json` (`>=8.2 <8.6`).
