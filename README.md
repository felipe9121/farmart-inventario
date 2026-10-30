# FARTMAR IPS — Dispensación e inventarios

Prueba técnica: API Laravel + SQL Server 2022 + frontend React.

## Levantar todo el sistema (un comando)

```bash
cp .env.example .env
# Editar .env: cambiar MSSQL_SA_PASSWORD y MSSQL_APP_PASSWORD, poner SEED_DEMO=true para datos
# de demostración y generar APP_KEY:
#   echo "base64:$(openssl rand -base64 32)"
docker compose up -d --build
# → http://localhost:8080  (usuarios de demostración más abajo)
```

Orden de arranque: `sqlserver` (con healthcheck) → `sqlserver-init` (bases y usuario `farmart_app`) →
`migrate` (migraciones y semillas, con credenciales de admin) → `app` (PHP-FPM) → `web` (nginx, el único
puerto publicado).

## Levantar el entorno (desarrollo)

```bash
cp .env.example .env                    # cambiar MSSQL_SA_PASSWORD y MSSQL_APP_PASSWORD
cp backend/.env.example backend/.env    # DB_PASSWORD = MSSQL_APP_PASSWORD, DB_ADMIN_PASSWORD = MSSQL_SA_PASSWORD
docker compose run --rm backend composer install
docker compose run --rm backend php artisan key:generate
docker compose run --rm backend php artisan migrate:fresh --seed --database=sqlsrv_admin
docker compose run --rm backend ./vendor/bin/pest
```

Las migraciones se ejecutan siempre con `--database=sqlsrv_admin`: el usuario de la app no tiene
permisos de DDL, así que un `migrate` sin esa opción falla a propósito.

Para obtener un token: `POST /api/auth/login` con `{"email": "...", "password": "..."}`.

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

```http
POST /api/dispensaciones
Authorization: Bearer <token>
Idempotency-Key: 7f9c1a52-...

{ "paciente_id": 1, "prescripcion_id": 1, "bodega_id": 1,
  "items": [{ "prescripcion_item_id": 1, "cantidad": 5 }],
  "autorizacion": { "email": "regente1@farmart.test", "password": "..." } }
```

### Traslados, kardex y ajustes
- **Máquina de estados en `EstadoTraslado`** (RN-07): `BORRADOR → SOLICITADO → APROBADO → EN_TRANSITO →
  RECIBIDO | RECIBIDO_PARCIAL`. Se puede pasar a `ANULADO` solo antes del despacho. La BD valida que cada
  estado tenga sus responsables, y la aplicación valida la transición.
- **Cada transición bloquea primero el traslado** y valida el estado con la fila bloqueada. Si dos
  regentes aprueban a la vez, uno gana y el otro recibe **409** `TRANSICION_INVALIDA`.
- **RN-08:** solo los regentes aprueban, y nunca el mismo usuario que solicitó (403 `APROBADOR_ES_SOLICITANTE`,
  respaldado por el CHECK).
- **Supuesto: cada ítem es un lote concreto, elegido al solicitar.** Al crear se revisa el stock como aviso
  temprano; la verificación definitiva es al despachar, con la fila bloqueada. El lote vencido se rechaza
  tanto al crear como al despachar (RN-01).
- **El despacho saca el stock del origen** (`TRASLADO_SALIDA`) y permite despachar menos de lo solicitado
  por ítem, incluso 0. Si un ítem falla, no se mueve nada.
- **La recepción entra el stock al destino** (`TRASLADO_ENTRADA`) y crea la existencia si no había. Hay que
  informar cada ítem, aunque sea con 0. Lo despachado y no recibido queda como **discrepancia pendiente**
  (columna calculada) y el traslado pasa a `RECIBIDO_PARCIAL`. Esas unidades no vuelven al origen; si
  aparecen después, se corrigen con un ajuste.
- **Orden de bloqueo común** con dispensación: primero el documento (prescripción o traslado), luego sus
  ítems por id y luego las existencias por `producto_id` e id de existencia. En traslados se leen los ids
  sin bloquear, se ordenan y después se bloquean, para no cruzarse con una dispensación del mismo producto.
- **Ajustes (RN-06):** `POST /api/ajustes`, solo para regentes, con motivo obligatorio. Son la única forma
  de corregir stock, porque el kardex no se edita. Permiten dar de baja lotes vencidos. Los ajustes no usan
  `Idempotency-Key`, ya que RN-09 solo lo exige para dispensaciones; una UI que reintente debería evitar
  el doble envío.
- **Kardex:** `GET /api/kardex`, con filtros por bodega, producto, lote, tipo y fechas, ordenado por id
  (el orden real de registro).

