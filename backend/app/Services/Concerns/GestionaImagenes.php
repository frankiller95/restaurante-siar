<?php

namespace App\Services\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda y elimina imágenes en el disco "imagenes" (backend/public/imagenes).
 * Lo usa ProductoService (CU01, CU02) y se puede reutilizar en otros módulos.
 */
trait GestionaImagenes
{
    /** Guarda el archivo con un nombre único y devuelve su ruta relativa (ej. "productos/abc.jpg"). */
    protected function guardarImagen(UploadedFile $archivo, string $carpeta): string
    {
        return $archivo->store($carpeta, 'imagenes');
    }

    protected function eliminarImagen(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('imagenes')->delete($ruta);
        }
    }

    /** URL pública de la imagen, o null si no tiene. */
    public static function urlImagen(?string $ruta): ?string
    {
        return $ruta ? Storage::disk('imagenes')->url($ruta) : null;
    }
}
