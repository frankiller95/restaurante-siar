<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Roles y permisos</h1>
        <p class="page-subtitulo">
          Los roles del sistema no se renombran ni se eliminan; solo se configuran sus permisos.
        </p>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn color="primary" icon="add" label="Nuevo rol" unelevated no-caps @click="abrirFormulario(null)" />
      </div>
    </div>

    <div v-if="cargando" class="row justify-center q-pa-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else class="row q-col-gutter-md">
      <div v-for="rol in roles" :key="rol.id_rol" class="col-12 col-md-6">
        <q-card flat bordered class="full-height column">
          <q-card-section class="row items-start no-wrap">
            <div class="col">
              <div class="row items-center q-gutter-x-sm">
                <span class="text-subtitle1 text-weight-medium">{{ rol.nombre_rol }}</span>
                <q-badge v-if="rol.es_predefinido" color="primary" label="Sistema" />
                <q-badge v-else color="secondary" label="Personalizado" />
              </div>
              <div class="text-body2 text-grey-7">{{ rol.descripcion || 'Sin descripción' }}</div>
              <div class="text-caption text-grey-6 q-mt-xs">
                <q-icon name="group" /> {{ rol.usuarios_count }} {{ rol.usuarios_count === 1 ? 'usuario' : 'usuarios' }}
              </div>
            </div>
            <div v-if="!rol.es_predefinido" class="col-auto">
              <q-btn flat round dense icon="edit" color="primary" aria-label="Editar rol" @click="abrirFormulario(rol)">
                <q-tooltip>Editar</q-tooltip>
              </q-btn>
              <q-btn
                flat
                round
                dense
                icon="delete"
                color="negative"
                aria-label="Eliminar rol"
                :disable="rol.usuarios_count > 0"
                @click="eliminar(rol)"
              >
                <q-tooltip>{{ rol.usuarios_count > 0 ? 'Tiene usuarios asociados' : 'Eliminar' }}</q-tooltip>
              </q-btn>
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section class="col">
            <div class="text-caption text-grey-7 q-mb-xs">Permisos</div>
            <div class="column">
              <q-checkbox
                v-for="permiso in permisos"
                :key="permiso.id_permiso"
                v-model="seleccion[rol.id_rol]"
                :val="permiso.id_permiso"
                :label="permiso.nombre"
                :disable="esPermisoBloqueado(rol, permiso)"
                dense
                class="q-py-xs"
              >
                <q-tooltip anchor="center right" self="center left">{{ permiso.descripcion }}</q-tooltip>
              </q-checkbox>
            </div>
          </q-card-section>

          <q-card-actions v-if="hayCambios(rol)" align="right" class="q-px-md q-pb-md">
            <q-btn flat no-caps label="Descartar" @click="restaurar(rol)" />
            <q-btn
              color="primary"
              unelevated
              no-caps
              label="Guardar permisos"
              :loading="guardandoRol === rol.id_rol"
              @click="guardarPermisos(rol)"
            />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <RolFormDialog v-model="formularioAbierto" :rol="rolSeleccionado" @guardado="cargar" />
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import rolesService from '@/services/rolesService'
import RolFormDialog from '@/components/roles/RolFormDialog.vue'
import { mensajeError } from '@/utils/errores'

const $q = useQuasar()
const auth = useAuthStore()

const roles = ref([])
const permisos = ref([])
const seleccion = reactive({})
const cargando = ref(true)
const guardandoRol = ref(null)
const formularioAbierto = ref(false)
const rolSeleccionado = ref(null)

function idsDe(rol) {
  return rol.permisos.map((p) => p.id_permiso)
}

function restaurar(rol) {
  seleccion[rol.id_rol] = idsDe(rol)
}

function hayCambios(rol) {
  const actual = [...(seleccion[rol.id_rol] || [])].sort()
  const original = idsDe(rol).sort()
  return actual.length !== original.length || actual.some((id, i) => id !== original[i])
}

/** El rol Administrador debe conservar "gestionar_usuarios". */
function esPermisoBloqueado(rol, permiso) {
  return rol.es_predefinido && rol.nombre_rol === 'Administrador' && permiso.codigo_permiso === 'gestionar_usuarios'
}

async function cargar() {
  cargando.value = roles.value.length === 0
  try {
    const [listaRoles, listaPermisos] = await Promise.all([rolesService.listar(), rolesService.permisos()])
    roles.value = listaRoles
    permisos.value = listaPermisos
    listaRoles.forEach(restaurar)
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

async function guardarPermisos(rol) {
  guardandoRol.value = rol.id_rol
  try {
    const respuesta = await rolesService.asignarPermisos(rol.id_rol, seleccion[rol.id_rol])
    const indice = roles.value.findIndex((r) => r.id_rol === rol.id_rol)
    roles.value[indice] = respuesta.data
    restaurar(respuesta.data)
    $q.notify({ type: 'positive', message: respuesta.message })

    // Si cambió el rol propio, se refresca el menú con los permisos nuevos.
    if (rol.id_rol === auth.usuario?.id_rol) await auth.cargarPerfil()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    guardandoRol.value = null
  }
}

function abrirFormulario(rol) {
  rolSeleccionado.value = rol
  formularioAbierto.value = true
}

function eliminar(rol) {
  $q.dialog({
    title: 'Eliminar rol',
    message: `¿Seguro que quieres eliminar el rol "${rol.nombre_rol}"?`,
    cancel: { flat: true, noCaps: true, label: 'Cancelar' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Eliminar' },
    persistent: true,
  }).onOk(async () => {
    try {
      const respuesta = await rolesService.eliminar(rol.id_rol)
      $q.notify({ type: 'positive', message: respuesta.message })
      cargar()
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
    }
  })
}

onMounted(cargar)
</script>
