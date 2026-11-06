#!/bin/sh
set -eu

: "${APP_KEY:?Set a persistent Laravel APP_KEY before deployment}"
: "${DATABASE_URL:?Set the Neon PostgreSQL connection URL}"
: "${APP_URL:?Set the public HTTPS URL}"

php artisan config:cache

case "${PROCESS_ROLE:-web}" in
    release)
        exec php artisan migrate --force --no-interaction
        ;;
    worker)
        exec php artisan queue:work --sleep=3 --tries=3 --timeout=60 --max-time=3600 --no-interaction
        ;;
    scheduler)
        exec php artisan schedule:work --no-interaction
        ;;
    web) ;;
    *) echo "PROCESS_ROLE must be web, release, worker or scheduler" >&2; exit 1 ;;
esac

sed -i "s/^Listen 80$/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT:-10000}/" /etc/apache2/sites-available/000-default.conf
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
