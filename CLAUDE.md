# Proyecto: Sistema de dispensación e inventarios (prueba técnica FARTMAR IPS)

## Stack
- Backend: PHP 8.2+, Laravel (versión estable vigente), API REST, Sanctum, Pest
- BD: SQL Server 2022 (driver pdo_sqlsrv). Restricciones CHECK, FK y UNIQUE filtrados.
- Frontend: React + TypeScript + Vite, Vitest
- Calidad: Pint, Larastan, ESLint
- Docker + Docker Compose, GitHub Actions

## Arquitectura
- Controladores delgados. La lógica de negocio vive en Services/Actions.
- Validación con Form Requests, respuestas con API Resources.
- Autorización con Policies/Gates por rol.
- Restricciones en la BD (CHECK, FK, UNIQUE), no solo en código.

## Roles
auxiliar_farmacia, regente_farmacia, medico, auditor (solo lectura, datos enmascarados), admin.

## Reglas de negocio (obligatorias)
- RN-01: existencia = bodega + producto + lote. Lote vencido nunca se dispensa ni traslada.
- RN-02: dispensación FEFO, puede tomar de varios lotes.
- RN-03: stock nunca negativo: lockForUpdate dentro de transacción + CHECK (cantidad >= 0). Bloquear filas siempre en el mismo orden para evitar deadlocks y reintentar la transacción.
- RN-04: dispensación ligada a paciente y prescripción vigente; no exceder lo prescrito; parciales acumuladas.
- RN-05: control especial requiere autorización de un segundo usuario regente_farmacia.
- RN-06: todo cambio de stock genera movimiento de kardex INMUTABLE (triggers INSTEAD OF UPDATE/DELETE que lancen THROW). Correcciones = ajuste con motivo.
- RN-07: traslado: BORRADOR, SOLICITADO, APROBADO, EN_TRANSITO, RECIBIDO, RECIBIDO_PARCIAL, ANULADO. Sale del origen al despachar, entra al destino al recibir. Lo no recibido = discrepancia pendiente.
- RN-08: quien solicita un traslado no puede aprobarlo.
- RN-09: crear dispensación es idempotente (header Idempotency-Key, UNIQUE por usuario + llave).
- RN-10: datos de paciente sensibles: acceso por rol, enmascarado para auditor, bitácora de consultas, NINGÚN dato personal en logs.
- RN-11: alertas de lotes que vencen en <= 90 días y stock bajo mínimo por bodega.

## Reglas de trabajo
- Commits pequeños y frecuentes (Conventional Commits). El historial se evalúa.
- Todo cambio de stock: transaccional + prueba.
- Solo datos sintéticos. Sin secretos en el repo.
- Explica brevemente cada decisión importante: debo poder defenderla en la sustentación.
- Registra supuestos y trade-offs en README.md.
- No usar SQLite en pruebas; usar una base de pruebas en SQL Server.
- Entorno: Windows + WSL2 + Docker. Composer y artisan corren dentro de contenedores.

## Orden de trabajo
1. Modelo de datos y migraciones  2. Dispensación  3. Traslados y kardex
4. Seguridad y auditoría  5. Asistente IA (con mock)  6. Frontend (4 pantallas)
7. Docker  8. CI/CD  9. Documentación
