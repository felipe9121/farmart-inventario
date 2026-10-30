// Cliente HTTP mínimo para la API. El token vive en sessionStorage: se pierde al cerrar
// la pestaña (trade-off de simplicidad frente a cookies httpOnly; ver README).
const CLAVE_TOKEN = 'farmart.token'

export class ApiError extends Error {
  readonly status: number
  readonly codigo?: string
  readonly detalle?: Record<string, unknown>
  readonly errores?: Record<string, string[]>

  constructor(status: number, cuerpo: { message?: string; codigo?: string; detalle?: Record<string, unknown>; errors?: Record<string, string[]> }) {
    super(cuerpo.message ?? `Error ${status}`)
    this.status = status
    this.codigo = cuerpo.codigo
    this.detalle = cuerpo.detalle
    this.errores = cuerpo.errors
  }
}

export const token = {
  obtener: (): string | null => sessionStorage.getItem(CLAVE_TOKEN),
  guardar: (valor: string) => sessionStorage.setItem(CLAVE_TOKEN, valor),
  borrar: () => sessionStorage.removeItem(CLAVE_TOKEN),
}

type Opciones = {
  metodo?: 'GET' | 'POST'
  cuerpo?: unknown
  idempotencyKey?: string
}

export async function api<T>(ruta: string, { metodo = 'GET', cuerpo, idempotencyKey }: Opciones = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const actual = token.obtener()
  if (actual) headers.Authorization = `Bearer ${actual}`
  if (cuerpo !== undefined) headers['Content-Type'] = 'application/json'
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey

  const respuesta = await fetch(`/api${ruta}`, {
    method: metodo,
    headers,
    body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
  })

  if (respuesta.status === 204) return undefined as T

  const datos = await respuesta.json().catch(() => ({}))
  if (!respuesta.ok) throw new ApiError(respuesta.status, datos)

  return datos as T
}

/** Mensaje legible de un error, con el código de negocio si lo hay. */
export function describirError(error: unknown): string {
  if (error instanceof ApiError) {
    const primero = error.errores ? Object.values(error.errores)[0]?.[0] : undefined
    return [primero ?? error.message, error.codigo && `(${error.codigo})`].filter(Boolean).join(' ')
  }

  return 'No se pudo conectar con el servidor.'
}
