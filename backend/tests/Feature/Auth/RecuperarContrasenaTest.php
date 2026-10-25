<?php

namespace Tests\Feature\Auth;

use App\Enums\TipoEvento;
use App\Mail\EnlaceRecuperacionMail;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\RecuperacionContrasenaService;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** CU-06 Recuperar contraseña. */
class RecuperarContrasenaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        Mail::fake();
    }

    public function test_envia_enlace_a_un_usuario_activo(): void
    {
        $usuario = $this->crearUsuario(Rol::MESERO);

        $this->postJson('/api/auth/recuperar-contrasena', ['correo' => $usuario->correo])
            ->assertOk()
            ->assertJsonPath('message', RecuperacionContrasenaService::MENSAJE_SOLICITUD);

        Mail::assertSent(EnlaceRecuperacionMail::class, fn ($mail) => $mail->hasTo($usuario->correo)
            && str_contains($mail->enlace, '/restablecer-contrasena?token='));
        $this->assertDatabaseHas('solicitudes_recuperacion', ['email' => $usuario->correo]);
    }

    public function test_no_revela_si_el_correo_existe(): void
    {
        $inactivo = $this->crearUsuario(Rol::MESERO, ['estado' => 'inactivo']);

        $this->postJson('/api/auth/recuperar-contrasena', ['correo' => 'no-existe@siar.test'])
            ->assertOk()->assertJsonPath('message', RecuperacionContrasenaService::MENSAJE_SOLICITUD);
        $this->postJson('/api/auth/recuperar-contrasena', ['correo' => $inactivo->correo])
            ->assertOk()->assertJsonPath('message', RecuperacionContrasenaService::MENSAJE_SOLICITUD);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('solicitudes_recuperacion', 0);
    }

    public function test_restablece_la_contrasena_con_un_enlace_valido_de_un_solo_uso(): void
    {
        $usuario = $this->crearUsuario(Rol::CAJERO);
        $usuario->createToken('sesion-abierta');
        $token = $this->solicitarToken($usuario);

        $datos = [
            'correo' => $usuario->correo,
            'token' => $token,
            'contrasena' => 'ClaveNueva2026',
            'contrasena_confirmation' => 'ClaveNueva2026',
        ];

        $this->postJson('/api/auth/restablecer-contrasena', $datos)->assertOk();

        $this->assertTrue($usuario->fresh()->autenticar('ClaveNueva2026'));
        $this->assertSame(0, $usuario->tokens()->count(), 'Debe cerrar las sesiones activas.');
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::CONTRASENA_RESTABLECIDA->value]);

        // El enlace ya se usó.
        $this->postJson('/api/auth/restablecer-contrasena', $datos)
            ->assertStatus(422)
            ->assertJsonPath('message', RecuperacionContrasenaService::MENSAJE_ENLACE_INVALIDO);
    }

    public function test_rechaza_un_enlace_vencido(): void
    {
        $usuario = $this->crearUsuario(Rol::CAJERO);
        $token = $this->solicitarToken($usuario);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $usuario->correo,
            'token' => $token,
            'contrasena' => 'ClaveNueva2026',
            'contrasena_confirmation' => 'ClaveNueva2026',
        ])->assertStatus(422)->assertJsonPath('message', RecuperacionContrasenaService::MENSAJE_ENLACE_INVALIDO);

        $this->assertTrue($usuario->fresh()->autenticar(UsuarioFactory::CONTRASENA_PRUEBA));
    }

    public function test_valida_que_las_contrasenas_coincidan_y_sean_seguras(): void
    {
        $usuario = $this->crearUsuario(Rol::CAJERO);
        $token = $this->solicitarToken($usuario);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $usuario->correo,
            'token' => $token,
            'contrasena' => 'ClaveNueva2026',
            'contrasena_confirmation' => 'OtraClave2026',
        ])->assertStatus(422)->assertJsonValidationErrors('contrasena');

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $usuario->correo,
            'token' => $token,
            'contrasena' => 'corta',
            'contrasena_confirmation' => 'corta',
        ])->assertStatus(422)->assertJsonValidationErrors('contrasena');
    }

    private function solicitarToken(Usuario $usuario): string
    {
        $this->postJson('/api/auth/recuperar-contrasena', ['correo' => $usuario->correo])->assertOk();

        $token = null;
        Mail::assertSent(EnlaceRecuperacionMail::class, function (EnlaceRecuperacionMail $mail) use (&$token) {
            parse_str((string) parse_url($mail->enlace, PHP_URL_QUERY), $parametros);
            $token = $parametros['token'] ?? null;

            return true;
        });

        $this->assertNotNull($token);

        return $token;
    }
}
