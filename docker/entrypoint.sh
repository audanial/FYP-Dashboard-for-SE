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

# TEMPORARY: seeds the two demo accounts for the supervisor demo — see
# docker/tmp-demo-accounts.php for the script and rationale. Remove this
# line AND that file once the next deploy confirms both accounts work.
php artisan tinker --execute="require base_path('docker/tmp-demo-accounts.php');"

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
