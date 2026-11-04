<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\MesaController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ReservaController;
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

// --- Módulo 3: gestión de mesas y reservas (RF12 a RF16) ---------------------
Route::middleware('auth:sanctum')->group(function () {
    // RF15: reservas y mesas disponibles (meseros, cajeros, administrador).
    // Va antes de mesas/{mesa} para que "disponibles" no se tome como un id.
    Route::middleware('permiso:gestionar_reservas,gestionar_mesas')->group(function () {
        Route::get('mesas/disponibles', [MesaController::class, 'disponibles']);
        Route::get('reservas', [ReservaController::class, 'index']);
        Route::post('reservas', [ReservaController::class, 'store']);
        Route::patch('reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
        Route::patch('reservas/{reserva}/llegada', [ReservaController::class, 'llegada']);

        // Ocupar (clientes sin reserva) y RF16 liberar.
        Route::patch('mesas/{mesa}/ocupar', [MesaController::class, 'ocupar']);
        Route::patch('mesas/{mesa}/liberar', [MesaController::class, 'liberar']);
    });

    // RF14: consultar el estado de las mesas.
    Route::middleware('permiso:consultar_mesas,gestionar_mesas,gestionar_reservas')->group(function () {
        Route::get('mesas', [MesaController::class, 'index']);
        Route::get('mesas/{mesa}', [MesaController::class, 'show']);
    });

    // RF12 y RF13: el administrador crea, modifica y da de baja mesas.
    Route::middleware('permiso:gestionar_mesas')->group(function () {
        Route::post('mesas', [MesaController::class, 'store']);
        Route::put('mesas/{mesa}', [MesaController::class, 'update']);
        Route::delete('mesas/{mesa}', [MesaController::class, 'destroy']);
        Route::patch('mesas/{mesa}/mantenimiento', [MesaController::class, 'mantenimiento']);
        Route::patch('mesas/{mesa}/habilitar', [MesaController::class, 'habilitar']);
    });
});
