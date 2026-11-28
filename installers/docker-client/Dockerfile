# syntax=docker/dockerfile:1
# =============================================================================
# UTE PARTS ERP - Docker image khusus NODE CLIENT (primary_store)
# =============================================================================
# Build context = ROOT repository (lihat docker-compose.yml / install.sh).
# Sengaja TIDAK pakai `COPY . .`: .env.client berisi rahasia wizard dan
# tidak boleh masuk ke layer image. Hanya path aplikasi yang disalin.
# Template .env.client.example (tanpa rahasia) yang dibawa ke dalam image.
# =============================================================================

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js .npmrc ./
COPY resources ./resources
COPY public ./public
RUN npm ci --ignore-scripts
RUN npm run build

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev libicu-dev libonig-dev libxml2-dev default-mysql-client \
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
  && a2enmod rewrite headers \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Kode aplikasi (tanpa folder installers/ agar .env.client tidak ikut) ----
COPY artisan composer.json composer.lock package.json package-lock.json vite.config.js version.json ./
COPY app bootstrap config database lang public resources routes storage ./
# Composer autoload memuat app/Helpers/functions.php saat package:discover.
# Salin salinan kanonik dari installer agar build tidak gagal bila file itu
# tidak ikut ter-copy dari context (zip download / filter Docker).
COPY installers/docker-client/helpers/functions.php ./app/Helpers/functions.php
COPY --from=assets /app/public/build ./public/build

# --- Template env & helper installer (aman, tanpa secret) --------------------
COPY installers/docker-client/.env.client.example .env
COPY installers/docker-client/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY installers/docker-client/bootstrap-client-admin.php /usr/local/bin/bootstrap-client-admin.php

# .env bisa ditulis www-data (UI Pengaturan / generate APP_KEY saat start)
RUN chown www-data:www-data .env && chmod 664 .env
RUN grep -q '^APP_KEY=' .env || (printf '\nAPP_KEY=\n' >> .env)

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
  && chown -R www-data:www-data storage bootstrap/cache

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

RUN chown -R www-data:www-data storage bootstrap/cache public/build \
  && chmod -R 775 storage bootstrap/cache

# Docroot Apache -> /public. WAJIB double-quote: pada single-quote shell tidak
# meng-expand ${APACHE_DOCUMENT_ROOT}, sed menerimanya literal, dan DocumentRoot
# jadi "/var/www/${APACHE_DOCUMENT_ROOT}" (semua request 404).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
  && sed -ri -e "s!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}>!" /etc/apache2/apache2.conf \
  && sed -ri -e "/<Directory \/var\/www\/html\/public>/,/<\/Directory>/ s!AllowOverride None!AllowOverride All!" /etc/apache2/apache2.conf \
  && grep -q "DocumentRoot ${APACHE_DOCUMENT_ROOT}" /etc/apache2/sites-available/000-default.conf \
  && grep -q "<Directory ${APACHE_DOCUMENT_ROOT}>" /etc/apache2/apache2.conf \
  && grep -q "AllowOverride All" /etc/apache2/apache2.conf

# PHP runtime limits untuk restore/backup database besar
RUN cat > /usr/local/etc/php/conf.d/99-ute-parts.ini <<'PHPINI'
upload_max_filesize=512M
post_max_size=512M
max_execution_time=3600
max_input_time=3600
memory_limit=1G
max_file_uploads=50
default_socket_timeout=3600
PHPINI

RUN printf '\nTimeout 3600\n' >> /etc/apache2/apache2.conf

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
