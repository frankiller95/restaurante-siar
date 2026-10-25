<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 460px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ rol ? 'Editar rol' : 'Nuevo rol personalizado' }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>
          <q-input
            v-model.trim="datos.nombre_rol"
            label="Nombre del rol *"
            outlined
            autofocus
            maxlength="50"
            :error="!!errores.nombre_rol"
            :error-message="errores.nombre_rol"
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
          <div v-if="!rol" class="text-caption text-grey-7">Después de crearlo podrás marcar sus permisos.</div>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps :label="rol ? 'Guardar' : 'Crear rol'" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import rolesService from '@/services/rolesService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const props = defineProps({ rol: { type: Object, default: null } })
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const datos = reactive({ nombre_rol: '', descripcion: '' })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

function preparar() {
  errores.value = {}
  mensaje.value = ''
  datos.nombre_rol = props.rol?.nombre_rol ?? ''
  datos.descripcion = props.rol?.descripcion ?? ''
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    const respuesta = props.rol
      ? await rolesService.actualizar(props.rol.id_rol, { ...datos })
      : await rolesService.crear({ ...datos })
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
