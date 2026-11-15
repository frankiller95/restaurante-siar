import { api } from './api'

/** Módulo 6: facturación (RF28 a RF32). */
export default {
  /** Facturas emitidas. filtros: estado, buscar, desde, hasta (Y-m-d), page, por_pagina */
  async listar(filtros = {}) {
    const { data } = await api.get('/facturas', { params: filtros })
    return data
  },

  /** Pedidos entregados que todavía no tienen factura (arreglo de pedidos). */
  async porCobrar() {
    const { data } = await api.get('/facturas/por-cobrar')
    return data
  },

  async obtener(idFactura) {
    const { data } = await api.get(`/facturas/${idFactura}`)
    return data
  },

  /**
   * RF29 y RF30: cuenta calculada sin guardar.
   * datos: { id_pedido, descuento_tipo, descuento_valor }
   */
  async previsualizar(datos) {
    const { data } = await api.post('/facturas/previsualizar', datos)
    return data.data
  },

  /**
   * RF28. datos: { id_pedido, cliente_nombre, cliente_documento,
   * descuento_tipo, descuento_valor, motivo_descuento }
   */
  async generar(datos) {
    const { data } = await api.post('/facturas', datos)
    return data
  },

  /** RF30. datos: { descuento_tipo: 'ninguno' | 'porcentaje' | 'valor', descuento_valor, motivo_descuento } */
  async aplicarDescuento(idFactura, datos) {
    const { data } = await api.patch(`/facturas/${idFactura}/descuento`, datos)
    return data
  },

  /** RF31. datos: { metodo, monto, monto_recibido (efectivo), referencia (tarjeta / transferencia) } */
  async registrarPago(idFactura, datos) {
    const { data } = await api.post(`/facturas/${idFactura}/pagos`, datos)
    return data
  },

  async anular(idFactura, motivo) {
    const { data } = await api.patch(`/facturas/${idFactura}/anular`, { motivo })
    return data
  },

  /** RF32. Registra la emisión y devuelve { titulo, emisor, factura, cambio_total }. */
  async comprobante(idFactura) {
    const { data } = await api.post(`/facturas/${idFactura}/comprobante`)
    return data
  },
}
