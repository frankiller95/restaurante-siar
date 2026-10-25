import { defineRouter } from '#q-app'
import { Notify } from 'quasar'
import {
  createMemoryHistory,
  createRouter,
  createWebHashHistory,
  createWebHistory,
} from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import routes from './routes.js'

export default defineRouter(({ store }) => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,
    // Se configura en quasar.config.js -> build -> vueRouterMode / publicPath
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
  })

  // Control de acceso: sesión obligatoria y permiso según la ruta.
  Router.beforeEach(async (to) => {
    const auth = useAuthStore(store)
    const esPublica = to.matched.some((ruta) => ruta.meta.publica)

    if (esPublica) {
      return to.name === 'login' && auth.autenticado ? { name: 'inicio' } : true
    }

    if (!auth.autenticado) {
      return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
    }

    // Al recargar la página se confirma con el servidor que la sesión sigue viva.
    if (!auth.perfilVerificado) {
      try {
        await auth.cargarPerfil()
      } catch {
        auth.limpiar()
        return { name: 'login' }
      }
    }

    const permiso = to.matched.map((ruta) => ruta.meta.permiso).find(Boolean)
    if (permiso && !auth.tienePermiso(permiso)) {
      Notify.create({ type: 'negative', message: 'No tienes permiso para entrar a esa sección.' })
      return { name: 'inicio' }
    }

    return true
  })

  return Router
})
