# Backend: PHP 8.4 + pdo_sqlsrv. Etapas:
#   base -> extensiones comunes (sqlsrv, zip, opcache) y endurecimiento de PHP.
#   dev  -> composer/artisan/pest en contenedor con el código montado (artisan serve).
#   prod -> PHP-FPM con el código y dependencias de producción dentro de la imagen.
# Bookworm porque es la versión de Debian con repositorio oficial de msodbcsql18 más estable.
FROM php:8.4-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl gnupg2 unzip git unixodbc-dev libzip-dev \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && docker-php-ext-install zip opcache \
    && apt-get purge -y gnupg2 && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# RN-10: las trazas de excepciones no incluyen argumentos (podrían ser datos de pacientes).
RUN printf 'zend.exception_ignore_args = On\nexpose_php = Off\n' > /usr/local/etc/php/conf.d/zz-seguridad.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# --- Desarrollo ---------------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000

RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g ${GID} app
USER app

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# --- Producción ---------------------------------------------------------------------
FROM base AS prod

COPY docker/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-farmart.conf
COPY docker/entrypoint.sh /usr/local/bin/farmart-entrypoint

# Dependencias primero (capa cacheable mientras no cambie composer.lock).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/farmart-entrypoint

USER www-data

EXPOSE 9000
ENTRYPOINT ["farmart-entrypoint"]
CMD ["php-fpm"]
