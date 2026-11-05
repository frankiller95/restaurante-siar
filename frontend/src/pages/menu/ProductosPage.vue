<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Productos del menú</h1>
        <p class="page-subtitulo">
          {{ puedeEditar ? 'Platos y bebidas: crea, edita, clasifica y da de baja.' : 'Marca los productos que se agotaron en cocina.' }}
        </p>
      </div>
      <div v-if="puedeEditar" class="col-12 col-sm-auto">
        <q-btn color="primary" icon="add" label="Nuevo producto" unelevated no-caps @click="abrirFormulario(null)" />
      </div>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-12 col-md-4">
          <q-input v-model="filtros.buscar" dense outlined clearable debounce="400" placeholder="Buscar por nombre o descripción">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-6 col-md-3">
          <q-select
            v-model="filtros.id_categoria"
            :options="categorias"
            option-value="id_categoria"
            option-label="nombre"
            emit-value
            map-options
            dense
            outlined
            clearable
            label="Categoría"
          />
        </div>
        <div class="col-6 col-md-3">
          <q-select v-model="filtros.disponible" :options="opcionesDisponible" emit-value map-options dense outlined clearable label="Disponibilidad" />
        </div>
        <div v-if="puedeEditar" class="col-12 col-md-2">
          <q-select v-model="filtros.estado" :options="opcionesEstado" emit-value map-options dense outlined label="Mostrar" />
        </div>
      </q-card-section>
    </q-card>

    <q-table
      v-model:pagination="paginacion"
      class="tabla-siar"
      flat
      bordered
      row-key="id_producto"
      :rows="productos"
      :columns="columnas"
      :loading="cargando"
      :rows-per-page-options="[10, 15, 25, 50]"
      no-data-label="No hay productos con esos filtros."
      loading-label="Cargando productos..."
      rows-per-page-label="Filas por página"
      :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      @request="cargar"
    >
      <template #body-cell-imagen="props">
        <q-td :props="props" style="width: 72px">
          <q-img
            v-if="props.row.imagen_url"
            :src="props.row.imagen_url"
            ratio="1"
            fit="cover"
            style="width: 48px; border-radius: 6px"
            :alt="props.row.nombre"
          />
          <q-avatar v-else size="48px" rounded color="grey-3" text-color="grey-6" icon="restaurant" />
        </q-td>
      </template>

      <template #body-cell-nombre="props">
        <q-td :props="props" style="white-space: normal; min-width: 200px">
          <div class="text-weight-medium">{{ props.row.nombre }}</div>
          <div v-if="props.row.descripcion" class="text-caption text-grey-7 ellipsis-2-lines">{{ props.row.descripcion }}</div>
        </q-td>
      </template>

      <template #body-cell-categoria="props">
        <q-td :props="props">
          <q-badge outline :color="props.row.categoria?.activa ? 'secondary' : 'grey'" :label="props.row.categoria?.nombre" />
        </q-td>
      </template>

      <template #body-cell-disponible="props">
        <q-td :props="props">
          <q-chip v-if="!props.row.activo" dense square color="grey-3" text-color="grey-8" icon="archive" label="Dado de baja" />
          <q-toggle
            v-else-if="puedeCambiarDisponibilidad"
            :model-value="props.row.disponible"
            :label="props.row.disponible ? 'Disponible' : 'Agotado'"
            color="positive"
            :disable="cambiando === props.row.id_producto"
            @update:model-value="(valor) => cambiarDisponibilidad(props.row, valor)"
          />
          <q-chip
            v-else
            dense
            square
            :color="props.row.disponible ? 'green-1' : 'red-1'"
            :text-color="props.row.disponible ? 'positive' : 'negative'"
            :label="props.row.disponible ? 'Disponible' : 'Agotado'"
          />
          <!-- RF40: lo agotó el inventario; vuelve solo al reponer insumos -->
          <div v-if="props.row.activo && props.row.agotado_por_inventario" class="text-caption text-warning">
            <q-icon name="inventory_2" /> Sin insumos
            <q-tooltip>El inventario no alcanza para una porción. Vuelve a estar disponible al registrar una entrada.</q-tooltip>
          </div>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td :props="props" class="text-no-wrap">
          <!-- RF38: receta del producto (Módulo 5) -->
          <q-btn
            v-if="puedeVerReceta && props.row.activo"
            flat
            round
            dense
            icon="menu_book"
            color="brown"
            aria-label="Receta"
            @click="abrirReceta(props.row)"
          >
            <q-tooltip>Receta (insumos por porción)</q-tooltip>
          </q-btn>
          <template v-if="puedeEditar && props.row.activo">
            <q-btn flat round dense icon="edit" color="primary" aria-label="Editar" @click="abrirFormulario(props.row)">
              <q-tooltip>Editar</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="drive_file_move" color="secondary" aria-label="Cambiar categoría">
              <q-tooltip>Cambiar categoría</q-tooltip>
              <q-menu auto-close>
                <q-list dense style="min-width: 180px">
                  <q-item-label header>Mover a...</q-item-label>
                  <q-item
                    v-for="categoria in categoriasActivas"
                    :key="categoria.id_categoria"
                    clickable
                    :active="categoria.id_categoria === props.row.id_categoria"
                    @click="cambiarCategoria(props.row, categoria)"
                  >
                    <q-item-section>{{ categoria.nombre }}</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
            <q-btn flat round dense icon="delete" color="negative" aria-label="Eliminar" @click="eliminar(props.row)">
              <q-tooltip>Dar de baja</q-tooltip>
            </q-btn>
          </template>
        </q-td>
      </template>
    </q-table>

    <ProductoFormDialog
      v-model="formularioAbierto"
      :producto="productoSeleccionado"
      :categorias="categoriasActivas"
      @guardado="recargar"
      @categoria-creada="cargarCategorias"
    />
    <RecetaDialog v-model="recetaAbierta" :producto="productoReceta" @guardado="recargar" />
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import menuService from '@/services/menuService'
import ProductoFormDialog from '@/components/menu/ProductoFormDialog.vue'
import RecetaDialog from '@/components/menu/RecetaDialog.vue'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'

