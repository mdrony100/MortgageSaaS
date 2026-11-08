FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY tsconfig.json vite.config.ts ./
RUN npm run build

FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev libfreetype6-dev libjpeg62-turbo-dev && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install pdo_pgsql mbstring bcmath pcntl zip gd intl && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/mortgagelens.ini
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist && mkdir -p storage/app/private-documents storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/public/build ./public/build
USER www-data
EXPOSE 8000
CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]
