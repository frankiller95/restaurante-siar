<?php

use App\Exceptions\ReglaNegocioException;
use App\Http\Middleware\VerificarPermiso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permiso' => VerificarPermiso::class,
        ]);

        // El login está en el frontend (Quasar): la API no redirige a una ruta
        // "login" del backend, responde 401 en JSON. Fuera de la API, va al inicio.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // La API siempre responde en JSON.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Reglas de negocio de los casos de uso -> {"message": "..."} con su código HTTP.
        $exceptions->render(function (ReglaNegocioException $e) {
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'codigo' => $e->codigo,
            ]), $e->codigoHttp);
        });
    })->create();
