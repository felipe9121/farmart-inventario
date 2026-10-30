import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError, describirError, token } from './cliente'

function respuesta(status: number, cuerpo: unknown) {
  return vi.fn().mockResolvedValue(new Response(JSON.stringify(cuerpo), { status, headers: { 'Content-Type': 'application/json' } }))
}

afterEach(() => vi.unstubAllGlobals())

describe('api', () => {
  it('envía el token y la llave de idempotencia', async () => {
    const fetch = respuesta(201, { data: { id: 1 } })
    vi.stubGlobal('fetch', fetch)
    token.guardar('abc')

    await api('/dispensaciones', { metodo: 'POST', cuerpo: { x: 1 }, idempotencyKey: 'k-1' })

    const [url, opciones] = fetch.mock.calls[0]
    expect(url).toBe('/api/dispensaciones')
    expect(opciones.headers).toMatchObject({ Authorization: 'Bearer abc', 'Idempotency-Key': 'k-1', 'Content-Type': 'application/json' })
    expect(opciones.body).toBe('{"x":1}')
  })

  it('convierte los errores de negocio en ApiError con su código', async () => {
    vi.stubGlobal('fetch', respuesta(422, { message: 'No hay stock vigente suficiente.', codigo: 'STOCK_INSUFICIENTE', detalle: { disponible: 2 } }))

    const error = await api('/dispensaciones').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 422, codigo: 'STOCK_INSUFICIENTE', detalle: { disponible: 2 } })
    expect(describirError(error)).toBe('No hay stock vigente suficiente. (STOCK_INSUFICIENTE)')
  })

  it('muestra el primer error de validación', async () => {
    vi.stubGlobal('fetch', respuesta(422, { message: 'Datos inválidos', errors: { motivo: ['El motivo es obligatorio.'] } }))

    expect(describirError(await api('/ajustes').catch((e: unknown) => e))).toBe('El motivo es obligatorio.')
  })
})
