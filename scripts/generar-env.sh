#!/usr/bin/env bash
# Genera .env (raíz) y backend/.env a partir de los .env.example, con claves aleatorias.
# No sobrescribe archivos existentes salvo con --forzar. Variables opcionales:
#   SEED_DEMO=true  -> el stack carga datos de demostración.
# Uso: scripts/generar-env.sh [--forzar]
set -euo pipefail
cd "$(dirname "$0")/.."

forzar="${1:-}"

# SQL Server exige complejidad: el sufijo garantiza mayúscula, minúscula, dígito y símbolo.
clave() { echo "$(openssl rand -hex 12)Aa1!"; }
valor() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }

if [[ -f .env && "$forzar" != "--forzar" ]]; then
    echo ".env ya existe: se reutilizan sus claves (use --forzar para regenerarlas)."
else
    sed -e "s|^MSSQL_SA_PASSWORD=.*|MSSQL_SA_PASSWORD=$(clave)|" \
        -e "s|^MSSQL_APP_PASSWORD=.*|MSSQL_APP_PASSWORD=$(clave)|" \
        -e "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" \
        -e "s|^SEED_DEMO=.*|SEED_DEMO=${SEED_DEMO:-false}|" \
        -e "s|^UID=.*|UID=$(id -u)|" \
        -e "s|^GID=.*|GID=$(id -g)|" \
        .env.example > .env
    echo ".env generado."
fi

if [[ -f backend/.env && "$forzar" != "--forzar" ]]; then
    echo "backend/.env ya existe: no se modifica."
else
    sed -e "s|^APP_KEY=.*|APP_KEY=$(valor APP_KEY)|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=\"$(valor MSSQL_APP_PASSWORD)\"|" \
        -e "s|^DB_ADMIN_PASSWORD=.*|DB_ADMIN_PASSWORD=\"$(valor MSSQL_SA_PASSWORD)\"|" \
        backend/.env.example > backend/.env
    echo "backend/.env generado."
fi
