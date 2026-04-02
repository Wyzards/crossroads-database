#!/bin/sh

echo "Running Laravel startup tasks..."

php artisan config:clear
php artisan cache:clear
php artisan route:clear

# optional but recommended
php artisan config:cache

# start the main container process
exec "$@"
