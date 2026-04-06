#!/bin/sh
set -e

php artisan migrate --force --no-interaction
php artisan db:seed --class=InventorySeeder --force --no-interaction

exec "$@"
