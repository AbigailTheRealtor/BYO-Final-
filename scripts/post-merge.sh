#!/bin/bash
set -e

composer install --no-interaction --no-ansi 2>/dev/null || true
php artisan migrate --force --no-interaction
php artisan db:seed --class=UserSeeder --force --no-interaction || true
php artisan config:clear
php artisan view:clear
npm install --silent 2>/dev/null || true
npm run dev -- --no-progress 2>/dev/null || true
