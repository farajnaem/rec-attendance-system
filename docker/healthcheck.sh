#!/bin/sh
PORT="${PORT:-80}"
URL="http://127.0.0.1:${PORT}/ping.php"

if command -v curl >/dev/null 2>&1; then
    curl -fsS "$URL" >/dev/null 2>&1 && exit 0
fi

if command -v wget >/dev/null 2>&1; then
    wget -q -O /dev/null "$URL" 2>/dev/null && exit 0
fi

HEALTH_URL="$URL" php -r '
$url = getenv("HEALTH_URL") ?: "http://127.0.0.1:80/ping.php";
$ctx = stream_context_create(["http" => ["timeout" => 5, "ignore_errors" => true]]);
$body = @file_get_contents($url, false, $ctx);
exit ($body === "pong") ? 0 : 1;
'
