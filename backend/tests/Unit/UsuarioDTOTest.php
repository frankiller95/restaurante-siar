<?php

namespace Tests\Unit;

use App\DTOs\UsuarioDTO;
use App\Enums\EstadoUsuario;
use PHPUnit\Framework\TestCase;

class UsuarioDTOTest extends TestCase
{
    public function test_normaliza_los_datos_de_entrada(): void
    {
        $dto = UsuarioDTO::desdeArray([
            'nombres' => '  Laura Gómez ',
            'documento' => ' 1144000111 ',
            'correo' => ' Laura.Gomez@Restaurante.TEST ',
            'id_rol' => '3',
            'estado' => 'inactivo',
        ]);

        $this->assertSame('Laura Gómez', $dto->nombres);
        $this->assertSame('1144000111', $dto->documento);
        $this->assertSame('laura.gomez@restaurante.test', $dto->correo);
        $this->assertSame(3, $dto->idRol);
        $this->assertSame(EstadoUsuario::INACTIVO, $dto->estado);
    }

    public function test_sin_estado_no_lo_incluye_en_el_arreglo(): void
    {
        $dto = UsuarioDTO::desdeArray([
            'nombres' => 'Pedro',
            'documento' => '12345',
            'correo' => 'pedro@siar.test',
            'id_rol' => 2,
        ]);

        $this->assertNull($dto->estado);
        $this->assertArrayNotHasKey('estado', $dto->aArray());
        $this->assertSame(['nombres', 'documento', 'correo', 'id_rol'], array_keys($dto->aArray()));
    }
}
