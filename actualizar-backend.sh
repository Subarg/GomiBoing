#!/usr/bin/env bash
# Aplica los archivos de /backend sobre un proyecto Laravel YA creado, migra y limpia cachés.
# Uso: ./actualizar-backend.sh [ruta-del-proyecto-laravel]   (por defecto: api-gomiboing)
set -e
DEST="${1:-api-gomiboing}"
[ -d "$DEST/app" ] || { echo "No encuentro un proyecto Laravel en '$DEST'."; exit 1; }

cp -R backend/. "$DEST/"
cd "$DEST"
php artisan migrate --force
php artisan optimize:clear

echo
echo "Rutas nuevas registradas (deben aparecer fondos, surtido y recetas):"
php artisan route:list --path=api/admin | grep -E "fondos|surtido|recetas" || echo "  ¡No aparecen! Revisa que routes/api.php se haya copiado."
