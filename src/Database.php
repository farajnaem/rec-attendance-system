<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $pdo = null;

    public static function resetConnection(): void
    {
        self::$pdo = null;
    }

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            if (!function_exists('app_config')) {
                require_once dirname(__DIR__) . '/config/config.php';
            }
            $config = app_config();
            $db = $config['db'];

            if (($db['driver'] ?? 'mysql') === 'sqlite') {
                $path = $db['sqlite_path'];
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $port = (int) ($db['port'] ?? 3306);
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $db['host'],
                    $port,
                    $db['name'],
                    $db['charset']
                );
                self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
        }

        return self::$pdo;
    }
}
