#!/bin/sh
set -e

# Ensure SQLite database file exists (volume mount may hide the build-time file)
mkdir -p /var/www/database
touch /var/www/database/database.sqlite

# Run migrations automatically on first start
php artisan migrate --force --no-interaction

exec "$@"
