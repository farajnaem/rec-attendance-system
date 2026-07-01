#!/bin/bash
set -e

cd /var/www/html

PORT="${PORT:-80}"
if [ "$PORT" != "80" ]; then
    echo "Configuring Apache to listen on port ${PORT}..."
    sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

mkdir -p /var/www/html/storage/documents
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true

has_mysql_config() {
    [ -n "${DATABASE_URL:-}" ] \
        || [ -n "${MYSQL_URL:-}" ] \
        || [ -n "${DB_URL:-}" ] \
        || [ -n "${MYSQL_HOST:-}" ] \
        || [ -n "${MYSQLHOST:-}" ] \
        || { [ -n "${DB_HOST:-}" ] && [ "${DB_DRIVER:-}" != "sqlite" ]; }
}

uses_mysql() {
    if [ "${DB_DRIVER:-}" = "sqlite" ]; then
        return 1
    fi
    if [ "${SKIP_DB_INIT:-}" = "true" ] || [ "${SKIP_DB_INIT:-}" = "1" ]; then
        return 1
    fi
    has_mysql_config
}

if uses_mysql; then
    echo "Waiting for MySQL..."
    attempts=0
    max_attempts=45
    until php docker/wait-db.php; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "WARNING: MySQL not ready after $((max_attempts * 2)) seconds."
            echo "Starting Apache anyway — fix DATABASE_URL / MYSQL_* and redeploy."
            break
        fi
        sleep 2
    done

    if [ "$attempts" -lt "$max_attempts" ]; then
        echo "Initializing database..."
        php docker/init-db.php || echo "WARNING: Database initialization failed. Apache will still start."
    fi
else
    echo "No MySQL configuration detected — skipping DB init (set DATABASE_URL or MYSQLHOST for production)."
fi

echo "Starting Apache on port ${PORT}..."
exec "$@"
