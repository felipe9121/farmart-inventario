# Imagen de desarrollo: PHP CLI + pdo_sqlsrv + Composer para correr artisan/pest en contenedor.
# Bookworm porque es la versión de Debian con repositorio oficial de msodbcsql18 más estable.
FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

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
    && docker-php-ext-install zip \
    && apt-get purge -y gnupg2 && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# RN-10: las trazas de excepciones no incluyen argumentos (podrían ser datos de pacientes).
RUN echo 'zend.exception_ignore_args = On' > /usr/local/etc/php/conf.d/zz-seguridad.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g ${GID} app
USER app
WORKDIR /var/www/html

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
