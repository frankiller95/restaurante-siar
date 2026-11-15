<?php

namespace App\Providers;

use App\Facturacion\CalculadoraFactura;
use App\Models\Sesion;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // RF29: la tarifa y la forma de calcular el impuesto salen de config/siar.php.
        $this->app->bind(CalculadoraFactura::class, fn () => CalculadoraFactura::desdeConfiguracion());
    }

    public function boot(): void
    {
        // Los tokens de Sanctum se guardan en la tabla `sesiones` (MODELO_ER).
        Sanctum::usePersonalAccessTokenModel(Sesion::class);

        // Respuestas de un solo recurso sin la envoltura {"data": ...}.
        JsonResource::withoutWrapping();
    }
}
