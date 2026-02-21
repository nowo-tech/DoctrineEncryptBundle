# Makefile for Doctrine Encrypt Bundle Demo - Symfony 8
# FrankenPHP + Caddyfile. Run from this directory: make up, make install, etc.

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build install shell logs setup db-create db-schema key fixtures cache-clear update-bundle test test-coverage encrypt-status encrypt-database decrypt-database ensure-up
.PHONY: encrypt-database-all decrypt-database-all encrypt-database-personal_data decrypt-database-personal_data encrypt-database-financial_data decrypt-database-financial_data encrypt-database-env_var decrypt-database-env_var
.PHONY: rotate-keys rotate-keys-backup

help:
	@echo "Doctrine Encrypt Bundle Demo - Symfony 8"
	@echo ""
	@echo "  make up                 Start containers, install deps, create DB, schema and load fixtures (FrankenPHP on port \$${PORT:-8008}); if exit 137 (OOM), run 'make install' then 'make setup'"
	@echo "  make down               Stop containers"
	@echo "  make build              Build image (no cache)"
	@echo "  make install            Install dependencies in container"
	@echo "  make setup              install + db-create + db-schema + key + fixtures"
	@echo "  make shell              Open shell in PHP container"
	@echo "  make logs               Show container logs"
	@echo "  make db-create          Create SQLite DB"
	@echo "  make db-schema          Update schema"
	@echo "  make key                Generate encryption secret key"
	@echo "  make fixtures           Load data fixtures (SecretMessage + SensitiveRecord)"
	@echo "  make encrypt-status     List entities and encrypted properties"
	@echo "  make encrypt-database   Encrypt: all configs (interactive)"
	@echo "  make encrypt-database-all            Same: all configs"
	@echo "  make encrypt-database-personal_data  Encrypt: config personal_data (default path)"
	@echo "  make encrypt-database-financial_data Encrypt: config financial_data (secret_key_filename)"
	@echo "  make encrypt-database-env_var       Encrypt: config env_var (%env(APP_ENCRYPT_KEY)%)"
	@echo "  make decrypt-database   Decrypt: all configs (interactive)"
	@echo "  make decrypt-database-all            Same: all configs"
	@echo "  make decrypt-database-personal_data  Decrypt: config personal_data"
	@echo "  make decrypt-database-financial_data Decrypt: config financial_data"
	@echo "  make decrypt-database-env_var       Decrypt: config env_var"
	@echo "  make rotate-keys        Rotate keys: decrypt → change keys → re-encrypt (interactive)"
	@echo "  make rotate-keys-backup Same with --backup (DB + keys to var/encrypt_rotation_backup_*)"
	@echo "  make cache-clear        Clear Symfony cache"
	@echo "  make update-bundle      Update bundle from path repo"
	@echo "  make test               Run tests"
	@echo "  make test-coverage      Run tests with coverage (console output)"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || (echo "APP_ENV=dev" > .env; echo "APP_SECRET=change-me" >> .env; echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" >> .env); fi
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@echo "Creating database, schema and loading fixtures..."
	@$(MAKE) db-create db-schema key fixtures
	@echo "App: http://localhost:$${PORT:-8008}"

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build --no-cache

# Ensure container is running (start if not). Used by install, setup, test, db-create, db-schema, key, etc.
ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container (demo docker-compose)..."; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction; \
	fi

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

setup: install db-create db-schema key fixtures
	@echo "Demo ready. App: http://localhost:$${PORT:-8008}"

fixtures: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:fixtures:load --no-interaction
	@echo "Fixtures loaded (SecretMessage + SensitiveRecord)."

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
	@echo "Keys: personal_data = .Halite.personal_data.key; financial_data = .demo_financial.key; env_var = APP_ENCRYPT_KEY (.env). Add printed key to .env if APP_ENCRYPT_KEY is empty."

encrypt-status: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console doctrine:encrypt:status

# Encrypt: all configs (no config argument)
encrypt-database encrypt-database-all: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:database

# Encrypt: single config
encrypt-database-personal_data: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:database personal_data

encrypt-database-financial_data: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:database financial_data

encrypt-database-env_var: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:database env_var

# Decrypt: all configs (no config argument)
decrypt-database decrypt-database-all: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database

# Decrypt: single config
decrypt-database-personal_data: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database personal_data

decrypt-database-financial_data: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database financial_data

decrypt-database-env_var: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database env_var

# Rotate keys: decrypt → change keys (generate new key files) → re-encrypt. Interactive (confirm each step).
rotate-keys: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:rotate-keys

# Same with backup of DB (SQLite → var/encrypt_rotation_backup_*/database.sqlite.gz) and key files.
rotate-keys-backup: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:encrypt:rotate-keys --backup

cache-clear: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer update nowo-tech/doctrine-encrypt-bundle --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear
	@echo "Bundle updated."

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test $(SERVICE_PHP) composer test 2>/dev/null || $(COMPOSE) exec $(SERVICE_PHP) php bin/console list doctrine

test-coverage: ensure-up
	$(COMPOSE) exec -e APP_ENV=test $(SERVICE_PHP) composer test-coverage 2>/dev/null || $(COMPOSE) exec $(SERVICE_PHP) composer test
