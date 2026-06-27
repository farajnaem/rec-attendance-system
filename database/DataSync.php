<?php

declare(strict_types=1);

/**
 * نقل البيانات بين SQLite (محلي) و MySQL (إنتاج).
 */
final class DataSync
{
  /** ترتيب الجداول حسب علاقات المفاتيح الأجنبية */
    private const TABLES = [
        'users',
        'departments',
        'work_schedule',
        'work_locations',
        'department_supervisors',
        'user_departments',
        'user_permissions',
        'daily_tasks',
        'task_completions',
        'task_evaluations',
        'attendance_records',
        'approved_leaves',
        'cross_department_assignments',
        'monthly_narrative_reports',
        'task_replies',
        'documents',
        'job_description_profiles',
        'job_description_duties',
        'schema_migrations',
    ];

    public static function defaultSqlitePath(): string
    {
        $path = env('DB_SQLITE_PATH', dirname(__DIR__) . '/database/attendance.sqlite');
        if ($path === null || $path === '') {
            return dirname(__DIR__) . '/database/attendance.sqlite';
        }
        if (!preg_match('~^([A-Za-z]:)?[/\\\\]~', $path)) {
            return dirname(__DIR__) . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        }
        return $path;
    }

    public static function connectSqlite(?string $path = null): PDO
    {
        $path = $path ?? self::defaultSqlitePath();
        if (!is_file($path)) {
            throw new RuntimeException("ملف SQLite غير موجود: {$path}");
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        return $pdo;
    }

    public static function connectMysql(?string $databaseUrl = null): PDO
    {
        if ($databaseUrl !== null && $databaseUrl !== '') {
            $parsed = parseDatabaseUrl($databaseUrl);
            if ($parsed === null) {
                throw new RuntimeException('رابط DATABASE_URL غير صالح.');
            }
            $db = $parsed;
        } else {
            $db = resolveDatabaseConfig();
            if (($db['driver'] ?? '') !== 'mysql') {
                throw new RuntimeException('المستهدف ليس MySQL. عيّن DATABASE_URL.');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            (int) $db['port'],
            $db['name']
        );
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    public static function exportFromSqlite(?string $sqlitePath = null): array
    {
        $pdo = self::connectSqlite($sqlitePath);
        $payload = self::exportFromConnection($pdo);
        $payload['sqlite_path'] = $sqlitePath ?? self::defaultSqlitePath();

        return $payload;
    }

    /** تصدير من أي اتصال PDO (SQLite أو MySQL) */
    public static function exportFromConnection(PDO $pdo): array
    {
        $driver = self::pdoDriver($pdo);
        $payload = [
            'exported_at' => date('c'),
            'source' => $driver,
            'app' => env('APP_NAME', 'REC'),
            'tables' => [],
            'stats' => [],
        ];

        foreach (self::TABLES as $table) {
            if (!self::tableExists($pdo, $driver, $table)) {
                continue;
            }
            $rows = $pdo->query('SELECT * FROM ' . self::quoteIdentifier($table, $driver))->fetchAll();
            $payload['tables'][$table] = $rows;
            $payload['stats'][$table] = count($rows);
        }

        return $payload;
    }

    /** تصدير من قاعدة البيانات الحالية للتطبيق */
    public static function exportCurrent(): array
    {
        require_once dirname(__DIR__) . '/src/Database.php';

        return self::exportFromConnection(Database::getConnection());
    }

    public static function saveExport(array $payload, string $file): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('تعذّر تحويل البيانات إلى JSON.');
        }
        if (file_put_contents($file, $json) === false) {
            throw new RuntimeException("تعذّر كتابة الملف: {$file}");
        }
    }

    public static function loadExport(string $file): array
    {
        if (!is_file($file)) {
            throw new RuntimeException("ملف التصدير غير موجود: {$file}");
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['tables']) || !is_array($data['tables'])) {
            throw new RuntimeException('ملف التصدير غير صالح.');
        }
        return $data;
    }

    public static function importToMysql(array $payload, ?string $databaseUrl = null, bool $replace = true): array
    {
        if ($databaseUrl !== null && $databaseUrl !== '') {
            putenv('DATABASE_URL=' . $databaseUrl);
            $_ENV['DATABASE_URL'] = $databaseUrl;
        }

        require_once dirname(__DIR__) . '/src/RoleHelper.php';
        require_once dirname(__DIR__) . '/src/Database.php';
        require_once dirname(__DIR__) . '/src/PermissionService.php';
        require_once dirname(__DIR__) . '/src/MigrationRunner.php';

        Database::resetConnection();
        MigrationRunner::ensureLatest();

        $pdo = self::connectMysql($databaseUrl);

        return self::importToConnection($pdo, $payload, $replace);
    }

    /** استيراد إلى قاعدة البيانات الحالية للتطبيق */
    public static function importCurrent(array $payload, bool $replace = true): array
    {
        require_once dirname(__DIR__) . '/src/Database.php';
        require_once dirname(__DIR__) . '/src/MigrationRunner.php';

        Database::resetConnection();
        MigrationRunner::ensureLatest();

        $pdo = Database::getConnection();
        $summary = self::importToConnection($pdo, $payload, $replace);
        Database::resetConnection();

        return $summary;
    }

