#!/bin/sh
set -e

# Cachea config, rutas y vistas con las variables del contenedor (necesita APP_KEY, etc.).
if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan optimize --no-ansi
fi

exec "$@"
