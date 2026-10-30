import { describe, expect, it } from 'vitest'
import { accionesTraslado, pantallasPara } from './roles'

describe('pantallasPara', () => {
  it('muestra la dispensación solo a quienes dispensan', () => {
    expect(pantallasPara('auxiliar_farmacia')).toEqual(['dispensacion', 'traslados', 'alertas'])
    expect(pantallasPara('auditor')).toEqual(['traslados', 'alertas'])
    expect(pantallasPara('medico')).toEqual([])
  })
})

describe('accionesTraslado', () => {
  it('ofrece las acciones de cada estado (RN-07)', () => {
    expect(accionesTraslado('BORRADOR', 'auxiliar_farmacia', false)).toEqual(['solicitar', 'anular'])
    expect(accionesTraslado('APROBADO', 'auxiliar_farmacia', false)).toEqual(['despachar', 'anular'])
    expect(accionesTraslado('EN_TRANSITO', 'regente_farmacia', false)).toEqual(['recibir'])
    expect(accionesTraslado('RECIBIDO_PARCIAL', 'regente_farmacia', false)).toEqual([])
  })

  it('solo un regente distinto del solicitante ve "aprobar" (RN-08)', () => {
    expect(accionesTraslado('SOLICITADO', 'regente_farmacia', false)).toEqual(['aprobar', 'anular'])
    expect(accionesTraslado('SOLICITADO', 'regente_farmacia', true)).toEqual(['anular'])
    expect(accionesTraslado('SOLICITADO', 'auxiliar_farmacia', false)).toEqual(['anular'])
  })

  it('auditor y admin solo consultan', () => {
    expect(accionesTraslado('BORRADOR', 'auditor', false)).toEqual([])
    expect(accionesTraslado('SOLICITADO', 'admin', false)).toEqual([])
  })
})
