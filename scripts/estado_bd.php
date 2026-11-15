<?php

/**
 * Ayudante de scripts/desplegar.sh: lee datos de la base SIN modificar nada.
 *
 * Uso (desde cualquier carpeta):  php scripts/estado_bd.php <modo>
 *   entorno   → APP_ENV de la aplicación (production, local...)
 *   conexion  → driver, host, puerto, base, usuario y contraseña (una por línea)
 *   conteos   → "tabla<TAB>filas" de cada tabla de negocio (para comparar antes y
 *               después de migrar: ninguna debe perder filas)
 *
 * Ante cualquier error escribe el mensaje en STDERR y termina con código 1
 * (el script de despliegue se detiene). No usa Tinker (no se instala en
 * producción) ni escribe en la base de datos.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** @return list<string> líneas de salida del modo pedido */
function ejecutar(string $modo): array
{
    switch ($modo) {
        case 'entorno':
            return [(string) config('app.env')];

        case 'conexion':
            $conexion = config('database.connections.'.config('database.default'));

            return array_map(
                fn (string $clave) => (string) ($conexion[$clave] ?? ''),
                ['driver', 'host', 'port', 'database', 'username', 'password'],
            );

        case 'conteos':
            // Tablas que cambian solas con el uso normal (sesiones, caché, colas): no se comparan.
            $volatiles = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sesiones', 'solicitudes_recuperacion', 'migrations'];

            // Solo las tablas (no vistas) de la base actual.
            $tablas = [];
            foreach (DB::select('SHOW FULL TABLES') as $fila) {
                $valores = array_values((array) $fila);
                if (strtoupper((string) ($valores[1] ?? '')) === 'BASE TABLE') {
                    $tablas[] = (string) $valores[0];
                }
            }
            $tablas = array_unique($tablas);
            sort($tablas);

            $lineas = [];
            foreach ($tablas as $tabla) {
                if (! in_array($tabla, $volatiles, true)) {
                    $lineas[] = $tabla."\t".DB::table($tabla)->count();
                }
            }

            return $lineas;
    }

    throw new InvalidArgumentException('Modo desconocido. Usa: entorno | conexion | conteos');
}

try {
    $raiz = dirname(__DIR__).'/backend';
    require $raiz.'/vendor/autoload.php';
    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // Se arma toda la salida antes de imprimir: si algo falla, no queda salida a medias.
    $salida = ejecutar($argv[1] ?? '');
} catch (Throwable $e) {
    fwrite(STDERR, 'estado_bd.php: '.$e->getMessage().PHP_EOL);
    exit(1);
}

echo implode(PHP_EOL, $salida), PHP_EOL;
