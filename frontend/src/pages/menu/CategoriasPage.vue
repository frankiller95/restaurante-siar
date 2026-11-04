<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Categorías del menú</h1>
        <p class="page-subtitulo">Agrupan los productos en la carta. Una categoría con productos no se borra: se desactiva.</p>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn color="primary" icon="add" label="Nueva categoría" unelevated no-caps @click="abrirFormulario(null)" />
      </div>
    </div>

    <q-table
      class="tabla-siar"
      flat
      bordered
      row-key="id_categoria"
      :rows="categorias"
      :columns="columnas"
      :loading="cargando"
      :pagination="{ rowsPerPage: 0 }"
      hide-pagination
      no-data-label="Todavía no hay categorías. Crea la primera."
    >
      <template #body-cell-nombre="props">
        <q-td :props="props" style="white-space: normal">
          <div class="text-weight-medium">{{ props.row.nombre }}</div>
          <div v-if="props.row.descripcion" class="text-caption text-grey-7">{{ props.row.descripcion }}</div>
        </q-td>
      </template>

      <template #body-cell-activa="props">
        <q-td :props="props">
          <q-toggle
            :model-value="props.row.activa"
            :label="props.row.activa ? 'Activa' : 'Inactiva'"
            color="positive"
            @update:model-value="(valor) => cambiarEstado(props.row, valor)"
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
            icon="delete"
            color="negative"
            aria-label="Eliminar"
            :disable="props.row.productos_count > 0"
            @click="eliminar(props.row)"
          >
            <q-tooltip>{{ props.row.productos_count > 0 ? 'Tiene productos: desactívala en su lugar' : 'Eliminar' }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <CategoriaFormDialog v-model="formularioAbierto" :categoria="categoriaSeleccionada" @guardado="cargar" />
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import menuService from '@/services/menuService'
import CategoriaFormDialog from '@/components/menu/CategoriaFormDialog.vue'
import { mensajeError } from '@/utils/errores'

const $q = useQuasar()

const categorias = ref([])
const cargando = ref(false)
const formularioAbierto = ref(false)
const categoriaSeleccionada = ref(null)

const columnas = [
  { name: 'nombre', label: 'Categoría', field: 'nombre', align: 'left' },
  { name: 'productos_count', label: 'Productos activos', field: 'productos_count', align: 'center' },
  { name: 'activa', label: 'Estado', field: 'activa', align: 'left' },
  { name: 'acciones', label: '', field: 'id_categoria', align: 'right' },
]

async function cargar() {
  cargando.value = true
  try {
    categorias.value = await menuService.categorias()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

function abrirFormulario(categoria) {
  categoriaSeleccionada.value = categoria
  formularioAbierto.value = true
}

async function cambiarEstado(categoria, activa) {
  try {
    const respuesta = await menuService.actualizarCategoria(categoria.id_categoria, {
      nombre: categoria.nombre,
      descripcion: categoria.descripcion,
      activa,
    })
    categoria.activa = respuesta.data.activa
    $q.notify({
      type: 'positive',
      message: activa ? `${categoria.nombre} vuelve a aparecer en el menú.` : `${categoria.nombre} quedó oculta del menú.`,
    })
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  }
}

function eliminar(categoria) {
  $q.dialog({
    title: 'Eliminar categoría',
    message: `¿Seguro que quieres eliminar la categoría "${categoria.nombre}"?`,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Eliminar' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await menuService.eliminarCategoria(categoria.id_categoria)
      $q.notify({ type: 'positive', message: respuesta.message })
      cargar()
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

onMounted(cargar)
</script>
