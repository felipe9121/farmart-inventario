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

### Dispensación
- **Un solo punto modifica stock:** `InventarioService`. Exige una transacción abierta, bloquea la
  fila y registra el movimiento de kardex con su saldo. Los traslados lo reutilizarán.
- **Bloqueo (RN-03):** `lockForUpdate` genera `WITH (ROWLOCK, UPDLOCK, HOLDLOCK)`. El orden es fijo:
  prescripción → ítems (por id) → existencias (por `producto_id` y luego id). Si SQL Server elige la
  transacción como víctima de un deadlock (1205), `DB::transaction` la reintenta
  (`FARMART_INTENTOS_TRANSACCION`, 3 por defecto).
- **Por qué el bloqueo y no solo el CHECK:** sin `lockForUpdate`, la prueba de concurrencia dispensa 18
  unidades de un lote con 10. Cada proceso lee 10 y escribe 7 (actualización perdida), sin llegar
  nunca a un valor negativo que el CHECK pueda rechazar.
- **FEFO (RN-02):** se bloquean todas las existencias vigentes del producto en la bodega y se reparten
  por fecha de vencimiento. Queda una fila de `dispensacion_items` y un movimiento por lote.
- **Stock insuficiente:** la dispensación se rechaza completa (422 `STOCK_INSUFICIENTE` con lo
  disponible), no se dispensa parcialmente de forma automática. Para una parcial se pide una
  cantidad menor; las parciales se acumulan (RN-04).
- **Control especial (RN-05): co-firma en la misma petición** (`autorizacion.email/password` del
  regente), como un "supervisor override". Se verifica antes de abrir la transacción (bcrypt es
  lento y no debe retener bloqueos) y el flag del producto se revalida con los datos bloqueados.
  Se descartó un flujo en dos pasos porque obligaría a reservar stock mientras se espera la
  aprobación. La clave lleva `#[SensitiveParameter]` y no entra en el hash de idempotencia.
  Un usuario inexistente y una clave errónea dan el mismo error.
- **Idempotencia (RN-09)** según el borrador IETF de `Idempotency-Key`: 400 si falta el header,
  201 al crear, 200 + `Idempotent-Replayed: true` al repetir y 422 si la llave se reutiliza con otro
  contenido. Si dos peticiones con la misma llave compiten, el UNIQUE filtrado deja pasar una y la
  otra devuelve el resultado de la ganadora. Una dispensación fallida no consume la llave.
- **Errores de negocio:** 422 con `codigo` estable y `detalle` que solo lleva IDs y cantidades. No se
  registran en el log.
- **Fecha de negocio:** "hoy" es el día en `America/Bogota` (`FARMART_ZONA_HORARIA`), aunque los
  timestamps se guarden en UTC. Se comparan fechas, no instantes.
- **Tipos de `pdo_sqlsrv`:** con `SQLSRV_ATTR_FETCHES_NUMERIC_TYPE`, `int` y `bit` llegan como enteros.
  `bigint` (los IDs) sigue llegando como string, así que las FKs llevan cast `integer`.
- **Pendiente para la etapa 4:** login para emitir tokens, bitácora de accesos al dispensar y límite
  de intentos de la co-firma.

```http
POST /api/dispensaciones
Authorization: Bearer <token>
Idempotency-Key: 7f9c1a52-...

{ "paciente_id": 1, "prescripcion_id": 1, "bodega_id": 1,
  "items": [{ "prescripcion_item_id": 1, "cantidad": 5 }],
  "autorizacion": { "email": "regente1@farmart.test", "password": "..." } }
```

### Pruebas
- `./vendor/bin/pest` corre todo. `--testsuite=Concurrencia` corre solo las pruebas con procesos PHP
  paralelos (`Concurrency::run`). Esa suite no usa `RefreshDatabase`, porque los otros procesos deben
  ver datos confirmados: recrea la base antes de cada prueba.

### Entorno
- **Laravel 13 / PHP 8.4** (Laravel 13 exige PHP ≥ 8.3).
- La app se conecta como `sa` en desarrollo; en la etapa de seguridad se creará un usuario con
  permisos mínimos.
- Conexión cifrada (ODBC Driver 18); `DB_TRUST_SERVER_CERTIFICATE=true` solo en local por el
  certificado autofirmado.
- Las pruebas usan la base `farmart_test` en SQL Server (nunca SQLite), con `RefreshDatabase`.
