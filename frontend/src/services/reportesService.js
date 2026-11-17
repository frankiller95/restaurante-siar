import { api } from './api'

/**
 * Módulo 7: reportes (RF33 a RF37). Todos reciben { desde, hasta } (Y-m-d);
 * sin fechas, el servidor usa los últimos 30 días.
 */
export default {
  /** RF33. agrupar: 'dia' | 'semana' | 'mes' */
  async ventas({ desde, hasta, agrupar = 'dia' }) {
    const { data } = await api.get('/reportes/ventas', { params: { desde, hasta, agrupar } })
    return data
  },

  /** RF34. ordenarPor: 'unidades' | 'ingresos'; limite: 1 a 50 */
  async productosMasVendidos({ desde, hasta, ordenarPor = 'unidades', limite = 10 }) {
    const { data } = await api.get('/reportes/productos-mas-vendidos', {
      params: { desde, hasta, ordenar_por: ordenarPor, limite },
    })
    return data
  },

  /** RF35 */
  async consumoInventario({ desde, hasta }) {
    const { data } = await api.get('/reportes/consumo-inventario', { params: { desde, hasta } })
    return data
  },

  /** RF36 */
  async ocupacionMesas({ desde, hasta }) {
    const { data } = await api.get('/reportes/ocupacion-mesas', { params: { desde, hasta } })
    return data
  },

  /** RF37 */
  async indicadoresFinancieros({ desde, hasta }) {
    const { data } = await api.get('/reportes/indicadores-financieros', { params: { desde, hasta } })
    return data
  },
}
