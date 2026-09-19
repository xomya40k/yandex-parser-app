#!/bin/sh
set -e

cd /var/www/html

wait_for_tcp() {
    host="$1"
    port="$2"
    label="$3"
    echo "Waiting for ${label} at ${host}:${port}..."
    i=0
    while ! php -r '
        $host = $argv[1];
        $port = (int) $argv[2];
        $errno = 0;
        $errstr = "";
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            exit(0);
        }
        exit(1);
    ' "$host" "$port"; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "${label} is still unavailable after 60s" >&2
            exit 1
        fi
        sleep 1
    done
}

ensure_app_key() {
    key_file="storage/app/.app_key"

    if [ -n "${APP_KEY:-}" ]; then
        return
    fi

    mkdir -p storage/app

    if [ ! -f "$key_file" ] || [ ! -s "$key_file" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$key_file"
        chmod 640 "$key_file"
        echo "Generated APP_KEY and saved to ${key_file}"
    else
        echo "Loaded APP_KEY from ${key_file}"
    fi

    APP_KEY="$(cat "$key_file")"
    export APP_KEY
}

# Named volumes start empty and would hide image contents — restore structure.
mkdir -p \
    storage/app \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Sync built frontend assets into the shared public volume (nginx reads it).
if [ -d /opt/public-dist ]; then
    mkdir -p public
    cp -a /opt/public-dist/. public/
fi

chown -R www-data:www-data storage bootstrap/cache public 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache

ensure_app_key

if [ -n "${DB_HOST:-}" ] && [ "${DB_CONNECTION:-}" = "mysql" ]; then
    wait_for_tcp "${DB_HOST}" "${DB_PORT:-3306}" "MySQL"
fi

if [ -n "${REDIS_HOST:-}" ] && { [ "${QUEUE_CONNECTION:-}" = "redis" ] || [ "${CACHE_STORE:-}" = "redis" ]; }; then
    wait_for_tcp "${REDIS_HOST}" "${REDIS_PORT:-6379}" "Redis"
fi

# Only the php-fpm (app) process should migrate/seed/cache once per boot.
# Workers and the scheduler skip this to avoid races and lock contention.
if [ "${1:-}" = "php-fpm" ]; then
    echo "Running migrations and seeders..."
    php artisan migrate --force
    php artisan db:seed --force

    echo "Caching config / routes / views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
