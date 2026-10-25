<?php

namespace Database\Seeders;

use App\Enums\CodigoPermiso;
use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CodigoPermiso::cases() as $permiso) {
            Permiso::updateOrCreate(
                ['codigo_permiso' => $permiso->value],
                ['nombre' => $permiso->nombre(), 'descripcion' => $permiso->descripcion()],
            );
        }
    }
}
