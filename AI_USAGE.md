# Uso de inteligencia artificial

## Herramienta

- **Claude Code** (CLI de Anthropic), con el modelo **Claude Opus 5.5**, ejecutado en la terminal de WSL2
  sobre este repositorio.
- Los commits en los que participó la IA llevan la línea
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. El commit inicial
  (`chore: estructura inicial del repositorio`) y el `CLAUDE.md` los escribió el autor.

## Cómo se trabajó

1. **El autor definió el marco** en `CLAUDE.md`: stack, arquitectura, roles, reglas de negocio RN-01 a
   RN-11, reglas de trabajo (commits pequeños, todo cambio de stock transaccional y con prueba, sin
   SQLite, solo datos sintéticos) y el orden de las 9 etapas.
2. **Se avanzó etapa por etapa.** El autor pedía cada etapa de forma explícita, y la IA no pasaba a la
   siguiente sin esa instrucción.
3. **La IA propuso un plan en cada etapa, lo implementó en commits pequeños y explicó sus decisiones**
   para que el autor pudiera defenderlas. Los supuestos y trade-offs quedaron en el `README.md`.
4. **El autor decidió** el alcance y cuándo publicar en GitHub. Pidió explícitamente cada push, salvo
   los dos de la etapa 8: al pedir "todos los pasos" del CI/CD, la IA hizo push para ejecutar el
   workflow en GitHub y luego otro push con su documentación.

## Decisiones tomadas por el autor

| Tema | Decisión |
|---|---|
| Roles | Rol como columna con `CHECK`, sin tabla de roles (propuesta de la IA, aprobada por el autor) |
| Asistente IA (etapa 5) | Primero se consideró omitirlo; finalmente se incluyó con **mock**, limitado a **dos preguntas** |
| Frontend (etapa 6) | Cuatro pantallas **sencillas**, porque el front pesa poco en la prueba |
| CI/CD (etapa 8) | Completo: verificaciones y publicación de imágenes en GHCR |
| Publicación | Push a GitHub al cierre de cada etapa, a pedido del autor (ver la excepción de la etapa 8) |

Las demás decisiones técnicas las propuso la IA y están justificadas en el `README.md` y en `docs/`.
Por ejemplo: el orden de bloqueo, el UNIQUE filtrado de idempotencia, la co-firma en la misma petición,
el usuario de BD sin DDL y el admin sin acceso a datos de pacientes.

## Cómo se verificó lo que generó la IA

- **Pruebas contra SQL Server real,** nunca SQLite, antes de cada commit de código: Pest en el backend (pruebas de
  restricciones de BD, de reglas de negocio, de la API y de **concurrencia real** con procesos
  paralelos) y Vitest en el frontend.
- **Calidad estática:** Pint, Larastan (nivel 6), ESLint y el chequeo de tipos de TypeScript.
- **Pruebas que demuestran que detectan el error:** se quitó a propósito el `lockForUpdate` y se
  comprobó que la prueba de concurrencia fallaba (actualización perdida); luego se restauró.
- **Pruebas de humo de punta a punta** contra el stack levantado con Docker y en el CI.
- **Errores detectados durante el trabajo** (todos corregidos y con prueba): un `CHECK` que aceptaba
  NULL, el cálculo de "hoy" en UTC en lugar de la hora de Colombia, enteros de `pdo_sqlsrv` como string,
  datos de pacientes en los logs de errores de SQL y Policies que recibían el id sin binding. El detalle
  está en `docs/sustentacion.md`.
- **Errores de la propia IA corregidos en el camino:** por ejemplo, afirmaciones incorrectas en borradores
  de la documentación (sobre las restricciones `CHECK` de SQL Server) y comandos de verificación que
  no ejecutaban lo que decían. Se detectaron al revisar y se corrigieron antes de publicar.

## Datos y privacidad

- **Datos sintéticos:** todos los datos son sintéticos (seeders con documentos que empiezan por 9 y
  correos `@farmart.test`). El proyecto no contiene datos reales de pacientes.
- **Sin secretos en el repositorio:** las claves locales están en `.env` (ignorado por git), el CI genera
  claves aleatorias en cada ejecución y antes de cada push se revisó el historial en busca de secretos.

## Responsabilidad

El autor es responsable del código entregado: debe revisarlo y poder explicarlo y defenderlo en la
sustentación. Para eso están `docs/sustentacion.md`, `docs/reglas-de-negocio.md` y las justificaciones
del `README.md`. Que una prueba pase no reemplaza esa revisión.
