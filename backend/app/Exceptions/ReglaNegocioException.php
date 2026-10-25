<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una operación viola una regla de negocio de los casos de uso
 * (por ejemplo, un administrador que intenta eliminarse a sí mismo).
 * Se responde como JSON con el código HTTP indicado (ver bootstrap/app.php).
 */
class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly int $codigoHttp = 422)
    {
        parent::__construct($mensaje);
    }
}
