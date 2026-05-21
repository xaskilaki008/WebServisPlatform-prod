FROM node:22-bookworm AS frontend

WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize

FROM debian:bookworm AS wgrib2-builder

ARG WGRIB2_URL=https://ftp.cpc.ncep.noaa.gov/wd51we/wgrib2/wgrib2.tgz

WORKDIR /tmp

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        cmake \
        curl \
        gcc \
        g++ \
        gfortran \
        make \
    && curl -fsSL "$WGRIB2_URL" -o wgrib2.tgz \
    && tar -xzf wgrib2.tgz \
    && make -C grib2 \
    && install -m 0755 grib2/wgrib2/wgrib2 /usr/local/bin/wgrib2 \
    && /usr/local/bin/wgrib2 -version

FROM php:8.4-fpm-bookworm AS app

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libbz2-dev \
        libzip-dev \
        libgfortran5 \
        libgomp1 \
        libquadmath0 \
        libstdc++6 \
        unzip \
    && docker-php-ext-install \
        pdo_pgsql \
        bz2 \
        zip \
        bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY --from=wgrib2-builder /usr/local/bin/wgrib2 /usr/bin/wgrib2
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

RUN rm -f public/hot \
    && test -x /usr/bin/wgrib2 \
    && /usr/bin/wgrib2 -version \
    && chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

WORKDIR /var/www/html

COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
