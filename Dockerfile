# Use the latest PHP image with Apache
FROM php:8.3-apache

# Set the working directory
WORKDIR /var/www/html

# Install required system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Copy composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files into container
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel storage and cache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Expose port 80 for web traffic
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]
