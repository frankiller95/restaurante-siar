<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 520px; max-width: 95vw">
      <q-card-section class="row items-center">
        <q-icon name="receipt_long" color="primary" size="sm" class="q-mr-sm" />
        <div class="text-h6">Generar factura</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form v-if="pedido" @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <div class="text-body2">
            <b>Pedido #{{ pedido.id_pedido }}</b> · {{ lugarPedido(pedido) }} ·
            {{ pedido.cantidad_productos }} {{ pedido.cantidad_productos === 1 ? 'producto' : 'productos' }}
          </div>
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-7">
              <q-input
                v-model.trim="datos.cliente_nombre"
                label="Nombre del cliente"
                placeholder="Consumidor final"
                outlined
                dense
                maxlength="160"
                :error="!!errores.cliente_nombre"
                :error-message="errores.cliente_nombre"
              />
            </div>
            <div class="col-12 col-sm-5">
              <q-input
                v-model.trim="datos.cliente_documento"
                label="NIT / Cédula"
                outlined
                dense
                maxlength="30"
                :error="!!errores.cliente_documento"
                :error-message="errores.cliente_documento"
              />
            </div>
          </div>

          <div>
            <div class="text-subtitle2 q-mb-sm">Descuento</div>
            <DescuentoCampos :modelo="datos" :errores="errores" />
          </div>

          <q-card flat bordered class="bg-grey-1">
            <q-card-section>
              <div v-if="!resumen" class="row justify-center q-pa-sm"><q-spinner color="primary" /></div>
              <FacturaTotales v-else :resumen="resumen" />
            </q-card-section>
          </q-card>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps icon="receipt_long" label="Generar factura" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import facturasService from '@/services/facturasService'
import DescuentoCampos from '@/components/facturacion/DescuentoCampos.vue'
import FacturaTotales from '@/components/facturacion/FacturaTotales.vue'
import { erroresPorCampo, mensajeError } from '@/utils/errores'
import { lugarPedido } from '@/utils/facturacion'

/** RF28: generar la factura de un pedido entregado. Muestra la cuenta calculada (RF29, RF30) antes de guardar. */
const props = defineProps({
  pedido: { type: Object, default: null },
})
const emit = defineEmits(['generada'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({
  cliente_nombre: '',
  cliente_documento: '',
  descuento_tipo: 'ninguno',
  descuento_valor: null,
  motivo_descuento: '',
})
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)
const resumen = ref(null)

function preparar() {
  Object.assign(datos, {
    cliente_nombre: props.pedido?.cliente_nombre ?? '',
    cliente_documento: '',
    descuento_tipo: 'ninguno',
    descuento_valor: null,
    motivo_descuento: '',
  })
  errores.value = {}
  mensaje.value = ''
  resumen.value = null
  calcular()
}

function cuerpoDescuento() {
  const conDescuento = datos.descuento_tipo !== 'ninguno'
  return {
    descuento_tipo: datos.descuento_tipo,
    descuento_valor: conDescuento ? datos.descuento_valor : null,
    motivo_descuento: conDescuento ? datos.motivo_descuento || null : null,
  }
}

/** Vista previa: se recalcula al cambiar el descuento (sin guardar nada). */
let temporizador = null
async function calcular() {
  if (!props.pedido) return
  const conDescuento = datos.descuento_tipo !== 'ninguno'
  // Sin valor todavía no hay nada que calcular: se muestra la cuenta sin descuento.
  const valor = conDescuento && datos.descuento_valor > 0 ? datos.descuento_valor : null
  try {
    resumen.value = await facturasService.previsualizar({
      id_pedido: props.pedido.id_pedido,
      descuento_tipo: valor ? datos.descuento_tipo : 'ninguno',
      descuento_valor: valor,
    })
    errores.value = { ...errores.value, descuento_valor: undefined }
  } catch (error) {
    const porCampo = erroresPorCampo(error)
    if (porCampo.descuento_valor) errores.value = { ...errores.value, descuento_valor: porCampo.descuento_valor }
    else mensaje.value = mensajeError(error)
  }
}

watch(
  () => [datos.descuento_tipo, datos.descuento_valor],
  () => {
    if (!abierto.value) return
    clearTimeout(temporizador)
    temporizador = setTimeout(calcular, 350)
  },
)

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const respuesta = await facturasService.generar({
      id_pedido: props.pedido.id_pedido,
      cliente_nombre: datos.cliente_nombre || null,
      cliente_documento: datos.cliente_documento || null,
      ...cuerpoDescuento(),
    })
    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('generada', respuesta.data)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
  } finally {
    guardando.value = false
  }
}
</script>
