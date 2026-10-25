<?php

namespace App\Providers;

use App\Models\Sesion;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Los tokens de Sanctum se guardan en la tabla `sesiones` (MODELO_ER).
        Sanctum::usePersonalAccessTokenModel(Sesion::class);

        // Respuestas de un solo recurso sin la envoltura {"data": ...}.
        JsonResource::withoutWrapping();
    }
}
