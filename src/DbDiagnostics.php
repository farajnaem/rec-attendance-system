<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

function dbConnectionSource(): string
{
    if (env('DATABASE_URL')) {
        return 'DATABASE_URL';
    }
    if (env('MYSQL_URL')) {
        return 'MYSQL_URL';
    }
    if (env('DB_URL')) {
        return 'DB_URL';
    }
    if (env('MYSQL_HOST') ?? env('MYSQLHOST')) {
        return 'MYSQL_HOST/...';
    }

    return 'DB_HOST/DB_USER/...';
}

function dbConfigForDebug(array $db): array
{
    return [
        'driver' => $db['driver'] ?? null,
        'host' => $db['host'] ?? null,
        'port' => $db['port'] ?? null,
        'name' => $db['name'] ?? null,
        'user' => $db['user'] ?? null,
        'password_set' => ($db['pass'] ?? '') !== '',
        'source' => dbConnectionSource(),
    ];
}

function testDatabaseConnection(): array
{
    if (!function_exists('app_config')) {
        require_once dirname(__DIR__) . '/config/config.php';
    }
    $config = app_config();
    $db = $config['db'];

    $result = [
        'ok' => false,
        'config' => dbConfigForDebug($db),
        'error' => null,
    ];

    try {
        if (($db['driver'] ?? 'mysql') === 'sqlite') {
            $pdo = new PDO('sqlite:' . $db['sqlite_path'], null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } else {
            $port = (int) ($db['port'] ?? 3306);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $port,
                $db['name'],
                $db['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        $pdo->query('SELECT 1');
        $result['ok'] = true;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}
