# Frontend — React + TypeScript + Vite

Cuatro pantallas sencillas sobre la API: login, dispensación, traslados y alertas con kardex.
Documentación del proyecto: [README principal](../README.md).

| Ruta | Contenido |
|---|---|
| `src/api/` | Cliente HTTP (token, `Idempotency-Key`, errores con código) y tipos de la API |
| `src/roles.ts` | Pantallas y acciones de traslado por rol (RN-07, RN-08) |
| `src/pantallas/` | Pantallas y la lógica pura de dispensación (co-firma e idempotencia) |
| `nginx.conf` | Producción: SPA + `/api` por FastCGI a PHP-FPM |

```bash
npm run lint && npm run typecheck && npm test && npm run build
```

En desarrollo, Vite reenvía `/api` al backend (`VITE_API_URL`, por defecto `http://localhost:8000`).
