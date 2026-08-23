#!/bin/sh
set -e

# Railway assigns a dynamic port via $PORT at container start — Apache's
# default config hardcodes port 80, so we rewrite it here every time the
# container boots (this can't be done at build time since $PORT isn't
# known yet during the Docker build).
: "${PORT:=80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/*.conf

# Config caching must happen here (at runtime), not during the Docker
# build — Railway injects real DB credentials only when the container
# starts, so caching config during build would freeze in blank values.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Safe to run every boot — creates the symlink if missing, no-ops if it
# already exists.
php artisan storage:link || true

# Applies any pending migrations. --force skips the interactive
# confirmation prompt, which would otherwise hang a non-interactive deploy.
php artisan migrate --force

exec apache2-foreground