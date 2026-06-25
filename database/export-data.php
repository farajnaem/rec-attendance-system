<?php

declare(strict_types=1);

/**
 * تصدير بيانات SQLite المحلية إلى ملف JSON للمراجعة والتعديل.
 *
 * الاستخدام:
 *   php database/export-data.php
 *   php database/export-data.php --out=database/my-export.json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/config/config.php';
require __DIR__ . '/DataSync.php';

$out = DataSync::defaultSqlitePath();
$outFile = __DIR__ . '/export.json';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--sqlite=')) {
        $out = substr($arg, 9);
    } elseif (str_starts_with($arg, '--out=')) {
        $outFile = substr($arg, 6);
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php database/export-data.php [--sqlite=path] [--out=file.json]\n";
        exit(0);
    }
}

try {
    $payload = DataSync::exportFromSqlite($out);
    DataSync::saveExport($payload, $outFile);

    echo "تم التصدير إلى: {$outFile}\n";
    echo "المصدر: {$payload['sqlite_path']}\n";
    echo "التاريخ: {$payload['exported_at']}\n\n";
    foreach ($payload['stats'] as $table => $count) {
        echo sprintf("  %-30s %d\n", $table, $count);
    }
    echo "\nيمكنك تعديل الملف ثم استيراده إلى MySQL:\n";
    echo "  php database/import-data.php --file={$outFile} --database-url=\"mysql://...\" --force\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'خطأ: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
