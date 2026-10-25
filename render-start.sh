#!/bin/sh
set -eu

: "${APP_KEY:?Set a persistent Laravel APP_KEY before deployment}"
: "${DATABASE_URL:?Set the Neon PostgreSQL connection URL}"
: "${APP_URL:?Set the public HTTPS URL}"

sed -i "s/^Listen 80$/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT:-10000}/" /etc/apache2/sites-available/000-default.conf
php artisan config:cache
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
