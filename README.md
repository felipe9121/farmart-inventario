# FARTMAR IPS — Dispensación e inventarios

Prueba técnica: API Laravel + SQL Server 2022 + frontend React.

## Levantar el entorno (desarrollo)

```bash
cp .env.example .env                    # cambiar MSSQL_SA_PASSWORD
cp backend/.env.example backend/.env    # DB_PASSWORD = MSSQL_SA_PASSWORD
docker compose run --rm backend php artisan key:generate
docker compose run --rm backend php artisan migrate:fresh --seed
docker compose run --rm backend ./vendor/bin/pest
```

Calidad: `composer lint`, `composer format`, `composer analyse` (dentro del contenedor `backend`).

Usuarios de demostración (clave `password`, solo local): `admin@`, `regente1@`, `regente2@`,
`auxiliar1@`, `auxiliar2@`, `medico@`, `auditor@` — todos `@farmart.test`.

## Supuestos y decisiones

### Base de datos
- **Las reglas viven también en la BD.** Cada regla verificable sin contexto externo tiene un
  `CHECK`, `FK` o `UNIQUE` con nombre explícito (`ck_`, `fk_`, `uq_`), y una prueba Pest que intenta
  violarla contra SQL Server. Las reglas que dependen de la fecha actual (lote vencido, prescripción
  vigente) o de otras tablas (rol del autorizador) se validan en los servicios.
- **NULL en los CHECK:** un `CHECK` solo rechaza `FALSE`, y `LEN(NULL) > 0` da `UNKNOWN`, así que
  las columnas que pueden ser nulas llevan `IS NOT NULL` explícito.
- **Rol como columna con `CHECK`**, sin tabla de roles: son 5 roles fijos sin permisos dinámicos.
  `rol` queda fuera de `$fillable` para evitar escalar privilegios por asignación masiva.
- **Existencia = bodega + producto + lote (RN-01).** `producto_id` es redundante con el lote, pero
  evita JOINs en la búsqueda FEFO. La FK compuesta `(lote_id, producto_id) → lotes(id, producto_id)`
  impide que no coincida con el lote.
- **Cantidades enteras** en unidades mínimas de dispensación.
- **Un lote es usable hasta su fecha de vencimiento, inclusive.**
- **Prescripción:** `cantidad_dispensada` acumula las parciales y el `CHECK 0 <= dispensada <= prescrita`
  impide en la BD exceder lo prescrito (RN-04). Vigente entre emisión y `vigente_hasta`, inclusive.
- **Idempotencia (RN-09):** la llave y el hash del payload se guardan en `dispensaciones` con un
  UNIQUE **filtrado** `(dispensado_por, idempotency_key) WHERE idempotency_key IS NOT NULL`, porque en
  SQL Server un UNIQUE normal solo admite un NULL. El hash permite detectar la misma llave con otro cuerpo.
- **Traslados:** cada ítem es un lote concreto, elegido al solicitar. La BD valida la coherencia
  entre estado y responsables (no hay APROBADO sin aprobador, etc.) y RN-08 (`aprobado_por <> solicitado_por`).
  Solo se anula antes del despacho. La discrepancia es una columna calculada `PERSISTED`
  (`despachada - recibida`).
- **Kardex inmutable (RN-06):** triggers `INSTEAD OF UPDATE/DELETE` con `THROW`. Dentro de un trigger
  el `THROW` revierte la transacción completa, así que un intento de editar el kardex aborta toda la
  operación. Cada movimiento guarda el `saldo_resultante` y una FK explícita (no polimórfica) a su
  documento de origen. Los UNIQUE filtrados impiden despachar o recibir dos veces el mismo ítem.
- **Bitácora de accesos (RN-10):** también inmutable. Solo guarda IDs, rol, acción e IP; nunca
  datos personales ni el término buscado.
- **Sin borrado en cascada:** SQL Server no admite múltiples rutas de cascada y, por auditoría,
  no se borran documentos con historia.
- Se modificó la migración base de `users` en lugar de crear una nueva porque aún no hay despliegues.

### Entorno
- **Laravel 13 / PHP 8.4** (Laravel 13 exige PHP ≥ 8.3).
- La app se conecta como `sa` en desarrollo; en la etapa de seguridad se creará un usuario con
  permisos mínimos.
- Conexión cifrada (ODBC Driver 18); `DB_TRUST_SERVER_CERTIFICATE=true` solo en local por el
  certificado autofirmado.
- Las pruebas usan la base `farmart_test` en SQL Server (nunca SQLite), con `RefreshDatabase`.
