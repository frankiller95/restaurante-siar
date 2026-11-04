<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col-12 col-sm">
        <h1 class="page-titulo">Carta del menú</h1>
        <p class="page-subtitulo">
          Productos agrupados por categoría. Los agotados no se pueden incluir en pedidos.
        </p>
      </div>
      <div class="col-12 col-sm-auto row q-gutter-sm items-center">
        <q-toggle v-model="soloDisponibles" label="Solo disponibles" color="primary" />
        <q-btn flat round icon="refresh" aria-label="Actualizar" :loading="cargando" @click="cargar">
          <q-tooltip>Actualizar</q-tooltip>
        </q-btn>
      </div>
    </div>

    <div v-if="cargando && carta.length === 0" class="row justify-center q-pa-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <q-banner v-else-if="carta.length === 0" rounded class="bg-grey-2 text-grey-8">
      Todavía no hay productos en el menú.
    </q-banner>

    <div v-for="categoria in cartaVisible" :key="categoria.id_categoria" class="q-mb-lg">
      <div class="row items-center q-mb-sm">
        <div class="text-h6">{{ categoria.nombre }}</div>
        <q-badge class="q-ml-sm" color="grey-5" :label="categoria.productos.length" />
      </div>

      <div class="row q-col-gutter-md">
        <div v-for="producto in categoria.productos" :key="producto.id_producto" class="col-12 col-sm-6 col-md-4 col-lg-3">
          <q-card flat bordered class="full-height" :class="{ 'producto-agotado': !producto.disponible }">
            <q-img v-if="producto.imagen_url" :src="producto.imagen_url" :ratio="4 / 3" fit="cover" :alt="producto.nombre">
              <div v-if="!producto.disponible" class="absolute-top-right bg-negative text-white text-weight-bold q-pa-xs">
                AGOTADO
              </div>
            </q-img>
            <q-card-section>
              <div class="row items-start no-wrap">
                <div class="col text-subtitle1 text-weight-medium">{{ producto.nombre }}</div>
                <div class="col-auto text-subtitle1 text-primary text-weight-bold q-ml-sm">
                  {{ formatearPrecio(producto.precio) }}
                </div>
              </div>
              <div v-if="producto.descripcion" class="text-body2 text-grey-7 q-mt-xs">{{ producto.descripcion }}</div>
              <q-badge v-if="!producto.disponible && !producto.imagen_url" color="negative" label="Agotado" class="q-mt-sm" />
            </q-card-section>
            <q-card-actions v-if="puedeCambiarDisponibilidad" class="q-px-md q-pt-none">
              <q-toggle
                :model-value="producto.disponible"
                :label="producto.disponible ? 'Disponible' : 'Agotado'"
                color="positive"
                dense
                :disable="cambiando === producto.id_producto"
                @update:model-value="(valor) => cambiarDisponibilidad(producto, valor)"
              />
            </q-card-actions>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import menuService from '@/services/menuService'
import { mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'

const $q = useQuasar()
const auth = useAuthStore()

const carta = ref([])
const cargando = ref(false)
const cambiando = ref(null)
const soloDisponibles = ref(false)

const puedeCambiarDisponibilidad = computed(() => auth.tienePermiso('gestionar_disponibilidad'))

const cartaVisible = computed(() =>
  carta.value
    .map((categoria) => ({
      ...categoria,
      productos: soloDisponibles.value ? categoria.productos.filter((p) => p.disponible) : categoria.productos,
    }))
    .filter((categoria) => categoria.productos.length > 0),
)

async function cargar() {
  cargando.value = true
  try {
    carta.value = await menuService.carta()
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

async function cambiarDisponibilidad(producto, disponible) {
  cambiando.value = producto.id_producto
  const anterior = producto.disponible
  producto.disponible = disponible
  try {
    const respuesta = await menuService.cambiarDisponibilidad(producto.id_producto, disponible)
    $q.notify({ type: disponible ? 'positive' : 'warning', message: respuesta.message })
  } catch (error) {
    producto.disponible = anterior
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cambiando.value = null
  }
}

onMounted(cargar)
</script>

<style scoped>
.producto-agotado {
  opacity: 0.6;
}
</style>
