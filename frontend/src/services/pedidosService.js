import { api } from './api'

export default {
  /** RF22. filtros: estado ('abiertos' o un estado), id_mesa, fecha (Y-m-d), mios (1), page, por_pagina */
  async listar(filtros = {}) {
    const { data } = await api.get('/pedidos', { params: filtros })
    return data
  },

  async obtener(idPedido) {
    const { data } = await api.get(`/pedidos/${idPedido}`)
    return data
  },

  /** RF22: cola de cocina (enviados, en preparación y listos). */
  async cocina() {
    const { data } = await api.get('/pedidos/cocina')
    return data
  },

  /** RF17. productos: [{ id_producto, cantidad, observacion }] (opcional) */
  async crear(idMesa, productos = []) {
    const { data } = await api.post('/pedidos', { id_mesa: idMesa, productos })
    return data
  },

  async actualizarObservaciones(idPedido, observaciones) {
    const { data } = await api.put(`/pedidos/${idPedido}`, { observaciones })
    return data
  },

  /** RF18 */
  async agregarProducto(idPedido, idProducto, cantidad = 1, observacion = null) {
    const { data } = await api.post(`/pedidos/${idPedido}/productos`, { id_producto: idProducto, cantidad, observacion })
    return data
  },

  /** RF19 */
  async modificarProducto(idPedido, idDetalle, cantidad, observacion = null) {
    const { data } = await api.put(`/pedidos/${idPedido}/productos/${idDetalle}`, { cantidad, observacion })
    return data
  },

  async quitarProducto(idPedido, idDetalle) {
    const { data } = await api.delete(`/pedidos/${idPedido}/productos/${idDetalle}`)
    return data
  },

  /** RF21 */
  async enviarACocina(idPedido) {
    const { data } = await api.patch(`/pedidos/${idPedido}/enviar-cocina`)
    return data
  },

  async preparar(idPedido) {
    const { data } = await api.patch(`/pedidos/${idPedido}/preparar`)
    return data
  },

  async marcarListo(idPedido) {
    const { data } = await api.patch(`/pedidos/${idPedido}/listo`)
    return data
  },

  async entregar(idPedido) {
    const { data } = await api.patch(`/pedidos/${idPedido}/entregar`)
    return data
  },

  /** RF20. En preparación el motivo es obligatorio. */
  async cancelar(idPedido, motivo = null) {
    const { data } = await api.patch(`/pedidos/${idPedido}/cancelar`, { motivo })
    return data
  },
}
