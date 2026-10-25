<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\PermisoRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\PermisoResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermisoController extends Controller
{
    public function __construct(private readonly PermisoRepositoryInterface $permisos) {}

    public function index(): AnonymousResourceCollection
    {
        return PermisoResource::collection($this->permisos->todos());
    }
}
