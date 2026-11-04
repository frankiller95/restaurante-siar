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

    /*
    | CU03 Eliminar producto: no se da de baja si está en pedidos con estos
    | estados. Las tablas llegan con el Módulo 4 (Pedidos); mientras no existan,
    | se asume que no hay pedidos pendientes.
    */
    'pedidos' => [
        'tabla_pedidos' => 'pedidos',
        'tabla_detalle' => 'detalle_pedidos',
        'estados_pendientes' => ['pendiente', 'en_preparacion'],
        // RF16: una mesa con pedidos en estos estados no se puede liberar.
        'estados_abiertos' => ['pendiente', 'en_preparacion', 'listo', 'entregado'],
    ],

    /*
    | Zona horaria del restaurante: la fecha y hora de las reservas se
    | interpretan en esta zona (la app guarda sus marcas de tiempo en UTC).
    */
    'zona_horaria' => env('SIAR_ZONA_HORARIA', 'America/Bogota'),

    /*
    | RF15 Reservas (en minutos):
    | - duracion: tiempo que se aparta la mesa; dos reservas de la misma mesa
    |   no pueden cruzarse dentro de este tiempo.
    | - ventana: cuánto antes de la hora la mesa pasa a "Reservada".
    | - tolerancia: cuánto se espera al cliente; después queda "No asistió".
    */
    'reservas' => [
        'duracion_minutos' => (int) env('SIAR_RESERVA_DURACION', 120),
        'ventana_minutos' => (int) env('SIAR_RESERVA_VENTANA', 60),
        'tolerancia_minutos' => (int) env('SIAR_RESERVA_TOLERANCIA', 20),
    ],

    /*
    | Imágenes de productos: tamaño máximo en KB y formatos permitidos.
    */
    'imagenes' => [
        'max_kb' => 2048,
        'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

];
