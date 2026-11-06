# ==============================================================================
# Bremspace ERP & POS System — Multi-Stage High Performance Container
# Base: PHP 8.5+ Alpine Linux (Ultra-lightweight, High Security, OPcache Ready)
# Brand: Bremspace
# ==============================================================================

FROM php:8.5-rc-fpm-alpine

LABEL maintainer="Bremspace Developer Team"
LABEL description="Bremspace ERP & POS System Container (PHP 8.5+ Alpine)"

# Set working directory
WORKDIR /var/www/html

# Environment variables
ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Asia/Jakarta \
    COMPOSER_ALLOW_SUPERUSER=1

# Install system runtime dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    tzdata \
    bash \
    supervisor \
    netcat-openbsd

# Install PHP extensions using official multi-platform extension installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd zip bcmath exif intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-bremspace.ini

# Copy application source code
COPY . /var/www/html

# Install Composer dependencies (production optimized)
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# Backup public assets to template directory for volume sync
RUN cp -r public /var/www/html/public_template

# Backup database directory to template for volume fallback
RUN cp -r database /var/www/html/database_template

# Prepare storage directories, database & cache permissions
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    bootstrap/cache \
    database \
    && chmod -R 775 storage bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database

# Entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
