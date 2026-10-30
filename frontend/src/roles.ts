import type { EstadoTraslado, Rol } from './api/tipos'

export type Pantalla = 'dispensacion' | 'traslados' | 'alertas'

const OPERAN_FARMACIA: Rol[] = ['auxiliar_farmacia', 'regente_farmacia']
const INVENTARIO: Rol[] = [...OPERAN_FARMACIA, 'auditor', 'admin']

/** Pantallas visibles por rol (el backend vuelve a autorizar cada petición). */
export function pantallasPara(rol: Rol): Pantalla[] {
  return [
    ...(OPERAN_FARMACIA.includes(rol) ? (['dispensacion'] as const) : []),
    ...(INVENTARIO.includes(rol) ? (['traslados', 'alertas'] as const) : []),
  ]
}

export type AccionTraslado = 'solicitar' | 'aprobar' | 'despachar' | 'recibir' | 'anular'

/**
 * Acciones que se ofrecen según estado (RN-07) y rol. RN-08: quien solicitó no ve "aprobar".
 */
export function accionesTraslado(estado: EstadoTraslado, rol: Rol, esSolicitante: boolean): AccionTraslado[] {
  if (!OPERAN_FARMACIA.includes(rol)) return []

  const porEstado: Record<EstadoTraslado, AccionTraslado[]> = {
    BORRADOR: ['solicitar', 'anular'],
    SOLICITADO: ['aprobar', 'anular'],
    APROBADO: ['despachar', 'anular'],
    EN_TRANSITO: ['recibir'],
    RECIBIDO: [],
    RECIBIDO_PARCIAL: [],
    ANULADO: [],
  }

  return porEstado[estado].filter(
    (accion) => accion !== 'aprobar' || (rol === 'regente_farmacia' && !esSolicitante),
  )
}
