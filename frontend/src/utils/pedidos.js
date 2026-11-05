/** Colores e íconos de los estados del pedido. */
export const ESTADOS_PEDIDO = {
  pendiente: { etiqueta: 'Pendiente', color: 'grey-7', icono: 'edit_note' },
  en_preparacion: { etiqueta: 'En preparación', color: 'warning', icono: 'soup_kitchen' },
  listo: { etiqueta: 'Listo', color: 'positive', icono: 'notifications_active' },
  entregado: { etiqueta: 'Entregado', color: 'info', icono: 'room_service' },
  facturado: { etiqueta: 'Facturado', color: 'secondary', icono: 'receipt_long' },
  cancelado: { etiqueta: 'Cancelado', color: 'negative', icono: 'cancel' },
}

/** Texto del estado, distinguiendo "pendiente" antes y después de enviarlo a cocina. */
export function textoEstado(pedido) {
  if (pedido.estado === 'pendiente') return pedido.enviado_cocina ? 'En cola de cocina' : 'Tomando pedido'
  return ESTADOS_PEDIDO[pedido.estado]?.etiqueta ?? pedido.estado
}

export function colorEstado(pedido) {
  if (pedido.estado === 'pendiente' && pedido.enviado_cocina) return 'primary'
  return ESTADOS_PEDIDO[pedido.estado]?.color ?? 'grey'
}

/** "hace 12 min" desde una fecha ISO. */
export function haceCuanto(iso) {
  if (!iso) return ''
  const minutos = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000))
  if (minutos < 1) return 'hace un momento'
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.floor(minutos / 60)
  return `hace ${horas} h ${minutos % 60} min`
}
