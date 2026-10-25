<?php

namespace Tests\Unit;

use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\Contracts\Services\ServicioCorreo;
use App\DTOs\UsuarioDTO;
use App\Enums\EstadoUsuario;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Prueba unitaria del servicio aislado de la base de datos: gracias al patrón
 * Repository, el repositorio y el correo se reemplazan por dobles de prueba.
 */
class UsuarioServiceTest extends TestCase
{
    private UsuarioRepositoryInterface&MockInterface $repositorio;

    private ServicioCorreo&MockInterface $correo;

    private UsuarioService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->repositorio = Mockery::mock(UsuarioRepositoryInterface::class);
        $this->correo = Mockery::mock(ServicioCorreo::class);
        $this->servicio = new UsuarioService($this->repositorio, $this->correo);
    }

    private function dto(): UsuarioDTO
    {
        return new UsuarioDTO('Laura Gómez', '1144000111', 'laura@siar.test', 2);
    }

    private function usuario(int $id, int $idRol = 2): Usuario
    {
        $usuario = new Usuario([
            'nombres' => 'Laura Gómez',
            'documento' => '1144000111',
            'correo' => 'laura@siar.test',
            'id_rol' => $idRol,
            'estado' => EstadoUsuario::ACTIVO,
        ]);
        $usuario->id_usuario = $id;
        $usuario->exists = true;
        $usuario->setRelation('rol', new Rol(['nombre_rol' => 'Mesero']));

        return $usuario;
    }

    public function test_registrar_crea_el_usuario_activo_con_contrasena_temporal(): void
    {
        $this->repositorio->shouldReceive('existeCorreo')->andReturnFalse();
        $this->repositorio->shouldReceive('existeDocumento')->andReturnFalse();
        $this->repositorio->shouldReceive('crear')->once()
            ->withArgs(fn (array $datos) => $datos['estado'] === EstadoUsuario::ACTIVO
                && strlen($datos['contrasena_cifrada']) === 12
                && $datos['correo'] === 'laura@siar.test')
            ->andReturn($this->usuario(10));
        $this->correo->shouldReceive('enviarCredenciales')->once()->andReturnTrue();

        $resultado = $this->servicio->registrar($this->dto());

        $this->assertTrue($resultado['correo_enviado']);
        Event::assertDispatched(EventoAuditable::class);
    }

    public function test_registrar_rechaza_correo_duplicado(): void
    {
        $this->repositorio->shouldReceive('existeCorreo')->andReturnTrue();
        $this->repositorio->shouldReceive('existeDocumento')->andReturnFalse();
        $this->repositorio->shouldNotReceive('crear');

        $this->expectException(ValidationException::class);

        $this->servicio->registrar($this->dto());
    }

    public function test_registrar_informa_si_el_correo_no_se_envio(): void
    {
        $this->repositorio->shouldReceive('existeCorreo', 'existeDocumento')->andReturnFalse();
        $this->repositorio->shouldReceive('crear')->andReturn($this->usuario(10));
        $this->correo->shouldReceive('enviarCredenciales')->andReturnFalse();

        $resultado = $this->servicio->registrar($this->dto());

        $this->assertFalse($resultado['correo_enviado']);
        Event::assertDispatchedTimes(EventoAuditable::class, 2);
    }

    public function test_un_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = $this->usuario(1);
        $this->repositorio->shouldNotReceive('eliminar');

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No puedes eliminar tu propia cuenta.');

        $this->servicio->eliminar($admin, $admin);
    }

    public function test_un_administrador_no_puede_cambiar_su_propio_rol(): void
    {
        $admin = $this->usuario(1, idRol: 1);

        $this->expectException(ReglaNegocioException::class);

        $this->servicio->actualizar($admin, new UsuarioDTO('Laura Gómez', '1144000111', 'laura@siar.test', 2), $admin);
    }

    public function test_actualizar_sin_cambios_no_toca_el_repositorio(): void
    {
        $usuario = $this->usuario(5);
        $admin = $this->usuario(1);
        $this->repositorio->shouldReceive('existeCorreo', 'existeDocumento')->andReturnFalse();
        $this->repositorio->shouldNotReceive('actualizar');
        $this->correo->shouldNotReceive('enviarNotificacionCambio');

        $resultado = $this->servicio->actualizar(
            $usuario,
            new UsuarioDTO('Laura Gómez', '1144000111', 'laura@siar.test', 2, EstadoUsuario::ACTIVO),
            $admin,
        );

        $this->assertFalse($resultado['sesiones_revocadas']);
    }
}
