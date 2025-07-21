FROM php:8.2-fpm

# Install PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    zip unzip curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev libjpeg-dev libfreetype6-dev \
    gnupg \
    && docker-php-ext-install pdo_mysql zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js and npm
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add production PHP settings
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www

COPY . /var/www

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build frontend during image build
RUN npm install && npm run build

# Create a script to fix permissions and run Laravel commands
RUN echo '#!/bin/bash\n\
mkdir -p /var/www/storage/logs\n\
mkdir -p /var/www/storage/framework/views\n\
mkdir -p /var/www/storage/framework/cache\n\
mkdir -p /var/www/storage/framework/sessions\n\
chmod -R 775 /var/www/storage\n\
chmod -R 775 /var/www/bootstrap/cache\n\
chown -R www-data:www-data /var/www/storage\n\
chown -R www-data:www-data /var/www/bootstrap/cache\n\
\n\
# Run Laravel optimizations and migrations\n\
if [ -f /var/www/artisan ]; then\n\
  php /var/www/artisan migrate --force\n\
  php /var/www/artisan config:cache\n\
  php /var/www/artisan route:cache\n\
  php /var/www/artisan view:cache\n\
fi\n\
\n\
php-fpm\n\
' > /usr/local/bin/start-container && chmod +x /usr/local/bin/start-container

CMD ["/usr/local/bin/start-container"]
