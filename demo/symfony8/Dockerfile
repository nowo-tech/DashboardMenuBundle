# FrankenPHP (§2.4 BUNDLES_STANDARDS_PROMPT)
FROM dunglas/frankenphp:1-php8.4-alpine

RUN apk add --no-cache git unzip bash curl ca-certificates icu-dev libzip-dev nodejs npm
RUN install-php-extensions zip pdo_mysql intl

RUN npm install -g pnpm@9.15.0

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN git config --global --add safe.directory /app && \
    git config --global --add safe.directory /var/dashboard-menu-bundle

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile.dev

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
