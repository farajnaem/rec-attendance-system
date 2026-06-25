<?php

declare(strict_types=1);

/**
 * إعدادات التطبيق — تُقرأ من متغيرات البيئة (Docker / Coolify).
 */

if (!defined('REC_CONFIG_LOADED')) {
    define('REC_CONFIG_LOADED', true);

    $envFile = dirname(__DIR__) . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('envBool')) {
    function envBool(string $key, bool $default = false): bool
    {
        $value = env($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('parseDatabaseUrl')) {
    function parseDatabaseUrl(?string $url): ?array
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parsed = parse_url(trim($url));
        if ($parsed === false || !isset($parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['mysql', 'mysqli'], true)) {
            return null;
        }

        $database = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
        if ($database === '') {
            $database = 'default';
        }

        return [
            'driver' => 'mysql',
            'host' => $parsed['host'],
            'port' => isset($parsed['port']) ? (int) $parsed['port'] : 3306,
            'name' => rawurldecode($database),
            'user' => isset($parsed['user']) ? rawurldecode((string) $parsed['user']) : '',
            'pass' => isset($parsed['pass']) ? rawurldecode((string) $parsed['pass']) : '',
            'charset' => 'utf8mb4',
        ];
    }
}

if (!function_exists('resolveDatabaseConfig')) {
    function resolveDatabaseConfig(): array
    {
        $driver = env('DB_DRIVER', 'mysql');

        $db = [
            'driver' => $driver,
            'sqlite_path' => (static function (): string {
                $path = env('DB_SQLITE_PATH', dirname(__DIR__) . '/database/attendance.sqlite');
                if ($path === null || $path === '') {
                    return dirname(__DIR__) . '/database/attendance.sqlite';
                }
                if (!preg_match('~^([A-Za-z]:)?[/\\\\]~', $path)) {
                    return dirname(__DIR__) . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
                }
                return $path;
            })(),
            'host' => env('DB_HOST', 'localhost'),
            'port' => (int) env('DB_PORT', '3306'),
            'name' => env('DB_NAME', 'rec_attendance'),
            'user' => env('DB_USER', 'root'),
            'pass' => env('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ];

        $dbUrl = env('DATABASE_URL') ?? env('MYSQL_URL') ?? env('DB_URL');
        $fromUrl = parseDatabaseUrl($dbUrl);
        if ($fromUrl !== null) {
            $db = array_merge($db, $fromUrl);
        }

        return $db;
    }
}

if (!function_exists('app_config')) {
    function app_config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = [
                'db' => resolveDatabaseConfig(),
                'app' => [
                    'name' => env('APP_NAME', 'جمعية مركز الإرشاد التربوي REC'),
                    'url' => rtrim(env('APP_URL', 'http://localhost:8080'), '/'),
                    'base_path' => env('APP_BASE_PATH', ''),
                    'default_timezone' => env('APP_TIMEZONE', 'Asia/Jerusalem'),
                    'debug' => envBool('APP_DEBUG', false),
                    'setup_enabled' => envBool('SETUP_ENABLED', false),
                ],
            ];
        }
        return $cfg;
    }
}

return app_config();
