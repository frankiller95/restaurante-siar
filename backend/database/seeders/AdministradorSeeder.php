<?php

namespace Database\Seeders;

use App\Enums\EstadoUsuario;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea el administrador inicial con los datos de config/siar.php (variables
 * SIAR_ADMIN_* del .env). Si ya existe, no lo modifica.
 */
class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('siar.admin');

        if (Usuario::where('correo', $config['correo'])->exists()) {
            return;
        }

        $contrasena = $config['contrasena'];
        if (blank($contrasena)) {
            $contrasena = Str::password(14);
            $this->command?->warn("SIAR_ADMIN_CONTRASENA no está definida. Contraseña generada para {$config['correo']}: {$contrasena}");
        }

        Usuario::create([
            'nombres' => $config['nombres'],
            'documento' => $config['documento'],
            'correo' => $config['correo'],
            'contrasena_cifrada' => $contrasena,
            'estado' => EstadoUsuario::ACTIVO,
            'id_rol' => Rol::where('nombre_rol', Rol::ADMINISTRADOR)->value('id_rol'),
        ]);
    }
}
