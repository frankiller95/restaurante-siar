<?php

return [

    /*
    | URL del frontend (Quasar). Se usa para armar el enlace de recuperación
    | de contraseña que llega por correo.
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:9000'),

    /*
    | Administrador inicial que crea el seeder (php artisan db:seed).
    | Si no se define la contraseña, el seeder genera una y la muestra en consola.
    */
    'admin' => [
        'nombres' => env('SIAR_ADMIN_NOMBRES', 'Administrador SIAR'),
        'documento' => env('SIAR_ADMIN_DOCUMENTO', '0000000000'),
        'correo' => env('SIAR_ADMIN_CORREO', 'admin@siar.local'),
        'contrasena' => env('SIAR_ADMIN_CONTRASENA'),
    ],

    /*
    | CU-03 Eliminar usuario: tablas que, si tienen registros del usuario,
    | obligan a una eliminación lógica (inactivar) en lugar de borrar la fila.
    | Las tablas que aún no existen (módulos futuros) se ignoran.
    */
    'dependencias_usuario' => [
        ['tabla' => 'pedidos', 'columna' => 'id_usuario'],
        ['tabla' => 'facturas', 'columna' => 'id_usuario'],
        ['tabla' => 'reservas', 'columna' => 'id_usuario'],
    ],

    /*
    | Duración de una sesión (token) en minutos. Por defecto, un turno de 8 h.
    */
    'duracion_sesion_minutos' => (int) env('SIAR_DURACION_SESION', 480),

];
