<template>
  <q-page padding>
    <div v-if="!pedido && cargando" class="row justify-center q-pa-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <template v-else-if="pedido">
      <div class="row items-center q-mb-md q-col-gutter-sm">
        <div class="col-auto">
          <q-btn flat round icon="arrow_back" aria-label="Volver" :to="{ name: 'pedidos' }" />
        </div>
        <div class="col">
          <h1 class="page-titulo">Pedido #{{ pedido.id_pedido }} · Mesa {{ pedido.mesa?.numero }}</h1>
          <p class="page-subtitulo">Tomado por {{ pedido.mesero }} · {{ formatearFecha(pedido.fecha_creacion) }}</p>
        </div>
        <div class="col-12 col-sm-auto">
          <q-badge :color="colorEstado(pedido)" class="text-body2 q-pa-sm">
            <q-icon :name="ESTADOS_PEDIDO[pedido.estado].icono" class="q-mr-xs" />
            {{ textoEstado(pedido) }}
          </q-badge>
        </div>
      </div>

      <q-banner v-if="pedido.estado === 'cancelado'" rounded class="bg-red-1 text-negative q-mb-md">
        Pedido cancelado {{ pedido.motivo_cancelacion ? `· Motivo: ${pedido.motivo_cancelacion}` : '' }}
      </q-banner>

      <div class="row q-col-gutter-md">
        <!-- Productos del pedido -->
        <div class="col-12" :class="pedido.editable && puedeTomar ? 'col-md-7' : 'col-md-8'">
          <q-card flat bordered>
            <q-card-section class="row items-center q-pb-none">
              <div class="text-subtitle1 text-weight-medium col">Productos</div>
              <div class="text-caption text-grey-7">{{ pedido.cantidad_productos }} en total</div>
            </q-card-section>

            <q-list separator>
              <q-item v-if="pedido.detalles.length === 0">
                <q-item-section class="text-grey-7">
                  {{ pedido.editable && puedeTomar ? 'Agrega productos desde la carta.' : 'Sin productos.' }}
                </q-item-section>
              </q-item>
              <q-item v-for="linea in pedido.detalles" :key="linea.id_detalle">
                <q-item-section v-if="pedido.editable && puedeTomar" side>
                  <div class="row items-center no-wrap">
                    <q-btn round dense flat icon="remove" size="sm" aria-label="Uno menos" :disable="ocupado" @click="cambiarCantidad(linea, -1)" />
                    <span class="text-weight-bold q-mx-xs" style="min-width: 20px; text-align: center">{{ linea.cantidad }}</span>
                    <q-btn round dense flat icon="add" size="sm" aria-label="Uno más" :disable="ocupado" @click="cambiarCantidad(linea, 1)" />
                  </div>
                </q-item-section>
                <q-item-section v-else side>
                  <span class="text-weight-bold">{{ linea.cantidad }}×</span>
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ linea.producto }}</q-item-label>
                  <q-item-label caption>
                    {{ formatearPrecio(linea.precio_unitario) }} c/u
                    <span v-if="linea.observacion" class="text-warning text-weight-medium"> · {{ linea.observacion }}</span>
                    <span v-if="pedido.editable && puedeTomar" class="text-primary cursor-pointer q-ml-xs">
                      <q-icon name="edit_note" /> {{ linea.observacion ? 'editar nota' : 'agregar nota' }}
                      <q-popup-edit v-slot="scope" :model-value="linea.observacion ?? ''" auto-save @save="(valor) => cambiarNota(linea, valor)">
                        <q-input v-model="scope.value" dense autofocus maxlength="255" label="Nota para cocina" placeholder="Ej. sin cebolla" @keyup.enter="scope.set" />
                      </q-popup-edit>
                    </span>
                  </q-item-label>
                </q-item-section>
                <q-item-section side class="text-weight-medium">{{ formatearPrecio(linea.subtotal) }}</q-item-section>
                <q-item-section v-if="pedido.editable && puedeTomar" side>
                  <q-btn flat round dense icon="delete" color="negative" size="sm" aria-label="Quitar" :disable="ocupado" @click="quitar(linea)" />
                </q-item-section>
              </q-item>
            </q-list>

            <q-separator />
            <q-card-section class="row items-center">
              <div class="col text-subtitle1">Total</div>
              <div class="text-h6 text-primary text-weight-bold">{{ formatearPrecio(pedido.total) }}</div>
            </q-card-section>

            <q-card-section class="q-pt-none">
              <q-input
                v-model="observaciones"
                label="Observaciones del pedido"
                outlined
                dense
                autogrow
                maxlength="255"
                :readonly="!(pedido.editable && puedeTomar)"
                @blur="guardarObservaciones"
              />
            </q-card-section>

            <!-- Acciones según el estado -->
            <q-card-actions align="right" class="q-px-md q-pb-md q-gutter-sm">
              <q-btn
                v-if="puedeCancelar"
                flat
                no-caps
                color="negative"
                icon="cancel"
                label="Cancelar pedido"
                :disable="ocupado"
                @click="cancelar"
              />
              <q-btn
                v-if="pedido.editable && puedeTomar"
                unelevated
                no-caps
                color="primary"
                icon="send"
                label="Enviar a cocina"
                :disable="pedido.detalles.length === 0"
                :loading="accion === 'enviar'"
                @click="ejecutar('enviar', () => pedidosService.enviarACocina(pedido.id_pedido))"
              />
              <q-btn
                v-if="puedePreparar && pedido.estado === 'pendiente' && pedido.enviado_cocina"
                unelevated
                no-caps
                color="warning"
                icon="soup_kitchen"
                label="Iniciar preparación"
                :loading="accion === 'preparar'"
                @click="ejecutar('preparar', () => pedidosService.preparar(pedido.id_pedido))"
              />
              <q-btn
                v-if="puedePreparar && pedido.estado === 'en_preparacion'"
                unelevated
                no-caps
                color="positive"
                icon="notifications_active"
                label="Marcar listo"
                :loading="accion === 'listo'"
                @click="ejecutar('listo', () => pedidosService.marcarListo(pedido.id_pedido))"
              />
              <q-btn
                v-if="puedeTomar && pedido.estado === 'listo'"
                unelevated
                no-caps
                color="info"
                icon="room_service"
                label="Entregar en la mesa"
                :loading="accion === 'entregar'"
                @click="ejecutar('entregar', () => pedidosService.entregar(pedido.id_pedido))"
              />
            </q-card-actions>
          </q-card>

          <!-- RF22: línea de tiempo del estado -->
          <q-card flat bordered class="q-mt-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium q-mb-sm">Seguimiento</div>
              <q-timeline color="primary" layout="dense">
                <q-timeline-entry
                  v-for="paso in pasos"
                  :key="paso.titulo"
                  :title="paso.titulo"
                  :subtitle="paso.fecha ? formatearFecha(paso.fecha) : 'Pendiente'"
                  :icon="paso.icono"
                  :color="paso.fecha ? paso.color : 'grey-4'"
                />
              </q-timeline>
            </q-card-section>
          </q-card>
        </div>

        <!-- RF18: carta para agregar productos -->
        <div v-if="pedido.editable && puedeTomar" class="col-12 col-md-5">
          <q-card flat bordered>
            <q-card-section class="q-pb-sm">
              <div class="text-subtitle1 text-weight-medium">Agregar de la carta</div>
              <q-input v-model="busqueda" dense outlined clearable placeholder="Buscar producto" class="q-mt-sm">
                <template #prepend><q-icon name="search" /></template>
              </q-input>
            </q-card-section>
            <q-scroll-area style="height: 60vh">
              <div v-for="categoria in cartaFiltrada" :key="categoria.id_categoria" class="q-px-md q-pb-md">
                <div class="text-caption text-weight-bold text-grey-7 q-mb-xs">{{ categoria.nombre.toUpperCase() }}</div>
                <q-list bordered separator class="rounded-borders">
                  <q-item
                    v-for="producto in categoria.productos"
                    :key="producto.id_producto"
                    :clickable="producto.disponible"
                    :class="{ 'text-grey-5': !producto.disponible }"
                    @click="producto.disponible && agregar(producto)"
                  >
                    <q-item-section>
                      <q-item-label>{{ producto.nombre }}</q-item-label>
                      <q-item-label caption>
                        {{ producto.disponible ? formatearPrecio(producto.precio) : 'Agotado' }}
                      </q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-btn v-if="producto.disponible" round dense unelevated color="primary" icon="add" size="sm" aria-label="Agregar" :disable="ocupado" />
                    </q-item-section>
                  </q-item>
                </q-list>
              </div>
              <div v-if="cartaFiltrada.length === 0" class="q-pa-md text-grey-7">No hay productos que coincidan.</div>
            </q-scroll-area>
          </q-card>
        </div>
      </div>
    </template>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import pedidosService from '@/services/pedidosService'
