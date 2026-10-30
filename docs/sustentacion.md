# Guía para la sustentación

## Demostración (≈ 10 minutos)

Preparación: `SEED_DEMO=true scripts/generar-env.sh && docker compose up -d --build` y abrir
`http://localhost:8080`. Todos los usuarios usan la clave `password`.

1. **Roles.** Entrar como `auditor@farmart.test`: no ve la pantalla de dispensación, y en los traslados
   no tiene botones de acción. Salir.
2. **Dispensación FEFO y parciales (RN-02, RN-04).** Entrar como `auxiliar1@farmart.test` y buscar un
   paciente. El documento sale de la prescripción `RX-000002` en la BD (son datos sintéticos). Dispensar
   una cantidad parcial: la respuesta muestra el lote `…-P` (el próximo a vencer) y nunca el `…-V`
   (vencido). Volver a buscar al paciente: lo pendiente bajó.
3. **Control especial (RN-05).** El paciente de `RX-000001` tiene Clonazepam (control especial), así que
   la pantalla pide la co-firma.
   - Con el correo y la clave del propio auxiliar: falla (`AUTORIZADOR_ES_DISPENSADOR`).
   - Con `regente1` y una clave errónea 5 veces: queda bloqueado (429). El bloqueo es por pareja de
     regente y dispensador.
   - Con `regente2@farmart.test`: pasa, y la respuesta indica que hubo autorización.
4. **Idempotencia (RN-09).** Repetir con `curl` la misma petición con la misma `Idempotency-Key`: la
   segunda responde 200 con `Idempotent-Replayed: true` y el mismo id, y el stock no vuelve a bajar.
5. **Traslado (RN-07, RN-08).** Como auxiliar: crear el traslado y solicitarlo. Como `regente1`:
   aprobarlo. Como auxiliar: despacharlo y recibir menos de lo despachado. El traslado queda en
   `RECIBIDO_PARCIAL`, con su discrepancia. Si un regente solicita el traslado, ese mismo regente no ve
   "aprobar".
6. **Alertas y asistente (RN-11).** Pantalla de alertas: lotes vencidos con stock y productos bajo el
   mínimo en la Sede Sur.

   El asistente está **oculto en la pantalla** (`MOSTRAR_ASISTENTE = false` en
   `frontend/src/pantallas/Alertas.tsx`), pero el backend sigue activo. Hay dos formas de mostrarlo:
   - **En la pantalla:** antes de la sustentación, poner `MOSTRAR_ASISTENTE = true`, ejecutar
     `docker compose up -d --build --force-recreate migrate app web` y recargar con `Ctrl + F5`.
     Preguntar "¿Qué productos están bajo el mínimo en la Farmacia Sede Sur?" y luego algo fuera de su
     alcance.
   - **Sin cambiar nada,** con `curl` desde la terminal de WSL:
     ```bash
     TOKEN=$(curl -s -X POST localhost:8080/api/auth/login -H 'Content-Type: application/json' \
       -d '{"email":"regente1@farmart.test","password":"password"}' \
       | python3 -c 'import sys, json; print(json.load(sys.stdin)["token"])')
     curl -s -X POST localhost:8080/api/asistente/preguntas -H "Authorization: Bearer $TOKEN" \
       -H 'Accept: application/json' -H 'Content-Type: application/json' \
       -d '{"pregunta":"¿Qué productos están bajo el mínimo en la Farmacia Sede Sur?"}' \
       | python3 -c 'import sys, json; print(json.load(sys.stdin)["data"]["respuesta"])'
     ```
7. **Kardex inmutable (RN-06).** En `sqlcmd`, como `farmart_app`: el `UPDATE movimientos_kardex …` falla
   por permisos. Como `sa`: falla por el trigger. Mostrar `KardexTest`.
8. **CI.** Mostrar el badge en verde y un run de GitHub Actions: Pest contra SQL Server, incluida la
   suite de concurrencia.

## Preguntas probables