const $q = useQuasar()
const auth = useAuthStore()

const puedeEditar = computed(() => auth.tienePermiso('gestionar_menu'))
const puedeCambiarDisponibilidad = computed(() => auth.tienePermiso('gestionar_disponibilidad'))

const productos = ref([])
const categorias = ref([])
const cargando = ref(false)
const cambiando = ref(null)
const formularioAbierto = ref(false)
const productoSeleccionado = ref(null)

// RF38: receta (quien gestiona inventario o el menú).
const puedeVerReceta = computed(() => auth.tienePermiso('gestionar_inventario') || auth.tienePermiso('gestionar_menu'))
const recetaAbierta = ref(false)
const productoReceta = ref(null)

function abrirReceta(producto) {
  productoReceta.value = producto
  recetaAbierta.value = true
}

const filtros = reactive({ buscar: '', id_categoria: null, disponible: null, estado: 'activos' })
const paginacion = ref({ page: 1, rowsPerPage: 15, rowsNumber: 0 })

const categoriasActivas = computed(() => categorias.value.filter((c) => c.activa))

const opcionesDisponible = [
  { label: 'Disponibles', value: 1 },
  { label: 'Agotados', value: 0 },
]
const opcionesEstado = [
  { label: 'Activos', value: 'activos' },
  { label: 'Dados de baja', value: 'baja' },
  { label: 'Todos', value: 'todos' },
]

const columnas = [
  { name: 'imagen', label: '', field: 'imagen_url', align: 'left' },
  { name: 'nombre', label: 'Producto', field: 'nombre', align: 'left' },
  { name: 'categoria', label: 'Categoría', field: (fila) => fila.categoria?.nombre, align: 'left' },
  { name: 'precio', label: 'Precio', field: 'precio', align: 'right', format: formatearPrecio },
  { name: 'disponible', label: 'Disponibilidad', field: 'disponible', align: 'left' },
  { name: 'acciones', label: '', field: 'id_producto', align: 'right' },
]

async function cargar(props) {
  const { page, rowsPerPage } = props?.pagination ?? paginacion.value
  cargando.value = true
  try {
    const respuesta = await menuService.productos({
      page,
      por_pagina: rowsPerPage,
      buscar: filtros.buscar || undefined,
      id_categoria: filtros.id_categoria || undefined,
      disponible: filtros.disponible ?? undefined,
      estado: filtros.estado,
    })
    productos.value = respuesta.data
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

async function cargarCategorias() {
  try {
    categorias.value = await menuService.categorias()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  }
}

watch(filtros, () => cargar({ pagination: { ...paginacion.value, page: 1 } }))

function abrirFormulario(producto) {
  productoSeleccionado.value = producto
  formularioAbierto.value = true
}

/** CU05: el cambio se ve de inmediato y se revierte si la API falla. */
async function cambiarDisponibilidad(producto, disponible) {
  cambiando.value = producto.id_producto
  const anterior = producto.disponible
  producto.disponible = disponible
  try {
    const respuesta = await menuService.cambiarDisponibilidad(producto.id_producto, disponible)
    $q.notify({ type: disponible ? 'positive' : 'warning', message: respuesta.message })
  } catch (error) {
    producto.disponible = anterior
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cambiando.value = null
  }
}

/** CU04: mover el producto a otra categoría. */
async function cambiarCategoria(producto, categoria) {
  if (categoria.id_categoria === producto.id_categoria) return
  try {
    const respuesta = await menuService.cambiarCategoria(producto.id_producto, categoria.id_categoria)
    $q.notify({ type: 'positive', message: respuesta.message })
    recargar()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  }
}

/** CU03: confirmación y baja lógica. */
function eliminar(producto) {
  $q.dialog({
    title: 'Dar de baja producto',
    message: `"${producto.nombre}" dejará de aparecer en el menú, pero se conserva en el historial de pedidos y reportes. ¿Continuar?`,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Dar de baja' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await menuService.eliminarProducto(producto.id_producto)
      $q.notify({ type: 'positive', message: respuesta.message })
      recargar()
    } catch (error) {
      // CU03 flujo 4a: con pedidos pendientes, se sugiere marcarlo como no disponible.
      if (error.response?.data?.codigo === 'pedidos_pendientes' && puedeCambiarDisponibilidad.value && producto.disponible) {
        $q.dialog({
          title: 'No se puede dar de baja',
          message: error.response.data.message,
          cancel: { flat: true, noCaps: true, label: 'Cerrar' },
          ok: { color: 'warning', unelevated: true, noCaps: true, label: 'Marcar como no disponible' },
        }).onOk(() => cambiarDisponibilidad(producto, false))
        return
      }
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

onMounted(async () => {
  await cargarCategorias()
  recargar()
})
</script>
