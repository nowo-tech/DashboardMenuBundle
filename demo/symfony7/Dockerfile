# FrankenPHP (§2.4 BUNDLES_STANDARDS_PROMPT)
FROM dunglas/frankenphp:1-php8.2-alpine

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

RUN echo '#!/bin/sh' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ "${APP_ENV:-prod}" = "dev" ]; then cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile; fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'mkdir -p /app/var/cache /app/var/log' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod -R 777 /app/var' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "Installing PHP dependencies..." && composer install --no-interaction --prefer-dist || true' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ -f /app/package.json ]; then echo "Installing JS dependencies (pnpm)..." && (cd /app && pnpm install --frozen-lockfile || pnpm install) && echo "Building assets..." && (cd /app && pnpm run build) || true; fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
