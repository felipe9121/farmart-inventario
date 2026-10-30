export type Rol = 'auxiliar_farmacia' | 'regente_farmacia' | 'medico' | 'auditor' | 'admin'

export type Usuario = { id: number; nombre: string; email: string; rol: Rol }

export type Bodega = { id: number; codigo: string; nombre: string }

export type Paciente = {
  id: number
  tipo_documento: string
  numero_documento: string
  nombres: string
  apellidos: string
  enmascarado: boolean
}

export type ItemPrescripcion = {
  id: number
  producto_id: number
  producto: string
  es_control_especial: boolean
  cantidad_prescrita: number
  cantidad_dispensada: number
  cantidad_pendiente: number
}

export type Prescripcion = {
  id: number
  numero: string
  vigente_hasta: string
  vigente: boolean
  items: ItemPrescripcion[]
}

export type Dispensacion = {
  id: number
  autorizado_por: number | null
  items: { id: number; prescripcion_item_id: number; cantidad: number; lote: { numero_lote: string; fecha_vencimiento: string } }[]
}

export type EstadoTraslado = 'BORRADOR' | 'SOLICITADO' | 'APROBADO' | 'EN_TRANSITO' | 'RECIBIDO' | 'RECIBIDO_PARCIAL' | 'ANULADO'

export type ItemTraslado = {
  id: number
  cantidad_solicitada: number
  cantidad_despachada: number | null
  cantidad_recibida: number | null
  cantidad_discrepancia: number | null
  lote: { id: number; numero_lote: string; fecha_vencimiento: string }
}

export type Traslado = {
  id: number
  codigo: string
  estado: EstadoTraslado
  bodega_origen_id: number
  bodega_destino_id: number
  solicitado_por: number | null
  items: ItemTraslado[]
}

export type Existencia = {
  id: number
  cantidad: number
  producto: { id: number; nombre: string; es_control_especial: boolean }
  lote: { id: number; numero_lote: string; fecha_vencimiento: string; vencido: boolean }
}

export type LotePorVencer = {
  bodega: string
  producto: string
  numero_lote: string
  fecha_vencimiento: string
  dias_para_vencer: number
  cantidad: number
}

export type StockBajo = { bodega: string; producto: string; minimo: number; disponible: number; faltante: number }

export type Movimiento = {
  id: number
  bodega_id: number
  lote_id: number
  tipo: string
  cantidad: number
  saldo_resultante: number
  motivo: string | null
  created_at: string
}

export type RespuestaAsistente = { intencion: string; respuesta: string; sugerencias: string[] }

export type Coleccion<T> = { data: T[] }
export type Recurso<T> = { data: T }
