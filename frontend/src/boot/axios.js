import { defineBoot } from '#q-app'
import { Notify } from 'quasar'
import { api } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

/**
 * Agrega el token a cada petición y, si la API responde 401 (sesión vencida o
 * revocada por un cambio de rol/permisos), cierra la sesión local.
 */
export default defineBoot(({ app, router, store }) => {
  const auth = useAuthStore(store)

  api.interceptors.request.use((config) => {
    if (auth.token) {
      config.headers.Authorization = `Bearer ${auth.token}`
    }
    return config
  })

  api.interceptors.response.use(
    (respuesta) => respuesta,
    (error) => {
      const esLogin = error.config?.url?.includes('/auth/login')

      if (error.response?.status === 401 && auth.token && !esLogin) {
        auth.limpiar()
        Notify.create({
          type: 'warning',
          message: 'Tu sesión terminó. Inicia sesión de nuevo.',
        })
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      }

      return Promise.reject(error)
    },
  )

  app.config.globalProperties.$api = api
})
