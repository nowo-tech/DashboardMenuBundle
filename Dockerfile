# Root Dockerfile: dev + tests (§2.1 BUNDLES_STANDARDS_PROMPT)
FROM php:8.2-cli-alpine

RUN apk add --no-cache git unzip bash icu-dev libzip-dev nodejs npm \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo pdo_mysql zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && npm install -g pnpm \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .phpize-deps

RUN git config --global --add safe.directory /app

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || composer update --no-interaction --prefer-dist --optimize-autoloader

COPY . .

CMD ["tail", "-f", "/dev/null"]
