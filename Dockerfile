ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-alpine

# Install extension installer helper and Composer
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install system dependencies and required PHP extensions
RUN apk add --no-cache git unzip bash \
    && install-php-extensions pcntl zip curl intl pcov

WORKDIR /app
