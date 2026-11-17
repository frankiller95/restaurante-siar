<template>
  <div>
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm">
        <div class="text-subtitle1 text-weight-medium">Productos más vendidos</div>
        <div class="text-caption text-grey-7">Según las facturas pagadas del periodo.</div>
      </div>
      <div class="col-6 col-sm-auto">
        <q-btn-toggle
          v-model="ordenarPor"
          :options="ORDENES"
          no-caps
          unelevated
          dense
          toggle-color="primary"
          color="grey-3"
          text-color="grey-9"
        />
      </div>
      <div class="col-6 col-sm-auto">
        <q-select v-model="limite" :options="LIMITES" dense outlined emit-value map-options label="Mostrar" style="min-width: 120px" />
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn outline no-caps dense color="primary" icon="download" label="Exportar CSV" :disable="!datos" @click="exportar" />
      </div>
    </div>

    <div v-if="cargando && !datos" class="row justify-center q-pa-xl"><q-spinner color="primary" size="40px" /></div>

    <template v-if="datos">
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-4"><KpiTarjeta etiqueta="Ingresos por productos" :valor="formatearPrecio(datos.totales.ingresos)" destacado /></div>
        <div class="col-6 col-sm-4"><KpiTarjeta etiqueta="Unidades vendidas" :valor="formatearNumero(datos.totales.unidades)" /></div>
        <div class="col-6 col-sm-4"><KpiTarjeta etiqueta="Productos distintos" :valor="formatearNumero(datos.totales.productos_distintos)" /></div>
      </div>

      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle2 q-mb-sm">
            {{ ordenarPor === 'ingresos' ? 'Ingresos' : 'Unidades vendidas' }} por producto
          </div>
          <GraficoReporte
            horizontal
            valores-en-puntas
            :alto="altoGrafico"
            :etiquetas="datos.productos.map((p) => p.producto)"
            :series="[{ nombre: ordenarPor === 'ingresos' ? 'Ingresos' : 'Unidades', datos: datos.productos.map((p) => p[ordenarPor]) }]"
            :formato="ordenarPor === 'ingresos' ? 'moneda' : 'numero'"
            :detalles="datos.productos.map((p) => [`${formatearPorcentaje(p.participacion)} de los ingresos`])"
            :descripcion="`Los ${datos.productos.length} productos más vendidos por ${ordenarPor}`"
          />
        </q-card-section>
      </q-card>

      <q-table
        class="tabla-siar"
        flat
        bordered
        dense
        row-key="posicion"
        :rows="datos.productos"
        :columns="columnas"
        :loading="cargando"
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        no-data-label="Sin ventas en este periodo."
      />
      <div v-if="datos.totales.productos_distintos > datos.productos.length" class="text-caption text-grey-7 q-mt-sm">
        Se muestran {{ datos.productos.length }} de {{ datos.totales.productos_distintos }} productos. La participación es sobre los ingresos de todos los productos.
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import reportesService from '@/services/reportesService'
import GraficoReporte from '@/components/reportes/GraficoReporte.vue'
import KpiTarjeta from '@/components/reportes/KpiTarjeta.vue'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { exportarCsv, formatearNumero, formatearPorcentaje } from '@/utils/reportes'

/** RF34: productos más vendidos, por unidades o por ingresos. */
const props = defineProps({
  rango: { type: Object, required: true },
})

const $q = useQuasar()
const ORDENES = [
  { label: 'Por unidades', value: 'unidades' },
  { label: 'Por ingresos', value: 'ingresos' },
]
const LIMITES = [
  { label: 'Top 5', value: 5 },
  { label: 'Top 10', value: 10 },
  { label: 'Top 20', value: 20 },
  { label: 'Top 50', value: 50 },
]

const ordenarPor = ref('unidades')
const limite = ref(10)
const datos = ref(null)
const cargando = ref(false)
let ultimaConsulta = 0

// Una barra por producto: el alto del gráfico crece con la cantidad.
const altoGrafico = computed(() => Math.max(180, (datos.value?.productos.length ?? 0) * 34 + 40))

const columnas = [
  { name: 'posicion', label: '#', field: 'posicion', align: 'left' },
  { name: 'producto', label: 'Producto', field: 'producto', align: 'left' },
  { name: 'categoria', label: 'Categoría', field: 'categoria', align: 'left', format: (v) => v ?? '—' },
  { name: 'unidades', label: 'Unidades', field: 'unidades', align: 'right', format: formatearNumero },
  { name: 'ingresos', label: 'Ingresos', field: 'ingresos', align: 'right', format: formatearPrecio },
  { name: 'participacion', label: 'Participación', field: 'participacion', align: 'right', format: formatearPorcentaje },
  { name: 'facturas', label: 'Facturas', field: 'facturas', align: 'right' },
]

async function cargar() {
  const consulta = ++ultimaConsulta
  cargando.value = true
  try {
    const respuesta = await reportesService.productosMasVendidos({ ...props.rango, ordenarPor: ordenarPor.value, limite: limite.value })
    if (consulta === ultimaConsulta) datos.value = respuesta
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    if (consulta === ultimaConsulta) cargando.value = false
  }
}

function exportar() {
  exportarCsv(
    `productos_mas_vendidos_${datos.value.periodo.desde}_${datos.value.periodo.hasta}`,
    [
      { etiqueta: '#', valor: (p) => p.posicion },
      { etiqueta: 'Producto', valor: (p) => p.producto },
      { etiqueta: 'Categoría', valor: (p) => p.categoria ?? '' },
      { etiqueta: 'Unidades', valor: (p) => p.unidades },
      { etiqueta: 'Ingresos', valor: (p) => p.ingresos },
      { etiqueta: 'Participación (%)', valor: (p) => p.participacion },
      { etiqueta: 'Facturas', valor: (p) => p.facturas },
    ],
    datos.value.productos,
  )
}

watch(() => [props.rango.desde, props.rango.hasta, ordenarPor.value, limite.value], cargar, { immediate: true })
</script>
