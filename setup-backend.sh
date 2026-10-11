#!/usr/bin/env bash
# Crea un proyecto Laravel limpio y le aplica los archivos de /backend.
# Uso: ./setup-backend.sh   (requiere PHP 8.2+ y Composer)
set -e
composer create-project laravel/laravel api-gomiboing
cp -R backend/. api-gomiboing/
cd api-gomiboing
php artisan install:api --without-migration-prompt || php artisan install:api
cp .env.example .env 2>/dev/null || true
php artisan key:generate
echo "Configura DB_* en api-gomiboing/.env y luego ejecuta:"
echo "  php artisan migrate --seed && php artisan serve"
