FROM php:8.2-apache
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader
RUN php artisan key:generate
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache
EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=8000
