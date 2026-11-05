<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\InsumoController;
use App\Http\Controllers\Api\MesaController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\RecetaController;
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

// --- Módulo 4: pedidos (RF17 a RF22) -----------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // RF22: cola de cocina (cocinero y administrador). Va antes de pedidos/{pedido}.
    Route::get('pedidos/cocina', [PedidoController::class, 'cocina'])->middleware('permiso:preparar_pedidos');

    // RF22: consultar pedidos (meseros, cocina y caja).
    Route::middleware('permiso:tomar_pedidos,preparar_pedidos,facturar')->group(function () {
        Route::get('pedidos', [PedidoController::class, 'index']);
        Route::get('pedidos/{pedido}', [PedidoController::class, 'show']);
    });

    // RF17 a RF21: el mesero toma, modifica, envía, cancela y entrega.
    Route::middleware('permiso:tomar_pedidos')->group(function () {
        Route::post('pedidos', [PedidoController::class, 'store']);
        Route::put('pedidos/{pedido}', [PedidoController::class, 'update']);
        Route::post('pedidos/{pedido}/productos', [PedidoController::class, 'agregarProducto']);
        Route::put('pedidos/{pedido}/productos/{detalle}', [PedidoController::class, 'modificarProducto'])->scopeBindings();
        Route::delete('pedidos/{pedido}/productos/{detalle}', [PedidoController::class, 'quitarProducto'])->scopeBindings();
        Route::patch('pedidos/{pedido}/enviar-cocina', [PedidoController::class, 'enviarACocina']);
        Route::patch('pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar']);
        Route::patch('pedidos/{pedido}/entregar', [PedidoController::class, 'entregar']);
    });

    // Cocina: el cocinero inicia la preparación y marca el pedido como listo.
    Route::middleware('permiso:preparar_pedidos')->group(function () {
        Route::patch('pedidos/{pedido}/preparar', [PedidoController::class, 'preparar']);
        Route::patch('pedidos/{pedido}/listo', [PedidoController::class, 'listo']);
    });
});

// --- Módulo 5: inventario (RF23 a RF27, RF38 a RF40) --------------------------
Route::middleware(['auth:sanctum', 'permiso:gestionar_inventario'])->group(function () {
    // Va antes de insumos/{insumo} para que "alertas" no se tome como un id.
    Route::get('insumos/alertas', [InsumoController::class, 'alertas']);

    Route::get('insumos', [InsumoController::class, 'index']);
    Route::post('insumos', [InsumoController::class, 'store']);
    Route::get('insumos/{insumo}', [InsumoController::class, 'show']);
    Route::put('insumos/{insumo}', [InsumoController::class, 'update']);
    Route::delete('insumos/{insumo}', [InsumoController::class, 'destroy']);

    Route::post('insumos/{insumo}/entradas', [InsumoController::class, 'entrada'])->name('insumos.entradas');
    Route::post('insumos/{insumo}/mermas', [InsumoController::class, 'merma'])->name('insumos.mermas');
    Route::post('insumos/{insumo}/ajustes', [InsumoController::class, 'ajuste'])->name('insumos.ajustes');
    Route::get('insumos/{insumo}/movimientos', [InsumoController::class, 'movimientos']);
});

// RF38: recetas (inventario o administración del menú).
Route::middleware(['auth:sanctum', 'permiso:gestionar_inventario,gestionar_menu'])->group(function () {
    Route::get('productos/{producto}/receta', [RecetaController::class, 'show']);
    Route::put('productos/{producto}/receta', [RecetaController::class, 'update']);
});
