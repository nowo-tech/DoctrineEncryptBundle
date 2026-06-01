---
description: Frontend rules for bundle assets with TypeScript, Vite and Pentatrion
globs:
  - "src/Resources/assets/**/*.ts"
  - "src/Resources/assets/**/*.tsx"
  - "src/Resources/assets/**/*.js"
  - "src/Resources/assets/**/*.jsx"
  - "src/Resources/assets/**/*.vue"
  - "src/Resources/assets/**/*.scss"
  - "src/Resources/assets/**/*.sass"
  - "src/Resources/assets/**/*.css"
  - "vite.config.ts"
  - "vite.config.js"
alwaysApply: false
---

Work only on the requested entrypoint, module, component, or stylesheet.

Prefer:
- strict TypeScript typing
- small modules and focused edits
- preserving the current Vite entrypoint strategy
- editing existing initialization code instead of creating parallel bootstraps
- assuming assets live under src/Resources/assets
- assuming generated files may end up under src/Resources/public/build
- preserving integration points used by Twig or Symfony rendering
- JSDoc in English for exported APIs, non-obvious functions, and complex behavior

Do not inspect dist, build, minified, or generated assets.
Do not add JSDoc in Spanish.
Do not edit generated build output.
Do not rewrite the whole frontend architecture unless explicitly requested.