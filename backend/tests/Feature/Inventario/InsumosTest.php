<?php

namespace Tests\Feature\Inventario;

use App\Enums\TipoEvento;
use App\Models\DetalleReceta;
use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF23 a RF27: insumos, entradas, mermas, ajustes y kárdex. */
class InsumosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
    }

    private function crearInsumo(array $cambios = []): array
    {
        return $this->comoUsuario($this->admin)->postJson('/api/insumos', [
            'nombre' => 'Arroz',
            'unidad_medida' => 'kilogramo',
            'stock_minimo' => 5,
            'costo_unitario' => 4200,
            'stock_inicial' => 20,
            ...$cambios,
        ])->assertCreated()->json('data');
    }

    // --- RF23 Registrar insumos ----------------------------------------------

    public function test_registra_un_insumo_con_stock_inicial_en_el_kardex(): void
    {
        $insumo = $this->crearInsumo();

        $this->assertSame('kg', $insumo['unidad']);
        $this->assertEquals(20, $insumo['stock_actual']);
        $this->assertEquals(84000, $insumo['valor_inventario']);
        $this->assertFalse($insumo['bajo_minimo']);

        $this->getJson("/api/insumos/{$insumo['id_insumo']}/movimientos")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'ajuste')
            ->assertJsonPath('data.0.motivo', 'Stock inicial')
            ->assertJsonPath('data.0.stock_resultante', 20);
    }

    public function test_valida_nombre_unico_unidad_y_valores(): void
    {
        $this->crearInsumo();

        $this->postJson('/api/insumos', ['nombre' => 'ARROZ', 'unidad_medida' => 'kilogramo', 'stock_minimo' => 1, 'costo_unitario' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->postJson('/api/insumos', ['nombre' => 'Sal', 'unidad_medida' => 'libra', 'stock_minimo' => -1, 'costo_unitario' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['unidad_medida', 'stock_minimo']);
    }

    // --- RF24 Modificar y desactivar -----------------------------------------

    public function test_modifica_un_insumo_pero_no_su_stock_ni_su_unidad_si_tiene_historial(): void
    {
        $insumo = $this->crearInsumo();
        $url = "/api/insumos/{$insumo['id_insumo']}";

        $this->putJson($url, ['nombre' => 'Arroz blanco', 'unidad_medida' => 'kilogramo', 'stock_minimo' => 8, 'costo_unitario' => 4500])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Arroz blanco')
            ->assertJsonPath('data.stock_minimo', 8);

        $this->putJson($url, ['nombre' => 'Arroz', 'unidad_medida' => 'gramo', 'stock_minimo' => 8, 'costo_unitario' => 4500])
            ->assertStatus(422)->assertJsonValidationErrors('unidad_medida');

        $this->putJson($url, ['nombre' => 'Arroz', 'unidad_medida' => 'kilogramo', 'stock_minimo' => 8, 'costo_unitario' => 1, 'stock_inicial' => 99])
            ->assertStatus(422)->assertJsonValidationErrors('stock_inicial');
    }

    public function test_desactiva_si_tiene_historial_y_borra_si_no(): void
    {
        $conHistorial = $this->crearInsumo();
        $sinHistorial = $this->crearInsumo(['nombre' => 'Sal', 'stock_inicial' => 0]);

        $this->deleteJson("/api/insumos/{$conHistorial['id_insumo']}")->assertOk()->assertJsonPath('tipo_eliminacion', 'logica');
        $this->deleteJson("/api/insumos/{$sinHistorial['id_insumo']}")->assertOk()->assertJsonPath('tipo_eliminacion', 'fisica');

        $this->assertDatabaseHas('insumos', ['id_insumo' => $conHistorial['id_insumo'], 'activo' => false]);
        $this->assertDatabaseMissing('insumos', ['id_insumo' => $sinHistorial['id_insumo']]);
        $this->getJson('/api/insumos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/insumos?estado=baja')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_no_desactiva_un_insumo_usado_en_recetas_activas(): void
    {
        $insumo = Insumo::factory()->create();
        DetalleReceta::create(['id_producto' => Producto::factory()->create()->id_producto, 'id_insumo' => $insumo->id_insumo, 'cantidad_por_porcion' => 0.2]);

        $this->comoUsuario($this->admin)->deleteJson("/api/insumos/{$insumo->id_insumo}")->assertStatus(409);
    }

    // --- RF25 Entradas -------------------------------------------------------

    public function test_registra_una_entrada_y_actualiza_el_costo(): void
    {
        $insumo = $this->crearInsumo();

        $respuesta = $this->postJson("/api/insumos/{$insumo['id_insumo']}/entradas", [
            'cantidad' => 10.5,
            'costo_unitario' => 4500,
            'proveedor' => 'Distribuidora El Grano',
        ])->assertCreated();

        $respuesta->assertJsonPath('data.tipo', 'entrada')
            ->assertJsonPath('data.cantidad', 10.5)
            ->assertJsonPath('data.stock_resultante', 30.5)
            ->assertJsonPath('data.proveedor', 'Distribuidora El Grano')
            ->assertJsonPath('data.usuario', $this->admin->nombres)
            ->assertJsonPath('insumo.costo_unitario', 4500);
        $this->assertEquals(47250, $respuesta->json('data.costo_total'));

        $this->postJson("/api/insumos/{$insumo['id_insumo']}/entradas", ['cantidad' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('cantidad');
        $this->postJson("/api/insumos/{$insumo['id_insumo']}/entradas", ['cantidad' => 1, 'fecha' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::INVENTARIO_ENTRADA->value]);
    }

    // --- RF26 Mermas y ajustes -----------------------------------------------

    public function test_merma_con_motivo_y_sin_superar_el_stock(): void
    {
        $insumo = $this->crearInsumo();
        $url = "/api/insumos/{$insumo['id_insumo']}/mermas";

        $this->postJson($url, ['cantidad' => 2])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->postJson($url, ['cantidad' => 25, 'motivo' => 'Se mojó'])->assertStatus(422)->assertJsonValidationErrors('cantidad');

        $this->postJson($url, ['cantidad' => 2, 'motivo' => 'Bulto con gorgojo'])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'merma')
            ->assertJsonPath('data.cantidad', -2)
            ->assertJsonPath('insumo.stock_actual', 18);
    }

    public function test_ajuste_por_conteo_registra_la_diferencia(): void
    {
        $insumo = $this->crearInsumo();
        $url = "/api/insumos/{$insumo['id_insumo']}/ajustes";

        $this->postJson($url, ['stock_contado' => 17.5, 'motivo' => 'Conteo de fin de mes'])
            ->assertCreated()
            ->assertJsonPath('data.cantidad', -2.5)
            ->assertJsonPath('insumo.stock_actual', 17.5);

        $this->postJson($url, ['stock_contado' => 18, 'motivo' => 'Recuento'])
            ->assertCreated()
            ->assertJsonPath('data.cantidad', 0.5);

        $this->postJson($url, ['stock_contado' => 18, 'motivo' => 'Igual'])
            ->assertStatus(422)->assertJsonValidationErrors('stock_contado');
    }

    // --- RF27 Existencias y kárdex -------------------------------------------

    public function test_kardex_filtra_por_tipo_y_fecha(): void
    {
        $insumo = $this->crearInsumo();
        $id = $insumo['id_insumo'];
        $this->postJson("/api/insumos/{$id}/entradas", ['cantidad' => 5])->assertCreated();
        $this->postJson("/api/insumos/{$id}/mermas", ['cantidad' => 1, 'motivo' => 'Vencido'])->assertCreated();

        $this->getJson("/api/insumos/{$id}/movimientos")->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.tipo', 'merma')
            ->assertJsonPath('insumo.stock_actual', 24);
        $this->getJson("/api/insumos/{$id}/movimientos?tipo=entrada")->assertOk()->assertJsonCount(1, 'data');

        $ayer = now(config('siar.zona_horaria'))->subDay()->toDateString();
        $this->getJson("/api/insumos/{$id}/movimientos?hasta={$ayer}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_alertas_de_stock_minimo(): void
    {
        $insumo = $this->crearInsumo(['stock_inicial' => 6, 'stock_minimo' => 5]);

        $this->getJson('/api/insumos/alertas')->assertOk()->assertJsonCount(0);

        $this->postJson("/api/insumos/{$insumo['id_insumo']}/mermas", ['cantidad' => 2, 'motivo' => 'Derrame'])
            ->assertCreated()
            ->assertJsonPath('insumo.bajo_minimo', true);

        $this->getJson('/api/insumos/alertas')->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Arroz');
        $this->getJson('/api/insumos?bajo_minimo=1')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::STOCK_BAJO->value]);
    }

    public function test_solo_quien_gestiona_inventario_accede(): void
    {
        foreach ([Rol::MESERO, Rol::COCINERO, Rol::CAJERO] as $rol) {
            $this->comoUsuario($this->crearUsuario($rol))->getJson('/api/insumos')->assertForbidden();
        }
    }
}
