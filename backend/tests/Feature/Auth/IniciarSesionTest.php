<?php

namespace Tests\Feature\Auth;

use App\Enums\TipoEvento;
use App\Models\Rol;
use App\Services\AuthService;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CU-05 Iniciar sesión. */
class IniciarSesionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
    }

    public function test_inicia_sesion_con_credenciales_validas(): void
    {
        $usuario = $this->crearUsuario(Rol::MESERO);

        $respuesta = $this->postJson('/api/auth/login', [
            'correo' => strtoupper($usuario->correo),
            'contrasena' => UsuarioFactory::CONTRASENA_PRUEBA,
        ]);

        $respuesta->assertOk()
            ->assertJsonStructure(['token', 'expira', 'usuario' => ['id_usuario', 'correo', 'rol', 'permisos']])
            ->assertJsonPath('usuario.rol.nombre_rol', Rol::MESERO)
            ->assertJsonMissingPath('usuario.contrasena_cifrada');

        $this->assertContains('tomar_pedidos', $respuesta->json('usuario.permisos'));
        $this->assertNotContains('gestionar_usuarios', $respuesta->json('usuario.permisos'));
        $this->assertDatabaseCount('sesiones', 1);
        $this->assertDatabaseHas('bitacora_auditoria', [
            'tipo_evento' => TipoEvento::INICIO_SESION->value,
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    public function test_credenciales_incorrectas_muestran_mensaje_generico(): void
    {
        $usuario = $this->crearUsuario(Rol::CAJERO);

        $claveIncorrecta = $this->postJson('/api/auth/login', ['correo' => $usuario->correo, 'contrasena' => 'otra-clave']);
        $correoInexistente = $this->postJson('/api/auth/login', ['correo' => 'nadie@siar.test', 'contrasena' => 'otra-clave']);

        $claveIncorrecta->assertStatus(422)->assertJsonPath('message', AuthService::MENSAJE_CREDENCIALES_INVALIDAS);
        $correoInexistente->assertStatus(422)->assertJsonPath('message', AuthService::MENSAJE_CREDENCIALES_INVALIDAS);

        $this->assertDatabaseCount('sesiones', 0);
        $this->assertDatabaseHas('bitacora_auditoria', [
            'tipo_evento' => TipoEvento::INICIO_SESION_FALLIDO->value,
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    public function test_campos_vacios_se_rechazan(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['correo', 'contrasena']);
    }

    public function test_usuario_inactivo_no_puede_ingresar(): void
    {
        $usuario = $this->crearUsuario(Rol::COCINERO, ['estado' => 'inactivo']);

        $this->postJson('/api/auth/login', [
            'correo' => $usuario->correo,
            'contrasena' => UsuarioFactory::CONTRASENA_PRUEBA,
        ])->assertForbidden()->assertJsonPath('message', 'Tu usuario está inactivo. Comunícate con el administrador.');

        $this->assertDatabaseCount('sesiones', 0);
    }

    public function test_perfil_requiere_sesion(): void
    {
        $this->getJson('/api/auth/perfil')->assertUnauthorized();
    }

    public function test_cerrar_sesion_revoca_el_token(): void
    {
        $usuario = $this->crearUsuario(Rol::MESERO);
        $this->comoUsuario($usuario);

        $this->getJson('/api/auth/perfil')->assertOk()->assertJsonPath('correo', $usuario->correo);
        $this->postJson('/api/auth/logout')->assertOk();

        $this->refrescarAutenticacion();
        $this->getJson('/api/auth/perfil')->assertUnauthorized();
        $this->assertDatabaseCount('sesiones', 0);
    }

    public function test_sesion_expirada_no_es_valida(): void
    {
        $usuario = $this->crearUsuario(Rol::MESERO);
        $token = $this->postJson('/api/auth/login', [
            'correo' => $usuario->correo,
            'contrasena' => UsuarioFactory::CONTRASENA_PRUEBA,
        ])->json('token');

        $this->travel(config('siar.duracion_sesion_minutos') + 1)->minutes();
        $this->refrescarAutenticacion();

        $this->withToken($token)->getJson('/api/auth/perfil')->assertUnauthorized();
    }

    public function test_cambiar_contrasena_cierra_las_otras_sesiones(): void
    {
        $usuario = $this->crearUsuario(Rol::CAJERO);
        $usuario->createToken('otro-dispositivo');
        $this->comoUsuario($usuario);

        $this->putJson('/api/auth/contrasena', [
            'contrasena_actual' => 'no-es-esta',
            'contrasena' => 'NuevaClave2026',
            'contrasena_confirmation' => 'NuevaClave2026',
        ])->assertStatus(422)->assertJsonValidationErrors('contrasena_actual');

        $this->putJson('/api/auth/contrasena', [
            'contrasena_actual' => UsuarioFactory::CONTRASENA_PRUEBA,
            'contrasena' => 'NuevaClave2026',
            'contrasena_confirmation' => 'NuevaClave2026',
        ])->assertOk();

        $this->assertTrue($usuario->fresh()->autenticar('NuevaClave2026'));
        $this->assertSame(1, $usuario->tokens()->count(), 'Solo debe quedar la sesión actual.');
    }
}
