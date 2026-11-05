<?php

namespace App\Providers;

use App\Contracts\Repositories\BitacoraRepositoryInterface;
use App\Contracts\Repositories\CategoriaRepositoryInterface;
use App\Contracts\Repositories\InsumoRepositoryInterface;
use App\Contracts\Repositories\MesaRepositoryInterface;
use App\Contracts\Repositories\PedidoRepositoryInterface;
use App\Contracts\Repositories\PermisoRepositoryInterface;
use App\Contracts\Repositories\ProductoRepositoryInterface;
use App\Contracts\Repositories\ReservaRepositoryInterface;
use App\Contracts\Repositories\RolRepositoryInterface;
use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\Contracts\Services\ServicioCorreo;
use App\Repositories\Eloquent\BitacoraRepository;
use App\Repositories\Eloquent\CategoriaRepository;
use App\Repositories\Eloquent\InsumoRepository;
use App\Repositories\Eloquent\MesaRepository;
use App\Repositories\Eloquent\PedidoRepository;
use App\Repositories\Eloquent\PermisoRepository;
use App\Repositories\Eloquent\ProductoRepository;
use App\Repositories\Eloquent\ReservaRepository;
use App\Repositories\Eloquent\RolRepository;
use App\Repositories\Eloquent\UsuarioRepository;
use App\Services\Correo\ServicioCorreoLaravel;
use Illuminate\Support\ServiceProvider;

/**
 * Une cada interfaz (Repository) con su implementación.
 *
 * Patrón Singleton: se registran con singleton(), así el contenedor de Laravel
 * crea una única instancia por petición y la reutiliza en todos los servicios.
 * (La conexión a la base de datos también es un singleton del framework: 'db').
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        UsuarioRepositoryInterface::class => UsuarioRepository::class,
        RolRepositoryInterface::class => RolRepository::class,
        PermisoRepositoryInterface::class => PermisoRepository::class,
        BitacoraRepositoryInterface::class => BitacoraRepository::class,
        CategoriaRepositoryInterface::class => CategoriaRepository::class,
        ProductoRepositoryInterface::class => ProductoRepository::class,
        MesaRepositoryInterface::class => MesaRepository::class,
        ReservaRepositoryInterface::class => ReservaRepository::class,
        PedidoRepositoryInterface::class => PedidoRepository::class,
        InsumoRepositoryInterface::class => InsumoRepository::class,
        ServicioCorreo::class => ServicioCorreoLaravel::class,
    ];
}
