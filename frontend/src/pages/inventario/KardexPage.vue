<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-auto">
        <q-btn flat round icon="arrow_back" aria-label="Volver" :to="{ name: 'inventario' }" />
      </div>
      <div class="col">
        <h1 class="page-titulo">Kárdex · {{ insumo?.nombre ?? '...' }}</h1>
        <p v-if="insumo" class="page-subtitulo">
          Stock actual:
          <b :class="{ 'text-negative': insumo.bajo_minimo }">{{ formatearCantidad(insumo.stock_actual) }} {{ insumo.unidad }}</b>
          · mínimo {{ formatearCantidad(insumo.stock_minimo) }} {{ insumo.unidad }}
          · costo {{ formatearPrecio(insumo.costo_unitario) }} / {{ insumo.unidad }}
        </p>
      </div>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-12 col-sm-4">
          <q-select
            v-model="filtros.tipo"
            :options="opcionesTipo"
            emit-value
            map-options
            dense
            outlined
            clearable
            label="Tipo de movimiento"
          />
        </div>
        <div class="col-6 col-sm-4">
          <q-input v-model="filtros.desde" type="date" dense outlined stack-label label="Desde" clearable />
        </div>
        <div class="col-6 col-sm-4">
          <q-input v-model="filtros.hasta" type="date" dense outlined stack-label label="Hasta" clearable />
        </div>
      </q-card-section>
    </q-card>

    <q-table
      v-model:pagination="paginacion"
      class="tabla-siar"
      flat
      bordered
      row-key="id_movimiento"
      :rows="movimientos"
      :columns="columnas"
      :loading="cargando"
      :rows-per-page-options="[30, 50, 100]"
      no-data-label="No hay movimientos con esos filtros."
      rows-per-page-label="Filas por página"
      :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      @request="cargar"
    >
      <template #body-cell-tipo="props">
        <q-td :props="props">
          <q-badge outline :color="TIPOS_MOVIMIENTO[props.row.tipo].color">
            <q-icon :name="TIPOS_MOVIMIENTO[props.row.tipo].icono" size="14px" class="q-mr-xs" />
            {{ props.row.tipo_etiqueta }}
          </q-badge>
        </q-td>
      </template>

      <template #body-cell-cantidad="props">
        <q-td :props="props" class="text-weight-bold" :class="props.row.cantidad < 0 ? 'text-negative' : 'text-positive'">
          {{ props.row.cantidad > 0 ? '+' : '' }}{{ formatearCantidad(props.row.cantidad) }}
        </q-td>
      </template>

      <template #body-cell-detalle="props">
        <q-td :props="props" style="white-space: normal; min-width: 200px">
          <span v-if="props.row.proveedor">Proveedor: {{ props.row.proveedor }}</span>
          <span v-if="props.row.costo_total"> · {{ formatearPrecio(props.row.costo_total) }}</span>
          <span v-if="props.row.motivo">{{ props.row.motivo }}</span>
          <router-link v-if="props.row.id_pedido" :to="{ name: 'pedido', params: { id: props.row.id_pedido } }" class="q-ml-xs text-primary">
            ver pedido
          </router-link>
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import inventarioService from '@/services/inventarioService'
import { formatearFecha, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { formatearCantidad, TIPOS_MOVIMIENTO } from '@/utils/inventario'

const $q = useQuasar()
const route = useRoute()

const insumo = ref(null)
const movimientos = ref([])
const cargando = ref(false)
const filtros = reactive({ tipo: null, desde: '', hasta: '' })
const paginacion = ref({ page: 1, rowsPerPage: 30, rowsNumber: 0 })

const opcionesTipo = Object.entries(TIPOS_MOVIMIENTO).map(([value, info]) => ({ value, label: info.etiqueta }))

const columnas = [
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', format: formatearFecha },
  { name: 'tipo', label: 'Movimiento', field: 'tipo', align: 'left' },
  { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'right' },
  { name: 'saldo', label: 'Saldo', field: (f) => formatearCantidad(f.stock_resultante), align: 'right' },
  { name: 'detalle', label: 'Detalle', field: 'motivo', align: 'left' },
  { name: 'usuario', label: 'Registró', field: (f) => f.usuario ?? 'Sistema', align: 'left' },
]

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await inventarioService.kardex(route.params.id, {
      page,
      por_pagina: rowsPerPage,
      tipo: filtros.tipo || undefined,
      desde: filtros.desde || undefined,
      hasta: filtros.hasta || undefined,
    })
    movimientos.value = respuesta.data
    insumo.value = respuesta.insumo
    paginacion.value = { ...paginacion.value, page, rowsPerPage, rowsNumber: respuesta.meta.total }
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

watch(filtros, () => cargar({ pagination: { ...paginacion.value, page: 1 } }))

onMounted(() => cargar())
</script>
