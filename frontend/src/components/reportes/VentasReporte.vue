<template>
  <div>
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm">
        <div class="text-subtitle1 text-weight-medium">Ventas {{ textoAgrupacion }}</div>
        <div class="text-caption text-grey-7">Facturas pagadas en el periodo, por fecha de pago.</div>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn-toggle
          v-model="agrupar"
          :options="AGRUPACIONES"
          no-caps
          unelevated
          dense
          toggle-color="primary"
          color="grey-3"
          text-color="grey-9"
        />
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn outline no-caps dense color="primary" icon="download" label="Exportar CSV" :disable="!datos" @click="exportar" />
      </div>
    </div>

    <div v-if="cargando && !datos" class="row justify-center q-pa-xl"><q-spinner color="primary" size="40px" /></div>

    <template v-if="datos">
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Ventas totales" :valor="formatearPrecio(datos.totales.ventas)" destacado /></div>
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Facturas pagadas" :valor="formatearNumero(datos.totales.facturas)" /></div>
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Ticket promedio" :valor="formatearPrecio(datos.totales.ticket_promedio)" /></div>
        <div class="col-6 col-md-3">
          <KpiTarjeta
            etiqueta="Mejor periodo"
            :valor="datos.mejor_periodo ? formatearPrecio(datos.mejor_periodo.ventas) : '—'"
            :nota="datos.mejor_periodo?.etiqueta ?? 'Sin ventas'"
          />
        </div>
      </div>

      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-md-8">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">Ventas {{ textoAgrupacion }}</div>
              <GraficoReporte
                :tipo="usarLinea ? 'linea' : 'barras'"
                :etiquetas="datos.series.map((s) => s.etiqueta)"
                :series="[{ nombre: 'Ventas', datos: datos.series.map((s) => s.ventas) }]"
                :detalles="datos.series.map((s) => [`${s.facturas} ${s.facturas === 1 ? 'factura' : 'facturas'}`])"
                formato="moneda"
                :descripcion="`Ventas ${textoAgrupacion} del ${datos.periodo.desde} al ${datos.periodo.hasta}`"
              />
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="text-subtitle2 q-mb-md">Formas de pago</div>
              <BarrasProporcion :items="metodos" />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <q-table
        class="tabla-siar"
        flat
        bordered
        dense
        row-key="clave"
        :rows="datos.series"
        :columns="columnas"
        :loading="cargando"
        :rows-per-page-options="[15, 31, 100, 0]"
        :pagination="{ rowsPerPage: 31 }"
        no-data-label="Sin ventas en este periodo."
        rows-per-page-label="Filas por página"
        :pagination-label="(inicio, fin, total) => `${inicio}-${fin} de ${total}`"
      >
        <template #bottom-row>
          <q-tr class="text-weight-bold bg-grey-2">
            <q-td>Total</q-td>
            <q-td class="text-right">{{ datos.totales.facturas }}</q-td>
            <q-td class="text-right">{{ formatearPrecio(datos.totales.ventas) }}</q-td>
            <q-td class="text-right">{{ formatearPrecio(datos.totales.ventas_netas) }}</q-td>
            <q-td class="text-right">{{ formatearPrecio(datos.totales.descuentos) }}</q-td>
            <q-td class="text-right">{{ formatearPrecio(datos.totales.impuestos) }}</q-td>
            <q-td class="text-right">{{ formatearPrecio(datos.totales.propinas) }}</q-td>
            <q-td class="text-right">{{ formatearPrecio(datos.totales.ticket_promedio) }}</q-td>
          </q-tr>
        </template>
      </q-table>
      <div class="text-caption text-grey-7 q-mt-sm">
        Ventas netas = ventas sin impuesto ni propina. Los descuentos incluyen promociones y descuentos de caja.
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import reportesService from '@/services/reportesService'
import BarrasProporcion from '@/components/reportes/BarrasProporcion.vue'
import GraficoReporte from '@/components/reportes/GraficoReporte.vue'
import KpiTarjeta from '@/components/reportes/KpiTarjeta.vue'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { exportarCsv, formatearNumero } from '@/utils/reportes'

/** RF33: ventas por periodo (día, semana o mes). */
const props = defineProps({
  rango: { type: Object, required: true },
})

const $q = useQuasar()
const AGRUPACIONES = [
  { label: 'Por día', value: 'dia' },
  { label: 'Por semana', value: 'semana' },
  { label: 'Por mes', value: 'mes' },
]

const agrupar = ref('dia')
const datos = ref(null)
const cargando = ref(false)
let ultimaConsulta = 0

const textoAgrupacion = computed(() => ({ dia: 'por día', semana: 'por semana', mes: 'por mes' })[agrupar.value])
// Una línea cuenta una historia en el tiempo; con pocos puntos, columnas.
const usarLinea = computed(() => agrupar.value === 'dia' && (datos.value?.series.length ?? 0) >= 3)

const metodos = computed(() =>
  (datos.value?.por_metodo_pago ?? []).map((m) => ({
    etiqueta: m.etiqueta,
    texto: formatearPrecio(m.monto),
    porcentaje: m.porcentaje,
  })),
)

const columnas = [
  { name: 'etiqueta', label: 'Periodo', field: 'etiqueta', align: 'left' },
  { name: 'facturas', label: 'Facturas', field: 'facturas', align: 'right' },
  { name: 'ventas', label: 'Ventas', field: 'ventas', align: 'right', format: formatearPrecio },
  { name: 'netas', label: 'Ventas netas', field: 'ventas_netas', align: 'right', format: formatearPrecio },
  { name: 'descuentos', label: 'Descuentos', field: 'descuentos', align: 'right', format: formatearPrecio },
  { name: 'impuestos', label: 'Impuestos', field: 'impuestos', align: 'right', format: formatearPrecio },
  { name: 'propinas', label: 'Propinas', field: 'propinas', align: 'right', format: formatearPrecio },
  { name: 'ticket', label: 'Ticket promedio', field: 'ticket_promedio', align: 'right', format: formatearPrecio },
]

async function cargar() {
  const consulta = ++ultimaConsulta
  cargando.value = true
  try {
    const respuesta = await reportesService.ventas({ ...props.rango, agrupar: agrupar.value })
    if (consulta === ultimaConsulta) datos.value = respuesta
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    if (consulta === ultimaConsulta) cargando.value = false
  }
}

function exportar() {
  exportarCsv(
    `ventas_${agrupar.value}_${datos.value.periodo.desde}_${datos.value.periodo.hasta}`,
    [
      { etiqueta: 'Periodo', valor: (f) => f.etiqueta },
      { etiqueta: 'Facturas', valor: (f) => f.facturas },
      { etiqueta: 'Ventas', valor: (f) => f.ventas },
      { etiqueta: 'Ventas netas', valor: (f) => f.ventas_netas },
      { etiqueta: 'Descuentos', valor: (f) => f.descuentos },
      { etiqueta: 'Impuestos', valor: (f) => f.impuestos },
      { etiqueta: 'Propinas', valor: (f) => f.propinas },
      { etiqueta: 'Ticket promedio', valor: (f) => f.ticket_promedio },
    ],
    datos.value.series,
  )
}

watch(() => [props.rango.desde, props.rango.hasta, agrupar.value], cargar, { immediate: true })
</script>
