<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| API REST de SIAR. Todas las rutas quedan bajo el prefijo /api.
*/

// --- Módulo 1: autenticación (CU-05, CU-06) ---------------------------------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('recuperar-contrasena', [AuthController::class, 'solicitarRecuperacion'])->middleware('throttle:3,1');
    Route::post('restablecer-contrasena', [AuthController::class, 'restablecerContrasena'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('perfil', [AuthController::class, 'perfil']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('contrasena', [AuthController::class, 'cambiarContrasena']);
    });
});

// --- Módulo 1: gestión de usuarios, roles y bitácora (CU-01 a CU-04) --------
Route::middleware(['auth:sanctum', 'permiso:gestionar_usuarios'])->group(function () {
    Route::apiResource('usuarios', UsuarioController::class);
    Route::post('usuarios/{usuario}/reenviar-credenciales', [UsuarioController::class, 'reenviarCredenciales']);

    Route::apiResource('roles', RolController::class)->parameters(['roles' => 'rol']);
    Route::put('roles/{rol}/permisos', [RolController::class, 'asignarPermisos']);

    Route::get('permisos', [PermisoController::class, 'index']);
    Route::get('bitacora', [BitacoraController::class, 'index']);
});
