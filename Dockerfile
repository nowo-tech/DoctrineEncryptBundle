# PHP 8.2+ for Doctrine Encrypt Bundle (Symfony 7|8)
FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    bash \
    libzip-dev \
    icu-dev \
    libsodium-dev

RUN docker-php-ext-install -j$(nproc) \
    zip \
    intl \
    sodium

# PCOV for code coverage (faster than Xdebug)
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN git config --global --add safe.directory /app

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
