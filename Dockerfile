
# syntax=docker/dockerfile:1

# ============================================================
# 1. PHP RUNTIME
# Shared by the API, application builder and worker.
# Contains no application source code.
# ============================================================

FROM php:8.4-fpm-alpine AS php-runtime

ENV TZ=UTC \
    HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    APP_ENV=prod \
    APP_DEBUG=0

# Install PHP system dependencies and extensions
RUN apk add --no-cache \
        git \
        unzip \
        tzdata \
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

# Composer
COPY --from=composer:2 \
    /usr/bin/composer /usr/local/bin/composer

# Symfony CLI
COPY --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

# Shared PHP entrypoint
COPY --chmod=755 \
    docker/php/entrypoint.sh \
    /usr/local/bin/php-entrypoint.sh

WORKDIR /app

ENTRYPOINT ["/usr/local/bin/php-entrypoint.sh"]


# ============================================================
# 2. FILEBOT WORKER RUNTIME
# Independent of Symfony source code.
# Only rebuilds when dependencies, FileBot version, or
# the underlying PHP runtime change.
# ============================================================

FROM php-runtime AS worker-runtime

ARG FILEBOT_VERSION=5.3.0
ARG FILEBOT_SHA256=633d798a917607051b4f61be37b5ad923457050708c7e7eea99c09957583b575

ENV FILEBOT_HOME=/opt/filebot \
    LANG=C.UTF-8

# Install Java and media processing dependencies
RUN apk add --no-cache \
        openjdk17-jre-headless \
        mediainfo \
        chromaprint \
        p7zip \
        wget \
        xz

# Download, verify and install FileBot
RUN mkdir -p "$FILEBOT_HOME" /filebot \
    && chown 1000:1000 /filebot \
    && wget -O /tmp/filebot.tar.xz \
        "https://get.filebot.net/filebot/FileBot_${FILEBOT_VERSION}/FileBot_${FILEBOT_VERSION}-portable.tar.xz" \
    && echo "$FILEBOT_SHA256  /tmp/filebot.tar.xz" | sha256sum -c - \
    && tar --extract \
        --file /tmp/filebot.tar.xz \
        --directory "$FILEBOT_HOME" \
    && rm -f /tmp/filebot.tar.xz \
    && find "$FILEBOT_HOME/lib" \
        -type f -not -name libjnidispatch.so -delete \
    && ln -s /filebot "$FILEBOT_HOME/data" \
    && ln -s "$FILEBOT_HOME/filebot.sh" /usr/local/bin/filebot

# Copy entrypoint AFTER expensive dependency installation
COPY --chmod=755 \
    docker/worker/entrypoint.sh \
    /usr/local/bin/entrypoint.sh


# ============================================================
# 3. SYMFONY APPLICATION BUILD
# Dependencies and application code built independently
# of the FileBot runtime.
# ============================================================

FROM php-runtime AS app-build

WORKDIR /app

# Copy dependency manifests first to maximise cache reuse
COPY composer.json composer.lock symfony.lock ./

# Install production dependencies
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# Copy application source
COPY . /app

# Generate optimised autoloader and prepare Symfony
RUN composer dump-autoload \
        --no-dev \
        --classmap-authoritative \
        --no-scripts \
    && touch .env \
    && APP_SECRET=build-only-secret \
        DEFAULT_URI=http://localhost \
        TRUSTED_PROXIES="" \
        MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages \
        php bin/console assets:install public \
    && APP_SECRET=build-only-secret \
        APP_DEBUG=1 \
        DEFAULT_URI=http://localhost \
        TRUSTED_PROXIES="" \
        MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages \
        php bin/console importmap:install \
    && APP_SECRET=build-only-secret \
        APP_DEBUG=1 \
        DEFAULT_URI=http://localhost \
        TRUSTED_PROXIES="" \
        MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages \
        php bin/console asset-map:compile \
    && mkdir -p var/cache var/log \
    && chmod -R 0777 var


# ============================================================
# 4. PHP-FPM API IMAGE
# GitHub Actions target: php
# ============================================================

FROM app-build AS php

EXPOSE 9000

# Verify PHP-FPM is listening and Symfony can bootstrap
HEALTHCHECK --interval=30s --timeout=5s \
    --retries=3 --start-period=10s \
    CMD php -r '$s=@fsockopen("127.0.0.1",9000); if (!$s) exit(1); fclose($s);' \
        && php bin/console about --no-interaction >/dev/null 2>&1

CMD ["php-fpm", "-F"]


# ============================================================
# 5. NGINX IMAGE
# GitHub Actions target: nginx
# ============================================================

FROM nginx:stable-alpine AS nginx

RUN apk add --no-cache tzdata

# Copy only Symfony's public assets
COPY --from=app-build /app/public /app/public

# Include Nginx configuration in the image
COPY docker/nginx/default.conf \
    /etc/nginx/conf.d/default.conf

EXPOSE 80


# ============================================================
# 6. FILEBOT WORKER IMAGE
# GitHub Actions target: worker
# Combines independently cached runtime and application.
# ============================================================

FROM worker-runtime AS worker

# Add Symfony application to the prepared FileBot runtime
COPY --from=app-build /app /app

CMD ["/usr/local/bin/entrypoint.sh"]
