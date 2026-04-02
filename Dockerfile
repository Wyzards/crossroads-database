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

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Make it executable
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Your existing CMD (IMPORTANT: keep this)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
