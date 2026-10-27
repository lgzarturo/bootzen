# Imagen de producción: PHP 8.3 + Apache con public/ como raíz web.
# Construir y levantar: docker compose up -d --build (guía en docs/deploy/docker.md)
FROM php:8.3-apache

# pdo_sqlite, mbstring, fileinfo y ctype ya vienen en la imagen oficial
RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/bootzen.ini
COPY deploy/docker/apache.conf /etc/apache2/conf-enabled/zz-bootzen.conf
COPY deploy/docker/entrypoint.sh /usr/local/bin/bootzen-entrypoint

WORKDIR /var/www/html
COPY . .

# Vistas precompiladas y permisos para el usuario de Apache
RUN php bin/bootzen view:cache \
    && chown -R www-data:www-data storage public/uploads

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_DATABASE=storage/database/app.sqlite

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'

ENTRYPOINT ["bootzen-entrypoint"]
CMD ["apache2-foreground"]
