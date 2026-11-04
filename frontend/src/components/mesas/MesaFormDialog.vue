<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 420px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ mesa ? `Editar mesa ${mesa.numero}` : 'Nueva mesa' }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>
          <div class="row q-col-gutter-md">
            <div class="col-6">
              <q-input
                v-model.number="datos.numero"
                type="number"
                label="Número *"
                outlined
                autofocus
                min="1"
                max="999"
                :error="!!errores.numero"
                :error-message="errores.numero"
                :rules="[(v) => (v >= 1 && v <= 999) || 'Entre 1 y 999.']"
                lazy-rules
              />
            </div>
            <div class="col-6">
              <q-input
                v-model.number="datos.capacidad"
                type="number"
                label="Capacidad *"
                suffix="personas"
                outlined
                min="1"
                max="30"
                :error="!!errores.capacidad"
                :error-message="errores.capacidad"
                :rules="[(v) => (v >= 1 && v <= 30) || 'Entre 1 y 30 personas.']"
                lazy-rules
              />
            </div>
          </div>
          <q-select
            v-model="datos.ubicacion"
            :options="ubicaciones"
            label="Ubicación"
            outlined
            use-input
            new-value-mode="add-unique"
            hide-dropdown-icon
            input-debounce="0"
            clearable
            hint="Elige una o escribe una nueva y presiona Enter."
            :error="!!errores.ubicacion"
            :error-message="errores.ubicacion"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps :label="mesa ? 'Guardar' : 'Crear mesa'" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import mesasService from '@/services/mesasService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const props = defineProps({
  mesa: { type: Object, default: null },
  ubicaciones: { type: Array, default: () => [] },
})
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ numero: null, capacidad: 4, ubicacion: null })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

function preparar() {
  errores.value = {}
  mensaje.value = ''
  datos.numero = props.mesa?.numero ?? null
  datos.capacidad = props.mesa?.capacidad ?? 4
  datos.ubicacion = props.mesa?.ubicacion ?? null
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const respuesta = props.mesa
      ? await mesasService.actualizarMesa(props.mesa.id_mesa, { ...datos })
      : await mesasService.crearMesa({ ...datos })
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
