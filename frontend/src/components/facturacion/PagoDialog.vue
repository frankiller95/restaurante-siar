<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 460px; max-width: 95vw">
      <q-card-section class="row items-center">
        <q-icon name="point_of_sale" color="positive" size="sm" class="q-mr-sm" />
        <div class="text-h6">Registrar pago</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form v-if="factura" @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <div class="row items-center text-body2">
            <div class="col"><b>{{ factura.numero }}</b> · saldo por pagar</div>
            <div class="text-h6 text-primary text-weight-bold">{{ formatearPrecio(factura.saldo) }}</div>
          </div>
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <q-btn-toggle
            v-model="datos.metodo"
            :options="opcionesMetodo"
            spread
            no-caps
            unelevated
            toggle-color="primary"
            color="grey-3"
            text-color="grey-9"
          />

          <q-input
            v-model.number="datos.monto"
            type="number"
            step="any"
            min="0"
            outlined
            autofocus
            label="Monto a abonar *"
            prefix="$"
            :hint="datos.monto !== factura.saldo ? 'Pago parcial: el resto queda pendiente.' : ''"
            :error="!!errores.monto"
            :error-message="errores.monto"
            :rules="[(v) => v > 0 || 'Debe ser mayor que cero.', (v) => v <= factura.saldo || 'Supera el saldo de la factura.']"
            lazy-rules
          >
            <template #append>
              <q-btn flat dense no-caps size="sm" color="primary" label="Saldo" @click="datos.monto = factura.saldo" />
            </template>
          </q-input>

          <template v-if="datos.metodo === 'efectivo'">
            <q-input
              v-model.number="datos.monto_recibido"
              type="number"
              step="any"
              min="0"
              outlined
              label="Efectivo recibido"
              prefix="$"
              hint="Opcional: escríbelo para calcular el cambio."
              :error="!!errores.monto_recibido"
              :error-message="errores.monto_recibido"
              :rules="[(v) => !v || v >= datos.monto || 'No alcanza para el monto a abonar.']"
              lazy-rules
            />
            <div v-if="cambio > 0" class="row items-center justify-between bg-green-1 text-positive rounded-borders q-pa-sm">
              <span class="text-weight-medium">Cambio a devolver</span>
              <span class="text-h6 text-weight-bold">{{ formatearPrecio(cambio) }}</span>
            </div>
          </template>

          <q-input
            v-else
            v-model.trim="datos.referencia"
            outlined
            maxlength="60"
            :label="datos.metodo === 'tarjeta' ? 'Número de aprobación *' : 'Referencia de la transferencia *'"
            :error="!!errores.referencia"
            :error-message="errores.referencia"
            :rules="[(v) => !!v || 'Este dato es obligatorio.']"
            lazy-rules
          />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="positive" unelevated no-caps icon="check" label="Registrar pago" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import facturasService from '@/services/facturasService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { METODOS_PAGO } from '@/utils/facturacion'

/** RF31: registrar un pago (efectivo, tarjeta o transferencia). Admite pagos parciales. */
const props = defineProps({
  factura: { type: Object, default: null },
})
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ metodo: 'efectivo', monto: null, monto_recibido: null, referencia: '' })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

const opcionesMetodo = Object.entries(METODOS_PAGO).map(([value, info]) => ({
  value,
  label: info.etiqueta,
  icon: info.icono,
}))

const cambio = computed(() => {
  if (datos.metodo !== 'efectivo' || !datos.monto_recibido || !datos.monto) return 0
  return Math.max(0, Math.round((datos.monto_recibido - datos.monto) * 100) / 100)
})

function preparar() {
  Object.assign(datos, { metodo: 'efectivo', monto: props.factura?.saldo ?? null, monto_recibido: null, referencia: '' })
  errores.value = {}
  mensaje.value = ''
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  const efectivo = datos.metodo === 'efectivo'
  try {
    const respuesta = await facturasService.registrarPago(props.factura.id_factura, {
      metodo: datos.metodo,
      monto: datos.monto,
      monto_recibido: efectivo && datos.monto_recibido ? datos.monto_recibido : null,
      referencia: efectivo ? null : datos.referencia,
    })
    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('guardado', respuesta.data, cambio.value)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
    // Si otro cajero cambió la factura mientras tanto, se avisa para que se recargue.
    if (error.response?.status === 409) emit('guardado', null, 0)
  } finally {
    guardando.value = false
  }
}
</script>
