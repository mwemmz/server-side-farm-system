# Use the official PHP image with Apache
FROM php:8.2-apache

# Install PostgreSQL PHP extensions and dependencies for Composer
RUN apt-get update && apt-get install -y libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . /var/www/html/

# Install composer dependencies (e.g. PHPMailer)
RUN if [ -f /var/www/html/composer.json ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --working-dir=/var/www/html ; \
    fi

# Tell Apache to serve from the 'public' directory
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Bind Apache to Render's $PORT on 0.0.0.0 (keeps port 80 as a fallback)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
CMD ["/usr/local/bin/docker-entrypoint.sh"]
