#!/bin/bash

echo "Starting initialization..."

# Wait for DB
echo "Waiting for database..."
until php artisan db:monitor --databases=mysql; do
  >&2 echo "MySQL is unavailable - sleeping"
  sleep 1
done

echo "MySQL is up - executing migrations"

# Run migrations
php artisan migrate --force

# Seed if necessary
php artisan db:seed --force

# Cache config and routes for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Initialization complete!"
