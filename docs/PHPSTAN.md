# PHPStan (REQ-CS-006)

PHPStan runs at **level 8** (`phpstan.neon.dist`) with `nowo-tech/phpstan-frankenphp` classic + worker rulesets (REQ-CS-005).

There is **no** blanket `ignoreErrors` in `phpstan.neon.dist`. Known issues are tracked in the committed **`phpstan-baseline.neon`**.

## Baseline policy

- Prefer **fixing** findings over growing the baseline.
- Regenerate the baseline only in a dedicated change (do not silently expand it with unrelated work).
- New production code must not add baseline entries without a linked issue or an entry in the table below.

## Baseline categories (justified)

Approximate mix after the 2026-07-28 refresh (~118 entries):

| Identifier | Approx. count | Justification | Removal target |
|------------|---------------|---------------|----------------|
| `missingType.iterableValue` | ~37 | Test helpers and array-shaped configs without value types; low risk, high noise | Annotate arrays / `@phpstan-type` in tests and DI Configuration |
| `argument.type` | ~15 | Doctrine / Reflection / mock seams in subscriber and AttributeReader | Narrow types where safe without breaking ORM 2/3 dual support |
| `method.notFound` / `class.notFound` | ~15 | Conditional stubs and PHPUnit doubles; some Attribute API edges | Replace with interfaces or stubs under `tests/` |
| `missingType.parameter` | ~9 | Legacy PHPDoc gaps on command helpers | Add parameter types incrementally |
| `method.alreadyNarrowedType` / `function.alreadyNarrowedType` | ~15 | Defensive `is_*` after typed params (CLI / crypto paths) | Simplify after PHP 8.2+ assert cleanup |
| Other (`phpDoc.parseError`, generics, dead code, …) | remainder | Isolated; mostly tests and AttributeReader | Fix when touching those files |

## Commands

```bash
make phpstan
# or
composer phpstan
```

PHP floor for analysis matches `composer.json` (`>=8.2 <8.6`).
