# This argument (when defined) allows to build prod stages from a pre-built image.
ARG base_image=base
ARG base_debug_image=base-debug
ARG NGINX_VERSION=1.21.1
ARG ALPINE_VERSION=3.15

#
# base php image
#
FROM php:8.1-fpm-alpine${ALPINE_VERSION} AS base

RUN apk add --no-cache --virtual .intl-deps icu-dev libpng-dev \
      libjpeg-turbo-dev libwebp-dev zlib-dev libzip-dev libxpm-dev freetype-dev \
    && docker-php-ext-configure gd --enable-gd --with-webp --with-jpeg \
      --with-xpm --with-freetype \
    && docker-php-ext-install opcache intl bcmath exif gd \
    && apk del .intl-deps \
    && apk add --update --no-cache icu libpng libjpeg-turbo libwebp zlib libzip libxpm freetype

RUN docker-php-source extract \
    && apk add --no-cache --virtual .phpize-deps-configure $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .phpize-deps-configure \
    && docker-php-source delete

RUN docker-php-source extract \
    && apk add --no-cache --virtual .phpize-deps-configure $PHPIZE_DEPS \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del .phpize-deps-configure \
    && docker-php-source delete

COPY .docker/php-fpm/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY .docker/php-fpm/inventory-php.ini /usr/local/etc/php/conf.d/inventory-php.ini

#
# debug base image
#
FROM $base_image AS base-debug

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apk --update --no-cache add git yarn

RUN apk add --no-cache --virtual .phpize-deps-configure $PHPIZE_DEPS \
    && pecl install xdebug \
    && apk del .phpize-deps-configure \
    && docker-php-ext-enable xdebug


#
# dev debug image (to use by devs)
#
FROM base-debug AS dev

COPY .docker/php-fpm/wait-for-it.sh /usr/bin/wait-for-it
RUN chmod +x /usr/bin/wait-for-it

RUN echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /var/www/html
CMD wait-for-it mongodb:27017 -- php-fpm
EXPOSE 9000


#
# production builder (installs dependencies)
#
FROM $base_image AS builder

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html/

RUN APP_ENV=prod composer install --no-interaction --no-dev --optimize-autoloader
RUN APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear


#
# nginx image
#
FROM nginx:${NGINX_VERSION}-alpine AS nginx

COPY .docker/nginx/ /etc/nginx/

WORKDIR /var/www/html
COPY --from=builder --chown=nginx:nginx /var/www/html/public/ /var/www/html/public/

EXPOSE 9091
