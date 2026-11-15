/** Colores, textos y estilos del módulo de facturación. */

export const ESTADOS_FACTURA = {
  pendiente: { etiqueta: 'Pendiente de pago', color: 'warning', icono: 'hourglass_top' },
  pagada: { etiqueta: 'Pagada', color: 'positive', icono: 'check_circle' },
  anulada: { etiqueta: 'Anulada', color: 'grey-7', icono: 'block' },
}

export const METODOS_PAGO = {
  efectivo: { etiqueta: 'Efectivo', icono: 'payments', requiereReferencia: false },
  tarjeta: { etiqueta: 'Tarjeta', icono: 'credit_card', requiereReferencia: true },
  transferencia: { etiqueta: 'Transferencia', icono: 'account_balance', requiereReferencia: true },
}

export const TIPOS_DESCUENTO = [
  { label: 'Sin descuento', value: 'ninguno' },
  { label: 'Porcentaje (%)', value: 'porcentaje' },
  { label: 'Valor fijo ($)', value: 'valor' },
]

/** Texto del lugar del pedido: "Mesa 4" o "Para recoger". */
export function lugarPedido(pedido) {
  if (!pedido) return ''
  if (pedido.tipo_pedido === 'recoger' || !pedido.mesa) return 'Para recoger'
  return `Mesa ${pedido.mesa.numero}`
}

/**
 * Estilos del comprobante. Se usan en pantalla y en la ventana de impresión
 * (que no carga Quasar), por eso son CSS simple con clases "tk-".
 */
export const ESTILOS_TICKET = `
  .tk { font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #111; width: 100%; max-width: 320px; margin: 0 auto; padding: 8px 4px; }
  .tk-centro { text-align: center; }
  .tk-titulo { font-size: 15px; font-weight: bold; }
  .tk-aparte { color: #444; }
  .tk-linea { border-top: 1px dashed #555; margin: 8px 0; }
  .tk-fila { display: flex; justify-content: space-between; gap: 8px; }
  .tk-fila > span:last-child { white-space: nowrap; text-align: right; }
  .tk-item { margin-bottom: 4px; }
  .tk-total { font-size: 14px; font-weight: bold; }
  .tk-negrita { font-weight: bold; }
  .tk-sello { display: inline-block; border: 2px solid #111; padding: 2px 10px; font-weight: bold; letter-spacing: 1px; margin: 6px 0; }
`
