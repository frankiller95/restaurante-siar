<?php

namespace Tests\Feature\Menu;

use App\Enums\TipoEvento;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CU04 Categorizar producto: administración de categorías. */
class CategoriasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
    }

    public function test_lista_categorias_con_conteo_de_productos_activos(): void
    {
        $bebidas = Categoria::factory()->create(['nombre' => 'Bebidas']);
        Producto::factory()->count(2)->create(['id_categoria' => $bebidas->id_categoria]);
        Producto::factory()->dadoDeBaja()->create(['id_categoria' => $bebidas->id_categoria]);
        Categoria::factory()->inactiva()->create(['nombre' => 'Temporada']);

        $this->comoUsuario($this->crearUsuario(Rol::MESERO))
            ->getJson('/api/categorias')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.nombre', 'Bebidas')
            ->assertJsonPath('0.productos_count', 2);

        $this->getJson('/api/categorias?solo_activas=1')->assertOk()->assertJsonCount(1);
    }

    public function test_el_administrador_crea_una_categoria_con_nombre_unico(): void
    {
        $this->comoUsuario($this->admin)
            ->postJson('/api/categorias', ['nombre' => 'Postres', 'descripcion' => 'Dulces'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Postres')
            ->assertJsonPath('data.activa', true);

        $this->postJson('/api/categorias', ['nombre' => 'POSTRES'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::CATEGORIA_CREADA->value]);
    }

    public function test_actualiza_y_desactiva_una_categoria(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Entradas']);

        $this->comoUsuario($this->admin)
            ->putJson("/api/categorias/{$categoria->id_categoria}", ['nombre' => 'Entradas frías', 'activa' => false])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Entradas frías')
            ->assertJsonPath('data.activa', false);
    }

    public function test_no_elimina_una_categoria_con_productos(): void
    {
        $conProductos = Categoria::factory()->create();
        Producto::factory()->dadoDeBaja()->create(['id_categoria' => $conProductos->id_categoria]);
        $vacia = Categoria::factory()->create();

        $this->comoUsuario($this->admin)
            ->deleteJson("/api/categorias/{$conProductos->id_categoria}")
            ->assertStatus(409);

        $this->deleteJson("/api/categorias/{$vacia->id_categoria}")->assertOk();

        $this->assertDatabaseHas('categorias', ['id_categoria' => $conProductos->id_categoria]);
        $this->assertDatabaseMissing('categorias', ['id_categoria' => $vacia->id_categoria]);
    }

    public function test_solo_el_administrador_gestiona_categorias(): void
    {
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO))
            ->postJson('/api/categorias', ['nombre' => 'Sopas'])
            ->assertForbidden();
    }
}
