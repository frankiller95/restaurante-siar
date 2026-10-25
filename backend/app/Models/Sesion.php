<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sesión de un usuario = token de acceso de Sanctum guardado en la tabla `sesiones`.
 * Se registra como modelo de tokens en AppServiceProvider.
 */
class Sesion extends PersonalAccessToken
{
    protected $table = 'sesiones';

    public function esValida(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function revocar(): void
    {
        $this->delete();
    }
}
