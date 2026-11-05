<template>
  <q-dialog v-model="abierto" persistent @before-show="cargar">
    <q-card style="width: 620px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div>
          <div class="text-h6">Receta · {{ producto?.nombre }}</div>
          <div class="text-caption text-grey-7">Insumos que lleva una porción (en la unidad de cada insumo).</div>
        </div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-card-section v-if="cargando" class="row justify-center">
        <q-spinner color="primary" size="32px" />
      </q-card-section>

      <q-card-section v-else class="q-pt-none">
        <q-banner v-if="mensaje" dense rounded class="bg-red-1 text-negative q-mb-md">{{ mensaje }}</q-banner>

        <div v-for="(linea, indice) in lineas" :key="indice" class="row q-col-gutter-sm items-start q-mb-sm">
          <div class="col-12 col-sm-7">
            <q-select
              v-model="linea.id_insumo"
              :options="opcionesInsumo"
              option-value="id_insumo"
              option-label="nombre"
              emit-value
              map-options
              use-input
              input-debounce="0"
              dense
              outlined
              label="Insumo"
              @filter="filtrarInsumos"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.nombre }}</q-item-label>
                    <q-item-label caption>Stock: {{ formatearCantidad(scope.opt.stock_actual) }} {{ scope.opt.unidad }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>
          <div class="col-9 col-sm-4">
            <q-input
              v-model.number="linea.cantidad_por_porcion"
              type="number"
              step="any"
              min="0"
              dense
              outlined
              label="Por porción"
              :suffix="unidadDe(linea.id_insumo)"
            />
          </div>
          <div class="col-3 col-sm-1 text-right">
            <q-btn flat round dense icon="delete" color="negative" aria-label="Quitar insumo" @click="lineas.splice(indice, 1)" />
          </div>
        </div>

        <div v-if="lineas.length === 0" class="text-grey-7 q-mb-sm">
          Sin receta: este producto no descuenta inventario al pedirlo.
        </div>

        <q-btn flat no-caps color="primary" icon="add" label="Agregar insumo" @click="lineas.push({ id_insumo: null, cantidad_por_porcion: null })" />

        <q-separator class="q-my-md" />
        <div class="row items-center">
          <div class="col text-body2">Costo estimado por porción</div>
          <div class="text-subtitle1 text-weight-bold">{{ formatearPrecio(costoEstimado) }}</div>
        </div>
        <div v-if="producto?.precio" class="row items-center text-caption text-grey-7">
          <div class="col">Precio de venta {{ formatearPrecio(producto.precio) }}</div>
          <div>Margen {{ margen }}%</div>
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat no-caps label="Cancelar" v-close-popup />
        <q-btn color="primary" unelevated no-caps label="Guardar receta" :loading="guardando" :disable="cargando" @click="guardar" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import inventarioService from '@/services/inventarioService'
import { erroresPorCampo, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { formatearCantidad } from '@/utils/inventario'

const props = defineProps({ producto: { type: Object, default: null } })
const emit = defineEmits(['guardado'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const insumos = ref([])
const opcionesInsumo = ref([])
const lineas = ref([])
const cargando = ref(false)
const guardando = ref(false)
const mensaje = ref('')

function insumoPorId(id) {
  return insumos.value.find((i) => i.id_insumo === id)
}

function unidadDe(id) {
  return insumoPorId(id)?.unidad ?? ''
}

const costoEstimado = computed(() =>
  lineas.value.reduce((suma, l) => suma + (Number(l.cantidad_por_porcion) || 0) * (insumoPorId(l.id_insumo)?.costo_unitario ?? 0), 0),
)

const margen = computed(() => {
  const precio = props.producto?.precio ?? 0
  return precio > 0 ? Math.round(((precio - costoEstimado.value) / precio) * 100) : 0
})

function filtrarInsumos(texto, actualizar) {
  actualizar(() => {
    const buscado = texto.toLowerCase()
    opcionesInsumo.value = insumos.value.filter((i) => i.nombre.toLowerCase().includes(buscado))
  })
}

async function cargar() {
  if (!props.producto) return
  cargando.value = true
  mensaje.value = ''
  try {
    const [listado, receta] = await Promise.all([
      inventarioService.listar({ estado: 'activos', por_pagina: 100 }),
      inventarioService.receta(props.producto.id_producto),
    ])
    insumos.value = listado.data
    opcionesInsumo.value = listado.data
    lineas.value = receta.insumos.map((l) => ({ id_insumo: l.id_insumo, cantidad_por_porcion: l.cantidad_por_porcion }))
  } catch (error) {
    mensaje.value = mensajeError(error)
  } finally {
    cargando.value = false
  }
}

async function guardar() {
  const completas = lineas.value.filter((l) => l.id_insumo || l.cantidad_por_porcion)
  if (completas.some((l) => !l.id_insumo || !(l.cantidad_por_porcion > 0))) {
    mensaje.value = 'Cada insumo necesita una cantidad por porción mayor que cero.'
    return
  }

  guardando.value = true
  mensaje.value = ''
  try {
    const respuesta = await inventarioService.guardarReceta(props.producto.id_producto, completas)
    const aviso = respuesta.producto.agotado_por_inventario ? ' El producto quedó agotado: no hay insumos para una porción.' : ''
    $q.notify({ type: aviso ? 'warning' : 'positive', message: respuesta.message + aviso })
    abierto.value = false
    emit('guardado', respuesta)
  } catch (error) {
    const errores = erroresPorCampo(error)
    mensaje.value = Object.values(errores)[0] || mensajeError(error)
  } finally {
    guardando.value = false
  }
}
</script>
