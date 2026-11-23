# syntax=docker/dockerfile:1
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libonig-dev libxml2-dev default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY Installer*docker/.env.docker .env

# Pastikan .env bisa ditulis oleh proses app (www-data) dari UI Pengaturan
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

# PHP runtime limits for large DB restore/upload
RUN cat > /usr/local/etc/php/conf.d/99-ute-parts.ini <<'PHPINI'
upload_max_filesize=512M
post_max_size=512M
max_execution_time=3600
max_input_time=3600
memory_limit=1G
max_file_uploads=50
default_socket_timeout=3600
PHPINI

# Apache timeout for long-running restore
RUN printf '\nTimeout 3600\n' >> /etc/apache2/apache2.conf

COPY Installer*docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]