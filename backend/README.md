# Backend — API Laravel 13 (PHP 8.4) + SQL Server 2022

La documentación del proyecto está en el [README principal](../README.md) y en [`docs/`](../docs).

Estructura:

| Ruta | Contenido |
|---|---|
| `app/Actions/` | Casos de uso con transacción: dispensación, traslados, ajustes |
| `app/Services/` | Inventario (FEFO, bloqueo, kardex), alertas, auditoría, asistente |
| `app/Http/` | Controladores delgados, Form Requests, Resources y middleware |
| `app/Policies/` | Autorización por rol |
| `database/migrations/` | Esquema con `CHECK`, `FK`, `UNIQUE` filtrados, triggers y permisos |
| `tests/Feature`, `tests/Unit` | Pest contra SQL Server (`farmart_test`), como usuario sin DDL |
| `tests/Concurrencia/` | Procesos PHP paralelos contra SQL Server (bloqueos reales) |

Comandos, siempre dentro del contenedor (`docker compose run --rm backend …`):

```bash
composer lint        # Pint (--test)
composer analyse     # Larastan
./vendor/bin/pest    # pruebas; --testsuite=Concurrencia para las paralelas
php artisan migrate --database=sqlsrv_admin   # las migraciones usan la conexión de admin
```
