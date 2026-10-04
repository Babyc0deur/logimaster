# --- 1) Assets (Vite / Tailwind) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- 2) Application ---
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libgmp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql zip intl gd bcmath mbstring exif opcache pcntl gmp \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'upload_max_filesize=12M\npost_max_size=12M\nmemory_limit=512M\nopcache.enable=1\nopcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh
EXPOSE 10000
CMD ["start.sh"]
