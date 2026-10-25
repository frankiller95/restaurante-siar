import { api } from './api'

export default {
  /** @param {{buscar?: string, id_rol?: number, estado?: string, page?: number, por_pagina?: number}} filtros */
  async listar(filtros = {}) {
    const { data } = await api.get('/usuarios', { params: filtros })
    return data
  },

  async crear(usuario) {
    const { data } = await api.post('/usuarios', usuario)
    return data
  },

  async actualizar(idUsuario, usuario) {
    const { data } = await api.put(`/usuarios/${idUsuario}`, usuario)
    return data
  },

  async eliminar(idUsuario) {
    const { data } = await api.delete(`/usuarios/${idUsuario}`)
    return data
  },

  async reenviarCredenciales(idUsuario) {
    const { data } = await api.post(`/usuarios/${idUsuario}/reenviar-credenciales`)
    return data
  },
}
