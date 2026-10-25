#!/usr/bin/env bash
# Actualiza el backend de SIAR en el servidor con lo último de GitHub.
# Uso, desde la carpeta del proyecto en el servidor:
#   bash scripts/desplegar.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "-> Descargando cambios de GitHub..."
git pull --ff-only

cd backend

echo "-> Instalando dependencias de PHP (sin las de desarrollo)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "-> Aplicando migraciones pendientes..."
php artisan migrate --force

echo "-> Guardando en caché la configuración, rutas y vistas..."
php artisan optimize

echo "Listo: backend actualizado."
