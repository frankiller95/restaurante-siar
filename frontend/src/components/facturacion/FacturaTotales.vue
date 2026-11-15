<template>
  <div class="column q-gutter-xs">
    <div class="row justify-between">
      <span class="text-grey-8">Subtotal productos</span>
      <span>{{ formatearPrecio(resumen.subtotal) }}</span>
    </div>
    <div v-if="resumen.descuento_promociones > 0" class="row justify-between text-positive">
      <span>Promociones del pedido</span>
      <span>− {{ formatearPrecio(resumen.descuento_promociones) }}</span>
    </div>
    <div v-if="resumen.descuento > 0" class="row justify-between text-positive">
      <span>
        Descuento
        <template v-if="resumen.descuento_tipo === 'porcentaje'">({{ resumen.descuento_valor }} %)</template>
      </span>
      <span>− {{ formatearPrecio(resumen.descuento) }}</span>
    </div>

    <q-separator class="q-my-xs" />

    <div class="row justify-between text-grey-8">
      <span>Base gravable</span>
      <span>{{ formatearPrecio(resumen.base_gravable) }}</span>
    </div>
    <div class="row justify-between text-grey-8">
      <span>
        {{ resumen.impuesto_nombre }} ({{ resumen.impuesto_porcentaje }} %)
        <span class="text-caption">{{ resumen.impuesto_incluido ? 'incluido' : 'adicional' }}</span>
      </span>
      <span>{{ formatearPrecio(resumen.impuesto) }}</span>
    </div>
    <div v-if="resumen.propina > 0" class="row justify-between text-grey-8">
      <span>Propina</span>
      <span>{{ formatearPrecio(resumen.propina) }}</span>
    </div>

    <q-separator class="q-my-xs" />

    <div class="row justify-between items-center">
      <span class="text-subtitle1 text-weight-medium">Total</span>
      <span class="text-h6 text-primary text-weight-bold">{{ formatearPrecio(resumen.total) }}</span>
    </div>
  </div>
</template>

<script setup>
import { formatearPrecio } from '@/utils/formato'

/**
 * Desglose de una cuenta: sirve para la vista previa (respuesta de
 * /facturas/previsualizar) y para una factura ya generada.
 */
defineProps({
  resumen: { type: Object, required: true },
})
</script>
