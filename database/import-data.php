<?php

declare(strict_types=1);

/**
 * استيراد بيانات JSON إلى MySQL (Coolify).
 *
 * الاستخدام:
 *   php database/import-data.php --file=database/export.json --database-url="mysql://..." --force
 *
 * أو عيّن DATABASE_URL في البيئة:
 *   set DATABASE_URL=mysql://...
 *   php database/import-data.php --file=database/export.json --force
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/config/config.php';
require __DIR__ . '/DataSync.php';

$file = __DIR__ . '/export.json';
$dbUrl = env('DATABASE_URL') ?? env('MYSQL_URL') ?? env('DB_URL');
$force = false;
$merge = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $file = substr($arg, 7);
    } elseif (str_starts_with($arg, '--database-url=')) {
        $dbUrl = substr($arg, 15);
    } elseif ($arg === '--force') {
        $force = true;
    } elseif ($arg === '--merge') {
        $merge = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php database/import-data.php --file=export.json [--database-url=...] --force\n";
        echo "  --force   مطلوب: يستبدل بيانات MySQL الحالية\n";
        echo "  --merge   إضافة دون حذف (افتراضي مع --force: استبدال كامل)\n";
        exit(0);
    }
}

if (!$force) {
    fwrite(STDERR, "للأمان: أضف --force لتأكيد استبدال بيانات الإنتاج.\n");
    exit(1);
}

if ($dbUrl === null || $dbUrl === '') {
    fwrite(STDERR, "عيّن DATABASE_URL أو استخدم --database-url=mysql://...\n");
    exit(1);
}

try {
    $payload = DataSync::loadExport($file);
    $host = parse_url($dbUrl, PHP_URL_HOST) ?: '?';
    echo "استيراد إلى MySQL: {$host}\n";
    echo "الملف: {$file}\n";
    echo 'الوضع: ' . ($merge ? 'دمج' : 'استبدال كامل') . "\n\n";

    $summary = DataSync::importToMysql($payload, $dbUrl, !$merge);

    echo "تم الاستيراد بنجاح.\n\n";
    foreach ($summary['imported'] as $table => $count) {
        echo sprintf("  %-30s %d صف\n", $table, $count);
    }
    if ($summary['skipped'] !== []) {
        echo "\nتخطّي:\n";
        foreach ($summary['skipped'] as $table => $count) {
            echo sprintf("  %-30s %d\n", $table, $count);
        }
    }
    echo "\nافتح الموقع وسجّل دخولاً للتحقق.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'خطأ: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
