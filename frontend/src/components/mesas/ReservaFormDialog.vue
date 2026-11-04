<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 560px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">Nueva reserva</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <div class="text-subtitle2 text-grey-8">1. ¿Cuándo y para cuántos?</div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-5">
              <q-input
                v-model="datos.fecha"
                type="date"
                label="Fecha *"
                stack-label
                outlined
                :min="hoy()"
                :error="!!errores.fecha"
                :error-message="errores.fecha"
                :rules="[(v) => !!v || 'Elige la fecha.']"
                lazy-rules
              />
            </div>
            <div class="col-6 col-sm-4">
              <q-input
                v-model="datos.hora"
                type="time"
                label="Hora *"
                stack-label
                outlined
                :error="!!errores.hora"
                :error-message="errores.hora"
                :rules="[(v) => !!v || 'Elige la hora.']"
                lazy-rules
              />
            </div>
            <div class="col-6 col-sm-3">
              <q-input
                v-model.number="datos.cantidad_personas"
                type="number"
                label="Personas *"
                outlined
                min="1"
                max="30"
                :error="!!errores.cantidad_personas"
                :error-message="errores.cantidad_personas"
                :rules="[(v) => (v >= 1 && v <= 30) || 'Entre 1 y 30.']"
                lazy-rules
              />
            </div>
          </div>

          <div class="text-subtitle2 text-grey-8">2. Mesa</div>
          <q-select
            v-model="datos.id_mesa"
            :options="mesas"
            option-value="id_mesa"
            :option-label="etiquetaMesa"
            emit-value
            map-options
            label="Mesa disponible *"
            outlined
            :loading="buscandoMesas"
            :disable="!puedeBuscar"
            :hint="puedeBuscar ? `${mesas.length} mesas disponibles para ese horario.` : 'Primero elige fecha, hora y personas.'"
            :error="!!errores.id_mesa"
            :error-message="errores.id_mesa"
            :rules="[(v) => !!v || 'Elige una mesa.']"
            lazy-rules
          >
            <template #no-option>
              <q-item>
                <q-item-section class="text-grey-7">No hay mesas libres para ese horario y número de personas.</q-item-section>
              </q-item>
            </template>
          </q-select>

          <div class="text-subtitle2 text-grey-8">3. Cliente</div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-7">
              <q-input
                v-model.trim="datos.cliente_nombre"
                label="Nombre *"
                outlined
                maxlength="120"
                :error="!!errores.cliente_nombre"
                :error-message="errores.cliente_nombre"
                :rules="[(v) => !!v || 'Escribe el nombre del cliente.']"
                lazy-rules
              />
            </div>
            <div class="col-12 col-sm-5">
              <q-input
                v-model.trim="datos.cliente_telefono"
                label="Teléfono *"
                type="tel"
                outlined
                maxlength="20"
                :error="!!errores.cliente_telefono"
                :error-message="errores.cliente_telefono"
                :rules="[(v) => !!v || 'Escribe el teléfono.']"
                lazy-rules
              />
            </div>
          </div>
          <q-input
            v-model.trim="datos.observaciones"
            label="Observaciones"
            outlined
            maxlength="255"
            placeholder="Ej. cumpleaños, silla para bebé..."
            :error="!!errores.observaciones"
            :error-message="errores.observaciones"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps label="Registrar reserva" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import mesasService from '@/services/mesasService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'
import { hoy } from '@/utils/mesas'

const props = defineProps({
  /** Fecha sugerida al abrir (la del filtro de la página). */
  fechaInicial: { type: String, default: '' },
  /** Mesa sugerida (al reservar desde el plano de mesas). */
  idMesaInicial: { type: Number, default: null },
})
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()

const datos = reactive({
  fecha: '',
  hora: '',
  cantidad_personas: 2,
  id_mesa: null,
  cliente_nombre: '',
  cliente_telefono: '',
  observaciones: '',
})
const mesas = ref([])
const buscandoMesas = ref(false)
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

const puedeBuscar = computed(
  () => Boolean(datos.fecha && datos.hora && datos.cantidad_personas >= 1 && datos.cantidad_personas <= 30),
)

function etiquetaMesa(mesa) {
  if (!mesa || typeof mesa !== 'object') return ''
  const lugar = mesa.ubicacion ? ` · ${mesa.ubicacion}` : ''
  return `Mesa ${mesa.numero} · hasta ${mesa.capacidad} personas${lugar}`
}

function preparar() {
  errores.value = {}
  mensaje.value = ''
  mesas.value = []
  Object.assign(datos, {
    fecha: props.fechaInicial && props.fechaInicial >= hoy() ? props.fechaInicial : hoy(),
    hora: '',
    cantidad_personas: 2,
    id_mesa: props.idMesaInicial,
    cliente_nombre: '',
    cliente_telefono: '',
    observaciones: '',
  })
}

/** RF15: al cambiar fecha, hora o personas se buscan las mesas sin cruce. */
let temporizador = null
watch(
  () => [datos.fecha, datos.hora, datos.cantidad_personas],
  () => {
    clearTimeout(temporizador)
    if (!puedeBuscar.value) {
      mesas.value = []
      return
    }
    temporizador = setTimeout(buscarMesas, 300)
  },
)

async function buscarMesas() {
  buscandoMesas.value = true
  try {
    mesas.value = await mesasService.disponibles(datos.fecha, datos.hora, datos.cantidad_personas)
    if (!mesas.value.some((m) => m.id_mesa === datos.id_mesa)) datos.id_mesa = null
  } catch (error) {
    mesas.value = []
    errores.value = erroresPorCampo(error)
  } finally {
    buscandoMesas.value = false
  }
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const respuesta = await mesasService.crearReserva({ ...datos, observaciones: datos.observaciones || null })
    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('guardado', respuesta.data)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
    // Si otra persona tomó la mesa mientras tanto, se refresca la lista.
    if (error.response?.data?.codigo === 'cruce_reserva') buscarMesas()
  } finally {
    guardando.value = false
  }
}
</script>
