#!/bin/sh
set -e

# Run migrations (PostgreSQL is guaranteed healthy by compose healthcheck)
php artisan migrate --force --no-interaction

exec "$@"
