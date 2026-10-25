<?php

namespace Tests\Feature\Usuarios;

use App\Enums\TipoEvento;
use App\Mail\NotificacionCambioMail;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** CU-02 Editar usuario. */
class EditarUsuarioTest extends TestCase
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

    private function datosDe(Usuario $usuario, array $cambios = []): array
    {
        return [
            'nombres' => $usuario->nombres,
            'documento' => $usuario->documento,
            'correo' => $usuario->correo,
            'id_rol' => $usuario->id_rol,
            'estado' => $usuario->estado->value,
            ...$cambios,
        ];
    }

    private function idRol(string $nombre): int
    {
        return Rol::where('nombre_rol', $nombre)->value('id_rol');
    }

    public function test_edita_los_datos_personales_sin_cerrar_sesiones(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->createToken('turno');

        $this->comoUsuario($this->admin)
            ->putJson("/api/usuarios/{$mesero->id_usuario}", $this->datosDe($mesero, ['nombres' => 'Nombre Corregido']))
            ->assertOk()
            ->assertJsonPath('data.nombres', 'Nombre Corregido')
            ->assertJsonPath('sesiones_revocadas', false);

        $this->assertSame(1, $mesero->tokens()->count());
        Mail::assertSent(NotificacionCambioMail::class, fn ($mail) => $mail->hasTo($mesero->correo));
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::USUARIO_ACTUALIZADO->value]);
    }

    public function test_cambiar_rol_o_estado_cierra_las_sesiones_del_usuario(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->createToken('turno');

        $this->comoUsuario($this->admin)
            ->putJson("/api/usuarios/{$mesero->id_usuario}", $this->datosDe($mesero, ['id_rol' => $this->idRol(Rol::CAJERO)]))
            ->assertOk()
            ->assertJsonPath('data.rol.nombre_rol', Rol::CAJERO)
            ->assertJsonPath('sesiones_revocadas', true);

        $this->assertSame(0, $mesero->tokens()->count());

        $cocinero = $this->crearUsuario(Rol::COCINERO);
        $cocinero->createToken('turno');

        $this->putJson("/api/usuarios/{$cocinero->id_usuario}", $this->datosDe($cocinero, ['estado' => 'inactivo']))
            ->assertOk()
            ->assertJsonPath('data.estado', 'inactivo');

        $this->assertSame(0, $cocinero->tokens()->count());
    }

    public function test_el_administrador_no_puede_cambiar_su_rol_ni_desactivarse(): void
    {
        $this->comoUsuario($this->admin)
            ->putJson("/api/usuarios/{$this->admin->id_usuario}", $this->datosDe($this->admin, ['id_rol' => $this->idRol(Rol::CAJERO)]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'No puedes cambiar tu propio rol.');

        $this->putJson("/api/usuarios/{$this->admin->id_usuario}", $this->datosDe($this->admin, ['estado' => 'inactivo']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'No puedes desactivar tu propia cuenta.');

        $this->assertTrue($this->admin->fresh()->estaActivo());
    }

    public function test_rechaza_correo_o_documento_de_otro_usuario(): void
    {
        $uno = $this->crearUsuario(Rol::MESERO);
        $otro = $this->crearUsuario(Rol::CAJERO);

        $this->comoUsuario($this->admin)
            ->putJson("/api/usuarios/{$uno->id_usuario}", $this->datosDe($uno, ['correo' => $otro->correo]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('correo');

        // Su propio correo y documento sí son válidos.
        $this->putJson("/api/usuarios/{$uno->id_usuario}", $this->datosDe($uno))->assertOk();
    }

    public function test_el_estado_es_obligatorio_al_editar(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $datos = $this->datosDe($mesero);
        unset($datos['estado']);

        $this->comoUsuario($this->admin)
            ->putJson("/api/usuarios/{$mesero->id_usuario}", $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    public function test_responde_404_si_el_usuario_no_existe(): void
    {
        $this->comoUsuario($this->admin)
            ->putJson('/api/usuarios/99999', $this->datosDe($this->admin))
            ->assertNotFound();
    }
}
