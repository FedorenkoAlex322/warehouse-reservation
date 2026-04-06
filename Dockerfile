FROM php:8.2-cli-alpine

RUN apk add --no-cache curl git unzip postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy only composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-plugins --optimize-autoloader

# Copy full application
COPY . .

# Run post-install scripts now that artisan is available
RUN composer run-script post-autoload-dump --no-interaction \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
