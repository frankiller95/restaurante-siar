<template>
  <q-dialog v-model="abierto" persistent @before-show="preparar">
    <q-card style="width: 520px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ esEdicion ? 'Editar usuario' : 'Nuevo usuario' }}</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-form @submit="guardar">
        <q-card-section class="q-gutter-md q-pt-none">
          <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative">{{ mensaje }}</q-banner>

          <q-input
            v-model.trim="datos.nombres"
            label="Nombres completos *"
            outlined
            autofocus
            maxlength="120"
            :error="!!errores.nombres"
            :error-message="errores.nombres"
            :rules="[(v) => !!v || 'Los nombres son obligatorios.']"
            lazy-rules
          />
          <q-input
            v-model.trim="datos.documento"
            label="Documento *"
            outlined
            maxlength="20"
            :error="!!errores.documento"
            :error-message="errores.documento"
            :rules="[(v) => !!v || 'El documento es obligatorio.']"
            lazy-rules
          />
          <q-input
            v-model.trim="datos.correo"
            type="email"
            label="Correo electrónico *"
            outlined
            maxlength="150"
            :error="!!errores.correo"
            :error-message="errores.correo"
            :rules="[(v) => !!v || 'El correo es obligatorio.']"
            lazy-rules
          />
          <q-select
            v-model="datos.id_rol"
            :options="roles"
            option-value="id_rol"
            option-label="nombre_rol"
            emit-value
            map-options
            label="Rol *"
            outlined
            :disable="esUnoMismo"
            :hint="esUnoMismo ? 'No puedes cambiar tu propio rol.' : undefined"
            :error="!!errores.id_rol"
            :error-message="errores.id_rol"
            :rules="[(v) => !!v || 'Selecciona un rol.']"
            lazy-rules
          />
          <div v-if="esEdicion">
            <q-toggle
              v-model="datos.estado"
              true-value="activo"
              false-value="inactivo"
              :label="datos.estado === 'activo' ? 'Usuario activo' : 'Usuario inactivo'"
              :disable="esUnoMismo"
              color="positive"
            />
            <div v-if="cambiaAcceso" class="text-caption text-warning q-ml-sm">
              Al cambiar el rol o el estado se cerrarán las sesiones abiertas de este usuario.
            </div>
          </div>
          <div v-else class="text-caption text-grey-7">
            El sistema genera una contraseña temporal y la envía al correo del usuario.
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat no-caps label="Cancelar" v-close-popup />
          <q-btn type="submit" color="primary" unelevated no-caps :label="esEdicion ? 'Guardar cambios' : 'Registrar'" :loading="guardando" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import usuariosService from '@/services/usuariosService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const props = defineProps({
  usuario: { type: Object, default: null },
  roles: { type: Array, default: () => [] },
})
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const auth = useAuthStore()

const datos = reactive({ nombres: '', documento: '', correo: '', id_rol: null, estado: 'activo' })
const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)

const esEdicion = computed(() => Boolean(props.usuario))
const esUnoMismo = computed(() => esEdicion.value && props.usuario.id_usuario === auth.usuario?.id_usuario)
const cambiaAcceso = computed(
  () => esEdicion.value && (datos.id_rol !== props.usuario.id_rol || datos.estado !== props.usuario.estado),
)

function preparar() {
  errores.value = {}
  mensaje.value = ''
  Object.assign(datos, {
    nombres: props.usuario?.nombres ?? '',
    documento: props.usuario?.documento ?? '',
    correo: props.usuario?.correo ?? '',
    id_rol: props.usuario?.id_rol ?? null,
    estado: props.usuario?.estado ?? 'activo',
  })
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  try {
    let respuesta
    if (esEdicion.value) {
      respuesta = await usuariosService.actualizar(props.usuario.id_usuario, { ...datos })
    } else {
      const { estado: _estado, ...nuevo } = datos
      respuesta = await usuariosService.crear(nuevo)
    }

    $q.notify({
      type: respuesta.correo_enviado === false ? 'warning' : 'positive',
      message: respuesta.message,
      timeout: respuesta.correo_enviado === false ? 8000 : 3500,
    })
    abierto.value = false
    emit('guardado', respuesta.data)
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) {
      mensaje.value = mensajeError(error)
    }
  } finally {
    guardando.value = false
  }
}
</script>
