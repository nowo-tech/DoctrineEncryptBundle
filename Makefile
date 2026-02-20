																																																																																									# Makefile for Doctrine Encrypt Bundle
# Tests and QA at bundle root (same pattern as TwigInspectorBundle / PdfSignableBundle)

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build shell install update validate test test-coverage cs-check cs-fix qa clean ensure-up

help:
	@echo "Doctrine Encrypt Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  build         Rebuild Docker image (no cache)"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  update        Update composer.lock (composer update)"
	@echo "  validate      Run composer validate --strict"
	@echo "  test          Run PHPUnit tests (starts container if needed)"
	@echo "  test-coverage Run tests with code coverage; target 95%% (needs PCOV: run 'make build' if you see 'No code coverage driver')"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  qa            Run all QA checks (validate + cs-check + test)"
	@echo "  clean         Remove vendor and cache"
	@echo ""

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "Installing dependencies..."
	$(COMPOSE) exec $(SERVICE_PHP) composer install --no-interaction
	@echo "Container ready!"

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build --no-cache

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

# Ensure root container is running (start if not). Used by cs-fix, cs-check, qa, install, validate, test, test-coverage.
ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container (root docker-compose)..."; \
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

test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage

cs-check: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer cs-fix

qa: ensure-up validate
	$(COMPOSE) exec $(SERVICE_PHP) composer qa

clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache
