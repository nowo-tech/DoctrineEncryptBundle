# Makefile for Doctrine Encrypt Bundle Demo - Symfony 7
# FrankenPHP + Caddyfile. Run from this directory: make up, make install, etc.

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build install shell logs setup db-create db-schema key cache-clear update-bundle test encrypt-status encrypt-database decrypt-database ensure-up

help:
	@echo "Doctrine Encrypt Bundle Demo - Symfony 7"
	@echo ""
	@echo "  make up                 Start containers (FrankenPHP on port \$${PORT:-8007})"
	@echo "  make down               Stop containers"
	@echo "  make build              Build image (no cache)"
	@echo "  make install            Install dependencies in container"
	@echo "  make setup              install + db-create + db-schema + key"
	@echo "  make shell              Open shell in PHP container"
	@echo "  make logs               Show container logs"
	@echo "  make db-create          Create SQLite DB"
	@echo "  make db-schema          Update schema"
	@echo "  make key                Generate encryption secret key"
	@echo "  make encrypt-status     List entities and encrypted properties"
	@echo "  make encrypt-database   Encrypt existing data (interactive)"
	@echo "  make decrypt-database   Decrypt existing data (interactive)"
	@echo "  make cache-clear        Clear Symfony cache"
	@echo "  make update-bundle      Update bundle from path repo"
	@echo "  make test               Run tests"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || (echo "APP_ENV=dev" > .env; echo "APP_SECRET=change-me" >> .env; echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" >> .env); fi
	$(COMPOSE) up -d
	@echo "App: http://localhost:$${PORT:-8007}"

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build --no-cache

# Ensure container is running (start if not). Used by install, setup, test, db-create, db-schema, key, etc.
ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container (demo docker-compose)..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
	fi

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction

setup: install db-create db-schema key
	@echo "Demo ready. App: http://localhost:$${PORT:-8007}"

shell: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) sh

logs:
	$(COMPOSE) logs -f

# SQLite: doctrine:database:create is not supported (getListDatabasesSQL). Ensure var/ and DB file exist.
db-create: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && touch var/data.db'

db-schema: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:schema:update --force --no-interaction

key: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:encrypt:generate-secret-key 2>/dev/null || true
	@echo "Secret key generated (or already exists)."

encrypt-status: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:encrypt:status

encrypt-database: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:database

decrypt-database: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database

cache-clear: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer update nowo-tech/doctrine-encrypt-bundle --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear
	@echo "Bundle updated."

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test $(SERVICE_PHP) composer test 2>/dev/null || $(COMPOSE) exec $(SERVICE_PHP) php bin/console list doctrine
