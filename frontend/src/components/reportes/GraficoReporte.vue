<template>
  <div v-if="vacio" class="grafico-vacio row items-center justify-center text-grey-7" :style="{ height: `${alto}px` }">
    <q-icon name="bar_chart" size="sm" class="q-mr-sm" /> Sin datos en este periodo.
  </div>

  <div v-else class="grafico" :style="{ height: `${alto}px` }" role="img" :aria-label="descripcion">
    <Line v-if="tipo === 'linea'" :data="datos" :options="opciones" />
    <Bar v-else :data="datos" :options="opciones" :plugins="[etiquetasEnPuntas]" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
} from 'chart.js'
import { COLORES, formatearCompacto, formatearNumero, formatearPorcentaje } from '@/utils/reportes'
import { formatearPrecio } from '@/utils/formato'

// Se registran solo las piezas que se usan (el resto de Chart.js no se carga).
ChartJS.register(BarElement, CategoryScale, Filler, Legend, LineElement, LinearScale, PointElement, Tooltip)

/**
 * Gráfico de barras o de línea para los reportes. Sigue las reglas de los
 * gráficos de SIAR: un solo eje, barras de máximo 24 px con el extremo
 * redondeado, líneas de 2 px, rejilla tenue, leyenda solo con 2 o más series
 * (con una sola, el título del reporte ya dice qué se grafica) y colores en
 * orden fijo. Cada gráfico se acompaña de su tabla en la pantalla.
 */
const props = defineProps({
  tipo: { type: String, default: 'barras' }, // 'barras' | 'linea'
  horizontal: { type: Boolean, default: false },
  etiquetas: { type: Array, required: true },
  /** [{ nombre, datos: number[] }]: el color sale del orden (1.º azul, 2.º naranja, 3.º aqua). */
  series: { type: Array, required: true },
  formato: { type: String, default: 'numero' }, // 'moneda' | 'numero' | 'porcentaje'
  /** Líneas extra del tooltip por posición: [['3 facturas', ...], ...] */
  detalles: { type: Array, default: () => [] },
  alto: { type: Number, default: 300 },
  descripcion: { type: String, default: 'Gráfico del reporte' },
  /** Barras horizontales: el valor se imprime en la punta de cada barra. */
  valoresEnPuntas: { type: Boolean, default: false },
})

const COLOR_SERIES = [COLORES.serie1, COLORES.serie2, COLORES.serie3]

const vacio = computed(() => props.series.every((s) => s.datos.every((v) => !v)))

/** Valor completo (tooltip). */
function completo(valor) {
  if (props.formato === 'moneda') return formatearPrecio(valor)
  if (props.formato === 'porcentaje') return formatearPorcentaje(valor)
  return formatearNumero(valor)
}

/** Valor corto (ejes y puntas de barra). */
function corto(valor) {
  if (props.formato === 'moneda') return `$ ${formatearCompacto(valor)}`
  if (props.formato === 'porcentaje') return `${formatearNumero(valor)} %`
  return formatearCompacto(valor)
}

function recortar(texto, maximo = 24) {
  const t = String(texto)
  return t.length > maximo ? `${t.slice(0, maximo - 1)}…` : t
}

const datos = computed(() => ({
  labels: props.etiquetas,
  datasets: props.series.map((serie, i) => {
    const color = COLOR_SERIES[i] ?? COLOR_SERIES[0]
    const base = { label: serie.nombre, data: serie.datos }

    if (props.tipo === 'linea') {
      return {
        ...base,
        borderColor: color,
        backgroundColor: `${color}1a`, // ~10 % de opacidad: un lavado, no un bloque
        fill: props.series.length === 1,
        borderWidth: 2,
        borderJoinStyle: 'round',
        borderCapStyle: 'round',
        tension: 0.2,
        // Con pocos puntos se marcan; con muchos, solo al pasar el cursor.
        pointRadius: props.etiquetas.length <= 16 ? 4 : 0,
        pointHoverRadius: 6,
        pointBackgroundColor: color,
        pointBorderColor: COLORES.superficie,
        pointBorderWidth: 2,
        pointHitRadius: 14,
      }
    }

    return {
      ...base,
      backgroundColor: color,
      maxBarThickness: 24,
      // Extremo de los datos redondeado (4 px); cuadrado sobre la línea base.
      borderRadius: props.horizontal
        ? { topRight: 4, bottomRight: 4, topLeft: 0, bottomLeft: 0 }
        : { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
      borderSkipped: false,
      // Separación de 2 px del color de la superficie entre barras agrupadas.
      borderColor: COLORES.superficie,
      borderWidth: props.series.length > 1 ? 2 : 0,
    }
  }),
}))

const letra = { family: 'system-ui, -apple-system, "Segoe UI", sans-serif', size: 12 }

const opciones = computed(() => {
  const ejeCategorias = {
    grid: { display: false },
    border: { color: COLORES.eje },
    ticks: {
      color: COLORES.tintaSuave,
      font: letra,
      maxRotation: 0,
      autoSkip: true,
      callback(valor) {
        return recortar(this.getLabelForValue(valor))
      },
    },
  }
  const ejeValores = {
    beginAtZero: true,
    grid: { color: COLORES.rejilla, lineWidth: 1 },
    border: { display: false },
    ticks: { color: COLORES.tintaSuave, font: letra, callback: (valor) => corto(valor) },
  }

  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: props.horizontal ? 'y' : 'x',
    interaction: { mode: 'index', intersect: false },
    layout: { padding: { top: 8, right: props.valoresEnPuntas ? 64 : 8 } },
    scales: props.horizontal ? { x: ejeValores, y: ejeCategorias } : { x: ejeCategorias, y: ejeValores },
    plugins: {
      legend: {
        display: props.series.length > 1,
        position: 'top',
        align: 'start',
        labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, color: COLORES.tintaSecundaria, font: letra },
      },
      tooltip: {
        backgroundColor: COLORES.tinta,
        titleColor: '#ffffff',
        bodyColor: '#ffffff',
        footerColor: '#c3c2b7',
        padding: 10,
        cornerRadius: 6,
        displayColors: props.series.length > 1,
        callbacks: {
          title: (items) => String(props.etiquetas[items[0].dataIndex] ?? ''),
          label: (item) => `${item.dataset.label}: ${completo(item.parsed[props.horizontal ? 'x' : 'y'])}`,
          afterBody: (items) => props.detalles[items[0].dataIndex] ?? [],
        },
      },
      etiquetasEnPuntas: { activo: props.valoresEnPuntas, formato: corto },
    },
  }
})

/** Imprime el valor en la punta de cada barra horizontal (sin librerías extra). */
const etiquetasEnPuntas = {
  id: 'etiquetasEnPuntas',
  afterDatasetsDraw(grafico, _argumentos, opciones) {
    if (!opciones?.activo) return
    const { ctx } = grafico
    ctx.save()
    ctx.fillStyle = COLORES.tintaSecundaria
    ctx.font = `600 12px ${letra.family}`
    ctx.textBaseline = 'middle'
    grafico.getDatasetMeta(0).data.forEach((barra, i) => {
      ctx.fillText(opciones.formato(grafico.data.datasets[0].data[i]), barra.x + 8, barra.y)
    })
    ctx.restore()
  },
}
</script>

<style scoped>
.grafico {
  position: relative;
  width: 100%;
}
</style>
