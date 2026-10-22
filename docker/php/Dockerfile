# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# base: PHP runtime with the extensions the app needs
# ---------------------------------------------------------------------------
FROM php:8.5.12-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    zip unzip curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-install pdo_mysql zip gd pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www

# ---------------------------------------------------------------------------
# vendor: composer dependencies (cached separately from the source code)
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# ---------------------------------------------------------------------------
# assets: Vite production build. VITE_* values are baked into the bundle,
# so they must be passed as build args.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /var/www

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_APP_NAME=Agronos
ARG VITE_MAPBOX_TOKEN=
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_MAPBOX_TOKEN=$VITE_MAPBOX_TOKEN \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

# ---------------------------------------------------------------------------
# app: PHP-FPM image used by app, workers and reverb
# ---------------------------------------------------------------------------
FROM base AS app

COPY --from=vendor /var/www/vendor ./vendor
COPY . .
COPY --from=assets /var/www/public/build ./public/build

RUN mkdir -p bootstrap/cache storage/logs storage/app/public \
       storage/framework/cache storage/framework/sessions storage/framework/views \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/start-container.sh /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

CMD ["/usr/local/bin/start-container"]

# ---------------------------------------------------------------------------
# web: nginx serving public/ and proxying PHP to the app service
# ---------------------------------------------------------------------------
FROM nginx:alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/public
COPY --from=assets /var/www/public/build /var/www/public/build