| Método | Ruta | Quién |
|---|---|---|
| GET | `/api/traslados`, `/api/traslados/{id}` | auxiliar, regente, auditor, admin |
| POST | `/api/traslados`, `/{id}/solicitar`, `/{id}/despachar`, `/{id}/recibir` | auxiliar, regente |
| POST | `/api/traslados/{id}/aprobar` | regente (≠ solicitante) |
| POST | `/api/traslados/{id}/anular` | creador o regente |
| POST | `/api/ajustes` | regente |
| GET | `/api/kardex` | auxiliar, regente, auditor, admin |

### Seguridad y auditoría
- **Autenticación:** tokens Bearer de Sanctum que expiran a las 8 h (`SANCTUM_EXPIRATION`).
  - Para no dar pistas a un atacante, el login devuelve el mismo 401 si la clave es errónea, si el
    usuario no existe o si está inactivo, y compara un hash incluso cuando el usuario no existe
    (así el tiempo de respuesta tampoco revela nada).
  - Hay un máximo de 5 intentos por minuto por correo + IP.
  - Un usuario desactivado pierde el acceso de inmediato, aunque tenga tokens vigentes.
- **Datos de pacientes (RN-10):**
  - **Quién accede:** auxiliar, regente, médico y auditor. El **admin no accede**, por mínimo
    privilegio: administra el sistema, no atiende pacientes.
  - **Enmascarado:** el **auditor** ve los datos enmascarados (iniciales, los últimos 4 dígitos del
    documento y solo el año de nacimiento).
  - **Búsqueda:** solo por **documento exacto**, sin listados ni búsqueda por nombre, para que no se
    puedan volcar datos.
  - **Caché:** las respuestas van con `Cache-Control: no-store`.
- **Bitácora de accesos:**
  - Se registra cada búsqueda (también sin resultado), cada consulta y cada dispensación. La de la
    dispensación va en la misma transacción.
  - Se guardan el usuario, su rol en ese momento, el paciente, la acción y la IP. Nunca el término buscado.
  - La consultan el auditor y el admin en `GET /api/bitacora-accesos`.
- **Co-firma de control especial:** se bloquea tras 5 fallos cada 5 minutos (429). El límite es por
  correo del regente + usuario que dispensa, para que nadie pueda bloquear a un regente a propósito.
- **Ningún dato personal en logs:**
  - **El problema:** el mensaje de un `QueryException` trae los valores de la consulta, y SQL Server
    repite el dato en conflicto ("The duplicate key value is (CC, 9012345678)").
  - **La solución:** estos errores se registran solo con el SQLSTATE, el código del driver, la
    restricción violada y el SQL con `?`. Además, `zend.exception_ignore_args=On` quita de las trazas
    los argumentos de las funciones.
  - **Cómo se comprueba:** una prueba provoca el error con datos reales y revisa todo lo que se registró.
- **Usuario de BD con mínimo privilegio:**
  - **Permisos:** la app se conecta como `farmart_app`, que solo tiene `db_datareader` y
    `db_datawriter`. Sin DDL, no puede crear ni borrar tablas, ni desactivar triggers.
  - **Restricciones extra:** una migración le aplica `DENY UPDATE/DELETE` sobre el kardex y la
    bitácora, y `DENY DELETE` sobre los documentos y catálogos con historia.
  - **Dos barreras:** aun con permisos de administrador, los triggers siguen impidiendo editar el kardex.
  - **Migraciones:** usan la conexión `sqlsrv_admin`.
  - **Pruebas:** **corren como `farmart_app`**; si pasan, la app funciona con esos permisos.

### Alertas y asistente de inventario
- **Alertas (RN-11):**
  - `GET /api/alertas/vencimientos` (`?bodega_id`, `?dias`, 90 por defecto) incluye también los lotes
    **ya vencidos que aún tienen stock**, porque hay que darlos de baja.
  - `GET /api/alertas/stock-bajo` compara el mínimo de cada bodega con el **stock vigente**. Los lotes
    vencidos no cuentan porque no se pueden dispensar.
