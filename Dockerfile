FROM php:8.3-cli

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev

RUN docker-php-ext-install pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy entire project first
COPY . .

RUN composer install --no-interaction --prefer-dist

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
