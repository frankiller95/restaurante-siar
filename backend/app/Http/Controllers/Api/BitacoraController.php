<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\BitacoraRepositoryInterface;
use App\Enums\TipoEvento;
use App\Http\Controllers\Controller;
use App\Http\Resources\BitacoraResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Consulta de la bitácora de auditoría (RNF06).
 */
class BitacoraController extends Controller
{
    public function __construct(private readonly BitacoraRepositoryInterface $bitacora) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'tipo_evento' => ['nullable', Rule::enum(TipoEvento::class)],
            'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        return BitacoraResource::collection(
            $this->bitacora->buscar($filtros, (int) ($filtros['por_pagina'] ?? 20))
        )->additional([
            'tipos_evento' => array_map(fn (TipoEvento $t) => $t->value, TipoEvento::cases()),
        ]);
    }
}
