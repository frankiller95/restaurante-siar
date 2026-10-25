<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;

/**
 * Datos del usuario autenticado + sus permisos, para que el frontend arme el
 * menú según el rol (CU-05 paso 6).
 *
 * @mixin Usuario
 */
class PerfilResource extends UsuarioResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'permisos' => $this->codigosPermiso(),
        ];
    }
}
