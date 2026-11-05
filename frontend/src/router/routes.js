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

      // Módulo 2: Gestión de Menú
      {
        path: 'carta',
        name: 'carta',
        component: () => import('@/pages/menu/CartaPage.vue'),
        meta: { permisos: ['consultar_menu', 'gestionar_menu', 'gestionar_disponibilidad'] },
      },
      {
        path: 'productos',
        name: 'productos',
        component: () => import('@/pages/menu/ProductosPage.vue'),
        meta: { permisos: ['gestionar_menu', 'gestionar_disponibilidad'] },
      },
      {
        path: 'categorias',
        name: 'categorias',
        component: () => import('@/pages/menu/CategoriasPage.vue'),
        meta: { permiso: 'gestionar_menu' },
      },

      // Módulo 3: Gestión de Mesas
      {
        path: 'mesas',
        name: 'mesas',
        component: () => import('@/pages/mesas/MesasPage.vue'),
        meta: { permisos: ['consultar_mesas', 'gestionar_mesas', 'gestionar_reservas'] },
      },
      {
        path: 'reservas',
        name: 'reservas',
        component: () => import('@/pages/mesas/ReservasPage.vue'),
        meta: { permisos: ['gestionar_reservas', 'gestionar_mesas'] },
      },

      // Módulo 4: Pedidos
      {
        path: 'pedidos',
        name: 'pedidos',
        component: () => import('@/pages/pedidos/PedidosPage.vue'),
        meta: { permisos: ['tomar_pedidos', 'preparar_pedidos', 'facturar'] },
      },
      {
        path: 'pedidos/:id(\\d+)',
        name: 'pedido',
        component: () => import('@/pages/pedidos/PedidoDetallePage.vue'),
        meta: { permisos: ['tomar_pedidos', 'preparar_pedidos', 'facturar'] },
      },
      {
        path: 'cocina',
        name: 'cocina',
        component: () => import('@/pages/pedidos/CocinaPage.vue'),
        meta: { permiso: 'preparar_pedidos' },
      },

      // Módulo 5: Inventario
      {
        path: 'inventario',
        name: 'inventario',
        component: () => import('@/pages/inventario/InventarioPage.vue'),
        meta: { permiso: 'gestionar_inventario' },
      },
      {
        path: 'inventario/:id(\\d+)',
        name: 'kardex',
        component: () => import('@/pages/inventario/KardexPage.vue'),
        meta: { permiso: 'gestionar_inventario' },
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
