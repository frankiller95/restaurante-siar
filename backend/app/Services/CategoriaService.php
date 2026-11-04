<?php

namespace App\Services;

use App\Contracts\Repositories\CategoriaRepositoryInterface;
use App\DTOs\CategoriaDTO;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Categoria;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * CU04 Categorizar producto: administración de las categorías del menú.
 */
class CategoriaService
{
    public function __construct(private readonly CategoriaRepositoryInterface $categorias) {}

    /** @return Collection<int, Categoria> */
    public function listar(bool $soloActivas = false): Collection
    {
        return $this->categorias->todas($soloActivas);
    }

    public function crear(CategoriaDTO $dto): Categoria
    {
        $this->verificarNombreUnico($dto->nombre);

        $categoria = $this->categorias->crear([...$dto->aArray(), 'activa' => $dto->activa ?? true]);

        EventoAuditable::dispatch(TipoEvento::CATEGORIA_CREADA, "Se creó la categoría {$categoria->nombre}.");

        return $categoria;
    }

    public function actualizar(Categoria $categoria, CategoriaDTO $dto): Categoria
    {
        $this->verificarNombreUnico($dto->nombre, $categoria->id_categoria);

        $anterior = $categoria->nombre;
        $categoria = $this->categorias->actualizar($categoria, $dto->aArray());

        $estado = $categoria->activa ? 'activa' : 'inactiva';
        EventoAuditable::dispatch(
            TipoEvento::CATEGORIA_ACTUALIZADA,
            "Se actualizó la categoría {$anterior} (ahora: {$categoria->nombre}, {$estado}).",
        );

        return $categoria;
    }

    /**
     * Solo se borra una categoría sin productos (ni siquiera dados de baja, que
     * siguen en el historial). Si tiene productos, se debe desactivar.
     */
    public function eliminar(Categoria $categoria): void
    {
        if ($categoria->tieneProductos()) {
            throw new ReglaNegocioException(
                'La categoría tiene productos asociados, por eso no se puede eliminar. Puedes desactivarla para ocultarla del menú.',
                409,
            );
        }

        $nombre = $categoria->nombre;
        $this->categorias->eliminar($categoria);

        EventoAuditable::dispatch(TipoEvento::CATEGORIA_ELIMINADA, "Se eliminó la categoría {$nombre}.");
    }

    private function verificarNombreUnico(string $nombre, ?int $exceptoId = null): void
    {
        if ($this->categorias->existeNombre($nombre, $exceptoId)) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una categoría con este nombre.']);
        }
    }
}
