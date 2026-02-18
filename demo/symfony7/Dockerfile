# FrankenPHP + PHP 8.2 for Doctrine Encrypt Bundle Demo (Symfony 7)
FROM dunglas/frankenphp:1-php8.2-alpine

RUN apk add --no-cache git unzip bash

RUN install-php-extensions zip intl sodium pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /app && \
	git config --global --add safe.directory /var/doctrine-encrypt-bundle

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
