<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 440px; max-width: 95vw">
      <q-card-section class="row items-center">
        <q-icon :name="config.icono" :color="config.color" size="sm" class="q-mr-sm" />
        <div class="text-h6">{{ config.titulo }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form v-if="insumo" @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <div class="text-body2">
            <b>{{ insumo.nombre }}</b> · stock actual
            <b>{{ formatearCantidad(insumo.stock_actual) }} {{ insumo.unidad }}</b>
          </div>
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <!-- RF25 entrada y RF26 merma: cantidad -->
          <q-input
            v-if="modo !== 'ajuste'"
            v-model.number="datos.cantidad"
            type="number"
            step="any"
            min="0"
            :label="modo === 'entrada' ? 'Cantidad que entra *' : 'Cantidad que se pierde *'"
            :suffix="insumo.unidad"
            outlined
            autofocus
            :error="!!errores.cantidad"
            :error-message="errores.cantidad"
            :rules="[(v) => v > 0 || 'Debe ser mayor que cero.']"
            lazy-rules
          />

          <!-- RF26 ajuste: lo que se contó -->
          <template v-else>
            <q-input
              v-model.number="datos.stock_contado"
              type="number"
              step="any"
              min="0"
              label="Stock contado físicamente *"
              :suffix="insumo.unidad"
              outlined
              autofocus
              :error="!!errores.stock_contado"
              :error-message="errores.stock_contado"
              :rules="[(v) => (v !== null && v !== '' && v >= 0) || 'Escribe lo que contaste.']"
              lazy-rules
            />
            <div v-if="diferencia !== null" class="text-caption" :class="diferencia < 0 ? 'text-negative' : 'text-positive'">
              Diferencia: {{ diferencia > 0 ? '+' : '' }}{{ formatearCantidad(diferencia) }} {{ insumo.unidad }}
            </div>
          </template>

          <template v-if="modo === 'entrada'">
            <q-input
              v-model.number="datos.costo_unitario"
              type="number"
              step="any"
              min="0"
              label="Costo unitario"
              prefix="$"
              :suffix="`/ ${insumo.unidad}`"
              outlined
              hint="Si cambió el precio, se actualiza el costo del insumo."
              :error="!!errores.costo_unitario"
              :error-message="errores.costo_unitario"
            />
            <q-input
              v-model.trim="datos.proveedor"
              label="Proveedor"
              outlined
              maxlength="120"
              :error="!!errores.proveedor"
              :error-message="errores.proveedor"
            />
            <q-input
              v-model="datos.fecha"
              type="date"
              label="Fecha de la entrada"
              stack-label
              outlined
              :max="hoy()"
              :error="!!errores.fecha"
              :error-message="errores.fecha"
            />
          </template>

          <q-input
            v-else
            v-model.trim="datos.motivo"
            :label="modo === 'merma' ? 'Motivo de la merma *' : 'Motivo del ajuste *'"
            :placeholder="modo === 'merma' ? 'Ej. producto vencido, se dañó...' : 'Ej. conteo de fin de mes'"
            outlined
            maxlength="255"
            :error="!!errores.motivo"
            :error-message="errores.motivo"
            :rules="[(v) => (v && v.length >= 3) || 'Escribe el motivo.']"
            lazy-rules
          />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" :color="config.color" unelevated no-caps :label="config.boton" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import inventarioService from '@/services/inventarioService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'
import { formatearCantidad } from '@/utils/inventario'
import { hoy } from '@/utils/mesas'

const props = defineProps({
  insumo: { type: Object, default: null },
  /** 'entrada' | 'merma' | 'ajuste' */
  modo: { type: String, default: 'entrada' },
})
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ cantidad: null, costo_unitario: null, proveedor: '', fecha: '', motivo: '', stock_contado: null })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

const CONFIG = {
  entrada: { titulo: 'Registrar entrada', boton: 'Registrar entrada', icono: 'add_circle', color: 'positive' },
  merma: { titulo: 'Registrar merma', boton: 'Registrar merma', icono: 'delete_sweep', color: 'negative' },
  ajuste: { titulo: 'Ajuste por conteo físico', boton: 'Ajustar stock', icono: 'tune', color: 'warning' },
}
const config = computed(() => CONFIG[props.modo])

const diferencia = computed(() => {
  if (datos.stock_contado === null || datos.stock_contado === '' || !props.insumo) return null
  return Math.round((Number(datos.stock_contado) - props.insumo.stock_actual) * 1000) / 1000
})

function preparar() {
  errores.value = {}
  mensaje.value = ''
  Object.assign(datos, {
    cantidad: null,
    costo_unitario: props.insumo?.costo_unitario ?? null,
    proveedor: '',
    fecha: hoy(),
    motivo: '',
    stock_contado: null,
  })
}

function cuerpo() {
  if (props.modo === 'entrada') {
    return {
      cantidad: datos.cantidad,
      costo_unitario: datos.costo_unitario === '' ? null : datos.costo_unitario,
      proveedor: datos.proveedor || null,
      // Hoy: se usa la hora actual; otro día: ese día.
      fecha: datos.fecha && datos.fecha !== hoy() ? datos.fecha : null,
    }
  }
  if (props.modo === 'merma') return { cantidad: datos.cantidad, motivo: datos.motivo }
  return { stock_contado: datos.stock_contado, motivo: datos.motivo }
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const respuesta = await inventarioService.registrarMovimiento(props.insumo.id_insumo, props.modo, cuerpo())
    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('guardado', respuesta.insumo)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
  } finally {
    guardando.value = false
  }
}
</script>
