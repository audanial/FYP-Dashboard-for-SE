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

# TEMPORARY: verifying DatabaseSeeder runs cleanly against production Postgres.
# Remove this line once the next deploy confirms seeding worked.
php artisan db:seed --force

# TEMPORARY: one-off cleanup of stale October 2025 FYP2 rows before re-importing
# the corrected CSV (Group numbers were renumbered to fix a cross-batch pair
# collision). Remove this block once the next deploy confirms the delete ran
# and the corrected CSV has been re-imported.
php artisan tinker --execute="\Illuminate\Support\Facades\DB::table('fyp_projects')->where('semester', 'OCTOBER 2025')->where('fyp_phase', 'FYP 2')->delete();"

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
