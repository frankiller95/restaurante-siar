<?php

namespace Tests\Feature\Usuarios;

use App\Contracts\Services\ServicioCorreo;
use App\Enums\TipoEvento;
use App\Mail\CredencialesMail;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** CU-01 Registrar usuario. */
class RegistrarUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
        Mail::fake();
    }

    private function datosValidos(array $cambios = []): array
    {
        return [
            'nombres' => 'Laura Gómez',
            'documento' => '1144000111',
            'correo' => 'Laura.Gomez@Restaurante.test',
            'id_rol' => Rol::where('nombre_rol', Rol::MESERO)->value('id_rol'),
            ...$cambios,
        ];
    }

    public function test_el_administrador_registra_un_usuario(): void
    {
        $respuesta = $this->comoUsuario($this->admin)->postJson('/api/usuarios', $this->datosValidos());

        $respuesta->assertCreated()
            ->assertJsonPath('correo_enviado', true)
            ->assertJsonPath('data.correo', 'laura.gomez@restaurante.test')
            ->assertJsonPath('data.estado', 'activo')
            ->assertJsonPath('data.rol.nombre_rol', Rol::MESERO)
            ->assertJsonMissingPath('data.contrasena_cifrada');

        $usuario = Usuario::where('correo', 'laura.gomez@restaurante.test')->firstOrFail();

        $contrasenaEnviada = null;
        Mail::assertSent(CredencialesMail::class, function (CredencialesMail $mail) use (&$contrasenaEnviada) {
            $contrasenaEnviada = $mail->contrasena;

            return $mail->hasTo('laura.gomez@restaurante.test');
        });

        // RNF04: se guarda cifrada, nunca en texto plano.
        $this->assertNotSame($contrasenaEnviada, $usuario->contrasena_cifrada);
        $this->assertTrue($usuario->autenticar($contrasenaEnviada));

        $this->assertDatabaseHas('bitacora_auditoria', [
            'tipo_evento' => TipoEvento::USUARIO_REGISTRADO->value,
            'id_usuario' => $this->admin->id_usuario,
        ]);
    }

    public function test_rechaza_correo_o_documento_ya_registrados(): void
    {
        $existente = $this->crearUsuario(Rol::CAJERO);

        $this->comoUsuario($this->admin)
            ->postJson('/api/usuarios', $this->datosValidos([
                'correo' => strtoupper($existente->correo),
                'documento' => $existente->documento,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['correo', 'documento']);

        Mail::assertNothingSent();
    }

    public function test_rechaza_datos_incompletos_o_con_formato_invalido(): void
    {
        $this->comoUsuario($this->admin)
            ->postJson('/api/usuarios', [
                'nombres' => '',
                'documento' => '12',
                'correo' => 'no-es-correo',
                'id_rol' => 9999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombres', 'documento', 'correo', 'id_rol']);
    }

    public function test_si_el_correo_falla_el_usuario_se_conserva(): void
    {
        $this->mock(ServicioCorreo::class)
            ->shouldReceive('enviarCredenciales')->once()->andReturnFalse();

        $this->comoUsuario($this->admin)
            ->postJson('/api/usuarios', $this->datosValidos())
            ->assertCreated()
            ->assertJsonPath('correo_enviado', false);

        $this->assertDatabaseHas('usuarios', ['correo' => 'laura.gomez@restaurante.test', 'estado' => 'activo']);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::CORREO_NO_ENVIADO->value]);
    }

    public function test_solo_quien_tiene_el_permiso_puede_registrar(): void
    {
        $this->postJson('/api/usuarios', $this->datosValidos())->assertUnauthorized();

        $mesero = $this->crearUsuario(Rol::MESERO);
        $this->comoUsuario($mesero)
            ->postJson('/api/usuarios', $this->datosValidos())
            ->assertForbidden();

        $this->assertDatabaseMissing('usuarios', ['correo' => 'laura.gomez@restaurante.test']);
    }

    public function test_no_se_puede_enviar_el_estado_al_registrar(): void
    {
        $this->comoUsuario($this->admin)
            ->postJson('/api/usuarios', $this->datosValidos(['estado' => 'inactivo']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }
}
