<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Mesas</h1>
        <p class="page-subtitulo">Estado de las mesas en tiempo real. Se actualiza cada 30 segundos.</p>
      </div>
      <div class="col-12 col-sm-auto row q-gutter-sm">
        <q-btn v-if="puedeReservar" outline color="primary" icon="event" label="Nueva reserva" no-caps @click="abrirReserva(null)" />
        <q-btn v-if="puedeGestionar" color="primary" icon="add" label="Nueva mesa" unelevated no-caps @click="abrirFormulario(null)" />
      </div>
    </div>

    <!-- Resumen por estado: también sirve como filtro -->
    <div class="row q-gutter-sm q-mb-md">
      <q-chip
        clickable
        :outline="filtroEstado !== null"
        color="primary"
        text-color="white"
        icon="table_restaurant"
        :label="`Todas · ${totalMesas}`"
        @click="filtroEstado = null"
      />
      <q-chip
        v-for="(info, estado) in ESTADOS_MESA"
        :key="estado"
        clickable
        :outline="filtroEstado !== estado"
        :color="info.color"
        text-color="white"
        :icon="info.icono"
        :label="`${info.etiqueta} · ${resumen[estado] ?? 0}`"
        @click="filtroEstado = filtroEstado === estado ? null : estado"
      />
    </div>

    <div v-if="cargando && mesas.length === 0" class="row justify-center q-pa-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <q-banner v-else-if="mesasVisibles.length === 0" rounded class="bg-grey-2 text-grey-8">
      {{ mesas.length === 0 ? 'Todavía no hay mesas registradas.' : 'No hay mesas en ese estado.' }}
    </q-banner>

    <div class="row q-col-gutter-md">
      <div v-for="mesa in mesasVisibles" :key="mesa.id_mesa" class="col-6 col-sm-4 col-md-3 col-lg-2">
        <q-card flat bordered class="full-height column" :class="`bg-${ESTADOS_MESA[mesa.estado].fondo}`">
          <q-card-section class="col q-pb-sm">
            <div class="row items-start no-wrap">
              <div class="col">
                <div class="text-caption text-grey-7">Mesa</div>
                <div class="text-h4 text-weight-bold">{{ mesa.numero }}</div>
              </div>
              <q-btn v-if="puedeGestionar" flat round dense icon="more_vert" size="sm" aria-label="Opciones de la mesa">
                <q-menu auto-close>
                  <q-list dense style="min-width: 160px">
                    <q-item clickable @click="abrirFormulario(mesa)">
                      <q-item-section avatar><q-icon name="edit" /></q-item-section>
                      <q-item-section>Editar</q-item-section>
                    </q-item>
                    <q-item clickable @click="eliminar(mesa)">
                      <q-item-section avatar><q-icon name="delete" color="negative" /></q-item-section>
                      <q-item-section>Dar de baja</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
            <q-badge :color="ESTADOS_MESA[mesa.estado].color" class="q-mt-xs">
              <q-icon :name="ESTADOS_MESA[mesa.estado].icono" size="14px" class="q-mr-xs" />
              {{ mesa.estado_etiqueta }}
            </q-badge>
            <div class="text-caption text-grey-8 q-mt-sm">
              <q-icon name="person" /> {{ mesa.capacidad }} personas
              <span v-if="mesa.ubicacion"> · {{ mesa.ubicacion }}</span>
            </div>
            <div v-if="mesa.proxima_reserva" class="text-caption q-mt-xs text-warning text-weight-medium">
              <q-icon name="event" />
              {{ formatearDia(mesa.proxima_reserva.fecha) }}, {{ formatearHora(mesa.proxima_reserva.hora) }} ·
              {{ mesa.proxima_reserva.cliente_nombre }}
            </div>
          </q-card-section>

          <q-card-actions v-if="accionesDe(mesa).length || puedeTomarPedido(mesa)" class="q-px-sm q-pt-none">
            <!-- Módulo 4: tomar un pedido para la mesa (si está libre, se ocupa) -->
            <q-btn
              v-if="puedeTomarPedido(mesa)"
              dense
              unelevated
              no-caps
              size="sm"
              color="primary"
              icon="add_shopping_cart"
              label="Pedido"
              :loading="cambiando === `${mesa.id_mesa}-pedido`"
              @click="tomarPedido(mesa)"
            />
            <q-btn
              v-for="accion in accionesDe(mesa)"
              :key="accion"
              dense
              unelevated
              no-caps
              size="sm"
              :color="ACCIONES_MESA[accion].color"
              :icon="ACCIONES_MESA[accion].icono"
              :label="ACCIONES_MESA[accion].etiqueta"
              :loading="cambiando === `${mesa.id_mesa}-${accion}`"
              @click="cambiarEstado(mesa, accion)"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <MesaFormDialog v-model="formularioAbierto" :mesa="mesaSeleccionada" :ubicaciones="ubicaciones" @guardado="cargar" />
    <ReservaFormDialog v-model="reservaAbierta" :id-mesa-inicial="mesaReserva" @guardado="cargar" />
  </q-page>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import mesasService from '@/services/mesasService'
