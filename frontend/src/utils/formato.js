const pesos = new Intl.NumberFormat('es-CO', {
  style: 'currency',
  currency: 'COP',
  maximumFractionDigits: 0,
})

/** 32000 → "$ 32.000" */
export function formatearPrecio(valor) {
  if (valor === null || valor === undefined || valor === '') return ''
  return pesos.format(Number(valor))
}
