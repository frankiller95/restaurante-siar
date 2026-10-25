/**
 * meta.publica: no requiere sesión.
 * meta.permiso: código de permiso necesario (RNF05 control de acceso por roles).
 *
 * El layout principal va primero para que "/" abra el inicio y no el layout de acceso.
 */
const routes = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', name: 'inicio', component: () => import('@/pages/InicioPage.vue') },
      { path: 'perfil', name: 'perfil', component: () => import('@/pages/PerfilPage.vue') },
      {
        path: 'usuarios',
        name: 'usuarios',
        component: () => import('@/pages/usuarios/UsuariosPage.vue'),
        meta: { permiso: 'gestionar_usuarios' },
      },
      {
        path: 'roles',
        name: 'roles',
        component: () => import('@/pages/roles/RolesPage.vue'),
        meta: { permiso: 'gestionar_usuarios' },
      },
      {
        path: 'bitacora',
        name: 'bitacora',
        component: () => import('@/pages/BitacoraPage.vue'),
        meta: { permiso: 'gestionar_usuarios' },
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { publica: true },
    children: [
      { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue') },
      {
        path: 'recuperar-contrasena',
        name: 'recuperar-contrasena',
        component: () => import('@/pages/auth/RecuperarContrasenaPage.vue'),
      },
      {
        path: 'restablecer-contrasena',
        name: 'restablecer-contrasena',
        component: () => import('@/pages/auth/RestablecerContrasenaPage.vue'),
      },
    ],
  },

  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
    meta: { publica: true },
  },
]

export default routes
