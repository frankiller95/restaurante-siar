<template>
  <q-card flat bordered class="full-height">
    <q-card-section>
      <div class="text-caption text-grey-8">{{ etiqueta }}</div>
      <div :class="destacado ? 'kpi-valor kpi-valor--grande' : 'kpi-valor'">{{ valor }}</div>

      <div class="q-mt-xs text-caption row items-center no-wrap">
        <template v-if="variacion === null || variacion === undefined">
          <span class="text-grey-7">Sin datos del periodo anterior</span>
        </template>
        <template v-else>
          <!-- Dirección + si es bueno o malo (el color nunca va solo: lleva ícono y texto) -->
          <span class="row items-center no-wrap text-weight-medium" :style="{ color: colorVariacion }">
            <q-icon :name="icono" size="16px" class="q-mr-xs" />
            {{ texto }}
          </span>
          <span class="text-grey-7 q-ml-xs">vs periodo anterior</span>
        </template>
      </div>
      <div v-if="nota" class="text-caption text-grey-7 q-mt-xs">{{ nota }}</div>
    </q-card-section>
  </q-card>
</template>

<script setup>
import { computed } from 'vue'
import { COLORES, formatearNumero } from '@/utils/reportes'

/** Cifra con su variación contra el periodo anterior. */
const props = defineProps({
  etiqueta: { type: String, required: true },
  valor: { type: String, required: true },
  variacion: { type: Number, default: null },
  /** 'porcentaje' o 'puntos' (para márgenes, que se comparan en puntos). */
  variacionTipo: { type: String, default: 'porcentaje' },
  /** Si subir es bueno (ventas) o malo (costos, mermas, descuentos). */
  subeEsBueno: { type: Boolean, default: true },
  destacado: { type: Boolean, default: false },
  nota: { type: String, default: '' },
})

const icono = computed(() => (props.variacion > 0 ? 'arrow_upward' : props.variacion < 0 ? 'arrow_downward' : 'remove'))

const texto = computed(() => {
  const signo = props.variacion > 0 ? '+' : ''
  const cifra = formatearNumero(props.variacion)
  return props.variacionTipo === 'puntos' ? `${signo}${cifra} pts` : `${signo}${cifra} %`
})

const colorVariacion = computed(() => {
  if (!props.variacion) return COLORES.tintaSuave
  return props.variacion > 0 === props.subeEsBueno ? COLORES.bueno : COLORES.malo
})
</script>

<style scoped>
.kpi-valor {
  font-size: 1.5rem;
  font-weight: 600;
  line-height: 1.3;
}
.kpi-valor--grande {
  font-size: 2.25rem;
}
</style>
