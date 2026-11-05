<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Pedidos</h1>
        <p class="page-subtitulo">Estado de los pedidos de las mesas. Se actualiza cada 20 segundos.</p>
      </div>
      <div v-if="puedeTomar" class="col-12 col-sm-auto">
        <q-btn color="primary" icon="add_shopping_cart" label="Nuevo pedido" unelevated no-caps @click="abrirNuevo" />
      </div>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-sm-4 col-md-3">
          <q-select v-model="filtros.estado" :options="opcionesEstado" emit-value map-options dense outlined label="Estado" />
        </div>
        <div class="col-6 col-sm-4 col-md-3">
          <q-input v-model="filtros.fecha" type="date" dense outlined stack-label label="Día" clearable />
        </div>
        <div class="col-6 col-sm-4 col-md-2">
          <q-input v-model.number="filtros.mesa" type="number" dense outlined label="Mesa (número)" clearable />
        </div>
        <div v-if="puedeTomar" class="col-12 col-md-auto">
          <q-toggle v-model="filtros.mios" label="Solo mis pedidos" />
        </div>
      </q-card-section>
    </q-card>

    <div v-if="cargando && pedidos.length === 0" class="row justify-center q-pa-xl">
      <q-spinner color="primary" size="40px" />
    </div>
    <q-banner v-else-if="pedidosVisibles.length === 0" rounded class="bg-grey-2 text-grey-8">
      No hay pedidos con esos filtros.
    </q-banner>

    <div class="row q-col-gutter-md">
      <div v-for="pedido in pedidosVisibles" :key="pedido.id_pedido" class="col-12 col-sm-6 col-md-4 col-lg-3">
        <q-card flat bordered class="full-height column cursor-pointer" @click="abrirDetalle(pedido)">
          <q-card-section class="col">
            <div class="row items-center no-wrap">
              <div class="col">
                <div class="text-caption text-grey-7">Pedido #{{ pedido.id_pedido }}</div>
                <div class="text-h6">Mesa {{ pedido.mesa?.numero }}</div>
              </div>
              <q-badge :color="colorEstado(pedido)" :label="textoEstado(pedido)" />
            </div>
            <div class="text-caption text-grey-7 q-mt-xs">
              {{ pedido.mesero }} · {{ haceCuanto(pedido.fecha_creacion) }}
            </div>
            <q-list dense class="q-mt-sm">
              <q-item v-for="linea in pedido.detalles.slice(0, 4)" :key="linea.id_detalle" class="q-px-none" style="min-height: 24px">
                <q-item-section>
                  <span><b>{{ linea.cantidad }}×</b> {{ linea.producto }}</span>
                </q-item-section>
              </q-item>
              <div v-if="pedido.detalles.length > 4" class="text-caption text-grey-7">
                y {{ pedido.detalles.length - 4 }} más...
              </div>
              <div v-if="pedido.detalles.length === 0" class="text-caption text-grey-6">Sin productos todavía.</div>
            </q-list>
          </q-card-section>
          <q-separator />
          <q-card-section class="row items-center q-py-sm">
            <div class="col text-caption text-grey-7">{{ pedido.cantidad_productos }} productos</div>
            <div class="text-subtitle1 text-weight-bold text-primary">{{ formatearPrecio(pedido.total) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div v-if="paginacion.ultima > 1" class="row justify-center q-mt-md">
      <q-pagination v-model="paginacion.pagina" :max="paginacion.ultima" direction-links @update:model-value="cargar" />
    </div>

    <!-- RF17: elegir la mesa del nuevo pedido -->
    <q-dialog v-model="nuevoAbierto">
      <q-card style="width: 420px; max-width: 95vw">
        <q-card-section class="row items-center">
          <div class="text-h6">Nuevo pedido</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="text-body2 text-grey-7 q-mb-sm">Elige la mesa. Si está libre, quedará ocupada.</div>
          <div v-if="mesasDisponibles.length === 0" class="text-grey-7">No hay mesas libres u ocupadas en este momento.</div>
          <div class="row q-col-gutter-sm">
            <div v-for="mesa in mesasDisponibles" :key="mesa.id_mesa" class="col-4">
              <q-btn
                class="full-width"
                unelevated
                no-caps
                :color="mesa.estado === 'libre' ? 'green-1' : 'red-1'"
                :text-color="mesa.estado === 'libre' ? 'positive' : 'negative'"
                :loading="creando === mesa.id_mesa"
                @click="crearPedido(mesa)"
              >
                <div class="column items-center">
                  <span class="text-h6">{{ mesa.numero }}</span>
                  <span class="text-caption">{{ mesa.estado_etiqueta }}</span>
                </div>
              </q-btn>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import pedidosService from '@/services/pedidosService'
import mesasService from '@/services/mesasService'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { hoy } from '@/utils/mesas'
import { colorEstado, ESTADOS_PEDIDO, haceCuanto, textoEstado } from '@/utils/pedidos'

const $q = useQuasar()
const router = useRouter()
const auth = useAuthStore()

const puedeTomar = computed(() => auth.tienePermiso('tomar_pedidos'))

const pedidos = ref([])
const cargando = ref(false)
const filtros = reactive({ estado: 'abiertos', fecha: hoy(), mesa: null, mios: false })
const paginacion = reactive({ pagina: 1, ultima: 1 })

const opcionesEstado = [
  { label: 'Abiertos', value: 'abiertos' },
  ...Object.entries(ESTADOS_PEDIDO).map(([value, info]) => ({ label: info.etiqueta, value })),
  { label: 'Todos', value: null },
]

// El filtro de mesa es por número (más cómodo que el id).
const pedidosVisibles = computed(() =>
  filtros.mesa ? pedidos.value.filter((p) => p.mesa?.numero === Number(filtros.mesa)) : pedidos.value,
)

async function cargar() {
  cargando.value = true
  try {
    const respuesta = await pedidosService.listar({
      page: paginacion.pagina,
      por_pagina: 48,
      estado: filtros.estado || undefined,
      fecha: filtros.fecha || undefined,
      mios: filtros.mios ? 1 : undefined,
    })
    pedidos.value = respuesta.data
    paginacion.ultima = respuesta.meta.last_page
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

watch(
  () => [filtros.estado, filtros.fecha, filtros.mios],
  () => {
    paginacion.pagina = 1
    cargar()
  },
)

function abrirDetalle(pedido) {
  router.push({ name: 'pedido', params: { id: pedido.id_pedido } })
}

// --- Nuevo pedido ---
const nuevoAbierto = ref(false)
const mesasDisponibles = ref([])
const creando = ref(null)

async function abrirNuevo() {
  nuevoAbierto.value = true
  try {
    const respuesta = await mesasService.mesas()
    mesasDisponibles.value = respuesta.data.filter((m) => m.estado === 'libre' || m.estado === 'ocupada')
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  }
}

async function crearPedido(mesa) {
  creando.value = mesa.id_mesa
  try {
    const respuesta = await pedidosService.crear(mesa.id_mesa)
    nuevoAbierto.value = false
    router.push({ name: 'pedido', params: { id: respuesta.data.id_pedido } })
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    creando.value = null
  }
}

let intervalo = null
onMounted(() => {
  cargar()
  intervalo = setInterval(() => {
    if (!nuevoAbierto.value) cargar()
  }, 20000)
})
onBeforeUnmount(() => clearInterval(intervalo))
</script>
