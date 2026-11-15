<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 440px; max-width: 95vw">
      <q-card-section class="row items-center">
        <q-icon name="percent" color="primary" size="sm" class="q-mr-sm" />
        <div class="text-h6">Descuento de la factura</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form v-if="factura" @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <div class="text-body2">
            <b>{{ factura.numero }}</b> · total actual <b>{{ formatearPrecio(factura.total) }}</b>
          </div>
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>
          <DescuentoCampos :modelo="datos" :errores="errores" />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps label="Guardar descuento" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import facturasService from '@/services/facturasService'
import DescuentoCampos from '@/components/facturacion/DescuentoCampos.vue'
import { erroresPorCampo, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'

/** RF30: aplicar, cambiar o quitar el descuento de una factura pendiente sin pagos. */
const props = defineProps({
  factura: { type: Object, default: null },
})
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ descuento_tipo: 'ninguno', descuento_valor: null, motivo_descuento: '' })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

function preparar() {
  const f = props.factura
  Object.assign(datos, {
    descuento_tipo: f?.descuento_tipo ?? 'ninguno',
    descuento_valor: f?.descuento_tipo !== 'ninguno' ? f?.descuento_valor : null,
    motivo_descuento: f?.motivo_descuento ?? '',
  })
  errores.value = {}
  mensaje.value = ''
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  const conDescuento = datos.descuento_tipo !== 'ninguno'
  try {
    const respuesta = await facturasService.aplicarDescuento(props.factura.id_factura, {
      descuento_tipo: datos.descuento_tipo,
      descuento_valor: conDescuento ? datos.descuento_valor : null,
      motivo_descuento: conDescuento ? datos.motivo_descuento || null : null,
    })
    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('guardado', respuesta.data)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
  } finally {
    guardando.value = false
  }
}
</script>
