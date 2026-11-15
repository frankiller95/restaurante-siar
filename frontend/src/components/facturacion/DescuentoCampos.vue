<template>
  <div class="q-gutter-md">
    <q-btn-toggle
      v-model="modelo.descuento_tipo"
      :options="TIPOS_DESCUENTO"
      spread
      no-caps
      unelevated
      toggle-color="primary"
      color="grey-3"
      text-color="grey-9"
      @update:model-value="cambiarTipo"
    />

    <template v-if="modelo.descuento_tipo !== 'ninguno'">
      <q-input
        v-model.number="modelo.descuento_valor"
        type="number"
        step="any"
        min="0"
        outlined
        dense
        :label="modelo.descuento_tipo === 'porcentaje' ? 'Porcentaje de descuento *' : 'Valor del descuento *'"
        :prefix="modelo.descuento_tipo === 'valor' ? '$' : undefined"
        :suffix="modelo.descuento_tipo === 'porcentaje' ? '%' : undefined"
        :error="!!errores.descuento_valor"
        :error-message="errores.descuento_valor"
      />
      <q-input
        v-model.trim="modelo.motivo_descuento"
        outlined
        dense
        maxlength="255"
        label="Motivo del descuento *"
        placeholder="Ej. cliente frecuente, demora en el servicio"
        :error="!!errores.motivo_descuento"
        :error-message="errores.motivo_descuento"
      />
    </template>
  </div>
</template>

<script setup>
import { TIPOS_DESCUENTO } from '@/utils/facturacion'

/**
 * Campos del descuento (RF30): tipo, valor y motivo. Se usa al generar la
 * factura y al cambiar el descuento de una factura pendiente.
 * modelo: { descuento_tipo, descuento_valor, motivo_descuento }
 */
const props = defineProps({
  modelo: { type: Object, required: true },
  errores: { type: Object, default: () => ({}) },
})

function cambiarTipo(tipo) {
  if (tipo === 'ninguno') {
    props.modelo.descuento_valor = null
    props.modelo.motivo_descuento = ''
  }
}
</script>
