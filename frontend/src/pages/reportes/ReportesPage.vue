<template>
  <q-page padding>
    <div class="q-mb-md">
      <h1 class="page-titulo">Reportes</h1>
      <p class="page-subtitulo">
        Elige el periodo y consulta ventas, productos, inventario, ocupación de mesas e indicadores.
        Las fechas son hora del restaurante.
      </p>
    </div>

    <q-banner v-if="pestanas.length === 0" rounded class="bg-grey-2 text-grey-8">
      Tu rol no tiene acceso a los reportes.
    </q-banner>

    <template v-else>
      <FiltroPeriodo v-model="rango" />

      <q-tabs v-model="pestana" dense align="left" active-color="primary" indicator-color="primary" no-caps class="q-mb-md" mobile-arrows outside-arrows>
        <q-tab v-for="p in pestanas" :key="p.id" :name="p.id" :icon="p.icono" :label="p.etiqueta" />
      </q-tabs>

      <!-- Solo se monta el reporte de la pestaña activa (y solo entonces se pide al servidor). -->
      <q-tab-panels v-model="pestana" animated class="bg-transparent">
        <q-tab-panel v-for="p in pestanas" :key="p.id" :name="p.id" class="q-pa-none">
          <component :is="p.componente" :rango="rango" />
        </q-tab-panel>
      </q-tab-panels>
    </template>
  </q-page>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import FiltroPeriodo from '@/components/reportes/FiltroPeriodo.vue'
import IndicadoresReporte from '@/components/reportes/IndicadoresReporte.vue'
import InventarioReporte from '@/components/reportes/InventarioReporte.vue'
import OcupacionReporte from '@/components/reportes/OcupacionReporte.vue'
import ProductosReporte from '@/components/reportes/ProductosReporte.vue'
import VentasReporte from '@/components/reportes/VentasReporte.vue'
import { rangoDePeriodo } from '@/utils/reportes'

const auth = useAuthStore()

/**
 * Cada reporte pide uno de estos permisos (igual que en la API):
 * - ver_reportes: todos (Administrador).
 * - ver_reportes_operativos: solo inventario y ocupación de mesas (Cajero).
 */
const REPORTES = [
  { id: 'ventas', etiqueta: 'Ventas', icono: 'trending_up', componente: VentasReporte, permisos: ['ver_reportes'] },
  { id: 'productos', etiqueta: 'Productos más vendidos', icono: 'emoji_events', componente: ProductosReporte, permisos: ['ver_reportes'] },
  { id: 'inventario', etiqueta: 'Consumo de inventario', icono: 'inventory_2', componente: InventarioReporte, permisos: ['ver_reportes', 'ver_reportes_operativos'] },
  { id: 'ocupacion', etiqueta: 'Ocupación de mesas', icono: 'table_restaurant', componente: OcupacionReporte, permisos: ['ver_reportes', 'ver_reportes_operativos'] },
  { id: 'indicadores', etiqueta: 'Indicadores financieros', icono: 'account_balance', componente: IndicadoresReporte, permisos: ['ver_reportes'] },
]

const pestanas = computed(() => REPORTES.filter((r) => r.permisos.some((p) => auth.tienePermiso(p))))

const rango = ref(rangoDePeriodo('30'))
const pestana = ref(pestanas.value[0]?.id ?? null)
</script>
