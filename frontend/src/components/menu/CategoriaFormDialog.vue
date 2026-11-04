<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 460px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ categoria ? 'Editar categoría' : 'Nueva categoría' }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>
          <q-input
            v-model.trim="datos.nombre"
            label="Nombre *"
            outlined
            autofocus
            maxlength="80"
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
            maxlength="255"
            :error="!!errores.descripcion"
            :error-message="errores.descripcion"
          />
          <q-toggle
            v-if="categoria"
            v-model="datos.activa"
            :label="datos.activa ? 'Activa (se ve en el menú)' : 'Inactiva (oculta del menú)'"
            color="positive"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps :label="categoria ? 'Guardar' : 'Crear categoría'" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import menuService from '@/services/menuService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const props = defineProps({ categoria: { type: Object, default: null } })
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ nombre: '', descripcion: '', activa: true })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

function preparar() {
  errores.value = {}
  mensaje.value = ''
  datos.nombre = props.categoria?.nombre ?? ''
  datos.descripcion = props.categoria?.descripcion ?? ''
  datos.activa = props.categoria?.activa ?? true
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const respuesta = props.categoria
      ? await menuService.actualizarCategoria(props.categoria.id_categoria, { ...datos })
      : await menuService.crearCategoria({ nombre: datos.nombre, descripcion: datos.descripcion })
    $q.notify({ type: 'positive', message: respuesta.message })
    abierto.value = false
    emit('guardado', respuesta.data)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) mensaje.value = mensajeError(error)
  } finally {
    guardando.value = false
  }
}
</script>
