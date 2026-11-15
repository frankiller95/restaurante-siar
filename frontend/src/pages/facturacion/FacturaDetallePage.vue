<template>
  <q-page padding>
    <div v-if="!factura && cargando" class="row justify-center q-pa-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <template v-else-if="factura">
      <div class="row items-center q-mb-md q-col-gutter-sm">
        <div class="col-auto">
          <q-btn flat round icon="arrow_back" aria-label="Volver" :to="{ name: 'facturacion' }" />
        </div>
        <div class="col">
          <h1 class="page-titulo">Factura {{ factura.numero }}</h1>
          <p class="page-subtitulo">
            Pedido #{{ factura.id_pedido }} · {{ lugarPedido(factura.pedido) }} · emitida {{ formatearFecha(factura.fecha_emision) }}
            <template v-if="factura.cajero"> por {{ factura.cajero }}</template>
          </p>
        </div>
        <div class="col-12 col-sm-auto">
          <q-badge :color="ESTADOS_FACTURA[factura.estado].color" class="text-body2 q-pa-sm">
            <q-icon :name="ESTADOS_FACTURA[factura.estado].icono" class="q-mr-xs" />
            {{ factura.estado_etiqueta }}
          </q-badge>
        </div>
      </div>

      <q-banner v-if="factura.estado === 'anulada'" rounded class="bg-grey-3 text-grey-9 q-mb-md">
        Factura anulada {{ factura.fecha_anulacion ? `el ${formatearFecha(factura.fecha_anulacion)}` : '' }}
        {{ factura.motivo_anulacion ? `· Motivo: ${factura.motivo_anulacion}` : '' }}.
        El pedido sigue entregado y se puede facturar de nuevo desde "Por cobrar".
      </q-banner>
      <q-banner v-else-if="factura.estado === 'pagada'" rounded class="bg-green-1 text-positive q-mb-md">
        <template #avatar><q-icon name="check_circle" color="positive" /></template>
        Factura pagada {{ factura.fecha_pago ? `el ${formatearFecha(factura.fecha_pago)}` : '' }}. El pedido #{{ factura.id_pedido }} quedó facturado.
        <template v-if="puedeLiberarMesa" #action>
          <q-btn flat no-caps color="positive" icon="logout" :label="`Liberar mesa ${factura.pedido.mesa.numero}`" :loading="liberando" @click="liberarMesa" />
        </template>
      </q-banner>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-7">
          <q-card flat bordered>
            <q-card-section class="q-pb-none">
              <div class="text-subtitle1 text-weight-medium">Cliente</div>
              <div class="text-body2">
                {{ factura.cliente_nombre }}
                <span v-if="factura.cliente_documento" class="text-grey-7"> · {{ factura.cliente_documento }}</span>
              </div>
            </q-card-section>

            <q-card-section class="q-pb-none">
              <div class="text-subtitle1 text-weight-medium">Productos</div>
            </q-card-section>
            <q-list separator>
              <q-item v-for="linea in factura.detalles" :key="linea.id_detalle_factura">
                <q-item-section side top><span class="text-weight-bold">{{ linea.cantidad }}×</span></q-item-section>
                <q-item-section>
                  <q-item-label>{{ linea.descripcion }}</q-item-label>
                  <q-item-label v-if="linea.adiciones" caption>Adiciones: {{ linea.adiciones }}</q-item-label>
                  <q-item-label caption>
                    {{ formatearPrecio(linea.precio_unitario) }} c/u
                    <span v-if="linea.descuento > 0" class="text-positive"> · promoción −{{ formatearPrecio(linea.descuento) }}</span>
                  </q-item-label>
                </q-item-section>
                <q-item-section side class="text-weight-medium">{{ formatearPrecio(linea.subtotal) }}</q-item-section>
              </q-item>
            </q-list>
          </q-card>

          <!-- RF31: pagos registrados -->
          <q-card flat bordered class="q-mt-md">
            <q-card-section class="q-pb-none">
              <div class="text-subtitle1 text-weight-medium">Pagos</div>
            </q-card-section>
            <q-card-section v-if="factura.pagos.length === 0" class="text-grey-7">Todavía no hay pagos registrados.</q-card-section>
            <q-list v-else separator>
              <q-item v-for="pago in factura.pagos" :key="pago.id_pago">
                <q-item-section avatar>
                  <q-icon :name="METODOS_PAGO[pago.metodo].icono" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ pago.metodo_etiqueta }}</q-item-label>
                  <q-item-label caption>
                    {{ formatearFecha(pago.fecha) }}<template v-if="pago.cajero"> · {{ pago.cajero }}</template>
                    <template v-if="pago.referencia"> · Ref. {{ pago.referencia }}</template>
                    <template v-if="pago.cambio > 0"> · recibido {{ formatearPrecio(pago.monto_recibido) }}, cambio {{ formatearPrecio(pago.cambio) }}</template>
                  </q-item-label>
                </q-item-section>
                <q-item-section side class="text-weight-medium">{{ formatearPrecio(pago.monto) }}</q-item-section>
              </q-item>
            </q-list>
          </q-card>
        </div>

        <div class="col-12 col-md-5">
          <q-card flat bordered>
            <q-card-section>
              <FacturaTotales :resumen="factura" />
              <div v-if="factura.descuento > 0 && factura.motivo_descuento" class="text-caption text-grey-7 q-mt-sm">
                Motivo del descuento: {{ factura.motivo_descuento }}
              </div>
            </q-card-section>

            <template v-if="factura.estado !== 'anulada'">
              <q-separator />
              <q-card-section>
                <div class="row justify-between">
                  <span class="text-grey-8">Pagado</span>
                  <span class="text-positive text-weight-medium">{{ formatearPrecio(factura.total_pagado) }}</span>
                </div>
                <div class="row justify-between items-center q-mt-xs">
                  <span class="text-subtitle1 text-weight-medium">Saldo</span>
                  <span class="text-h6 text-weight-bold" :class="factura.saldo > 0 ? 'text-negative' : 'text-grey-7'">
                    {{ formatearPrecio(factura.saldo) }}
                  </span>
                </div>
              </q-card-section>
            </template>

            <q-separator />
            <q-card-actions vertical class="q-pa-md q-gutter-sm">
              <q-btn
                v-if="factura.puede_pagar"
                class="full-width"
                color="positive"
                unelevated
                no-caps
                icon="point_of_sale"
                label="Registrar pago"
                @click="pagoAbierto = true"
              />
              <q-btn
                v-if="factura.puede_aplicar_descuento"
                class="full-width"
                outline
                no-caps
                color="primary"
                icon="percent"
                :label="factura.descuento > 0 ? 'Cambiar descuento' : 'Aplicar descuento'"
                @click="descuentoAbierto = true"
              />
              <q-btn
                v-if="factura.estado !== 'anulada'"
                class="full-width"
                outline
                no-caps
                color="primary"
                icon="print"
                :label="factura.estado === 'pagada' ? 'Comprobante' : 'Imprimir cuenta'"
                @click="comprobanteAbierto = true"
              />
              <q-btn
                v-if="factura.puede_anular"
                class="full-width"
                flat
                no-caps
                color="negative"
                icon="block"
                label="Anular factura"
                :loading="anulando"
                @click="anular"
              />
              <q-btn
                class="full-width"
                flat
                no-caps
                color="grey-8"
                icon="receipt"
                :label="`Ver pedido #${factura.id_pedido}`"
                :to="{ name: 'pedido', params: { id: factura.id_pedido } }"
              />
            </q-card-actions>
          </q-card>
        </div>
      </div>

      <PagoDialog v-model="pagoAbierto" :factura="factura" @guardado="despuesDePago" />
      <DescuentoDialog v-model="descuentoAbierto" :factura="factura" @guardado="actualizar" />
      <ComprobanteDialog v-model="comprobanteAbierto" :id-factura="factura.id_factura" @emitido="cargar" />
    </template>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import facturasService from '@/services/facturasService'
