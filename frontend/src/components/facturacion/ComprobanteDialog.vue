<template>
  <q-dialog v-model="abierto" @before-show="cargar">
    <q-card style="width: 400px; max-width: 95vw">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Comprobante</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup aria-label="Cerrar" />
      </q-card-section>

      <q-card-section v-if="cargando" class="row justify-center q-pa-xl">
        <q-spinner color="primary" size="40px" />
      </q-card-section>
      <q-banner v-else-if="error" dense rounded class="bg-red-1 text-negative q-ma-md">{{ error }}</q-banner>

      <q-card-section v-else-if="comprobante" class="q-pt-sm">
        <q-scroll-area style="height: 60vh">
          <!-- Mismo estilo en pantalla y en la ventana de impresión -->
          <component :is="'style'">{{ ESTILOS_TICKET }}</component>
          <div ref="ticket" class="tk">
            <div class="tk-centro">
              <div class="tk-titulo">{{ comprobante.emisor.nombre }}</div>
              <div v-if="comprobante.emisor.nit" class="tk-aparte">NIT {{ comprobante.emisor.nit }}</div>
              <div v-if="comprobante.emisor.direccion" class="tk-aparte">{{ comprobante.emisor.direccion }}</div>
              <div v-if="comprobante.emisor.telefono" class="tk-aparte">Tel. {{ comprobante.emisor.telefono }}</div>
            </div>

            <div class="tk-linea"></div>
            <div class="tk-centro tk-negrita">{{ comprobante.titulo.toUpperCase() }}</div>
            <div class="tk-centro">{{ factura.numero }}</div>
            <div class="tk-centro tk-aparte">{{ formatearFecha(factura.fecha_emision) }}</div>

            <div class="tk-linea"></div>
            <div>Cliente: {{ factura.cliente_nombre }}</div>
            <div v-if="factura.cliente_documento">NIT/CC: {{ factura.cliente_documento }}</div>
            <div>{{ lugarPedido(factura.pedido) }} · Pedido #{{ factura.id_pedido }}</div>
            <div v-if="factura.cajero">Atendió: {{ factura.cajero }}</div>

            <div class="tk-linea"></div>
            <div v-for="linea in factura.detalles" :key="linea.id_detalle_factura" class="tk-item">
              <div class="tk-fila">
                <span>{{ linea.cantidad }} × {{ linea.descripcion }}</span>
                <span>{{ formatearPrecio(linea.subtotal) }}</span>
              </div>
              <div v-if="linea.adiciones" class="tk-aparte">&nbsp;&nbsp;+ {{ linea.adiciones }}</div>
            </div>

            <div class="tk-linea"></div>
            <div class="tk-fila"><span>Subtotal</span><span>{{ formatearPrecio(factura.subtotal) }}</span></div>
            <div v-if="factura.descuento_promociones > 0" class="tk-fila">
              <span>Promociones</span><span>-{{ formatearPrecio(factura.descuento_promociones) }}</span>
            </div>
            <div v-if="factura.descuento > 0" class="tk-fila">
              <span>Descuento</span><span>-{{ formatearPrecio(factura.descuento) }}</span>
            </div>
            <div class="tk-fila">
              <span>Base gravable</span><span>{{ formatearPrecio(factura.base_gravable) }}</span>
            </div>
            <div class="tk-fila">
              <span>{{ factura.impuesto_nombre }} {{ factura.impuesto_porcentaje }}%{{ factura.impuesto_incluido ? ' (incl.)' : '' }}</span>
              <span>{{ formatearPrecio(factura.impuesto) }}</span>
            </div>
            <div v-if="factura.propina > 0" class="tk-fila">
              <span>Propina</span><span>{{ formatearPrecio(factura.propina) }}</span>
            </div>
            <div class="tk-linea"></div>
            <div class="tk-fila tk-total"><span>TOTAL</span><span>{{ formatearPrecio(factura.total) }}</span></div>

            <template v-if="factura.pagos.length">
              <div class="tk-linea"></div>
              <div class="tk-negrita">Pagos</div>
              <div v-for="pago in factura.pagos" :key="pago.id_pago" class="tk-item">
                <div class="tk-fila">
                  <span>{{ pago.metodo_etiqueta }}</span><span>{{ formatearPrecio(pago.monto) }}</span>
                </div>
                <div v-if="pago.referencia" class="tk-aparte">&nbsp;&nbsp;Ref. {{ pago.referencia }}</div>
                <div v-if="pago.cambio > 0" class="tk-aparte">&nbsp;&nbsp;Recibido {{ formatearPrecio(pago.monto_recibido) }} · cambio {{ formatearPrecio(pago.cambio) }}</div>
              </div>
              <div v-if="factura.saldo > 0" class="tk-fila tk-negrita"><span>Saldo pendiente</span><span>{{ formatearPrecio(factura.saldo) }}</span></div>
            </template>

            <div class="tk-centro">
              <div v-if="factura.estado === 'pagada'" class="tk-sello">PAGADA</div>
              <div v-else class="tk-sello">PENDIENTE DE PAGO</div>
            </div>
            <div v-if="comprobante.emisor.mensaje" class="tk-centro tk-aparte">{{ comprobante.emisor.mensaje }}</div>
          </div>
        </q-scroll-area>
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat no-caps label="Cerrar" v-close-popup />
        <q-btn color="primary" unelevated no-caps icon="print" label="Imprimir" :disable="!comprobante" @click="imprimir" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import facturasService from '@/services/facturasService'
import { formatearFecha, mensajeError } from '@/utils/errores'
import { formatearPrecio } from '@/utils/formato'
import { ESTILOS_TICKET, lugarPedido } from '@/utils/facturacion'

/**
 * RF32: comprobante de la factura. Al abrirlo se registra la emisión en el
 * servidor y se muestra como ticket, listo para imprimir.
 */
const props = defineProps({
  idFactura: { type: Number, default: null },
})
const emit = defineEmits(['emitido'])
const abierto = defineModel({ type: Boolean, default: false })

const $q = useQuasar()
const comprobante = ref(null)
const cargando = ref(false)
const error = ref('')
const ticket = ref(null)

const factura = computed(() => comprobante.value?.factura)

async function cargar() {
  comprobante.value = null
  error.value = ''
  cargando.value = true
  try {
    comprobante.value = await facturasService.comprobante(props.idFactura)
    emit('emitido')
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

/** Abre una ventana solo con el ticket (sin el resto de la aplicación) y lanza la impresión. */
function imprimir() {
  const ventana = window.open('', '_blank', 'width=420,height=720')
  if (!ventana) {
    $q.notify({ type: 'warning', message: 'El navegador bloqueó la ventana de impresión. Permite las ventanas emergentes e inténtalo de nuevo.' })
    return
  }
  ventana.document.write(
    `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${factura.value.numero}</title>` +
      `<style>body{margin:0;padding:8px}${ESTILOS_TICKET}</style></head><body>${ticket.value.outerHTML}</body></html>`,
  )
  ventana.document.close()
  ventana.focus()
  setTimeout(() => {
    ventana.print()
    ventana.close()
  }, 300)
}
</script>
