<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Cocina</h1>
        <p class="page-subtitulo">Pedidos enviados por los meseros, en orden de llegada. Se actualiza cada 15 segundos.</p>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn flat round icon="refresh" aria-label="Actualizar" :loading="cargando" @click="cargar" />
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <div v-for="columna in columnas" :key="columna.clave" class="col-12 col-md-4">
        <div class="row items-center q-mb-sm">
          <q-icon :name="columna.icono" :color="columna.color" size="sm" class="q-mr-xs" />
          <span class="text-subtitle1 text-weight-medium">{{ columna.titulo }}</span>
          <q-badge class="q-ml-sm" :color="columna.color" :label="columna.pedidos.length" />
        </div>

        <div v-if="columna.pedidos.length === 0" class="text-grey-6 text-body2 q-pa-md bg-grey-2 rounded-borders">
          {{ columna.vacio }}
        </div>

        <q-card v-for="pedido in columna.pedidos" :key="pedido.id_pedido" flat bordered class="q-mb-md">
          <q-card-section class="q-pb-sm">
            <div class="row items-center no-wrap">
              <div class="col">
                <div class="text-h6">Mesa {{ pedido.mesa?.numero }}</div>
                <div class="text-caption text-grey-7">
                  #{{ pedido.id_pedido }} · {{ pedido.mesero }} · {{ haceCuanto(columna.desde(pedido)) }}
                </div>
              </div>
            </div>
          </q-card-section>
          <q-list dense class="q-px-sm">
            <q-item v-for="linea in pedido.detalles" :key="linea.id_detalle">
              <q-item-section side class="text-weight-bold text-body1">{{ linea.cantidad }}×</q-item-section>
              <q-item-section>
                <q-item-label class="text-body1">{{ linea.producto }}</q-item-label>
                <q-item-label v-if="linea.observacion" caption class="text-warning text-weight-medium">
                  {{ linea.observacion }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-if="pedido.observaciones" class="q-pt-xs text-caption text-weight-medium text-negative">
            <q-icon name="info" /> {{ pedido.observaciones }}
          </q-card-section>
          <q-card-actions v-if="columna.accion" class="q-px-md q-pb-md">
            <q-btn
              class="full-width"
              unelevated
              no-caps
              :color="columna.accion.color"
              :icon="columna.accion.icono"
              :label="columna.accion.etiqueta"
              :loading="procesando === pedido.id_pedido"
              @click="avanzar(pedido, columna.accion)"
            />
          </q-card-actions>
          <q-card-section v-else class="q-pt-none text-caption text-positive">
            Esperando que el mesero lo recoja.
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import pedidosService from '@/services/pedidosService'
import { mensajeError } from '@/utils/errores'
import { haceCuanto } from '@/utils/pedidos'

const $q = useQuasar()

const pedidos = ref([])
const cargando = ref(false)
const procesando = ref(null)

const columnas = computed(() => [
  {
    clave: 'por_preparar',
    titulo: 'Por preparar',
    icono: 'inbox',
    color: 'primary',
    vacio: 'No hay pedidos nuevos.',
    pedidos: pedidos.value.filter((p) => p.estado === 'pendiente'),
    desde: (p) => p.fecha_envio_cocina,
    accion: { etiqueta: 'Iniciar preparación', icono: 'soup_kitchen', color: 'warning', llamada: pedidosService.preparar },
  },
  {
    clave: 'en_preparacion',
    titulo: 'En preparación',
    icono: 'soup_kitchen',
    color: 'warning',
    vacio: 'Nada en preparación.',
    pedidos: pedidos.value.filter((p) => p.estado === 'en_preparacion'),
    desde: (p) => p.fecha_inicio_preparacion,
    accion: { etiqueta: 'Marcar listo', icono: 'notifications_active', color: 'positive', llamada: pedidosService.marcarListo },
  },
  {
    clave: 'listos',
    titulo: 'Listos para entregar',
    icono: 'notifications_active',
    color: 'positive',
    vacio: 'No hay pedidos esperando entrega.',
    pedidos: pedidos.value.filter((p) => p.estado === 'listo'),
    desde: (p) => p.fecha_listo,
    accion: null,
  },
])

async function cargar() {
  cargando.value = true
  try {
    pedidos.value = await pedidosService.cocina()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

async function avanzar(pedido, accion) {
  procesando.value = pedido.id_pedido
  try {
    const respuesta = await accion.llamada(pedido.id_pedido)
    $q.notify({ type: 'positive', message: respuesta.message })
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    procesando.value = null
    cargar()
  }
}

let intervalo = null
onMounted(() => {
  cargar()
  intervalo = setInterval(cargar, 15000)
})
onBeforeUnmount(() => clearInterval(intervalo))
</script>
