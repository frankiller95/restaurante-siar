/** Colores, íconos y textos de los estados de mesa y reserva. */

export const ESTADOS_MESA = {
  libre: { etiqueta: 'Libre', color: 'positive', fondo: 'green-1', icono: 'check_circle' },
  ocupada: { etiqueta: 'Ocupada', color: 'negative', fondo: 'red-1', icono: 'groups' },
  reservada: { etiqueta: 'Reservada', color: 'warning', fondo: 'orange-1', icono: 'event' },
  mantenimiento: { etiqueta: 'En mantenimiento', color: 'grey-7', fondo: 'grey-3', icono: 'build' },
}

export const ACCIONES_MESA = {
  ocupar: { etiqueta: 'Ocupar', icono: 'login', color: 'negative' },
  liberar: { etiqueta: 'Liberar', icono: 'logout', color: 'positive' },
  enviarAMantenimiento: { etiqueta: 'Mantenimiento', icono: 'build', color: 'grey-8' },
  habilitar: { etiqueta: 'Habilitar', icono: 'check', color: 'positive' },
}

export const ESTADOS_RESERVA = {
  pendiente: { etiqueta: 'Pendiente', color: 'warning' },
  cumplida: { etiqueta: 'Cumplida', color: 'positive' },
  cancelada: { etiqueta: 'Cancelada', color: 'grey-7' },
  no_asistio: { etiqueta: 'No asistió', color: 'negative' },
}

/** Fecha local de hoy en formato YYYY-MM-DD. */
export function hoy() {
  const fecha = new Date()
  const mes = String(fecha.getMonth() + 1).padStart(2, '0')
  const dia = String(fecha.getDate()).padStart(2, '0')
  return `${fecha.getFullYear()}-${mes}-${dia}`
}

/** "2026-11-10" → "mar 10 nov" */
export function formatearDia(fecha) {
  if (!fecha) return ''
  const [anio, mes, dia] = fecha.split('-').map(Number)
  return new Date(anio, mes - 1, dia).toLocaleDateString('es-CO', { weekday: 'short', day: 'numeric', month: 'short' })
}

/** "19:30" → "7:30 p. m." */
export function formatearHora(hora) {
  if (!hora) return ''
  const [h, m] = hora.split(':').map(Number)
  return new Date(2000, 0, 1, h, m).toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' })
}
