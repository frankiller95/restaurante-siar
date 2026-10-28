#!/usr/bin/env bash
# Actualiza el backend de SIAR en el servidor con lo último de GitHub.
# Uso, desde la carpeta del proyecto en el servidor:
#   bash scripts/desplegar.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "-> Descargando cambios de GitHub..."
git pull --ff-only

cd backend

# En Hostinger PHP tiene desactivada la función proc_open, y Composer la usa
# para correr los scripts de Laravel. Por eso se instala con --no-scripts y
# esos pasos se ejecutan después con artisan.
echo "-> Instalando dependencias de PHP (sin las de desarrollo)..."
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

echo "-> Limpiando cachés y registrando paquetes de Laravel..."
php artisan optimize:clear
php artisan package:discover --ansi

echo "-> Aplicando migraciones pendientes..."
php artisan migrate --force

echo "-> Guardando en caché la configuración, rutas y vistas..."
php artisan optimize

echo "Listo: backend actualizado."
