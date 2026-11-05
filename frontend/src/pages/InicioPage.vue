<template>
  <q-page padding>
    <div class="q-mb-lg">
      <h1 class="page-titulo">Hola, {{ primerNombre }}</h1>
      <p class="page-subtitulo">Ingresaste como {{ auth.nombreRol }}. Estos son los módulos disponibles para tu rol.</p>
    </div>

    <div class="row q-col-gutter-md">
      <div v-for="modulo in modulosVisibles" :key="modulo.titulo" class="col-12 col-sm-6 col-md-4">
        <q-card
          flat
          bordered
          class="full-height"
          :class="{ 'cursor-pointer': modulo.to }"
          @click="modulo.to && $router.push(modulo.to)"
        >
          <q-card-section class="row items-center no-wrap">
            <q-avatar :color="modulo.to ? 'orange-1' : 'grey-3'" :text-color="modulo.to ? 'primary' : 'grey-6'" :icon="modulo.icono" size="48px" />
            <div class="q-ml-md">
              <div class="text-subtitle1 text-weight-medium">{{ modulo.titulo }}</div>
              <div class="text-body2 text-grey-7">{{ modulo.descripcion }}</div>
            </div>
          </q-card-section>
          <q-card-section v-if="!modulo.to" class="q-pt-none">
            <q-badge color="grey-5" label="Próximamente" />
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const primerNombre = computed(() => (auth.usuario?.nombres || '').split(' ')[0])

const modulos = [
  { titulo: 'Usuarios', descripcion: 'Registrar, editar y dar de baja cuentas.', icono: 'group', to: { name: 'usuarios' }, permisos: ['gestionar_usuarios'] },
  { titulo: 'Roles y permisos', descripcion: 'Configurar qué puede hacer cada rol.', icono: 'admin_panel_settings', to: { name: 'roles' }, permisos: ['gestionar_usuarios'] },
  { titulo: 'Bitácora', descripcion: 'Auditoría de acciones del sistema.', icono: 'history', to: { name: 'bitacora' }, permisos: ['gestionar_usuarios'] },
  { titulo: 'Carta del menú', descripcion: 'Platos y bebidas por categoría, con los agotados marcados.', icono: 'menu_book', to: { name: 'carta' }, permisos: ['consultar_menu', 'gestionar_menu', 'gestionar_disponibilidad'] },
  { titulo: 'Productos', descripcion: 'Crear, editar y dar de baja productos; cambiar disponibilidad.', icono: 'restaurant_menu', to: { name: 'productos' }, permisos: ['gestionar_menu', 'gestionar_disponibilidad'] },
  { titulo: 'Categorías', descripcion: 'Entradas, platos fuertes, bebidas, postres...', icono: 'category', to: { name: 'categorias' }, permisos: ['gestionar_menu'] },
  { titulo: 'Mesas', descripcion: 'Plano de mesas: libres, ocupadas, reservadas y en mantenimiento.', icono: 'table_restaurant', to: { name: 'mesas' }, permisos: ['consultar_mesas', 'gestionar_mesas', 'gestionar_reservas'] },
  { titulo: 'Reservas', descripcion: 'Registrar reservas, llegadas y cancelaciones.', icono: 'event', to: { name: 'reservas' }, permisos: ['gestionar_reservas', 'gestionar_mesas'] },
  { titulo: 'Pedidos', descripcion: 'Tomar pedidos, enviarlos a cocina y seguir su estado.', icono: 'receipt', to: { name: 'pedidos' }, permisos: ['tomar_pedidos', 'preparar_pedidos', 'facturar'] },
  { titulo: 'Cocina', descripcion: 'Cola de pedidos por preparar, en preparación y listos.', icono: 'soup_kitchen', to: { name: 'cocina' }, permisos: ['preparar_pedidos'] },
  { titulo: 'Inventario', descripcion: 'Insumos, entradas, mermas, ajustes y kárdex.', icono: 'inventory_2', to: { name: 'inventario' }, permisos: ['gestionar_inventario'] },
  { titulo: 'Mi perfil', descripcion: 'Tus datos y cambio de contraseña.', icono: 'person', to: { name: 'perfil' }, permisos: [] },
]

const modulosVisibles = computed(() =>
  modulos.filter((m) => m.permisos.length === 0 || m.permisos.some((p) => auth.tienePermiso(p))),
)
</script>
