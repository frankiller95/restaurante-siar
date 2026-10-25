<template>
  <q-card-section class="q-pa-lg">
    <div class="text-h6 q-mb-xs">Iniciar sesión</div>
    <div class="text-body2 text-grey-7 q-mb-lg">Ingresa con tu correo y contraseña.</div>

    <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative q-mb-md">
      <template #avatar><q-icon name="error_outline" color="negative" /></template>
      {{ mensaje }}
    </q-banner>

    <q-form ref="formulario" class="q-gutter-md" @submit="ingresar">
      <q-input
        v-model.trim="correo"
        type="email"
        label="Correo electrónico"
        outlined
        autofocus
        autocomplete="username"
        :rules="[(v) => !!v || 'Escribe tu correo.']"
        lazy-rules
      >
        <template #prepend><q-icon name="mail" /></template>
      </q-input>

      <q-input
        v-model="contrasena"
        :type="verContrasena ? 'text' : 'password'"
        label="Contraseña"
        outlined
        autocomplete="current-password"
        :rules="[(v) => !!v || 'Escribe tu contraseña.']"
        lazy-rules
      >
        <template #prepend><q-icon name="lock" /></template>
        <template #append>
          <q-icon
            :name="verContrasena ? 'visibility_off' : 'visibility'"
            class="cursor-pointer"
            :aria-label="verContrasena ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            @click="verContrasena = !verContrasena"
          />
        </template>
      </q-input>

      <q-btn
        type="submit"
        color="primary"
        label="Ingresar"
        class="full-width"
        size="md"
        unelevated
        no-caps
        :loading="cargando"
      />
    </q-form>

    <div class="text-center q-mt-md">
      <router-link :to="{ name: 'recuperar-contrasena' }" class="text-primary">
        ¿Olvidó su contraseña?
      </router-link>
    </div>
  </q-card-section>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { mensajeError } from '@/utils/errores'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const correo = ref('')
const contrasena = ref('')
const verContrasena = ref(false)
const cargando = ref(false)
const mensaje = ref('')

async function ingresar() {
  mensaje.value = ''
  cargando.value = true
  try {
    await auth.login(correo.value, contrasena.value)
    const destino = typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'inicio' }
    router.replace(destino)
  } catch (error) {
    mensaje.value = mensajeError(error)
    contrasena.value = ''
  } finally {
    cargando.value = false
  }
}
</script>
