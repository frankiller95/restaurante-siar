<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Facturación</h1>
        <p class="page-subtitulo">Cobra los pedidos entregados y consulta las facturas emitidas.</p>
      </div>
    </div>

    <q-tabs v-model="pestana" dense align="left" active-color="primary" indicator-color="primary" class="q-mb-md" no-caps>
      <q-tab name="por-cobrar" icon="point_of_sale">
        <template #default>
          <span class="q-ml-xs">Por cobrar</span>
          <q-badge v-if="porCobrar.length" color="warning" text-color="black" :label="porCobrar.length" class="q-ml-sm" />
        </template>
      </q-tab>
      <q-tab name="facturas" icon="receipt_long" label="Facturas" />
    </q-tabs>

    <q-tab-panels v-model="pestana" animated keep-alive class="bg-transparent">
      <!-- RF28: pedidos entregados esperando factura -->
      <q-tab-panel name="por-cobrar" class="q-pa-none">
        <div v-if="cargandoPorCobrar && porCobrar.length === 0" class="row justify-center q-pa-xl">
          <q-spinner color="primary" size="40px" />
        </div>
        <q-banner v-else-if="porCobrar.length === 0" rounded class="bg-grey-2 text-grey-8">
          No hay pedidos entregados esperando factura. Se actualiza cada 20 segundos.
        </q-banner>

        <div class="row q-col-gutter-md">
          <div v-for="pedido in porCobrar" :key="pedido.id_pedido" class="col-12 col-sm-6 col-md-4 col-lg-3">
            <q-card flat bordered class="full-height column">
              <q-card-section class="col">
                <div class="row items-center no-wrap">
                  <div class="col">
                    <div class="text-caption text-grey-7">Pedido #{{ pedido.id_pedido }}</div>
                    <div class="text-h6">{{ lugarPedido(pedido) }}</div>
                  </div>
                  <q-badge color="info" label="Entregado" />
                </div>
                <div class="text-caption text-grey-7 q-mt-xs">
                  {{ pedido.cliente_nombre || pedido.mesero || 'Sin mesero' }} · entregado {{ haceCuanto(pedido.fecha_entrega) }}
                </div>
                <q-list dense class="q-mt-sm">
                  <q-item v-for="linea in pedido.detalles.slice(0, 4)" :key="linea.id_detalle" class="q-px-none" style="min-height: 24px">
                    <q-item-section><span><b>{{ linea.cantidad }}×</b> {{ linea.producto }}</span></q-item-section>
                  </q-item>
                  <div v-if="pedido.detalles.length > 4" class="text-caption text-grey-7">y {{ pedido.detalles.length - 4 }} más...</div>
                </q-list>
              </q-card-section>
              <q-separator />
              <q-card-section class="row items-center q-py-sm">
                <div class="col text-subtitle1 text-weight-bold text-primary">{{ formatearPrecio(pedido.total) }}</div>
                <q-btn color="primary" unelevated no-caps dense icon="receipt_long" label="Facturar" class="q-px-sm" @click="abrirFacturar(pedido)" />
              </q-card-section>
            </q-card>
          </div>
        </div>
      </q-tab-panel>

      <!-- Facturas emitidas -->
      <q-tab-panel name="facturas" class="q-pa-none">
        <q-card flat bordered class="q-mb-md">
          <q-card-section class="row q-col-gutter-sm items-center">
            <div class="col-12 col-md-4">
              <q-input v-model="filtros.buscar" dense outlined clearable debounce="400" placeholder="Número, cliente o documento">
                <template #prepend><q-icon name="search" /></template>
              </q-input>
            </div>
            <div class="col-12 col-sm-4 col-md-2">
              <q-select v-model="filtros.estado" :options="opcionesEstado" emit-value map-options dense outlined label="Estado" />
            </div>
            <div class="col-6 col-sm-4 col-md-2">
              <q-input v-model="filtros.desde" type="date" dense outlined stack-label label="Desde" clearable />
            </div>
            <div class="col-6 col-sm-4 col-md-2">
              <q-input v-model="filtros.hasta" type="date" dense outlined stack-label label="Hasta" clearable />
            </div>
          </q-card-section>
        </q-card>

        <q-table
          v-model:pagination="paginacion"
          class="tabla-siar"
          flat
          bordered
          row-key="id_factura"
          :rows="facturas"
          :columns="columnas"
          :loading="cargando"
          :rows-per-page-options="[20, 50, 100]"
          no-data-label="No hay facturas con esos filtros."
          rows-per-page-label="Filas por página"
          :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
          @request="cargar"
        >
          <template #body-cell-numero="props">
            <q-td :props="props">
              <router-link :to="{ name: 'factura', params: { id: props.row.id_factura } }" class="text-primary text-weight-medium">
                {{ props.row.numero }}
              </router-link>
              <div class="text-caption text-grey-7">Pedido #{{ props.row.id_pedido }} · {{ lugarPedido(props.row.pedido) }}</div>
            </q-td>
          </template>
          <template #body-cell-estado="props">
            <q-td :props="props">
              <q-badge :color="ESTADOS_FACTURA[props.row.estado].color" :label="props.row.estado_etiqueta" />
              <div v-if="props.row.estado === 'pendiente' && props.row.total_pagado > 0" class="text-caption text-grey-7">
                Saldo {{ formatearPrecio(props.row.saldo) }}
              </div>
            </q-td>
          </template>
          <template #body-cell-acciones="props">
            <q-td :props="props">
              <q-btn flat round dense icon="visibility" color="primary" aria-label="Ver factura" :to="{ name: 'factura', params: { id: props.row.id_factura } }">
                <q-tooltip>Ver factura</q-tooltip>
              </q-btn>
            </q-td>
          </template>
        </q-table>
      </q-tab-panel>
    </q-tab-panels>

    <FacturaFormDialog v-model="facturarAbierto" :pedido="pedidoSeleccionado" @generada="irAFactura" />
  </q-page>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import facturasService from '@/services/facturasService'
