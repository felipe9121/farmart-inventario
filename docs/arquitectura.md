# Arquitectura

## Componentes

```mermaid
flowchart LR
    U[Navegador] -->|HTTP :8080| W[web<br/>nginx + SPA React]
    W -->|/api, /up<br/>FastCGI| A[app<br/>PHP-FPM · Laravel 13]
    A -->|farmart_app<br/>sin DDL| DB[(SQL Server 2022<br/>farmart)]
    M[migrate<br/>una sola ejecución] -->|sa<br/>DDL, triggers, permisos| DB
    I[sqlserver-init<br/>una sola ejecución] -->|sa<br/>bases y login| DB
```

- **`web`** es el único puerto publicado: sirve la SPA y pasa `/api` y `/up` por FastCGI a `app`. Al
  haber un solo origen, no hace falta CORS.
- **`app`** se conecta como `farmart_app`, un usuario que solo lee y escribe datos. La clave de `sa` solo
  la reciben `sqlserver-init` y `migrate`.
- **El arranque se ordena con `depends_on`:** `sqlserver` (healthy) → `sqlserver-init` → `migrate`
  (completado con éxito) → `app` → `web`.

## Capas del backend

```mermaid
flowchart TD
    R[routes/api.php<br/>auth:sanctum, activo, idempotencia, throttle] --> C[Controladores delgados]
    C --> FR[Form Requests<br/>forma de los datos + Policy]
    C --> AC[Actions<br/>casos de uso con transacción]
    AC --> S[Services<br/>InventarioService · AlertasInventario · BitacoraAccesos · Asistente]
    S --> MD[Modelos Eloquent]
    AC --> MD
    C --> RS[API Resources<br/>sin datos del paciente; enmascarado para auditor]
    MD --> DB[(SQL Server<br/>CHECK · FK · UNIQUE filtrados · triggers · DENY)]
```

| Capa | Responsabilidad | Ejemplos |
|---|---|---|
| Form Request | Forma de los datos y autorización por rol (Policy) | `StoreDispensacionRequest`, `RecibirTrasladoRequest` |
| Action | Un caso de uso: transacción con reintento, orden de bloqueo y reglas de negocio | `RegistrarDispensacion`, `DespacharTraslado`, `RegistrarAjuste` |
| Service | Lógica reutilizable | `InventarioService` (único punto que cambia stock), `AlertasInventario` |
| Resource | Forma de la respuesta | `DispensacionResource`, `PacienteResource` (enmascara) |
| BD | Última barrera: lo que no depende de la fecha ni de otras tablas | `ck_existencias_cantidad`, triggers del kardex |

Los errores de negocio se lanzan como `ReglaNegocioException`, que se convierte en una respuesta 422,
403, 404, 409 o 429 con un `codigo` estable y no se registra en el log.

## Modelo de datos

```mermaid
erDiagram
    users ||--o{ prescripciones : "medico_id"
    pacientes ||--o{ prescripciones : tiene
    prescripciones ||--|{ prescripcion_items : contiene
    productos ||--o{ prescripcion_items : ""
    productos ||--o{ lotes : ""
    bodegas ||--o{ existencias : ""
    lotes ||--o{ existencias : "FK (lote_id, producto_id)"
    bodegas ||--o{ stock_minimos : ""
    productos ||--o{ stock_minimos : ""
    prescripciones ||--o{ dispensaciones : ""
    bodegas ||--o{ dispensaciones : ""
    users ||--o{ dispensaciones : "dispensado_por / autorizado_por"
    dispensaciones ||--|{ dispensacion_items : "un ítem por lote (FEFO)"
    prescripcion_items ||--o{ dispensacion_items : ""
    lotes ||--o{ dispensacion_items : ""
    bodegas ||--o{ traslados : "origen / destino"
    users ||--o{ traslados : "creado / solicitado / aprobado / despachado / recibido / anulado"
    traslados ||--|{ traslado_items : "un ítem por lote"
    lotes ||--o{ traslado_items : ""
    lotes ||--o{ movimientos_kardex : "FK (lote_id, producto_id)"
    bodegas ||--o{ movimientos_kardex : ""
    dispensacion_items ||--o| movimientos_kardex : "UNIQUE filtrado"
    traslado_items ||--o{ movimientos_kardex : "una salida y una entrada"
    users ||--o{ movimientos_kardex : usuario
    users ||--o{ bitacora_accesos_paciente : usuario
    pacientes ||--o{ bitacora_accesos_paciente : "nulo en búsqueda sin resultado"

    existencias {
        int cantidad "CHECK >= 0"
    }
    prescripcion_items {
        int cantidad_prescrita "CHECK > 0"
        int cantidad_dispensada "CHECK 0..prescrita"
    }
    dispensaciones {
        string idempotency_key "UNIQUE filtrado con dispensado_por"
        string idempotency_hash
    }
    traslados {
        string estado "CHECK 7 estados + coherencia"
    }
    traslado_items {
        int cantidad_discrepancia "calculada PERSISTED"
    }
    movimientos_kardex {
        string tipo "CHECK + signo"
        int saldo_resultante "CHECK >= 0"
        string motivo "obligatorio en ajustes"
    }
```

**Tablas inmutables:** `movimientos_kardex` y `bitacora_accesos_paciente`.
- Tienen triggers `INSTEAD OF UPDATE/DELETE` que lanzan `THROW`.
- Además, `farmart_app` tiene `DENY UPDATE, DELETE` sobre ellas.

**Sin borrado en cascada:** SQL Server no admite varias rutas de cascada, y los documentos con historia
nunca se borran (`DENY DELETE`).

## Concurrencia

```mermaid
sequenceDiagram
    participant API as RegistrarDispensacion
    participant DB as SQL Server
    API->>API: huella del payload, ¿llave ya usada?
    API->>API: verificar co-firma (bcrypt, fuera de la transacción)
    API->>DB: BEGIN TRANSACTION
    API->>DB: 1. prescripción  WITH (UPDLOCK, HOLDLOCK)
    API->>DB: 2. ítems de la prescripción, por id
    API->>DB: 3. existencias por producto_id e id (FEFO en memoria)
    API->>DB: INSERT dispensación (UNIQUE filtrado de idempotencia)
    API->>DB: UPDATE existencias + INSERT kardex + acumulado + bitácora
    API->>DB: COMMIT
    Note over API,DB: Si SQL Server elige la transacción como víctima de un deadlock (1205),<br/>Transaccion::conReintento la repite (3 intentos).
```

- **Orden global de bloqueo:** primero el documento (la prescripción o el traslado), luego sus ítems por
  id y por último las existencias por `producto_id` e id. Dispensaciones y traslados siguen el mismo
  orden, así que no se bloquean en orden cruzado.
- **`lockForUpdate` en SQL Server** genera `WITH (ROWLOCK, UPDLOCK, HOLDLOCK)`. El `HOLDLOCK` bloquea
  también el rango de índice, por lo que dos recepciones simultáneas no pueden crear la misma existencia.
- **Verificación:** la suite `tests/Concurrencia` lo prueba con procesos PHP paralelos. Sin el bloqueo,
  6 dispensaciones simultáneas sacan 18 unidades de un lote de 10 (una actualización perdida que el
  `CHECK` no detecta); con el bloqueo, solo pasan 3.