    public static function importToConnection(PDO $pdo, array $payload, bool $replace = true): array
    {
        $driver = self::pdoDriver($pdo);
        $summary = ['imported' => [], 'skipped' => []];
        $tablesToReset = [];

        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } else {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }

        try {
            $pdo->beginTransaction();

            if ($replace) {
                foreach (array_reverse(self::TABLES) as $table) {
                    if (!self::tableExists($pdo, $driver, $table)) {
                        continue;
                    }
                    $pdo->exec('DELETE FROM ' . self::quoteIdentifier($table, $driver));
                }
            }

            foreach (self::TABLES as $table) {
                $rows = $payload['tables'][$table] ?? [];
                if ($rows === [] || !self::tableExists($pdo, $driver, $table)) {
                    $summary['skipped'][$table] = count($rows);
                    continue;
                }

                $columns = self::tableColumns($pdo, $driver, $table);
                $imported = 0;
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $data = array_intersect_key($row, array_flip($columns));
                    if ($data === []) {
                        continue;
                    }
                    $fields = array_keys($data);
                    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                    $sql = sprintf(
                        'INSERT INTO %s (%s) VALUES (%s)',
                        self::quoteIdentifier($table, $driver),
                        implode(', ', array_map(fn ($f) => self::quoteIdentifier($f, $driver), $fields)),
                        $placeholders
                    );
                    $pdo->prepare($sql)->execute(array_values($data));
                    $imported++;
                }

                if ($imported > 0) {
                    $tablesToReset[] = $table;
                }
                $summary['imported'][$table] = $imported;
            }

            self::safeCommit($pdo);

            // MySQL: ALTER TABLE (AUTO_INCREMENT) ينهي المعاملة — يُنفَّذ بعد commit
            foreach ($tablesToReset as $table) {
                self::resetSequence($pdo, $driver, $table);
            }
        } catch (Throwable $e) {
            self::safeRollBack($pdo);
            throw $e;
        } finally {
            if ($driver === 'mysql') {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } else {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
        }

        return $summary;
    }

    private static function safeCommit(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    private static function safeRollBack(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    public static function driverLabel(?PDO $pdo = null): string
    {
        require_once dirname(__DIR__) . '/src/Database.php';
        $pdo = $pdo ?? Database::getConnection();
        $driver = self::pdoDriver($pdo);

        if ($driver === 'sqlite') {
            $cfg = app_config()['db'] ?? [];

            return 'SQLite — ' . ($cfg['sqlite_path'] ?? 'محلي');
        }

        $cfg = app_config()['db'] ?? [];

        return 'MySQL — ' . ($cfg['host'] ?? '?') . ' / ' . ($cfg['name'] ?? '?');
    }

    private static function pdoDriver(PDO $pdo): string
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return $driver === 'sqlite' ? 'sqlite' : 'mysql';
    }

    private static function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        return $driver === 'sqlite'
            ? self::sqliteTableExists($pdo, $table)
            : self::mysqlTableExists($pdo, $table);
    }

    private static function tableColumns(PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(' . self::quoteIdentifier($table, 'sqlite') . ')');

            return array_column($stmt->fetchAll(), 'name');
        }

        return self::mysqlColumns($pdo, $table);
    }

    private static function resetSequence(PDO $pdo, string $driver, string $table): void
    {
        if ($driver === 'mysql') {
            self::resetAutoIncrement($pdo, $table);

            return;
        }

        self::resetSqliteSequence($pdo, $table);
    }

    private static function resetSqliteSequence(PDO $pdo, string $table): void
    {
        if (!self::sqliteTableExists($pdo, $table)) {
            return;
        }

        try {
            $quoted = self::quoteIdentifier($table, 'sqlite');
            $max = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM ' . $quoted)->fetchColumn();
            $pdo->exec('DELETE FROM sqlite_sequence WHERE name = ' . $pdo->quote($table));
            if ($max > 0) {
                $stmt = $pdo->prepare('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)');
                $stmt->execute([$table, $max]);
            }
        } catch (Throwable) {
            // بعض الجداول لا تستخدم AUTOINCREMENT
        }
    }

    private static function sqliteTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function mysqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function mysqlColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM ' . self::quoteIdentifier($table, 'mysql'));
        return array_column($stmt->fetchAll(), 'Field');
    }

    private static function resetAutoIncrement(PDO $pdo, string $table): void
    {
        $stmt = $pdo->prepare(
            'SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND extra LIKE "%auto_increment%"'
        );
        $stmt->execute([$table]);
        $col = $stmt->fetchColumn();
        if (!$col) {
            return;
        }
        $max = (int) $pdo->query(
            'SELECT COALESCE(MAX(' . self::quoteIdentifier((string) $col, 'mysql') . '), 0) FROM '
            . self::quoteIdentifier($table, 'mysql')
        )->fetchColumn();
        $next = $max + 1;
        $pdo->exec('ALTER TABLE ' . self::quoteIdentifier($table, 'mysql') . " AUTO_INCREMENT = {$next}");
    }

    private static function quoteIdentifier(string $name, string $driver): string
    {
        if ($driver === 'sqlite') {
            return '"' . str_replace('"', '""', $name) . '"';
        }
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
