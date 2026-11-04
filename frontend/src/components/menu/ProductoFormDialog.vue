<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar" @hide="liberarVistaPrevia">
    <q-card style="width: 560px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ esEdicion ? 'Editar producto' : 'Nuevo producto' }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar()">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <q-input
            v-model.trim="datos.nombre"
            label="Nombre *"
            outlined
            autofocus
            maxlength="120"
            :error="!!errores.nombre"
            :error-message="errores.nombre"
            :rules="[(v) => !!v || 'El nombre es obligatorio.']"
            lazy-rules
          />

          <q-input
            v-model.trim="datos.descripcion"
            label="Descripción"
            type="textarea"
            autogrow
            outlined
            maxlength="500"
            :error="!!errores.descripcion"
            :error-message="errores.descripcion"
          />

          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-5">
              <q-input
                v-model.number="datos.precio"
                type="number"
                label="Precio *"
                prefix="$"
                outlined
                min="0"
                step="100"
                :error="!!errores.precio"
                :error-message="errores.precio"
                :rules="[
                  (v) => (v !== '' && v !== null) || 'El precio es obligatorio.',
                  (v) => Number(v) >= 0 || 'El precio no puede ser negativo.',
                ]"
                lazy-rules
              />
            </div>
            <div class="col-12 col-sm-7">
              <q-select
                v-model="datos.id_categoria"
                :options="categorias"
                option-value="id_categoria"
                option-label="nombre"
                emit-value
                map-options
                label="Categoría *"
                outlined
                :error="!!errores.id_categoria"
                :error-message="errores.id_categoria"
                :rules="[(v) => !!v || 'Selecciona una categoría.']"
                lazy-rules
              >
                <template #after>
                  <q-btn round dense flat color="primary" icon="add" aria-label="Nueva categoría" @click="categoriaAbierta = true">
                    <q-tooltip>Crear categoría</q-tooltip>
                  </q-btn>
                </template>
                <template #no-option>
                  <q-item>
                    <q-item-section class="text-grey-7">No hay categorías. Crea una con el botón +.</q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
          </div>

          <div class="row items-center q-col-gutter-md">
            <div class="col-auto">
              <q-img
                v-if="vistaPrevia"
                :src="vistaPrevia"
                ratio="1"
                style="width: 96px; border-radius: 8px"
                fit="cover"
                alt="Imagen del producto"
              />
              <q-avatar v-else size="96px" rounded color="grey-3" text-color="grey-6" icon="image" />
            </div>
            <div class="col">
              <q-file
                v-model="imagen"
                label="Imagen (opcional)"
                outlined
                dense
                clearable
                accept=".jpg,.jpeg,.png,.webp,image/*"
                max-file-size="2097152"
                hint="JPG, PNG o WEBP de hasta 2 MB."
                :error="!!errores.imagen"
                :error-message="errores.imagen"
                @rejected="imagenRechazada"
                @update:model-value="quitarImagen = false"
              >
                <template #prepend><q-icon name="photo_camera" /></template>
              </q-file>
              <q-btn
                v-if="esEdicion && producto.imagen_url && !imagen && !quitarImagen"
                flat
                dense
                no-caps
                color="negative"
                icon="delete"
                label="Quitar imagen actual"
                class="q-mt-sm"
                @click="quitarImagen = true"
              />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps :label="esEdicion ? 'Guardar cambios' : 'Crear producto'" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>

    <!-- CU04 flujo 2a: crear la categoría desde el mismo formulario -->
    <CategoriaFormDialog v-model="categoriaAbierta" @guardado="categoriaCreada" />
  </q-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import menuService from '@/services/menuService'
import CategoriaFormDialog from '@/components/menu/CategoriaFormDialog.vue'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const props = defineProps({
  producto: { type: Object, default: null },
  categorias: { type: Array, default: () => [] },
})
const emit = defineEmits(['guardado', 'categoria-creada'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()

const datos = reactive({ nombre: '', descripcion: '', precio: null, id_categoria: null })
const imagen = ref(null)
const quitarImagen = ref(false)
const urlLocal = ref(null)
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)
const categoriaAbierta = ref(false)

const esEdicion = computed(() => Boolean(props.producto))

const vistaPrevia = computed(() => {
  if (urlLocal.value) return urlLocal.value
  if (quitarImagen.value) return null
  return props.producto?.imagen_url ?? null
})

watch(imagen, (archivo) => {
  liberarVistaPrevia()
  urlLocal.value = archivo ? URL.createObjectURL(archivo) : null
})

function liberarVistaPrevia() {
  if (urlLocal.value) URL.revokeObjectURL(urlLocal.value)
  urlLocal.value = null
}

onBeforeUnmount(liberarVistaPrevia)

function preparar() {
  errores.value = {}
  mensaje.value = ''
  imagen.value = null
  quitarImagen.value = false
  Object.assign(datos, {
    nombre: props.producto?.nombre ?? '',
    descripcion: props.producto?.descripcion ?? '',
    precio: props.producto?.precio ?? null,
    id_categoria: props.producto?.id_categoria ?? null,
  })
}

function imagenRechazada() {
  $q.notify({ type: 'negative', message: 'La imagen debe ser JPG, PNG o WEBP y pesar máximo 2 MB.' })
}

function categoriaCreada(categoria) {
  emit('categoria-creada', categoria)
  datos.id_categoria = categoria.id_categoria
}

async function guardar(confirmarDuplicado = false) {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  const opciones = { imagen: imagen.value, quitarImagen: quitarImagen.value, confirmarDuplicado }

  try {
    const respuesta = esEdicion.value
      ? await menuService.actualizarProducto(props.producto.id_producto, datos, opciones)
      : await menuService.crearProducto(datos, opciones)

    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('guardado', respuesta.data)
  } catch (error) {
    // CU01 flujo 3a: el nombre ya existe en la categoría → confirmar o renombrar.
    if (error.response?.data?.codigo === 'nombre_duplicado') {
      $q.dialog({
        title: 'Nombre repetido',
        message: error.response.data.message,
        cancel: { flat: true, noCaps: true, label: 'Cambiar el nombre' },
        ok: { color: 'primary', unelevated: true, noCaps: true, label: 'Guardar de todas formas' },
        persistent: true,
      }).onOk(() => guardar(true))
      return
    }

    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
  } finally {
    guardando.value = false
  }
}
</script>
