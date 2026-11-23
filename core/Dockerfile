FROM php:8.5-fpm

# Install system dependencies (Debian base)
RUN apt-get update && apt-get install -y git curl libpng-dev libjpeg-dev libfreetype6-dev unzip zip libicu-dev libonig-dev libpq-dev libzip-dev libssl-dev build-essential && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl zip pdo_pgsql && \
    pecl install redis && docker-php-ext-enable redis && \
    rm -rf /var/lib/apt/lists/*

# PHP extensions are pre‑installed in the base image

# Install Composer (official installer)
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php && php composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm composer-setup.php

# Set working directory
WORKDIR /var/www/html

# Copy application source code and install dependencies
COPY . /var/www/html

# Install PHP dependencies without dev requirements
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Ensure permissions (optional, adjust as needed)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8000

# Default command (will be overridden by docker-compose if needed)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
