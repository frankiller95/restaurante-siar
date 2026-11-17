<template>
  <div v-if="items.length === 0" class="text-grey-7">Sin datos en este periodo.</div>
  <div v-else class="column q-gutter-sm">
    <div v-for="item in items" :key="item.etiqueta">
      <div class="row items-baseline justify-between text-body2">
        <span>{{ item.etiqueta }}</span>
        <span class="text-weight-medium">
          {{ item.texto }}
          <span class="text-grey-7 text-caption">· {{ formatearPorcentaje(item.porcentaje) }}</span>
        </span>
      </div>
      <!-- Pista: un paso más claro de la misma rampa que el relleno -->
      <div class="pista" role="img" :aria-label="`${item.etiqueta}: ${formatearPorcentaje(item.porcentaje)}`">
        <div class="relleno" :style="{ width: `${Math.max(0, Math.min(100, item.porcentaje))}%` }"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatearPorcentaje } from '@/utils/reportes'

/** Lista de proporciones: nombre, barra y cifra, una fila por elemento (una sola barra de un solo color). */
defineProps({
  /** [{ etiqueta, texto, porcentaje }] */
  items: { type: Array, required: true },
})
</script>

<style scoped>
.pista {
  height: 8px;
  border-radius: 4px;
  background: #cde2fb;
  margin-top: 4px;
  overflow: hidden;
}
.relleno {
  height: 100%;
  border-radius: 4px;
  background: #2a78d6;
}
</style>
