#!/bin/sh
set -e

: "${PORT:=8080}"

# Render sets $PORT dynamically per deploy — nginx can't read env vars
# directly, so bake it into the config here at container start.
envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/sites-enabled/default

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan storage:link || true

php artisan migrate --force

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
