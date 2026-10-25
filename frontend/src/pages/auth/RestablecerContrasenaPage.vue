<template>
  <q-card-section class="q-pa-lg">
    <div class="text-h6 q-mb-xs">Crear nueva contraseña</div>

    <template v-if="!enlaceCompleto">
      <q-banner rounded class="bg-red-1 text-negative q-my-md">
        El enlace está incompleto. Solicita uno nuevo.
      </q-banner>
      <q-btn color="primary" unelevated no-caps class="full-width" :to="{ name: 'recuperar-contrasena' }" label="Solicitar enlace" />
    </template>

    <template v-else>
      <div class="text-body2 text-grey-7 q-mb-lg">Para la cuenta {{ correo }}</div>

      <q-banner v-if="mensaje" rounded class="bg-red-1 text-negative q-mb-md">
        {{ mensaje }}
        <template v-if="enlaceInvalido" #action>
          <q-btn flat color="negative" no-caps label="Pedir otro enlace" :to="{ name: 'recuperar-contrasena' }" />
        </template>
      </q-banner>

      <q-form class="q-gutter-md" @submit="restablecer">
        <q-input
          v-model="contrasena"
          :type="ver ? 'text' : 'password'"
          label="Nueva contraseña"
          outlined
          autocomplete="new-password"
          :error="!!errores.contrasena"
          :error-message="errores.contrasena"
          hint="Mínimo 8 caracteres, con mayúscula, minúscula y número."
          :rules="[(v) => !!v || 'Escribe la nueva contraseña.']"
          lazy-rules
        >
          <template #append>
            <q-icon :name="ver ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="ver = !ver" />
          </template>
        </q-input>

        <q-input
          v-model="confirmacion"
          :type="ver ? 'text' : 'password'"
          label="Confirmar contraseña"
          outlined
          autocomplete="new-password"
          :rules="[(v) => v === contrasena || 'Las contraseñas no coinciden.']"
          lazy-rules
        />

        <q-btn
          type="submit"
          color="primary"
          label="Guardar contraseña"
          class="full-width"
          unelevated
          no-caps
          :loading="cargando"
        />
      </q-form>
    </template>
  </q-card-section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import authService from '@/services/authService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()

const token = String(route.query.token || '')
const correo = String(route.query.correo || '')
const enlaceCompleto = computed(() => token !== '' && correo !== '')

const contrasena = ref('')
const confirmacion = ref('')
const ver = ref(false)
const cargando = ref(false)
const mensaje = ref('')
const enlaceInvalido = ref(false)
const errores = ref({})

async function restablecer() {
  cargando.value = true
  mensaje.value = ''
  errores.value = {}
  enlaceInvalido.value = false
  try {
    const respuesta = await authService.restablecerContrasena({
      correo,
      token,
      contrasena: contrasena.value,
      contrasena_confirmation: confirmacion.value,
    })
    $q.notify({ type: 'positive', message: respuesta.message })
    router.replace({ name: 'login' })
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (!errores.value.contrasena) {
      mensaje.value = mensajeError(error)
      enlaceInvalido.value = error.response?.status === 422
    }
  } finally {
    cargando.value = false
  }
}
</script>
