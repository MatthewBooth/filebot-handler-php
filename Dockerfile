FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git \
        unzip \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
        zip \
        opcache \
    && apk del .build-deps


# Install PHP Redis extension
RUN apk add --no-cache --virtual .redis-build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-build-deps


COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install Symfony CLI
COPY --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

WORKDIR /app

ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=10s \
    CMD php -r '$response = require "/app/public/index.php"; exit(0);' || exit 1

CMD ["php-fpm", "-F"]
