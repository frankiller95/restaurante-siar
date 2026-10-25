import { api } from './api'

export default {
  async listar() {
    const { data } = await api.get('/roles')
    return data
  },

  async permisos() {
    const { data } = await api.get('/permisos')
    return data
  },

  async crear(rol) {
    const { data } = await api.post('/roles', rol)
    return data
  },

  async actualizar(idRol, rol) {
    const { data } = await api.put(`/roles/${idRol}`, rol)
    return data
  },

  async eliminar(idRol) {
    const { data } = await api.delete(`/roles/${idRol}`)
    return data
  },

  async asignarPermisos(idRol, idsPermiso) {
    const { data } = await api.put(`/roles/${idRol}/permisos`, { permisos: idsPermiso })
    return data
  },
}
