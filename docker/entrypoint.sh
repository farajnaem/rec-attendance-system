#!/bin/bash

cd /var/www/html

PORT="${PORT:-80}"
if [ "$PORT" != "80" ]; then
    echo "Configuring Apache to listen on port ${PORT}..."
    sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

uses_mysql() {
    [ -n "${DATABASE_URL:-}" ] \
        || [ -n "${MYSQL_URL:-}" ] \
        || [ -n "${DB_URL:-}" ] \
        || [ -n "${MYSQL_HOST:-}" ] \
        || [ "${DB_DRIVER:-mysql}" = "mysql" ]
}

if uses_mysql; then
    echo "Scheduling database init in background..."
    (
        sleep 3
        attempts=0
        until php docker/wait-db.php; do
            attempts=$((attempts + 1))
            if [ "$attempts" -ge 30 ]; then
                echo "WARNING: MySQL not ready after 60 seconds."
                exit 0
            fi
            sleep 2
        done
        php docker/init-db.php || echo "WARNING: schema init failed."
    ) &
fi

echo "Starting Apache on port ${PORT}..."
exec "$@"
