<template>
  <q-page padding>
    <div class="q-mb-lg">
      <h1 class="page-titulo">Mi perfil</h1>
      <p class="page-subtitulo">Tus datos de cuenta y cambio de contraseña.</p>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-5">
        <q-card flat bordered>
          <q-list>
            <q-item>
              <q-item-section>
                <q-item-label caption>Nombres</q-item-label>
                <q-item-label>{{ auth.usuario?.nombres }}</q-item-label>
              </q-item-section>
            </q-item>
            <q-item>
              <q-item-section>
                <q-item-label caption>Documento</q-item-label>
                <q-item-label>{{ auth.usuario?.documento }}</q-item-label>
              </q-item-section>
            </q-item>
            <q-item>
              <q-item-section>
                <q-item-label caption>Correo</q-item-label>
                <q-item-label>{{ auth.usuario?.correo }}</q-item-label>
              </q-item-section>
            </q-item>
            <q-item>
              <q-item-section>
                <q-item-label caption>Rol</q-item-label>
                <q-item-label>{{ auth.nombreRol }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>

      <div class="col-12 col-md-7">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">Cambiar contraseña</div>
            <div class="text-body2 text-grey-7">Al cambiarla se cierran tus sesiones en otros dispositivos.</div>
          </q-card-section>
          <q-card-section>
            <q-form ref="formulario" class="q-gutter-md" @submit="guardar">
              <q-input
                v-model="datos.contrasena_actual"
                type="password"
                label="Contraseña actual"
                outlined
                autocomplete="current-password"
                :error="!!errores.contrasena_actual"
                :error-message="errores.contrasena_actual"
                :rules="[(v) => !!v || 'Escribe tu contraseña actual.']"
                lazy-rules
              />
              <q-input
                v-model="datos.contrasena"
                type="password"
                label="Nueva contraseña"
                outlined
                autocomplete="new-password"
                hint="Mínimo 8 caracteres, con mayúscula, minúscula y número."
                :error="!!errores.contrasena"
                :error-message="errores.contrasena"
                :rules="[(v) => !!v || 'Escribe la nueva contraseña.']"
                lazy-rules
              />
              <q-input
                v-model="datos.contrasena_confirmation"
                type="password"
                label="Confirmar nueva contraseña"
                outlined
                autocomplete="new-password"
                :rules="[(v) => v === datos.contrasena || 'Las contraseñas no coinciden.']"
                lazy-rules
              />
              <div class="row justify-end">
                <q-btn type="submit" color="primary" label="Cambiar contraseña" unelevated no-caps :loading="guardando" />
              </div>
            </q-form>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import authService from '@/services/authService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'

const auth = useAuthStore()
const $q = useQuasar()

const formulario = ref(null)
const guardando = ref(false)
const errores = ref({})
const datos = reactive({ contrasena_actual: '', contrasena: '', contrasena_confirmation: '' })

async function guardar() {
  guardando.value = true
  errores.value = {}
  try {
    const respuesta = await authService.cambiarContrasena({ ...datos })
    $q.notify({ type: 'positive', message: respuesta.message })
    Object.assign(datos, { contrasena_actual: '', contrasena: '', contrasena_confirmation: '' })
    formulario.value?.resetValidation()
  } catch (error) {
    errores.value = erroresPorCampo(error)
    if (Object.keys(errores.value).length === 0) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  } finally {
    guardando.value = false
  }
}
</script>
