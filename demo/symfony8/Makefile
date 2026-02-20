# Makefile for Doctrine Encrypt Bundle Demo - Symfony 8
# FrankenPHP + Caddyfile. Run from this directory: make up, make install, etc.

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build install shell logs setup db-create db-schema key fixtures cache-clear update-bundle test encrypt-status encrypt-database decrypt-database ensure-up
.PHONY: encrypt-database-all decrypt-database-all encrypt-database-personal_data decrypt-database-personal_data encrypt-database-financial_data decrypt-database-financial_data

help:
	@echo "Doctrine Encrypt Bundle Demo - Symfony 8"
	@echo ""
	@echo "  make up                 Start containers (FrankenPHP on port \$${PORT:-8008})"
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
	@echo "  make encrypt-database-personal_data  Encrypt: only config personal_data"
	@echo "  make encrypt-database-financial_data Encrypt: only config financial_data"
	@echo "  make decrypt-database   Decrypt: all configs (interactive)"
	@echo "  make decrypt-database-all            Same: all configs"
	@echo "  make decrypt-database-personal_data  Decrypt: only config personal_data"
	@echo "  make decrypt-database-financial_data Decrypt: only config financial_data"
	@echo "  make cache-clear        Clear Symfony cache"
	@echo "  make update-bundle      Update bundle from path repo"
	@echo "  make test               Run tests"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || (echo "APP_ENV=dev" > .env; echo "APP_SECRET=change-me" >> .env; echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" >> .env); fi
	$(COMPOSE) up -d
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
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
	fi

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction

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
	@echo "Halite key for personal_data generated (or exists). Defuse creates .Defuse.financial_data.key on first use."

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

# Decrypt: all configs (no config argument)
decrypt-database decrypt-database-all: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database

# Decrypt: single config
decrypt-database-personal_data: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database personal_data

decrypt-database-financial_data: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) php bin/console doctrine:decrypt:database financial_data

cache-clear: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer update nowo-tech/doctrine-encrypt-bundle --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear
	@echo "Bundle updated."

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test $(SERVICE_PHP) composer test 2>/dev/null || $(COMPOSE) exec $(SERVICE_PHP) php bin/console list doctrine
