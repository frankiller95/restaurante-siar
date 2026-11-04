<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

/**
 * Categorías iniciales del menú (CU04: deben existir categorías para clasificar).
 * Si ya existen, no las modifica. En el servidor:
 *   php artisan db:seed --class=CategoriaSeeder --force
 */
class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            'Entradas' => 'Platos para empezar.',
            'Platos fuertes' => 'Platos principales.',
            'Bebidas' => 'Bebidas frías y calientes.',
            'Postres' => 'Dulces para terminar.',
        ];

        foreach ($categorias as $nombre => $descripcion) {
            Categoria::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion, 'activa' => true]);
        }
    }
}
