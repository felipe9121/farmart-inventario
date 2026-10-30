import { useEffect, useState, type FormEvent } from 'react'
import { api, describirError } from '../api/cliente'
import type { Bodega, Coleccion, EstadoTraslado, Existencia, Traslado, Usuario } from '../api/tipos'
import { accionesTraslado, type AccionTraslado } from '../roles'

const ESTADOS: EstadoTraslado[] = ['BORRADOR', 'SOLICITADO', 'APROBADO', 'EN_TRANSITO', 'RECIBIDO', 'RECIBIDO_PARCIAL', 'ANULADO']

export function Traslados({ usuario, bodegas }: { usuario: Usuario; bodegas: Bodega[] }) {
  const [estado, setEstado] = useState<EstadoTraslado | ''>('')
  const [traslados, setTraslados] = useState<Traslado[]>([])
  const [mensaje, setMensaje] = useState<{ tipo: 'error' | 'exito'; texto: string } | null>(null)
  const [recibiendo, setRecibiendo] = useState<{ id: number; cantidades: Record<number, number> } | null>(null)
  const nombre = (id: number) => bodegas.find((b) => b.id === id)?.nombre ?? `#${id}`
  const opera = usuario.rol === 'auxiliar_farmacia' || usuario.rol === 'regente_farmacia'

  // Cambiar la versión fuerza una recarga del listado tras cada acción.
  const [version, setVersion] = useState(0)
  const recargar = () => setVersion((v) => v + 1)

  useEffect(() => {
    api<Coleccion<Traslado>>(`/traslados${estado ? `?estado=${estado}` : ''}`)
      .then((respuesta) => setTraslados(respuesta.data))
      .catch((e) => setMensaje({ tipo: 'error', texto: describirError(e) }))
  }, [estado, version])

  async function ejecutar(traslado: Traslado, accion: AccionTraslado) {
    let cuerpo: unknown
    if (accion === 'anular') {
      const motivo = window.prompt('Motivo de la anulación')
      if (!motivo) return
      cuerpo = { motivo }
    }
    if (accion === 'recibir') {
      // Se propone recibir lo despachado; el usuario ajusta si llegó menos.
      setRecibiendo({ id: traslado.id, cantidades: Object.fromEntries(traslado.items.map((i) => [i.id, i.cantidad_despachada ?? 0])) })
      return
    }
    await enviar(traslado, accion, cuerpo)
  }

  async function enviar(traslado: Traslado, accion: AccionTraslado, cuerpo?: unknown) {
    try {
      await api(`/traslados/${traslado.id}/${accion}`, { metodo: 'POST', cuerpo })
      setMensaje({ tipo: 'exito', texto: `Traslado ${traslado.codigo}: ${accion} realizado.` })
      setRecibiendo(null)
      recargar()
    } catch (e) {
      setMensaje({ tipo: 'error', texto: describirError(e) })
    }
  }

  return (
    <section>
      <h2>Traslados</h2>
      {opera && <NuevoTraslado bodegas={bodegas} onCreado={(codigo) => { setMensaje({ tipo: 'exito', texto: `Traslado ${codigo} creado en BORRADOR.` }); recargar() }} />}

      <label>
        Estado
        <select value={estado} onChange={(e) => setEstado(e.target.value as EstadoTraslado | '')}>
          <option value="">Todos</option>
          {ESTADOS.map((e) => <option key={e}>{e}</option>)}
        </select>
      </label>
      {mensaje && <p className={mensaje.tipo} role={mensaje.tipo === 'error' ? 'alert' : 'status'}>{mensaje.texto}</p>}

      <table>
        <thead><tr><th>Código</th><th>Origen → destino</th><th>Estado</th><th>Lotes</th><th>Acciones</th></tr></thead>
        <tbody>
          {traslados.map((t) => (
            <tr key={t.id}>
              <td>{t.codigo}</td>
              <td>{nombre(t.bodega_origen_id)} → {nombre(t.bodega_destino_id)}</td>
              <td><span className="etiqueta">{t.estado}</span></td>
              <td>
                {t.items.map((i) => (
                  <div key={i.id}>
                    {i.lote.numero_lote}: {i.cantidad_solicitada}
                    {i.cantidad_despachada !== null && ` / desp. ${i.cantidad_despachada}`}
                    {i.cantidad_recibida !== null && ` / rec. ${i.cantidad_recibida}`}
                    {(i.cantidad_discrepancia ?? 0) > 0 && <span className="etiqueta alerta">faltan {i.cantidad_discrepancia}</span>}
                    {recibiendo?.id === t.id && (
                      <input
                        type="number" min={0} max={i.cantidad_despachada ?? 0} aria-label={`Recibido del lote ${i.lote.numero_lote}`}
                        value={recibiendo.cantidades[i.id]}
                        onChange={(e) => setRecibiendo({ id: t.id, cantidades: { ...recibiendo.cantidades, [i.id]: Number(e.target.value) } })}
                      />
                    )}
                  </div>
                ))}
              </td>
              <td>
                {recibiendo?.id === t.id ? (
                  <button onClick={() => enviar(t, 'recibir', {
                    items: Object.entries(recibiendo.cantidades).map(([id, cantidad]) => ({ traslado_item_id: Number(id), cantidad_recibida: cantidad })),
                  })}>Confirmar recepción</button>
                ) : (
                  accionesTraslado(t.estado, usuario.rol, t.solicitado_por === usuario.id).map((accion) => (
                    <button key={accion} onClick={() => ejecutar(t, accion)}>{accion}</button>
                  ))
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}

function NuevoTraslado({ bodegas, onCreado }: { bodegas: Bodega[]; onCreado: (codigo: string) => void }) {
  const [origen, setOrigen] = useState<number | ''>('')
  const [destino, setDestino] = useState<number | ''>('')
  const [existencias, setExistencias] = useState<Existencia[]>([])
  const [loteId, setLoteId] = useState<number | ''>('')
  const [cantidad, setCantidad] = useState(1)
  const [error, setError] = useState<string | null>(null)

  async function elegirOrigen(id: number) {
    setOrigen(id)
    setLoteId('')
    // RN-01: los lotes vencidos no se ofrecen (el backend también los rechaza).
    const respuesta = await api<Coleccion<Existencia>>(`/bodegas/${id}/existencias`)
    setExistencias(respuesta.data.filter((e) => !e.lote.vencido))
  }

  async function crear(evento: FormEvent) {
    evento.preventDefault()
    setError(null)
    try {
      const creado = await api<{ data: Traslado }>('/traslados', {
        metodo: 'POST',
        cuerpo: { bodega_origen_id: origen, bodega_destino_id: destino, items: [{ lote_id: loteId, cantidad }] },
      })
      onCreado(creado.data.codigo)
    } catch (e) {
      setError(describirError(e))
    }
  }

  return (
    <form className="tarjeta fila" onSubmit={crear}>
      <strong>Nuevo traslado</strong>
      <select aria-label="Origen" value={origen} onChange={(e) => void elegirOrigen(Number(e.target.value))} required>
        <option value="">Origen…</option>
        {bodegas.map((b) => <option key={b.id} value={b.id}>{b.nombre}</option>)}
      </select>
      <select aria-label="Lote" value={loteId} onChange={(e) => setLoteId(Number(e.target.value))} required>
        <option value="">Lote…</option>
        {existencias.map((e) => <option key={e.id} value={e.lote.id}>{e.producto.nombre} · {e.lote.numero_lote} ({e.cantidad})</option>)}
      </select>
      <input type="number" aria-label="Cantidad" min={1} value={cantidad} onChange={(e) => setCantidad(Number(e.target.value))} />
      <select aria-label="Destino" value={destino} onChange={(e) => setDestino(Number(e.target.value))} required>
        <option value="">Destino…</option>
        {bodegas.filter((b) => b.id !== origen).map((b) => <option key={b.id} value={b.id}>{b.nombre}</option>)}
      </select>
      <button type="submit">Crear</button>
      {error && <p className="error" role="alert">{error}</p>}
    </form>
  )
}
