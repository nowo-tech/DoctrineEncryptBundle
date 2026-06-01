---
description: PHP and Symfony bundle rules
globs:
  - "src/**/*.php"
  - "config/**/*.php"
  - "config/**/*.yaml"
  - "config/**/*.yml"
alwaysApply: false
---

Target this bundle compatibility by default: PHP >=8.1 and Symfony 6/7/8.

Prefer:
- modern PHP with strict typing and explicit return types
- PSR-12 coding style
- final classes by default
- readonly where appropriate
- constructor injection
- small focused services
- framework conventions over custom abstractions
- preserving public extension points
- keeping DI extension, compiler pass wiring, and form type options backward compatible
- PHPDoc in English for non-trivial classes, methods, and complex behavior

Do not introduce BC breaks unless explicitly requested.
Do not add PHPDoc in Spanish.
Do not rename public services, configuration keys, or Twig integrations without warning.
Do not touch unrelated namespaces or files.