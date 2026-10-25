/**
 * Utilidades para mostrar los errores que devuelve la API de Laravel.
 */

/** Mensaje legible de un error de axios. */
export function mensajeError(error, porDefecto = 'Ocurrió un error inesperado. Intenta de nuevo.') {
  if (!error?.response) {
    return 'No hay conexión con el servidor. Verifica que la API esté encendida.'
  }
  if (error.response.status === 429) {
    return 'Demasiados intentos. Espera un minuto y vuelve a intentar.'
  }
  return error.response.data?.message || porDefecto
}

/**
 * Errores de validación por campo ({ correo: 'mensaje', ... }) para
 * resaltar los campos del formulario (respuestas 422 de Laravel).
 */
export function erroresPorCampo(error) {
  const errores = error?.response?.data?.errors || {}
  return Object.fromEntries(
    Object.entries(errores).map(([campo, mensajes]) => [campo, mensajes[0]]),
  )
}

/** Formatea una fecha ISO para mostrarla (ej. 25/10/2026 14:30). */
export function formatearFecha(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('es-CO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
