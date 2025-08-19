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

# Create required directories for Laravel
RUN mkdir -p /var/www/bootstrap/cache \
    && mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build frontend at container start (not during image build)
# (removed the previous "RUN npm install && npm run build" to avoid hidden artifacts when using bind-mounts)

# Create a script to fix permissions and run Laravel commands
RUN echo '#!/bin/bash\n\
# Set proper ownership for runtime\n\
chown -R www-data:www-data /var/www/storage\n\
chown -R www-data:www-data /var/www/bootstrap/cache\n\
\n\
# Build frontend if package.json exists (run after host bind-mount is applied)\n\
if [ -f /var/www/package.json ]; then\n\
  cd /var/www\n\
  if command -v npm >/dev/null 2>&1; then\n\
    npm ci --silent || npm install --silent\n\
    npm run build --silent || true\n\
    chown -R www-data:www-data /var/www/public || true\n\
  fi\n\
fi\n\
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
