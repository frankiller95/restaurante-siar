<template>
  <div>
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm">
        <div class="text-subtitle1 text-weight-medium">Indicadores financieros</div>
        <div class="text-caption text-grey-7">
          Comparados con el periodo anterior de la misma duración
          <template v-if="datos">({{ datos.periodo_anterior.desde }} a {{ datos.periodo_anterior.hasta }})</template>.
        </div>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn outline no-caps dense color="primary" icon="download" label="Exportar CSV" :disable="!datos" @click="exportar" />
      </div>
    </div>

    <div v-if="cargando && !datos" class="row justify-center q-pa-xl"><q-spinner color="primary" size="40px" /></div>

    <template v-if="datos">
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-md-6">
          <KpiTarjeta
            etiqueta="Ventas totales"
            :valor="formatearPrecio(k.ventas_totales.valor)"
            :variacion="k.ventas_totales.variacion"
            destacado
          />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Ventas netas" :valor="formatearPrecio(k.ventas_netas.valor)" :variacion="k.ventas_netas.variacion" nota="sin impuesto ni propina" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Margen bruto" :valor="formatearPrecio(k.margen_bruto.valor)" :variacion="k.margen_bruto.variacion" :nota="`${formatearPorcentaje(k.margen_porcentaje.valor)} de las ventas netas`" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Facturas pagadas" :valor="formatearNumero(k.facturas.valor)" :variacion="k.facturas.variacion" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Ticket promedio" :valor="formatearPrecio(k.ticket_promedio.valor)" :variacion="k.ticket_promedio.variacion" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Costo de insumos" :valor="formatearPrecio(k.costo_insumos.valor)" :variacion="k.costo_insumos.variacion" :sube-es-bueno="false" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Mermas" :valor="formatearPrecio(k.mermas.valor)" :variacion="k.mermas.variacion" :sube-es-bueno="false" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Descuentos" :valor="formatearPrecio(k.descuentos.valor)" :variacion="k.descuentos.variacion" :sube-es-bueno="false" />
        </div>
        <div class="col-6 col-md-3">
          <KpiTarjeta etiqueta="Margen (% de ventas netas)" :valor="formatearPorcentaje(k.margen_porcentaje.valor)" :variacion="k.margen_porcentaje.variacion" variacion-tipo="puntos" />
        </div>
      </div>

      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-md-4">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="text-subtitle2 q-mb-md">De qué se compone lo cobrado</div>
              <BarrasProporcion :items="composicion" />
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
        <div class="col-12 col-md-4">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">Pendiente y anulado</div>
              <q-list dense>
                <q-item class="q-px-none">
                  <q-item-section>
                    <q-item-label>Facturas por cobrar</q-item-label>
                    <q-item-label caption>{{ datos.por_cobrar.facturas_pendientes }} factura(s) sin pagar del todo</q-item-label>
                  </q-item-section>
                  <q-item-section side class="text-weight-medium">{{ formatearPrecio(datos.por_cobrar.saldo_facturas) }}</q-item-section>
                </q-item>
                <q-item class="q-px-none">
                  <q-item-section>
                    <q-item-label>Pedidos entregados sin facturar</q-item-label>
                    <q-item-label caption>{{ datos.por_cobrar.pedidos_sin_facturar }} pedido(s) esperando factura</q-item-label>
                  </q-item-section>
                  <q-item-section side class="text-weight-medium">{{ formatearPrecio(datos.por_cobrar.valor_pedidos_sin_facturar) }}</q-item-section>
                </q-item>
                <q-item class="q-px-none">
                  <q-item-section>
                    <q-item-label>Facturas anuladas</q-item-label>
                    <q-item-label caption>{{ datos.anuladas.cantidad }} en el periodo</q-item-label>
                  </q-item-section>
                  <q-item-section side class="text-weight-medium">{{ formatearPrecio(datos.anuladas.total) }}</q-item-section>
                </q-item>
              </q-list>
              <div class="text-caption text-grey-7 q-mt-sm">Lo pendiente es a hoy, sin importar el periodo elegido.</div>
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
        :rows="filas"
        :columns="columnas"
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
      >
        <template #body-cell-variacion="props">
          <q-td :props="props">
            <span v-if="props.row.variacion === null" class="text-grey-7">—</span>
            <span v-else>{{ props.row.variacion > 0 ? '+' : '' }}{{ formatearNumero(props.row.variacion) }}{{ props.row.tipo === 'puntos' ? ' pts' : ' %' }}</span>
          </q-td>
        </template>
      </q-table>
      <div class="text-caption text-grey-7 q-mt-sm">{{ datos.nota_costos }} Margen bruto = ventas netas − costo de insumos consumidos.</div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import reportesService from '@/services/reportesService'
