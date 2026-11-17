<template>
  <div>
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm">
        <div class="text-subtitle1 text-weight-medium">Ocupación de mesas</div>
        <div class="text-caption text-grey-7">
          Tiempo en que cada mesa tuvo un pedido (desde que se toma hasta que se paga o se entrega), sobre
          {{ datos ? formatearNumero(datos.horas_operacion) : '…' }} h de atención al día.
        </div>
      </div>
      <div class="col-12 col-sm-auto">
        <q-btn outline no-caps dense color="primary" icon="download" label="Exportar CSV" :disable="!datos" @click="exportar" />
      </div>
    </div>

    <div v-if="cargando && !datos" class="row justify-center q-pa-xl"><q-spinner color="primary" size="40px" /></div>

    <template v-if="datos">
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-6 col-md-3"><KpiTarjeta etiqueta="Ocupación promedio" :valor="formatearPorcentaje(datos.resumen.ocupacion_promedio)" destacado /></div>
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Permanencia promedio" :valor="formatearMinutos(datos.resumen.permanencia_promedio_min)" /></div>
        <div class="col-6 col-md-3"><KpiTarjeta etiqueta="Rotación diaria" :valor="formatearNumero(datos.resumen.rotacion_diaria)" nota="veces que se usa cada mesa por día" /></div>
        <div class="col-12 col-sm-6 col-md-3">
          <KpiTarjeta etiqueta="Horas de mesa ocupada" :valor="formatearNumero(datos.resumen.horas_ocupadas)" :nota="`${datos.resumen.pedidos} pedidos · ${datos.resumen.mesas} mesas`" />
        </div>
      </div>

      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-lg-7">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">¿Cuándo se llenan las mesas?</div>
              <MapaCalorOcupacion :mapa="datos.mapa_calor" />
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-lg-5">
          <q-card flat bordered class="full-height">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">Ocupación por mesa</div>
              <GraficoReporte
                horizontal
                valores-en-puntas
                formato="porcentaje"
                :alto="altoGrafico"
                :etiquetas="datos.mesas.map((m) => `Mesa ${m.numero}`)"
                :series="[{ nombre: 'Ocupación', datos: datos.mesas.map((m) => m.ocupacion) }]"
                :detalles="datos.mesas.map((m) => [`${formatearNumero(m.horas_ocupada)} h ocupada · ${m.pedidos} pedidos`])"
                descripcion="Porcentaje de ocupación de cada mesa en el periodo"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <q-card flat bordered class="q-mb-md">
        <q-card-section class="row items-center q-col-gutter-md">
          <div class="col-12 col-sm-auto text-subtitle2">Reservas del periodo</div>
          <div class="col-auto"><q-chip dense square outline icon="event" :label="`${datos.reservas.total} en total`" /></div>
          <div class="col-auto"><q-chip dense square outline icon="check_circle" :label="`${datos.reservas.cumplidas} cumplidas`" /></div>
          <div class="col-auto"><q-chip dense square outline icon="hourglass_top" :label="`${datos.reservas.pendientes} pendientes`" /></div>
          <div class="col-auto"><q-chip dense square outline icon="cancel" :label="`${datos.reservas.canceladas} canceladas`" /></div>
          <div class="col-auto"><q-chip dense square outline icon="person_off" :label="`${datos.reservas.no_asistio} no asistieron`" /></div>
        </q-card-section>
      </q-card>

      <q-table
        class="tabla-siar"
        flat
        bordered
        dense
        row-key="id_mesa"
        :rows="datos.mesas"
        :columns="columnas"
        :loading="cargando"
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        no-data-label="No hay mesas activas."
      />
      <div class="text-caption text-grey-7 q-mt-sm">
        Un pedido sin cerrar cuenta como máximo {{ formatearNumero(maximoHoras) }} h. Los días que todavía no llegan no cuentan como tiempo disponible.
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
import MapaCalorOcupacion from '@/components/reportes/MapaCalorOcupacion.vue'
import { mensajeError } from '@/utils/errores'
import { exportarCsv, formatearMinutos, formatearNumero, formatearPorcentaje } from '@/utils/reportes'

/** RF36: ocupación de mesas (mapa de calor, ranking por mesa y reservas). */
const props = defineProps({
  rango: { type: Object, required: true },
})

const $q = useQuasar()
const datos = ref(null)
const cargando = ref(false)
let ultimaConsulta = 0

// Mismo valor por defecto que el servidor (config siar.reportes.maximo_horas_por_pedido).
const maximoHoras = 6
const altoGrafico = computed(() => Math.max(180, (datos.value?.mesas.length ?? 0) * 34 + 40))

const columnas = [
  { name: 'numero', label: 'Mesa', field: 'numero', align: 'left', format: (v) => `Mesa ${v}` },
  { name: 'ubicacion', label: 'Ubicación', field: 'ubicacion', align: 'left', format: (v) => v ?? '—' },
  { name: 'capacidad', label: 'Capacidad', field: 'capacidad', align: 'right', format: (v) => `${v} personas` },
  { name: 'pedidos', label: 'Pedidos', field: 'pedidos', align: 'right' },
  { name: 'sesiones', label: 'Veces ocupada', field: 'sesiones', align: 'right' },
  { name: 'horas', label: 'Horas ocupada', field: 'horas_ocupada', align: 'right', format: formatearNumero },
  { name: 'ocupacion', label: 'Ocupación', field: 'ocupacion', align: 'right', format: formatearPorcentaje },
  { name: 'permanencia', label: 'Permanencia promedio', field: 'permanencia_promedio_min', align: 'right', format: formatearMinutos },
]

async function cargar() {
  const consulta = ++ultimaConsulta
  cargando.value = true
  try {
    const respuesta = await reportesService.ocupacionMesas(props.rango)
    if (consulta === ultimaConsulta) datos.value = respuesta
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    if (consulta === ultimaConsulta) cargando.value = false
  }
}

function exportar() {
  exportarCsv(
    `ocupacion_mesas_${datos.value.periodo.desde}_${datos.value.periodo.hasta}`,
    [
      { etiqueta: 'Mesa', valor: (m) => m.numero },
      { etiqueta: 'Ubicación', valor: (m) => m.ubicacion ?? '' },
      { etiqueta: 'Capacidad', valor: (m) => m.capacidad },
      { etiqueta: 'Pedidos', valor: (m) => m.pedidos },
      { etiqueta: 'Veces ocupada', valor: (m) => m.sesiones },
      { etiqueta: 'Horas ocupada', valor: (m) => m.horas_ocupada },
      { etiqueta: 'Ocupación (%)', valor: (m) => m.ocupacion },
      { etiqueta: 'Permanencia promedio (min)', valor: (m) => m.permanencia_promedio_min },
    ],
    datos.value.mesas,
  )
}

watch(() => [props.rango.desde, props.rango.hasta], cargar, { immediate: true })
</script>
