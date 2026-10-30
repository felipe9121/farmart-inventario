import { describe, expect, it } from 'vitest'
import type { Prescripcion } from '../api/tipos'
import { itemsAEnviar, llaveIdempotencia, requiereCofirma } from './logicaDispensacion'

const prescripcion: Prescripcion = {
  id: 1,
  numero: 'RX-1',
  vigente_hasta: '2030-01-01',
  vigente: true,
  items: [
    { id: 10, producto_id: 1, producto: 'Acetaminofén', es_control_especial: false, cantidad_prescrita: 10, cantidad_dispensada: 0, cantidad_pendiente: 10 },
    { id: 11, producto_id: 2, producto: 'Clonazepam', es_control_especial: true, cantidad_prescrita: 5, cantidad_dispensada: 0, cantidad_pendiente: 5 },
  ],
}

describe('itemsAEnviar', () => {
  it('envía solo los ítems con cantidad', () => {
    expect(itemsAEnviar({ 10: 3, 11: 0 })).toEqual([{ prescripcion_item_id: 10, cantidad: 3 }])
  })
})

describe('requiereCofirma (RN-05)', () => {
  it('pide co-firma solo si se dispensa un producto de control especial', () => {
    expect(requiereCofirma(prescripcion, { 10: 3 })).toBe(false)
    expect(requiereCofirma(prescripcion, { 10: 3, 11: 0 })).toBe(false)
    expect(requiereCofirma(prescripcion, { 11: 1 })).toBe(true)
    expect(requiereCofirma(undefined, { 11: 1 })).toBe(false)
  })
})

describe('llaveIdempotencia (RN-09)', () => {
  let n = 0
  const nueva = () => `llave-${++n}`

  it('reutiliza la llave al reintentar el mismo contenido', () => {
    const primera = llaveIdempotencia(null, 'A', nueva)
    expect(llaveIdempotencia(primera, 'A', nueva)).toBe(primera)
  })

  it('genera otra llave si el contenido cambia', () => {
    const primera = llaveIdempotencia(null, 'A', nueva)
    expect(llaveIdempotencia(primera, 'B', nueva).llave).not.toBe(primera.llave)
  })
})