import BarrasProporcion from '@/components/reportes/BarrasProporcion.vue'
import KpiTarjeta from '@/components/reportes/KpiTarjeta.vue'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { exportarCsv, formatearNumero, formatearPorcentaje } from '@/utils/reportes'

/** RF37: indicadores financieros con comparación contra el periodo anterior. */
const props = defineProps({
  rango: { type: Object, required: true },
})

const $q = useQuasar()
const datos = ref(null)
const cargando = ref(false)
let ultimaConsulta = 0

const k = computed(() => datos.value.kpis)

/** Lo cobrado se reparte en ventas netas, impuesto y propina. */
const composicion = computed(() => {
  const total = k.value.ventas_totales.valor
  const parte = (etiqueta, valor) => ({ etiqueta, texto: formatearPrecio(valor), porcentaje: total > 0 ? (valor / total) * 100 : 0 })

  return [
    parte('Ventas netas', k.value.ventas_netas.valor),
    parte('Impuestos', k.value.impuestos.valor),
    parte('Propinas', k.value.propinas.valor),
  ]
})

const metodos = computed(() =>
  datos.value.metodos_pago.map((m) => ({ etiqueta: m.etiqueta, texto: formatearPrecio(m.monto), porcentaje: m.porcentaje })),
)

const ETIQUETAS = {
  ventas_totales: ['Ventas totales', 'moneda'],
  ventas_netas: ['Ventas netas', 'moneda'],
  facturas: ['Facturas pagadas', 'numero'],
  ticket_promedio: ['Ticket promedio', 'moneda'],
  descuentos: ['Descuentos', 'moneda'],
  impuestos: ['Impuestos', 'moneda'],
  propinas: ['Propinas', 'moneda'],
  costo_insumos: ['Costo de insumos', 'moneda'],
  mermas: ['Mermas', 'moneda'],
  margen_bruto: ['Margen bruto', 'moneda'],
  margen_porcentaje: ['Margen (% de ventas netas)', 'porcentaje'],
}

function mostrar(valor, formato) {
  if (formato === 'moneda') return formatearPrecio(valor)
  if (formato === 'porcentaje') return formatearPorcentaje(valor)
  return formatearNumero(valor)
}

/** Vista en tabla de todos los indicadores. */
const filas = computed(() =>
  Object.entries(ETIQUETAS).map(([clave, [etiqueta, formato]]) => ({
    clave,
    etiqueta,
    formato,
    actual: k.value[clave].valor,
    anterior: k.value[clave].anterior,
    variacion: k.value[clave].variacion,
    tipo: k.value[clave].variacion_tipo,
  })),
)

const columnas = [
  { name: 'etiqueta', label: 'Indicador', field: 'etiqueta', align: 'left' },
  { name: 'actual', label: 'Periodo actual', field: (f) => mostrar(f.actual, f.formato), align: 'right' },
  { name: 'anterior', label: 'Periodo anterior', field: (f) => mostrar(f.anterior, f.formato), align: 'right' },
  { name: 'variacion', label: 'Variación', field: 'variacion', align: 'right' },
]

async function cargar() {
  const consulta = ++ultimaConsulta
  cargando.value = true
  try {
    const respuesta = await reportesService.indicadoresFinancieros(props.rango)
    if (consulta === ultimaConsulta) datos.value = respuesta
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    if (consulta === ultimaConsulta) cargando.value = false
  }
}

function exportar() {
  exportarCsv(
    `indicadores_financieros_${datos.value.periodo.desde}_${datos.value.periodo.hasta}`,
    [
      { etiqueta: 'Indicador', valor: (f) => f.etiqueta },
      { etiqueta: 'Periodo actual', valor: (f) => f.actual },
      { etiqueta: 'Periodo anterior', valor: (f) => f.anterior },
      { etiqueta: 'Variación', valor: (f) => f.variacion ?? '' },
      { etiqueta: 'Tipo de variación', valor: (f) => (f.tipo === 'puntos' ? 'puntos' : '%') },
    ],
    filas.value,
  )
}

watch(() => [props.rango.desde, props.rango.hasta], cargar, { immediate: true })
</script>
