import { useEffect, useState } from 'react'
import { api, token } from './api/cliente'
import type { Bodega, Coleccion, Recurso, Usuario } from './api/tipos'
import { Alertas } from './pantallas/Alertas'
import { Dispensacion } from './pantallas/Dispensacion'
import { Login } from './pantallas/Login'
import { Traslados } from './pantallas/Traslados'
import { pantallasPara, type Pantalla } from './roles'

const TITULOS: Record<Pantalla, string> = {
  dispensacion: 'Dispensación',
  traslados: 'Traslados',
  alertas: 'Alertas y kardex',
}

export default function App() {
  const [usuario, setUsuario] = useState<Usuario | null>(null)
  const [cargando, setCargando] = useState(token.obtener() !== null)
  const [pantalla, setPantalla] = useState<Pantalla | null>(null)
  const [bodegas, setBodegas] = useState<Bodega[]>([])

  // Restaura la sesión si hay un token guardado en la pestaña.
  useEffect(() => {
    if (!token.obtener()) return
    api<Recurso<Usuario>>('/auth/me')
      .then((r) => setUsuario(r.data))
      .catch(() => token.borrar())
      .finally(() => setCargando(false))
  }, [])

  useEffect(() => {
    if (!usuario) return
    api<Coleccion<Bodega>>('/bodegas').then((r) => setBodegas(r.data)).catch(() => setBodegas([]))
  }, [usuario])

  async function salir() {
    await api('/auth/logout', { metodo: 'POST' }).catch(() => undefined)
    token.borrar()
    setUsuario(null)
    setPantalla(null)
  }

  if (cargando) return <p>Cargando…</p>
  if (!usuario) return <Login onIngreso={setUsuario} />

  const disponibles = pantallasPara(usuario.rol)
  // Sin elección explícita se muestra la primera pantalla del rol.
  const actual = pantalla ?? disponibles[0] ?? null

  return (
    <>
      <header>
        <nav>
          {disponibles.map((p) => (
            <button key={p} className={p === actual ? 'activa' : 'secundario'} onClick={() => setPantalla(p)}>{TITULOS[p]}</button>
          ))}
        </nav>
        <span>{usuario.nombre} · {usuario.rol}</span>
        <button className="secundario" onClick={salir}>Salir</button>
      </header>
      <main>
        {disponibles.length === 0 && <p>Su rol no tiene pantallas en esta versión.</p>}
        {actual === 'dispensacion' && <Dispensacion bodegas={bodegas} />}
        {actual === 'traslados' && <Traslados usuario={usuario} bodegas={bodegas} />}
        {actual === 'alertas' && <Alertas bodegas={bodegas} />}
      </main>
    </>
  )
}
