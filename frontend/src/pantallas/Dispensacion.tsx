import { useRef, useState, type FormEvent } from 'react'
import { api, describirError } from '../api/cliente'
import type { Bodega, Coleccion, Dispensacion as DispensacionApi, Paciente, Prescripcion, Recurso } from '../api/tipos'
import { itemsAEnviar, llaveIdempotencia, requiereCofirma, type Cantidades } from './logicaDispensacion'

const TIPOS_DOCUMENTO = ['CC', 'TI', 'RC', 'CE', 'PA', 'PPT']

export function Dispensacion({ bodegas }: { bodegas: Bodega[] }) {
  const [bodegaId, setBodegaId] = useState<number | ''>('')
  const [tipo, setTipo] = useState('CC')
  const [numero, setNumero] = useState('')
  const [paciente, setPaciente] = useState<Paciente | null>(null)
  const [prescripciones, setPrescripciones] = useState<Prescripcion[]>([])
  const [prescripcionId, setPrescripcionId] = useState<number | null>(null)
  const [cantidades, setCantidades] = useState<Cantidades>({})
  const [cofirma, setCofirma] = useState({ email: '', password: '' })
  const [resultado, setResultado] = useState<DispensacionApi | null>(null)
  const [error, setError] = useState<string | null>(null)
  const llave = useRef<{ llave: string; huella: string } | null>(null)

  // Sin elección explícita se usa la primera bodega.
  const bodega: number | '' = bodegaId === '' ? (bodegas.at(0)?.id ?? '') : bodegaId
  const prescripcion = prescripciones.find((p) => p.id === prescripcionId)
  const pideCofirma = requiereCofirma(prescripcion, cantidades)

  async function buscar(evento: FormEvent) {
    evento.preventDefault()
    setError(null)
    setResultado(null)
    setPaciente(null)
    setPrescripciones([])
    try {
      const encontrados = await api<Coleccion<Paciente>>(`/pacientes?tipo_documento=${tipo}&numero_documento=${encodeURIComponent(numero)}`)
      const encontrado = encontrados.data[0]
      if (!encontrado) {
        setError('No se encontró un paciente con ese documento.')
        return
      }
      setPaciente(encontrado)
      const vigentes = await api<Coleccion<Prescripcion>>(`/pacientes/${encontrado.id}/prescripciones?vigentes=1`)
      setPrescripciones(vigentes.data)
      setPrescripcionId(vigentes.data[0]?.id ?? null)
      setCantidades({})
    } catch (e) {
      setError(describirError(e))
    }
  }

  async function dispensar(evento: FormEvent) {
    evento.preventDefault()
    if (!paciente || !prescripcion || bodega === '') return

    const cuerpo = {
      paciente_id: paciente.id,
      prescripcion_id: prescripcion.id,
      bodega_id: bodega,
      items: itemsAEnviar(cantidades),
      ...(pideCofirma ? { autorizacion: cofirma } : {}),
    }
    // La huella excluye la clave del regente: reintentar con otra clave no es otro contenido.
    llave.current = llaveIdempotencia(llave.current, JSON.stringify({ ...cuerpo, autorizacion: cofirma.email }), () => crypto.randomUUID())

    setError(null)
    try {
      const respuesta = await api<Recurso<DispensacionApi>>('/dispensaciones', {
        metodo: 'POST',
        cuerpo,
        idempotencyKey: llave.current.llave,
      })
      setResultado(respuesta.data)
      llave.current = null
      setCofirma({ email: '', password: '' })
      const vigentes = await api<Coleccion<Prescripcion>>(`/pacientes/${paciente.id}/prescripciones?vigentes=1`)
      setPrescripciones(vigentes.data)
      setCantidades({})
    } catch (e) {
      setError(describirError(e))
    }
  }

  return (
    <section>
      <h2>Dispensación</h2>
      <form className="fila" onSubmit={buscar}>
        <label>
          Bodega
          <select value={bodega} onChange={(e) => setBodegaId(Number(e.target.value))}>
            {bodegas.map((b) => <option key={b.id} value={b.id}>{b.nombre}</option>)}
          </select>
        </label>
        <label>
          Documento
          <select value={tipo} onChange={(e) => setTipo(e.target.value)}>
            {TIPOS_DOCUMENTO.map((t) => <option key={t}>{t}</option>)}
          </select>
        </label>
        <input aria-label="Número de documento" value={numero} onChange={(e) => setNumero(e.target.value)} required />
        <button type="submit">Buscar</button>
      </form>

      {error && <p className="error" role="alert">{error}</p>}

      {paciente && (
        <div className="tarjeta">
          <strong>{paciente.nombres} {paciente.apellidos}</strong> · {paciente.tipo_documento} {paciente.numero_documento}
          {prescripciones.length === 0 ? (
            <p>El paciente no tiene prescripciones vigentes.</p>
          ) : (
            <form onSubmit={dispensar}>
              <label>
                Prescripción
                <select value={prescripcionId ?? ''} onChange={(e) => { setPrescripcionId(Number(e.target.value)); setCantidades({}) }}>
                  {prescripciones.map((p) => <option key={p.id} value={p.id}>{p.numero} (vigente hasta {p.vigente_hasta})</option>)}
                </select>
              </label>
              <table>
                <thead><tr><th>Producto</th><th>Prescrito</th><th>Dispensado</th><th>Pendiente</th><th>Dispensar</th></tr></thead>
                <tbody>
                  {prescripcion?.items.map((item) => (
                    <tr key={item.id}>
                      <td>{item.producto} {item.es_control_especial && <span className="etiqueta">Control especial</span>}</td>
                      <td>{item.cantidad_prescrita}</td>
                      <td>{item.cantidad_dispensada}</td>
                      <td>{item.cantidad_pendiente}</td>
                      <td>
                        <input
                          type="number" min={0} max={item.cantidad_pendiente} aria-label={`Cantidad de ${item.producto}`}
                          value={cantidades[item.id] ?? 0}
                          onChange={(e) => setCantidades({ ...cantidades, [item.id]: Number(e.target.value) })}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {pideCofirma && (
                <fieldset>
                  <legend>Autorización de un segundo regente (control especial)</legend>
                  <input type="email" placeholder="Correo del regente" value={cofirma.email} onChange={(e) => setCofirma({ ...cofirma, email: e.target.value })} required />
                  <input type="password" placeholder="Contraseña" value={cofirma.password} onChange={(e) => setCofirma({ ...cofirma, password: e.target.value })} required autoComplete="off" />
                </fieldset>
              )}
              <button type="submit" disabled={itemsAEnviar(cantidades).length === 0}>Dispensar</button>
            </form>
          )}
        </div>
      )}

      {resultado && (
        <div className="tarjeta exito" role="status">
          Dispensación #{resultado.id} registrada{resultado.autorizado_por ? ' con autorización de regente' : ''}. Lotes (FEFO):
          <ul>{resultado.items.map((i) => <li key={i.id}>{i.lote.numero_lote} (vence {i.lote.fecha_vencimiento}): {i.cantidad}</li>)}</ul>
        </div>
      )}
    </section>
  )
}
