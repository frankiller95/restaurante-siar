import { defineStore } from 'pinia'
import authService from '@/services/authService'

const CLAVE_TOKEN = 'siar_token'
const CLAVE_USUARIO = 'siar_usuario'

function leer(clave) {
  try {
    return localStorage.getItem(clave)
  } catch {
    return null
  }
}

function guardar(clave, valor) {
  try {
    if (valor === null) localStorage.removeItem(clave)
    else localStorage.setItem(clave, valor)
  } catch {
    // Sin almacenamiento (modo privado): la sesión dura mientras la pestaña esté abierta.
  }
}

function usuarioGuardado() {
  try {
    return JSON.parse(leer(CLAVE_USUARIO) || 'null')
  } catch {
    return null
  }
}

/**
 * Sesión del usuario (CU-05). Guarda el token de Sanctum y el perfil con
 * sus permisos para armar el menú según el rol.
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: leer(CLAVE_TOKEN),
    usuario: usuarioGuardado(),
    perfilVerificado: false,
  }),

  getters: {
    autenticado: (state) => Boolean(state.token),
    permisos: (state) => state.usuario?.permisos || [],
    nombreRol: (state) => state.usuario?.rol?.nombre_rol || '',
  },

  actions: {
    tienePermiso(codigo) {
      return this.permisos.includes(codigo)
    },

    async login(correo, contrasena) {
      const respuesta = await authService.login(correo, contrasena)
      this.establecerSesion(respuesta.token, respuesta.usuario)
      this.perfilVerificado = true
      return respuesta.usuario
    },

    /** Vuelve a pedir el perfil al servidor (permisos actualizados). */
    async cargarPerfil() {
      const usuario = await authService.perfil()
      this.usuario = usuario
      this.perfilVerificado = true
      guardar(CLAVE_USUARIO, JSON.stringify(usuario))
      return usuario
    },

    async logout() {
      try {
        if (this.token) await authService.logout()
      } finally {
        this.limpiar()
      }
    },

    establecerSesion(token, usuario) {
      this.token = token
      this.usuario = usuario
      guardar(CLAVE_TOKEN, token)
      guardar(CLAVE_USUARIO, JSON.stringify(usuario))
    },

    limpiar() {
      this.token = null
      this.usuario = null
      this.perfilVerificado = false
      guardar(CLAVE_TOKEN, null)
      guardar(CLAVE_USUARIO, null)
    },
  },
})
