FROM php:8.0-fpm

RUN docker-php-ext-install pdo pdo_mysql

RUN pecl install xdebug-3.1.3 \
    && docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./app /var/www/html

RUN composer install