<template>
  <q-page padding>
    <div class="q-mb-md">
      <h1 class="page-titulo">Bitácora de auditoría</h1>
      <p class="page-subtitulo">Registro de inicios de sesión y cambios sobre usuarios y roles.</p>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-12 col-md-4">
          <q-input v-model="filtros.buscar" dense outlined clearable debounce="400" placeholder="Buscar en la descripción">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
          <q-select
            v-model="filtros.tipo_evento"
            :options="tiposEvento"
            :option-label="etiquetaEvento"
            dense
            outlined
            clearable
            label="Tipo de evento"
          />
        </div>
        <div class="col-6 col-sm-3 col-md-2">
          <q-input v-model="filtros.desde" type="date" dense outlined stack-label label="Desde" />
        </div>
        <div class="col-6 col-sm-3 col-md-2">
          <q-input v-model="filtros.hasta" type="date" dense outlined stack-label label="Hasta" />
        </div>
      </q-card-section>
    </q-card>

    <q-table
      v-model:pagination="paginacion"
      class="tabla-siar"
      flat
      bordered
      row-key="id_bitacora"
      :rows="registros"
      :columns="columnas"
      :loading="cargando"
      :rows-per-page-options="[20, 50, 100]"
      no-data-label="No hay eventos con esos filtros."
      rows-per-page-label="Filas por página"
      :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      @request="cargar"
    >
      <template #body-cell-tipo_evento="props">
        <q-td :props="props">
          <q-badge outline :color="colorEvento(props.value)" :label="etiquetaEvento(props.value)" />
        </q-td>
      </template>
      <template #body-cell-descripcion="props">
        <q-td :props="props" style="white-space: normal; min-width: 280px">{{ props.value }}</q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import bitacoraService from '@/services/bitacoraService'
import { formatearFecha, mensajeError } from '@/utils/errores'

const $q = useQuasar()

const registros = ref([])
const tiposEvento = ref([])
const cargando = ref(false)
const filtros = reactive({ buscar: '', tipo_evento: null, desde: '', hasta: '' })
const paginacion = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

const columnas = [
  { name: 'fecha_hora', label: 'Fecha y hora', field: 'fecha_hora', align: 'left', format: formatearFecha },
  { name: 'tipo_evento', label: 'Evento', field: 'tipo_evento', align: 'left' },
  { name: 'descripcion', label: 'Descripción', field: 'descripcion', align: 'left' },
  { name: 'usuario', label: 'Usuario', field: (fila) => fila.usuario?.nombres ?? '—', align: 'left' },
  { name: 'ip_origen', label: 'IP', field: 'ip_origen', align: 'left' },
]

function etiquetaEvento(tipo) {
  if (!tipo) return ''
  const texto = tipo.replace(/_/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

function colorEvento(tipo) {
  if (tipo.includes('fallido') || tipo.includes('no_enviado')) return 'negative'
  if (tipo.includes('eliminado') || tipo.includes('inactivado')) return 'warning'
  if (tipo.includes('sesion')) return 'info'
  return 'primary'
}

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await bitacoraService.listar({
      page,
      por_pagina: rowsPerPage,
      buscar: filtros.buscar || undefined,
      tipo_evento: filtros.tipo_evento || undefined,
      desde: filtros.desde || undefined,
      hasta: filtros.hasta || undefined,
    })
    registros.value = respuesta.data
    tiposEvento.value = respuesta.tipos_evento
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
