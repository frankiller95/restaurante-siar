<template>
  <q-card flat bordered class="q-mb-md">
    <q-card-section class="row q-col-gutter-sm items-center">
      <div class="col-12 col-md-auto">
        <q-btn-toggle
          v-model="periodo"
          :options="opciones"
          no-caps
          unelevated
          dense
          toggle-color="primary"
          color="grey-3"
          text-color="grey-9"
          class="filtro-periodo"
          @update:model-value="elegirPeriodo"
        />
      </div>
      <div class="col-6 col-sm-4 col-md-2">
        <q-input v-model="fechas.desde" type="date" dense outlined stack-label label="Desde" :max="fechas.hasta" @update:model-value="personalizar" />
      </div>
      <div class="col-6 col-sm-4 col-md-2">
        <q-input v-model="fechas.hasta" type="date" dense outlined stack-label label="Hasta" :min="fechas.desde" :max="hoy()" @update:model-value="personalizar" />
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { hoy } from '@/utils/mesas'
import { PERIODOS, rangoDePeriodo } from '@/utils/reportes'

/** Selector de periodo compartido por todos los reportes: atajos y fechas a mano. */
const modelo = defineModel({ type: Object, required: true }) // { desde, hasta }

const opciones = PERIODOS.map((p) => ({ label: p.etiqueta, value: p.id }))
const periodo = ref('30')
const fechas = reactive({ desde: modelo.value.desde, hasta: modelo.value.hasta })

function elegirPeriodo(id) {
  Object.assign(fechas, rangoDePeriodo(id))
  modelo.value = { ...fechas }
}

/** Al escribir fechas a mano se desmarca el atajo; solo se consulta con un rango válido. */
function personalizar() {
  periodo.value = null
  if (fechas.desde && fechas.hasta && fechas.desde <= fechas.hasta) {
    modelo.value = { ...fechas }
  }
}

watch(modelo, (nuevo) => Object.assign(fechas, nuevo))
</script>

<style scoped>
.filtro-periodo {
  flex-wrap: wrap;
}
</style>
