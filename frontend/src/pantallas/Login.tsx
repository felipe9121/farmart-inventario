import { useState, type FormEvent } from 'react'
import { api, describirError, token } from '../api/cliente'
import type { Usuario } from '../api/tipos'

type Props = { onIngreso: (usuario: Usuario) => void }

export function Login({ onIngreso }: Props) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function ingresar(evento: FormEvent) {
    evento.preventDefault()
    setEnviando(true)
    setError(null)
    try {
      const sesion = await api<{ token: string; usuario: Usuario }>('/auth/login', {
        metodo: 'POST',
        cuerpo: { email, password, dispositivo: 'web' },
      })
      token.guardar(sesion.token)
      onIngreso(sesion.usuario)
    } catch (e) {
      setError(describirError(e))
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form className="tarjeta login" onSubmit={ingresar}>
      <h1>FARTMAR · Inventarios</h1>
      <label>
        Correo
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="username" />
      </label>
      <label>
        Contraseña
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password" />
      </label>
      {error && <p className="error" role="alert">{error}</p>}
      <button type="submit" disabled={enviando}>{enviando ? 'Ingresando…' : 'Ingresar'}</button>
    </form>
  )
}
