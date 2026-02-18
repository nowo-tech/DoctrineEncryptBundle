#!/bin/sh
set -e

mkdir -p /app/var/cache /app/var/log /app/var
chmod -R 777 /app/var 2>/dev/null || true

if [ ! -f /app/vendor/autoload_runtime.php ]; then
	echo "📦 vendor not found, running composer install..."
	composer install --no-interaction --working-dir=/app
	echo "✅ Composer install done."
fi

if [ -d /app/vendor ]; then
	echo "📦 Creating database and schema if needed..."
	php /app/bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
	php /app/bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true
	echo "📦 Generating encryption secret key if needed..."
	php /app/bin/console doctrine:encrypt:generate-secret-key 2>/dev/null || true
	echo "📦 Loading fixtures..."
	php /app/bin/console doctrine:fixtures:load --no-interaction 2>/dev/null || true
	echo "✅ Database, key and fixtures ready."
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
