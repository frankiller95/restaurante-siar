<template>
  <div>
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm">
        <div class="text-subtitle1 text-weight-medium">Consumo de inventario</div>
        <div class="text-caption text-grey-7">
          Sale del kárdex: lo consumido por los pedidos (menos lo reintegrado), mermas, entradas y ajustes.
        </div>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn outline no-caps dense color="primary" icon="download" label="Exportar CSV" :disable="!datos" @click="exportar" />
      </div>
    </div>

    <div v-if="cargando && !datos" class="row justify-center q-pa-xl"><q-spinner color="primary" size="40px" /></div>

    <template v-if="datos">
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-6 col-md-3"><KpiTarjeta etiqueta="Valor consumido" :valor="formatearPrecio(datos.totales.valor_consumo)" destacado /></div>
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Valor de mermas" :valor="formatearPrecio(datos.totales.valor_mermas)" /></div>
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Compras (entradas)" :valor="formatearPrecio(datos.totales.valor_entradas)" /></div>
        <div class="col-12 col-md-3">
          <KpiTarjeta
            etiqueta="Insumos con stock bajo"
            :valor="formatearNumero(datos.totales.insumos_bajo_minimo)"
            :nota="`de ${datos.totales.insumos_con_movimientos} con movimientos`"
          />
        </div>
      </div>

      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <div class="text-subtitle2 q-mb-sm">Insumos que más valor consumieron</div>
          <GraficoReporte
            horizontal
            valores-en-puntas
            formato="moneda"
            :alto="altoGrafico"
            :etiquetas="principales.map((i) => i.nombre)"
            :series="[{ nombre: 'Valor consumido', datos: principales.map((i) => i.valor_consumo) }]"
            :detalles="principales.map((i) => [`${formatearNumero(i.consumo)} ${i.unidad} consumidos`])"
            descripcion="Los diez insumos con mayor valor consumido en el periodo"
          />
        </q-card-section>
      </q-card>

      <q-table
        class="tabla-siar"
        flat
        bordered
        dense
        row-key="id_insumo"
        :rows="datos.insumos"
        :columns="columnas"
        :loading="cargando"
        :rows-per-page-options="[15, 30, 0]"
        :pagination="{ rowsPerPage: 15 }"
        no-data-label="No hubo movimientos de inventario en este periodo."
        rows-per-page-label="Filas por página"
        :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      >
        <template #body-cell-stock="props">
          <q-td :props="props">
            <span :class="{ 'text-negative text-weight-bold': props.row.bajo_minimo }">
              {{ formatearNumero(props.row.stock_actual) }} {{ props.row.unidad }}
            </span>
            <q-icon v-if="props.row.bajo_minimo" name="warning" color="warning" size="16px" class="q-ml-xs">
              <q-tooltip>En su stock mínimo o por debajo ({{ formatearNumero(props.row.stock_minimo) }} {{ props.row.unidad }})</q-tooltip>
            </q-icon>
          </q-td>
        </template>
      </q-table>
      <div class="text-caption text-grey-7 q-mt-sm">
        Los valores de consumo y mermas usan el costo unitario actual de cada insumo; las entradas, el costo con que se compraron.
        El ajuste es la diferencia neta por conteo físico (puede ser negativa).
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
import { exportarCsv, formatearNumero } from '@/utils/reportes'

/** RF35: consumo de inventario por insumo. */
const props = defineProps({
  rango: { type: Object, required: true },
})

const $q = useQuasar()
const datos = ref(null)
const cargando = ref(false)
let ultimaConsulta = 0

const principales = computed(() => (datos.value?.insumos ?? []).filter((i) => i.valor_consumo > 0).slice(0, 10))
const altoGrafico = computed(() => Math.max(180, principales.value.length * 34 + 40))

const cantidad = (campo) => (fila) => `${formatearNumero(fila[campo])} ${fila.unidad}`

const columnas = [
  { name: 'nombre', label: 'Insumo', field: 'nombre', align: 'left' },
  { name: 'consumo', label: 'Consumo', field: cantidad('consumo'), align: 'right' },
  { name: 'valor_consumo', label: 'Valor consumo', field: 'valor_consumo', align: 'right', format: formatearPrecio },
  { name: 'mermas', label: 'Mermas', field: cantidad('mermas'), align: 'right' },
  { name: 'valor_mermas', label: 'Valor mermas', field: 'valor_mermas', align: 'right', format: formatearPrecio },
  { name: 'entradas', label: 'Entradas', field: cantidad('entradas'), align: 'right' },
  { name: 'ajustes', label: 'Ajustes', field: cantidad('ajustes'), align: 'right' },
  { name: 'stock', label: 'Stock actual', field: 'stock_actual', align: 'right' },
]

async function cargar() {
  const consulta = ++ultimaConsulta
  cargando.value = true
  try {
    const respuesta = await reportesService.consumoInventario(props.rango)
    if (consulta === ultimaConsulta) datos.value = respuesta
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    if (consulta === ultimaConsulta) cargando.value = false
  }
}

function exportar() {
  exportarCsv(
    `consumo_inventario_${datos.value.periodo.desde}_${datos.value.periodo.hasta}`,
    [
      { etiqueta: 'Insumo', valor: (i) => i.nombre },
      { etiqueta: 'Unidad', valor: (i) => i.unidad },
      { etiqueta: 'Consumo', valor: (i) => i.consumo },
      { etiqueta: 'Valor consumo', valor: (i) => i.valor_consumo },
      { etiqueta: 'Mermas', valor: (i) => i.mermas },
      { etiqueta: 'Valor mermas', valor: (i) => i.valor_mermas },
      { etiqueta: 'Entradas', valor: (i) => i.entradas },
      { etiqueta: 'Valor entradas', valor: (i) => i.valor_entradas },
      { etiqueta: 'Ajustes', valor: (i) => i.ajustes },
      { etiqueta: 'Stock actual', valor: (i) => i.stock_actual },
      { etiqueta: 'Stock mínimo', valor: (i) => i.stock_minimo },
    ],
    datos.value.insumos,
  )
}

watch(() => [props.rango.desde, props.rango.hasta], cargar, { immediate: true })
</script>
