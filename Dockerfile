# Use the official PHP image with Apache
FROM php:8.2-apache

# Install PostgreSQL PHP extensions, git and unzip (for Composer)
RUN apt-get update && apt-get install -y libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy your application code
COPY . /var/www/html/

# Install PHP dependencies (PHPMailer)
RUN cd /var/www/html && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Tell Apache to serve from the 'public' directory
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Enable Apache mod_rewrite
RUN a2enmod rewrite