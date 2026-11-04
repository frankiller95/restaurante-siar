<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Reservas</h1>
        <p class="page-subtitulo">
          La mesa se aparta 1 hora antes. Si el cliente no llega 20 minutos después de la hora, la reserva vence sola.
        </p>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn color="primary" icon="event" label="Nueva reserva" unelevated no-caps @click="reservaAbierta = true" />
      </div>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-auto row items-center no-wrap q-gutter-xs">
          <q-btn flat round dense icon="chevron_left" aria-label="Día anterior" @click="moverDia(-1)" />
          <q-input v-model="filtros.fecha" type="date" dense outlined stack-label label="Fecha" style="min-width: 160px" />
          <q-btn flat round dense icon="chevron_right" aria-label="Día siguiente" @click="moverDia(1)" />
          <q-btn flat dense no-caps label="Hoy" color="primary" @click="filtros.fecha = hoy()" />
        </div>
        <div class="col-6 col-md-3">
          <q-select
            v-model="filtros.estado"
            :options="opcionesEstado"
            emit-value
            map-options
            dense
            outlined
            clearable
            label="Estado"
          />
        </div>
        <div class="col-6 col-md">
          <q-input v-model="filtros.buscar" dense outlined clearable debounce="400" placeholder="Cliente o teléfono">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
      </q-card-section>
    </q-card>

    <q-table
      v-model:pagination="paginacion"
      class="tabla-siar"
      flat
      bordered
      row-key="id_reserva"
      :rows="reservas"
      :columns="columnas"
      :loading="cargando"
      :rows-per-page-options="[20, 50, 100]"
      no-data-label="No hay reservas con esos filtros."
      rows-per-page-label="Filas por página"
      :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      @request="cargar"
    >
      <template #body-cell-hora="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ formatearHora(props.row.hora) }}</div>
          <div class="text-caption text-grey-7">{{ formatearDia(props.row.fecha) }}</div>
        </q-td>
      </template>

      <template #body-cell-cliente="props">
        <q-td :props="props" style="white-space: normal">
          <div class="text-weight-medium">{{ props.row.cliente_nombre }}</div>
          <div class="text-caption text-grey-7">
            <q-icon name="phone" /> {{ props.row.cliente_telefono }}
            <span v-if="props.row.observaciones"> · {{ props.row.observaciones }}</span>
          </div>
        </q-td>
      </template>

      <template #body-cell-mesa="props">
        <q-td :props="props">
          Mesa {{ props.row.mesa?.numero }}
          <div v-if="props.row.mesa?.ubicacion" class="text-caption text-grey-7">{{ props.row.mesa.ubicacion }}</div>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <q-badge :color="ESTADOS_RESERVA[props.row.estado].color" :label="props.row.estado_etiqueta" />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td :props="props" class="text-no-wrap">
          <template v-if="props.row.estado === 'pendiente'">
            <q-btn dense unelevated no-caps size="sm" color="positive" icon="how_to_reg" label="Llegó" @click="registrarLlegada(props.row)" />
            <q-btn flat round dense icon="event_busy" color="negative" aria-label="Cancelar reserva" class="q-ml-xs" @click="cancelar(props.row)">
              <q-tooltip>Cancelar reserva</q-tooltip>
            </q-btn>
          </template>
        </q-td>
      </template>
    </q-table>

    <ReservaFormDialog v-model="reservaAbierta" :fecha-inicial="filtros.fecha" @guardado="recargar" />
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import mesasService from '@/services/mesasService'
import ReservaFormDialog from '@/components/mesas/ReservaFormDialog.vue'
import { mensajeError } from '@/utils/errores'
import { ESTADOS_RESERVA, formatearDia, formatearHora, hoy } from '@/utils/mesas'

const $q = useQuasar()

const reservas = ref([])
const cargando = ref(false)
const reservaAbierta = ref(false)
const filtros = reactive({ fecha: hoy(), estado: null, buscar: '' })
const paginacion = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

const opcionesEstado = Object.entries(ESTADOS_RESERVA).map(([value, info]) => ({ value, label: info.etiqueta }))

const columnas = [
  { name: 'hora', label: 'Hora', field: 'hora', align: 'left' },
  { name: 'cliente', label: 'Cliente', field: 'cliente_nombre', align: 'left' },
  { name: 'personas', label: 'Personas', field: 'cantidad_personas', align: 'center' },
  { name: 'mesa', label: 'Mesa', field: (fila) => fila.mesa?.numero, align: 'left' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'registrada_por', label: 'Registró', field: 'registrada_por', align: 'left' },
  { name: 'acciones', label: '', field: 'id_reserva', align: 'right' },
]

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await mesasService.reservas({
      page,
      por_pagina: rowsPerPage,
      fecha: filtros.fecha || undefined,
      estado: filtros.estado || undefined,
      buscar: filtros.buscar || undefined,
    })
    reservas.value = respuesta.data
    paginacion.value = { ...paginacion.value, page, rowsPerPage, rowsNumber: respuesta.meta.total }
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

function recargar() {
  cargar({ pagination: paginacion.value })
}

watch(filtros, () => cargar({ pagination: { ...paginacion.value, page: 1 } }))

function moverDia(dias) {
  const [anio, mes, dia] = (filtros.fecha || hoy()).split('-').map(Number)
  const fecha = new Date(anio, mes - 1, dia + dias)
  filtros.fecha = `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`
}

async function registrarLlegada(reserva) {
  try {
    const respuesta = await mesasService.registrarLlegada(reserva.id_reserva)
    $q.notify({ type: 'positive', message: respuesta.message })
    recargar()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
    recargar()
  }
}

function cancelar(reserva) {
  $q.dialog({
    title: 'Cancelar reserva',
    message: `¿Cancelar la reserva de ${reserva.cliente_nombre} (${formatearHora(reserva.hora)}, mesa ${reserva.mesa?.numero})?`,
    cancel: { flat: true, noCaps: true, label: 'Volver' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Cancelar reserva' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await mesasService.cancelarReserva(reserva.id_reserva)
      $q.notify({ type: 'positive', message: respuesta.message })
      recargar()
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

onMounted(recargar)
</script>