import FacturaFormDialog from '@/components/facturacion/FacturaFormDialog.vue'
import { formatearFecha, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { ESTADOS_FACTURA, lugarPedido } from '@/utils/facturacion'
import { haceCuanto } from '@/utils/pedidos'

const $q = useQuasar()
const router = useRouter()

const pestana = ref('por-cobrar')

// --- Por cobrar ---
const porCobrar = ref([])
const cargandoPorCobrar = ref(false)
const facturarAbierto = ref(false)
const pedidoSeleccionado = ref(null)

async function cargarPorCobrar() {
  cargandoPorCobrar.value = true
  try {
    porCobrar.value = await facturasService.porCobrar()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargandoPorCobrar.value = false
  }
}

function abrirFacturar(pedido) {
  pedidoSeleccionado.value = pedido
  facturarAbierto.value = true
}

/** Con la factura generada se pasa directo a cobrarla. */
function irAFactura(factura) {
  router.push({ name: 'factura', params: { id: factura.id_factura } })
}

// --- Facturas emitidas ---
const facturas = ref([])
const cargando = ref(false)
const filtros = reactive({ buscar: '', estado: null, desde: '', hasta: '' })
const paginacion = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

const opcionesEstado = [
  { label: 'Todas', value: null },
  ...Object.entries(ESTADOS_FACTURA).map(([value, info]) => ({ label: info.etiqueta, value })),
]

const columnas = [
  { name: 'numero', label: 'Factura', field: 'numero', align: 'left' },
  { name: 'cliente', label: 'Cliente', field: 'cliente_nombre', align: 'left' },
  { name: 'fecha', label: 'Emitida', field: 'fecha_emision', align: 'left', format: formatearFecha },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'total', label: 'Total', field: 'total', align: 'right', format: formatearPrecio },
  { name: 'acciones', label: '', field: 'id_factura', align: 'right' },
]

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await facturasService.listar({
      page,
      por_pagina: rowsPerPage,
      buscar: filtros.buscar || undefined,
      estado: filtros.estado || undefined,
      desde: filtros.desde || undefined,
      hasta: filtros.hasta || undefined,
    })
    facturas.value = respuesta.data
    paginacion.value = { ...paginacion.value, page, rowsPerPage, rowsNumber: respuesta.meta.total }
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

watch(filtros, () => cargar({ pagination: { ...paginacion.value, page: 1 } }))

let intervalo = null
onMounted(() => {
  cargarPorCobrar()
  cargar()
  intervalo = setInterval(() => {
    if (!facturarAbierto.value) cargarPorCobrar()
  }, 20000)
})
onBeforeUnmount(() => clearInterval(intervalo))
</script>
