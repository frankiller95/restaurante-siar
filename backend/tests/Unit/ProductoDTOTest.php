<?php

namespace Tests\Unit;

use App\DTOs\ProductoDTO;
use PHPUnit\Framework\TestCase;

class ProductoDTOTest extends TestCase
{
    public function test_normaliza_los_datos_del_producto(): void
    {
        $dto = ProductoDTO::desdeArray([
            'nombre' => '  Ajiaco santafereño ',
            'descripcion' => '   ',
            'precio' => '24000',
            'id_categoria' => '3',
            'confirmar_duplicado' => '1',
        ]);

        $this->assertSame('Ajiaco santafereño', $dto->nombre);
        $this->assertNull($dto->descripcion);
        $this->assertSame('24000.00', $dto->precio);
        $this->assertSame(3, $dto->idCategoria);
        $this->assertTrue($dto->confirmarDuplicado);
        $this->assertSame(['nombre', 'descripcion', 'precio', 'id_categoria'], array_keys($dto->aArray()));
    }

    public function test_sin_confirmacion_no_acepta_duplicados(): void
    {
        $dto = ProductoDTO::desdeArray(['nombre' => 'Flan', 'precio' => 9000, 'id_categoria' => 1]);

        $this->assertFalse($dto->confirmarDuplicado);
    }
}
