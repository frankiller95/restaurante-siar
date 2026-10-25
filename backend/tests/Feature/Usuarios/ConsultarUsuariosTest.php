<?php

namespace Tests\Feature\Usuarios;

use App\Mail\CredencialesMail;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Búsqueda de usuarios (paso 1 de CU-02/CU-03) y reenvío de credenciales. */
class ConsultarUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR, ['nombres' => 'Ana Admin']);
    }

    public function test_lista_y_filtra_usuarios(): void
    {
        $this->crearUsuario(Rol::MESERO, ['nombres' => 'Pedro Mesero']);
        $this->crearUsuario(Rol::COCINERO, ['nombres' => 'Carla Cocina', 'estado' => 'inactivo']);

        $this->comoUsuario($this->admin);

        $this->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page'], 'links']);

        $this->getJson('/api/usuarios?buscar=pedro')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombres', 'Pedro Mesero');

        $this->getJson('/api/usuarios?estado=inactivo')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombres', 'Carla Cocina');

        $idCocinero = Rol::where('nombre_rol', Rol::COCINERO)->value('id_rol');
        $this->getJson("/api/usuarios?id_rol={$idCocinero}")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_muestra_un_usuario(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);

        $this->comoUsuario($this->admin)
            ->getJson("/api/usuarios/{$mesero->id_usuario}")
            ->assertOk()
            ->assertJsonPath('id_usuario', $mesero->id_usuario)
            ->assertJsonPath('rol.nombre_rol', Rol::MESERO);
    }

    public function test_reenvia_credenciales_con_una_nueva_contrasena(): void
    {
        Mail::fake();
        $mesero = $this->crearUsuario(Rol::MESERO);

        $this->comoUsuario($this->admin)
            ->postJson("/api/usuarios/{$mesero->id_usuario}/reenviar-credenciales")
            ->assertOk()
            ->assertJsonPath('correo_enviado', true);

        Mail::assertSent(CredencialesMail::class, fn ($mail) => $mail->hasTo($mesero->correo)
            && $mesero->fresh()->autenticar($mail->contrasena));
        $this->assertFalse($mesero->fresh()->autenticar(UsuarioFactory::CONTRASENA_PRUEBA));
    }

    public function test_no_reenvia_credenciales_a_un_usuario_inactivo(): void
    {
        Mail::fake();
        $inactivo = $this->crearUsuario(Rol::MESERO, ['estado' => 'inactivo']);

        $this->comoUsuario($this->admin)
            ->postJson("/api/usuarios/{$inactivo->id_usuario}/reenviar-credenciales")
            ->assertStatus(422);

        Mail::assertNothingSent();
    }
}
