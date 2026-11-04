<?php

namespace App\Services;

use App\Contracts\Repositories\CategoriaRepositoryInterface;
use App\Contracts\Repositories\ProductoRepositoryInterface;
use App\DTOs\ProductoDTO;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\Concerns\GestionaImagenes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CU01 Crear, CU02 Modificar, CU03 Eliminar, CU04 Categorizar y
 * CU05 Gestionar disponibilidad de productos del menú.
 */
class ProductoService
{
    use GestionaImagenes;

    public const CODIGO_NOMBRE_DUPLICADO = 'nombre_duplicado';

    private const CARPETA_IMAGENES = 'productos';

    public function __construct(
        private readonly ProductoRepositoryInterface $productos,
        private readonly CategoriaRepositoryInterface $categorias,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->productos->buscar($filtros, $porPagina);
    }

    /**
     * Carta del menú para meseros: categorías activas con sus productos activos.
     * Los agotados se muestran marcados para que no se pidan (CU05 paso 4).
     *
     * @return Collection<int, Categoria>
     */
    public function menu(): Collection
    {
        return $this->categorias->conProductosEnCarta();
    }

    /** CU01. El producto queda disponible por defecto. */
    public function crear(ProductoDTO $dto, ?UploadedFile $imagen = null): Producto
    {
        $categoria = $this->categoriaActiva($dto->idCategoria);
        $this->verificarNombre($dto);

        $ruta = $imagen ? $this->guardarImagen($imagen, self::CARPETA_IMAGENES) : null;

        $producto = $this->productos->crear([
            ...$dto->aArray(),
            'imagen_url' => $ruta,
            'disponible' => true,
        ]);

        EventoAuditable::dispatch(
            TipoEvento::PRODUCTO_CREADO,
            "Se creó el producto {$producto->nombre} en {$categoria->nombre} con precio {$producto->precio}.",
        );

        return $producto;
    }

    /**
     * CU02. Cambiar el precio no es retroactivo: los pedidos guardan su propio
     * precio unitario (Módulo 4).
     */
    public function actualizar(
        Producto $producto,
        ProductoDTO $dto,
        ?UploadedFile $imagen = null,
        bool $quitarImagen = false,
    ): Producto {
        $this->verificarActivo($producto);

        if ($dto->idCategoria !== $producto->id_categoria) {
            $this->categoriaActiva($dto->idCategoria);
        }
        $this->verificarNombre($dto, $producto->id_producto);

        $imagenAnterior = $producto->imagen_url;
        $datos = $dto->aArray();

        if ($imagen) {
            $datos['imagen_url'] = $this->guardarImagen($imagen, self::CARPETA_IMAGENES);
        } elseif ($quitarImagen) {
            $datos['imagen_url'] = null;
        }

        $producto->fill($datos);
        $cambios = array_keys($producto->getDirty());

        if ($cambios === []) {
            return $producto->loadMissing('categoria');
        }

        $producto = $this->productos->actualizar($producto, $datos);

        if (array_key_exists('imagen_url', $datos) && $imagenAnterior !== $datos['imagen_url']) {
            $this->eliminarImagen($imagenAnterior);
        }

        $lista = implode(', ', $cambios);
        EventoAuditable::dispatch(
            TipoEvento::PRODUCTO_ACTUALIZADO,
            "Se actualizó el producto {$producto->nombre}. Campos: {$lista}.",
        );

        return $producto;
    }

    /**
     * CU03. Baja lógica: se conserva para el historial de pedidos y RF34.
     * No procede si el producto tiene pedidos pendientes o en preparación.
     */
    public function eliminar(Producto $producto): void
    {
        $this->verificarActivo($producto);

        if ($this->productos->tienePedidosPendientes($producto)) {
            throw new ReglaNegocioException(
                'El producto tiene pedidos pendientes, por eso no se puede eliminar. Márcalo como "No disponible" mientras tanto.',
                409,
                'pedidos_pendientes',
            );
        }

        $producto->darDeBaja();

        EventoAuditable::dispatch(TipoEvento::PRODUCTO_ELIMINADO, "Se dio de baja el producto {$producto->nombre}.");
    }

    /** CU04. Asigna o cambia la categoría del producto. */
    public function cambiarCategoria(Producto $producto, int $idCategoria): Producto
    {
        $this->verificarActivo($producto);

        if ($producto->id_categoria === $idCategoria) {
            return $producto->loadMissing('categoria');
        }

        $categoria = $this->categoriaActiva($idCategoria);
        $anterior = $producto->categoria?->nombre;

        DB::transaction(fn () => $producto->cambiarCategoria($categoria));

        EventoAuditable::dispatch(
            TipoEvento::PRODUCTO_ACTUALIZADO,
            "El producto {$producto->nombre} pasó de la categoría {$anterior} a {$categoria->nombre}.",
        );

        return $producto->load('categoria');
    }

    /**
     * CU05. Marca el producto como disponible o agotado. Los pedidos que ya están
     * en preparación no se cancelan; solo se impide pedirlo de nuevo.
     */
    public function cambiarDisponibilidad(Producto $producto, bool $disponible): Producto
    {
        $this->verificarActivo($producto);

        if ($producto->disponible === $disponible) {
            return $producto->loadMissing('categoria');
        }

        $producto->marcarDisponible($disponible);

        $estado = $disponible ? 'disponible' : 'no disponible (agotado)';
        EventoAuditable::dispatch(TipoEvento::DISPONIBILIDAD_CAMBIADA, "El producto {$producto->nombre} quedó {$estado}.");

        return $producto->loadMissing('categoria');
    }

    /** CU01 flujo 3a: nombre repetido en la categoría → pedir confirmación. */
    private function verificarNombre(ProductoDTO $dto, ?int $exceptoId = null): void
    {
        if ($dto->confirmarDuplicado) {
            return;
        }

        if ($this->productos->existeNombreEnCategoria($dto->nombre, $dto->idCategoria, $exceptoId)) {
            throw new ReglaNegocioException(
                "Ya existe un producto llamado \"{$dto->nombre}\" en esta categoría. Confirma si quieres registrarlo de todas formas o cambia el nombre.",
                409,
                self::CODIGO_NOMBRE_DUPLICADO,
            );
        }
    }

    private function categoriaActiva(int $idCategoria): Categoria
    {
        $categoria = $this->categorias->obtenerPorId($idCategoria);

        if (! $categoria || ! $categoria->activa) {
            throw ValidationException::withMessages(['id_categoria' => 'La categoría seleccionada no existe o está inactiva.']);
        }

        return $categoria;
    }

    private function verificarActivo(Producto $producto): void
    {
        if (! $producto->estaActivo()) {
            throw new ReglaNegocioException('El producto está dado de baja y no se puede modificar.');
        }
    }
}
