<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 480px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ insumo ? 'Editar insumo' : 'Nuevo insumo' }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <q-input
            v-model.trim="datos.nombre"
            label="Nombre *"
            outlined
            autofocus
            maxlength="100"
            :error="!!errores.nombre"
            :error-message="errores.nombre"
            :rules="[(v) => !!v || 'El nombre es obligatorio.']"
            lazy-rules
          />
          <q-select
            v-model="datos.unidad_medida"
            :options="UNIDADES"
            emit-value
            map-options
            label="Unidad de medida *"
            outlined
            :error="!!errores.unidad_medida"
            :error-message="errores.unidad_medida"
            :hint="insumo ? 'No se puede cambiar si el insumo ya tiene movimientos o recetas.' : undefined"
          />
          <div class="row q-col-gutter-md">
            <div v-if="!insumo" class="col-12 col-sm-6">
              <q-input
                v-model.number="datos.stock_inicial"
                type="number"
                step="any"
                min="0"
                label="Stock inicial"
                :suffix="unidad"
                outlined
                :error="!!errores.stock_inicial"
                :error-message="errores.stock_inicial"
              />
            </div>
            <div class="col-12" :class="insumo ? 'col-sm-6' : 'col-sm-6'">
              <q-input
                v-model.number="datos.stock_minimo"
                type="number"
                step="any"
                min="0"
                label="Stock mínimo *"
                :suffix="unidad"
                outlined
                hint="Al llegar aquí se genera una alerta."
                :error="!!errores.stock_minimo"
                :error-message="errores.stock_minimo"
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-input
                v-model.number="datos.costo_unitario"
                type="number"
                step="any"
                min="0"
                label="Costo unitario *"
                prefix="$"
                :suffix="`/ ${unidad}`"
                outlined
                :error="!!errores.costo_unitario"
                :error-message="errores.costo_unitario"
              />
            </div>
          </div>
          <div v-if="insumo" class="text-caption text-grey-7">
            El stock no se edita aquí: usa Entrada, Merma o Ajuste para que quede en el kárdex.
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps :label="insumo ? 'Guardar' : 'Registrar insumo'" :loading="guardando" />
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
import { UNIDADES } from '@/utils/inventario'

const props = defineProps({ insumo: { type: Object, default: null } })
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ nombre: '', unidad_medida: 'kilogramo', stock_inicial: 0, stock_minimo: 0, costo_unitario: 0 })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

const unidad = computed(() => UNIDADES.find((u) => u.value === datos.unidad_medida)?.corto ?? '')

function preparar() {
  errores.value = {}
  mensaje.value = ''
  Object.assign(datos, {
    nombre: props.insumo?.nombre ?? '',
    unidad_medida: props.insumo?.unidad_medida ?? 'kilogramo',
    stock_inicial: 0,
    stock_minimo: props.insumo?.stock_minimo ?? 0,
    costo_unitario: props.insumo?.costo_unitario ?? 0,
  })
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const { stock_inicial: stockInicial, ...resto } = datos
    const respuesta = props.insumo
      ? await inventarioService.actualizar(props.insumo.id_insumo, resto)
      : await inventarioService.crear({ ...resto, stock_inicial: stockInicial || 0 })
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
