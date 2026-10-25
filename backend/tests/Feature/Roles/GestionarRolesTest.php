<?php

namespace Tests\Feature\Roles;

use App\Enums\CodigoPermiso;
use App\Enums\TipoEvento;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CU-04 Gestionar roles. */
class GestionarRolesTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
    }

    private function rol(string $nombre): Rol
    {
        return Rol::where('nombre_rol', $nombre)->firstOrFail();
    }

    /** @return list<int> */
    private function idsPermisos(CodigoPermiso ...$codigos): array
    {
        return Permiso::whereIn('codigo_permiso', array_map(fn ($c) => $c->value, $codigos))
            ->pluck('id_permiso')->all();
    }

    public function test_lista_los_roles_con_sus_permisos(): void
    {
        $this->comoUsuario($this->admin)
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonStructure([['id_rol', 'nombre_rol', 'es_predefinido', 'usuarios_count', 'permisos']]);

        $this->getJson('/api/permisos')->assertOk()->assertJsonCount(count(CodigoPermiso::cases()));
    }

    public function test_crea_un_rol_personalizado_con_nombre_unico(): void
    {
        $this->comoUsuario($this->admin)
            ->postJson('/api/roles', ['nombre_rol' => 'Bartender', 'descripcion' => 'Prepara bebidas'])
            ->assertCreated()
            ->assertJsonPath('data.nombre_rol', 'Bartender')
            ->assertJsonPath('data.es_predefinido', false);

        $this->postJson('/api/roles', ['nombre_rol' => 'BARTENDER'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre_rol');
        $this->postJson('/api/roles', ['nombre_rol' => 'mesero'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre_rol');

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::ROL_CREADO->value]);
    }

    public function test_edita_un_rol_personalizado(): void
    {
        $rol = Rol::factory()->create(['nombre_rol' => 'Bartender']);

        $this->comoUsuario($this->admin)
            ->putJson("/api/roles/{$rol->id_rol}", ['nombre_rol' => 'Barista', 'descripcion' => 'Café'])
            ->assertOk()
            ->assertJsonPath('data.nombre_rol', 'Barista');
    }

    public function test_los_roles_del_sistema_no_se_renombran_ni_se_eliminan(): void
    {
        $cajero = $this->rol(Rol::CAJERO);

        $this->comoUsuario($this->admin)
            ->putJson("/api/roles/{$cajero->id_rol}", ['nombre_rol' => 'Caja'])
            ->assertStatus(422);

        $this->deleteJson("/api/roles/{$cajero->id_rol}")->assertStatus(422);

        $this->assertDatabaseHas('roles', ['nombre_rol' => Rol::CAJERO]);
    }

    public function test_no_elimina_un_rol_personalizado_con_usuarios(): void
    {
        $rol = Rol::factory()->create(['nombre_rol' => 'Bartender']);
        Usuario::factory()->create(['id_rol' => $rol->id_rol]);
        $vacio = Rol::factory()->create(['nombre_rol' => 'Temporal']);

        $this->comoUsuario($this->admin)
            ->deleteJson("/api/roles/{$rol->id_rol}")
            ->assertStatus(409);

        $this->deleteJson("/api/roles/{$vacio->id_rol}")->assertOk();

        $this->assertDatabaseHas('roles', ['nombre_rol' => 'Bartender']);
        $this->assertDatabaseMissing('roles', ['nombre_rol' => 'Temporal']);
    }

    public function test_cambiar_permisos_cierra_las_sesiones_de_los_usuarios_del_rol(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->createToken('turno');
        $cajero = $this->crearUsuario(Rol::CAJERO);
        $cajero->createToken('turno');
        $rolMesero = $this->rol(Rol::MESERO);

        $this->comoUsuario($this->admin)
            ->putJson("/api/roles/{$rolMesero->id_rol}/permisos", [
                'permisos' => $this->idsPermisos(CodigoPermiso::CONSULTAR_MENU, CodigoPermiso::TOMAR_PEDIDOS),
            ])
            ->assertOk()
            ->assertJsonPath('sesiones_revocadas', 1)
            ->assertJsonCount(2, 'data.permisos');

        $this->assertSame(0, $mesero->tokens()->count());
        $this->assertSame(1, $cajero->tokens()->count(), 'Los otros roles no se afectan.');
        $this->assertFalse($mesero->fresh()->tienePermiso(CodigoPermiso::GESTIONAR_RESERVAS->value));
    }

    public function test_sin_cambios_en_permisos_no_cierra_sesiones(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->createToken('turno');
        $rolMesero = $this->rol(Rol::MESERO)->load('permisos');

        $this->comoUsuario($this->admin)
            ->putJson("/api/roles/{$rolMesero->id_rol}/permisos", [
                'permisos' => $rolMesero->permisos->pluck('id_permiso')->all(),
            ])
            ->assertOk()
            ->assertJsonPath('sesiones_revocadas', 0);

        $this->assertSame(1, $mesero->tokens()->count());
    }

    public function test_el_administrador_conserva_su_sesion_y_el_permiso_de_gestion(): void
    {
        $otroAdmin = $this->crearUsuario(Rol::ADMINISTRADOR);
        $otroAdmin->createToken('turno');
        $rolAdmin = $this->rol(Rol::ADMINISTRADOR);

        $this->comoUsuario($this->admin)
            ->putJson("/api/roles/{$rolAdmin->id_rol}/permisos", [
                'permisos' => $this->idsPermisos(CodigoPermiso::VER_REPORTES),
            ])
            ->assertStatus(422);

        $this->putJson("/api/roles/{$rolAdmin->id_rol}/permisos", [
            'permisos' => $this->idsPermisos(CodigoPermiso::GESTIONAR_USUARIOS, CodigoPermiso::VER_REPORTES),
        ])->assertOk()->assertJsonPath('sesiones_revocadas', 1);

        // La sesión de quien hizo el cambio sigue activa; la del otro admin no.
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->assertSame(0, $otroAdmin->tokens()->count());
    }

    public function test_un_usuario_sin_permiso_no_gestiona_roles(): void
    {
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO))
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    public function test_consulta_la_bitacora(): void
    {
        $this->comoUsuario($this->admin)->postJson('/api/roles', ['nombre_rol' => 'Bartender'])->assertCreated();

        $this->getJson('/api/bitacora?tipo_evento=rol_creado')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario.id_usuario', $this->admin->id_usuario)
            ->assertJsonStructure(['data', 'meta', 'tipos_evento']);
    }
}