import mesasService from '@/services/mesasService'
import { useAuthStore } from '@/stores/auth'
import ComprobanteDialog from '@/components/facturacion/ComprobanteDialog.vue'
import DescuentoDialog from '@/components/facturacion/DescuentoDialog.vue'
import FacturaTotales from '@/components/facturacion/FacturaTotales.vue'
import PagoDialog from '@/components/facturacion/PagoDialog.vue'
import { formatearFecha, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { ESTADOS_FACTURA, METODOS_PAGO, lugarPedido } from '@/utils/facturacion'

const $q = useQuasar()
const route = useRoute()
const auth = useAuthStore()

const factura = ref(null)
const cargando = ref(false)
const pagoAbierto = ref(false)
const descuentoAbierto = ref(false)
const comprobanteAbierto = ref(false)
const anulando = ref(false)
const liberando = ref(false)

// RF16: tras el pago, el cajero puede dejar la mesa libre si ya no tiene cuentas abiertas.
const puedeLiberarMesa = computed(() => {
  const mesa = factura.value?.pedido?.mesa
  return (
    mesa?.estado === 'ocupada' &&
    (auth.tienePermiso('gestionar_reservas') || auth.tienePermiso('gestionar_mesas'))
  )
})

function actualizar(datos) {
  factura.value = datos
}

async function cargar() {
  cargando.value = true
  try {
    factura.value = await facturasService.obtener(route.params.id)
  } catch (error) {
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    cargando.value = false
  }
}

/** El diálogo entrega la factura actualizada; si entrega null, otro usuario la cambió y se recarga. */
function despuesDePago(datos, cambio) {
  if (!datos) return cargar()
  actualizar(datos)
  if (cambio > 0) {
    $q.notify({ type: 'info', icon: 'payments', timeout: 8000, message: `Devuelve ${formatearPrecio(cambio)} de cambio.` })
  }
  if (datos.estado === 'pagada') comprobanteAbierto.value = true
}

function anular() {
  $q.dialog({
    title: 'Anular factura',
    message: `La factura ${factura.value.numero} quedará anulada y el pedido se podrá facturar de nuevo. Escribe el motivo:`,
    prompt: { model: '', type: 'text', isValid: (valor) => valor.trim().length >= 3 },
    cancel: { flat: true, noCaps: true, label: 'Volver' },
    ok: { color: 'negative', unelevated: true, noCaps: true, label: 'Anular factura' },
    persistent: true,
  }).onOk(async (motivo) => {
    anulando.value = true
    try {
      const respuesta = await facturasService.anular(factura.value.id_factura, motivo.trim())
      $q.notify({ type: 'positive', message: respuesta.message })
      actualizar(respuesta.data)
    } catch (error) {
      $q.notify({ type: 'negative', message: mensajeError(error) })
      if (error.response?.status === 409) cargar()
    } finally {
      anulando.value = false
    }
  })
}

async function liberarMesa() {
  liberando.value = true
  try {
    await mesasService.cambiarEstado(factura.value.pedido.mesa.id_mesa, 'liberar')
    $q.notify({ type: 'positive', message: `Mesa ${factura.value.pedido.mesa.numero} liberada.` })
    await cargar()
  } catch (error) {
    // Por ejemplo: la mesa todavía tiene otro pedido sin facturar.
    $q.notify({ type: 'negative', message: mensajeError(error) })
  } finally {
    liberando.value = false
  }
}

onMounted(cargar)
</script>
