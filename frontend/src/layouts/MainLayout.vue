<template>
  <q-layout view="hHh Lpr lFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menú" @click="menuAbierto = !menuAbierto" />

        <q-toolbar-title class="row items-center no-wrap">
          <q-icon name="restaurant" class="q-mr-sm" />
          <span class="text-weight-bold">SIAR</span>
        </q-toolbar-title>

        <q-btn flat no-caps class="q-px-sm">
          <q-avatar size="32px" color="white" text-color="primary" class="q-mr-sm">
            {{ iniciales }}
          </q-avatar>
          <div class="column items-start gt-xs">
            <span class="text-body2">{{ auth.usuario?.nombres }}</span>
            <span class="text-caption" style="opacity: 0.85">{{ auth.nombreRol }}</span>
          </div>
          <q-icon name="arrow_drop_down" />

          <q-menu anchor="bottom right" self="top right">
            <q-list style="min-width: 200px">
              <q-item clickable v-close-popup :to="{ name: 'perfil' }">
                <q-item-section avatar><q-icon name="person" /></q-item-section>
                <q-item-section>Mi perfil</q-item-section>
              </q-item>
              <q-separator />
              <q-item clickable v-close-popup @click="cerrarSesion">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>Cerrar sesión</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="menuAbierto" show-if-above bordered :width="250">
      <q-list padding>
        <template v-for="grupo in menuVisible" :key="grupo.titulo">
          <q-item-label header>{{ grupo.titulo }}</q-item-label>
          <q-item
            v-for="opcion in grupo.opciones"
            :key="opcion.titulo"
            :to="opcion.to"
            :disable="!opcion.to"
            clickable
            exact
            active-class="text-primary bg-orange-1 text-weight-medium"
          >
            <q-item-section avatar><q-icon :name="opcion.icono" /></q-item-section>
            <q-item-section>
              <q-item-label>{{ opcion.titulo }}</q-item-label>
              <q-item-label v-if="opcion.nota" caption>{{ opcion.nota }}</q-item-label>
            </q-item-section>
          </q-item>
        </template>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const $q = useQuasar()
const menuAbierto = ref(false)

/** Menú según los permisos del rol (CU-05 paso 6). */
const menu = [
  {
    titulo: 'General',
    opciones: [{ titulo: 'Inicio', icono: 'home', to: { name: 'inicio' } }],
  },
  {
    titulo: 'Administración',
    opciones: [
      { titulo: 'Usuarios', icono: 'group', to: { name: 'usuarios' }, permiso: 'gestionar_usuarios' },
      { titulo: 'Roles y permisos', icono: 'admin_panel_settings', to: { name: 'roles' }, permiso: 'gestionar_usuarios' },
      { titulo: 'Bitácora', icono: 'history', to: { name: 'bitacora' }, permiso: 'gestionar_usuarios' },
    ],
  },
  {
    titulo: 'Operación',
    opciones: [
      { titulo: 'Menú', icono: 'restaurant_menu', nota: 'Próximamente', permisos: ['gestionar_menu', 'consultar_menu'] },
      { titulo: 'Mesas', icono: 'table_restaurant', nota: 'Próximamente', permisos: ['gestionar_mesas', 'consultar_mesas'] },
    ],
  },
]

const menuVisible = computed(() =>
  menu
    .map((grupo) => ({
      ...grupo,
      opciones: grupo.opciones.filter((opcion) => {
        if (opcion.permiso) return auth.tienePermiso(opcion.permiso)
        if (opcion.permisos) return opcion.permisos.some((p) => auth.tienePermiso(p))
        return true
      }),
    }))
    .filter((grupo) => grupo.opciones.length > 0),
)

const iniciales = computed(() => {
  const partes = (auth.usuario?.nombres || '').trim().split(/\s+/)
  return partes.slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('') || '?'
})

async function cerrarSesion() {
  $q.loading.show({ message: 'Cerrando sesión...' })
  try {
    await auth.logout()
  } finally {
    $q.loading.hide()
    router.push({ name: 'login' })
  }
}
</script>