**¿Por qué las restricciones también en la BD si ya están en el código?**
Porque el código puede tener errores, y la BD es la última barrera. Ejemplo real de este proyecto: la
prueba de concurrencia sin `lockForUpdate` dejó pasar 18 unidades de un lote de 10, y el `CHECK >= 0` no
lo detectó, porque fue una actualización perdida y no un valor negativo. Por eso hay dos capas: el
bloqueo evita la carrera y el `CHECK` evita el negativo.

**¿Cómo evitan los deadlocks?**
Todos los casos de uso bloquean en el mismo orden: el documento, luego sus ítems por id y luego las
existencias por `producto_id` e id. En traslados se leen los ids sin bloquear, se ordenan y después se
bloquean. Si SQL Server igual elige una víctima (error 1205), `Transaccion::conReintento` repite la
transacción.

**¿Por qué un UNIQUE filtrado para la idempotencia?**
En SQL Server un UNIQUE normal admite un solo NULL. El filtro `WHERE idempotency_key IS NOT NULL` permite
dispensaciones sin llave (procesos internos) y garantiza la unicidad cuando hay llave. Si dos peticiones
con la misma llave compiten, una gana y la otra captura la violación del UNIQUE y devuelve el resultado
de la ganadora.

**¿Qué pasa si alguien intenta modificar el kardex directamente en la BD?**
Con el usuario de la app falla por `DENY UPDATE, DELETE`. Con `sa`, el trigger `INSTEAD OF` lanza
`THROW`, que además revierte toda la transacción. Las correcciones se hacen con un ajuste que lleva
motivo.

**¿Cómo cumplen RN-10?**
- **Acceso por rol:** el admin no ve pacientes y el auditor los ve enmascarados.
- **Búsqueda acotada:** solo por documento exacto.
- **Bitácora inmutable:** registra cada acceso, sin el término buscado.
- **Respuestas sin datos del paciente:** las respuestas de dispensación no incluyen sus datos.
- **Logs:** los errores de SQL Server se registran sin sus valores (antes, un documento duplicado
  aparecía en el log).

**¿Por qué el asistente no inventa datos?**
El modelo solo interpreta la pregunta (intención, bodega, días). Las cifras salen de la BD y la
respuesta se arma con plantillas. El modelo no recibe datos de inventario ni de pacientes, y el mock se
puede reemplazar por un modelo real detrás del contrato `ModeloLenguaje`.

**¿Por qué la co-firma en la misma petición y no un flujo de aprobación?**
Un flujo en dos pasos obligaría a reservar stock mientras se espera la aprobación. La co-firma funciona
como un "supervisor override" en el puesto de dispensación: se verifica fuera de la transacción (bcrypt
es lento), tiene límite de intentos y la clave nunca entra en la huella de idempotencia ni en los logs.

## Errores que encontraron las propias pruebas

Todos quedaron corregidos con una prueba que los cubre. Los que ya estaban en un commit tienen su
propio commit `fix:` en el historial (idempotencia, tipos de `pdo_sqlsrv` y logs); los otros dos se
detectaron antes de hacer el commit:

| Hallazgo | Causa | Corrección |
|---|---|---|
| Un CHECK aceptaba un hash de idempotencia sin llave | `LEN(NULL) > 0` es UNKNOWN y un CHECK solo rechaza FALSE | `IS NOT NULL` explícito |
| Lotes que vencían hoy aparecían como vencidos desde las 19:00 | "Hoy" en UTC (Colombia es UTC-5) | `FechaNegocio` en `America/Bogota`, comparando fechas |
| Comparaciones de IDs fallaban | `pdo_sqlsrv` devuelve `bigint` como string | Opción numérica de PDO + casts en las FK |
| Documento de paciente en el log | El mensaje de SQL Server repite el valor duplicado | `ErrorBaseDatos` registra sin valores |
| Las Policies de traslados recibían el id como texto | Sin `Traslado` tipado en el controlador no hay binding implícito | Parámetro tipado |