import pedidosService from '@/services/pedidosService'
import MesaFormDialog from '@/components/mesas/MesaFormDialog.vue'
import ReservaFormDialog from '@/components/mesas/ReservaFormDialog.vue'
import { mensajeError } from '@/utils/errores'
import { ACCIONES_MESA, ESTADOS_MESA, formatearDia, formatearHora } from '@/utils/mesas'

const $q = useQuasar()
const router = useRouter()
const auth = useAuthStore()

const puedeGestionar = computed(() => auth.tienePermiso('gestionar_mesas'))
const puedeReservar = computed(() => auth.tienePermiso('gestionar_reservas') || puedeGestionar.value)

const mesas = ref([])
const resumen = ref({})
const cargando = ref(false)
const cambiando = ref(null)
const filtroEstado = ref(null)
const formularioAbierto = ref(false)
const mesaSeleccionada = ref(null)
const reservaAbierta = ref(false)
const mesaReserva = ref(null)

const totalMesas = computed(() => mesas.value.length)
const mesasVisibles = computed(() =>
  filtroEstado.value ? mesas.value.filter((m) => m.estado === filtroEstado.value) : mesas.value,
)
const ubicaciones = computed(() => [...new Set(mesas.value.map((m) => m.ubicacion).filter(Boolean))].sort())

/**
 * El backend dice qué permite el estado (patrón State); aquí se filtra por
 * permisos. "reservar" no es un botón: la mesa se reserva sola al acercarse la hora.
 */
function accionesDe(mesa) {
  return mesa.acciones.filter((accion) => {
    if (accion === 'ocupar' || accion === 'liberar') return puedeReservar.value
    if (accion === 'enviarAMantenimiento' || accion === 'habilitar') return puedeGestionar.value
    return false
  })
}

async function cargar() {
  cargando.value = true
  try {
    const respuesta = await mesasService.mesas()
    mesas.value = respuesta.data
    resumen.value = respuesta.resumen
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

function puedeTomarPedido(mesa) {
  return auth.tienePermiso('tomar_pedidos') && (mesa.estado === 'libre' || mesa.estado === 'ocupada')
}

async function tomarPedido(mesa) {
  cambiando.value = `${mesa.id_mesa}-pedido`
  try {
    const respuesta = await pedidosService.crear(mesa.id_mesa)
    router.push({ name: 'pedido', params: { id: respuesta.data.id_pedido } })
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cambiando.value = null
  }
}

async function cambiarEstado(mesa, accion) {
  cambiando.value = `${mesa.id_mesa}-${accion}`
  try {
    const respuesta = await mesasService.cambiarEstado(mesa.id_mesa, accion)
    $q.notify({ type: 'positive', message: respuesta.message })
    await cargar()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cambiando.value = null
  }
}

function abrirFormulario(mesa) {
  mesaSeleccionada.value = mesa
  formularioAbierto.value = true
}

function abrirReserva(mesa) {
  mesaReserva.value = mesa?.id_mesa ?? null
  reservaAbierta.value = true
}

function eliminar(mesa) {
  $q.dialog({
    title: 'Dar de baja mesa',
    message: `La mesa ${mesa.numero} dejará de aparecer. Su historial de reservas se conserva. ¿Continuar?`,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Dar de baja' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await mesasService.eliminarMesa(mesa.id_mesa)
      $q.notify({ type: 'positive', message: respuesta.message })
      cargar()
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

let intervalo = null
onMounted(() => {
  cargar()
  intervalo = setInterval(() => {
    if (!formularioAbierto.value && !reservaAbierta.value) cargar()
  }, 30000)
})
onBeforeUnmount(() => clearInterval(intervalo))
</script>
