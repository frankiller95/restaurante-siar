import { api } from './api'

/** Acciones de estado de una mesa → ruta de la API. */
const RUTAS_ACCION = {
  ocupar: 'ocupar',
  liberar: 'liberar',
  enviarAMantenimiento: 'mantenimiento',
  habilitar: 'habilitar',
}

export default {
  // --- Mesas (RF12, RF13, RF14, RF16) ---

  /** Devuelve { data: [...mesas], resumen: { libre, ocupada, reservada, mantenimiento } } */
  async mesas(filtros = {}) {
    const { data } = await api.get('/mesas', { params: filtros })
    return data
  },

  async crearMesa(mesa) {
    const { data } = await api.post('/mesas', mesa)
    return data
  },

  async actualizarMesa(idMesa, mesa) {
    const { data } = await api.put(`/mesas/${idMesa}`, mesa)
    return data
  },

  async eliminarMesa(idMesa) {
    const { data } = await api.delete(`/mesas/${idMesa}`)
    return data
  },

  /** accion: ocupar | liberar | enviarAMantenimiento | habilitar */
  async cambiarEstado(idMesa, accion) {
    const { data } = await api.patch(`/mesas/${idMesa}/${RUTAS_ACCION[accion]}`)
    return data
  },

  /** RF15: mesas sin cruce de reservas para esa fecha, hora y personas. */
  async disponibles(fecha, hora, personas) {
    const { data } = await api.get('/mesas/disponibles', { params: { fecha, hora, personas } })
    return data
  },

  // --- Reservas (RF15) ---
  async reservas(filtros = {}) {
    const { data } = await api.get('/reservas', { params: filtros })
    return data
  },

  async crearReserva(reserva) {
    const { data } = await api.post('/reservas', reserva)
    return data
  },

  async cancelarReserva(idReserva) {
    const { data } = await api.patch(`/reservas/${idReserva}/cancelar`)
    return data
  },

  async registrarLlegada(idReserva) {
    const { data } = await api.patch(`/reservas/${idReserva}/llegada`)
    return data
  },
}
