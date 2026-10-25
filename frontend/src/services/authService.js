import { api } from './api'

export default {
  async login(correo, contrasena) {
    const { data } = await api.post('/auth/login', { correo, contrasena })
    return data
  },

  async perfil() {
    const { data } = await api.get('/auth/perfil')
    return data
  },

  async logout() {
    await api.post('/auth/logout')
  },

  async cambiarContrasena(datos) {
    const { data } = await api.put('/auth/contrasena', datos)
    return data
  },

  async solicitarRecuperacion(correo) {
    const { data } = await api.post('/auth/recuperar-contrasena', { correo })
    return data
  },

  async restablecerContrasena(datos) {
    const { data } = await api.post('/auth/restablecer-contrasena', datos)
    return data
  },
}
