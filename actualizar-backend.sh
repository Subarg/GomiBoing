#!/usr/bin/env bash
# Aplica los archivos de /backend sobre un proyecto Laravel YA creado, migra y limpia cachés.
# Uso: ./actualizar-backend.sh [ruta-del-proyecto-laravel]   (por defecto: api-gomiboing)
set -e
DEST="${1:-api-gomiboing}"
[ -d "$DEST/app" ] || { echo "No encuentro un proyecto Laravel en '$DEST'."; exit 1; }

cp -R backend/. "$DEST/"
cd "$DEST"
composer dump-autoload -q
php artisan migrate --force
php artisan optimize:clear

echo
echo "Verificación de versión (debe aparecer 'estado', 'fondos', 'surtido', 'recetas', 'sabores', 'cortes'):"
php artisan route:list --path=api | grep -E "estado|fondos|surtido|recetas|sabores|cortes" || echo "  ¡No aparecen! routes/api.php no se copió."
echo
echo "Reinicia el servidor (php artisan serve) y abre la app: si el backend sigue desactualizado verás un aviso rojo arriba."
