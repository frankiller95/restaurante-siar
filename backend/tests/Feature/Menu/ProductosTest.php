<?php

namespace Tests\Feature\Menu;

use App\Enums\EstadoPedido;
use App\Enums\TipoEvento;
use App\Models\Categoria;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** CU01 Crear, CU02 Modificar, CU03 Eliminar y CU04 Categorizar producto. */
class ProductosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Categoria $platos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        Storage::fake('imagenes');

        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
        $this->platos = Categoria::factory()->create(['nombre' => 'Platos fuertes']);
    }

    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Bandeja paisa',
            'descripcion' => 'Frijoles, arroz, chicharrón y huevo',
            'precio' => 32000,
            'id_categoria' => $this->platos->id_categoria,
            ...$cambios,
        ];
    }

    // --- CU01 Crear producto -------------------------------------------------

    public function test_crea_un_producto_disponible_por_defecto_con_imagen(): void
    {
        $respuesta = $this->comoUsuario($this->admin)->post('/api/productos', $this->datos([
            'imagen' => UploadedFile::fake()->image('bandeja.jpg', 400, 300),
        ]), ['Accept' => 'application/json']);

        $respuesta->assertCreated()
            ->assertJsonPath('data.nombre', 'Bandeja paisa')
            ->assertJsonPath('data.precio', 32000)
            ->assertJsonPath('data.disponible', true)
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.categoria.nombre', 'Platos fuertes');

        $producto = Producto::firstOrFail();
        Storage::disk('imagenes')->assertExists($producto->imagen_url);
        $this->assertStringStartsWith('productos/', $producto->imagen_url);
        $this->assertStringEndsWith($producto->imagen_url, $respuesta->json('data.imagen_url'));
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::PRODUCTO_CREADO->value]);
    }

    public function test_rechaza_precio_negativo_o_vacio_y_campos_obligatorios(): void
    {
        $this->comoUsuario($this->admin)
            ->postJson('/api/productos', $this->datos(['precio' => -100]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio');

        $this->postJson('/api/productos', ['nombre' => '', 'precio' => '', 'id_categoria' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'precio', 'id_categoria']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_nombre_repetido_en_la_categoria_pide_confirmacion(): void
    {
        Producto::factory()->create(['nombre' => 'Bandeja Paisa', 'id_categoria' => $this->platos->id_categoria]);

        $this->comoUsuario($this->admin)
            ->postJson('/api/productos', $this->datos())
            ->assertStatus(409)
            ->assertJsonPath('codigo', ProductoService::CODIGO_NOMBRE_DUPLICADO);

        // Confirmado por el administrador: sí se guarda.
        $this->postJson('/api/productos', $this->datos(['confirmar_duplicado' => true]))->assertCreated();

        // En otra categoría no hay conflicto.
        $otra = Categoria::factory()->create();
        $this->postJson('/api/productos', $this->datos(['id_categoria' => $otra->id_categoria]))->assertCreated();

        $this->assertDatabaseCount('productos', 3);
    }

    public function test_no_crea_productos_en_una_categoria_inactiva(): void
    {
        $inactiva = Categoria::factory()->inactiva()->create();

        $this->comoUsuario($this->admin)
            ->postJson('/api/productos', $this->datos(['id_categoria' => $inactiva->id_categoria]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_categoria');
    }

    public function test_rechaza_archivos_que_no_son_imagenes(): void
    {
        $this->comoUsuario($this->admin)
            ->post('/api/productos', $this->datos([
                'imagen' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('imagen');
    }

    // --- CU02 Modificar producto ---------------------------------------------

    public function test_modifica_un_producto_y_actualiza_la_fecha_de_modificacion(): void
    {
        $producto = Producto::factory()->create(['id_categoria' => $this->platos->id_categoria, 'precio' => 20000]);
        $this->travel(5)->minutes();

        $this->comoUsuario($this->admin)
            ->putJson("/api/productos/{$producto->id_producto}", $this->datos(['nombre' => 'Ajiaco', 'precio' => 24000]))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Ajiaco')
            ->assertJsonPath('data.precio', 24000);

        $this->assertTrue($producto->fresh()->fecha_modificacion->gt($producto->fecha_creacion));
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::PRODUCTO_ACTUALIZADO->value]);
    }

    public function test_reemplaza_y_quita_la_imagen_al_editar_con_multipart(): void
    {
        $this->comoUsuario($this->admin)->post('/api/productos', $this->datos([
            'imagen' => UploadedFile::fake()->image('vieja.jpg'),
        ]), ['Accept' => 'application/json'])->assertCreated();
        $producto = Producto::firstOrFail();
        $imagenVieja = $producto->imagen_url;

        // Editar con imagen nueva: POST + _method=PUT (multipart).
        $this->post("/api/productos/{$producto->id_producto}", $this->datos([
            '_method' => 'PUT',
            'imagen' => UploadedFile::fake()->image('nueva.png'),
        ]), ['Accept' => 'application/json'])->assertOk();

        $imagenNueva = $producto->fresh()->imagen_url;
        $this->assertNotSame($imagenVieja, $imagenNueva);
        Storage::disk('imagenes')->assertMissing($imagenVieja);
        Storage::disk('imagenes')->assertExists($imagenNueva);

        $this->putJson("/api/productos/{$producto->id_producto}", $this->datos(['quitar_imagen' => true]))
            ->assertOk()
            ->assertJsonPath('data.imagen_url', null);
        Storage::disk('imagenes')->assertMissing($imagenNueva);
    }

    // --- CU03 Eliminar producto ----------------------------------------------

    public function test_eliminar_es_una_baja_logica(): void
    {
        $producto = Producto::factory()->create(['id_categoria' => $this->platos->id_categoria]);

        $this->comoUsuario($this->admin)
            ->deleteJson("/api/productos/{$producto->id_producto}")
            ->assertOk();

        $this->assertDatabaseHas('productos', ['id_producto' => $producto->id_producto, 'activo' => false]);
        $this->assertNotNull($producto->fresh()->fecha_baja);

        $this->getJson('/api/productos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/productos?estado=baja')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/menu')->assertOk()->assertJsonCount(0);

        // Un producto dado de baja ya no se modifica.
        $this->deleteJson("/api/productos/{$producto->id_producto}")->assertStatus(422);
        $this->putJson("/api/productos/{$producto->id_producto}", $this->datos())->assertStatus(422);
    }

    public function test_no_elimina_un_producto_con_pedidos_pendientes(): void
    {
        // Un pedido del Módulo 4 en preparación que incluye el producto.
        $producto = Producto::factory()->create(['id_categoria' => $this->platos->id_categoria]);
        $pedido = Pedido::factory()->enEstado(EstadoPedido::EN_PREPARACION)->create();
        DetallePedido::factory()->create(['id_pedido' => $pedido->id_pedido, 'id_producto' => $producto->id_producto]);

        $this->comoUsuario($this->admin)
            ->deleteJson("/api/productos/{$producto->id_producto}")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'pedidos_pendientes');

        // Con el pedido ya entregado, sí se puede dar de baja.
        $pedido->forceFill(['estado' => EstadoPedido::ENTREGADO])->save();
        $this->deleteJson("/api/productos/{$producto->id_producto}")->assertOk();
    }

    // --- CU04 Categorizar producto -------------------------------------------

    public function test_cambia_la_categoria_de_un_producto(): void
    {
        $producto = Producto::factory()->create(['id_categoria' => $this->platos->id_categoria]);
        $bebidas = Categoria::factory()->create(['nombre' => 'Bebidas']);

        $this->comoUsuario($this->admin)
            ->patchJson("/api/productos/{$producto->id_producto}/categoria", ['id_categoria' => $bebidas->id_categoria])
            ->assertOk()
            ->assertJsonPath('data.categoria.nombre', 'Bebidas');

        $this->assertSame($bebidas->id_categoria, $producto->fresh()->id_categoria);
    }

    // --- Consulta y permisos -------------------------------------------------

    public function test_lista_y_filtra_productos(): void
    {
        Producto::factory()->create(['nombre' => 'Limonada', 'descripcion' => null, 'id_categoria' => $this->platos->id_categoria]);
        Producto::factory()->agotado()->create(['nombre' => 'Lulada', 'descripcion' => null, 'id_categoria' => $this->platos->id_categoria]);
        Producto::factory()->create(['nombre' => 'Arepa', 'descripcion' => 'Con queso']);

        $this->comoUsuario($this->crearUsuario(Rol::MESERO));

        $this->getJson('/api/productos')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/productos?buscar=ada')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/productos?buscar=queso')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/productos?disponible=0')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Lulada');
        $this->getJson("/api/productos?id_categoria={$this->platos->id_categoria}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_solo_el_administrador_crea_modifica_y_elimina(): void
    {
        $producto = Producto::factory()->create(['id_categoria' => $this->platos->id_categoria]);

        foreach ([Rol::MESERO, Rol::COCINERO, Rol::CAJERO] as $rol) {
            $this->comoUsuario($this->crearUsuario($rol));
            $this->postJson('/api/productos', $this->datos())->assertForbidden();
            $this->putJson("/api/productos/{$producto->id_producto}", $this->datos())->assertForbidden();
            $this->deleteJson("/api/productos/{$producto->id_producto}")->assertForbidden();
        }

        $this->refrescarAutenticacion();
        $this->withToken('')->getJson('/api/productos')->assertUnauthorized();
    }
}
