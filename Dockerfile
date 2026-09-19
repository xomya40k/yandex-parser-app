# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 1: build Vue / Vite assets
# -----------------------------------------------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json ./

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: PHP-FPM application image
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app

WORKDIR /var/www/html

# curl is already present in php:fpm-alpine; install-php-extensions pulls its own deps.
RUN curl -sSLf \
        -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && mkdir -p \
        storage/framework/{cache/data,sessions,testing,views} \
        storage/logs \
        bootstrap/cache \
    && cp -a public /opt/public-dist \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
