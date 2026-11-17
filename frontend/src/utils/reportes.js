import { exportFile } from 'quasar'
import { hoy } from '@/utils/mesas'

/**
 * Colores de los gráficos. Paleta categórica validada (modo claro, superficie
 * blanca): azul, naranja, aqua, en ese orden y sin repetir. Una sola medida
 * siempre va en el azul; el naranja es la segunda serie de una comparación.
 * El aqua queda por debajo de 3:1 de contraste, por eso todo gráfico tiene su tabla.
 */
export const COLORES = {
  serie1: '#2a78d6',
  serie2: '#eb6834',
  serie3: '#1baf7a',
  tinta: '#0b0b0b',
  tintaSecundaria: '#52514e',
  tintaSuave: '#898781',
  rejilla: '#e1e0d9',
  eje: '#c3c2b7',
  superficie: '#fcfcfb',
  // Estados (siempre con ícono y texto, nunca solo color).
  bueno: '#006300',
  malo: '#d03b3b',
}

/** Rampa secuencial de un solo tono (azul), de claro a oscuro: pasos 100 a 700. */
export const RAMPA_AZUL = [
  '#cde2fb', '#b7d3f6', '#9ec5f4', '#86b6ef', '#6da7ec', '#5598e7', '#3987e5',
  '#2a78d6', '#256abf', '#1c5cab', '#184f95', '#104281', '#0d366b',
]

/** Color de una celda según su valor respecto al máximo (0 = sin color). */
export function colorRampa(valor, maximo) {
  if (!valor || !maximo) return null
  const proporcion = Math.min(1, valor / maximo)
  return RAMPA_AZUL[Math.min(RAMPA_AZUL.length - 1, Math.floor(proporcion * RAMPA_AZUL.length))]
}

// --- Periodos ----------------------------------------------------------------

function aTexto(fecha) {
  const mes = String(fecha.getMonth() + 1).padStart(2, '0')
  const dia = String(fecha.getDate()).padStart(2, '0')
  return `${fecha.getFullYear()}-${mes}-${dia}`
}

/** Atajos de periodo (hora local del navegador). */
export const PERIODOS = [
  { id: 'hoy', etiqueta: 'Hoy' },
  { id: '7', etiqueta: 'Últimos 7 días' },
  { id: '30', etiqueta: 'Últimos 30 días' },
  { id: 'mes', etiqueta: 'Este mes' },
  { id: 'mes_anterior', etiqueta: 'Mes anterior' },
]

export function rangoDePeriodo(id) {
  const ahora = new Date()
  const hasta = hoy()

  switch (id) {
    case 'hoy':
      return { desde: hasta, hasta }
    case '7':
      return { desde: aTexto(new Date(ahora.getFullYear(), ahora.getMonth(), ahora.getDate() - 6)), hasta }
    case 'mes':
      return { desde: aTexto(new Date(ahora.getFullYear(), ahora.getMonth(), 1)), hasta }
    case 'mes_anterior':
      return {
        desde: aTexto(new Date(ahora.getFullYear(), ahora.getMonth() - 1, 1)),
        hasta: aTexto(new Date(ahora.getFullYear(), ahora.getMonth(), 0)),
      }
    default: // '30'
      return { desde: aTexto(new Date(ahora.getFullYear(), ahora.getMonth(), ahora.getDate() - 29)), hasta }
  }
}

// --- Formatos ----------------------------------------------------------------

const compacto = new Intl.NumberFormat('es-CO', { notation: 'compact', maximumFractionDigits: 1 })
const decimal = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 })

/** 1250000 → "1,3 M" (ejes de los gráficos) */
export function formatearCompacto(valor) {
  return compacto.format(Number(valor) || 0)
}

export function formatearNumero(valor) {
  return decimal.format(Number(valor) || 0)
}

/** 43.2 → "43,2 %" */
export function formatearPorcentaje(valor) {
  return `${decimal.format(Number(valor) || 0)} %`
}

/** 135 → "2 h 15 min" */
export function formatearMinutos(minutos) {
  const total = Math.round(Number(minutos) || 0)
  if (total < 60) return `${total} min`
  const horas = Math.floor(total / 60)
  const resto = total % 60
  return resto ? `${horas} h ${resto} min` : `${horas} h`
}

// --- Exportar ----------------------------------------------------------------

/**
 * Descarga una tabla como CSV pensado para Excel en español: separador ";",
 * decimales con coma y BOM UTF-8 (para que se vean bien las tildes).
 * columnas: [{ etiqueta, valor: (fila) => ... }]
 */
export function exportarCsv(nombreArchivo, columnas, filas) {
  const celda = (valor) => {
    const texto = typeof valor === 'number' ? String(valor).replace('.', ',') : String(valor ?? '')
    return /[";\n\r]/.test(texto) ? `"${texto.replace(/"/g, '""')}"` : texto
  }

  const lineas = [
    columnas.map((c) => celda(c.etiqueta)).join(';'),
    ...filas.map((fila) => columnas.map((c) => celda(c.valor(fila))).join(';')),
  ]

  return exportFile(`${nombreArchivo}.csv`, `﻿${lineas.join('\r\n')}`, { mimeType: 'text/csv;charset=utf-8' })
}
