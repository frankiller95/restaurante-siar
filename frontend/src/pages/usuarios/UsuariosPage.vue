<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Usuarios</h1>
        <p class="page-subtitulo">Cuentas de administradores, cajeros, meseros y cocineros.</p>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn color="primary" icon="person_add" label="Nuevo usuario" unelevated no-caps @click="abrirFormulario(null)" />
      </div>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-12 col-md-6">
          <q-input v-model="filtros.buscar" dense outlined clearable debounce="400" placeholder="Buscar por nombre, documento o correo">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-6 col-md-3">
          <q-select
            v-model="filtros.id_rol"
            :options="roles"
            option-value="id_rol"
            option-label="nombre_rol"
            emit-value
            map-options
            dense
            outlined
            clearable
            label="Rol"
          />
        </div>
        <div class="col-6 col-md-3">
          <q-select v-model="filtros.estado" :options="opcionesEstado" emit-value map-options dense outlined clearable label="Estado" />
        </div>
      </q-card-section>
    </q-card>

    <q-table
      v-model:pagination="paginacion"
      class="tabla-siar"
      flat
      bordered
      row-key="id_usuario"
      :rows="usuarios"
      :columns="columnas"
      :loading="cargando"
      :rows-per-page-options="[10, 15, 25, 50]"
      no-data-label="No hay usuarios que coincidan con la búsqueda."
      loading-label="Cargando usuarios..."
      rows-per-page-label="Filas por página"
      :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      @request="cargar"
    >
      <template #body-cell-nombres="props">
        <q-td :props="props">
          <div class="text-weight-medium">{{ props.row.nombres }}</div>
          <div class="text-caption text-grey-7">{{ props.row.correo }}</div>
        </q-td>
      </template>

      <template #body-cell-rol="props">
        <q-td :props="props">
          <q-badge :color="props.row.rol?.es_predefinido ? 'primary' : 'secondary'" :label="props.row.rol?.nombre_rol" />
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <q-chip
            dense
            square
            :color="props.row.estado === 'activo' ? 'green-1' : 'grey-3'"
            :text-color="props.row.estado === 'activo' ? 'positive' : 'grey-8'"
            :icon="props.row.estado === 'activo' ? 'check_circle' : 'block'"
            :label="props.row.estado_etiqueta"
          />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td :props="props" class="text-no-wrap">
          <q-btn flat round dense icon="edit" color="primary" aria-label="Editar" @click="abrirFormulario(props.row)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="forward_to_inbox"
            color="secondary"
            aria-label="Reenviar credenciales"
            :disable="props.row.estado !== 'activo'"
            @click="reenviar(props.row)"
          >
            <q-tooltip>Reenviar credenciales</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="delete"
            color="negative"
            aria-label="Eliminar"
            :disable="props.row.id_usuario === auth.usuario?.id_usuario"
            @click="eliminar(props.row)"
          >
            <q-tooltip>{{ props.row.id_usuario === auth.usuario?.id_usuario ? 'No puedes eliminar tu cuenta' : 'Eliminar' }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <UsuarioFormDialog v-model="formularioAbierto" :usuario="usuarioSeleccionado" :roles="roles" @guardado="recargar" />
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import usuariosService from '@/services/usuariosService'
import rolesService from '@/services/rolesService'
import UsuarioFormDialog from '@/components/usuarios/UsuarioFormDialog.vue'
import { formatearFecha, mensajeError } from '@/utils/errores'

const $q = useQuasar()
const auth = useAuthStore()

const usuarios = ref([])
const roles = ref([])
const cargando = ref(false)
const formularioAbierto = ref(false)
const usuarioSeleccionado = ref(null)

const filtros = reactive({ buscar: '', id_rol: null, estado: null })
const paginacion = ref({ page: 1, rowsPerPage: 15, rowsNumber: 0 })

const opcionesEstado = [
  { label: 'Activo', value: 'activo' },
  { label: 'Inactivo', value: 'inactivo' },
]

const columnas = [
  { name: 'nombres', label: 'Usuario', field: 'nombres', align: 'left' },
  { name: 'documento', label: 'Documento', field: 'documento', align: 'left' },
  { name: 'rol', label: 'Rol', field: (fila) => fila.rol?.nombre_rol, align: 'left' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'fecha_creacion', label: 'Creado', field: 'fecha_creacion', align: 'left', format: formatearFecha },
  { name: 'acciones', label: 'Acciones', field: 'id_usuario', align: 'right' },
]

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await usuariosService.listar({
      page,
      por_pagina: rowsPerPage,
      buscar: filtros.buscar || undefined,
      id_rol: filtros.id_rol || undefined,
      estado: filtros.estado || undefined,
    })
    usuarios.value = respuesta.data
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

function abrirFormulario(usuario) {
  usuarioSeleccionado.value = usuario
  formularioAbierto.value = true
}

/** CU-03: confirma antes de eliminar. */
function eliminar(usuario) {
  $q.dialog({
    title: 'Eliminar usuario',
    message: `¿Seguro que quieres eliminar a <strong>${escapar(usuario.nombres)}</strong>? Si tiene pedidos, facturas o reservas se inactivará en lugar de borrarse.`,
    html: true,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Eliminar' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await usuariosService.eliminar(usuario.id_usuario)
      $q.notify({ type: respuesta.tipo_eliminacion === 'logica' ? 'info' : 'positive', message: respuesta.message })
      recargar()
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

function reenviar(usuario) {
  $q.dialog({
    title: 'Reenviar credenciales',
    message: `Se generará una nueva contraseña temporal para ${usuario.nombres} y se enviará a ${usuario.correo}. Sus sesiones abiertas se cerrarán.`,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'primary', unelevated: true, noCaps: true, label: 'Reenviar' },
  }).onOk(async () => {
    try {
      const respuesta = await usuariosService.reenviarCredenciales(usuario.id_usuario)
      $q.notify({ type: 'positive', message: respuesta.message })
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

function escapar(texto) {
  const div = document.createElement('div')
  div.textContent = texto
  return div.innerHTML
}

onMounted(async () => {
  try {
    roles.value = await rolesService.listar()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  }
  recargar()
})
</script>
