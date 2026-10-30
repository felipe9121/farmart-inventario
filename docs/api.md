# Referencia de la API

Base: `/api`. JSON en la petición y en la respuesta (`Accept: application/json`). Todas las rutas,
salvo el login, exigen `Authorization: Bearer <token>`.

## Convenciones

| Situación | Código | Cuerpo |
|---|---|---|
| Éxito | 200 / 201 / 204 | `{"data": …}` (listados paginados: `data`, `links`, `meta`) |
| Sin token o token inválido/expirado | 401 | `{"message": "Unauthenticated."}` |
| Rol sin permiso o usuario inactivo | 403 | `{"message": …}`; `codigo: USUARIO_INACTIVO` si aplica |
| Validación de forma | 422 | `{"message": …, "errors": {"campo": ["…"]}}` |
| Regla de negocio | 422 (403/404/409/429 en casos concretos) | `{"message": …, "codigo": "…", "detalle": {…}}` |

- **`detalle`** lleva solo IDs y cantidades, nunca datos personales (RN-10).
- **Caché:** todas las respuestas van con `Cache-Control: no-store`.

## Autenticación

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/login` | `{email, password, dispositivo?}` → `{token, token_type, expires_at, usuario}`. Máximo 5 intentos por minuto por correo + IP. El mismo 401 (`CREDENCIALES_INVALIDAS`) para clave errónea, usuario inexistente o inactivo. |
| GET | `/auth/me` | Usuario actual (`id`, `nombre`, `email`, `rol`). |
| POST | `/auth/logout` | Revoca el token actual (204). |

Los tokens expiran a los 480 minutos (`SANCTUM_EXPIRATION`).

## Endpoints

Roles: **AUX** auxiliar_farmacia · **REG** regente_farmacia · **MED** medico · **AUD** auditor · **ADM** admin.

| Método | Ruta | Roles | Notas |
|---|---|---|---|
| GET | `/bodegas` | todos | Bodegas activas |
| GET | `/bodegas/{id}/existencias` | AUX REG AUD ADM | Existencias con stock; marca lotes vencidos |
| GET | `/pacientes?tipo_documento=&numero_documento=` | AUX REG MED AUD | Solo por documento exacto. Registra `BUSQUEDA` en la bitácora |
| GET | `/pacientes/{id}` | AUX REG MED AUD | AUD recibe los datos enmascarados. Registra `CONSULTA_DETALLE` |
| GET | `/pacientes/{id}/prescripciones?vigentes=1` | AUX REG MED AUD | Con lo pendiente por ítem. Registra `CONSULTA_PRESCRIPCIONES` |
| POST | `/dispensaciones` | AUX REG | Exige `Idempotency-Key`. Ver más abajo |
| GET | `/dispensaciones/{id}` | AUX REG AUD ADM | |
| GET | `/traslados?estado=&bodega_id=` | AUX REG AUD ADM | Paginado |
| POST | `/traslados` | AUX REG | `{bodega_origen_id, bodega_destino_id, items: [{lote_id, cantidad}], observaciones?}` → BORRADOR |
| GET | `/traslados/{id}` | AUX REG AUD ADM | Con discrepancia por ítem |
| POST | `/traslados/{id}/solicitar` | AUX REG | BORRADOR → SOLICITADO |
| POST | `/traslados/{id}/aprobar` | REG | SOLICITADO → APROBADO; nunca quien solicitó (RN-08) |
| POST | `/traslados/{id}/despachar` | AUX REG | `{items?: [{traslado_item_id, cantidad}]}`; los ítems omitidos se despachan completos |
| POST | `/traslados/{id}/recibir` | AUX REG | `{items: [{traslado_item_id, cantidad_recibida}]}` con **todos** los ítems |
| POST | `/traslados/{id}/anular` | creador o REG | `{motivo}`; solo antes del despacho |
| POST | `/ajustes` | REG | `{bodega_id, lote_id, cantidad (con signo, ≠ 0), motivo}` |
| GET | `/kardex?bodega_id=&producto_id=&lote_id=&tipo=&desde=&hasta=&por_pagina=` | AUX REG AUD ADM | Ordenado por id descendente |
| GET | `/bitacora-accesos?paciente_id=&usuario_id=&accion=&desde=&hasta=` | AUD ADM | |
| GET | `/alertas/vencimientos?bodega_id=&dias=` | AUX REG AUD ADM | Vencen en 90 días o menos; incluye los vencidos con stock |
| GET | `/alertas/stock-bajo?bodega_id=` | AUX REG AUD ADM | Stock vigente por debajo del mínimo de la bodega |
| POST | `/asistente/preguntas` | AUX REG AUD ADM | `{pregunta}`. Máximo 30 por minuto. Responde solo dos preguntas (vencimientos, stock bajo) |

## Dispensación (RN-02, RN-04, RN-05, RN-09)

```http
POST /api/dispensaciones
Authorization: Bearer <token>
Idempotency-Key: 7f9c1a52-0c1e-4a7b-9d55-2f1e3c4b5a6d

