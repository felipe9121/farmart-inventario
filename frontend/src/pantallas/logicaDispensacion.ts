import type { Prescripcion } from '../api/tipos'

export type Cantidades = Record<number, number>

/** Ítems con cantidad > 0, en el formato de la API. */
export function itemsAEnviar(cantidades: Cantidades) {
  return Object.entries(cantidades)
    .filter(([, cantidad]) => cantidad > 0)
    .map(([id, cantidad]) => ({ prescripcion_item_id: Number(id), cantidad }))
}

/** RN-05: hace falta la co-firma de un regente si se dispensa algún producto de control especial. */
export function requiereCofirma(prescripcion: Prescripcion | undefined, cantidades: Cantidades): boolean {
  return (prescripcion?.items ?? []).some((item) => item.es_control_especial && (cantidades[item.id] ?? 0) > 0)
}

/**
 * RN-09: la llave de idempotencia se conserva mientras el contenido no cambie, así un
 * reintento (doble clic, red caída) devuelve la misma dispensación en vez de duplicarla.
 * Cambia cuando cambia el contenido o después de un éxito.
 */
export function llaveIdempotencia(anterior: { llave: string; huella: string } | null, huella: string, nueva: () => string) {
  return anterior && anterior.huella === huella ? anterior : { llave: nueva(), huella }
}
