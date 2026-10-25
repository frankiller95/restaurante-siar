<template>
  <q-card-section class="q-pa-lg">
    <div class="text-h6 q-mb-xs">Recuperar contraseña</div>
    <div class="text-body2 text-grey-7 q-mb-lg">
      Escribe el correo con el que te registraron y te enviaremos un enlace para crear una nueva contraseña.
    </div>

    <q-banner v-if="enviado" rounded class="bg-green-1 text-positive q-mb-md">
      <template #avatar><q-icon name="mark_email_read" color="positive" /></template>
      {{ mensaje }}
    </q-banner>
    <q-banner v-else-if="mensaje" dense rounded class="bg-red-1 text-negative q-mb-md">
      {{ mensaje }}
    </q-banner>

    <q-form v-if="!enviado" class="q-gutter-md" @submit="enviar">
      <q-input
        v-model.trim="correo"
        type="email"
        label="Correo electrónico"
        outlined
        autofocus
        :rules="[(v) => !!v || 'Escribe tu correo.']"
        lazy-rules
      >
        <template #prepend><q-icon name="mail" /></template>
      </q-input>

      <q-btn
        type="submit"
        color="primary"
        label="Enviar enlace"
        class="full-width"
        unelevated
        no-caps
        :loading="cargando"
      />
    </q-form>

    <div class="text-center q-mt-md">
      <router-link :to="{ name: 'login' }" class="text-primary">Volver a iniciar sesión</router-link>
    </div>
  </q-card-section>
</template>

<script setup>
import { ref } from 'vue'
import authService from '@/services/authService'
import { mensajeError } from '@/utils/errores'

const correo = ref('')
const cargando = ref(false)
const enviado = ref(false)
const mensaje = ref('')

async function enviar() {
  cargando.value = true
  mensaje.value = ''
  try {
    const respuesta = await authService.solicitarRecuperacion(correo.value)
    // CU-06 3a: el mensaje es el mismo exista o no el correo.
    mensaje.value = respuesta.message
    enviado.value = true
  } catch (error) {
    mensaje.value = mensajeError(error)
  } finally {
    cargando.value = false
  }
}
</script>
