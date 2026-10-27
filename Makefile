# Makefile for Doctrine Encrypt Bundle
# Tests and QA at bundle root (same pattern as TwigInspectorBundle / PdfSignableBundle)

COMPOSE_FILE := docker-compose.yml
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE_BIN := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
COMPOSE     := $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down down-dev build shell install update validate test test-coverage coverage-php-percent cs-check cs-fix qa clean ensure-up assets release-check release-check-demos demo-smoke composer-sync rector rector-dry phpstan igor check-no-cursor-coauthor check-open-prs check-no-raw-html-form strip-cursor-coauthor-from-history setup-hooks validate-translations test-e2e demo-screenshots

help:
	@echo "Doctrine Encrypt Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  down-dev      Stop root container (alias of down; non-destructive)"
	@echo "  build         Rebuild Docker image (no cache)"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  assets        No frontend assets in this bundle (no-op)"
	@echo "  test          Run PHPUnit tests (starts container if needed)"
	@echo "  test-coverage Run tests with code coverage; target >=99%% includable (needs PCOV: run 'make build' if you see 'No code coverage driver')"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  igor          Run Igor worker-state audit (REQ-CS-008)"
	@echo "  qa            Run all QA checks (validate + cs-check + test)"
	@echo "  release-check Pre-release: ensure-up + co-author + open-PRs + cs + phpstan + igor + translations + coverage + e2e + demos"
	@echo "  validate-translations  Translation key parity (7 locales) + YAML parse (REQ-MAKE-004)"
	@echo "  demo-smoke    Boot demo/symfony8 and assert HTTP 200 (REQ-TEST-011)"
	@echo "  test-e2e      Playwright e2e against demo/symfony8: create a secret message, ciphertext in MySQL, decrypted on load (REQ-DEMO-013)"
	@echo "  demo-screenshots Reload demo fixtures and refresh README screenshots docs/images/demo/*.webp (REQ-DEMO-013)"
	@echo "  check-open-prs Fail if unresolved open GitHub PRs remain (REQ-REL-003)"
	@echo "  check-no-raw-html-form Fail on raw <form>/<input>/<textarea>/<select> in Twig (REQ-TWIG-005)"
	@echo "  composer-sync Validate composer.json and align composer.lock (no install)"
	@echo "  clean         Remove vendor and cache"
	@echo "  update        Update composer.lock (composer update)"
	@echo "  validate      Run composer validate --strict"
	@echo ""
	@echo "Demos:"
	@echo "  (use make -C demo or make -C demo/symfonyX)"
	@echo ""

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "Installing dependencies..."
	$(COMPOSE) exec $(SERVICE_PHP) composer install --no-interaction
	@echo "Container ready!"

down:
	$(COMPOSE) down --remove-orphans

down-dev: down

build:
	$(COMPOSE) build --no-cache

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

# Ensure root container is running (start if not). Used by cs-fix, cs-check, qa, install, validate, test, test-coverage.
ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container (root $(COMPOSE))..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
	fi

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

cs-check: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer cs-fix

rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

qa: ensure-up validate
	$(COMPOSE) exec $(SERVICE_PHP) composer qa


# Run Igor worker-state audit (REQ-CS-008)
igor: ensure-up
	$(COMPOSE) exec -T php composer igor

# REQ-MAKE-002: sequential chain, ensure-up first.
# check-twig-extra (REQ-TWIG-004) is omitted: the bundle ships no Twig views.
release-check:
	@$(MAKE) ensure-up
	@$(MAKE) check-no-cursor-coauthor
	@$(MAKE) check-open-prs
	@$(MAKE) check-no-raw-html-form
	@$(MAKE) composer-sync
	@$(MAKE) cs-fix
	@$(MAKE) cs-check
	@$(MAKE) rector-dry
	@$(MAKE) phpstan
	@$(MAKE) igor
	@$(MAKE) validate-translations
	@$(MAKE) test-coverage
	@$(MAKE) test-e2e
	@$(MAKE) release-check-demos

# REQ-MAKE-004 / REQ-I18N-002: key parity across the 7 required locales + YAML syntax.
validate-translations: ensure-up
	python3 .scripts/check-translation-key-parity.py
	$(COMPOSE) exec -T $(SERVICE_PHP) php -r 'require "vendor/autoload.php"; foreach (glob("src/Resources/translations/*.yaml") as $$f) { Symfony\Component\Yaml\Yaml::parseFile($$f); } echo "YAML OK\n";'

# REQ-TEST-011 — boot demo stack and assert one HTTP 200
demo-smoke:
	@$(MAKE) -C demo/symfony8 up
	@PORT=$$(grep "^PORT=" demo/symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" demo/symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8008; \
	echo "Smoke GET http://localhost:$$PORT/"; \
	for i in 1 2 3 4 5 6 7 8 9 10; do \
		code=$$(curl -fsS -o /dev/null -w "%{http_code}" --max-time 10 "http://localhost:$$PORT/" 2>/dev/null || true); \
		if [ "$$code" = "200" ]; then echo "demo-smoke OK (HTTP 200)"; exit 0; fi; \
		sleep 3; \
	done; \
	echo "demo-smoke failed: last HTTP $$code"; exit 1

# REQ-DEMO-013: Playwright e2e + WebP README screenshots (demo/symfony8/e2e, Playwright Docker image).
test-e2e:
	@$(MAKE) -C demo/symfony8 test-e2e

demo-screenshots:
	@$(MAKE) -C demo/symfony8 demo-screenshots

release-check-demos:
	@$(MAKE) -C demo release-check

composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction --prefer-dist

# No frontend assets in this bundle
assets:
	@echo "No frontend assets in this bundle."

clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f coverage-php.txt
	rm -f .php-cs-fixer.cache

setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@chmod +x .githooks/prepare-commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "Git hooks installed (.githooks — includes commit-msg / prepare-commit-msg for REQ-GIT-001)."

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

# Fail on raw <form>/<input>/<textarea>/<select> in Twig (REQ-TWIG-005)
check-no-raw-html-form:
	@bash .scripts/check-no-raw-html-form.sh

check-open-prs:
	@chmod +x .scripts/check-open-prs.sh
	@bash .scripts/check-open-prs.sh

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh master

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
