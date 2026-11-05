export const UNIDADES = [
  { value: 'kilogramo', label: 'Kilogramos (kg)', corto: 'kg' },
  { value: 'gramo', label: 'Gramos (g)', corto: 'g' },
  { value: 'litro', label: 'Litros (L)', corto: 'L' },
  { value: 'mililitro', label: 'Mililitros (ml)', corto: 'ml' },
  { value: 'unidad', label: 'Unidades (und)', corto: 'und' },
]

export const TIPOS_MOVIMIENTO = {
  entrada: { etiqueta: 'Entrada', color: 'positive', icono: 'add_circle' },
  consumo_pedido: { etiqueta: 'Consumo por pedido', color: 'primary', icono: 'restaurant' },
  reintegro_pedido: { etiqueta: 'Reintegro (pedido cancelado)', color: 'info', icono: 'undo' },
  merma: { etiqueta: 'Merma', color: 'negative', icono: 'delete_sweep' },
  ajuste: { etiqueta: 'Ajuste', color: 'warning', icono: 'tune' },
}

/** 12.5 → "12,5" (hasta 3 decimales, sin ceros de más) */
export function formatearCantidad(valor) {
  if (valor === null || valor === undefined) return ''
  return Number(valor).toLocaleString('es-CO', { maximumFractionDigits: 3 })
}
