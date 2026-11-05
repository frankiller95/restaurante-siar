import { api } from './api'

export default {
  // --- Insumos (RF23, RF24, RF27) ---
  async listar(filtros = {}) {
    const { data } = await api.get('/insumos', { params: filtros })
    return data
  },

  async obtener(idInsumo) {
    const { data } = await api.get(`/insumos/${idInsumo}`)
    return data
  },

  /** RF40: insumos en su stock mínimo o por debajo. */
  async alertas() {
    const { data } = await api.get('/insumos/alertas')
    return data
  },

  async crear(insumo) {
    const { data } = await api.post('/insumos', insumo)
    return data
  },

  async actualizar(idInsumo, insumo) {
    const { data } = await api.put(`/insumos/${idInsumo}`, insumo)
    return data
  },

  async eliminar(idInsumo) {
    const { data } = await api.delete(`/insumos/${idInsumo}`)
    return data
  },

  // --- Movimientos (RF25, RF26) ---
  /** modo: 'entrada' | 'merma' | 'ajuste' */
  async registrarMovimiento(idInsumo, modo, datos) {
    const rutas = { entrada: 'entradas', merma: 'mermas', ajuste: 'ajustes' }
    const { data } = await api.post(`/insumos/${idInsumo}/${rutas[modo]}`, datos)
    return data
  },

  /** RF27: kárdex. filtros: tipo, desde, hasta, page, por_pagina */
  async kardex(idInsumo, filtros = {}) {
    const { data } = await api.get(`/insumos/${idInsumo}/movimientos`, { params: filtros })
    return data
  },

  // --- Recetas (RF38) ---
  async receta(idProducto) {
    const { data } = await api.get(`/productos/${idProducto}/receta`)
    return data
  },

  /** insumos: [{ id_insumo, cantidad_por_porcion }] */
  async guardarReceta(idProducto, insumos) {
    const { data } = await api.put(`/productos/${idProducto}/receta`, { insumos })
    return data
  },
}
