<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
rec_load_core();

$failures = 0;

function assertTrue(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        $failures++;
        return;
    }
    echo "OK: {$message}\n";
}

assertTrue(LeaveHelper::isValid('sick'), 'LeaveHelper accepts sick leave');
assertTrue(!LeaveHelper::isValid('invalid'), 'LeaveHelper rejects invalid leave');
assertTrue(LeaveHelper::label('regular') === 'إجازة عادية', 'LeaveHelper label');

$page = paginate(range(1, 45), 2, 20);
assertTrue($page['page'] === 2, 'paginate page number');
assertTrue(count($page['items']) === 20, 'paginate items count');
assertTrue($page['pages'] === 3, 'paginate total pages');

$late = WorkScheduleService::isLateCheckIn('2026-06-25 09:00:00');
assertTrue(is_bool($late), 'WorkScheduleService isLateCheckIn returns bool');

exit($failures > 0 ? 1 : 0);
