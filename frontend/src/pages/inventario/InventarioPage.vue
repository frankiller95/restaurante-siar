<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Inventario</h1>
        <p class="page-subtitulo">Existencias de insumos. Los pedidos enviados a cocina descuentan según las recetas.</p>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn color="primary" icon="add" label="Nuevo insumo" unelevated no-caps @click="abrirFormulario(null)" />
      </div>
    </div>

    <!-- RF40: alertas de stock mínimo -->
    <q-banner v-if="alertas.length" rounded class="bg-orange-1 text-orange-10 q-mb-md">
      <template #avatar><q-icon name="warning" color="warning" /></template>
      <b>{{ alertas.length }} {{ alertas.length === 1 ? 'insumo está' : 'insumos están' }} en su stock mínimo o por debajo:</b>
      {{ alertas.map((a) => `${a.nombre} (${formatearCantidad(a.stock_actual)} ${a.unidad})`).join(', ') }}
      <template #action>
        <q-btn flat no-caps color="warning" :label="filtros.bajo_minimo ? 'Ver todos' : 'Ver solo estos'" @click="filtros.bajo_minimo = !filtros.bajo_minimo" />
      </template>
    </q-banner>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-5">
          <q-input v-model="filtros.buscar" dense outlined clearable debounce="400" placeholder="Buscar insumo">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-6 col-md-3">
          <q-select v-model="filtros.estado" :options="opcionesEstado" emit-value map-options dense outlined label="Mostrar" />
        </div>
        <div class="col-6 col-md-auto">
          <q-toggle v-model="filtros.bajo_minimo" label="Solo stock bajo" color="warning" />
        </div>
        <div class="col-12 col-md text-right text-body2 text-grey-8">
          Valor (esta página): <b>{{ formatearPrecio(valorTotal) }}</b>
        </div>
      </q-card-section>
    </q-card>

    <q-table
      v-model:pagination="paginacion"
      class="tabla-siar"
      flat
      bordered
      row-key="id_insumo"
      :rows="insumos"
      :columns="columnas"
      :loading="cargando"
      :rows-per-page-options="[20, 50, 100]"
      no-data-label="No hay insumos con esos filtros."
      rows-per-page-label="Filas por página"
      :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      @request="cargar"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ props.row.nombre }}</div>
          <div class="text-caption text-grey-7">
            {{ props.row.recetas_count ? `En ${props.row.recetas_count} receta(s)` : 'Sin recetas' }}
            <q-badge v-if="!props.row.activo" color="grey" label="Dado de baja" class="q-ml-xs" />
          </div>
        </q-td>
      </template>

      <template #body-cell-stock="props">
        <q-td :props="props">
          <span class="text-weight-bold" :class="{ 'text-negative': props.row.bajo_minimo }">
            {{ formatearCantidad(props.row.stock_actual) }} {{ props.row.unidad }}
          </span>
          <q-icon v-if="props.row.bajo_minimo" name="warning" color="warning" class="q-ml-xs">
            <q-tooltip>En su stock mínimo o por debajo</q-tooltip>
          </q-icon>
          <div class="text-caption text-grey-7">mín. {{ formatearCantidad(props.row.stock_minimo) }} {{ props.row.unidad }}</div>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td :props="props" class="text-no-wrap">
          <template v-if="props.row.activo">
            <q-btn flat round dense icon="add_circle" color="positive" aria-label="Entrada" @click="abrirMovimiento(props.row, 'entrada')">
              <q-tooltip>Entrada (compra)</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="delete_sweep" color="negative" aria-label="Merma" @click="abrirMovimiento(props.row, 'merma')">
              <q-tooltip>Merma</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="tune" color="warning" aria-label="Ajuste" @click="abrirMovimiento(props.row, 'ajuste')">
              <q-tooltip>Ajuste por conteo</q-tooltip>
            </q-btn>
          </template>
          <q-btn flat round dense icon="history" color="primary" aria-label="Kárdex" :to="{ name: 'kardex', params: { id: props.row.id_insumo } }">
            <q-tooltip>Kárdex</q-tooltip>
          </q-btn>
          <template v-if="props.row.activo">
            <q-btn flat round dense icon="edit" aria-label="Editar" @click="abrirFormulario(props.row)">
              <q-tooltip>Editar</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="archive" color="grey-7" aria-label="Desactivar" @click="eliminar(props.row)">
              <q-tooltip>Desactivar</q-tooltip>
            </q-btn>
          </template>
        </q-td>
      </template>
    </q-table>

    <InsumoFormDialog v-model="formularioAbierto" :insumo="insumoSeleccionado" @guardado="recargarTodo" />
    <MovimientoDialog v-model="movimientoAbierto" :insumo="insumoSeleccionado" :modo="modoMovimiento" @guardado="recargarTodo" />
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import inventarioService from '@/services/inventarioService'
import InsumoFormDialog from '@/components/inventario/InsumoFormDialog.vue'
import MovimientoDialog from '@/components/inventario/MovimientoDialog.vue'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { formatearCantidad } from '@/utils/inventario'

