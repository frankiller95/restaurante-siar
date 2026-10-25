<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

/** Reglas de seguridad de contraseña compartidas (CU-06 paso 6). */
final class ReglasContrasena
{
    /** @return array<int, mixed> */
    public static function nueva(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers(), 'max:72'];
    }
}
