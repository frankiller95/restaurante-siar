<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\ProductoController;
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

// --- Módulo 2: gestión de menú (CU01 a CU05) ---------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Consulta: cualquiera que vea el menú, lo administre o gestione la disponibilidad.
    Route::middleware('permiso:consultar_menu,gestionar_menu,gestionar_disponibilidad')->group(function () {
        Route::get('menu', [ProductoController::class, 'menu']);
        Route::get('categorias', [CategoriaController::class, 'index']);
        Route::get('productos', [ProductoController::class, 'index']);
        Route::get('productos/{producto}', [ProductoController::class, 'show']);
    });

    // CU01 a CU04: el administrador gestiona productos y categorías.
    Route::middleware('permiso:gestionar_menu')->group(function () {
        Route::post('categorias', [CategoriaController::class, 'store']);
        Route::put('categorias/{categoria}', [CategoriaController::class, 'update']);
        Route::delete('categorias/{categoria}', [CategoriaController::class, 'destroy']);

        Route::post('productos', [ProductoController::class, 'store']);
        Route::put('productos/{producto}', [ProductoController::class, 'update']);
        Route::delete('productos/{producto}', [ProductoController::class, 'destroy']);
        Route::patch('productos/{producto}/categoria', [ProductoController::class, 'cambiarCategoria']);
    });

    // CU05: el cocinero (o el administrador) cambia la disponibilidad.
    Route::patch('productos/{producto}/disponibilidad', [ProductoController::class, 'cambiarDisponibilidad'])
        ->middleware('permiso:gestionar_disponibilidad');
});