const $q = useQuasar()

const insumos = ref([])
const alertas = ref([])
const cargando = ref(false)
const filtros = reactive({ buscar: '', estado: 'activos', bajo_minimo: false })
const paginacion = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

const formularioAbierto = ref(false)
const movimientoAbierto = ref(false)
const insumoSeleccionado = ref(null)
const modoMovimiento = ref('entrada')

const opcionesEstado = [
  { label: 'Activos', value: 'activos' },
  { label: 'Dados de baja', value: 'baja' },
  { label: 'Todos', value: 'todos' },
]

const columnas = [
  { name: 'nombre', label: 'Insumo', field: 'nombre', align: 'left' },
  { name: 'stock', label: 'Stock', field: 'stock_actual', align: 'left' },
  { name: 'costo', label: 'Costo unitario', field: (f) => `${formatearPrecio(f.costo_unitario)} / ${f.unidad}`, align: 'right' },
  { name: 'valor', label: 'Valor', field: 'valor_inventario', align: 'right', format: formatearPrecio },
  { name: 'acciones', label: '', field: 'id_insumo', align: 'right' },
]

const valorTotal = computed(() => insumos.value.reduce((suma, i) => suma + i.valor_inventario, 0))

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await inventarioService.listar({
      page,
      por_pagina: rowsPerPage,
      buscar: filtros.buscar || undefined,
      estado: filtros.estado,
      bajo_minimo: filtros.bajo_minimo ? 1 : undefined,
    })
    insumos.value = respuesta.data
    paginacion.value = { ...paginacion.value, page, rowsPerPage, rowsNumber: respuesta.meta.total }
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

async function cargarAlertas() {
  try {
    alertas.value = await inventarioService.alertas()
  } catch {
    alertas.value = []
  }
}

function recargarTodo() {
  cargar({ pagination: paginacion.value })
  cargarAlertas()
}

watch(filtros, () => cargar({ pagination: { ...paginacion.value, page: 1 } }))

function abrirFormulario(insumo) {
  insumoSeleccionado.value = insumo
  formularioAbierto.value = true
}

function abrirMovimiento(insumo, modo) {
  insumoSeleccionado.value = insumo
  modoMovimiento.value = modo
  movimientoAbierto.value = true
}

/** RF24: con historial se desactiva; sin historial se borra. */
function eliminar(insumo) {
  $q.dialog({
    title: 'Desactivar insumo',
    message: `"${insumo.nombre}" dejará de aparecer en el inventario. Si tiene movimientos, se conserva su kárdex. ¿Continuar?`,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Desactivar' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await inventarioService.eliminar(insumo.id_insumo)
      $q.notify({ type: 'positive', message: respuesta.message })
      recargarTodo()
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

onMounted(recargarTodo)
</script>
