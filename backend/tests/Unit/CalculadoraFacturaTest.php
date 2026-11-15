<?php

namespace Tests\Unit;

use App\Enums\TipoDescuento;
use App\Facturacion\CalculadoraFactura;
use App\Facturacion\Descuentos\DescuentoPorcentaje;
use App\Facturacion\Descuentos\DescuentoValorFijo;
use App\Facturacion\Descuentos\SinDescuento;
use App\Facturacion\Impuestos\ImpuestoAdicional;
use App\Facturacion\Impuestos\ImpuestoIncluidoEnPrecio;
use App\Models\DetallePedido;
use App\Models\Pedido;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** RF29 y RF30: cálculo de impuestos y descuentos (patrón Strategy). */
class CalculadoraFacturaTest extends TestCase
{
    /** Pedido sin base de datos: dos líneas, 2 × 24.000 y 1 × 10.000 (con 2.000 de promoción). */
    private function pedido(float $propina = 0): Pedido
    {
        $pedido = (new Pedido)->forceFill(['propina' => $propina]);
        $pedido->setRelation('detalles', collect([
            (new DetallePedido)->forceFill(['cantidad' => 2, 'precio_unitario' => 24000, 'descuento' => 0]),
            (new DetallePedido)->forceFill(['cantidad' => 1, 'precio_unitario' => 10000, 'descuento' => 2000]),
        ]));

        return $pedido;
    }

    private function calculadora(bool $incluido, float $porcentaje = 8): CalculadoraFactura
    {
        return new CalculadoraFactura('Impuesto al consumo', $porcentaje, $incluido ? new ImpuestoIncluidoEnPrecio : new ImpuestoAdicional);
    }

    public function test_cada_tipo_de_descuento_tiene_su_estrategia(): void
    {
        $this->assertInstanceOf(SinDescuento::class, TipoDescuento::NINGUNO->estrategia());
        $this->assertInstanceOf(DescuentoPorcentaje::class, TipoDescuento::PORCENTAJE->estrategia());
        $this->assertInstanceOf(DescuentoValorFijo::class, TipoDescuento::VALOR->estrategia());
    }

    public function test_impuesto_incluido_no_cambia_el_total(): void
    {
        $resumen = $this->calculadora(incluido: true)->calcular($this->pedido());

        $this->assertSame(58000.0, $resumen->subtotal);
        $this->assertSame(2000.0, $resumen->descuentoPromociones);
        // Neto = 56.000; impuesto = 56.000 × 8 / 108.
        $this->assertSame(4148.15, $resumen->impuesto);
        $this->assertSame(51851.85, $resumen->baseGravable);
        $this->assertSame(56000.0, $resumen->total);
        $this->assertTrue($resumen->impuestoIncluido);
    }

    public function test_impuesto_adicional_se_suma_al_total(): void
    {
        $resumen = $this->calculadora(incluido: false)->calcular($this->pedido());

        $this->assertSame(56000.0, $resumen->baseGravable);
        $this->assertSame(4480.0, $resumen->impuesto);
        $this->assertSame(60480.0, $resumen->total);
        $this->assertFalse($resumen->impuestoIncluido);
    }

    public function test_la_propina_se_suma_sin_pagar_impuesto(): void
    {
        $resumen = $this->calculadora(incluido: true)->calcular($this->pedido(propina: 5600));

        $this->assertSame(4148.15, $resumen->impuesto);
        $this->assertSame(5600.0, $resumen->propina);
        $this->assertSame(61600.0, $resumen->total);
    }

    public function test_descuento_por_porcentaje_reduce_la_base_del_impuesto(): void
    {
        $resumen = $this->calculadora(incluido: false)->calcular($this->pedido(), TipoDescuento::PORCENTAJE, 10);

        // 10 % de 56.000 = 5.600 → base 50.400 → impuesto 4.032.
        $this->assertSame(5600.0, $resumen->descuento);
        $this->assertSame(50400.0, $resumen->baseGravable);
        $this->assertSame(4032.0, $resumen->impuesto);
        $this->assertSame(54432.0, $resumen->total);
    }

    public function test_descuento_de_valor_fijo(): void
    {
        $resumen = $this->calculadora(incluido: true)->calcular($this->pedido(), TipoDescuento::VALOR, 6000);

        $this->assertSame(6000.0, $resumen->descuento);
        $this->assertSame(50000.0, $resumen->total);
    }

    public function test_sin_descuento_ignora_el_valor_enviado(): void
    {
        $resumen = $this->calculadora(incluido: true)->calcular($this->pedido(), TipoDescuento::NINGUNO, 9999);

        $this->assertSame(0.0, $resumen->descuento);
        $this->assertSame(0.0, $resumen->descuentoValor);
    }

    public function test_rechaza_descuentos_invalidos(): void
    {
        $invalidos = [
            [TipoDescuento::PORCENTAJE, 0],
            [TipoDescuento::PORCENTAJE, 101],
            [TipoDescuento::VALOR, 0],
            [TipoDescuento::VALOR, 56001],
        ];

        foreach ($invalidos as [$tipo, $valor]) {
            try {
                $this->calculadora(incluido: true)->calcular($this->pedido(), $tipo, $valor);
                $this->fail("Debió rechazar {$tipo->value} {$valor}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('descuento_valor', $e->errors());
            }
        }
    }

    public function test_el_resumen_se_convierte_a_columnas_de_la_factura(): void
    {
        $columnas = $this->calculadora(incluido: true)->calcular($this->pedido())->aArray();

        $this->assertSame('ninguno', $columnas['descuento_tipo']);
        $this->assertSame('Impuesto al consumo', $columnas['impuesto_nombre']);
        $this->assertSame(56000.0, $columnas['total']);
    }
}
