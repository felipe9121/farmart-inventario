import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { token } from '../api/cliente'
import { Login } from './Login'

afterEach(() => vi.unstubAllGlobals())

async function ingresar(clave: string) {
  await userEvent.type(screen.getByLabelText('Correo'), 'regente1@farmart.test')
  await userEvent.type(screen.getByLabelText('Contraseña'), clave)
  await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }))
}

describe('Login', () => {
  it('guarda el token y entrega el usuario', async () => {
    const usuario = { id: 2, nombre: 'Regente Uno', email: 'regente1@farmart.test', rol: 'regente_farmacia' }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ token: 't-1', usuario }), { status: 200 })))
    const onIngreso = vi.fn()
    render(<Login onIngreso={onIngreso} />)

    await ingresar('password')

    expect(onIngreso).toHaveBeenCalledWith(usuario)
    expect(token.obtener()).toBe('t-1')
  })

  it('muestra el error de credenciales', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'Credenciales inválidas.', codigo: 'CREDENCIALES_INVALIDAS' }), { status: 401 })))
    render(<Login onIngreso={vi.fn()} />)

    await ingresar('mala')

    expect(await screen.findByRole('alert')).toHaveTextContent('Credenciales inválidas. (CREDENCIALES_INVALIDAS)')
    expect(token.obtener()).toBeNull()
  })
})
