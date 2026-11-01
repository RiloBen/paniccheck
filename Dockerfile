FROM php:8.3-cli-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    curl \
    git \
    nodejs \
    npm \
    sqlite-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_sqlite pdo_mysql zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Set permissions for storage, cache, and database directory (crucial for SQLite journaling)
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database \
    && chmod -R 777 storage bootstrap/cache database

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install Node dependencies & build frontend assets
RUN npm install && npm run build

# Expose port (Render/Koyeb/Railway dynamically inject PORT)
EXPOSE 8000

# Start script: initialize storage, sqlite, run migrations & seeder, and serve
CMD ["sh", "-c", "php artisan storage:link || true; touch database/database.sqlite && chmod 777 database/database.sqlite; php artisan migrate --force; php artisan db:seed --force; php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