import menuService from '@/services/menuService'
import { formatearFecha, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { colorEstado, ESTADOS_PEDIDO, textoEstado } from '@/utils/pedidos'

const $q = useQuasar()
const route = useRoute()
const auth = useAuthStore()

const puedeTomar = computed(() => auth.tienePermiso('tomar_pedidos'))
const puedePreparar = computed(() => auth.tienePermiso('preparar_pedidos'))

const pedido = ref(null)
const cargando = ref(false)
const ocupado = ref(false)
const accion = ref(null)
const observaciones = ref('')
const carta = ref([])
const busqueda = ref('')

const puedeCancelar = computed(() => puedeTomar.value && pedido.value?.acciones.includes('cancelado'))

const pasos = computed(() => {
  const p = pedido.value
  if (!p) return []
  const lista = [
    { titulo: 'Pedido tomado', fecha: p.fecha_creacion, icono: 'edit_note', color: 'grey-7' },
    { titulo: 'Enviado a cocina', fecha: p.fecha_envio_cocina, icono: 'send', color: 'primary' },
    { titulo: 'En preparación', fecha: p.fecha_inicio_preparacion, icono: 'soup_kitchen', color: 'warning' },
    { titulo: 'Listo', fecha: p.fecha_listo, icono: 'notifications_active', color: 'positive' },
    { titulo: 'Entregado', fecha: p.fecha_entrega, icono: 'room_service', color: 'info' },
  ]
  if (p.estado === 'cancelado') {
    lista.push({ titulo: 'Cancelado', fecha: p.fecha_cancelacion, icono: 'cancel', color: 'negative' })
  }
  return lista
})

const cartaFiltrada = computed(() => {
  const texto = (busqueda.value || '').trim().toLowerCase()
  return carta.value
    .map((categoria) => ({
      ...categoria,
      productos: categoria.productos.filter((p) => !texto || p.nombre.toLowerCase().includes(texto)),
    }))
    .filter((categoria) => categoria.productos.length > 0)
})

function actualizar(respuesta) {
  pedido.value = respuesta.data ?? respuesta
  observaciones.value = pedido.value.observaciones ?? ''
}

async function cargar() {
  cargando.value = true
  try {
    actualizar(await pedidosService.obtener(route.params.id))
    if (pedido.value.editable && puedeTomar.value && carta.value.length === 0) {
      carta.value = await menuService.carta()
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

/** Envuelve una llamada que devuelve el pedido actualizado. */
async function conPedido(llamada, mensajeOk = null) {
  ocupado.value = true
  try {
    const respuesta = await llamada()
    actualizar(respuesta)
    if (mensajeOk) $q.notify({ type: 'positive', message: mensajeOk === true ? respuesta.message : mensajeOk })
    return true
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
    // Si alguien cambió el pedido (ej. ya se envió), se recarga.
    if (error.response?.status === 409) cargar()
    return false
  } finally {
    ocupado.value = false
  }
}

function agregar(producto) {
  conPedido(() => pedidosService.agregarProducto(pedido.value.id_pedido, producto.id_producto, 1), `${producto.nombre} agregado.`)
}

function cambiarCantidad(linea, delta) {
  const nueva = linea.cantidad + delta
  if (nueva < 1) return quitar(linea)
  if (nueva > 50) return
  conPedido(() => pedidosService.modificarProducto(pedido.value.id_pedido, linea.id_detalle, nueva, linea.observacion))
}

function cambiarNota(linea, valor) {
  const nota = (valor || '').trim() || null
  conPedido(() => pedidosService.modificarProducto(pedido.value.id_pedido, linea.id_detalle, linea.cantidad, nota))
}

function quitar(linea) {
  conPedido(() => pedidosService.quitarProducto(pedido.value.id_pedido, linea.id_detalle), `${linea.producto} quitado.`)
}

function guardarObservaciones() {
  const actual = pedido.value.observaciones ?? ''
  if (!pedido.value.editable || observaciones.value === actual) return
  conPedido(() => pedidosService.actualizarObservaciones(pedido.value.id_pedido, observaciones.value || null))
}

async function ejecutar(nombre, llamada) {
  accion.value = nombre
  await conPedido(llamada, true)
  accion.value = null
}

/** RF20: en preparación se pide el motivo (el backend lo exige). */
function cancelar() {
  const enPreparacion = pedido.value.estado === 'en_preparacion'
  $q.dialog({
    title: 'Cancelar pedido',
    message: enPreparacion
      ? 'El pedido ya se está preparando. Escribe el motivo de la cancelación:'
      : '¿Seguro que quieres cancelar este pedido? Puedes indicar el motivo (opcional):',
    prompt: { model: '', type: 'text', isValid: (valor) => !enPreparacion || valor.trim().length > 0 },
    cancel: { flat: true, noCaps: true, label: 'Volver' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Cancelar pedido' },
    persistent: true,
  }).onOk((motivo) => {
    conPedido(() => pedidosService.cancelar(pedido.value.id_pedido, motivo?.trim() || null), true)
  })
}

onMounted(cargar)
</script>
