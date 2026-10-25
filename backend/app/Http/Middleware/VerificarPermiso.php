<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RNF05 Control de acceso por roles. Uso en rutas: ->middleware('permiso:gestionar_usuarios').
 * Si se indican varios permisos, basta con tener uno.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        /** @var Usuario|null $usuario */
        $usuario = $request->user();

        if (! $usuario || ! $usuario->estaActivo()) {
            abort(403, 'Tu usuario no está activo.');
        }

        foreach ($permisos as $permiso) {
            if ($usuario->tienePermiso($permiso)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permiso para realizar esta acción.');
    }
}
