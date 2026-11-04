<?php

namespace Tests\Feature\Menu;

use App\Enums\CodigoPermiso;
use App\Enums\TipoEvento;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CU05 Gestionar disponibilidad y carta del menú. */
class DisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
    }

    public function test_el_cocinero_tiene_el_permiso_de_disponibilidad(): void
    {
        $cocinero = $this->crearUsuario(Rol::COCINERO);
        $mesero = $this->crearUsuario(Rol::MESERO);

        $this->assertTrue($cocinero->tienePermiso(CodigoPermiso::GESTIONAR_DISPONIBILIDAD->value));
        $this->assertFalse($mesero->tienePermiso(CodigoPermiso::GESTIONAR_DISPONIBILIDAD->value));
    }

    public function test_el_cocinero_marca_un_producto_como_agotado_y_lo_reactiva(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Sancocho']);
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO));

        $this->patchJson("/api/productos/{$producto->id_producto}/disponibilidad", ['disponible' => false])
            ->assertOk()
            ->assertJsonPath('data.disponible', false);

        $this->patchJson("/api/productos/{$producto->id_producto}/disponibilidad", ['disponible' => true])
            ->assertOk()
            ->assertJsonPath('data.disponible', true);

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::DISPONIBILIDAD_CAMBIADA->value]);
    }

    public function test_el_mesero_no_cambia_la_disponibilidad(): void
    {
        $producto = Producto::factory()->create();

        $this->comoUsuario($this->crearUsuario(Rol::MESERO))
            ->patchJson("/api/productos/{$producto->id_producto}/disponibilidad", ['disponible' => false])
            ->assertForbidden();

        $this->assertTrue($producto->fresh()->disponible);
    }

    public function test_la_carta_agrupa_por_categoria_y_marca_los_agotados(): void
    {
        $bebidas = Categoria::factory()->create(['nombre' => 'Bebidas']);
        $postres = Categoria::factory()->create(['nombre' => 'Postres']);
        $oculta = Categoria::factory()->inactiva()->create(['nombre' => 'Temporada']);

        Producto::factory()->create(['nombre' => 'Limonada', 'id_categoria' => $bebidas->id_categoria]);
        Producto::factory()->agotado()->create(['nombre' => 'Jugo de mora', 'id_categoria' => $bebidas->id_categoria]);
        Producto::factory()->create(['nombre' => 'Flan', 'id_categoria' => $postres->id_categoria]);
        Producto::factory()->dadoDeBaja()->create(['nombre' => 'Torta vieja', 'id_categoria' => $postres->id_categoria]);
        Producto::factory()->create(['nombre' => 'Natilla', 'id_categoria' => $oculta->id_categoria]);

        $respuesta = $this->comoUsuario($this->crearUsuario(Rol::MESERO))
            ->getJson('/api/menu')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.nombre', 'Bebidas')
            ->assertJsonCount(2, '0.productos')
            ->assertJsonPath('1.nombre', 'Postres')
            ->assertJsonCount(1, '1.productos');

        $jugo = collect($respuesta->json('0.productos'))->firstWhere('nombre', 'Jugo de mora');
        $this->assertFalse($jugo['disponible']);
    }
}
