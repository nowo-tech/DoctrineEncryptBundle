---
description: Tests and quality rules
globs:
  - "tests/**/*.php"
  - "tests/**/*.ts"
  - "tests/**/*.js"
  - "src/Resources/assets/**/*.test.ts"
  - "src/Resources/assets/**/*.spec.ts"
  - "src/Resources/assets/**/*.test.js"
  - "src/Resources/assets/**/*.spec.js"
  - "phpunit.xml*"
  - "vitest.config.*"
  - "eslint.config.*"
alwaysApply: false
---

When changing behavior, prefer adding or updating the nearest relevant test.

Prefer:
- one focused test
- minimal fixture changes
- preserving the existing testing style
- test updates close to the changed behavior

Do not introduce new testing frameworks unless explicitly requested.
Do not rewrite unrelated tests.