{
  "paciente_id": 1,
  "prescripcion_id": 1,
  "bodega_id": 1,
  "items": [{ "prescripcion_item_id": 1, "cantidad": 5 }],
  "autorizacion": { "email": "regente2@farmart.test", "password": "…" }
}
```

- **`autorizacion`:** solo es obligatoria si algún ítem es de control especial. Debe ser la co-firma de un
  regente activo distinto de quien dispensa.
- **`Idempotency-Key`:** de 1 a 100 caracteres ASCII imprimibles (por ejemplo, un UUID).
  - Si falta, la respuesta es **400** con `IDEMPOTENCY_KEY_REQUERIDA`.
  - Si la llave es nueva, se crea la dispensación: **201** con `Idempotent-Replayed: false`.
  - Si se repite la misma llave con el mismo contenido: **200** con `Idempotent-Replayed: true` y la
    dispensación original.
  - Si se repite la misma llave con otro contenido: **422** con `IDEMPOTENCY_KEY_REUTILIZADA`.
- **La respuesta** incluye los lotes usados por FEFO, con un ítem por lote.

## Códigos de error de negocio

| Código | HTTP | Cuándo |
|---|---|---|
| `STOCK_INSUFICIENTE` | 422 | No hay stock vigente suficiente; `detalle.disponible` |
| `EXCEDE_LO_PRESCRITO` | 422 | La cantidad supera lo pendiente (RN-04); `detalle.pendiente` |
| `PRESCRIPCION_NO_VIGENTE` / `PRESCRIPCION_DE_OTRO_PACIENTE` / `PRESCRIPCION_NO_ENCONTRADA` | 422 | RN-04 |
| `ITEM_NO_PERTENECE_A_PRESCRIPCION` / `PRODUCTO_INACTIVO` | 422 | |
| `AUTORIZACION_REQUERIDA` / `AUTORIZACION_INVALIDA` / `AUTORIZADOR_NO_REGENTE` / `AUTORIZADOR_ES_DISPENSADOR` | 422 | Co-firma de control especial (RN-05) |
| `AUTORIZACION_BLOQUEADA` | 429 | 5 co-firmas fallidas en 5 minutos (por regente y dispensador) |
| `IDEMPOTENCY_KEY_REUTILIZADA` | 422 | RN-09 |
| `LOTE_VENCIDO` / `LOTE_NO_ENCONTRADO` | 422 | RN-01 |
| `BODEGAS_IGUALES` / `BODEGA_NO_DISPONIBLE` | 422 | |
| `TRANSICION_INVALIDA` | 409 | El estado del traslado no admite la operación (RN-07); `detalle.estado_actual` |
| `APROBADOR_ES_SOLICITANTE` | 403 | RN-08 |
| `TRASLADO_NO_ENCONTRADO` | 404 | |
| `EXCEDE_LO_SOLICITADO` / `DESPACHO_VACIO` | 422 | Despacho |
| `RECEPCION_INCOMPLETA` / `EXCEDE_LO_DESPACHADO` / `ITEM_NO_PERTENECE_AL_TRASLADO` | 422 | Recepción |
| `AJUSTE_VACIO` | 422 | Ajuste con cantidad 0 |
| `CREDENCIALES_INVALIDAS` | 401 | Login |
| `USUARIO_INACTIVO` | 403 | Token de un usuario desactivado |
| `IDEMPOTENCY_KEY_REQUERIDA` | 400 | Falta el header o no es válido |