- **Asistente (con mock):** `POST /api/asistente/preguntas {"pregunta": "..."}`. Por decisión de alcance
  responde **solo dos preguntas**: qué lotes vencen y qué productos están bajo el mínimo, en una bodega
  o en todas.
  - **El modelo solo interpreta.** `ModeloLenguaje` recibe la pregunta y el catálogo de bodegas y devuelve
    la intención, la bodega y los días. No recibe datos de inventario ni de pacientes.
  - **Los datos salen de la BD** (`AlertasInventario`) y **la respuesta se arma con plantillas**, así que
    el modelo no puede inventar cifras. La respuesta incluye las filas que la respaldan.
  - **El mock** (`ModeloLenguajeSimulado`) es determinista: funciona con palabras clave y no distingue
    tildes ni mayúsculas. Si la pregunta no corresponde o es ambigua, no adivina: explica su alcance y
    sugiere las dos preguntas.
  - **Un modelo real** sería otra implementación del contrato, seleccionada con `FARMART_ASISTENTE_MODELO`;
    no habría que cambiar nada más. Solo recibiría la pregunta y nombres de bodegas.
  - **Privacidad:** la pregunta no se guarda ni se registra en logs, por si el usuario escribe datos
    personales.

### Frontend
- **Alcance:** cuatro pantallas **sencillas** (login, dispensación, traslados, y alertas con kardex), porque
  en esta prueba el peso está en el backend. React + TypeScript + Vite, **sin router ni librerías de UI**.
- **Las reglas que la UI debe respetar** están en funciones puras con pruebas Vitest:
  - acciones de un traslado según estado y rol (quien solicitó no ve "aprobar");
  - co-firma solo cuando hay productos de control especial;
  - **reutilizar la llave de idempotencia** si se reintenta el mismo contenido (doble clic o red caída),
    para no duplicar la dispensación.
- **La UI solo oculta**, no autoriza: cada petición la vuelve a autorizar el backend.
- **API sin CORS:** el proxy de Vite reenvía `/api` al backend, así que el navegador trabaja con un solo origen.
- **Token en `sessionStorage`:** es simple y se borra al cerrar la pestaña. El trade-off es que queda
  expuesto si hubiera XSS. La alternativa más robusta sería la autenticación de SPA de Sanctum con cookies
  `httpOnly`, que exige CSRF y el mismo dominio.
- **TypeScript 6.0** en lugar del 7 de la plantilla, porque `typescript-eslint` (el CLAUDE.md exige ESLint)
  aún no soporta la 7.

```bash
cd frontend
docker run --rm -it -u 1000:1000 -v "$PWD":/app -w /app --network farmart_default \
  -e VITE_API_URL=http://backend:8000 -p 5173:5173 node:24-alpine npx vite --host 0.0.0.0
# requiere el backend arriba: docker compose up -d backend  → http://localhost:5173
```

Calidad del frontend: `npm run lint`, `npm run typecheck`, `npm test`.

### Docker
- **Un solo `docker compose up`** levanta el sistema completo. Las migraciones son un servicio `migrate` de
  una sola ejecución; con `SEED_DEMO=true` carga datos sintéticos **solo si la base está vacía** (el seeder
  es idempotente y se niega a correr con `APP_ENV=production`).
- **nginx es la única puerta de entrada:** sirve la SPA y envía `/api` y `/up` por FastCGI a PHP-FPM.
  El contenedor `app` no publica puertos.
- **Mínimo privilegio también en contenedores:**
  - la clave de `sa` solo la reciben `sqlserver-init` y `migrate`; `app` se conecta como `farmart_app`;
  - los procesos corren como `www-data`;
  - `APP_DEBUG` es siempre `false`.
- **Imagen de producción del backend:**
  - sin dependencias de desarrollo y con OPcache sin revalidar;
  - config, rutas y vistas se cachean al arrancar con las variables del contenedor;
  - los logs van a `stderr` (`docker logs`).
  - Excepción: **Faker** está en `require`, para que la demo se pueda sembrar desde la imagen.
- **Perfil `dev`:** el contenedor de desarrollo (`backend`) solo arranca si se nombra (por ejemplo,
  `docker compose run --rm backend …`).
- **Demostración, no producción real:** `APP_ENV=demo` por defecto y `DB_TRUST_SERVER_CERTIFICATE=true`,
  porque SQL Server usa un certificado autofirmado. Para producción real: `APP_ENV=production`, certificado
  real, HTTPS delante de nginx y secretos en un gestor de secretos, no en `.env`.

### Pruebas
- `./vendor/bin/pest` corre todo. `--testsuite=Concurrencia` corre solo las pruebas con procesos PHP
  paralelos (`Concurrency::run`). Esa suite no usa `RefreshDatabase`, porque los otros procesos deben
  ver datos confirmados: recrea la base antes de cada prueba.

### Entorno
- **Laravel 13 / PHP 8.4** (Laravel 13 exige PHP ≥ 8.3).
- Conexión cifrada (ODBC Driver 18); `DB_TRUST_SERVER_CERTIFICATE=true` solo en local por el
  certificado autofirmado.
- Las pruebas usan la base `farmart_test` en SQL Server (nunca SQLite), con `RefreshDatabase`.
