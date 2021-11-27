FROM php:8.0-apache

# Install build dependencies, e.g. composer, system libs
RUN apt-get update 1> /dev/null \
    && apt-get install -y git zip unzip libgd3 \ 
        libpng-dev libwebp-dev libjpeg62-turbo-dev libxpm-dev libfreetype6-dev 1> /dev/null \
    && php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get -y autoremove 1> /dev/null \
    && apt-get clean 1> /dev/null \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP
RUN pecl install mongodb 1> /dev/null \
    && docker-php-ext-configure gd --enable-gd --with-webp --with-jpeg \
        --with-xpm --with-freetype \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install bcmath exif gd 1> /dev/null

WORKDIR /var/www/app

COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY inventory-php.ini /usr/local/etc/php/conf.d/inventory-php.ini
