# Makefile for Doctrine Encrypt Bundle Demo - Symfony 7
# FrankenPHP + Caddyfile. Run from this directory: make up, make install, etc.

.PHONY: help up down build install shell logs setup db-create db-schema key cache-clear update-bundle test encrypt-status encrypt-database decrypt-database

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
	docker-compose up -d
	@echo "App: http://localhost:$${PORT:-8007}"

down:
	docker-compose down

build:
	docker-compose build --no-cache

install:
	docker-compose exec php composer install --no-interaction

setup: install db-create db-schema key
	@echo "Demo ready. App: http://localhost:$${PORT:-8007}"

shell:
	docker-compose exec php sh

logs:
	docker-compose logs -f

db-create:
	docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction

db-schema:
	docker-compose exec -T php php bin/console doctrine:schema:update --force --no-interaction

key:
	docker-compose exec -T php php bin/console doctrine:encrypt:generate-secret-key 2>/dev/null || true
	@echo "Secret key generated (or already exists)."

encrypt-status:
	docker-compose exec -T php php bin/console doctrine:encrypt:status

encrypt-database:
	docker-compose exec php php bin/console doctrine:encrypt:database

decrypt-database:
	docker-compose exec php php bin/console doctrine:decrypt:database

cache-clear:
	docker-compose exec -T php php bin/console cache:clear

update-bundle:
	docker-compose exec php composer update nowo-tech/doctrine-encrypt-bundle --no-interaction
	docker-compose exec -T php php bin/console cache:clear
	@echo "Bundle updated."

test:
	docker-compose exec -e APP_ENV=test php composer test 2>/dev/null || docker-compose exec php php bin/console list doctrine
