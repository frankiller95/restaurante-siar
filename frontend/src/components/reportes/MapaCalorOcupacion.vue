<template>
  <div>
    <div class="mapa-contenedor">
      <table class="mapa" aria-label="Ocupación promedio por día de la semana y hora">
        <thead>
          <tr>
            <th></th>
            <th v-for="hora in horas" :key="hora" scope="col" class="mapa-hora">{{ hora }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(fila, dia) in mapa" :key="dia">
            <th scope="row" class="mapa-dia">{{ DIAS[dia].corto }}</th>
            <td
              v-for="hora in horas"
              :key="hora"
              class="mapa-celda"
              :style="{ background: colorRampa(fila[hora], maximo) ?? COLOR_VACIO }"
              :title="`${DIAS[dia].largo}, ${hora}:00 a ${hora + 1}:00 · ${formatearPorcentaje(fila[hora])} de las mesas ocupadas`"
            >
              <span class="sr-only">{{ formatearPorcentaje(fila[hora]) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Leyenda: la misma rampa, de 0 al máximo observado -->
    <div class="row items-center q-mt-sm text-caption text-grey-8">
      <span class="q-mr-sm">0 %</span>
      <span v-for="color in RAMPA_AZUL" :key="color" class="leyenda-paso" :style="{ background: color }"></span>
      <span class="q-ml-sm">{{ formatearPorcentaje(maximo) }}</span>
      <span class="q-ml-md text-grey-7">Horas del día (columnas) · promedio de mesas ocupadas</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RAMPA_AZUL, colorRampa, formatearPorcentaje } from '@/utils/reportes'

/**
 * Mapa de calor de la ocupación (RF36): días de la semana × horas. Cada celda es el
 * % promedio de mesas ocupadas en esa hora. Una sola rampa de azul, de claro a oscuro.
 */
const props = defineProps({
  /** 7 filas (lunes a domingo) × 24 columnas (hora 0 a 23), valores de 0 a 100. */
  mapa: { type: Array, required: true },
})

const DIAS = [
  { corto: 'Lun', largo: 'Lunes' },
  { corto: 'Mar', largo: 'Martes' },
  { corto: 'Mié', largo: 'Miércoles' },
  { corto: 'Jue', largo: 'Jueves' },
  { corto: 'Vie', largo: 'Viernes' },
  { corto: 'Sáb', largo: 'Sábado' },
  { corto: 'Dom', largo: 'Domingo' },
]
const COLOR_VACIO = '#f0efec'

const maximo = computed(() => Math.max(0, ...props.mapa.flat()))

/** Solo se muestran las horas con ocupación (y un margen); sin datos, la jornada típica. */
const horas = computed(() => {
  const activas = []
  props.mapa.forEach((fila) => fila.forEach((valor, hora) => valor > 0 && activas.push(hora)))
  const primera = activas.length ? Math.max(0, Math.min(...activas) - 1) : 8
  const ultima = activas.length ? Math.min(23, Math.max(...activas) + 1) : 20
  return Array.from({ length: ultima - primera + 1 }, (_, i) => primera + i)
})
</script>

<style scoped>
.mapa-contenedor {
  overflow-x: auto;
}
.mapa {
  border-collapse: separate;
  border-spacing: 2px; /* separación de 2 px entre celdas */
}
.mapa-hora {
  font-size: 11px;
  font-weight: 400;
  color: #898781;
  text-align: center;
}
.mapa-dia {
  font-size: 12px;
  font-weight: 500;
  color: #52514e;
  text-align: right;
  padding-right: 6px;
}
.mapa-celda {
  width: 28px;
  min-width: 28px;
  height: 28px;
  border-radius: 4px;
}
.leyenda-paso {
  display: inline-block;
  width: 14px;
  height: 10px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
</style>
