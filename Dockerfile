FROM php:8.4-fpm-alpine AS app-base

ENV TZ=UTC

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


COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install Symfony CLI
COPY --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

COPY docker/php/entrypoint.sh /usr/local/bin/php-entrypoint.sh
RUN chmod +x /usr/local/bin/php-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/php-entrypoint.sh"]

WORKDIR /app

ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer

ENV APP_ENV=prod \
    APP_DEBUG=0

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=10s \
    CMD php bin/console about --no-interaction >/dev/null 2>&1 || exit 1

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . /app

RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && touch .env \
    && APP_SECRET=build-only-secret \
        DEFAULT_URI=http://localhost \
        TRUSTED_PROXIES="" \
        MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages \
        php bin/console assets:install public \
    && mkdir -p var/cache var/log \
    && chmod -R 0777 var

FROM app-base AS php

CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS nginx

RUN apk add --no-cache tzdata

COPY --from=php /app/public /app/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

FROM app-base AS worker

ARG FILEBOT_VERSION=5.3.0
ARG FILEBOT_SHA256=633d798a917607051b4f61be37b5ad923457050708c7e7eea99c09957583b575

ENV FILEBOT_HOME=/opt/filebot \
    LANG=C.UTF-8

COPY docker/worker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN apk add --no-cache \
        openjdk17-jre-headless \
        mediainfo \
        chromaprint \
        p7zip \
        wget \
        xz \
    && mkdir -p "$FILEBOT_HOME" /filebot \
    && chown 1000:1000 /filebot \
    && wget -O /tmp/filebot.tar.xz "https://get.filebot.net/filebot/FileBot_${FILEBOT_VERSION}/FileBot_${FILEBOT_VERSION}-portable.tar.xz" \
    && echo "$FILEBOT_SHA256  /tmp/filebot.tar.xz" | sha256sum -c - \
    && tar --extract --file /tmp/filebot.tar.xz --directory "$FILEBOT_HOME" \
    && rm -f /tmp/filebot.tar.xz \
    && find "$FILEBOT_HOME/lib" -type f -not -name libjnidispatch.so -delete \
    && ln -s /filebot "$FILEBOT_HOME/data" \
    && ln -s "$FILEBOT_HOME/filebot.sh" /usr/local/bin/filebot

CMD ["/usr/local/bin/entrypoint.sh"]
