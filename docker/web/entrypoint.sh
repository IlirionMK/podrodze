#!/usr/bin/env sh
set -e

cd /var/www/backend

php artisan config:clear || true
php artisan cache:clear || true

php artisan migrate --force

php artisan db:seed --class=CategorySeeder --force || true

php-fpm -D
exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
