FROM php:8.3-apache

# Apache serves /public and PHP only needs the extensions used by the app.
RUN apt-get update \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/backups /var/www/html/storage/cache /var/www/html/storage/config /var/www/html/storage/logs /var/www/html/storage/updates /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads

EXPOSE 80
