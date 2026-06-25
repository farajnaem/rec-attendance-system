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
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
                }
                self::$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
            }
        }

        return self::$pdo;
    }
}
