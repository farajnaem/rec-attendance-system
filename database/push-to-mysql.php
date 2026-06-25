<?php

declare(strict_types=1);

/**
 * تصدير من SQLite المحلي واستيراد مباشرة إلى MySQL.
 *
 *   php database/push-to-mysql.php --database-url="mysql://user:pass@host:3306/default" --force
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/config/config.php';
require __DIR__ . '/DataSync.php';

$dbUrl = env('DATABASE_URL') ?? env('MYSQL_URL') ?? env('DB_URL');
$sqlite = null;
$force = false;
$saveCopy = __DIR__ . '/export.json';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--database-url=')) {
        $dbUrl = substr($arg, 15);
    } elseif (str_starts_with($arg, '--sqlite=')) {
        $sqlite = substr($arg, 9);
    } elseif (str_starts_with($arg, '--save=')) {
        $saveCopy = substr($arg, 7);
    } elseif ($arg === '--force') {
        $force = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php database/push-to-mysql.php --database-url=mysql://... --force\n";
        exit(0);
    }
}

if (!$force) {
    fwrite(STDERR, "أضف --force لتأكيد رفع البيانات إلى الإنتاج.\n");
    exit(1);
}

if ($dbUrl === null || $dbUrl === '') {
    fwrite(STDERR, "عيّن DATABASE_URL أو --database-url\n");
    exit(1);
}

try {
    $payload = DataSync::exportFromSqlite($sqlite);
    DataSync::saveExport($payload, $saveCopy);
    echo "نسخة احتياطية: {$saveCopy}\n";

    $summary = DataSync::importToMysql($payload, $dbUrl, true);
    echo "تم الرفع إلى MySQL.\n";
    foreach ($summary['imported'] as $table => $count) {
        echo sprintf("  %-30s %d\n", $table, $count);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'خطأ: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
