import { useEffect, useState, type FormEvent } from 'react'
import { api, describirError } from '../api/cliente'
import type { Bodega, Coleccion, LotePorVencer, Movimiento, RespuestaAsistente, StockBajo } from '../api/tipos'

// Asistente de IA oculto por ahora: cambiar a true para mostrarlo.
const MOSTRAR_ASISTENTE = false

export function Alertas({ bodegas }: { bodegas: Bodega[] }) {
  const [bodegaId, setBodegaId] = useState<number | ''>('')
  const [vencimientos, setVencimientos] = useState<LotePorVencer[]>([])
  const [stockBajo, setStockBajo] = useState<StockBajo[]>([])
  const [kardex, setKardex] = useState<Movimiento[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const filtro = bodegaId ? `bodega_id=${bodegaId}` : ''
    Promise.all([
      api<Coleccion<LotePorVencer>>(`/alertas/vencimientos?${filtro}`),
      api<Coleccion<StockBajo>>(`/alertas/stock-bajo?${filtro}`),
      api<Coleccion<Movimiento>>(`/kardex?por_pagina=20&${filtro}`),
    ])
      .then(([v, s, k]) => { setVencimientos(v.data); setStockBajo(s.data); setKardex(k.data); setError(null) })
      .catch((e) => setError(describirError(e)))
  }, [bodegaId])

  return (
    <section>
      <h2>Alertas y kardex</h2>
      <label>
        Bodega
        <select value={bodegaId} onChange={(e) => setBodegaId(e.target.value ? Number(e.target.value) : '')}>
          <option value="">Todas</option>
          {bodegas.map((b) => <option key={b.id} value={b.id}>{b.nombre}</option>)}
        </select>
      </label>
      {error && <p className="error" role="alert">{error}</p>}

      {MOSTRAR_ASISTENTE && <Asistente />}

      <h3>Lotes que vencen en 90 días o menos ({vencimientos.length})</h3>
      <table>
        <thead><tr><th>Bodega</th><th>Producto</th><th>Lote</th><th>Vence</th><th>Días</th><th>Cantidad</th></tr></thead>
        <tbody>
          {vencimientos.map((v) => (
            <tr key={`${v.bodega}-${v.numero_lote}`} className={v.dias_para_vencer < 0 ? 'vencido' : ''}>
              <td>{v.bodega}</td><td>{v.producto}</td><td>{v.numero_lote}</td><td>{v.fecha_vencimiento}</td><td>{v.dias_para_vencer}</td><td>{v.cantidad}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <h3>Stock bajo el mínimo ({stockBajo.length})</h3>
      <table>
        <thead><tr><th>Bodega</th><th>Producto</th><th>Mínimo</th><th>Disponible</th><th>Faltante</th></tr></thead>
        <tbody>
          {stockBajo.map((s) => (
            <tr key={`${s.bodega}-${s.producto}`}><td>{s.bodega}</td><td>{s.producto}</td><td>{s.minimo}</td><td>{s.disponible}</td><td>{s.faltante}</td></tr>
          ))}
        </tbody>
      </table>

      <h3>Kardex: últimos movimientos</h3>
      <table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Bodega</th><th>Lote</th><th>Cantidad</th><th>Saldo</th><th>Motivo</th></tr></thead>
        <tbody>
          {kardex.map((m) => (
            <tr key={m.id}>
              <td>{new Date(m.created_at).toLocaleString()}</td><td>{m.tipo}</td><td>#{m.bodega_id}</td><td>#{m.lote_id}</td>
              <td>{m.cantidad}</td><td>{m.saldo_resultante}</td><td>{m.motivo ?? ''}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}

function Asistente() {
  const [pregunta, setPregunta] = useState('')
  const [respuesta, setRespuesta] = useState<RespuestaAsistente | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function preguntar(texto: string) {
    setError(null)
    try {
      const r = await api<{ data: RespuestaAsistente }>('/asistente/preguntas', { metodo: 'POST', cuerpo: { pregunta: texto } })
      setRespuesta(r.data)
    } catch (e) {
      setError(describirError(e))
    }
  }

  function enviar(evento: FormEvent) {
    evento.preventDefault()
    void preguntar(pregunta)
  }

  return (
    <form className="tarjeta" onSubmit={enviar}>
      <strong>Asistente de inventario - IA</strong>
      <div className="fila">
        <input aria-label="Pregunta" style={{ flex: 1 }} placeholder="¿Qué lotes vencen en los próximos 30 días en la Bodega Central?" value={pregunta} onChange={(e) => setPregunta(e.target.value)} required minLength={3} />
        <button type="submit">Preguntar</button>
      </div>
      {error && <p className="error" role="alert">{error}</p>}
      {respuesta && (
        <div role="status">
          <p>{respuesta.respuesta}</p>
          {respuesta.sugerencias.map((s) => (
            <button type="button" key={s} className="secundario" onClick={() => { setPregunta(s); void preguntar(s) }}>{s}</button>
          ))}
        </div>
      )}
    </form>
  )
}
